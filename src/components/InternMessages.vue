<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { 
  BellIcon,
  MagnifyingGlassIcon,
  PaperAirplaneIcon,
  PaperClipIcon,
  EllipsisVerticalIcon,
  PhoneIcon,
  VideoCameraIcon
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()

const userInitials = computed(() => {
  const name = authStore.user?.displayName || authStore.user?.email || 'User'
  return name
    .split(' ')
    .filter(Boolean)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

// TEMPORARY DATA: Conversations list - replace with real data from backend
const conversations = ref([
  {
    id: 1,
    name: 'TechCorp HR Team',
    avatar: '/icons/profiles/emily-johnson.jpg',
    lastMessage: 'Your application has been reviewed',
    timestamp: '2m ago',
    unread: 2,
    online: true
  },
  {
    id: 2,
    name: 'Maria Santos',
    avatar: '/icons/profiles/maria-santos.jpg',
    lastMessage: 'Thanks for the update!',
    timestamp: '1h ago',
    unread: 0,
    online: true
  },
  {
    id: 3,
    name: 'John Smith',
    avatar: '/icons/profiles/john-smith.jpg',
    lastMessage: 'Can we schedule a meeting?',
    timestamp: '3h ago',
    unread: 1,
    online: false
  },
  {
    id: 4,
    name: 'Innovation Labs',
    avatar: '/icons/profiles/david-kim.jpg',
    lastMessage: 'Welcome to the team!',
    timestamp: '1d ago',
    unread: 0,
    online: false
  }
])

// TEMPORARY DATA: Selected conversation messages - replace with real data from backend
const selectedConversation = ref(1)
const messages = ref([
  {
    id: 1,
    sender: 'TechCorp HR Team',
    content: 'Hello! Thank you for applying to our Software Engineering Internship position.',
    timestamp: '10:30 AM',
    isOwn: false
  },
  {
    id: 2,
    sender: 'You',
    content: 'Thank you for considering my application! I\'m very excited about this opportunity.',
    timestamp: '10:32 AM',
    isOwn: true
  },
  {
    id: 3,
    sender: 'TechCorp HR Team',
    content: 'We\'ve reviewed your application and would like to schedule an interview. Are you available next week?',
    timestamp: '10:35 AM',
    isOwn: false
  },
  {
    id: 4,
    sender: 'You',
    content: 'Yes, I\'m available next week. What days work best for you?',
    timestamp: '10:37 AM',
    isOwn: true
  },
  {
    id: 5,
    sender: 'TechCorp HR Team',
    content: 'Great! How about Tuesday at 2:00 PM? We\'ll send you a calendar invite with the meeting link.',
    timestamp: '10:40 AM',
    isOwn: false
  }
])

const messageInput = ref('')
const searchQuery = ref('')

function selectConversation(id: number) {
  selectedConversation.value = id
}

function sendMessage() {
  if (messageInput.value.trim()) {
    messages.value.push({
      id: messages.value.length + 1,
      sender: 'You',
      content: messageInput.value,
      timestamp: new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }),
      isOwn: true
    })
    messageInput.value = ''
  }
}

function handleAvatarClick() {
  console.log('Avatar clicked')
}

const activeConversation = computed(() => {
  return conversations.value.find(c => c.id === selectedConversation.value)
})
</script>

<template>
  <div class="messages-container">
    <!-- Header -->
    <header class="messages-header">
      <div class="header-left">
        <img src="/icons/icon-message.png" alt="Messages" class="header-icon" />
        <h1 class="header-title">Messages</h1>
      </div>
      <div class="header-right">
        <BellIcon class="notification-icon-bell" />
        <div class="avatar" @click="handleAvatarClick" title="View Profile">{{ userInitials }}</div>
      </div>
    </header>

    <div class="messages-layout">
      <!-- Conversations Sidebar -->
      <aside class="conversations-sidebar">
        <div class="sidebar-header">
          <div class="search-box">
            <MagnifyingGlassIcon class="search-icon" />
            <input 
              type="text" 
              v-model="searchQuery"
              placeholder="Search conversations..." 
              class="search-input"
            />
          </div>
        </div>

        <div class="conversations-list">
          <div 
            v-for="conversation in conversations" 
            :key="conversation.id"
            @click="selectConversation(conversation.id)"
            class="conversation-item"
            :class="{ active: selectedConversation === conversation.id }"
          >
            <div class="conversation-avatar-wrapper">
              <img :src="conversation.avatar" :alt="conversation.name" class="conversation-avatar" />
              <span v-if="conversation.online" class="online-indicator"></span>
            </div>
            <div class="conversation-info">
              <div class="conversation-header">
                <h3 class="conversation-name">{{ conversation.name }}</h3>
                <span class="conversation-time">{{ conversation.timestamp }}</span>
              </div>
              <div class="conversation-preview">
                <p class="last-message">{{ conversation.lastMessage }}</p>
                <span v-if="conversation.unread > 0" class="unread-badge">{{ conversation.unread }}</span>
              </div>
            </div>
          </div>
        </div>
      </aside>

      <!-- Chat Area -->
      <main class="chat-area">
        <div v-if="activeConversation" class="chat-container">
          <!-- Chat Header -->
          <div class="chat-header">
            <div class="chat-user-info">
              <div class="chat-avatar-wrapper">
                <img :src="activeConversation.avatar" :alt="activeConversation.name" class="chat-avatar" />
                <span v-if="activeConversation.online" class="online-dot"></span>
              </div>
              <div class="chat-user-details">
                <h2 class="chat-user-name">{{ activeConversation.name }}</h2>
                <span class="chat-user-status">{{ activeConversation.online ? 'Active now' : 'Offline' }}</span>
              </div>
            </div>
            <div class="chat-actions">
              <button class="chat-action-btn" title="Voice call">
                <PhoneIcon class="action-icon" />
              </button>
              <button class="chat-action-btn" title="Video call">
                <VideoCameraIcon class="action-icon" />
              </button>
              <button class="chat-action-btn" title="More options">
                <EllipsisVerticalIcon class="action-icon" />
              </button>
            </div>
          </div>

          <!-- Messages -->
          <div class="messages-area">
            <div v-for="message in messages" :key="message.id" class="message-wrapper" :class="{ own: message.isOwn }">
              <div class="message-bubble">
                <p class="message-content">{{ message.content }}</p>
                <span class="message-time">{{ message.timestamp }}</span>
              </div>
            </div>
          </div>

          <!-- Message Input -->
          <div class="message-input-area">
            <button class="attach-btn" title="Attach file">
              <PaperClipIcon class="attach-icon" />
            </button>
            <input 
              type="text" 
              v-model="messageInput"
              @keyup.enter="sendMessage"
              placeholder="Type a message..." 
              class="message-input"
            />
            <button @click="sendMessage" class="send-btn" :disabled="!messageInput.trim()">
              <PaperAirplaneIcon class="send-icon" />
            </button>
          </div>
        </div>

        <div v-else class="no-conversation">
          <div class="no-conversation-content">
            <img src="/icons/icon-message.png" alt="Messages" class="no-conversation-icon" />
            <h3 class="no-conversation-title">Select a conversation</h3>
            <p class="no-conversation-text">Choose a conversation from the list to start messaging</p>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
.messages-container {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background: #f8fafc;
}

/* Header */
.messages-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 32px;
  background: #dbeafe;
  border-bottom: 1px solid #bfdbfe;
  flex-shrink: 0;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon {
  width: 24px;
  height: 24px;
  object-fit: contain;
  filter: brightness(0) saturate(100%) invert(27%) sepia(51%) saturate(2878%) hue-rotate(220deg) brightness(104%) contrast(97%);
}

.header-title {
  font-size: 24px;
  font-weight: 700;
  color: #1e40af;
  margin: 0;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.notification-icon-bell {
  width: 24px;
  height: 24px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s ease;
}

.notification-icon-bell:hover {
  color: #2563eb;
  transform: scale(1.1);
}

.avatar {
  width: 36px;
  height: 36px;
  background: #3b82f6;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.avatar:hover {
  background: #2563eb;
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Layout */
.messages-layout {
  display: grid;
  grid-template-columns: 360px 1fr;
  flex: 1;
  overflow: hidden;
}

/* Conversations Sidebar */
.conversations-sidebar {
  background: #fff;
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.sidebar-header {
  padding: 20px;
  border-bottom: 1px solid #e5e7eb;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  width: 18px;
  height: 18px;
  color: #9ca3af;
}

.search-input {
  width: 100%;
  padding: 10px 12px 10px 40px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: all 0.2s;
}

.search-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.conversations-list {
  flex: 1;
  overflow-y: auto;
}

.conversation-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  cursor: pointer;
  transition: background 0.2s;
  border-bottom: 1px solid #f3f4f6;
}

.conversation-item:hover {
  background: #f9fafb;
}

.conversation-item.active {
  background: #eff6ff;
  border-left: 3px solid #2563eb;
}

.conversation-avatar-wrapper {
  position: relative;
  flex-shrink: 0;
}

.conversation-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
}

.online-indicator {
  position: absolute;
  bottom: 2px;
  right: 2px;
  width: 12px;
  height: 12px;
  background: #16a34a;
  border: 2px solid #fff;
  border-radius: 50%;
}

.conversation-info {
  flex: 1;
  min-width: 0;
}

.conversation-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.conversation-name {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.conversation-time {
  font-size: 12px;
  color: #9ca3af;
  flex-shrink: 0;
}

.conversation-preview {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.last-message {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
}

.unread-badge {
  background: #2563eb;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 10px;
  min-width: 20px;
  text-align: center;
  flex-shrink: 0;
}

/* Chat Area */
.chat-area {
  display: flex;
  flex-direction: column;
  background: #fff;
  overflow: hidden;
}

.chat-container {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.chat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: #fff;
}

.chat-user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.chat-avatar-wrapper {
  position: relative;
}

.chat-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.online-dot {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 10px;
  height: 10px;
  background: #16a34a;
  border: 2px solid #fff;
  border-radius: 50%;
}

.chat-user-details {
  display: flex;
  flex-direction: column;
}

.chat-user-name {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.chat-user-status {
  font-size: 12px;
  color: #6b7280;
}

.chat-actions {
  display: flex;
  gap: 8px;
}

.chat-action-btn {
  width: 36px;
  height: 36px;
  border: none;
  background: #f3f4f6;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.chat-action-btn:hover {
  background: #e5e7eb;
}

.action-icon {
  width: 20px;
  height: 20px;
  color: #6b7280;
}

/* Messages Area */
.messages-area {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
  background: #f9fafb;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.message-wrapper {
  display: flex;
  justify-content: flex-start;
}

.message-wrapper.own {
  justify-content: flex-end;
}

.message-bubble {
  max-width: 60%;
  padding: 12px 16px;
  border-radius: 16px;
  background: #fff;
  border: 1px solid #e5e7eb;
}

.message-wrapper.own .message-bubble {
  background: #2563eb;
  border-color: #2563eb;
}

.message-content {
  font-size: 14px;
  color: #374151;
  margin: 0 0 4px 0;
  line-height: 1.5;
}

.message-wrapper.own .message-content {
  color: #fff;
}

.message-time {
  font-size: 11px;
  color: #9ca3af;
}

.message-wrapper.own .message-time {
  color: rgba(255, 255, 255, 0.8);
}

/* Message Input */
.message-input-area {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  background: #fff;
}

.attach-btn {
  width: 40px;
  height: 40px;
  border: none;
  background: #f3f4f6;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
}

.attach-btn:hover {
  background: #e5e7eb;
}

.attach-icon {
  width: 20px;
  height: 20px;
  color: #6b7280;
}

.message-input {
  flex: 1;
  padding: 10px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 24px;
  font-size: 14px;
  outline: none;
  transition: all 0.2s;
}

.message-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.send-btn {
  width: 40px;
  height: 40px;
  border: none;
  background: #2563eb;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
}

.send-btn:hover:not(:disabled) {
  background: #1d4ed8;
}

.send-btn:disabled {
  background: #e5e7eb;
  cursor: not-allowed;
}

.send-icon {
  width: 20px;
  height: 20px;
  color: #fff;
}

.send-btn:disabled .send-icon {
  color: #9ca3af;
}

/* No Conversation */
.no-conversation {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  background: #f9fafb;
}

.no-conversation-content {
  text-align: center;
  max-width: 300px;
}

.no-conversation-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 20px;
  opacity: 0.3;
}

.no-conversation-title {
  font-size: 18px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 8px 0;
}

.no-conversation-text {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
  .messages-layout {
    grid-template-columns: 1fr;
  }
  
  .conversations-sidebar {
    display: none;
  }
  
  .message-bubble {
    max-width: 80%;
  }
}
</style>
