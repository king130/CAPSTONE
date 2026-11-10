<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { ArrowLeft, MessageCircle, Paperclip, Plus, Search, Send, Smile, X } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useFloatingChat } from '@/composables/useFloatingChat'
import {
  ChatApiError,
  listConversations,
  listEligibleChatPartners,
  listMessages,
  markConversationRead,
  openConversation,
  sendChatMessage,
  type ChatConversation,
  type ChatMessage,
} from '@/services/chat'
import type { PublicProfile } from '@/services/profilesPublic'

defineProps<{
  userType?: 'intern' | 'company' | 'school'
}>()

const authStore = useAuthStore()
const floatingChat = useFloatingChat()
const isOpen = floatingChat.isOpen
const newMessage = ref('')
const searchQuery = ref('')
const FETCH_TIMEOUT_MS = 20000
const messagesContainer = ref<HTMLElement | null>(null)
const composerField = ref<HTMLTextAreaElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const pendingAttachment = ref<File | null>(null)
const pendingAttachmentPreview = ref<string | null>(null)
const showEmojiPanel = ref(false)
const stickToBottom = ref(true)
let sendLock = false

const EMOJI_QUICK = ['😀', '😂', '😍', '👍', '🙏', '🎉', '🔥', '✅', '👋', '💯', '😢', '🤝']

async function withTimeout<T>(promise: Promise<T>, label: string): Promise<T> {
  let timer: ReturnType<typeof setTimeout> | undefined
  try {
    return await Promise.race([
      promise,
      new Promise<T>((_, reject) => {
        timer = setTimeout(() => reject(new Error(`${label} timed out. Check your connection and retry.`)), FETCH_TIMEOUT_MS)
      }),
    ])
  } finally {
    if (timer) clearTimeout(timer)
  }
}

type ConversationItem = {
  id: string
  name: string
  subtitle: string
  initials: string
  lastMessage: string
  time: string
  unread: number
}

type TimelineEntry =
  | { kind: 'separator'; id: string; label: string }
  | {
      kind: 'message'
      id: string
      body: string
      time: string
      isOwn: boolean
      isFirstInGroup: boolean
      isLastInGroup: boolean
      status?: 'Sent' | 'Seen'
      attachment?: ChatMessage['attachment']
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
const partnerSearch = ref('')

const filteredChatPartners = computed(() => {
  const query = partnerSearch.value.trim().toLowerCase()
  if (!query) return chatPartners.value
  return chatPartners.value.filter((partner) =>
    [partner.displayName, partner.orgName, partner.role, partner.email]
      .some((value) => String(value || '').toLowerCase().includes(query)),
  )
})

const groupedChatPartners = computed(() => {
  const groups = new Map<string, PublicProfile[]>()
  for (const partner of filteredChatPartners.value) {
    const role = String(partner.role || 'other').toLowerCase()
    const org = String(partner.orgName || '').trim()
    const key = role === 'student'
      ? `Students${org ? ` · ${org}` : ''}`
      : role === 'school'
        ? `Schools${org ? ` · ${org}` : ''}`
        : role === 'company'
          ? `Companies${org ? ` · ${org}` : ''}`
          : `Other${org ? ` · ${org}` : ''}`
    const rows = groups.get(key) ?? []
    rows.push(partner)
    groups.set(key, rows)
  }
  return [...groups.entries()].map(([label, partners]) => ({ label, partners }))
})

const currentUserId = computed(() => authStore.user?.uid || '')
const POLL_MS = 10000
let pollTimer: ReturnType<typeof setInterval> | null = null
let listPollTimer: ReturnType<typeof setInterval> | null = null
let loadMessagesSeq = 0

const unreadCount = computed(() =>
  conversations.value.reduce((sum, c) => sum + (c.unread > 0 ? c.unread : 0), 0),
)

watch(unreadCount, (count) => floatingChat.setUnread(count), { immediate: true })

function toggleChat() {
  if (isOpen.value) floatingChat.close()
  else floatingChat.open()
}

function closeChat() {
  floatingChat.close()
  selectedConversation.value = null
  showNewChatPanel.value = false
}

function openChat() {
  // Event handler for `chat:open`. Only flip state — do not call floatingChat.open()
  // (that re-dispatches `chat:open` and used to recurse until stack overflow).
  if (!isOpen.value) isOpen.value = true
}

function initialsFromName(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
    .slice(0, 2) || '?'
}

function formatMessageTime(iso: string | null): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
}

function formatListTime(iso: string | null): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  const now = new Date()
  const sameDay = date.toDateString() === now.toDateString()
  if (sameDay) return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
  const yesterday = new Date(now)
  yesterday.setDate(now.getDate() - 1)
  if (date.toDateString() === yesterday.toDateString()) return 'Yesterday'
  return date.toLocaleDateString([], { month: 'short', day: 'numeric' })
}

function daySeparatorLabel(iso: string | null): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  const now = new Date()
  if (date.toDateString() === now.toDateString()) return 'Today'
  const yesterday = new Date(now)
  yesterday.setDate(now.getDate() - 1)
  if (date.toDateString() === yesterday.toDateString()) return 'Yesterday'
  return date.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' })
}

function mapConversation(thread: ChatConversation): ConversationItem {
  const peerName = thread.peer?.name || 'Chat'
  const peerRole = thread.peer?.role || 'Direct'
  return {
    id: thread.id,
    name: peerName,
    subtitle: peerRole,
    initials: initialsFromName(peerName),
    lastMessage: thread.latestMessage?.body || 'No messages yet',
    time: formatListTime(thread.latestMessage?.createdAt || thread.updatedAt),
    unread: thread.unreadCount || 0,
  }
}

function applyConversations(list: ChatConversation[]) {
  conversationById.value = Object.fromEntries(list.map((c) => [c.id, c]))
  conversations.value = list.map(mapConversation)
}

function mergeMessages(list: ChatMessage[]) {
  const byId = new Map(rawMessages.value.map((message) => [message.id, message]))
  for (const message of list) byId.set(message.id, message)
  rawMessages.value = [...byId.values()].sort((a, b) => {
    const aTime = a.createdAt ? new Date(a.createdAt).getTime() : 0
    const bTime = b.createdAt ? new Date(b.createdAt).getTime() : 0
    return aTime - bTime
  })
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

const activePeerMeta = computed(() => {
  const thread = selectedConversation.value
    ? conversationById.value[selectedConversation.value]
    : null
  const role = thread?.peer?.role || activeConversation.value?.subtitle || 'Direct'
  return String(role)
})

const timeline = computed<TimelineEntry[]>(() => {
  const rows: TimelineEntry[] = []
  let lastDay = ''
  const list = rawMessages.value
  const thread = selectedConversation.value
    ? conversationById.value[selectedConversation.value]
    : null
  const peerReadAt = thread?.peerLastReadAt ? new Date(thread.peerLastReadAt).getTime() : 0
  const lastOwnId = [...list].reverse().find((message) => message.senderId === currentUserId.value)?.id

  for (let index = 0; index < list.length; index += 1) {
    const message = list[index]
    const day = daySeparatorLabel(message.createdAt)
    if (day && day !== lastDay) {
      rows.push({ kind: 'separator', id: `day-${day}-${message.id}`, label: day })
      lastDay = day
    }
    const prev = list[index - 1]
    const next = list[index + 1]
    const isOwn = message.senderId === currentUserId.value
    const isFirstInGroup = !prev || prev.senderId !== message.senderId
    const isLastInGroup = !next || next.senderId !== message.senderId
    const created = message.createdAt ? new Date(message.createdAt).getTime() : 0
    let status: 'Sent' | 'Seen' | undefined
    if (isOwn && message.id === lastOwnId) {
      status = peerReadAt > 0 && created > 0 && peerReadAt >= created ? 'Seen' : 'Sent'
    }
    rows.push({
      kind: 'message',
      id: message.id,
      body: message.body,
      time: formatMessageTime(message.createdAt),
      isOwn,
      isFirstInGroup,
      isLastInGroup,
      status,
      attachment: message.attachment ?? null,
    })
  }
  return rows
})

const canSend = computed(() => {
  return Boolean(newMessage.value.trim() || pendingAttachment.value) && !sending.value
})

function clearPendingAttachment() {
  if (pendingAttachmentPreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(pendingAttachmentPreview.value)
  }
  pendingAttachment.value = null
  pendingAttachmentPreview.value = null
  if (fileInput.value) fileInput.value.value = ''
}

function onPickAttachment(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  const allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg']
  const okType = allowed.includes(file.type) || /\.(pdf|jpe?g|png)$/i.test(file.name)
  if (!okType) {
    sendError.value = 'Only PDF, JPG, or PNG files are allowed.'
    input.value = ''
    return
  }
  if (file.size > 10 * 1024 * 1024) {
    sendError.value = 'File must be 10 MB or smaller.'
    input.value = ''
    return
  }
  clearPendingAttachment()
  pendingAttachment.value = file
  pendingAttachmentPreview.value = file.type.startsWith('image/')
    ? URL.createObjectURL(file)
    : null
  sendError.value = null
  showEmojiPanel.value = false
}

function insertEmoji(emoji: string) {
  newMessage.value += emoji
  showEmojiPanel.value = false
  void nextTick(() => {
    composerField.value?.focus()
    resizeComposer()
  })
}

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

async function scrollMessagesToBottom(force = false) {
  await nextTick()
  const el = messagesContainer.value
  if (!el) return
  if (!force && !stickToBottom.value) return
  el.scrollTop = el.scrollHeight
}

function onMessagesScroll() {
  const el = messagesContainer.value
  if (!el) return
  stickToBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight < 80
}

async function loadConversations(silent = false) {
  if (!currentUserId.value) {
    conversations.value = []
    conversationById.value = {}
    if (!silent) {
      conversationsError.value = 'Sign in to use chat.'
      conversationsLoading.value = false
    }
    return
  }

  if (!silent) {
    conversationsLoading.value = true
    conversationsError.value = null
  }

  try {
    const list = await withTimeout(listConversations(), 'Loading conversations')
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
    conversationsLoading.value = false
  }
}

async function refreshMessages(conversationId: string, silent = false) {
  const el = messagesContainer.value
  const previousHeight = el?.scrollHeight ?? 0
  const previousTop = el?.scrollTop ?? 0
  try {
    const list = await listMessages(conversationId)
    if (selectedConversation.value !== conversationId) return
    mergeMessages(list)
    messagesError.value = null
    messagesUnauthorized.value = false
    await nextTick()
    if (!stickToBottom.value && el) {
      el.scrollTop = previousTop + (el.scrollHeight - previousHeight)
    } else {
      await scrollMessagesToBottom()
    }
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
  stickToBottom.value = true

  try {
    const list = await listMessages(conversationId)
    if (seq !== loadMessagesSeq || selectedConversation.value !== conversationId) return
    mergeMessages(list)
    await scrollMessagesToBottom(true)
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
    if (seq === loadMessagesSeq) messagesLoading.value = false
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
    // Non-blocking
  }
}

async function selectConversation(id: string) {
  if (selectedConversation.value === id && rawMessages.value.length > 0 && !messagesError.value) {
    return
  }
  showNewChatPanel.value = false
  selectedConversation.value = id
  stopMessagePolling()
  await loadConversationMessages(id)
  if (selectedConversation.value !== id) return
  if (!messagesError.value) {
    await markRead(id)
    startMessagePolling(id)
  }
  await nextTick()
  composerField.value?.focus()
}

function backToConversationList() {
  stopMessagePolling()
  selectedConversation.value = null
  rawMessages.value = []
  messagesError.value = null
}

function resizeComposer() {
  const el = composerField.value
  if (!el) return
  el.style.height = 'auto'
  el.style.height = `${Math.min(el.scrollHeight, 120)}px`
}

async function sendMessage() {
  const conversationId = selectedConversation.value
  if (!conversationId || sending.value || sendLock) return
  const content = newMessage.value.trim()
  const file = pendingAttachment.value
  if (!content && !file) return

  sendLock = true
  sending.value = true
  sendError.value = null
  showEmojiPanel.value = false
  try {
    const saved = await sendChatMessage(conversationId, content, file)
    newMessage.value = ''
    clearPendingAttachment()
    resizeComposer()
    mergeMessages([saved])
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
            hasAttachment: Boolean(saved.attachment),
          },
          unreadCount: 0,
        },
      }
    }
    stickToBottom.value = true
    await scrollMessagesToBottom(true)
    await markRead(conversationId)
  } catch (caught) {
    sendError.value = caught instanceof Error ? caught.message : 'Failed to send message'
  } finally {
    sending.value = false
    sendLock = false
  }
}

function onComposerKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    void sendMessage()
  }
}

async function openNewChatPanel() {
  newChatError.value = null
  partnerSearch.value = ''
  partnersLoading.value = true
  showNewChatPanel.value = true
  selectedConversation.value = null
  try {
    const partners = await withTimeout(listEligibleChatPartners(), 'Loading partners')
    chatPartners.value = partners.map((partner) => ({
      uid: partner.uid,
      displayName: partner.displayName,
      role: (partner.role as PublicProfile['role']) || 'school',
      orgName: partner.orgName,
      email: partner.email,
      courses: partner.courses,
      verificationStatus: partner.verificationStatus ?? 'approved',
    }))
  } catch (caught) {
    chatPartners.value = []
    newChatError.value = caught instanceof Error ? caught.message : 'Failed to load chat partners'
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

watch(newMessage, () => {
  void nextTick(resizeComposer)
})

onMounted(() => {
  window.addEventListener('chat:open', openChat)
  document.addEventListener('visibilitychange', onVisibilityChange)
  void loadConversations(true)
})

onUnmounted(() => {
  stopAllPolling()
  window.removeEventListener('chat:open', openChat)
  document.removeEventListener('visibilitychange', onVisibilityChange)
})
</script>

<template>
  <button
    v-if="!isOpen"
    type="button"
    class="msg-fab msg-fab--desktop"
    :aria-label="unreadCount > 0 ? `Open messages, ${unreadCount} unread` : 'Open messages'"
    @click="toggleChat"
  >
    <MessageCircle class="h-5 w-5" aria-hidden="true" />
    <span v-if="unreadCount > 0" class="msg-fab__badge" aria-hidden="true">
      {{ unreadCount > 99 ? '99+' : unreadCount }}
    </span>
  </button>

  <div v-if="isOpen" class="msg-shell" role="dialog" aria-label="Messages">
    <div class="msg-topbar msg-topbar--desktop">
      <div class="msg-topbar__left">
        <MessageCircle class="h-5 w-5 text-primary" aria-hidden="true" />
        <h1 class="msg-topbar__title">Messages</h1>
      </div>
      <button type="button" class="msg-icon-btn" aria-label="Close messages" @click="closeChat">
        <X class="h-5 w-5" />
      </button>
    </div>

    <div class="msg-body" :class="{ 'msg-body--thread': !!activeConversation || showNewChatPanel }">
      <aside class="msg-sidebar" :class="{ 'msg-sidebar--hidden-mobile': !!activeConversation || showNewChatPanel }">
        <div class="msg-sidebar__header">
          <div class="msg-sidebar__title-row">
            <h2>Chats</h2>
            <button type="button" class="msg-icon-btn msg-close-mobile" aria-label="Close messages" @click="closeChat">
              <X class="h-5 w-5" />
            </button>
          </div>
          <button
            type="button"
            class="msg-icon-btn msg-icon-btn--accent"
            aria-label="New message"
            title="New message"
            @click="openNewChatPanel"
          >
            <Plus class="h-5 w-5" />
          </button>
        </div>

        <div class="msg-search">
          <Search class="msg-search__icon" aria-hidden="true" />
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Search"
            aria-label="Search conversations"
          />
        </div>

        <div v-if="conversationsLoading" class="msg-skeleton" aria-busy="true">
          <div v-for="index in 6" :key="index" class="msg-skeleton__row" />
        </div>
        <div v-else-if="conversationsError" class="msg-empty">
          <p>{{ conversationsError }}</p>
          <button type="button" class="msg-retry" @click="loadConversations()">Retry</button>
        </div>
        <div v-else-if="filteredConversations.length === 0" class="msg-empty">
          No conversations yet.
        </div>
        <div v-else class="msg-list">
          <button
            v-for="conversation in filteredConversations"
            :key="conversation.id"
            type="button"
            class="msg-row"
            :class="{ 'msg-row--active': selectedConversation === conversation.id }"
            @click="selectConversation(conversation.id)"
          >
            <span class="msg-avatar" aria-hidden="true">{{ conversation.initials }}</span>
            <span class="msg-row__body">
              <span class="msg-row__top">
                <span class="msg-row__name" :class="{ 'msg-row__name--unread': conversation.unread > 0 }">
                  {{ conversation.name }}
                </span>
                <span class="msg-row__time">{{ conversation.time }}</span>
              </span>
              <span class="msg-row__bottom">
                <span class="msg-row__preview">{{ conversation.lastMessage }}</span>
                <span
                  v-if="conversation.unread > 0"
                  class="msg-row__unread"
                  :aria-label="`${conversation.unread} unread`"
                >
                  {{ conversation.unread > 99 ? '99+' : conversation.unread }}
                </span>
              </span>
            </span>
          </button>
        </div>
      </aside>

      <section
        v-if="showNewChatPanel"
        class="msg-thread msg-thread--new"
        aria-label="New message"
      >
        <div class="msg-thread__header">
          <button type="button" class="msg-back" aria-label="Back to chats" @click="showNewChatPanel = false">
            <ArrowLeft class="h-5 w-5" />
          </button>
          <div class="msg-thread__title">
            <h2>New message</h2>
            <p>People you’re allowed to message</p>
          </div>
        </div>
        <div class="msg-search msg-search--inset">
          <Search class="msg-search__icon" aria-hidden="true" />
          <input
            v-model="partnerSearch"
            type="search"
            placeholder="Search people"
            aria-label="Search people you can message"
          />
        </div>
        <div v-if="partnersLoading" class="msg-skeleton" aria-busy="true">
          <div v-for="index in 5" :key="index" class="msg-skeleton__row" />
        </div>
        <div v-else-if="newChatError" class="msg-empty">
          <p>{{ newChatError }}</p>
          <button type="button" class="msg-retry" @click="openNewChatPanel">Retry</button>
        </div>
        <div v-else-if="chatPartners.length === 0" class="msg-empty">
          No eligible partners available to message yet.
        </div>
        <div v-else-if="groupedChatPartners.length === 0" class="msg-empty">
          No matches for that search.
        </div>
        <div v-else class="msg-partner-list">
          <div v-for="group in groupedChatPartners" :key="group.label" class="msg-partner-group">
            <p class="msg-partner-group__label">{{ group.label }}</p>
            <button
              v-for="partner in group.partners"
              :key="partner.uid"
              type="button"
              class="msg-partner"
              :disabled="partner.uid === currentUserId || startingChat"
              @click="startChatWith(partner)"
            >
              <span class="msg-avatar" aria-hidden="true">{{ initialsFromName(partner.displayName) }}</span>
              <span class="msg-partner__meta">
                <span class="msg-partner__name">{{ partner.displayName }}</span>
                <span class="msg-partner__role">
                  {{ partner.role }}{{ partner.orgName ? ` · ${partner.orgName}` : '' }}
                </span>
              </span>
            </button>
          </div>
        </div>
      </section>

      <section
        v-else-if="activeConversation"
        class="msg-thread"
        aria-label="Conversation"
      >
        <div class="msg-thread__header">
          <button type="button" class="msg-back" aria-label="Back to chats" @click="backToConversationList">
            <ArrowLeft class="h-5 w-5" />
          </button>
          <span class="msg-avatar msg-avatar--sm" aria-hidden="true">{{ activeConversation.initials }}</span>
          <div class="msg-thread__title">
            <h2>{{ activeConversation.name }}</h2>
            <p>{{ activePeerMeta }}</p>
          </div>
        </div>

        <div
          ref="messagesContainer"
          class="msg-messages"
          @scroll="onMessagesScroll"
        >
          <div v-if="messagesLoading" class="msg-skeleton msg-skeleton--thread" aria-busy="true">
            <div v-for="index in 4" :key="index" class="msg-skeleton__bubble" />
          </div>
          <div v-else-if="messagesError" class="msg-empty">
            <p>{{ messagesError }}</p>
            <button
              type="button"
              class="msg-retry"
              @click="loadConversationMessages(activeConversation.id)"
            >
              Retry
            </button>
          </div>
          <div v-else-if="timeline.length === 0" class="msg-empty">
            <h3>No messages yet</h3>
            <p>Say hello to start the conversation.</p>
          </div>
          <template v-else>
            <div v-for="entry in timeline" :key="entry.id">
              <div v-if="entry.kind === 'separator'" class="msg-day">
                <span>{{ entry.label }}</span>
              </div>
              <div
                v-else
                class="msg-bubble-row"
                :class="{
                  'msg-bubble-row--own': entry.isOwn,
                  'msg-bubble-row--first': entry.isFirstInGroup,
                  'msg-bubble-row--last': entry.isLastInGroup,
                }"
              >
                <div class="msg-bubble" :class="entry.isOwn ? 'msg-bubble--own' : 'msg-bubble--peer'">
                  <a
                    v-if="entry.attachment"
                    class="msg-attachment"
                    :href="entry.attachment.url"
                    target="_blank"
                    rel="noopener"
                  >
                    <Paperclip class="h-4 w-4" aria-hidden="true" />
                    <span>{{ entry.attachment.name || 'Attachment' }}</span>
                  </a>
                  <p v-if="entry.body" class="msg-bubble__text">{{ entry.body }}</p>
                </div>
                <div v-if="entry.isLastInGroup" class="msg-bubble__meta">
                  <span>{{ entry.time }}</span>
                  <span v-if="entry.status"> · {{ entry.status }}</span>
                </div>
              </div>
            </div>
          </template>
        </div>

        <p v-if="sendError" class="msg-send-error">{{ sendError }}</p>

        <div v-if="pendingAttachment" class="msg-attach-preview">
          <img
            v-if="pendingAttachmentPreview"
            :src="pendingAttachmentPreview"
            alt=""
            class="msg-attach-preview__thumb"
          />
          <div class="msg-attach-preview__meta">
            <p>{{ pendingAttachment.name }}</p>
            <span>{{ Math.ceil(pendingAttachment.size / 1024) }} KB</span>
          </div>
          <button type="button" class="msg-icon-btn" aria-label="Remove attachment" @click="clearPendingAttachment">
            <X class="h-4 w-4" />
          </button>
        </div>

        <div v-if="showEmojiPanel" class="msg-emoji-panel" role="listbox" aria-label="Emoji">
          <button
            v-for="emoji in EMOJI_QUICK"
            :key="emoji"
            type="button"
            class="msg-emoji-panel__btn"
            @click="insertEmoji(emoji)"
          >
            {{ emoji }}
          </button>
        </div>

        <div class="msg-composer">
          <button
            type="button"
            class="msg-icon-btn msg-composer__tool"
            aria-label="Insert emoji"
            :disabled="sending || messagesLoading || !!messagesError"
            @click="showEmojiPanel = !showEmojiPanel"
          >
            <Smile class="h-5 w-5" />
          </button>
          <textarea
            ref="composerField"
            v-model="newMessage"
            rows="1"
            class="msg-composer__input"
            placeholder="Message"
            aria-label="Message text"
            :disabled="sending || messagesLoading || !!messagesError"
            @keydown="onComposerKeydown"
            @input="resizeComposer"
          />
          <input
            ref="fileInput"
            type="file"
            class="sr-only"
            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
            @change="onPickAttachment"
          />
          <button
            type="button"
            class="msg-icon-btn msg-composer__tool"
            aria-label="Attach file"
            :disabled="sending || messagesLoading || !!messagesError"
            @click="fileInput?.click()"
          >
            <Paperclip class="h-5 w-5" />
          </button>
          <button
            type="button"
            class="msg-composer__send"
            aria-label="Send message"
            :disabled="!canSend || messagesLoading || !!messagesError"
            @click="sendMessage"
          >
            <Send class="h-4 w-4" />
          </button>
        </div>
      </section>

      <div v-else class="msg-placeholder msg-placeholder--desktop">
        <MessageCircle class="h-12 w-12 text-muted-foreground" aria-hidden="true" />
        <h3>{{ messagesUnauthorized ? 'Conversation unavailable' : 'Select a conversation' }}</h3>
        <p>
          {{
            messagesUnauthorized
              ? messagesError || "You don't have access to this conversation."
              : conversationsError || 'Choose a chat from the left to start messaging.'
          }}
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.msg-fab {
  position: fixed;
  right: max(1rem, env(safe-area-inset-right, 0px));
  bottom: max(1.25rem, calc(1rem + var(--shell-tabbar-height, 0px) + env(safe-area-inset-bottom, 0px)));
  z-index: var(--z-dropdown);
  display: none;
  height: 2.75rem;
  width: 2.75rem;
  min-height: 44px;
  min-width: 44px;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 999px;
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  box-shadow: 0 4px 14px hsl(var(--primary) / 0.28);
  cursor: pointer;
}

.msg-fab--desktop {
  display: none;
}

.msg-fab__badge {
  position: absolute;
  top: -0.2rem;
  right: -0.2rem;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.3rem;
  border-radius: 999px;
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  border: 2px solid hsl(var(--background));
  font-size: 0.6875rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.msg-shell {
  position: fixed;
  inset: 0;
  z-index: var(--z-sheet);
  display: flex;
  flex-direction: column;
  background: hsl(var(--background));
  color: hsl(var(--foreground));
  height: 100dvh;
}

.msg-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-height: 3.5rem;
  padding: 0.5rem 1rem;
  padding-top: max(0.5rem, env(safe-area-inset-top, 0px));
  border-bottom: 1px solid hsl(var(--border));
  background: hsl(var(--card));
}

.msg-sidebar__title-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
  flex: 1;
}

.msg-close-mobile {
  display: none;
}

.msg-icon-btn--accent {
  background: hsl(var(--primary) / 0.12);
  color: hsl(var(--primary));
}

.msg-topbar__left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.msg-topbar__title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
}

.msg-icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border: none;
  border-radius: 999px;
  background: hsl(var(--muted));
  color: hsl(var(--foreground));
  cursor: pointer;
}

.msg-body {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: minmax(280px, 360px) 1fr;
}

.msg-sidebar,
.msg-thread {
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: hsl(var(--background));
}

.msg-sidebar {
  border-right: 1px solid hsl(var(--border));
}

.msg-sidebar__header,
.msg-thread__header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-height: 3.5rem;
  padding: 0.5rem 0.875rem;
  border-bottom: 1px solid hsl(var(--border));
}

.msg-sidebar__header h2,
.msg-thread__title h2 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
}

.msg-thread__title {
  min-width: 0;
}

.msg-thread__title p,
.msg-partner__role {
  margin: 0.1rem 0 0;
  color: hsl(var(--muted-foreground));
  font-size: 0.8125rem;
  text-transform: capitalize;
}

.msg-search {
  position: relative;
  margin: 0.75rem;
}

.msg-search--inset {
  margin-top: 0.5rem;
}

.msg-search__icon {
  position: absolute;
  left: 0.85rem;
  top: 50%;
  width: 1rem;
  height: 1rem;
  transform: translateY(-50%);
  color: hsl(var(--muted-foreground));
  pointer-events: none;
}

.msg-search input,
.msg-composer__input {
  width: 100%;
  border: 1px solid hsl(var(--border));
  border-radius: 999px;
  background: hsl(var(--muted));
  color: hsl(var(--foreground));
  font-size: 16px;
  outline: none;
}

.msg-search input {
  min-height: 2.75rem;
  padding: 0.65rem 0.9rem 0.65rem 2.4rem;
}

.msg-list,
.msg-partner-list,
.msg-messages {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

.msg-row,
.msg-partner {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-height: 4.25rem;
  padding: 0.65rem 0.875rem;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  color: inherit;
}

.msg-row:hover,
.msg-partner:hover:not(:disabled),
.msg-row--active {
  background: hsl(var(--accent));
}

.msg-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 3rem;
  height: 3rem;
  border-radius: 999px;
  background: hsl(var(--primary) / 0.15);
  color: hsl(var(--primary));
  font-size: 0.875rem;
  font-weight: 700;
  flex-shrink: 0;
}

.msg-avatar--sm {
  width: 2.5rem;
  height: 2.5rem;
}

.msg-row__body,
.msg-partner__meta {
  min-width: 0;
  flex: 1;
}

.msg-row__top,
.msg-row__bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.msg-row__name {
  font-size: 0.95rem;
  font-weight: 500;
  color: hsl(var(--foreground));
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.msg-row__name--unread {
  font-weight: 700;
}

.msg-row__time {
  flex-shrink: 0;
  color: hsl(var(--muted-foreground));
  font-size: 0.75rem;
}

.msg-row__preview {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: hsl(var(--muted-foreground));
  font-size: 0.8125rem;
}

.msg-row__unread {
  flex-shrink: 0;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.35rem;
  border-radius: 999px;
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  font-size: 0.6875rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.msg-partner__name {
  display: block;
  font-weight: 600;
}

.msg-partner-group__label {
  margin: 0;
  padding: 0.65rem 0.875rem 0.25rem;
  color: hsl(var(--muted-foreground));
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.msg-messages {
  padding: 0.75rem 1rem 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.msg-day {
  display: flex;
  justify-content: center;
  margin: 0.75rem 0;
}

.msg-day span {
  padding: 0.25rem 0.7rem;
  border-radius: 999px;
  background: hsl(var(--muted));
  color: hsl(var(--muted-foreground));
  font-size: 0.75rem;
  font-weight: 600;
}

.msg-bubble-row {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  max-width: 100%;
}

.msg-bubble-row--own {
  align-items: flex-end;
}

.msg-bubble-row--first {
  margin-top: 0.55rem;
}

.msg-bubble {
  max-width: min(78%, 34rem);
  padding: 0.55rem 0.85rem;
  border-radius: 1.15rem;
  word-break: break-word;
}

.msg-bubble--peer {
  background: hsl(215 16% 28%);
  color: #f8fafc;
  border-bottom-left-radius: 0.35rem;
}

:root.dark .msg-bubble--peer,
.dark .msg-bubble--peer {
  background: hsl(215 14% 22%);
  color: #f1f5f9;
}

.msg-attachment {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  margin-bottom: 0.35rem;
  padding: 0.35rem 0.55rem;
  border-radius: 0.65rem;
  background: hsl(0 0% 100% / 0.14);
  color: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  text-decoration: none;
}

.msg-attach-preview {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin: 0 0.75rem;
  padding: 0.55rem 0.65rem;
  border: 1px solid hsl(var(--border));
  border-radius: 0.85rem;
  background: hsl(var(--muted));
}

.msg-attach-preview__thumb {
  width: 2.75rem;
  height: 2.75rem;
  object-fit: cover;
  border-radius: 0.5rem;
}

.msg-attach-preview__meta {
  min-width: 0;
  flex: 1;
}

.msg-attach-preview__meta p {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.msg-attach-preview__meta span {
  color: hsl(var(--muted-foreground));
  font-size: 0.75rem;
}

.msg-emoji-panel {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0.25rem;
  margin: 0 0.75rem 0.35rem;
  padding: 0.5rem;
  border: 1px solid hsl(var(--border));
  border-radius: 0.85rem;
  background: hsl(var(--card));
}

.msg-emoji-panel__btn {
  min-height: 2.5rem;
  border: none;
  border-radius: 0.5rem;
  background: transparent;
  font-size: 1.25rem;
  cursor: pointer;
}

.msg-emoji-panel__btn:hover {
  background: hsl(var(--muted));
}

.msg-composer__tool {
  flex-shrink: 0;
  background: transparent;
}

.msg-bubble--own {
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  border-bottom-right-radius: 0.35rem;
}

.msg-bubble-row--last .msg-bubble--peer {
  border-bottom-left-radius: 1.15rem;
}

.msg-bubble-row--last .msg-bubble--own {
  border-bottom-right-radius: 1.15rem;
}

.msg-bubble__text {
  margin: 0;
  font-size: 0.9375rem;
  line-height: 1.4;
  white-space: pre-wrap;
}

.msg-bubble__meta {
  margin-top: 0.2rem;
  padding: 0 0.35rem;
  color: hsl(var(--muted-foreground));
  font-size: 0.6875rem;
}

.msg-composer {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
  padding: 0.65rem 0.75rem calc(0.65rem + env(safe-area-inset-bottom, 0px));
  border-top: 1px solid hsl(var(--border));
  background: hsl(var(--card));
}

.msg-composer__input {
  min-height: 2.75rem;
  max-height: 7.5rem;
  resize: none;
  padding: 0.7rem 1rem;
  line-height: 1.35;
  border-radius: 1.25rem;
}

.msg-composer__send {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border: none;
  border-radius: 999px;
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
  cursor: pointer;
  flex-shrink: 0;
}

.msg-composer__send:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.msg-back {
  display: none;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border: none;
  border-radius: 999px;
  background: hsl(var(--muted));
  color: hsl(var(--foreground));
  cursor: pointer;
  flex-shrink: 0;
}

.msg-empty,
.msg-placeholder {
  margin: auto;
  padding: 2rem 1.25rem;
  text-align: center;
  color: hsl(var(--muted-foreground));
}

.msg-empty h3,
.msg-placeholder h3 {
  margin: 0.75rem 0 0.35rem;
  color: hsl(var(--foreground));
}

.msg-retry {
  margin-top: 0.75rem;
  min-height: 2.75rem;
  padding: 0 1rem;
  border-radius: 0.65rem;
  border: 1px solid hsl(var(--border));
  background: hsl(var(--background));
  color: hsl(var(--foreground));
  font-weight: 600;
  cursor: pointer;
}

.msg-send-error {
  margin: 0;
  padding: 0.35rem 1rem;
  color: hsl(var(--destructive));
  font-size: 0.8125rem;
}

.msg-skeleton {
  padding: 0.75rem;
  display: grid;
  gap: 0.65rem;
}

.msg-skeleton__row,
.msg-skeleton__bubble {
  height: 3.5rem;
  border-radius: 1rem;
  background: linear-gradient(90deg, hsl(var(--muted)), hsl(var(--accent)), hsl(var(--muted)));
  background-size: 200% 100%;
  animation: msg-shimmer 1.2s linear infinite;
}

.msg-skeleton__bubble {
  width: 55%;
  height: 2.5rem;
}

.msg-skeleton__bubble:nth-child(even) {
  margin-left: auto;
}

@keyframes msg-shimmer {
  0% { background-position: 100% 0; }
  100% { background-position: -100% 0; }
}

@media (min-width: 768px) {
  .msg-fab--desktop {
    display: inline-flex;
  }

  .msg-back {
    display: none !important;
  }

  .msg-sidebar--hidden-mobile {
    display: flex;
  }

  .msg-close-mobile {
    display: none !important;
  }
}

@media (max-width: 767px) {
  .msg-topbar--desktop {
    display: none;
  }

  .msg-close-mobile {
    display: inline-flex;
  }

  .msg-sidebar__header {
    padding-top: max(0.5rem, env(safe-area-inset-top, 0px));
  }

  .msg-body {
    grid-template-columns: 1fr;
  }

  .msg-sidebar--hidden-mobile {
    display: none;
  }

  .msg-placeholder--desktop {
    display: none;
  }

  .msg-back {
    display: inline-flex;
  }

  .msg-thread,
  .msg-thread--new {
    position: absolute;
    inset: 0;
  }

  .msg-body--thread {
    position: relative;
  }

  .msg-thread__header {
    padding-top: max(0.5rem, env(safe-area-inset-top, 0px));
  }
}
</style>
