<?php

namespace Tests\Unit;

use App\Support\CourseAlignment;
use PHPUnit\Framework\TestCase;

class CourseAlignmentTest extends TestCase
{
    public function test_normalize_list_trims_collapses_and_dedupes_case_insensitively(): void
    {
        $this->assertSame(
            ['BS Hotel Management'],
            CourseAlignment::normalizeList(['  BS  Hotel  Management ', 'bs hotel management', '', 12])
        );
    }

    public function test_shared_courses_use_viewer_display_names(): void
    {
        $this->assertSame(
            ['BSIT'],
            CourseAlignment::sharedCourses(['BSHM', 'BSIT'], ['bsit', 'BSA'])
        );
    }

    public function test_alignment_statuses(): void
    {
        $this->assertSame(CourseAlignment::ALIGNED, CourseAlignment::alignment(['BSHM'], ['BSHM']));
        $this->assertSame(CourseAlignment::NONE, CourseAlignment::alignment(['BSHM'], ['BSIT']));
        $this->assertSame(CourseAlignment::UNSPECIFIED, CourseAlignment::alignment(['BSHM'], []));
        // Viewer has no courses but the other org does → not unspecified (other is specified).
        $this->assertSame(CourseAlignment::NONE, CourseAlignment::alignment([], ['BSIT']));
    }

    public function test_aligned_filter_includes_partnered_and_unspecified(): void
    {
        $this->assertTrue(CourseAlignment::includeWhenAlignedFilter(CourseAlignment::ALIGNED, false));
        $this->assertTrue(CourseAlignment::includeWhenAlignedFilter(CourseAlignment::UNSPECIFIED, false));
        $this->assertTrue(CourseAlignment::includeWhenAlignedFilter(CourseAlignment::NONE, true));
        $this->assertFalse(CourseAlignment::includeWhenAlignedFilter(CourseAlignment::NONE, false));
    }
}
