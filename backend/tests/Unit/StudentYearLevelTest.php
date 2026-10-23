<?php

namespace Tests\Unit;

use App\Support\StudentYearLevel;
use PHPUnit\Framework\TestCase;

class StudentYearLevelTest extends TestCase
{
    public function test_normalizes_first_through_fifth_year_aliases(): void
    {
        $this->assertSame('1st Year', StudentYearLevel::normalize('1st Year'));
        $this->assertSame('1st Year', StudentYearLevel::normalize('1'));
        $this->assertSame('2nd Year', StudentYearLevel::normalize('second'));
        $this->assertSame('3rd Year', StudentYearLevel::normalize('3rd Year'));
        $this->assertSame('3rd Year', StudentYearLevel::normalize('III'));
        $this->assertSame('4th Year', StudentYearLevel::normalize('4th Year'));
        $this->assertSame('4th Year', StudentYearLevel::normalize('IV'));
        $this->assertSame('5th Year', StudentYearLevel::normalize('5th'));
        $this->assertNull(StudentYearLevel::normalize('Custom'));
        $this->assertNull(StudentYearLevel::normalize(''));
        $this->assertTrue(StudentYearLevel::isValid('1st Year'));
        $this->assertTrue(StudentYearLevel::isValid('5th Year'));
        $this->assertFalse(StudentYearLevel::isValid('Custom'));
    }

    public function test_default_eligible_is_third_and_fourth(): void
    {
        $this->assertSame(['3rd Year', '4th Year'], StudentYearLevel::DEFAULT_ELIGIBLE);
        $this->assertTrue(StudentYearLevel::isEligible('3rd Year', StudentYearLevel::DEFAULT_ELIGIBLE));
        $this->assertFalse(StudentYearLevel::isEligible('1st Year', StudentYearLevel::DEFAULT_ELIGIBLE));
        $this->assertFalse(StudentYearLevel::isEligible('2nd Year', StudentYearLevel::DEFAULT_ELIGIBLE));
    }

    public function test_normalize_list_orders_and_dedupes(): void
    {
        $this->assertSame(
            ['2nd Year', '4th Year', '5th Year'],
            StudentYearLevel::normalizeList(['4th Year', '2nd', '5th Year', '4', 'bogus'])
        );
    }
}
