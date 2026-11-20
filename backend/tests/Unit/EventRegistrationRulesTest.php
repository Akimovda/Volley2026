<?php

namespace Tests\Unit;

use App\Services\EventRegistrationRules;
use PHPUnit\Framework\TestCase;

/** Режимы регистрации и размеры групп по направлению. Без БД и без приложения. */
class EventRegistrationRulesTest extends TestCase
{
    public function test_allowed_modes(): void
    {
        $this->assertSame(['single', 'team', 'team_classic'], EventRegistrationRules::allowedModes('classic'));
        $this->assertSame(['single', 'mixed_group', 'team_beach'], EventRegistrationRules::allowedModes('beach'));
        $this->assertSame(['single'], EventRegistrationRules::allowedModes('other'));
    }

    public function test_assert_mode_allowed(): void
    {
        EventRegistrationRules::assertModeAllowed('classic', 'single');
        EventRegistrationRules::assertModeAllowed('beach', 'mixed_group');
        $this->expectException(\DomainException::class);
        EventRegistrationRules::assertModeAllowed('classic', 'mixed_group');
    }

    public function test_group_size(): void
    {
        $this->assertSame(1, EventRegistrationRules::groupSize('classic', '5x1'));
        $this->assertSame(2, EventRegistrationRules::groupSize('beach', '2x2'));
        $this->assertSame(3, EventRegistrationRules::groupSize('beach', '3x3'));
        $this->assertSame(4, EventRegistrationRules::groupSize('beach', '4x4'));
        $this->assertSame(1, EventRegistrationRules::groupSize('beach', null));
    }

    public function test_required_players_multiple(): void
    {
        $this->assertSame(0, EventRegistrationRules::requiredPlayersMultiple('classic', '4x2'));
        $this->assertSame(4, EventRegistrationRules::requiredPlayersMultiple('beach', '2x2'));
        $this->assertSame(6, EventRegistrationRules::requiredPlayersMultiple('beach', '3x3'));
    }
}
