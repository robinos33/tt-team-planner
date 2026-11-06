<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit\Rest;

require_once dirname(__DIR__, 2) . '/Stubs/WpRest.php';

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TT\TeamPlanner\Repository\SmsTemplateRepository;
use TT\TeamPlanner\Rest\SmsTemplatesController;
use WP_REST_Request;

#[CoversClass(SmsTemplatesController::class)]
#[CoversClass(SmsTemplateRepository::class)]
final class SmsTemplatesControllerTest extends TestCase
{
    private const ME    = 7;
    private const OTHER = 8;

    /** @var array<string, string> modèles du club (options) */
    private array $options = [];

    /** @var array<int, array<string, string>> modèles personnels (user meta) */
    private array $meta = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->options = [
            'ttp_sms_template_availability' => 'Dispo {prenom} ?',
            'ttp_sms_template_confirmation' => 'Tu joues en {equipe}',
        ];
        $this->meta = [];
        Functions\when('__')->returnArg();
        Functions\when('get_current_user_id')->justReturn(self::ME);
        Functions\when('sanitize_textarea_field')->alias(static fn (string $v): string => strip_tags($v));
        Functions\when('get_option')->alias(fn (string $name, mixed $default = false): mixed => $this->options[$name] ?? $default);
        Functions\when('get_user_meta')->alias(fn (int $id, string $key, bool $single): string => $this->meta[$id][$key] ?? '');
        Functions\when('update_user_meta')->alias(function (int $id, string $key, string $value): bool {
            $this->meta[$id][$key] = $value;
            return true;
        });
        Functions\when('delete_user_meta')->alias(function (int $id, string $key): bool {
            unset($this->meta[$id][$key]);
            return true;
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testEnregistreLeModelePourLeMembreSeulement(): void
    {
        $response = (new SmsTemplatesController())->update(new WP_REST_Request(['availability' => 'Salut {prenom}, dispo J{journee} ?']));

        self::assertSame(200, $response->get_status());
        self::assertSame([
            'templates' => ['availability' => 'Salut {prenom}, dispo J{journee} ?', 'confirmation' => 'Tu joues en {equipe}'],
            'custom'    => ['availability' => true, 'confirmation' => false],
        ], $response->get_data());
        self::assertSame('Dispo {prenom} ?', $this->options['ttp_sms_template_availability'], 'le modèle du club ne bouge pas');
        self::assertSame('Dispo {prenom} ?', (new SmsTemplateRepository())->forUser(self::OTHER)['templates']['availability'], 'un autre membre garde le modèle du club');
    }

    public function testNettoieLeTexte(): void
    {
        (new SmsTemplatesController())->update(new WP_REST_Request(['confirmation' => '<b>Tu joues</b> en {equipe}']));

        self::assertSame('Tu joues en {equipe}', $this->meta[self::ME]['ttp_sms_template_confirmation']);
    }

    public function testRevientAuModeleDuClub(): void
    {
        $this->meta[self::ME] = ['ttp_sms_template_confirmation' => 'Le mien'];

        $response = (new SmsTemplatesController())->reset(new WP_REST_Request(['kind' => 'confirmation']));

        self::assertSame('Tu joues en {equipe}', $response->get_data()['templates']['confirmation']);
        self::assertFalse($response->get_data()['custom']['confirmation']);
    }

    public function testRefuseUnModeleVideSansRienEnregistrer(): void
    {
        $response = (new SmsTemplatesController())->update(new WP_REST_Request([
            'availability' => 'Nouveau texte',
            'confirmation' => "  \n ",
        ]));

        self::assertSame(400, $response->get_status());
        self::assertSame([], $this->meta);
    }
}
