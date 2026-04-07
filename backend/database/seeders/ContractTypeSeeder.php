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
                'name' => 'OJT Memorandum of Agreement (MOA)',
                'slug' => 'ojt-moa',
                'scope' => 'global',
                'description' => 'Default MOA for OJT: aligns eligible programs with courses shared by both school and company.',
                'fields_schema' => [
                    ['key' => 'programs', 'label' => 'Eligible Programs (aligned with shared courses)', 'type' => 'multiselect', 'required' => true, 'options' => ['BS Computer Science', 'BS Information Technology', 'BS Information Systems']],
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
            [
                'name' => 'Practicum Partnership Agreement',
                'slug' => 'practicum-partnership-agreement',
                'scope' => 'global',
                'description' => 'For practice teaching, campus immersion, and school-based placements such as BSED endorsements.',
                'fields_schema' => [
                    ['key' => 'programs', 'label' => 'Eligible Programs', 'type' => 'multiselect', 'required' => true, 'options' => ['Bachelor of Secondary Education', 'Bachelor of Elementary Education']],
                    ['key' => 'placement_type', 'label' => 'Placement Type', 'type' => 'select', 'required' => true, 'options' => ['School-based', 'Partner-school-based', 'Department-based']],
                    ['key' => 'mentor_name', 'label' => 'Mentor / Cooperating Teacher', 'type' => 'text', 'required' => true],
                    ['key' => 'intern_count', 'label' => 'Number of Interns', 'type' => 'number', 'required' => false],
                    ['key' => 'effective_on', 'label' => 'Target Start Date', 'type' => 'date', 'required' => false],
                ],
                'default_values' => [],
                'settings' => ['allow_templates' => true],
                'sort_order' => 30,
            ],
            [
                'name' => 'Student Deployment Letter',
                'slug' => 'student-deployment-letter',
                'scope' => 'global',
                'description' => 'A lighter agreement for formally deploying endorsed students to an approved host.',
                'fields_schema' => [
                    ['key' => 'programs', 'label' => 'Covered Programs', 'type' => 'multiselect', 'required' => true, 'options' => ['BS Computer Science', 'BS Information Technology', 'Bachelor of Secondary Education']],
                    ['key' => 'deployment_period', 'label' => 'Deployment Period', 'type' => 'text', 'required' => true],
                    ['key' => 'host_supervisor', 'label' => 'Host Supervisor', 'type' => 'text', 'required' => true],
                    ['key' => 'student_requirements', 'label' => 'Required Student Documents', 'type' => 'textarea', 'required' => false],
                ],
                'default_values' => [],
                'settings' => ['allow_templates' => true],
                'sort_order' => 40,
            ],
            [
                'name' => 'Data Sharing Agreement',
                'slug' => 'data-sharing-agreement',
                'scope' => 'global',
                'description' => 'Defines how student information, evaluations, and internship records may be exchanged between partners.',
                'fields_schema' => [
                    ['key' => 'shared_data_scope', 'label' => 'Shared Data Scope', 'type' => 'textarea', 'required' => true],
                    ['key' => 'retention_period', 'label' => 'Retention Period', 'type' => 'text', 'required' => true],
                    ['key' => 'authorized_contact_email', 'label' => 'Authorized Contact Email', 'type' => 'email', 'required' => true],
                    ['key' => 'security_requirements', 'label' => 'Security Requirements', 'type' => 'textarea', 'required' => false],
                ],
                'default_values' => [],
                'settings' => ['allow_templates' => false],
                'sort_order' => 50,
            ],
            [
                'name' => 'Non-Disclosure Agreement',
                'slug' => 'internship-nda',
                'scope' => 'global',
                'description' => 'Use for placements where interns may access proprietary records, systems, or confidential materials.',
                'fields_schema' => [
                    ['key' => 'confidential_scope', 'label' => 'Confidential Information Scope', 'type' => 'textarea', 'required' => true],
                    ['key' => 'valid_until', 'label' => 'Confidentiality End Date', 'type' => 'date', 'required' => false],
                    ['key' => 'authorized_signatory', 'label' => 'Authorized Signatory', 'type' => 'text', 'required' => true],
                ],
                'default_values' => [],
                'settings' => ['allow_templates' => false],
                'sort_order' => 60,
            ],
            [
                'name' => 'Safety and Compliance Undertaking',
                'slug' => 'safety-compliance-undertaking',
                'scope' => 'global',
                'description' => 'Captures host safety expectations, orientation requirements, and student compliance commitments.',
                'fields_schema' => [
                    ['key' => 'orientation_date', 'label' => 'Orientation Date', 'type' => 'date', 'required' => false],
                    ['key' => 'required_trainings', 'label' => 'Required Trainings', 'type' => 'textarea', 'required' => true],
                    ['key' => 'ppe_or_materials', 'label' => 'Required PPE / Materials', 'type' => 'textarea', 'required' => false],
                    ['key' => 'incident_contact', 'label' => 'Incident Contact Person', 'type' => 'text', 'required' => true],
                ],
                'default_values' => [],
                'settings' => ['allow_templates' => true],
                'sort_order' => 70,
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
