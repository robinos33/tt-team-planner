<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit\Domain;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TT\TeamPlanner\Domain\QualificationRules;

#[CoversClass(QualificationRules::class)]
final class QualificationRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testEveryRuleIsComplete(): void
    {
        $statuses = [QualificationRules::CONTROLLED, QualificationRules::SIGNALED, QualificationRules::NOT_CONTROLLED];

        foreach (QualificationRules::all() as $rule) {
            self::assertContains($rule['status'], $statuses, $rule['title']);
            self::assertNotSame('', $rule['title']);
            self::assertNotSame('', $rule['summary'], $rule['title']);
            self::assertNotSame('', $rule['reference'], $rule['title']);
            self::assertNotSame('', $rule['detail'], $rule['title']);
        }
    }
}
