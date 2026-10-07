<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisabledUserTokenEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, organization: Organization, company: Company, token: string}
     */
    private function createApprovedCompanyUserWithToken(): array
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_internships'],
            ['scope' => 'organization', 'description' => 'Create and update internships']
        );

        $user = User::factory()->create([
            'role' => 'company',
            'email' => 'active.company.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Active Company Org',
            'type' => 'company',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $user->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
            'is_system' => false,
        ]);

        $permission = Permission::query()->where('key', 'org.manage_internships')->firstOrFail();
        $role->permissions()->sync([$permission->id]);

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

        $company = Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'company_name' => 'Active Company',
            'verification_status' => 'approved',
        ]);

        $token = $user->createToken('spa')->plainTextToken;

        return compact('user', 'organization', 'company', 'token');
    }

    public function test_disabled_user_cannot_use_existing_token_for_auth_me(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email' => 'disabled.me.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $token = $user->createToken('spa')->plainTextToken;

        $user->forceFill(['is_active' => false])->save();

        $response = $this->withToken($token)->getJson('/api/auth/me');

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Account Disabled');
    }

    public function test_disabled_user_cannot_use_existing_token_for_organization_endpoint(): void
    {
        $context = $this->createApprovedCompanyUserWithToken();
        $before = Internship::query()->count();

        $context['user']->forceFill(['is_active' => false])->save();

        $response = $this->withToken($context['token'])->postJson('/api/internships', [
            'title' => 'Should Not Be Created',
            'status' => 'active',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Account Disabled');
        $this->assertSame($before, Internship::query()->count());
        $this->assertDatabaseMissing('internships', [
            'title' => 'Should Not Be Created',
            'company_id' => $context['company']->id,
        ]);
    }

    public function test_disabled_student_cannot_use_existing_token_for_student_endpoint(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email' => 'disabled.student.'.uniqid('', true).'@example.com',
            'is_active' => true,
            'profile_setup_complete' => true,
        ]);

        Student::query()->create([
            'user_id' => $user->id,
            'course' => 'BSIT',
        ]);

        $token = $user->createToken('spa')->plainTextToken;
        $user->forceFill(['is_active' => false])->save();

        $response = $this->withToken($token)->getJson('/api/internships/eligible');

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Account Disabled');
    }

    public function test_active_user_with_valid_token_still_works(): void
    {
        $context = $this->createApprovedCompanyUserWithToken();

        $me = $this->withToken($context['token'])->getJson('/api/auth/me');
        $me->assertOk();
        $me->assertJsonPath('email', $context['user']->email);

        $create = $this->withToken($context['token'])->postJson('/api/internships', [
            'title' => 'Allowed Internship',
            'status' => 'active',
        ]);

        $create->assertCreated();
        $this->assertDatabaseHas('internships', [
            'title' => 'Allowed Internship',
            'company_id' => $context['company']->id,
        ]);
    }

    public function test_disabled_user_cannot_login_for_new_token(): void
    {
        $user = User::factory()->create([
            'role' => 'company',
            'email' => 'disabled.login.'.uniqid('', true).'@example.com',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Account Disabled');
    }

    public function test_logout_still_revokes_current_token(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email' => 'logout.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $created = $user->createToken('spa');
        $token = $created->plainTextToken;
        $tokenId = $created->accessToken->id;

        $logout = $this->withToken($token)->postJson('/api/auth/logout');
        $logout->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);

        // Clear any sticky guard user from the previous authenticated request.
        $this->app['auth']->forgetGuards();

        $me = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/auth/me');
        $me->assertUnauthorized();
    }
}
