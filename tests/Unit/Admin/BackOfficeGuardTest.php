<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TT\TeamPlanner\Admin\BackOfficeGuard;

#[CoversClass(BackOfficeGuard::class)]
final class BackOfficeGuardTest extends TestCase
{
    /** @var array<string, bool> droits de l'utilisateur courant */
    private array $caps = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $GLOBALS['pagenow'] = 'index.php';
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('current_user_can')->alias(fn (string $cap): bool => $this->caps[$cap] ?? false);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['pagenow']);
        Monkey\tearDown();
        parent::tearDown();
    }

    /** @return iterable<string, array{array<string, bool>, bool}> */
    public static function utilisateurs(): iterable
    {
        yield 'membre commission équipe'    => [['ttp_manage_planner' => true], true];
        yield 'éditeur gestionnaire'        => [['ttp_manage_planner' => true, 'edit_posts' => true], false];
        yield 'abonné'                      => [[], false];
        yield 'contributeur sans le droit'  => [['edit_posts' => true], false];
    }

    /** @param array<string, bool> $caps */
    #[DataProvider('utilisateurs')]
    public function testSeulLeGestionnaireSansDroitEditionEstTenuHorsDuBackOffice(array $caps, bool $confined): void
    {
        $this->caps = $caps;
        $guard = new BackOfficeGuard();

        self::assertSame($confined, $guard->shouldRedirect());
        self::assertSame(! $confined, $guard->filterAdminBar(true));
    }

    public function testLaisseAdminAjaxAuGestionnaire(): void
    {
        $this->caps = ['ttp_manage_planner' => true];
        Functions\when('wp_doing_ajax')->justReturn(true);

        self::assertFalse((new BackOfficeGuard())->shouldRedirect());
    }

    public function testLaisseAdminPostAuGestionnaire(): void
    {
        $this->caps = ['ttp_manage_planner' => true];
        $GLOBALS['pagenow'] = 'admin-post.php';

        self::assertFalse((new BackOfficeGuard())->shouldRedirect());
    }

    public function testNeReafichePasUneBarreDejaMasquee(): void
    {
        self::assertFalse((new BackOfficeGuard())->filterAdminBar(false));
    }

    public function testRenvoieVersLaPageQuiPorteLeShortcode(): void
    {
        Functions\when('get_posts')->justReturn([12, 34]);
        Functions\when('get_post_field')->alias(
            static fn (string $field, int $id): string => $id === 34 ? '[tt_team_planner]' : 'cite tt_team_planner en texte'
        );
        Functions\when('has_shortcode')->alias(
            static fn (string $content): bool => $content === '[tt_team_planner]'
        );
        Functions\when('get_permalink')->alias(static fn (int $id): string => "https://club.test/?page_id=$id");

        self::assertSame('https://club.test/?page_id=34', (new BackOfficeGuard())->plannerUrl());
    }

    public function testRenvoieALAccueilSansPagePlanner(): void
    {
        Functions\when('get_posts')->justReturn([]);
        Functions\when('home_url')->justReturn('https://club.test/');

        self::assertSame('https://club.test/', (new BackOfficeGuard())->plannerUrl());
    }
}
