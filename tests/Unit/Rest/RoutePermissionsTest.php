<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit\Rest;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TT\TeamPlanner\Rest\AvailabilityController;
use TT\TeamPlanner\Rest\MagicLinkController;
use TT\TeamPlanner\Rest\ManifestController;
use TT\TeamPlanner\Rest\MatchAppearanceController;
use TT\TeamPlanner\Rest\PhaseSquadController;
use TT\TeamPlanner\Rest\PlayersController;
use TT\TeamPlanner\Rest\SeasonController;
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
            AvailabilityController::class,
            MagicLinkController::class,
            ManifestController::class,
            MatchAppearanceController::class,
            PhaseSquadController::class,
            PlayersController::class,
            SeasonController::class,
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
