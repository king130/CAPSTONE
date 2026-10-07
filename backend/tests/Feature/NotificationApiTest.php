<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_returns_only_authenticated_user_notifications(): void
    {
        $userA = User::factory()->create([
            'email' => 'notif-a.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $userB = User::factory()->create([
            'email' => 'notif-b.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $own = Notification::query()->create([
            'user_id' => $userA->id,
            'title' => 'Own notification',
            'body' => 'Visible to A',
            'channel' => 'in_app',
            'metadata' => ['type' => 'system'],
        ]);

        Notification::query()->create([
            'user_id' => $userB->id,
            'title' => 'Other notification',
            'body' => 'Visible to B',
            'channel' => 'in_app',
            'metadata' => ['type' => 'system'],
        ]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/notifications');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$own->id], $ids);
        $response->assertJsonPath('data.0.title', 'Own notification');
        $response->assertJsonPath('data.0.body', 'Visible to A');
        $response->assertJsonPath('data.0.type', 'system');
        $this->assertArrayHasKey('readAt', $response->json('data.0'));
        $this->assertArrayHasKey('createdAt', $response->json('data.0'));
    }

    public function test_user_can_mark_own_notification_read(): void
    {
        $user = User::factory()->create([
            'email' => 'notif-read.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'title' => 'Unread',
            'body' => 'Please read',
            'channel' => 'in_app',
            'read_at' => null,
            'metadata' => ['type' => 'subscription_overage', 'redirectTo' => '/organization/subscription'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/notifications/'.$notification->id.'/read');

        $response->assertOk();
        $response->assertJsonPath('message', 'Notification marked as read');
        $response->assertJsonPath('id', (string) $notification->id);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);

        $alreadyRead = $this->patchJson('/api/notifications/'.$notification->id.'/read');
        $alreadyRead->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_read(): void
    {
        $owner = User::factory()->create([
            'email' => 'notif-owner.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $other = User::factory()->create([
            'email' => 'notif-other.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $notification = Notification::query()->create([
            'user_id' => $owner->id,
            'title' => 'Private',
            'body' => 'Owner only',
            'channel' => 'in_app',
            'read_at' => null,
            'metadata' => ['type' => 'system'],
        ]);

        Sanctum::actingAs($other);

        $response = $this->patchJson('/api/notifications/'.$notification->id.'/read');

        $response->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->patchJson('/api/notifications/1/read')->assertUnauthorized();
    }
}
