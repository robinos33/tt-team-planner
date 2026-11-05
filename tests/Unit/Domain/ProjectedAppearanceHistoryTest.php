<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit\Domain;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TT\TeamPlanner\Domain\AppearanceHistory;
use TT\TeamPlanner\Domain\BurnageChecker;
use TT\TeamPlanner\Domain\MatchAppearance;
use TT\TeamPlanner\Domain\Player;
use TT\TeamPlanner\Domain\ProjectedAppearanceHistory;
use TT\TeamPlanner\Domain\TeamComposition;
use TT\TeamPlanner\Repository\PlayerRepository;
use TT\TeamPlanner\Repository\TeamCompositionRepository;
use TT\TeamPlanner\Repository\TeamForfeitRepository;
use TT\TeamPlanner\Repository\ValidatedRoundRepository;

#[CoversClass(ProjectedAppearanceHistory::class)]
final class ProjectedAppearanceHistoryTest extends TestCase
{
    private const SEASON = '2026-2027';

    /** @var list<MatchAppearance> */
    private array $appearances = [];

    /** @var list<TeamComposition> */
    private array $compositions = [];

    /** @var list<array{int, int, string}> phase, journée, équipe */
    private array $validated = [];

    /** @var list<array{int, int, string}> phase, journée, équipe */
    private array $forfeits = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('__')->returnArg();
        Functions\when('get_option')->justReturn([]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testCountsThePlannedCompositionOfAnEarlierRoundAsPlayed(): void
    {
        $this->played(playerId: 1, round: 1, team: 'T1');
        $this->composed(round: 2, team: 'T2', playerIds: [1]);

        $appearances = $this->history(untilRound: 3)->findByPlayerAndPhase(self::SEASON, 1, 1);

        self::assertSame([[1, 'T1', 1], [2, 'T2', 2]], $this->summary($appearances));
    }

    public function testListsPlannedAppearancesOfARoundAlongsideThePlayedOnes(): void
    {
        $this->played(playerId: 1, round: 1, team: 'T1');
        $this->composed(round: 1, team: 'T2', playerIds: [2, 3]);

        $appearances = $this->history(untilRound: 2)->findByPhaseAndRound(self::SEASON, 1, 1);

        self::assertSame([[1, 'T1', 1], [1, 'T2', 2], [1, 'T2', 2]], $this->summary($appearances));
    }

    public function testValidatedTeamKeepsItsRecordedAppearancesOnly(): void
    {
        // La feuille dit 1 et 4 ; la composition, restée telle quelle, disait 1 et 2.
        $this->played(playerId: 1, round: 1, team: 'T1');
        $this->played(playerId: 4, round: 1, team: 'T1');
        $this->composed(round: 1, team: 'T1', playerIds: [1, 2]);
        $this->validated[] = [1, 1, 'T1'];

        $appearances = $this->history(untilRound: 2)->findByPhaseAndRound(self::SEASON, 1, 1);

        self::assertSame([1, 4], array_map(static fn (MatchAppearance $a): int => $a->playerId, $appearances));
    }

    public function testForfeitTeamProjectsNoAppearance(): void
    {
        $this->composed(round: 1, team: 'T1', playerIds: [1]);
        $this->forfeits[] = [1, 1, 'T1'];

        self::assertSame([], $this->history(untilRound: 2)->findByPlayerAndPhase(self::SEASON, 1, 1));
    }

    public function testDoesNotProjectTheStudiedRoundNorTheFollowingOnes(): void
    {
        $this->composed(round: 2, team: 'T1', playerIds: [1]);
        $this->composed(round: 3, team: 'T1', playerIds: [1]);

        $history = $this->history(untilRound: 2);

        self::assertSame([], $history->findByPlayerAndPhase(self::SEASON, 1, 1));
        self::assertSame([], $history->findByPhaseAndRound(self::SEASON, 1, 2));
    }

    public function testAFarAwayRoundOnlyWalksTheComposedRounds(): void
    {
        // Une journée démesurée ne doit pas faire boucler sur chaque numéro de journée.
        $this->composed(round: 1, team: 'T1', playerIds: [1]);

        $appearances = $this->history(untilRound: PHP_INT_MAX)->findByPlayerAndPhase(self::SEASON, 1, 1);

        self::assertSame([[1, 'T1', 1]], $this->summary($appearances));
    }

    public function testDoesNotProjectAnotherPhase(): void
    {
        $this->composed(round: 1, team: 'T1', playerIds: [1], phase: 2);

        $history = $this->history(untilRound: 3);

        self::assertSame([], $history->findByPlayerAndPhase(self::SEASON, 2, 1));
        self::assertSame([], $history->findByPhaseAndRound(self::SEASON, 2, 1));
    }

    // --- Avec les règles de brûlage ---------------------------------------

    public function testRule2BurnsOnARoundStillToBePlayed(): void
    {
        $this->played(playerId: 1, round: 1, team: 'T1');
        $this->composed(round: 2, team: 'T1', playerIds: [1]);

        self::assertFalse($this->checker(projected: false, untilRound: 3)->statusFor(self::SEASON, 1, 3, 'T2', 1)->burned);
        self::assertSame('rule2', $this->checker(projected: true, untilRound: 3)->statusFor(self::SEASON, 1, 3, 'T2', 1)->reason);
    }

    public function testRule3AppliesBeforeTheFirstRoundIsPlayed(): void
    {
        $this->composed(round: 1, team: 'T1', playerIds: [1, 2]);
        $this->composed(round: 2, team: 'T2', playerIds: [1, 2]);

        self::assertFalse($this->checker(projected: false, untilRound: 2)->statusFor(self::SEASON, 1, 2, 'T2', 2)->burned);
        self::assertSame('rule3', $this->checker(projected: true, untilRound: 2)->statusFor(self::SEASON, 1, 2, 'T2', 2)->reason);
    }

    // --- Mise en place ----------------------------------------------------

    /**
     * @param MatchAppearance[] $appearances
     * @return list<array{int, string, int}> journée, équipe, rang
     */
    private function summary(array $appearances): array
    {
        return array_map(
            static fn (MatchAppearance $a): array => [$a->round, $a->teamCode, $a->teamRank],
            array_values($appearances)
        );
    }

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

    private function history(int $untilRound, int $phase = 1): ProjectedAppearanceHistory
    {
        return new ProjectedAppearanceHistory(
            $this->playedHistory(),
            $this->compositions(),
            $this->validations(),
            $this->forfeits(),
            self::SEASON,
            $phase,
            $untilRound,
        );
    }

    private function checker(bool $projected, int $untilRound): BurnageChecker
    {
        $players = new class () extends PlayerRepository {
            public function __construct() {}

            public function findById(int $id): ?Player
            {
                return null;
            }
        };

        return new BurnageChecker(
            $projected ? $this->history($untilRound) : $this->playedHistory(),
            $this->compositions(),
            $players,
            $this->forfeits(),
        );
    }

    /**
     * Doublures en mémoire : chaque repository surcharge le constructeur (qui
     * lit $wpdb) et les seules méthodes interrogées ici.
     */
    private function playedHistory(): AppearanceHistory
    {
        return new class ($this->appearances) implements AppearanceHistory {
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
    }

    private function compositions(): TeamCompositionRepository
    {
        return new class ($this->compositions) extends TeamCompositionRepository {
            /** @param list<TeamComposition> $rows */
            public function __construct(private readonly array $rows) {}

            public function findByPhase(string $season, int $phase): array
            {
                return array_values(array_filter(
                    $this->rows,
                    static fn (TeamComposition $c): bool => $c->season === $season && $c->phase === $phase
                ));
            }

            public function findByTeamAndRound(string $season, int $phase, int $round, string $teamCode): array
            {
                return array_values(array_filter(
                    $this->findByPhase($season, $phase),
                    static fn (TeamComposition $c): bool => $c->round === $round && $c->teamCode === $teamCode
                ));
            }
        };
    }

    private function validations(): ValidatedRoundRepository
    {
        return new class ($this->validated) extends ValidatedRoundRepository {
            /** @param list<array{int, int, string}> $rows */
            public function __construct(private readonly array $rows) {}

            public function isValidated(string $season, int $phase, int $round, string $teamCode): bool
            {
                return in_array([$phase, $round, $teamCode], $this->rows, true);
            }
        };
    }

    private function forfeits(): TeamForfeitRepository
    {
        return new class ($this->forfeits) extends TeamForfeitRepository {
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
    }
}
