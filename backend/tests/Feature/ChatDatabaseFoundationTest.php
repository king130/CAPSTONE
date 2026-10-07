<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatDatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_can_have_two_participants(): void
    {
        $userA = User::factory()->create(['email' => 'chat-a.'.uniqid('', true).'@example.com']);
        $userB = User::factory()->create(['email' => 'chat-b.'.uniqid('', true).'@example.com']);

        $conversation = Conversation::query()->create();

        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $userA->id,
            'last_read_at' => null,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $userB->id,
            'last_read_at' => null,
        ]);

        $conversation->load('participants');

        $this->assertCount(2, $conversation->participants);
        $this->assertEqualsCanonicalizing(
            [$userA->id, $userB->id],
            $conversation->participants->pluck('user_id')->all()
        );
    }

    public function test_duplicate_participant_in_same_conversation_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'chat-dup.'.uniqid('', true).'@example.com']);
        $conversation = Conversation::query()->create();

        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(QueryException::class);

        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_message_belongs_to_conversation_and_sender(): void
    {
        $sender = User::factory()->create(['email' => 'chat-sender.'.uniqid('', true).'@example.com']);
        $peer = User::factory()->create(['email' => 'chat-peer.'.uniqid('', true).'@example.com']);
        $conversation = Conversation::query()->create();

        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $peer->id,
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => 'Hello from the chat foundation test.',
        ]);

        $message->load(['conversation', 'sender']);

        $this->assertTrue($message->conversation->is($conversation));
        $this->assertTrue($message->sender->is($sender));
        $this->assertSame('Hello from the chat foundation test.', $message->body);
        $this->assertCount(1, $conversation->fresh()->messages);
    }

    public function test_last_read_at_is_nullable(): void
    {
        $user = User::factory()->create(['email' => 'chat-read.'.uniqid('', true).'@example.com']);
        $conversation = Conversation::query()->create();

        $participant = ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'last_read_at' => null,
        ]);

        $this->assertNull($participant->fresh()->last_read_at);

        $participant->forceFill(['last_read_at' => now()])->save();
        $this->assertNotNull($participant->fresh()->last_read_at);
    }
}
