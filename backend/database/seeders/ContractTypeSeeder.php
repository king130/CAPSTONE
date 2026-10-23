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
            [
                'name' => 'School Endorsement Letter',
                'slug' => 'school-endorsement-letter',
                'scope' => 'global',
                'description' => 'School endorsement for a student or batch to begin OJT with an approved host company.',
                'fields_schema' => [
                    ['key' => 'programs', 'label' => 'Covered Programs', 'type' => 'multiselect', 'required' => true, 'options' => ['BS Computer Science', 'BS Information Technology', 'BS Information Systems', 'Bachelor of Secondary Education']],
                    ['key' => 'endorsed_student_count', 'label' => 'Number of Endorsed Students', 'type' => 'number', 'required' => true],
                    ['key' => 'ojt_hours_required', 'label' => 'Required OJT Hours', 'type' => 'number', 'required' => true],
                    ['key' => 'endorsement_period', 'label' => 'Endorsement Period', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. June–September 2026'],
                    ['key' => 'coordinator_name', 'label' => 'School Coordinator', 'type' => 'text', 'required' => true],
                    ['key' => 'coordinator_email', 'label' => 'Coordinator Email', 'type' => 'email', 'required' => true],
                    ['key' => 'remarks', 'label' => 'Additional Remarks', 'type' => 'textarea', 'required' => false],
                ],
                'default_values' => ['ojt_hours_required' => 486],
                'settings' => ['allow_templates' => true],
                'sort_order' => 80,
            ],
            [
                'name' => 'Hybrid / Flexible OJT Arrangement',
                'slug' => 'hybrid-flexible-ojt-arrangement',
                'scope' => 'global',
                'description' => 'Defines on-site, remote, and flexible schedule expectations for hybrid internship placements.',
                'fields_schema' => [
                    ['key' => 'work_setup', 'label' => 'Primary Work Setup', 'type' => 'select', 'required' => true, 'options' => ['Hybrid', 'Remote', 'Flexible on-site', 'Shifting']],
                    ['key' => 'onsite_days', 'label' => 'On-site Days', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. Mon–Wed'],
                    ['key' => 'remote_days', 'label' => 'Remote Days', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. Thu–Fri'],
                    ['key' => 'reporting_channel', 'label' => 'Daily Reporting Channel', 'type' => 'select', 'required' => true, 'options' => ['Email', 'Company chat', 'OJT Intern Path logs', 'Google Meet check-in']],
                    ['key' => 'supervisor_name', 'label' => 'Host Supervisor', 'type' => 'text', 'required' => true],
                    ['key' => 'supervisor_email', 'label' => 'Supervisor Email', 'type' => 'email', 'required' => true],
                    ['key' => 'tools_required', 'label' => 'Required Tools / Access', 'type' => 'textarea', 'required' => false],
                ],
                'default_values' => [],
                'settings' => ['allow_templates' => true],
                'sort_order' => 90,
            ],
            [
                'name' => 'Allowance and Benefits Terms',
                'slug' => 'allowance-benefits-terms',
                'scope' => 'global',
                'description' => 'Documents stipend, meal, transportation, and other benefit terms between school and host.',
                'fields_schema' => [
                    ['key' => 'allowance_type', 'label' => 'Allowance Type', 'type' => 'select', 'required' => true, 'options' => ['Monthly stipend', 'Daily allowance', 'Transportation only', 'Meal only', 'No allowance', 'Other']],
                    ['key' => 'allowance_amount', 'label' => 'Amount (PHP)', 'type' => 'number', 'required' => false],
                    ['key' => 'payment_schedule', 'label' => 'Payment Schedule', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. every 15th and 30th'],
                    ['key' => 'other_benefits', 'label' => 'Other Benefits', 'type' => 'textarea', 'required' => false],
                    ['key' => 'effective_on', 'label' => 'Effective Date', 'type' => 'date', 'required' => true],
                ],
                'default_values' => [],
                'settings' => ['allow_templates' => true],
                'sort_order' => 100,
            ],
            [
                'name' => 'OJT Completion and Evaluation Request',
                'slug' => 'ojt-completion-evaluation-request',
                'scope' => 'global',
                'description' => 'Request host evaluation, certificate issuance, and completion confirmation for finishing interns.',
                'fields_schema' => [
                    ['key' => 'intern_batch', 'label' => 'Intern Batch / Cohort', 'type' => 'text', 'required' => true],
                    ['key' => 'completion_date', 'label' => 'Target Completion Date', 'type' => 'date', 'required' => true],
                    ['key' => 'hours_completed', 'label' => 'Hours Completed', 'type' => 'number', 'required' => false],
                    ['key' => 'evaluation_needed', 'label' => 'Evaluation Form Needed', 'type' => 'checkbox', 'required' => false],
                    ['key' => 'certificate_needed', 'label' => 'Certificate of Completion Needed', 'type' => 'checkbox', 'required' => false],
                    ['key' => 'return_documents_to', 'label' => 'Return Documents To', 'type' => 'email', 'required' => true],
                    ['key' => 'notes', 'label' => 'Notes for Host', 'type' => 'textarea', 'required' => false],
                ],
                'default_values' => [
                    'evaluation_needed' => true,
                    'certificate_needed' => true,
                ],
                'settings' => ['allow_templates' => true],
                'sort_order' => 110,
            ],
            [
                'name' => 'Cavite Multi-Site Placement Agreement',
                'slug' => 'cavite-multi-site-placement',
                'scope' => 'global',
                'description' => 'For OJT hosts with branches across Cavite cities/municipalities or rotating site assignments.',
                'fields_schema' => [
                    ['key' => 'primary_site_city', 'label' => 'Primary Site City / Municipality', 'type' => 'select', 'required' => true, 'options' => ['Bacoor City', 'Imus City', 'Dasmarinas City', 'General Trias City', 'Trece Martires City', 'Tagaytay City', 'Cavite City', 'Silang', 'Tanza', 'Carmona', 'Other']],
                    ['key' => 'secondary_sites', 'label' => 'Secondary Sites', 'type' => 'textarea', 'required' => false, 'placeholder' => 'List other Cavite locations if rotating'],
                    ['key' => 'rotation_allowed', 'label' => 'Site Rotation Allowed', 'type' => 'checkbox', 'required' => false],
                    ['key' => 'transport_support', 'label' => 'Transportation Support', 'type' => 'select', 'required' => false, 'options' => ['None', 'Partial reimbursement', 'Shuttle', 'Full support']],
                    ['key' => 'site_supervisor', 'label' => 'Primary Site Supervisor', 'type' => 'text', 'required' => true],
                    ['key' => 'site_contact_email', 'label' => 'Site Contact Email', 'type' => 'email', 'required' => true],
                ],
                'default_values' => [],
                'settings' => ['allow_templates' => true],
                'sort_order' => 120,
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
