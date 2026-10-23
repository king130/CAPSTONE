<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMedia;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobPostBrandingCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_posts_use_each_companys_logo_and_three_preview_photos(): void
    {
        Storage::fake('public');
        $companyA = $this->makeCompany('Company A');
        $companyB = $this->makeCompany('Company B');

        $this->giveProfileAvatar($companyA['user']);
        $this->giveProfileAvatar($companyB['user']);

        $this->addMedia($companyA['organization'], 'A-photo-1', false, 1);
        $this->addMedia($companyA['organization'], 'A-photo-2', false, 2);
        $this->addMedia($companyA['organization'], 'A-photo-3', false, 3);
        $this->addMedia($companyA['organization'], 'A-photo-extra', false, 4);

        $this->addMedia($companyB['organization'], 'B-photo-1', false, 1);
        $this->addMedia($companyB['organization'], 'B-photo-2', false, 2);
        $this->addMedia($companyB['organization'], 'B-photo-3', false, 3);

        $jobA = $this->makeInternship($companyA['company'], 'Job A');
        $jobB = $this->makeInternship($companyB['company'], 'Job B');

        $firstA = $this->getJson('/api/internships/'.$jobA->id)->assertOk();
        $this->assertStringContainsString('/profile/avatar/'.$companyA['user']->id, (string) $firstA->json('data.coverImage'));
        $this->assertStringNotContainsString('/profile/avatar/'.$companyB['user']->id, (string) $firstA->json('data.coverImage'));
        $captionsA = collect($firstA->json('data.previewPhotos'))->pluck('caption')->all();
        $this->assertSame(['A-photo-1', 'A-photo-2', 'A-photo-3'], $captionsA);
        $this->assertNotContains('A-photo-extra', $captionsA);

        $responseB = $this->getJson('/api/internships/'.$jobB->id)->assertOk();
        $this->assertStringContainsString('/profile/avatar/'.$companyB['user']->id, (string) $responseB->json('data.coverImage'));
        $this->assertSame(
            ['B-photo-1', 'B-photo-2', 'B-photo-3'],
            collect($responseB->json('data.previewPhotos'))->pluck('caption')->all(),
        );

        $secondA = $this->getJson('/api/internships/'.$jobA->id)->assertOk();
        $this->assertStringContainsString('/profile/avatar/'.$companyA['user']->id, (string) $secondA->json('data.coverImage'));
        $this->assertSame(['A-photo-1', 'A-photo-2', 'A-photo-3'], collect($secondA->json('data.previewPhotos'))->pluck('caption')->all());
    }

    public function test_capacity_counts_accepted_applications_and_ignores_rejected_ones(): void
    {
        $company = $this->makeCompany('Capacity Co');
        $internship = $this->makeInternship($company['company'], 'Capacity Role', 10);
        $student = Student::query()->create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'course' => 'BS Information Technology',
        ]);

        $empty = $this->getJson('/api/internships/'.$internship->id)->assertOk();
        $empty->assertJsonPath('data.filledSlots', 0);
        $empty->assertJsonPath('data.slots_remaining', 10);
        $empty->assertJsonPath('data.slotsAvailable', 10);

        foreach (range(1, 7) as $index) {
            Application::query()->create([
                'internship_id' => $internship->id,
                'student_id' => $student->id,
                'company_id' => $company['company']->id,
                'status' => 'accepted',
                'internship_title' => 'Capacity Role',
                'student_name' => 'Student '.$index,
            ]);
        }
        Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'company_id' => $company['company']->id,
            'status' => 'rejected',
            'internship_title' => 'Capacity Role',
            'student_name' => 'Rejected Student',
        ]);

        $partial = $this->getJson('/api/internships/'.$internship->id)->assertOk();
        $partial->assertJsonPath('data.filledSlots', 7);
        $partial->assertJsonPath('data.slots_remaining', 3);
    }

    /**
     * @return array{organization: Organization, company: Company, user: User}
     */
    private function makeCompany(string $name): array
    {
        $user = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $organization = Organization::query()->create([
            'name' => $name,
            'type' => 'company',
            'owner_user_id' => $user->id,
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'company_name' => $name,
            'verification_status' => 'approved',
        ]);

        return compact('organization', 'company', 'user');
    }

    private function giveProfileAvatar(User $user): void
    {
        $path = 'avatars/'.$user->id.'/profile.jpg';
        Storage::disk('public')->put($path, 'avatar');
        $user->forceFill(['profile' => ['avatarPath' => $path]])->save();
    }

    private function addMedia(Organization $organization, string $caption, bool $cover, int $sort): void
    {
        OrganizationMedia::query()->create([
            'organization_id' => $organization->id,
            'path' => 'organization-media/'.$organization->id.'/'.$caption.'.jpg',
            'caption' => $caption,
            'sort_order' => $sort,
            'is_cover' => $cover,
        ]);
    }

    private function makeInternship(Company $company, string $title, int $slots = 2): Internship
    {
        return Internship::query()->create([
            'company_id' => $company->id,
            'company_name' => $company->company_name,
            'host_type' => 'company',
            'host_name' => $company->company_name,
            'title' => $title,
            'status' => 'active',
            'slots_available' => $slots,
            'location' => 'Imus City, Cavite',
        ]);
    }
}
