<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit\Domain;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TT\TeamPlanner\Domain\BurnageChecker;
use TT\TeamPlanner\Domain\BurnageStatus;
use TT\TeamPlanner\Domain\MatchAppearance;
use TT\TeamPlanner\Domain\Player;
use TT\TeamPlanner\Domain\TeamComposition;
use TT\TeamPlanner\Repository\MatchAppearanceRepository;
use TT\TeamPlanner\Repository\PlayerRepository;
use TT\TeamPlanner\Repository\TeamCompositionRepository;
use TT\TeamPlanner\Repository\TeamForfeitRepository;

#[CoversClass(BurnageChecker::class)]
final class BurnageCheckerTest extends TestCase
{
    private const SEASON = '2026-2027';

    /** @var list<MatchAppearance> */
    private array $appearances = [];

    /** @var list<TeamComposition> */
    private array $compositions = [];

    /** @var array<int, Player> */
    private array $players = [];

    /** @var list<array{int, int, string}> phase, journée, équipe forfait */
    private array $forfeits = [];

    /** @var list<array{code: string, level: string}> option ttp_teams */
    private array $teams = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('__')->returnArg();
        Functions\when('remove_accents')->alias(
            static fn (string $text): string => strtr($text, ['é' => 'e', 'è' => 'e', 'É' => 'E'])
        );
        Functions\when('get_option')->alias(
            fn (string $name, mixed $default = false): mixed => $name === 'ttp_teams' ? $this->teams : $default
        );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // --- Règle 2 : 2 rencontres dans une équipe de numéro N ---------------

    public function testRule2BurnsPlayerAfterTwoMatchesInALowerNumberedTeam(): void
    {
        $this->played(playerId: 1, round: 1, team: 'T1');
        $this->played(playerId: 1, round: 2, team: 'T1');

        $status = $this->statusOf(round: 3, team: 'T2', playerId: 1);

        self::assertTrue($status->burned);
        self::assertSame('rule2', $status->reason);
    }

    public function testRule2LetsPlayerStayInTheTeamHeIsBurnedIn(): void
    {
        $this->played(playerId: 1, round: 1, team: 'T1');
        $this->played(playerId: 1, round: 2, team: 'T1');

        self::assertFalse($this->statusOf(round: 3, team: 'T1', playerId: 1)->burned);
    }

    public function testRule2CeilingIsTheSecondBestRankPlayed(): void
    {
        $this->played(playerId: 1, round: 1, team: 'T1');
        $this->played(playerId: 1, round: 2, team: 'T3');

        self::assertFalse($this->statusOf(round: 3, team: 'T3', playerId: 1)->burned);
        self::assertSame('rule2', $this->statusOf(round: 3, team: 'T4', playerId: 1)->reason);
    }

    public function testRule2IgnoresASingleMatch(): void
    {
        $this->played(playerId: 1, round: 1, team: 'T1');

        self::assertFalse($this->statusOf(round: 2, team: 'T4', playerId: 1)->burned);
    }

    public function testRule2CountsRoundsNotAppearances(): void
    {
        // Deux lignes pour la même journée ne font qu'une rencontre disputée.
        $this->played(playerId: 1, round: 1, team: 'T1');
        $this->played(playerId: 1, round: 1, team: 'T2');

        self::assertFalse($this->statusOf(round: 2, team: 'T3', playerId: 1)->burned);
    }

    // --- Règle 3 : un seul joueur « monté » de J1 en J2 --------------------

    public function testRule3BurnsTheSecondPlayerComingFromALowerNumberedTeam(): void
    {
        $this->played(playerId: 1, round: 1, team: 'T1');
        $this->played(playerId: 2, round: 1, team: 'T1');
        $this->composed(round: 2, team: 'T2', playerIds: [1, 2]);

        $status = $this->statusOf(round: 2, team: 'T2', playerId: 2);

        self::assertTrue($status->burned);
        self::assertSame('rule3', $status->reason);
    }

    public function testRule3AllowsASinglePlayerComingFromALowerNumberedTeam(): void
    {
        $this->played(playerId: 1, round: 1, team: 'T1');
        $this->played(playerId: 2, round: 1, team: 'T2');
        $this->composed(round: 2, team: 'T2', playerIds: [1, 2]);

        self::assertFalse($this->statusOf(round: 2, team: 'T2', playerId: 1)->burned);
    }

    public function testRule3OnlyAppliesToTheSecondRound(): void
    {
        $this->played(playerId: 1, round: 2, team: 'T1');
        $this->played(playerId: 2, round: 2, team: 'T1');
        $this->composed(round: 3, team: 'T2', playerIds: [1, 2]);

        self::assertFalse($this->statusOf(round: 3, team: 'T2', playerId: 2)->burned);
    }

    // --- Forfaits (FFTT II.112.2) ------------------------------------------

    public function testForfeitInFirstRoundBarsPlayersWhoPlayedRoundOneElsewhere(): void
    {
        $this->forfeit(round: 1, team: 'T2');
        $this->played(playerId: 1, round: 1, team: 'T3');

        $status = $this->statusOf(round: 2, team: 'T2', playerId: 1);

        self::assertTrue($status->burned);
        self::assertSame('forfeit', $status->reason);
    }

    public function testForfeitInFirstRoundAcceptsPlayersWhoDidNotPlay(): void
    {
        $this->forfeit(round: 1, team: 'T2');
        $this->played(playerId: 1, round: 1, team: 'T3');

        self::assertFalse($this->statusOf(round: 2, team: 'T2', playerId: 2)->burned);
    }

    public function testForfeitBarsPreviousRoundPlayersFromHigherNumberedTeams(): void
    {
        $this->forfeit(round: 4, team: 'T1');
        $this->played(playerId: 1, round: 3, team: 'T1');

        $status = $this->statusOf(round: 4, team: 'T2', playerId: 1);

        self::assertTrue($status->burned);
        self::assertSame('forfeit', $status->reason);
    }

    public function testForfeitDoesNotAffectPlayersOfAnotherTeam(): void
    {
        $this->forfeit(round: 4, team: 'T1');
        $this->played(playerId: 1, round: 3, team: 'T3');

        self::assertFalse($this->statusOf(round: 4, team: 'T4', playerId: 1)->burned);
    }

    // --- Points minimum (LNATT 4.1, FFTT II.112.4) -------------------------

    public function testMinimumPointsBurnsAPlayerBelowTheDivisionThreshold(): void
    {
        $this->teams = [['code' => 'T1', 'level' => 'Pré-Nationale']];
        $this->player(1, points: 1100);

        $status = $this->statusOf(round: 1, team: 'T1', playerId: 1);

        self::assertTrue($status->burned);
        self::assertSame('min_points', $status->reason);
    }

    public function testMinimumPointsAcceptsAPlayerAboveTheThreshold(): void
    {
        $this->teams = [['code' => 'T1', 'level' => 'Pré-Nationale']];
        $this->player(1, points: 1250);

        self::assertFalse($this->statusOf(round: 1, team: 'T1', playerId: 1)->burned);
    }

    public function testMinimumPointsIgnoresPointsFromAnotherSeason(): void
    {
        $this->teams = [['code' => 'T1', 'level' => 'Pré-Nationale']];
        $this->player(1, points: 900, season: '2025-2026');

        self::assertFalse($this->statusOf(round: 1, team: 'T1', playerId: 1)->burned);
    }

    public function testMinimumPointsKeepsPhaseOneRegularsQualifiedInPhaseTwo(): void
    {
        $this->teams = [['code' => 'T1', 'level' => 'Régionale 1']];
        $this->player(1, points: 900);
        foreach ([1, 2, 3] as $round) {
            $this->played(playerId: 1, round: $round, team: 'T1', phase: 1);
        }

        self::assertFalse($this->statusOf(round: 1, team: 'T1', playerId: 1, phase: 2)->burned);
    }

    public function testMinimumPointsRequiresThreePhaseOneMatchesWithTheTeam(): void
    {
        $this->teams = [['code' => 'T1', 'level' => 'Régionale 1']];
        $this->player(1, points: 900);
        $this->played(playerId: 1, round: 1, team: 'T1', phase: 1);
        $this->played(playerId: 1, round: 2, team: 'T1', phase: 1);

        self::assertSame('min_points', $this->statusOf(round: 1, team: 'T1', playerId: 1, phase: 2)->reason);
    }

    // --- Étrangers (FFTT II.609) ------------------------------------------

    public function testForeignLimitBurnsASecondForeignPlayer(): void
    {
        $this->player(1, foreign: true);
        $this->player(2, foreign: true);
        $this->composed(round: 1, team: 'T1', playerIds: [1, 2]);

        $status = $this->statusOf(round: 1, team: 'T1', playerId: 2);

        self::assertTrue($status->burned);
        self::assertSame('foreign', $status->reason);
    }

    public function testForeignLimitAcceptsASingleForeignPlayer(): void
    {
        $this->player(1, foreign: true);
        $this->player(2);
        $this->composed(round: 1, team: 'T1', playerIds: [1, 2]);

        self::assertFalse($this->statusOf(round: 1, team: 'T1', playerId: 1)->burned);
    }

    // --- Lecture des codes et niveaux --------------------------------------

    public function testBurnageRulesAreSkippedForATeamCodeWithoutNumber(): void
    {
        $this->played(playerId: 1, round: 1, team: 'T1');
        $this->played(playerId: 1, round: 2, team: 'T1');

        self::assertFalse($this->statusOf(round: 3, team: 'Vétérans', playerId: 1)->burned);
    }

    #[DataProvider('levels')]
    public function testMinimumPointsForLevel(string $level, ?int $expected): void
    {
        self::assertSame($expected, BurnageChecker::minimumPointsForLevel($level));
    }

    /** @return iterable<string, array{string, ?int}> */
    public static function levels(): iterable
    {
        yield 'abréviation PN' => ['PN', 1200];
        yield 'libellé Pré-Nationale' => ['Pré-Nationale', 1200];
        yield 'abréviation R1' => ['r1', 1000];
        yield 'libellé Régionale 1' => [' Régionale 1 ', 1000];
        yield 'Régionale 2 sans seuil' => ['Régionale 2', null];
        yield 'niveau vide' => ['', null];
    }

    public function testExtractTeamRank(): void
    {
        self::assertSame(1, BurnageChecker::extractTeamRank('T1'));
        self::assertSame(12, BurnageChecker::extractTeamRank('T12'));
        self::assertNull(BurnageChecker::extractTeamRank('Vétérans'));
    }

    // --- Mise en place ----------------------------------------------------

    private function played(int $playerId, int $round, string $team, int $phase = 1): void
    {
        $this->appearances[] = new MatchAppearance(
            id: count($this->appearances) + 1,
            season: self::SEASON,
            phase: $phase,
            round: $round,
            teamCode: $team,
            teamRank: (int) BurnageChecker::extractTeamRank($team),
            playerId: $playerId,
            slotNumber: 1,
            validatedAt: '2026-10-01 20:00:00',
            validatedBy: null,
        );
    }

    /** @param list<int> $playerIds */
    private function composed(int $round, string $team, array $playerIds, int $phase = 1): void
    {
        foreach ($playerIds as $index => $playerId) {
            $this->compositions[] = new TeamComposition(
                id: count($this->compositions) + 1,
                season: self::SEASON,
                phase: $phase,
                round: $round,
                teamCode: $team,
                slotNumber: $index + 1,
                playerId: $playerId,
            );
        }
    }

    private function forfeit(int $round, string $team, int $phase = 1): void
    {
        $this->forfeits[] = [$phase, $round, $team];
    }

    private function player(int $id, int $points = 0, string $season = self::SEASON, bool $foreign = false): void
    {
        $this->players[$id] = new Player(
            id: $id,
            externalId: (string) $id,
            firstName: 'Joueur',
            lastName: (string) $id,
            licenseNumber: sprintf('33%05d', $id),
            phone: '',
            email: '',
            ranking: $points,
            usualTeam: '',
            isForeign: $foreign,
            isYoung: false,
            isMutation: false,
            notes: '',
            syncedAt: null,
            bestOfficialPoints: $points,
            bestOfficialSeason: $season,
        );
    }

    private function statusOf(int $round, string $team, int $playerId, int $phase = 1): BurnageStatus
    {
        return $this->checker()->statusFor(self::SEASON, $phase, $round, $team, $playerId);
    }

    /**
     * Repositories en mémoire : chaque doublure surcharge le constructeur
     * (qui lit $wpdb) et les seules méthodes interrogées par BurnageChecker.
     */
    private function checker(): BurnageChecker
    {
        $appearances = new class ($this->appearances) extends MatchAppearanceRepository {
            /** @param list<MatchAppearance> $rows */
            public function __construct(private readonly array $rows) {}

            public function findByPlayerAndPhase(string $season, int $phase, int $playerId): array
            {
                return array_values(array_filter(
                    $this->rows,
                    static fn (MatchAppearance $a): bool => $a->season === $season && $a->phase === $phase && $a->playerId === $playerId
                ));
            }

            public function findByPhaseAndRound(string $season, int $phase, int $round): array
            {
                return array_values(array_filter(
                    $this->rows,
                    static fn (MatchAppearance $a): bool => $a->season === $season && $a->phase === $phase && $a->round === $round
                ));
            }
        };

        $compositions = new class ($this->compositions) extends TeamCompositionRepository {
            /** @param list<TeamComposition> $rows */
            public function __construct(private readonly array $rows) {}

            public function findByTeamAndRound(string $season, int $phase, int $round, string $teamCode): array
            {
                return array_values(array_filter(
                    $this->rows,
                    static fn (TeamComposition $c): bool => $c->season === $season && $c->phase === $phase
                        && $c->round === $round && $c->teamCode === $teamCode
                ));
            }
        };

        $players = new class ($this->players) extends PlayerRepository {
            /** @param array<int, Player> $rows */
            public function __construct(private readonly array $rows) {}

            public function findById(int $id): ?Player
            {
                return $this->rows[$id] ?? null;
            }
        };

        $forfeits = new class ($this->forfeits) extends TeamForfeitRepository {
            /** @param list<array{int, int, string}> $rows */
            public function __construct(private readonly array $rows) {}

            public function isForfeit(string $season, int $phase, int $round, string $teamCode): bool
            {
                return in_array([$phase, $round, $teamCode], $this->rows, true);
            }

            public function teamsForRound(string $season, int $phase, int $round): array
            {
                $teams = [];
                foreach ($this->rows as [$forfeitPhase, $forfeitRound, $team]) {
                    if ($forfeitPhase === $phase && $forfeitRound === $round) {
                        $teams[] = $team;
                    }
                }

                return $teams;
            }
        };

        return new BurnageChecker($appearances, $compositions, $players, $forfeits);
    }
}
