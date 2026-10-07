<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatRelationshipGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    private const MAX_BODY_LENGTH = 5000;

    public function __construct(
        private readonly ChatRelationshipGate $relationships,
    ) {
    }

    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        if ($denied = $this->denyNonChatActor($user)) {
            return $denied;
        }

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
            ->with([
                'participants.user:id,name,email,role',
                'messages' => fn ($query) => $query->latest('id')->limit(1),
            ])
            ->get()
            ->sortByDesc(function (Conversation $conversation) {
                $latest = $conversation->messages->first();

                return $latest?->created_at?->getTimestamp() ?? $conversation->updated_at?->getTimestamp() ?? 0;
            })
            ->values()
            ->map(fn (Conversation $conversation) => $this->serializeConversation($conversation, $user));

        return response()->json(['data' => $conversations]);
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        if ($denied = $this->denyNonChatActor($user)) {
            return $denied;
        }

        $data = $request->validate([
            'peerUserId' => ['required', 'integer', 'exists:users,id'],
        ]);

        $peer = User::query()->findOrFail($data['peerUserId']);

        if ((int) $peer->id === (int) $user->id) {
            return response()->json(['message' => 'You cannot start a conversation with yourself.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->relationships->canCommunicate($user, $peer)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $conversation = $this->findDirectConversation((int) $user->id, (int) $peer->id);

        if (! $conversation) {
            $conversation = DB::transaction(function () use ($user, $peer) {
                $created = Conversation::query()->create();
                ConversationParticipant::query()->create([
                    'conversation_id' => $created->id,
                    'user_id' => $user->id,
                    'last_read_at' => null,
                ]);
                ConversationParticipant::query()->create([
                    'conversation_id' => $created->id,
                    'user_id' => $peer->id,
                    'last_read_at' => null,
                ]);

                return $created;
            });
        }

        $conversation->load([
            'participants.user:id,name,email,role',
            'messages' => fn ($query) => $query->latest('id')->limit(1),
        ]);

        return response()->json([
            'data' => $this->serializeConversation($conversation, $user),
        ], Response::HTTP_CREATED);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        /** @var User $user */
        $user = $request->user();
        if ($denied = $this->denyNonChatActor($user)) {
            return $denied;
        }

        if (! $this->isParticipant($conversation, $user)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Message $message) => $this->serializeMessage($message));

        return response()->json(['data' => $messages]);
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        /** @var User $user */
        $user = $request->user();
        if ($denied = $this->denyNonChatActor($user)) {
            return $denied;
        }

        if (! $this->isParticipant($conversation, $user)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.self::MAX_BODY_LENGTH],
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            return response()->json([
                'message' => 'The message body cannot be empty.',
                'errors' => ['body' => ['The message body cannot be empty.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $body,
        ]);

        $conversation->touch();

        return response()->json([
            'data' => $this->serializeMessage($message),
        ], Response::HTTP_CREATED);
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        /** @var User $user */
        $user = $request->user();
        if ($denied = $this->denyNonChatActor($user)) {
            return $denied;
        }

        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $participant) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $participant->forceFill(['last_read_at' => now()])->save();

        return response()->json([
            'data' => [
                'conversationId' => (string) $conversation->id,
                'lastReadAt' => $participant->last_read_at?->toIso8601String(),
            ],
        ]);
    }

    private function denyNonChatActor(User $user): ?\Illuminate\Http\JsonResponse
    {
        $appRole = $user->effectiveAppRole();
        if (! in_array($appRole, ['student', 'school', 'company'], true)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function isParticipant(Conversation $conversation, User $user): bool
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function findDirectConversation(int $userIdA, int $userIdB): ?Conversation
    {
        $candidates = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userIdA))
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userIdB))
            ->with('participants:id,conversation_id,user_id')
            ->get();

        return $candidates->first(function (Conversation $conversation) use ($userIdA, $userIdB) {
            $participantIds = $conversation->participants->pluck('user_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

            return $participantIds === collect([$userIdA, $userIdB])->sort()->values()->all();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeConversation(Conversation $conversation, User $viewer): array
    {
        $conversation->loadMissing([
            'participants.user:id,name,email,role',
            'messages' => fn ($query) => $query->latest('id')->limit(1),
        ]);

        $viewerParticipant = $conversation->participants->firstWhere('user_id', $viewer->id);
        $peerParticipant = $conversation->participants->first(fn (ConversationParticipant $participant) => (int) $participant->user_id !== (int) $viewer->id);
        $peer = $peerParticipant?->user;
        $latest = $conversation->messages->first();

        $lastReadAt = $viewerParticipant?->last_read_at;
        $unreadCount = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $viewer->id)
            ->when(
                $lastReadAt,
                fn ($query) => $query->where('created_at', '>', $lastReadAt),
                fn ($query) => $query
            )
            ->count();

        return [
            'id' => (string) $conversation->id,
            'peer' => $peer ? [
                'id' => (string) $peer->id,
                'name' => $peer->name,
                'role' => $peer->effectiveAppRole() ?? $peer->role,
            ] : null,
            'latestMessage' => $latest ? [
                'id' => (string) $latest->id,
                'body' => $latest->body,
                'senderId' => (string) $latest->sender_id,
                'createdAt' => $latest->created_at?->toIso8601String(),
            ] : null,
            'lastReadAt' => $lastReadAt?->toIso8601String(),
            'unreadCount' => $unreadCount,
            'updatedAt' => $conversation->updated_at?->toIso8601String(),
            'createdAt' => $conversation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(Message $message): array
    {
        return [
            'id' => (string) $message->id,
            'conversationId' => (string) $message->conversation_id,
            'senderId' => (string) $message->sender_id,
            'body' => $message->body,
            'createdAt' => $message->created_at?->toIso8601String(),
        ];
    }
}
