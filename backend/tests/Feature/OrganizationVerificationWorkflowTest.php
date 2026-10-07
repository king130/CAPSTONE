<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationVerificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, organization: Organization, school: School}
     */
    private function createPendingSchool(): array
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_internships'],
            ['scope' => 'organization', 'description' => 'Manage internships']
        );

        $user = User::factory()->create([
            'role' => 'school',
            'email' => 'pending-school.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'free',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Pending Workflow School',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $user->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
        ]);
        $role->permissions()->sync(
            Permission::query()->where('key', 'org.manage_internships')->pluck('id')
        );

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);

        $user->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();

        $school = School::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'institution_name' => 'Pending Workflow School',
            'verification_status' => 'pending',
        ]);

        return compact('user', 'organization', 'school');
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'email' => 'admin.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
    }

    public function test_pending_organization_can_authenticate_but_not_use_protected_routes(): void
    {
        $context = $this->createPendingSchool();

        $login = $this->postJson('/api/auth/login', [
            'email' => $context['user']->email,
            'password' => 'password',
        ]);
        $login->assertOk();
        $login->assertJsonPath('user.verificationStatus', 'pending');

        Sanctum::actingAs($context['user']);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('verificationStatus', 'pending');
        $this->postJson('/api/internships', [
            'title' => 'Should Fail',
            'status' => 'active',
        ])->assertForbidden()->assertJsonPath('code', 'organization_verification_pending');
    }

    public function test_rejection_requires_reason_keeps_user_active_and_exposes_reason(): void
    {
        $context = $this->createPendingSchool();
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/users/'.$context['user']->id, [
            'verificationStatus' => 'rejected',
        ])->assertStatus(422);

        $this->assertSame('pending', $context['school']->fresh()->verification_status);
        $this->assertTrue($context['user']->fresh()->is_active);

        $this->patchJson('/api/admin/users/'.$context['user']->id, [
            'verificationStatus' => 'rejected',
            'verificationRejectionReason' => 'Missing institutional documentation.',
        ])->assertOk();

        $context['user']->refresh();
        $context['school']->refresh();
        $context['organization']->refresh();

        $this->assertTrue($context['user']->is_active);
        $this->assertSame('rejected', $context['school']->verification_status);
        $this->assertSame(
            'Missing institutional documentation.',
            $context['organization']->settings['verification_rejection_reason'] ?? null
        );

        Sanctum::actingAs($context['user']);
        $me = $this->getJson('/api/auth/me');
        $me->assertOk();
        $me->assertJsonPath('verificationStatus', 'rejected');
        $me->assertJsonPath('verificationRejectionReason', 'Missing institutional documentation.');
        $this->assertTrue($me->json('isActive'));

        $this->postJson('/api/internships', [
            'title' => 'Should Fail',
            'status' => 'active',
        ])->assertForbidden()->assertJsonPath('code', 'organization_verification_rejected');

        $notification = Notification::query()->where('user_id', $context['user']->id)->latest('id')->first();
        $this->assertNotNull($notification);
        $this->assertSame('rejected', $notification->metadata['verificationStatus'] ?? null);
        $this->assertSame('Missing institutional documentation.', $notification->metadata['rejectionReason'] ?? null);
        $this->assertStringContainsString('Missing institutional documentation.', (string) $notification->body);
    }

    public function test_genuinely_disabled_user_remains_blocked(): void
    {
        $context = $this->createPendingSchool();
        $context['user']->forceFill(['is_active' => false])->save();

        $this->postJson('/api/auth/login', [
            'email' => $context['user']->email,
            'password' => 'password',
        ])->assertForbidden()->assertJsonPath('message', 'Account Disabled');
    }

    public function test_approval_clears_rejection_reason_and_unlocks_org_features(): void
    {
        $context = $this->createPendingSchool();
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/users/'.$context['user']->id, [
            'verificationStatus' => 'rejected',
            'verificationRejectionReason' => 'Incomplete profile.',
        ])->assertOk();

        $this->patchJson('/api/admin/users/'.$context['user']->id, [
            'verificationStatus' => 'approved',
        ])->assertOk();

        $context['school']->refresh();
        $context['organization']->refresh();
        $this->assertSame('approved', $context['school']->verification_status);
        $this->assertArrayNotHasKey('verification_rejection_reason', $context['organization']->settings ?? []);

        Sanctum::actingAs($context['user']);
        $me = $this->getJson('/api/auth/me');
        $me->assertOk()->assertJsonPath('verificationStatus', 'approved');
        $this->assertNull($me->json('verificationRejectionReason'));

        $this->postJson('/api/internships', [
            'title' => 'Approved Internship',
            'description' => 'Now allowed',
            'status' => 'active',
            'slots_available' => 1,
        ])->assertCreated();

        $this->assertSame(
            1,
            Notification::query()
                ->where('user_id', $context['user']->id)
                ->where('metadata->verificationStatus', 'approved')
                ->count()
        );
    }
}
