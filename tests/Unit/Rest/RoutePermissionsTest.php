<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit\Rest;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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

/**
 * Inventaire des routes de l'API : aucune ne doit être ouverte sans
 * connexion, sauf celles qui n'ont pas de donnée à protéger ou qui
 * s'authentifient autrement.
 */
#[CoversNothing]
final class RoutePermissionsTest extends TestCase
{
    /** Routes publiques assumées : « MÉTHODE /route ». */
    private const PUBLIC_ROUTES = [
        'GET /manifest',                                         // manifeste PWA, sans donnée
        'GET /magic-links/(?P<token>[a-f0-9]{64})',              // le jeton fait office d'authentification
        'POST /magic-links/(?P<token>[a-f0-9]{64})/availability',
    ];

    /** Opérations d'administration, hors du droit de gérer le planner. */
    private const ADMIN_ROUTES = [
        'DELETE /appearances/validate', // dévalide une journée
        'GET /audit-log',               // journal des actions : administrateurs seulement
        'POST /appearances/import',
        'POST /players/sync',
        'POST /season/reset',
    ];

    /** @var array<string, callable> « MÉTHODE /route » => permission_callback */
    private array $routes = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('register_rest_route')->alias(function (string $ns, string $route, array $args): void {
            foreach (isset($args['methods']) ? [$args] : $args as $endpoint) {
                foreach ((array) $endpoint['methods'] as $method) {
                    $this->routes[$method . ' ' . $route] = $endpoint['permission_callback'];
                }
            }
        });

        foreach ([
            AuditLogController::class,
            AvailabilityController::class,
            MagicLinkController::class,
            ManifestController::class,
            MatchAppearanceController::class,
            PhaseSquadController::class,
            PlayersController::class,
            SeasonController::class,
            SmsTemplatesController::class,
            SyncController::class,
            TeamsController::class,
        ] as $controller) {
            (new $controller())->registerRoutes();
        }
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testSeulesLesRoutesAssumeesSontPubliques(): void
    {
        $public = array_keys(array_filter(
            $this->routes,
            static fn (callable|string $callback): bool => $callback === '__return_true'
        ));

        sort($public);
        $expected = self::PUBLIC_ROUTES;
        sort($expected);

        self::assertSame($expected, $public);
    }

    /**
     * Lire demande d'être connecté, écrire demande le droit de gérer le
     * planner, administrer demande `manage_options`. Une nouvelle route
     * se range dans l'une des trois cases, ou le test échoue.
     */
    public function testChaqueRouteDemandeLeDroitDeSaCategorie(): void
    {
        $asked = null;
        Functions\when('current_user_can')->alias(static function (string $capability) use (&$asked): bool {
            $asked = $capability;
            return true;
        });

        $actual   = [];
        $expected = [];
        foreach ($this->routes as $route => $callback) {
            if (in_array($route, self::PUBLIC_ROUTES, true)) {
                continue;
            }
            $asked = null;
            $callback();
            $actual[$route] = $asked;

            $expected[$route] = match (true) {
                in_array($route, self::ADMIN_ROUTES, true) => 'manage_options',
                str_starts_with($route, 'GET ')            => 'read',
                default                                    => 'ttp_manage_planner',
            };
        }

        ksort($actual);
        ksort($expected);
        self::assertSame($expected, $actual);
    }

    /** @return iterable<string, array{string}> */
    public static function lecturesReserveesAuxConnectes(): iterable
    {
        yield 'joueurs'        => ['GET /players'];
        yield 'joueur'         => ['GET /players/(?P<id>\d+)'];
        yield 'disponibilités' => ['GET /availability'];
        yield 'effectifs'      => ['GET /phase-squads'];
    }

    #[DataProvider('lecturesReserveesAuxConnectes')]
    public function testLectureRefuseeSansConnexion(string $route): void
    {
        Functions\expect('current_user_can')->once()->with('read')->andReturn(false);

        self::assertFalse(($this->routes[$route])());
    }

    #[DataProvider('lecturesReserveesAuxConnectes')]
    public function testLectureAutoriseeAUnConnecte(string $route): void
    {
        Functions\expect('current_user_can')->once()->with('read')->andReturn(true);

        self::assertTrue(($this->routes[$route])());
    }
}
