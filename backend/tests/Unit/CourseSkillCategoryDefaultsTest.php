<?php

namespace Tests\Unit;

use App\Support\CourseSkillCategoryDefaults;
use PHPUnit\Framework\TestCase;

class CourseSkillCategoryDefaultsTest extends TestCase
{
    public function test_it_course_names_map_to_it_and_communication(): void
    {
        foreach (['BS Information Technology', 'BS IT', 'BS Computer Science'] as $course) {
            $this->assertSame(
                ['IT & Software', 'Communication'],
                CourseSkillCategoryDefaults::forCourseName($course),
                $course
            );
        }
    }

    public function test_business_course_maps_to_business_and_communication(): void
    {
        $this->assertSame(
            ['Business', 'Communication'],
            CourseSkillCategoryDefaults::forCourseName('BS Business Administration')
        );
    }

    public function test_unmapped_course_returns_empty_list(): void
    {
        $this->assertSame([], CourseSkillCategoryDefaults::forCourseName('Bachelor of Religious Studies'));
    }

    public function test_for_course_names_unions_categories(): void
    {
        $this->assertEqualsCanonicalizing(
            ['IT & Software', 'Communication', 'Business'],
            CourseSkillCategoryDefaults::forCourseNames([
                'BS Information Technology',
                'BS Business Administration',
            ])
        );
    }

    public function test_normalize_list_accepts_json_string(): void
    {
        $this->assertSame(
            ['IT & Software', 'Communication'],
            CourseSkillCategoryDefaults::normalizeList('["IT & Software","Communication","Nope"]')
        );
    }
}
