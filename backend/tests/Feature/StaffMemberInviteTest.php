<?php

namespace Tests\Feature;

use App\Mail\StaffAccountSetupMail;
use App\Mail\StudentAccountSetupMail;
use App\Models\AccountSetupToken;
use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffMemberInviteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   owner: User,
     *   organization: Organization,
     *   managerRole: Role,
     *   peerRole: Role,
     *   peer: User,
     *   peerMembership: OrganizationMembership
     * }
     */
    private function createCompanyTenantContext(): array
    {
        foreach ([
            'org.manage_roles' => 'Manage organization roles',
            'manage_roles' => 'Manage roles',
            'org.manage_members' => 'Manage organization members',
            'manage_users' => 'Manage users',
        ] as $key => $description) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $description]
            );
        }

        $owner = User::factory()->create([
            'role' => 'company',
            'email' => 'owner.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Invite Company Org',
            'type' => 'company',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $owner->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $managerRole = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);
        $managerRole->permissions()->sync(
            Permission::query()->whereIn('key', [
                'org.manage_members',
                'manage_users',
                'org.manage_roles',
            ])->pluck('id')->all()
        );

        $peerRole = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Staff',
            'slug' => 'staff_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role_id' => $managerRole->id,
            'status' => 'active',
            'title' => 'Owner',
        ]);

        $owner->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $managerRole->id,
        ])->save();

        Company::query()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'company_name' => 'Invite Company',
            'verification_status' => 'approved',
        ]);

        $peer = User::factory()->create([
            'role' => 'company',
            'email' => 'peer.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $peerMembership = OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $peer->id,
            'role_id' => $peerRole->id,
            'status' => 'active',
            'title' => 'Staff',
        ]);

        $peer->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $peerRole->id,
        ])->save();

        return compact('owner', 'organization', 'managerRole', 'peerRole', 'peer', 'peerMembership');
    }

    public function test_store_member_response_has_no_password_and_sends_invite(): void
    {
        Mail::fake();
        $context = $this->createCompanyTenantContext();
        Sanctum::actingAs($context['owner']);

        $response = $this->postJson('/api/rbac/tenants/'.$context['organization']->id.'/members', [
            'name' => 'New Staff',
            'email' => 'new.staff.'.uniqid('', true).'@example.com',
            'roleId' => $context['peerRole']->id,
            'title' => 'Coordinator',
        ]);

        $response->assertCreated();
        $response->assertJsonMissingPath('temporaryPassword');
        $this->assertArrayNotHasKey('temporaryPassword', $response->json());
        $this->assertArrayNotHasKey('password', $response->json());
        $response->assertJsonPath('inviteSent', true);
        $this->assertNotEmpty($response->json('email'));

        $created = User::query()->where('email', $response->json('email'))->first();
        $this->assertNotNull($created);
        $this->assertTrue((bool) $created->must_change_password);
        $this->assertTrue((bool) $created->is_temporary);
        $this->assertFalse(Hash::check('password', $created->password));
        $this->assertFalse(Hash::check('Temp@TEST', $created->password));

        Mail::assertSent(StaffAccountSetupMail::class);
        $this->assertSame(1, AccountSetupToken::query()->where('user_id', $created->id)->whereNull('used_at')->count());

        $token = AccountSetupToken::query()->where('user_id', $created->id)->first();
        $this->assertNotNull($token);
        $this->assertTrue($token->expires_at->between(now()->addHours(23), now()->addHours(25)));
    }

    public function test_resend_requires_manage_members_permission(): void
    {
        Mail::fake();
        $context = $this->createCompanyTenantContext();

        $pending = User::factory()->create([
            'role' => 'company',
            'email' => 'pending.'.uniqid('', true).'@example.com',
            'is_temporary' => true,
            'must_change_password' => true,
            'is_active' => true,
        ]);
        $pendingMembership = OrganizationMembership::query()->create([
            'organization_id' => $context['organization']->id,
            'user_id' => $pending->id,
            'role_id' => $context['peerRole']->id,
            'status' => 'active',
            'title' => 'Pending',
        ]);

        Sanctum::actingAs($context['peer']);
        $forbidden = $this->postJson(
            '/api/rbac/tenants/'.$context['organization']->id.'/members/'.$pendingMembership->id.'/resend-setup-link'
        );
        $forbidden->assertForbidden();

        Sanctum::actingAs($context['owner']);
        $allowed = $this->postJson(
            '/api/rbac/tenants/'.$context['organization']->id.'/members/'.$pendingMembership->id.'/resend-setup-link'
        );
        $allowed->assertOk();
        $allowed->assertJsonPath('inviteSent', true);
        Mail::assertSent(StaffAccountSetupMail::class);
    }

    public function test_resend_invalidates_old_unused_tokens(): void
    {
        Mail::fake();
        $context = $this->createCompanyTenantContext();

        $pending = User::factory()->create([
            'role' => 'company',
            'email' => 'pending2.'.uniqid('', true).'@example.com',
            'is_temporary' => true,
            'must_change_password' => true,
            'is_active' => true,
        ]);
        $pendingMembership = OrganizationMembership::query()->create([
            'organization_id' => $context['organization']->id,
            'user_id' => $pending->id,
            'role_id' => $context['peerRole']->id,
            'status' => 'active',
            'title' => 'Pending',
        ]);

        $oldHash = hash('sha256', 'old-token-value');
        AccountSetupToken::query()->create([
            'user_id' => $pending->id,
            'invited_by_user_id' => $context['owner']->id,
            'token_hash' => $oldHash,
            'expires_at' => now()->addHours(24),
        ]);

        Sanctum::actingAs($context['owner']);
        $this->postJson(
            '/api/rbac/tenants/'.$context['organization']->id.'/members/'.$pendingMembership->id.'/resend-setup-link'
        )->assertOk();

        $this->assertSame(0, AccountSetupToken::query()->where('token_hash', $oldHash)->count());
        $this->assertSame(1, AccountSetupToken::query()->where('user_id', $pending->id)->whereNull('used_at')->count());
    }

    public function test_resend_rejects_completed_setup(): void
    {
        Mail::fake();
        $context = $this->createCompanyTenantContext();

        $finished = User::factory()->create([
            'role' => 'company',
            'email' => 'finished.'.uniqid('', true).'@example.com',
            'is_temporary' => false,
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $membership = OrganizationMembership::query()->create([
            'organization_id' => $context['organization']->id,
            'user_id' => $finished->id,
            'role_id' => $context['peerRole']->id,
            'status' => 'active',
            'title' => 'Done',
        ]);

        Sanctum::actingAs($context['owner']);
        $this->postJson(
            '/api/rbac/tenants/'.$context['organization']->id.'/members/'.$membership->id.'/resend-setup-link'
        )->assertStatus(422)->assertJsonPath('message', 'This member already finished account setup.');
    }

    public function test_student_setup_flow_still_uses_48_hour_expiry(): void
    {
        Mail::fake();

        foreach ([
            'manage_users' => 'Manage users',
            'org.manage_members' => 'Manage organization members',
        ] as $key => $description) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $description]
            );
        }

        $user = User::factory()->create([
            'role' => 'school',
            'email' => 'school.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Student Invite School Org',
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
            Permission::query()->whereIn('key', ['manage_users', 'org.manage_members'])->pluck('id')
        );

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Primary Admin',
        ]);

        $user->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();

        School::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'institution_name' => 'Student Invite School',
            'subscription_code' => 'SIS-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/school-students', [
            'email' => 'student.'.uniqid('', true).'@example.com',
            'studentName' => 'Test Student',
            'studentNumber' => '2026-001',
            'course' => 'BSIT',
            'yearLevel' => '3rd Year',
        ]);

        $response->assertCreated();
        Mail::assertSent(StudentAccountSetupMail::class);

        $studentUser = User::query()->where('email', $response->json('data.email'))->first();
        $this->assertNotNull($studentUser);
        $token = AccountSetupToken::query()->where('user_id', $studentUser->id)->first();
        $this->assertNotNull($token);
        $this->assertTrue($token->expires_at->between(now()->addHours(47), now()->addHours(49)));
    }
}
