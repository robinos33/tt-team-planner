<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit\Audit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TT\TeamPlanner\Audit\AuditDescriber;
use TT\TeamPlanner\Audit\AuditLookup;
use TT\TeamPlanner\Rest\AuditLogController;
use TT\TeamPlanner\Rest\AvailabilityController;
use TT\TeamPlanner\Rest\MagicLinkController;
use TT\TeamPlanner\Rest\ManifestController;
use TT\TeamPlanner\Rest\MatchAppearanceController;
use TT\TeamPlanner\Rest\PhaseSquadController;
use TT\TeamPlanner\Rest\PlayersController;
use TT\TeamPlanner\Rest\SeasonController;
use TT\TeamPlanner\Rest\SmsTemplatesController;
use TT\TeamPlanner\Rest\SyncController;
use TT\TeamPlanner\Rest\TeamsController;

#[CoversClass(AuditDescriber::class)]
final class AuditDescriberTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const ROUND = ['season' => '2026-2027', 'phase' => 1, 'round' => 3, 'team_code' => 'E1'];

    private AuditDescriber $describer;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $lookup = Mockery::mock(AuditLookup::class);
        $lookup->allows('playerName')->andReturnUsing(static fn (int $id): string => [1 => 'Léa DURAND', 2 => 'Hugo PETIT'][$id] ?? "joueur n° $id");
        $lookup->allows('teamName')->andReturnUsing(static fn (string $code): string => $code === 'E1' ? 'Équipe 1' : $code);
        $this->describer = new AuditDescriber($lookup);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /** Une route d'écriture absente de OPERATIONS ne serait jamais tracée. */
    public function testChaqueRouteDEcritureEstConnueDuJournal(): void
    {
        $operations = [];
        Functions\when('register_rest_route')->alias(static function (string $ns, string $route, array $args) use (&$operations): void {
            foreach (isset($args['methods']) ? [$args] : $args as $endpoint) {
                if ((array) $endpoint['methods'] !== ['GET']) {
                    $class        = get_class($endpoint['callback'][0]);
                    $operations[] = substr($class, (int) strrpos($class, '\\') + 1) . '::' . $endpoint['callback'][1];
                }
            }
        });

        foreach ([
            AuditLogController::class, AvailabilityController::class, MagicLinkController::class,
            ManifestController::class, MatchAppearanceController::class, PhaseSquadController::class,
            PlayersController::class, SeasonController::class, SmsTemplatesController::class,
            SyncController::class, TeamsController::class,
        ] as $controller) {
            (new $controller())->registerRoutes();
        }

        $expected = AuditDescriber::OPERATIONS;
        sort($expected);
        sort($operations);
        self::assertSame($expected, $operations);
    }

    public function testPlacerUnJoueurDansUnSlotVide(): void
    {
        $entry = $this->describer->describe('TeamsController::setSlot', self::ROUND + ['slot_number' => 2, 'player_id' => 2], ['player_id' => null], []);

        self::assertSame('a placé Hugo PETIT (Équipe 1, J3 de la phase 1, slot 2)', $entry['summary']);
        self::assertSame(['E1', 1, 3], [$entry['team_code'], $entry['phase'], $entry['round']]);
    }

    public function testRemplacerUnJoueurNommeCeluiQuiPart(): void
    {
        $entry = $this->describer->describe('TeamsController::setSlot', self::ROUND + ['slot_number' => 2, 'player_id' => 2], ['player_id' => 1], []);

        self::assertSame('a remplacé Léa DURAND par Hugo PETIT (Équipe 1, J3 de la phase 1, slot 2)', $entry['summary']);
    }

    public function testRemettreLeMemeJoueurNEstPasTrace(): void
    {
        self::assertNull($this->describer->describe('TeamsController::setSlot', self::ROUND + ['slot_number' => 2, 'player_id' => 1], ['player_id' => 1], []));
    }

    public function testVider(): void
    {
        $entry = $this->describer->describe('TeamsController::clearSlot', self::ROUND + ['slot_number' => 4], ['player_id' => 1], []);
        self::assertSame('a retiré Léa DURAND de la compo (Équipe 1, J3 de la phase 1, slot 4)', $entry['summary']);

        self::assertNull($this->describer->describe('TeamsController::clearSlot', self::ROUND + ['slot_number' => 4], ['player_id' => null], []));
    }

    public function testCapitaineDeLaJournee(): void
    {
        $entry = $this->describer->describe('TeamsController::setCaptain', self::ROUND + ['player_id' => 2], [], []);

        self::assertSame('a nommé Hugo PETIT capitaine (Équipe 1, J3 de la phase 1)', $entry['summary']);
    }

    public function testCapitaineDePhase(): void
    {
        $squad = ['season' => '2026-2027', 'phase' => 1, 'team_code' => 'E1'];

        $entry = $this->describer->describe('PhaseSquadController::setCaptain', $squad + ['player_id' => 1], [], []);
        self::assertSame('a nommé Léa DURAND capitaine de phase (Équipe 1, phase 1)', $entry['summary']);

        $entry = $this->describer->describe('PhaseSquadController::setCaptain', $squad + ['player_id' => 0], [], []);
        self::assertSame('a retiré le capitaine de phase (Équipe 1, phase 1)', $entry['summary']);
    }

    public function testDispoAvecLAncienStatut(): void
    {
        $entry = $this->describer->describe('AvailabilityController::setAvailability', self::ROUND + ['player_id' => 1, 'status' => 'unavailable'], ['status' => 'available'], []);

        self::assertSame('a passé la dispo de Léa DURAND de « Dispo » à « Indispo » pour la J3 de la phase 1', $entry['summary']);
    }

    public function testReponseParLienJoueurAuNomDuJoueur(): void
    {
        $entry = $this->describer->describe(
            'MagicLinkController::setAvailability',
            ['token' => str_repeat('a', 64), 'phase' => 1, 'round' => 3, 'status' => 'available'],
            ['player_id' => 1, 'season' => '2026-2027'],
            []
        );

        self::assertSame(['Léa DURAND', 0, 'lien joueur'], [$entry['actor'], $entry['user_id'], $entry['source']]);
        self::assertSame('a répondu « Dispo » pour la J3 de la phase 1', $entry['summary']);
    }

    public function testContactModifieSansSaValeur(): void
    {
        $entry = $this->describer->describe(
            'PlayersController::updatePlayer',
            ['id' => 1, 'phone' => '06 99 99 99 99', 'email' => 'lea@example.org', 'notes' => 'Blessée'],
            ['contact' => ['phone' => '06 11 11 11 11', 'email' => 'lea@example.org', 'notes' => '']],
            []
        );

        self::assertSame('a modifié le téléphone et la note de Léa DURAND', $entry['summary']);
        self::assertStringNotContainsString('06', $entry['summary']);
        self::assertStringNotContainsString('Blessée', $entry['summary']);
    }

    public function testSuppressionDefinitiveAvecLeNomLuAvant(): void
    {
        $entry = $this->describer->describe('PlayersController::deletePlayerPermanently', ['id' => 9], ['name' => 'Léa DURAND'], []);

        self::assertSame('a supprimé définitivement Léa DURAND et tout son historique', $entry['summary']);
    }

    public function testLienDeDispoEnvoye(): void
    {
        $entry = $this->describer->describe('MagicLinkController::generate', ['player_ids' => [1, 2]], [], ['results' => [
            ['player_id' => 1, 'name' => 'Léa DURAND', 'status' => 'sent'],
            ['player_id' => 2, 'name' => 'Hugo PETIT', 'status' => 'no_email'],
        ]]);

        self::assertSame('a envoyé un lien de dispo à Léa DURAND', $entry['summary']);
    }

    public function testDatesDuBackOffice(): void
    {
        $entry = $this->describer->describeOption(
            'ttp_journee_dates_p1',
            ['2026-09-27', '2026-10-11', '2026-11-07'],
            ['2026-09-27', '2026-10-11', '2026-11-14']
        );

        self::assertSame('a modifié les dates de la phase 1 : J3 07/11 → 14/11', $entry['summary']);
        self::assertNull($this->describer->describeOption('ttp_last_sync', null, 'x'));
    }
}
