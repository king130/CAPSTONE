<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Support\SkillNameNormalizer;
use Illuminate\Database\Seeder;

class SkillCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'IT & Software' => [
                'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'PHP', 'HTML', 'CSS', 'React', 'Vue.js',
                'Node.js', 'Laravel', 'SQL', 'MySQL', 'Git', 'REST APIs', 'UI Design', 'QA Testing',
                'Cybersecurity Basics', 'Cloud Basics', 'Excel', 'Power BI', 'Data Analysis',
            ],
            'Business' => [
                'Customer Service', 'Sales', 'Marketing', 'Social Media Marketing', 'Bookkeeping',
                'Financial Analysis', 'Project Management', 'Business Writing', 'Negotiation',
                'Market Research', 'Inventory Management', 'Office Administration',
            ],
            'Engineering' => [
                'AutoCAD', 'SolidWorks', 'Circuit Design', 'PLC Basics', 'Technical Drawing',
                'Quality Control', 'Manufacturing Processes', 'Safety Compliance', 'Surveying',
                'Mechanical Troubleshooting',
            ],
            'Education' => [
                'Lesson Planning', 'Classroom Management', 'Tutoring', 'Curriculum Support',
                'Student Assessment', 'Public Speaking', 'Educational Technology', 'Special Education Support',
            ],
            'Hospitality' => [
                'Front Desk Operations', 'Food and Beverage Service', 'Housekeeping', 'Event Coordination',
                'Guest Relations', 'Bartending Basics', 'Kitchen Prep', 'Tour Guiding',
            ],
            'Healthcare' => [
                'Patient Care', 'Vital Signs Monitoring', 'Medical Records', 'First Aid',
                'Pharmacy Assistance', 'Laboratory Assistance', 'Health Education',
            ],
            'Communication' => [
                'Written Communication', 'Verbal Communication', 'Presentation Skills', 'Active Listening',
                'Team Collaboration', 'Conflict Resolution', 'English Proficiency', 'Filipino Proficiency',
            ],
            'Creative & Design' => [
                'Graphic Design', 'Adobe Photoshop', 'Canva', 'Video Editing', 'Photography',
                'Content Writing', 'Illustration', 'Branding',
            ],
            'Agriculture' => [
                'Crop Management', 'Livestock Care', 'Soil Analysis', 'Farm Equipment Operation',
                'Organic Farming', 'Agricultural Extension',
            ],
        ];

        foreach ($catalog as $category => $skills) {
            foreach ($skills as $name) {
                $normalized = SkillNameNormalizer::normalize($name);
                $existing = Skill::query()
                    ->where('normalized_name', $normalized)
                    ->where('status', Skill::STATUS_APPROVED)
                    ->first();

                if ($existing) {
                    continue;
                }

                Skill::query()->create([
                    'name' => $name,
                    'normalized_name' => $normalized,
                    'category' => $category,
                    'status' => Skill::STATUS_APPROVED,
                ]);
            }
        }
    }
}
