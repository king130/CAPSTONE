<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  ensureDefaultChat,
  sendMessage as sendChatMessage,
  subscribeToChats,
  subscribeToMessages,
  type ChatMessage,
  type ChatThread,
} from '@/services/chat'

// Props for customization
const props = defineProps<{
  userType?: 'intern' | 'company'
}>()

// State management
const authStore = useAuthStore()
const isOpen = ref(false)
const isMinimized = ref(false)
const unreadCount = ref(0)
const showGroupInfo = ref(true)
const newMessage = ref('')

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
const currentChat = ref<ConversationItem | null>(null)
const messages = ref<MessageItem[]>([])
const currentUserId = computed(() => authStore.user?.uid || '')
const unsubChats = ref<null | (() => void)>(null)
const unsubMessages = ref<null | (() => void)>(null)

// Group info data
const groupInfo = ref({
  name: 'OJT Intern Path',
  subtitle: 'Group - 12Members',
  description: 'This group serves as a central communication hub for all participants in the OJT Intern Path program. Here, you can connect with fellow interns, mentors, and coordinators to share experiences, ask questions, and collaborate on projects.',
  link: 'https://bit.ly/ojt-intern-path',
  notifications: true,
  members: [
    { name: 'Runing Jagan', subtitle: 'Software Engineer', time: '30m', avatar: '/icons/profiles/robert-taylor.jpg' },
    { name: 'Maria Santos', subtitle: 'UI/UX Designer', time: '45m', avatar: '/icons/profiles/maria-santos.jpg' },
    { name: 'John Smith', subtitle: 'Project Manager', time: '1h', avatar: '/icons/profiles/john-smith.jpg' },
    { name: 'Emily Johnson', subtitle: 'Frontend Developer', time: '2h', avatar: '/icons/profiles/emily-johnson.jpg' },
    { name: 'David Kim', subtitle: 'Backend Developer', time: '3h', avatar: '/icons/profiles/david-kim.jpg' },
    { name: 'Sophia Martinez', subtitle: 'Data Analyst', time: '4h', avatar: '/icons/profiles/sophia-martinez.jpg' }
  ],
  media: [
    '/icons/profiles/alex-doe.jpg',
    '/icons/profiles/maria-santos.jpg',
    '/icons/profiles/emily-johnson.jpg',
    '/icons/profiles/robert-taylor.jpg',
    '/icons/profiles/david-kim.jpg',
    '/icons/profiles/james-wilson.jpg',
    '/icons/profiles/john-smith.jpg',
    '/icons/profiles/sophia-martinez.jpg',
    '/icons/profiles/alex-doe.jpg'
  ]
})

// Functions
function toggleChat() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    isMinimized.value = false
    unreadCount.value = 0
  }
}

function minimizeChat() {
  isMinimized.value = true
}

function maximizeChat() {
  isMinimized.value = false
}

function closeChat() {
  isOpen.value = false
  isMinimized.value = false
}

function selectConversation(conversation: ConversationItem) {
  currentChat.value = conversation
  conversation.unread = 0
  startMessageListener(conversation.id)
}

function mapThread(thread: ChatThread): ConversationItem {
  return {
    id: thread.id,
    name: thread.title || 'Support',
    subtitle: thread.members.length > 1 ? 'Group Chat' : 'Direct Chat',
    avatar: '/icons/logo-main.png',
    lastMessage: thread.lastMessage || 'No messages yet',
    time: '',
    unread: 0,
    online: false,
    isGroup: thread.members.length > 1,
  }
}

function mapMessage(message: ChatMessage): MessageItem {
  const isOwn = message.senderId === currentUserId.value
  const createdAt =
    message.createdAt && typeof message.createdAt === 'object' && 'toDate' in message.createdAt
      ? (message.createdAt as { toDate: () => Date }).toDate()
      : message.createdAt
      ? new Date(message.createdAt)
      : null
  return {
    id: message.id,
    sender: isOwn ? 'You' : 'Member',
    avatar: '/icons/logo-main.png',
    message: message.text,
    time: createdAt ? createdAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '',
    isOwn,
  }
}

function startMessageListener(chatId: string) {
  if (unsubMessages.value) {
    unsubMessages.value()
  }
  unsubMessages.value = subscribeToMessages(chatId, (snapshot) => {
    messages.value = snapshot.map(mapMessage)
  })
}

async function sendMessage() {
  if (!currentChat.value || !currentUserId.value) return
  const content = newMessage.value.trim()
  if (!content) return
  await sendChatMessage(currentChat.value.id, currentUserId.value, content)
  newMessage.value = ''
}

async function startChatListeners() {
  if (!currentUserId.value) return
  const defaultChatId = await ensureDefaultChat(currentUserId.value)
  unsubChats.value = subscribeToChats(currentUserId.value, (threads) => {
    conversations.value = threads.map(mapThread)
    if (!currentChat.value && conversations.value.length > 0) {
      const initial = conversations.value.find((chat) => chat.id === defaultChatId) || conversations.value[0]
      currentChat.value = initial
      startMessageListener(initial.id)
    }
  })
}

onMounted(() => {
  startChatListeners()
})

watch(currentUserId, () => {
  if (unsubChats.value) {
    unsubChats.value()
    unsubChats.value = null
  }
  if (unsubMessages.value) {
    unsubMessages.value()
    unsubMessages.value = null
  }
  conversations.value = []
  messages.value = []
  currentChat.value = null
  startChatListeners()
})

onUnmounted(() => {
  if (unsubChats.value) unsubChats.value()
  if (unsubMessages.value) unsubMessages.value()
})

function toggleGroupInfo() {
  showGroupInfo.value = !showGroupInfo.value
}

function handleImageError(event: Event) {
  const img = event.target as HTMLImageElement
  const name = img.alt || 'User'
  const initials = name.split(' ').map(n => n[0]).join('').toUpperCase()
  
  const colors = ['#2563eb', '#7c3aed', '#dc2626', '#059669', '#d97706', '#0891b2']
  const colorIndex = name.length % colors.length
  const color = colors[colorIndex]
  
  const svg = `
    <svg width="100" height="100" xmlns="http://www.w3.org/2000/svg">
      <rect width="100" height="100" fill="${color}"/>
      <text x="50" y="50" font-family="Arial, sans-serif" font-size="36" font-weight="bold" 
            fill="white" text-anchor="middle" dominant-baseline="central">${initials}</text>
    </svg>
  `
  
  const dataUrl = 'data:image/svg+xml;base64,' + btoa(svg)
  img.src = dataUrl
}
</script>

<template>
  <!-- Floating Chat Button -->
  <div v-if="!isOpen" class="floating-chat-button" @click="toggleChat">
    <img src="/icons/icon-message.png" alt="Chat" class="chat-button-icon" />
    <div v-if="unreadCount > 0" class="unread-badge">{{ unreadCount }}</div>
  </div>

  <!-- Full Screen Chat Widget -->
  <div v-if="isOpen" class="floating-chat-widget" :class="{ minimized: isMinimized }">
    <!-- Main Chat Header -->
    <div class="main-chat-header" v-if="currentChat">
      <div class="header-left">
        <div class="chat-avatar">
          <img :src="currentChat.avatar" :alt="currentChat.name" @error="handleImageError" />
          <div class="status-indicator">
            <div class="status-dot online"></div>
            <span class="online-text">Online</span>
          </div>
        </div>
        <div class="chat-info">
          <h2 class="chat-name">{{ currentChat.name }}</h2>
        </div>
      </div>
      <div class="header-right">
        <button class="header-btn" disabled title="Coming soon">
          <span>📹</span>
        </button>
        <button class="header-btn" disabled title="Coming soon">
          <span>📞</span>
        </button>
        <button class="header-btn" disabled title="Coming soon">
          <span>⋯</span>
        </button>
        <button @click="toggleGroupInfo" class="header-btn group-info-btn">
          <span>Group Info</span>
        </button>
        <button @click="closeChat" class="header-btn close-btn">
          <span>×</span>
        </button>
      </div>
    </div>

    <!-- Chat Content Container -->
    <div class="chat-container">
      <!-- Left Sidebar - Conversations -->
      <div class="conversations-sidebar">
        <!-- Sidebar Header -->
        <div class="sidebar-header">
          <h3>Message</h3>
          <div class="header-actions">
            <button class="action-btn" disabled title="Coming soon">✏️</button>
            <button class="action-btn" disabled title="Coming soon">⋯</button>
          </div>
        </div>

        <!-- Search -->
        <div class="search-container">
          <div class="search-input-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" placeholder="Search" class="search-input" />
          </div>
        </div>

        <!-- Pinned Section -->
        <div class="pinned-section">
          <div class="section-header">
            <span class="pin-icon">📌</span>
            <span>Pinned</span>
          </div>
        </div>

        <!-- All Messages Section -->
        <div class="all-messages-section">
          <div class="section-header">
            <span class="messages-icon">💬</span>
            <span>All Messages</span>
          </div>
        </div>

        <!-- Conversations List -->
        <div class="conversations-list">
          <div 
            v-for="conversation in conversations" 
            :key="conversation.id"
            class="conversation-item"
            :class="{ active: currentChat && currentChat.id === conversation.id }"
            @click="selectConversation(conversation)"
          >
            <div class="conversation-avatar">
              <img :src="conversation.avatar" :alt="conversation.name" @error="handleImageError" />
              <div v-if="conversation.online" class="online-dot"></div>
            </div>
            <div class="conversation-content">
              <div class="conversation-header">
                <span class="conversation-name">{{ conversation.name }}</span>
                <span class="conversation-time">{{ conversation.time }}</span>
              </div>
              <div class="conversation-preview">
                <span class="conversation-subtitle">{{ conversation.subtitle }}</span>
                <div v-if="conversation.unread > 0" class="unread-count">{{ conversation.unread }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Chat Area -->
      <div class="main-chat-area" v-if="currentChat">
        <!-- Chat Date -->
        <div class="chat-date">Today, January 21, 2026</div>

        <!-- Messages -->
        <div class="chat-messages">
          <div 
            v-for="message in messages" 
            :key="message.id"
            class="message"
            :class="{ 'own-message': message.isOwn }"
          >
            <div v-if="!message.isOwn" class="message-avatar">
              <img :src="message.avatar" :alt="message.sender" @error="handleImageError" />
            </div>
            <div class="message-content">
              <div v-if="!message.isOwn" class="message-header">
                <span class="message-sender">{{ message.sender }}</span>
              </div>
              <div class="message-bubble" :class="{ 'own-bubble': message.isOwn }">
                {{ message.message }}
              </div>
              <div class="message-time">{{ message.time }}</div>
            </div>
            <div v-if="message.isOwn" class="message-avatar own-avatar">
              <img :src="message.avatar" :alt="message.sender" @error="handleImageError" />
            </div>
          </div>
        </div>

        <!-- Message Input -->
        <div class="message-input-area">
          <div class="input-container">
            <button class="attachment-btn" disabled title="Coming soon">
              <span>📎</span>
            </button>
            <input 
              v-model="newMessage"
              type="text" 
              placeholder="Type a message..."
              class="message-input"
              @keyup.enter="sendMessage"
            />
            <button class="emoji-btn" disabled title="Coming soon">
              <span>😊</span>
            </button>
            <button class="media-btn" disabled title="Coming soon">
              <span>📷</span>
            </button>
            <button @click="sendMessage" class="send-btn">
              <span>Send</span>
            </button>
          </div>
        </div>
      </div>
      <div v-else class="empty-chat">
        <p>No conversations yet. Start a new message to begin.</p>
      </div>

      <!-- Right Sidebar - Group Info -->
      <div v-if="showGroupInfo" class="group-info-sidebar">
        <!-- Group Header -->
        <div class="group-header">
          <div class="group-avatar-section">
            <div class="group-avatars">
              <div class="avatar-stack">
                <div class="avatar-item blue">👥</div>
                <div class="avatar-item dark">👤</div>
                <div class="avatar-item green">👤</div>
              </div>
            </div>
            <h3 class="group-name">{{ groupInfo.name }}</h3>
            <p class="group-subtitle">{{ groupInfo.subtitle }}</p>
          </div>
        </div>

        <!-- Group Description -->
        <div class="group-section">
          <div class="section-header">
            <span class="section-icon">📄</span>
            <span>Description</span>
          </div>
          <p class="group-description">{{ groupInfo.description }}</p>
          <div class="group-link">
            <span class="link-icon">🔗</span>
            <a :href="groupInfo.link" class="group-link-text">{{ groupInfo.link }}</a>
            <button class="copy-btn" disabled title="Coming soon">📋</button>
          </div>
        </div>

        <!-- Notifications -->
        <div class="group-section">
          <div class="section-header">
            <span class="section-icon">🔔</span>
            <span>Notifications</span>
            <div class="toggle-switch" :class="{ active: groupInfo.notifications }">
              <div class="toggle-slider"></div>
            </div>
          </div>
        </div>

        <!-- Members -->
        <div class="group-section">
          <div class="section-header">
            <span class="section-icon">👥</span>
            <span>Members</span>
            <span class="member-count">ONLINE (6)</span>
          </div>
          <div class="members-list">
            <div v-for="member in groupInfo.members" :key="member.name" class="member-item">
              <img :src="member.avatar" :alt="member.name" class="member-avatar" @error="handleImageError" />
              <div class="member-info">
                <span class="member-name">{{ member.name }}</span>
                <span class="member-subtitle">{{ member.subtitle }}</span>
              </div>
              <span class="member-time">{{ member.time }}</span>
            </div>
          </div>
        </div>

        <!-- Media -->
        <div class="group-section">
          <div class="section-header">
            <span class="section-icon">🖼️</span>
            <span>Media</span>
          </div>
          <div class="media-grid">
            <div v-for="(media, index) in groupInfo.media" :key="index" class="media-item">
              <img :src="media" :alt="`Media ${index + 1}`" @error="handleImageError" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Floating Chat Button */
.floating-chat-button {
  position: fixed;
  bottom: 24px;
  right: 24px;
  width: 60px;
  height: 60px;
  background: #2563eb;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
  transition: all 0.3s ease;
  z-index: 1000;
}

.floating-chat-button:hover {
  background: #1d4ed8;
  transform: scale(1.05);
  box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
}

.chat-button-icon {
  width: 24px;
  height: 24px;
  filter: brightness(0) saturate(100%) invert(100%);
}

.unread-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #ef4444;
  color: white;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  border: 2px solid white;
}

/* Full Screen Chat Widget */
.floating-chat-widget {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: white;
  display: flex;
  flex-direction: column;
  z-index: 1001;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
}

.floating-chat-widget.minimized {
  display: none;
}

/* Main Chat Header */
.main-chat-header {
  background: white;
  border-bottom: 1px solid #e5e7eb;
  padding: 12px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-shrink: 0;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.chat-avatar {
  position: relative;
}

.chat-avatar img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.status-indicator {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 2px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.status-dot.online {
  background: #10b981;
}

.online-text {
  font-size: 12px;
  color: #10b981;
  font-weight: 500;
}

.chat-info {
  display: flex;
  flex-direction: column;
}

.chat-name {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-btn {
  background: none;
  border: none;
  padding: 8px;
  border-radius: 6px;
  cursor: pointer;
  color: #6b7280;
  font-size: 16px;
  transition: background 0.2s;
}

.header-btn:hover {
  background: #f3f4f6;
}

.group-info-btn {
  font-size: 14px;
  color: #2563eb;
  font-weight: 500;
}

.close-btn {
  font-size: 20px;
  color: #ef4444;
}

/* Chat Container */
.chat-container {
  flex: 1;
  display: flex;
  overflow: hidden;
}

/* Left Sidebar - Conversations */
.conversations-sidebar {
  width: 320px;
  background: white;
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.sidebar-header {
  padding: 16px 20px;
  border-bottom: 1px solid #f3f4f6;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.sidebar-header h3 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 8px;
}

.action-btn {
  background: none;
  border: none;
  padding: 6px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  color: #6b7280;
  transition: background 0.2s;
}

.action-btn:hover {
  background: #f3f4f6;
}

/* Search */
.search-container {
  padding: 12px 20px;
  border-bottom: 1px solid #f3f4f6;
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  color: #9ca3af;
  font-size: 14px;
}

.search-input {
  width: 100%;
  padding: 8px 12px 8px 36px;
  border: 1px solid #e5e7eb;
  border-radius: 20px;
  font-size: 14px;
  outline: none;
  background: #f9fafb;
}

.search-input:focus {
  border-color: #2563eb;
  background: white;
}

/* Section Headers */
.pinned-section,
.all-messages-section {
  padding: 8px 20px;
  border-bottom: 1px solid #f3f4f6;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

/* Conversations List */
.conversations-list {
  flex: 1;
  overflow-y: auto;
}

.conversation-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  cursor: pointer;
  transition: background 0.2s;
  border-bottom: 1px solid #f8fafc;
}

.conversation-item:hover {
  background: #f8fafc;
}

.conversation-item.active {
  background: #eff6ff;
  border-right: 3px solid #2563eb;
}

.conversation-avatar {
  position: relative;
  flex-shrink: 0;
}

.conversation-avatar img {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
}

.online-dot {
  position: absolute;
  bottom: 2px;
  right: 2px;
  width: 12px;
  height: 12px;
  background: #10b981;
  border: 2px solid white;
  border-radius: 50%;
}

.conversation-content {
  flex: 1;
  min-width: 0;
}

.conversation-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2px;
}

.conversation-name {
  font-weight: 600;
  font-size: 14px;
  color: #1f2937;
}

.conversation-time {
  font-size: 12px;
  color: #9ca3af;
}

.conversation-preview {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.conversation-subtitle {
  font-size: 13px;
  color: #6b7280;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
}

.unread-count {
  background: #ef4444;
  color: white;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 600;
  flex-shrink: 0;
  margin-left: 8px;
}

/* Main Chat Area */
.main-chat-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: #f9fafb;
}

.empty-chat {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  font-size: 13px;
  background: #f9fafb;
}

.chat-date {
  text-align: center;
  color: #6b7280;
  font-size: 12px;
  padding: 16px 0;
  background: white;
  border-bottom: 1px solid #f3f4f6;
}

.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
  background: #f9fafb;
}

.message {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.message.own-message {
  flex-direction: row-reverse;
}

.message-avatar img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.own-avatar {
  align-self: flex-end;
}

.message-content {
  max-width: 60%;
  display: flex;
  flex-direction: column;
}

.own-message .message-content {
  align-items: flex-end;
}

.message-header {
  margin-bottom: 4px;
}

.message-sender {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.message-bubble {
  background: white;
  padding: 12px 16px;
  border-radius: 18px;
  font-size: 14px;
  line-height: 1.4;
  color: #374151;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.message-bubble.own-bubble {
  background: #2563eb;
  color: white;
}

.message-time {
  font-size: 11px;
  color: #9ca3af;
  margin-top: 4px;
}

/* Message Input */
.message-input-area {
  background: white;
  border-top: 1px solid #e5e7eb;
  padding: 16px 20px;
  flex-shrink: 0;
}

.input-container {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 24px;
  padding: 8px 12px;
}

.attachment-btn,
.emoji-btn,
.media-btn {
  background: none;
  border: none;
  padding: 6px;
  border-radius: 50%;
  cursor: pointer;
  font-size: 16px;
  color: #6b7280;
  transition: background 0.2s;
}

.attachment-btn:hover,
.emoji-btn:hover,
.media-btn:hover {
  background: #e5e7eb;
}

.message-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: 14px;
  padding: 4px 8px;
}

.send-btn {
  background: #2563eb;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.send-btn:hover {
  background: #1d4ed8;
}

/* Right Sidebar - Group Info */
.group-info-sidebar {
  width: 300px;
  background: white;
  border-left: 1px solid #e5e7eb;
  overflow-y: auto;
  flex-shrink: 0;
}

.group-header {
  padding: 24px 20px;
  text-align: center;
  border-bottom: 1px solid #f3f4f6;
}

.group-avatar-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.group-avatars {
  margin-bottom: 8px;
}

.avatar-stack {
  display: flex;
  gap: 4px;
}

.avatar-item {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: white;
  font-weight: bold;
}

.avatar-item.blue {
  background: #2563eb;
}

.avatar-item.dark {
  background: #374151;
}

.avatar-item.green {
  background: #10b981;
}

.group-name {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.group-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

/* Group Sections */
.group-section {
  padding: 16px 20px;
  border-bottom: 1px solid #f3f4f6;
}

.group-section .section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
  font-weight: 600;
  color: #374151;
}

.section-icon {
  margin-right: 8px;
}

.group-description {
  font-size: 14px;
  line-height: 1.5;
  color: #6b7280;
  margin: 0 0 12px 0;
}

.group-link {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #f3f4f6;
  border-radius: 8px;
}

.link-icon {
  color: #6b7280;
}

.group-link-text {
  flex: 1;
  font-size: 14px;
  color: #2563eb;
  text-decoration: none;
}

.copy-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 14px;
  color: #6b7280;
}

/* Toggle Switch */
.toggle-switch {
  width: 44px;
  height: 24px;
  background: #e5e7eb;
  border-radius: 12px;
  position: relative;
  cursor: pointer;
  transition: background 0.2s;
}

.toggle-switch.active {
  background: #2563eb;
}

.toggle-slider {
  width: 20px;
  height: 20px;
  background: white;
  border-radius: 50%;
  position: absolute;
  top: 2px;
  left: 2px;
  transition: transform 0.2s;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.toggle-switch.active .toggle-slider {
  transform: translateX(20px);
}

.member-count {
  font-size: 12px;
  color: #10b981;
  font-weight: 600;
}

/* Members List */
.members-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.member-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.member-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

.member-info {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.member-name {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

.member-subtitle {
  font-size: 12px;
  color: #6b7280;
}

.member-time {
  font-size: 12px;
  color: #9ca3af;
}

/* Media Grid */
.media-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px;
}

.media-item {
  aspect-ratio: 1;
  overflow: hidden;
  border-radius: 4px;
}

.media-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Responsive */
@media (max-width: 1024px) {
  .group-info-sidebar {
    display: none;
  }
}

@media (max-width: 768px) {
  .conversations-sidebar {
    width: 280px;
  }
  
  .floating-chat-widget {
    font-size: 14px;
  }
}

@media (max-width: 640px) {
  .conversations-sidebar {
    position: absolute;
    left: -100%;
    z-index: 10;
    transition: left 0.3s ease;
  }
  
  .conversations-sidebar.open {
    left: 0;
  }
}
</style>