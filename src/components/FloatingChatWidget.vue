<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
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
const selectedConversation = ref<string | null>(null)
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

function selectConversation(id: string) {
  selectedConversation.value = id
  
  // Check if this is a mock conversation
  if (id.startsWith('mock-')) {
    loadMockMessages(id)
  } else {
    startMessageListener(id)
  }
}

const activeConversation = computed(() => {
  return conversations.value.find(c => c.id === selectedConversation.value)
})

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
  if (!activeConversation.value) return
  const content = newMessage.value.trim()
  if (!content) return
  
  // Handle mock conversations
  if (activeConversation.value.id.startsWith('mock-')) {
    const now = new Date()
    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    messages.value.push({
      id: `msg-${Date.now()}`,
      sender: 'You',
      avatar: '/icons/profiles/alex-doe.jpg',
      message: content,
      time: timeStr,
      isOwn: true,
    })
    newMessage.value = ''
    return
  }
  
  // Handle real Firebase conversations
  if (!currentUserId.value) return
  await sendChatMessage(activeConversation.value.id, currentUserId.value, content)
  newMessage.value = ''
}

async function startChatListeners() {
  if (!currentUserId.value) {
    console.log('FloatingChatWidget: No user ID, loading mock data')
    loadMockConversations()
    return
  }
  
  try {
    const defaultChatId = await ensureDefaultChat(currentUserId.value)
    unsubChats.value = subscribeToChats(currentUserId.value, (threads) => {
      console.log('FloatingChatWidget: Received threads from Firebase:', threads)
      if (threads.length === 0) {
        console.log('FloatingChatWidget: No threads found, loading mock data')
        loadMockConversations()
      } else {
        conversations.value = threads.map(mapThread)
        if (!selectedConversation.value && conversations.value.length > 0) {
          const initial = conversations.value.find((chat) => chat.id === defaultChatId) || conversations.value[0]
          selectedConversation.value = initial.id
          startMessageListener(initial.id)
        }
      }
    })
  } catch (error) {
    console.error('FloatingChatWidget: Error loading chats, using mock data:', error)
    loadMockConversations()
  }
}

function loadMockConversations() {
  conversations.value = [
    {
      id: 'mock-1',
      name: 'OJT Support Team',
      subtitle: 'Support',
      avatar: '/icons/logo-main.png',
      lastMessage: 'Welcome! How can we help you?',
      time: '2m',
      unread: 0,
      online: true,
      isGroup: false,
    },
    {
      id: 'mock-2',
      name: 'Maria Santos',
      subtitle: 'UI/UX Designer',
      avatar: '/icons/profiles/maria-santos.jpg',
      lastMessage: 'Thanks for the feedback!',
      time: '1h',
      unread: 2,
      online: true,
      isGroup: false,
    },
    {
      id: 'mock-3',
      name: 'John Smith',
      subtitle: 'Project Manager',
      avatar: '/icons/profiles/john-smith.jpg',
      lastMessage: 'Meeting at 3pm tomorrow',
      time: '3h',
      unread: 0,
      online: false,
      isGroup: false,
    },
    {
      id: 'mock-4',
      name: 'Emily Johnson',
      subtitle: 'Frontend Developer',
      avatar: '/icons/profiles/emily-johnson.jpg',
      lastMessage: 'Check out the new design',
      time: '5h',
      unread: 1,
      online: true,
      isGroup: false,
    },
  ]
  
  // Auto-select first conversation
  if (conversations.value.length > 0) {
    selectedConversation.value = conversations.value[0].id
    loadMockMessages(conversations.value[0].id)
  }
}

function loadMockMessages(conversationId: string) {
  messages.value = [
    {
      id: 'msg-1',
      sender: 'Support',
      avatar: '/icons/logo-main.png',
      message: 'Hello! Welcome to OJT Intern Path. How can we assist you today?',
      time: '10:30 AM',
      isOwn: false,
    },
    {
      id: 'msg-2',
      sender: 'You',
      avatar: '/icons/profiles/alex-doe.jpg',
      message: 'Hi! I have a question about my internship application.',
      time: '10:32 AM',
      isOwn: true,
    },
    {
      id: 'msg-3',
      sender: 'Support',
      avatar: '/icons/logo-main.png',
      message: 'Of course! I\'d be happy to help. What would you like to know?',
      time: '10:33 AM',
      isOwn: false,
    },
  ]
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
  selectedConversation.value = null
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
    <ChatBubbleLeftRightIcon class="chat-button-icon" />
    <div v-if="unreadCount > 0" class="unread-badge">{{ unreadCount }}</div>
  </div>

  <!-- Full Screen Chat Widget -->
  <div v-if="isOpen" class="floating-chat-widget" :class="{ minimized: isMinimized }">
    <!-- Main Chat Header -->
    <div class="main-chat-header">
      <div class="header-left">
        <ChatBubbleLeftRightIcon class="header-icon" />
        <h1 class="header-title">Messages</h1>
      </div>
      <div class="header-right">
        <button @click="closeChat" class="close-btn">
          <XMarkIcon class="close-icon" />
        </button>
      </div>
    </div>

    <!-- Chat Content Container -->
    <div class="chat-container">
      <!-- Profiles Sidebar -->
      <aside class="profiles-sidebar">
        <div class="sidebar-header">
          <h2 class="sidebar-title">Chats</h2>
        </div>

        <div class="sidebar-search">
          <div class="search-box">
            <MagnifyingGlassIcon class="search-icon" />
            <input type="text" placeholder="Search conversations..." class="search-input" />
          </div>
        </div>

        <div class="profiles-list">
          <div 
            v-for="conversation in conversations" 
            :key="conversation.id"
            @click="selectConversation(conversation.id)"
            class="profile-item"
            :class="{ active: selectedConversation === conversation.id }"
          >
            <div class="profile-avatar-wrapper">
              <img :src="conversation.avatar" :alt="conversation.name" class="profile-avatar" @error="handleImageError" />
              <span v-if="conversation.online" class="online-badge"></span>
            </div>
            <div class="profile-info-sidebar">
              <h3 class="profile-name-sidebar">{{ conversation.name }}</h3>
              <p class="profile-last-message">{{ conversation.lastMessage }}</p>
            </div>
          </div>
        </div>
      </aside>

      <!-- Main Chat Area -->
      <main class="main-area" v-if="activeConversation">
        <!-- Profile Header -->
        <div class="profile-header">
          <div class="profile-info">
            <div class="profile-avatar-large-wrapper">
              <img :src="activeConversation.avatar" :alt="activeConversation.name" class="profile-avatar-large" @error="handleImageError" />
              <span v-if="activeConversation.online" class="online-badge-large"></span>
            </div>
            <div class="profile-details">
              <h2 class="profile-name">{{ activeConversation.name }}</h2>
              <p class="profile-status">{{ activeConversation.online ? 'Active now' : 'Offline' }}</p>
            </div>
          </div>
        </div>

        <!-- Messages Section -->
        <div class="messages-section">
          <div class="messages-container">
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

          <!-- Message Input -->
          <div class="message-input-container">
            <button class="input-action-btn" title="Attach file">
              <PlusIcon class="input-icon" />
            </button>
            <input 
              v-model="newMessage"
              type="text" 
              placeholder="Aa"
              class="message-input-field"
              @keyup.enter="sendMessage"
            />
            <button class="input-action-btn" title="Emoji">
              <FaceSmileIcon class="input-icon" />
            </button>
            <button @click="sendMessage" class="send-message-btn" :disabled="!newMessage.trim()">
              <PaperAirplaneIcon class="send-icon" />
            </button>
          </div>
        </div>
      </main>

      <div v-else class="empty-state">
        <div class="empty-state-content">
          <ChatBubbleLeftRightIcon class="empty-icon" />
          <h3 class="empty-title">Select a conversation</h3>
          <p class="empty-text">Choose a conversation from the list to start messaging</p>
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
  background: linear-gradient(135deg, #0084ff 0%, #0066cc 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 132, 255, 0.4);
  transition: all 0.3s ease;
  z-index: 1000;
}

.floating-chat-button:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 20px rgba(0, 132, 255, 0.5);
}

.chat-button-icon {
  width: 28px;
  height: 28px;
  color: white;
  stroke-width: 2;
}

.unread-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #fa3e3e;
  color: white;
  border-radius: 50%;
  min-width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  border: 2px solid white;
  padding: 0 4px;
}

/* Full Screen Chat Widget */
.floating-chat-widget {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: #fff;
  display: flex;
  flex-direction: column;
  z-index: 1001;
  font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
}

.floating-chat-widget.minimized {
  display: none;
}

/* Main Chat Header */
.main-chat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon {
  width: 28px;
  height: 28px;
  color: #2563eb;
  stroke-width: 2;
}

.header-title {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
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
  background: #f3f4f6;
}

.close-icon {
  width: 24px;
  height: 24px;
  color: #6b7280;
  stroke-width: 2.5;
}

/* Chat Container */
.chat-container {
  flex: 1;
  display: grid;
  grid-template-columns: 360px 1fr;
  overflow: hidden;
  background: #fff;
}

/* Profiles Sidebar */
.profiles-sidebar {
  background: #fff;
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.sidebar-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.sidebar-title {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.sidebar-search {
  padding: 12px 16px;
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
  color: #9ca3af;
  stroke-width: 2;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 10px 12px 10px 36px;
  border: none;
  border-radius: 24px;
  font-size: 15px;
  outline: none;
  transition: all 0.2s;
  background: #f0f2f5;
}

.search-input:focus {
  background: #e4e6eb;
}

/* Profiles List */
.profiles-list {
  flex: 1;
  overflow-y: auto;
  padding: 4px 8px;
}

.profile-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  cursor: pointer;
  transition: background 0.2s;
  position: relative;
  border-radius: 8px;
  margin-bottom: 2px;
}

.profile-item:hover {
  background: #f0f2f5;
}

.profile-item.active {
  background: #e7f3ff;
}

.profile-avatar-wrapper {
  position: relative;
  flex-shrink: 0;
}

.profile-avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  object-fit: cover;
  border: none;
}

.online-badge {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 14px;
  height: 14px;
  background: #31a24c;
  border: 3px solid #fff;
  border-radius: 50%;
}

.profile-info-sidebar {
  flex: 1;
  min-width: 0;
}

.profile-name-sidebar {
  font-size: 15px;
  font-weight: 600;
  color: #050505;
  margin: 0 0 4px 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.profile-last-message {
  font-size: 13px;
  color: #65676b;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Main Area */
.main-area {
  display: flex;
  flex-direction: column;
  background: #fff;
  overflow: hidden;
}

/* Profile Header */
.profile-header {
  padding: 12px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: #fff;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
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
  background: #31a24c;
  border: 3px solid #fff;
  border-radius: 50%;
}

.profile-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.profile-name {
  font-size: 15px;
  font-weight: 600;
  color: #050505;
  margin: 0;
  line-height: 1.3;
}

.profile-status {
  font-size: 12px;
  color: #65676b;
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
  background: #fff;
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
  max-width: 60%;
  padding: 8px 12px;
  border-radius: 18px;
  background: #f0f2f5;
  position: relative;
}

.message-row.own-message .message-bubble {
  background: #0084ff;
  border-radius: 18px;
}

.message-text {
  font-size: 15px;
  color: #050505;
  margin: 0;
  line-height: 1.4;
  word-wrap: break-word;
}

.message-row.own-message .message-text {
  color: #fff;
}

.message-timestamp {
  font-size: 11px;
  color: #65676b;
  margin-top: 4px;
  display: block;
}

.message-row.own-message .message-timestamp {
  color: rgba(255, 255, 255, 0.8);
}

/* Message Input */
.message-input-container {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  padding: 12px 24px 16px;
  border-top: 1px solid #e5e7eb;
  background: #fff;
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
  background: #f0f2f5;
}

.input-icon {
  width: 20px;
  height: 20px;
  color: #0084ff;
  stroke-width: 2;
}

.message-input-field {
  flex: 1;
  padding: 10px 16px;
  border: none;
  border-radius: 20px;
  font-size: 15px;
  outline: none;
  background: #f0f2f5;
  transition: all 0.2s;
  resize: none;
  max-height: 100px;
  line-height: 1.4;
}

.message-input-field:focus {
  background: #e4e6eb;
}

.send-message-btn {
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

.send-message-btn:hover:not(:disabled) {
  background: #f0f2f5;
}

.send-message-btn:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.send-icon {
  width: 20px;
  height: 20px;
  color: #0084ff;
  stroke-width: 2;
}

.send-message-btn:disabled .send-icon {
  color: #bcc0c4;
}

/* Empty State */
.empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  background: #fff;
}

.empty-state-content {
  text-align: center;
  max-width: 320px;
}

.empty-icon {
  width: 64px;
  height: 64px;
  color: #bcc0c4;
  stroke-width: 1.5;
  margin: 0 auto 16px;
}

.empty-title {
  font-size: 20px;
  font-weight: 600;
  color: #050505;
  margin: 0 0 8px 0;
}

.empty-text {
  font-size: 14px;
  color: #65676b;
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
    padding: 12px 16px;
  }
}
</style>