<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Internship;
use App\Models\Message;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   schoolUser: User,
     *   school: School,
     *   schoolOrg: Organization,
     *   studentUser: User,
     *   student: Student
     * }
     */
    private function createLinkedStudentAndSchool(string $verificationStatus = 'approved'): array
    {
        $schoolUser = User::factory()->create([
            'role' => 'school',
            'email' => 'school.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $schoolOrg = Organization::query()->create([
            'name' => 'Chat School Org',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $schoolRole = Role::query()->create([
            'tenant_id' => $schoolOrg->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
        ]);

        OrganizationMembership::query()->create([
            'organization_id' => $schoolOrg->id,
            'user_id' => $schoolUser->id,
            'role_id' => $schoolRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);

        $schoolUser->forceFill([
            'tenant_id' => $schoolOrg->id,
            'role_id' => $schoolRole->id,
        ])->save();

        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Chat School',
            'subscription_code' => 'CHS-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => $verificationStatus,
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => $school->institution_name,
            'school_subscription_code' => $school->subscription_code,
            'course' => 'BSIT',
        ]);

        return compact('schoolUser', 'school', 'schoolOrg', 'studentUser', 'student');
    }

    /**
     * @return array{
     *   companyUser: User,
     *   company: Company,
     *   companyOrg: Organization,
     *   internship: Internship
     * }
     */
    private function createCompany(string $verificationStatus = 'approved'): array
    {
        $companyUser = User::factory()->create([
            'role' => 'company',
            'email' => 'company.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $companyOrg = Organization::query()->create([
            'name' => 'Chat Company Org',
            'type' => 'company',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $companyUser->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $companyRole = Role::query()->create([
            'tenant_id' => $companyOrg->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);

        OrganizationMembership::query()->create([
            'organization_id' => $companyOrg->id,
            'user_id' => $companyUser->id,
            'role_id' => $companyRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);

        $companyUser->forceFill([
            'tenant_id' => $companyOrg->id,
            'role_id' => $companyRole->id,
        ])->save();

        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'Chat Company',
            'verification_status' => $verificationStatus,
        ]);

        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'title' => 'Chat Internship',
            'status' => 'active',
        ]);

        return compact('companyUser', 'company', 'companyOrg', 'internship');
    }

    public function test_list_returns_only_participant_conversations(): void
    {
        $linked = $this->createLinkedStudentAndSchool();
        Sanctum::actingAs($linked['studentUser']);

        $own = $this->postJson('/api/conversations', [
            'peerUserId' => $linked['schoolUser']->id,
        ]);
        $own->assertCreated();

        $strangerA = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $strangerB = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $foreign = Conversation::query()->create();
        ConversationParticipant::query()->create(['conversation_id' => $foreign->id, 'user_id' => $strangerA->id]);
        ConversationParticipant::query()->create(['conversation_id' => $foreign->id, 'user_id' => $strangerB->id]);

        $response = $this->getJson('/api/conversations');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains((string) $own->json('data.id'), $ids);
        $this->assertNotContains((string) $foreign->id, $ids);
    }

    public function test_student_can_open_conversation_with_school(): void
    {
        $linked = $this->createLinkedStudentAndSchool();
        Sanctum::actingAs($linked['studentUser']);

        $response = $this->postJson('/api/conversations', [
            'peerUserId' => $linked['schoolUser']->id,
        ]);

        $response->assertCreated();
        $this->assertSame((string) $linked['schoolUser']->id, $response->json('data.peer.id'));
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
    }

    public function test_student_can_open_conversation_with_company_via_endorsed_application(): void
    {
        $linked = $this->createLinkedStudentAndSchool();
        $company = $this->createCompany();

        Application::query()->create([
            'internship_id' => $company['internship']->id,
            'student_id' => $linked['student']->id,
            'company_id' => $company['company']->id,
            'status' => 'endorsed',
            'internship_title' => 'Chat Internship',
            'student_name' => $linked['studentUser']->name,
            'student_email' => $linked['studentUser']->email,
        ]);

        Sanctum::actingAs($linked['studentUser']);

        $response = $this->postJson('/api/conversations', [
            'peerUserId' => $company['companyUser']->id,
        ]);

        $response->assertCreated();
        $this->assertSame((string) $company['companyUser']->id, $response->json('data.peer.id'));
    }

    public function test_school_can_open_conversation_with_company_via_active_agreement(): void
    {
        $linked = $this->createLinkedStudentAndSchool();
        $company = $this->createCompany();

        Contract::query()->create([
            'school_user_id' => $linked['schoolUser']->id,
            'school_name' => $linked['school']->institution_name,
            'company_user_id' => $company['companyUser']->id,
            'company_name' => $company['company']->company_name,
            'requested_by_role' => 'school',
            'requester_organization_id' => $linked['schoolOrg']->id,
            'partner_organization_id' => $company['companyOrg']->id,
            'status' => 'active',
            'subject' => 'MOA',
        ]);

        Sanctum::actingAs($linked['schoolUser']);

        $response = $this->postJson('/api/conversations', [
            'peerUserId' => $company['companyUser']->id,
        ]);

        $response->assertCreated();
        $this->assertSame((string) $company['companyUser']->id, $response->json('data.peer.id'));
    }

    public function test_unrelated_users_cannot_open_conversation(): void
    {
        $linked = $this->createLinkedStudentAndSchool();
        $company = $this->createCompany();
        Sanctum::actingAs($linked['studentUser']);

        $before = Conversation::query()->count();
        $response = $this->postJson('/api/conversations', [
            'peerUserId' => $company['companyUser']->id,
        ]);

        $response->assertForbidden();
        $this->assertSame($before, Conversation::query()->count());
    }

    public function test_existing_conversation_is_reused(): void
    {
        $linked = $this->createLinkedStudentAndSchool();
        Sanctum::actingAs($linked['studentUser']);

        $first = $this->postJson('/api/conversations', [
            'peerUserId' => $linked['schoolUser']->id,
        ]);
        $first->assertCreated();

        $second = $this->postJson('/api/conversations', [
            'peerUserId' => $linked['schoolUser']->id,
        ]);
        $second->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
    }

    public function test_non_participant_cannot_read_or_send_messages(): void
    {
        $linked = $this->createLinkedStudentAndSchool();
        Sanctum::actingAs($linked['studentUser']);
        $created = $this->postJson('/api/conversations', [
            'peerUserId' => $linked['schoolUser']->id,
        ])->json('data.id');

        $outsider = User::factory()->create([
            'role' => 'student',
            'email' => 'outsider.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        Sanctum::actingAs($outsider);

        $this->getJson('/api/conversations/'.$created.'/messages')->assertForbidden();

        $before = Message::query()->count();
        $this->postJson('/api/conversations/'.$created.'/messages', [
            'body' => 'Should fail',
        ])->assertForbidden();
        $this->assertSame($before, Message::query()->count());

        $this->patchJson('/api/conversations/'.$created.'/read')->assertForbidden();
        $peerParticipant = ConversationParticipant::query()
            ->where('conversation_id', $created)
            ->where('user_id', $linked['schoolUser']->id)
            ->firstOrFail();
        $this->assertNull($peerParticipant->fresh()->last_read_at);
    }

    public function test_participant_can_send_message_and_sender_cannot_be_spoofed(): void
    {
        $linked = $this->createLinkedStudentAndSchool();
        Sanctum::actingAs($linked['studentUser']);
        $conversationId = $this->postJson('/api/conversations', [
            'peerUserId' => $linked['schoolUser']->id,
        ])->json('data.id');

        $response = $this->postJson('/api/conversations/'.$conversationId.'/messages', [
            'body' => '  Hello school  ',
            'sender_id' => $linked['schoolUser']->id,
            'senderId' => $linked['schoolUser']->id,
        ]);

        $response->assertCreated();
        $this->assertSame((string) $linked['studentUser']->id, $response->json('data.senderId'));
        $this->assertSame('Hello school', $response->json('data.body'));
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $linked['studentUser']->id,
            'body' => 'Hello school',
        ]);
    }

    public function test_empty_message_is_rejected(): void
    {
        $linked = $this->createLinkedStudentAndSchool();
        Sanctum::actingAs($linked['studentUser']);
        $conversationId = $this->postJson('/api/conversations', [
            'peerUserId' => $linked['schoolUser']->id,
        ])->json('data.id');

        $this->postJson('/api/conversations/'.$conversationId.'/messages', [
            'body' => '   ',
        ])->assertStatus(422);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_participant_can_update_own_read_state_only(): void
    {
        $linked = $this->createLinkedStudentAndSchool();
        Sanctum::actingAs($linked['studentUser']);
        $conversationId = $this->postJson('/api/conversations', [
            'peerUserId' => $linked['schoolUser']->id,
        ])->json('data.id');

        $peerParticipant = ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $linked['schoolUser']->id)
            ->firstOrFail();
        $this->assertNull($peerParticipant->last_read_at);

        $response = $this->patchJson('/api/conversations/'.$conversationId.'/read');
        $response->assertOk();
        $this->assertNotNull($response->json('data.lastReadAt'));

        $own = ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $linked['studentUser']->id)
            ->firstOrFail();
        $peerParticipant->refresh();

        $this->assertNotNull($own->last_read_at);
        $this->assertNull($peerParticipant->last_read_at);
    }

    public function test_pending_school_cannot_use_chat(): void
    {
        $linked = $this->createLinkedStudentAndSchool('pending');
        Sanctum::actingAs($linked['schoolUser']);

        $response = $this->postJson('/api/conversations', [
            'peerUserId' => $linked['studentUser']->id,
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('code', 'organization_verification_pending');
    }

    public function test_student_can_chat_even_if_school_is_pending(): void
    {
        $linked = $this->createLinkedStudentAndSchool('pending');
        Sanctum::actingAs($linked['studentUser']);

        $response = $this->postJson('/api/conversations', [
            'peerUserId' => $linked['schoolUser']->id,
        ]);

        // Student is not gated by org.verified; school peer may still be messaged if relationship exists.
        // Opening succeeds for student; school side cannot initiate while pending (covered above).
        $response->assertCreated();
    }

    public function test_disabled_user_cannot_use_chat(): void
    {
        $linked = $this->createLinkedStudentAndSchool();
        $linked['studentUser']->forceFill(['is_active' => false])->save();
        Sanctum::actingAs($linked['studentUser']);

        $this->getJson('/api/conversations')->assertForbidden();
        $this->assertSame('Account Disabled', $this->getJson('/api/conversations')->json('message'));
    }

    public function test_guest_cannot_use_chat(): void
    {
        $guest = User::factory()->create([
            'role' => 'guest',
            'email' => 'guest.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        Sanctum::actingAs($guest);

        $this->getJson('/api/conversations')->assertForbidden();
        $this->postJson('/api/conversations', ['peerUserId' => 1])->assertForbidden();
    }

    public function test_submitted_application_alone_does_not_authorize_student_company_chat(): void
    {
        $linked = $this->createLinkedStudentAndSchool();
        $company = $this->createCompany();

        Application::query()->create([
            'internship_id' => $company['internship']->id,
            'student_id' => $linked['student']->id,
            'company_id' => $company['company']->id,
            'status' => 'submitted',
            'internship_title' => 'Chat Internship',
            'student_name' => $linked['studentUser']->name,
            'student_email' => $linked['studentUser']->email,
        ]);

        Sanctum::actingAs($linked['studentUser']);

        $this->postJson('/api/conversations', [
            'peerUserId' => $company['companyUser']->id,
        ])->assertForbidden();
    }
}
