<?php

namespace Tests\Unit;

use App\Http\Middleware\StripNumberCommas;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function test_initials(): void
    {
        $this->assertSame('FR', User::initialsOf('Fida Muhammad Rahmani'));
        $this->assertSame('A', User::initialsOf('Ahmad'));
        $this->assertSame('?', User::initialsOf(''));
    }

    public function test_money_and_sums(): void
    {
        $this->assertSame('1,234.50 USD', money(1234.5, 'USD'));
        $this->assertSame('60.00 USD · 4,500.00 AFN', sums(['USD' => 60, 'AFN' => 4500, 'EUR' => 0]));
        $this->assertSame('0', sums([]));
    }

    public function test_grouped_numbers(): void
    {
        $this->assertTrue(StripNumberCommas::isGroupedNumber('12,500.75'));
        $this->assertFalse(StripNumberCommas::isGroupedNumber('30, 7, 1, 0'));
    }
}
