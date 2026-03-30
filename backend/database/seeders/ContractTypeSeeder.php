<?php

namespace Database\Seeders;

use App\Models\ContractType;
use Illuminate\Database\Seeder;

class ContractTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'OJT Memorandum of Agreement',
                'slug' => 'ojt-moa',
                'scope' => 'global',
                'description' => 'Standard partnership agreement for internship placements.',
                'fields_schema' => [
                    ['key' => 'programs', 'label' => 'Eligible Programs', 'type' => 'multiselect', 'required' => true, 'options' => ['BS Computer Science', 'BS Information Technology', 'BS Information Systems']],
                    ['key' => 'contact_person', 'label' => 'Primary Contact Person', 'type' => 'text', 'required' => true],
                    ['key' => 'contact_email', 'label' => 'Primary Contact Email', 'type' => 'email', 'required' => true],
                    ['key' => 'max_interns', 'label' => 'Maximum Intern Slots', 'type' => 'number', 'required' => false],
                    ['key' => 'start_on', 'label' => 'Proposed Start Date', 'type' => 'date', 'required' => false],
                ],
                'default_values' => ['max_interns' => 10],
                'settings' => ['allow_templates' => true],
                'sort_order' => 10,
            ],
            [
                'name' => 'Internship Addendum',
                'slug' => 'internship-addendum',
                'scope' => 'global',
                'description' => 'Use when extending or changing an existing internship agreement.',
                'fields_schema' => [
                    ['key' => 'reference_contract', 'label' => 'Reference Contract No.', 'type' => 'text', 'required' => true],
                    ['key' => 'change_summary', 'label' => 'Requested Change Summary', 'type' => 'textarea', 'required' => true],
                    ['key' => 'effective_on', 'label' => 'Effective Date', 'type' => 'date', 'required' => true],
                ],
                'default_values' => [],
                'settings' => ['allow_templates' => false],
                'sort_order' => 20,
            ],
        ];

        foreach ($types as $type) {
            ContractType::query()->updateOrCreate(
                ['slug' => $type['slug'], 'organization_id' => null],
                $type
            );
        }
    }
}
