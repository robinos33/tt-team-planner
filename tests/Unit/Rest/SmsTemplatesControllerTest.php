<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit\Rest;

require_once dirname(__DIR__, 2) . '/Stubs/WpRest.php';

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TT\TeamPlanner\Rest\SmsTemplatesController;
use WP_REST_Request;

#[CoversClass(SmsTemplatesController::class)]
final class SmsTemplatesControllerTest extends TestCase
{
    /** @var array<string, string> options WordPress simulées */
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->options = [
            'ttp_sms_template_availability' => 'Dispo {prenom} ?',
            'ttp_sms_template_confirmation' => 'Tu joues en {equipe}',
        ];
        Functions\when('__')->returnArg();
        Functions\when('sanitize_textarea_field')->alias(static fn (string $v): string => strip_tags($v));
        Functions\when('get_option')->alias(fn (string $name, mixed $default = false): mixed => $this->options[$name] ?? $default);
        Functions\when('update_option')->alias(function (string $name, mixed $value): bool {
            $this->options[$name] = $value;
            return true;
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testEnregistreLeModeleEnvoyeEtRenvoieLesDeux(): void
    {
        $response = (new SmsTemplatesController())->update(new WP_REST_Request(['availability' => 'Salut {prenom}, dispo J{journee} ?']));

        self::assertSame(200, $response->get_status());
        self::assertSame([
            'availability' => 'Salut {prenom}, dispo J{journee} ?',
            'confirmation' => 'Tu joues en {equipe}',
        ], $response->get_data());
    }

    public function testNettoieLeTexte(): void
    {
        (new SmsTemplatesController())->update(new WP_REST_Request(['confirmation' => '<b>Tu joues</b> en {equipe}']));

        self::assertSame('Tu joues en {equipe}', $this->options['ttp_sms_template_confirmation']);
    }

    public function testRefuseUnModeleVideSansRienEnregistrer(): void
    {
        $response = (new SmsTemplatesController())->update(new WP_REST_Request([
            'availability' => 'Nouveau texte',
            'confirmation' => "  \n ",
        ]));

        self::assertSame(400, $response->get_status());
        self::assertSame('Dispo {prenom} ?', $this->options['ttp_sms_template_availability']);
        self::assertSame('Tu joues en {equipe}', $this->options['ttp_sms_template_confirmation']);
    }
}
