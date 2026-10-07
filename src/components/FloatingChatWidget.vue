<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { MessageCircle } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import {
  MagnifyingGlassIcon,
  PaperAirplaneIcon,
  PlusIcon,
  FaceSmileIcon,
  XMarkIcon,
  ChatBubbleLeftRightIcon,
} from '@heroicons/vue/24/outline'
import {
  ChatApiError,
  listConversations,
  listMessages,
  markConversationRead,
  openConversation,
  sendChatMessage,
  type ChatConversation,
  type ChatMessage,
} from '@/services/chat'
import { listPublicProfiles, type PublicProfile } from '@/services/profilesPublic'

const props = defineProps<{
  userType?: 'intern' | 'company' | 'school'
}>()

const authStore = useAuthStore()
const isOpen = ref(false)
const newMessage = ref('')
const searchQuery = ref('')

type ConversationItem = {
  id: string
  name: string
  subtitle: string
  avatar: string
  lastMessage: string
  time: string
  unread: number
  online: boolean
  isGroup: boolean
}

type MessageItem = {
  id: string
  sender: string
  avatar: string
  message: string
  time: string
  isOwn: boolean
}

const conversations = ref<ConversationItem[]>([])
const conversationById = ref<Record<string, ChatConversation>>({})
const selectedConversation = ref<string | null>(null)
const rawMessages = ref<ChatMessage[]>([])

const conversationsLoading = ref(false)
const conversationsError = ref<string | null>(null)
const messagesLoading = ref(false)
const messagesError = ref<string | null>(null)
const messagesUnauthorized = ref(false)
const sending = ref(false)
const sendError = ref<string | null>(null)
const newChatError = ref<string | null>(null)
const showNewChatPanel = ref(false)
const chatPartners = ref<PublicProfile[]>([])
const partnersLoading = ref(false)
const startingChat = ref(false)

const currentUserId = computed(() => authStore.user?.uid || '')
const POLL_MS = 10000
let pollTimer: ReturnType<typeof setInterval> | null = null
let listPollTimer: ReturnType<typeof setInterval> | null = null
let loadMessagesSeq = 0

const unreadCount = computed(() =>
  conversations.value.reduce((sum, c) => sum + (c.unread > 0 ? c.unread : 0), 0),
)

function toggleChat() {
  isOpen.value = !isOpen.value
}

function closeChat() {
  isOpen.value = false
}

function openChat() {
  isOpen.value = true
}

function formatMessageTime(iso: string | null): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function formatListTime(iso: string | null): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function mapConversation(thread: ChatConversation): ConversationItem {
  const peerName = thread.peer?.name || 'Chat'
  const peerRole = thread.peer?.role || 'Direct Chat'
  return {
    id: thread.id,
    name: peerName,
    subtitle: peerRole,
    avatar: '/icons/logo-main.png',
    lastMessage: thread.latestMessage?.body || 'No messages yet',
    time: formatListTime(thread.latestMessage?.createdAt || thread.updatedAt),
    unread: thread.unreadCount || 0,
    online: false,
    isGroup: false,
  }
}

function applyConversations(list: ChatConversation[]) {
  conversationById.value = Object.fromEntries(list.map((c) => [c.id, c]))
  conversations.value = list.map(mapConversation)
}

function mapMessage(message: ChatMessage): MessageItem {
  const isOwn = message.senderId === currentUserId.value
  const peerName = selectedConversation.value
    ? conversationById.value[selectedConversation.value]?.peer?.name || 'User'
    : 'User'
  return {
    id: message.id,
    sender: isOwn ? 'You' : peerName,
    avatar: '/icons/logo-main.png',
    message: message.body,
    time: formatMessageTime(message.createdAt),
    isOwn,
  }
}

const filteredConversations = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return conversations.value
  return conversations.value.filter(
    (c) =>
      c.name.toLowerCase().includes(q) ||
      c.lastMessage.toLowerCase().includes(q) ||
      c.subtitle.toLowerCase().includes(q),
  )
})

const activeConversation = computed(() =>
  conversations.value.find((c) => c.id === selectedConversation.value) || null,
)

const messages = computed(() => rawMessages.value.map(mapMessage))

function stopMessagePolling() {
  if (pollTimer !== null) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

function stopListPolling() {
  if (listPollTimer !== null) {
    clearInterval(listPollTimer)
    listPollTimer = null
  }
}

function stopAllPolling() {
  stopMessagePolling()
  stopListPolling()
}

function canPoll(): boolean {
  if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
    return false
  }
  return isOpen.value
}

function startMessagePolling(conversationId: string) {
  stopMessagePolling()
  pollTimer = setInterval(() => {
    if (!canPoll()) return
    if (selectedConversation.value !== conversationId) return
    void refreshMessages(conversationId, true)
  }, POLL_MS)
}

function startListPolling() {
  stopListPolling()
  listPollTimer = setInterval(() => {
    if (!canPoll()) return
    void loadConversations(true)
  }, POLL_MS)
}

async function loadConversations(silent = false) {
  if (!currentUserId.value) {
    conversations.value = []
    conversationById.value = {}
    if (!silent) {
      conversationsError.value = 'Sign in to use chat.'
    }
    return
  }

  if (!silent) {
    conversationsLoading.value = true
    conversationsError.value = null
  }

  try {
    const list = await listConversations()
    applyConversations(list)
    conversationsError.value = null
  } catch (caught) {
    const message = caught instanceof Error ? caught.message : 'Failed to load conversations'
    if (!silent) {
      conversations.value = []
      conversationById.value = {}
      conversationsError.value = message
    }
  } finally {
    if (!silent) {
      conversationsLoading.value = false
    }
  }
}

async function refreshMessages(conversationId: string, silent = false) {
  try {
    const list = await listMessages(conversationId)
    if (selectedConversation.value !== conversationId) return
    rawMessages.value = list
    messagesError.value = null
    messagesUnauthorized.value = false
  } catch (caught) {
    if (selectedConversation.value !== conversationId) return
    const err = caught instanceof ChatApiError ? caught : null
    if (err?.isForbidden) {
      selectedConversation.value = null
      rawMessages.value = []
      messagesUnauthorized.value = true
      messagesError.value = "You don't have access to this conversation."
      stopMessagePolling()
      return
    }
    if (!silent) {
      rawMessages.value = []
      messagesError.value = caught instanceof Error ? caught.message : 'Failed to load messages'
    }
  }
}

async function loadConversationMessages(conversationId: string) {
  const seq = ++loadMessagesSeq
  messagesLoading.value = true
  messagesError.value = null
  messagesUnauthorized.value = false
  sendError.value = null
  rawMessages.value = []

  try {
    const list = await listMessages(conversationId)
    if (seq !== loadMessagesSeq || selectedConversation.value !== conversationId) return
    rawMessages.value = list
  } catch (caught) {
    if (seq !== loadMessagesSeq || selectedConversation.value !== conversationId) return
    const err = caught instanceof ChatApiError ? caught : null
    if (err?.isForbidden) {
      selectedConversation.value = null
      rawMessages.value = []
      messagesUnauthorized.value = true
      messagesError.value = "You don't have access to this conversation."
      stopMessagePolling()
      return
    }
    rawMessages.value = []
    messagesError.value = caught instanceof Error ? caught.message : 'Failed to load messages'
  } finally {
    if (seq === loadMessagesSeq) {
      messagesLoading.value = false
    }
  }
}

async function markRead(conversationId: string) {
  try {
    const result = await markConversationRead(conversationId)
    const existing = conversationById.value[conversationId]
    if (existing) {
      conversationById.value = {
        ...conversationById.value,
        [conversationId]: {
          ...existing,
          lastReadAt: result.lastReadAt,
          unreadCount: 0,
        },
      }
    }
    conversations.value = conversations.value.map((c) =>
      c.id === conversationId ? { ...c, unread: 0 } : c,
    )
  } catch {
    // Non-blocking for MVP; unread may stay until next list refresh.
  }
}

async function selectConversation(id: string) {
  if (selectedConversation.value === id && rawMessages.value.length > 0 && !messagesError.value) {
    return
  }
  selectedConversation.value = id
  stopMessagePolling()
  await loadConversationMessages(id)
  if (selectedConversation.value !== id) return
  if (!messagesError.value) {
    await markRead(id)
    startMessagePolling(id)
  }
}

async function sendMessage() {
  const conversationId = selectedConversation.value
  if (!conversationId || sending.value) return
  const content = newMessage.value.trim()
  if (!content) return

  sending.value = true
  sendError.value = null
  try {
    const saved = await sendChatMessage(conversationId, content)
    newMessage.value = ''
    if (!rawMessages.value.some((m) => m.id === saved.id)) {
      rawMessages.value = [...rawMessages.value, saved]
    }
    conversations.value = conversations.value.map((c) =>
      c.id === conversationId
        ? {
            ...c,
            lastMessage: saved.body,
            time: formatListTime(saved.createdAt),
            unread: 0,
          }
        : c,
    )
    const existing = conversationById.value[conversationId]
    if (existing) {
      conversationById.value = {
        ...conversationById.value,
        [conversationId]: {
          ...existing,
          latestMessage: {
            id: saved.id,
            body: saved.body,
            senderId: saved.senderId,
            createdAt: saved.createdAt,
          },
          unreadCount: 0,
        },
      }
    }
    await markRead(conversationId)
  } catch (caught) {
    sendError.value = caught instanceof Error ? caught.message : 'Failed to send message'
  } finally {
    sending.value = false
  }
}

async function openNewChatPanel() {
  newChatError.value = null
  partnersLoading.value = true
  showNewChatPanel.value = true
  try {
    const role = props.userType || authStore.user?.role
    if (role === 'company') {
      chatPartners.value = await listPublicProfiles('school')
    } else if (role === 'school') {
      chatPartners.value = await listPublicProfiles('company')
    } else if (role === 'student' || role === 'intern') {
      const [schools, companies] = await Promise.all([
        listPublicProfiles('school'),
        listPublicProfiles('company'),
      ])
      chatPartners.value = [...schools, ...companies]
    } else {
      chatPartners.value = []
    }
  } catch (caught) {
    chatPartners.value = []
    newChatError.value = caught instanceof Error ? caught.message : 'Failed to load directory'
  } finally {
    partnersLoading.value = false
  }
}

async function startChatWith(partner: PublicProfile) {
  const uid = currentUserId.value
  if (!uid || uid === partner.uid || startingChat.value) return

  startingChat.value = true
  newChatError.value = null
  try {
    const conversation = await openConversation(partner.uid)
    showNewChatPanel.value = false
    const mapped = mapConversation(conversation)
    conversationById.value = {
      ...conversationById.value,
      [conversation.id]: conversation,
    }
    const existingIdx = conversations.value.findIndex((c) => c.id === conversation.id)
    if (existingIdx >= 0) {
      const next = [...conversations.value]
      next[existingIdx] = mapped
      conversations.value = next
    } else {
      conversations.value = [mapped, ...conversations.value]
    }
    selectedConversation.value = conversation.id
    stopMessagePolling()
    await loadConversationMessages(conversation.id)
    if (selectedConversation.value === conversation.id && !messagesError.value) {
      await markRead(conversation.id)
      startMessagePolling(conversation.id)
    }
  } catch (caught) {
    const err = caught instanceof ChatApiError ? caught : null
    if (err?.isForbidden) {
      newChatError.value = "You can't start a conversation with this user."
    } else {
      newChatError.value = caught instanceof Error ? caught.message : 'Failed to start conversation'
    }
  } finally {
    startingChat.value = false
  }
}

function handleImageError(event: Event) {
  const img = event.target as HTMLImageElement
  const name = img.alt || 'User'
  const initials = name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)

  const colors = ['#2563eb', '#7c3aed', '#dc2626', '#059669', '#d97706', '#0891b2']
  const colorIndex = name.length % colors.length
  const color = colors[colorIndex]

  const svg =
    '<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg">' +
    `<rect width="100" height="100" fill="${color}"/>` +
    '<text x="50" y="50" font-family="Arial, sans-serif" font-size="36" font-weight="bold" ' +
    'fill="white" text-anchor="middle" dominant-baseline="central">' +
    initials +
    '</text></svg>'

  img.src = 'data:image/svg+xml;base64,' + btoa(svg)
}

function onVisibilityChange() {
  if (document.visibilityState === 'visible' && isOpen.value) {
    void loadConversations(true)
    if (selectedConversation.value) {
      void refreshMessages(selectedConversation.value, true)
    }
  }
}

watch(isOpen, async (open) => {
  if (open) {
    await loadConversations()
    startListPolling()
    if (selectedConversation.value) {
      await loadConversationMessages(selectedConversation.value)
      if (!messagesError.value) {
        await markRead(selectedConversation.value)
        startMessagePolling(selectedConversation.value)
      }
    }
  } else {
    stopAllPolling()
  }
})

watch(currentUserId, () => {
  stopAllPolling()
  conversations.value = []
  conversationById.value = {}
  rawMessages.value = []
  selectedConversation.value = null
  conversationsError.value = null
  messagesError.value = null
  sendError.value = null
  newChatError.value = null
  if (isOpen.value) {
    void loadConversations()
    startListPolling()
  }
})

onMounted(() => {
  window.addEventListener('chat:open', openChat)
  document.addEventListener('visibilitychange', onVisibilityChange)
})

onUnmounted(() => {
  stopAllPolling()
  window.removeEventListener('chat:open', openChat)
  document.removeEventListener('visibilitychange', onVisibilityChange)
})
</script>

<template>
  <!-- Floating Chat Launcher -->
  <button
    v-if="!isOpen"
    type="button"
    class="floating-chat-launcher"
    :aria-label="unreadCount > 0 ? `Open messages, ${unreadCount} unread` : 'Open messages'"
    @click="toggleChat"
  >
    <MessageCircle class="floating-chat-launcher__icon" aria-hidden="true" />
    <span v-if="unreadCount > 0" class="floating-chat-launcher__badge" aria-hidden="true">
      {{ unreadCount > 99 ? '99+' : unreadCount }}
    </span>
  </button>

  <!-- Full Screen Chat Widget -->
  <div v-if="isOpen" class="floating-chat-widget">
    <div class="main-chat-header">
      <div class="header-left">
        <ChatBubbleLeftRightIcon class="header-icon" />
        <h1 class="header-title">Messages</h1>
      </div>
      <div class="header-right">
        <button
          @click="closeChat"
          class="close-btn"
          type="button"
          aria-label="Close messages"
        >
          <XMarkIcon class="close-icon" />
        </button>
      </div>
    </div>

    <div class="chat-container">
      <aside class="profiles-sidebar">
        <div class="sidebar-header">
          <h2 class="sidebar-title">Chats</h2>
          <button class="btn-new-chat" @click="openNewChatPanel" title="New conversation" type="button">
            <PlusIcon class="icon-sm" />
          </button>
        </div>

        <div v-if="showNewChatPanel" class="new-chat-panel">
          <div class="new-chat-header">
            <span>Start conversation</span>
            <button class="btn-close-panel" @click="showNewChatPanel = false" type="button">
              <XMarkIcon class="icon-sm" />
            </button>
          </div>
          <p v-if="newChatError" class="chat-error-text">{{ newChatError }}</p>
          <p v-if="partnersLoading" class="chat-status-text">Loading directory...</p>
          <div v-else-if="chatPartners.length === 0" class="new-chat-empty">
            No users available. Ask them to complete their profile.
          </div>
          <div v-else class="new-chat-list">
            <div
              v-for="p in chatPartners"
              :key="p.uid"
              class="new-chat-item"
              :class="{ disabled: p.uid === currentUserId || startingChat }"
              @click="p.uid !== currentUserId && !startingChat && startChatWith(p)"
            >
              <span class="new-chat-name">{{ p.orgName || p.displayName }}</span>
              <span class="new-chat-role">{{ p.role }}</span>
            </div>
          </div>
        </div>

        <div class="sidebar-search">
          <div class="search-box">
            <MagnifyingGlassIcon class="search-icon" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search conversations..."
              class="search-input"
            />
          </div>
        </div>

        <p v-if="conversationsLoading" class="chat-status-text">Loading conversations...</p>
        <p v-else-if="conversationsError" class="chat-error-text">{{ conversationsError }}</p>
        <p v-else-if="filteredConversations.length === 0" class="chat-status-text">No conversations yet.</p>

        <div class="profiles-list">
          <div
            v-for="conversation in filteredConversations"
            :key="conversation.id"
            @click="selectConversation(conversation.id)"
            class="profile-item"
            :class="{ active: selectedConversation === conversation.id }"
          >
            <div class="profile-avatar-wrapper">
              <img
                :src="conversation.avatar"
                :alt="conversation.name"
                class="profile-avatar"
                @error="handleImageError"
              />
            </div>
            <div class="profile-info-sidebar">
              <div class="profile-row-top">
                <h3 class="profile-name-sidebar">{{ conversation.name }}</h3>
                <span v-if="conversation.time" class="profile-time">{{ conversation.time }}</span>
              </div>
              <div class="profile-row-bottom">
                <p class="profile-last-message">{{ conversation.lastMessage }}</p>
                <span v-if="conversation.unread > 0" class="sidebar-unread" :aria-label="`${conversation.unread} unread`">
                  {{ conversation.unread > 99 ? '99+' : conversation.unread }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </aside>

      <main class="main-area" v-if="activeConversation">
        <div class="profile-header">
          <div class="profile-info">
            <div class="profile-avatar-large-wrapper">
              <img
                :src="activeConversation.avatar"
                :alt="activeConversation.name"
                class="profile-avatar-large"
                @error="handleImageError"
              />
            </div>
            <div class="profile-details">
              <div class="profile-name-row">
                <h2 class="profile-name">{{ activeConversation.name }}</h2>
                <span class="status-pill">Direct</span>
              </div>
              <p class="profile-status">Conversation</p>
            </div>
          </div>
        </div>

        <div class="messages-section">
          <p v-if="messagesLoading" class="chat-status-text">Loading messages...</p>
          <p v-else-if="messagesError" class="chat-error-text">{{ messagesError }}</p>
          <div v-else-if="messages.length === 0" class="messages-container">
            <div class="empty-state-content" style="margin: auto; padding: 24px 0;">
              <h3 class="empty-title">No messages yet</h3>
              <p class="empty-text">Send a message to start the conversation</p>
            </div>
          </div>
          <div v-else class="messages-container">
            <div
              v-for="message in messages"
              :key="message.id"
              class="message-row"
              :class="{ 'own-message': message.isOwn }"
            >
              <div class="message-bubble">
                <p class="message-text">{{ message.message }}</p>
                <span class="message-timestamp">{{ message.time }}</span>
              </div>
            </div>
          </div>

          <p v-if="sendError" class="chat-error-text composer-error">{{ sendError }}</p>

          <div class="message-input-container">
            <button class="input-action-btn" title="Attach file" type="button" disabled>
              <PlusIcon class="input-icon" />
            </button>
            <input
              v-model="newMessage"
              type="text"
              placeholder="Type a message..."
              class="message-input-field"
              aria-label="Message text"
              :disabled="sending || messagesLoading || !!messagesError"
              @keyup.enter="sendMessage"
            />
            <button class="input-action-btn" title="Emoji" type="button" disabled aria-label="Emoji (unavailable)">
              <FaceSmileIcon class="input-icon" />
            </button>
            <button
              @click="sendMessage"
              class="send-message-btn"
              type="button"
              aria-label="Send message"
              :disabled="!newMessage.trim() || sending || messagesLoading || !!messagesError"
            >
              <PaperAirplaneIcon class="send-icon" />
            </button>
          </div>
        </div>
      </main>

      <div v-else class="empty-state">
        <div class="empty-state-content">
          <ChatBubbleLeftRightIcon class="empty-icon" />
          <h3 class="empty-title">{{ messagesUnauthorized ? 'Conversation unavailable' : 'Select a conversation' }}</h3>
          <p class="empty-text">
            {{
              messagesUnauthorized
                ? messagesError || "You don't have access to this conversation."
                : conversationsError
                  ? conversationsError
                  : 'Choose a conversation from the list to start messaging'
            }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Floating Chat Launcher — presentation only; chat panel styles unchanged below */
.floating-chat-launcher {
  position: fixed;
  right: 1.25rem;
  bottom: 1.25rem;
  z-index: 1000;
  display: inline-flex;
  height: 2.75rem;
  width: 2.75rem;
  align-items: center;
  justify-content: center;
  border-radius: calc(var(--radius) + 4px);
  border: 1px solid hsl(var(--border));
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  box-shadow: 0 8px 20px hsl(var(--foreground) / 0.12);
  cursor: pointer;
  transition:
    background-color 0.15s ease,
    color 0.15s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease,
    transform 0.15s ease;
}

.floating-chat-launcher:hover {
  background: hsl(var(--accent));
  color: hsl(var(--accent-foreground));
  border-color: hsl(var(--border));
  box-shadow: 0 10px 24px hsl(var(--foreground) / 0.14);
}

.floating-chat-launcher:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 2px hsl(var(--background)),
    0 0 0 4px hsl(var(--ring));
}

.floating-chat-launcher:active {
  transform: translateY(1px);
}

.floating-chat-launcher__icon {
  width: 1.25rem;
  height: 1.25rem;
  stroke-width: 2;
}

.floating-chat-launcher__badge {
  position: absolute;
  top: -0.35rem;
  right: -0.35rem;
  display: inline-flex;
  min-width: 1.15rem;
  height: 1.15rem;
  align-items: center;
  justify-content: center;
  padding: 0 0.3rem;
  border-radius: 9999px;
  border: 2px solid hsl(var(--background));
  background: hsl(var(--destructive));
  color: hsl(var(--destructive-foreground));
  font-size: 0.65rem;
  font-weight: 700;
  line-height: 1;
}

@media (max-width: 640px) {
  .floating-chat-launcher {
    right: 1rem;
    bottom: 1rem;
  }
}

/* Full Screen Chat Widget — OJT semantic surfaces; messenger blue removed */
.floating-chat-widget {
  --chat-send: hsl(var(--primary));
  --chat-send-fg: hsl(var(--primary-foreground));
  --chat-send-muted: hsl(var(--primary-foreground) / 0.75);
  --chat-accent: hsl(var(--primary));
  --chat-accent-soft: hsl(var(--accent));
  --chat-active: hsl(var(--accent));
  --chat-online: #31a24c;
  --chat-disabled-icon: hsl(var(--muted-foreground));

  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: hsl(var(--background));
  color: hsl(var(--foreground));
  display: flex;
  flex-direction: column;
  z-index: 1001;
  font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
}

/* Main Chat Header */
.main-chat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  background: hsl(var(--card));
  border-bottom: 1px solid hsl(var(--border));
  flex-shrink: 0;
  box-shadow: 0 1px 2px hsl(var(--foreground) / 0.05);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon {
  width: 28px;
  height: 28px;
  color: var(--chat-accent);
  stroke-width: 2;
}

.header-title {
  font-size: 1.375rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: hsl(var(--foreground));
  margin: 0;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.close-btn {
  width: 36px;
  height: 36px;
  background: none;
  border: none;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  padding: 0;
}

.close-btn:hover {
  background: hsl(var(--muted));
}

.close-btn:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 2px hsl(var(--background)),
    0 0 0 4px hsl(var(--ring));
}

.close-icon {
  width: 24px;
  height: 24px;
  color: hsl(var(--muted-foreground));
  stroke-width: 2.5;
}

/* Chat Container */
.chat-container {
  flex: 1;
  display: grid;
  grid-template-columns: 360px 1fr;
  overflow: hidden;
  background: hsl(var(--background));
}

/* Profiles Sidebar */
.profiles-sidebar {
  background: hsl(var(--card));
  border-right: 1px solid hsl(var(--border));
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.sidebar-header {
  padding: 16px 20px;
  border-bottom: 1px solid hsl(var(--border));
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.btn-new-chat {
  padding: 6px;
  border-radius: 8px;
  border: 1px solid hsl(var(--border));
  background: hsl(var(--muted));
  cursor: pointer;
  color: hsl(var(--foreground));
}
.btn-new-chat:hover { background: hsl(var(--accent)); }
.btn-new-chat:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 2px hsl(var(--background)),
    0 0 0 4px hsl(var(--ring));
}

.new-chat-panel {
  padding: 12px;
  border-bottom: 1px solid hsl(var(--border));
  background: hsl(var(--muted));
  max-height: 200px;
  overflow-y: auto;
}

.new-chat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  color: hsl(var(--foreground));
}

.btn-close-panel {
  padding: 4px;
  background: none;
  border: none;
  cursor: pointer;
  color: hsl(var(--muted-foreground));
}

.new-chat-empty {
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
  padding: 8px 0;
}

.new-chat-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.new-chat-item {
  padding: 10px 12px;
  border-radius: 8px;
  cursor: pointer;
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  transition: background 0.15s;
}
.new-chat-item:hover:not(.disabled) { background: var(--chat-accent-soft); }
.new-chat-item.disabled { opacity: 0.5; cursor: not-allowed; }

.new-chat-name { font-weight: 600; color: hsl(var(--foreground)); display: block; }
.new-chat-role { font-size: 0.75rem; color: hsl(var(--muted-foreground)); text-transform: capitalize; }

.icon-sm { width: 18px; height: 18px; }

.sidebar-title {
  font-size: 1.125rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: hsl(var(--foreground));
  margin: 0;
}

.sidebar-search {
  padding: 10px 16px 12px;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  width: 16px;
  height: 16px;
  color: hsl(var(--muted-foreground));
  stroke-width: 2;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 10px 12px 10px 36px;
  border: 1px solid hsl(var(--border));
  border-radius: calc(var(--radius) + 4px);
  font-size: 0.875rem;
  outline: none;
  transition: all 0.2s;
  background: hsl(var(--muted));
  color: hsl(var(--foreground));
}

.search-input::placeholder {
  color: hsl(var(--muted-foreground));
}

.search-input:focus {
  background: hsl(var(--accent));
  border-color: hsl(var(--border));
  box-shadow: 0 0 0 2px hsl(var(--ring) / 0.35);
}

/* Profiles List */
.profiles-list {
  flex: 1;
  overflow-y: auto;
  padding: 4px 10px 12px;
}

.profile-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  cursor: pointer;
  transition:
    background 0.15s ease,
    border-color 0.15s ease;
  position: relative;
  border-radius: calc(var(--radius) + 4px);
  border: 1px solid transparent;
  margin-bottom: 4px;
}

.profile-item:hover {
  background: hsl(var(--muted));
}

.profile-item.active {
  background: var(--chat-active);
  border-color: hsl(var(--primary) / 0.35);
}

.profile-item:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 2px hsl(var(--background)),
    0 0 0 4px hsl(var(--ring));
}

.profile-avatar-wrapper {
  position: relative;
  flex-shrink: 0;
}

.profile-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid hsl(var(--border));
}

.online-badge {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 14px;
  height: 14px;
  background: var(--chat-online);
  border: 3px solid hsl(var(--card));
  border-radius: 50%;
}

.profile-info-sidebar {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.profile-row-top,
.profile-row-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-width: 0;
}

.profile-name-sidebar {
  font-size: 0.875rem;
  font-weight: 600;
  color: hsl(var(--foreground));
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.profile-time {
  flex-shrink: 0;
  font-size: 0.7rem;
  font-weight: 500;
  color: hsl(var(--muted-foreground));
}

.profile-last-message {
  font-size: 0.8125rem;
  color: hsl(var(--muted-foreground));
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  min-width: 0;
}

/* Main Area */
.main-area {
  display: flex;
  flex-direction: column;
  background: hsl(var(--background));
  overflow: hidden;
}

/* Profile Header */
.profile-header {
  padding: 12px 24px;
  border-bottom: 1px solid hsl(var(--border));
  background: hsl(var(--card));
  box-shadow: 0 1px 2px hsl(var(--foreground) / 0.05);
}

.profile-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.profile-avatar-large-wrapper {
  position: relative;
}

.profile-avatar-large {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.online-badge-large {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 12px;
  height: 12px;
  background: var(--chat-online);
  border: 3px solid hsl(var(--card));
  border-radius: 50%;
}

.profile-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.profile-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.profile-name {
  font-size: 0.9375rem;
  font-weight: 600;
  color: hsl(var(--foreground));
  margin: 0;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.status-pill {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  padding: 0.1rem 0.5rem;
  border-radius: 9999px;
  border: 1px solid hsl(var(--border));
  background: hsl(var(--accent));
  color: hsl(var(--accent-foreground));
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1.4;
}

.profile-status {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  margin: 0;
  line-height: 1.3;
}

/* Messages Section */
.messages-section {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.messages-container {
  flex: 1;
  overflow-y: auto;
  padding: 16px 24px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: hsl(var(--background));
}

.message-row {
  display: flex;
  justify-content: flex-start;
  margin-bottom: 4px;
}

.message-row.own-message {
  justify-content: flex-end;
}

.message-bubble {
  max-width: 65%;
  padding: 10px 14px;
  border-radius: 16px 16px 16px 6px;
  background: hsl(var(--muted));
  border: 1px solid hsl(var(--border) / 0.7);
  position: relative;
}

.message-row.own-message .message-bubble {
  background: var(--chat-send);
  border-color: transparent;
  border-radius: 16px 16px 6px 16px;
}

.message-text {
  font-size: 0.9375rem;
  color: hsl(var(--foreground));
  margin: 0;
  line-height: 1.45;
  word-wrap: break-word;
}

.message-row.own-message .message-text {
  color: var(--chat-send-fg);
}

.message-timestamp {
  font-size: 0.6875rem;
  color: hsl(var(--muted-foreground));
  margin-top: 6px;
  display: block;
}

.message-row.own-message .message-timestamp {
  color: var(--chat-send-muted);
}

/* Message Input */
.message-input-container {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 16px 16px;
  padding: 8px 10px 8px 8px;
  border: 1px solid hsl(var(--border));
  border-radius: calc(var(--radius) + 6px);
  background: hsl(var(--card));
}

.input-action-btn {
  width: 36px;
  height: 36px;
  border: none;
  background: none;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
  padding: 0;
}

.input-action-btn:hover {
  background: hsl(var(--muted));
}

.input-action-btn:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 2px hsl(var(--background)),
    0 0 0 4px hsl(var(--ring));
}

.input-icon {
  width: 18px;
  height: 18px;
  color: hsl(var(--muted-foreground));
  stroke-width: 2;
}

.message-input-field {
  flex: 1;
  padding: 10px 12px;
  border: 1px solid transparent;
  border-radius: calc(var(--radius) + 2px);
  font-size: 0.9375rem;
  outline: none;
  background: hsl(var(--muted));
  color: hsl(var(--foreground));
  transition: all 0.2s;
  resize: none;
  max-height: 100px;
  line-height: 1.4;
}

.message-input-field::placeholder {
  color: hsl(var(--muted-foreground));
}

.message-input-field:focus {
  background: hsl(var(--background));
  border-color: hsl(var(--border));
  box-shadow: 0 0 0 2px hsl(var(--ring) / 0.25);
}

.send-message-btn {
  width: 38px;
  height: 38px;
  border: none;
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  border-radius: calc(var(--radius) + 2px);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
  padding: 0;
}

.send-message-btn:hover:not(:disabled) {
  opacity: 0.92;
}

.send-message-btn:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 2px hsl(var(--background)),
    0 0 0 4px hsl(var(--ring));
}

.send-message-btn:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.send-icon {
  width: 18px;
  height: 18px;
  color: hsl(var(--primary-foreground));
  stroke-width: 2;
}

.send-message-btn:disabled .send-icon {
  color: hsl(var(--primary-foreground));
}

/* Empty State */
.empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  background: hsl(var(--background));
}

.empty-state-content {
  text-align: center;
  max-width: 320px;
}

.empty-icon {
  width: 64px;
  height: 64px;
  color: hsl(var(--muted-foreground));
  stroke-width: 1.5;
  margin: 0 auto 16px;
}

.empty-title {
  font-size: 20px;
  font-weight: 600;
  color: hsl(var(--foreground));
  margin: 0 0 8px 0;
}

.empty-text {
  font-size: 14px;
  color: hsl(var(--muted-foreground));
  margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
  .chat-container {
    grid-template-columns: 1fr;
  }
  
  .profiles-sidebar {
    display: none;
  }
  
  .message-bubble {
    max-width: 80%;
  }
  
  .profile-header {
    padding: 16px 20px;
  }
  
  .profile-avatar-large {
    width: 60px;
    height: 60px;
  }
  
  .profile-name {
    font-size: 20px;
  }
  
  .messages-container {
    padding: 16px 20px;
  }
  
  .message-input-container {
    margin: 0 12px 12px;
  }
}

.chat-status-text {
  margin: 8px 16px;
  font-size: 13px;
  color: hsl(var(--muted-foreground));
}
.chat-error-text {
  margin: 8px 16px;
  font-size: 13px;
  color: hsl(var(--destructive));
}
.composer-error {
  margin: 0 24px 12px;
}
.sidebar-unread {
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  border-radius: 999px;
  min-width: 1.25rem;
  height: 1.25rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 0 0.35rem;
  flex-shrink: 0;
}
.input-action-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
</style>
