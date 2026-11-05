<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TT\TeamPlanner\Capabilities;

#[CoversClass(Capabilities::class)]
final class CapabilitiesTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var array<string, Mockery\MockInterface> rôles WordPress simulés */
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('__')->returnArg();
        Functions\when('get_role')->alias(fn (string $name): ?object => $this->roles[$name] ?? null);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testCreeLeRoleAvecLaLectureEtLeDroitDeGerer(): void
    {
        $this->roles = ['administrator' => $this->role(), 'editor' => $this->role()];

        Functions\expect('add_role')->once()->with(
            'ttp_planner',
            'Gestionnaire planner',
            ['read' => true, 'ttp_manage_planner' => true]
        );

        Capabilities::install();
    }

    public function testDonneLeDroitAuxAdministrateursEtEditeurs(): void
    {
        $this->roles = [
            'administrator' => $this->role()->shouldReceive('add_cap')->once()->with('ttp_manage_planner')->getMock(),
            'editor'        => $this->role()->shouldReceive('add_cap')->once()->with('ttp_manage_planner')->getMock(),
            'author'        => $this->role()->shouldNotReceive('add_cap')->getMock(),
        ];
        Functions\when('add_role')->justReturn(null);

        Capabilities::install();
    }

    public function testSupporteUnRoleEditeurSupprime(): void
    {
        $this->roles = [
            'administrator' => $this->role()->shouldReceive('add_cap')->once()->getMock(),
        ];
        Functions\when('add_role')->justReturn(null);

        Capabilities::install();
    }

    public function testNeRendPasLeDroitRetireSiLeRoleExisteDeja(): void
    {
        $this->roles = [
            'ttp_planner' => $this->role(),
            'editor'      => $this->role()->shouldNotReceive('add_cap')->getMock(),
        ];
        Functions\expect('add_role')->never();

        Capabilities::install();
    }

    public function testDesinstalleLeRoleEtRetireLeDroitPartout(): void
    {
        $this->roles = [
            'administrator' => $this->role()->shouldReceive('remove_cap')->once()->with('ttp_manage_planner')->getMock(),
            'subscriber'    => $this->role()->shouldReceive('remove_cap')->once()->with('ttp_manage_planner')->getMock(),
        ];
        Functions\expect('remove_role')->once()->with('ttp_planner');
        Functions\when('wp_roles')->justReturn((object) ['roles' => ['administrator' => [], 'subscriber' => []]]);

        Capabilities::uninstall();
    }

    public function testPeutGererSelonLeDroitDedie(): void
    {
        Functions\expect('current_user_can')->once()->with('ttp_manage_planner')->andReturn(true);

        self::assertTrue(Capabilities::canManagePlanner());
    }

    private function role(): Mockery\MockInterface
    {
        return Mockery::mock('WP_Role')->shouldIgnoreMissing();
    }
}
