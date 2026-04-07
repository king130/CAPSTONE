export interface ChatThread {
  id: string
  members: string[]
  title: string
  lastMessage?: string
  updatedAt?: string
  memberNames?: Record<string, string>
}

export interface ChatMessage {
  id: string
  senderId: string
  text: string
  createdAt?: string
}

interface ChatStore {
  threads: ChatThread[]
  messages: Record<string, ChatMessage[]>
}

interface DirectChatOptions {
  title?: string
  userName1?: string
  userName2?: string
}

const STORAGE_KEY = 'ojt-chat-store'
const CHAT_CHANGED_EVENT = 'chat:changed'

function readStore(): ChatStore {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)
    if (!raw) {
      return { threads: [], messages: {} }
    }

    const parsed = JSON.parse(raw) as Partial<ChatStore>
    return {
      threads: Array.isArray(parsed.threads) ? parsed.threads : [],
      messages: parsed.messages && typeof parsed.messages === 'object' ? parsed.messages : {},
    }
  } catch {
    return { threads: [], messages: {} }
  }
}

function writeStore(store: ChatStore) {
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(store))
  window.dispatchEvent(new CustomEvent(CHAT_CHANGED_EVENT))
}

function notifySubscriber(callback: () => void) {
  const onStorage = (event: StorageEvent) => {
    if (event.key === STORAGE_KEY) callback()
  }

  window.addEventListener(CHAT_CHANGED_EVENT, callback)
  window.addEventListener('storage', onStorage)

  return () => {
    window.removeEventListener(CHAT_CHANGED_EVENT, callback)
    window.removeEventListener('storage', onStorage)
  }
}

function sortThreads(threads: ChatThread[]) {
  return [...threads].sort((left, right) => {
    const leftTime = new Date(left.updatedAt || 0).getTime()
    const rightTime = new Date(right.updatedAt || 0).getTime()
    return rightTime - leftTime
  })
}

function canonicalDirectChatId(userId1: string, userId2: string) {
  return `direct:${[userId1, userId2].sort().join(':')}`
}

export function subscribeToChats(
  userId: string,
  callback: (threads: ChatThread[]) => void,
  onError?: (err: Error) => void,
): () => void {
  const emit = () => {
    try {
      const store = readStore()
      callback(sortThreads(store.threads.filter((thread) => thread.members.includes(userId))))
    } catch (caughtError) {
      onError?.(caughtError instanceof Error ? caughtError : new Error('Unable to read chat threads.'))
    }
  }

  emit()
  return notifySubscriber(emit)
}

export async function ensureDefaultChat(userId: string): Promise<string> {
  const store = readStore()
  const firstThread = sortThreads(store.threads.filter((thread) => thread.members.includes(userId)))[0]
  return firstThread?.id || ''
}

export async function createOrGetDirectChat(
  userId1: string,
  userId2: string,
  options: DirectChatOptions = {},
): Promise<string> {
  const chatId = canonicalDirectChatId(userId1, userId2)
  const store = readStore()
  const existing = store.threads.find((thread) => thread.id === chatId)
  if (existing) {
    if (options.userName1 || options.userName2) {
      existing.memberNames = {
        ...(existing.memberNames ?? {}),
        ...(options.userName1 ? { [userId1]: options.userName1 } : {}),
        ...(options.userName2 ? { [userId2]: options.userName2 } : {}),
      }
      writeStore(store)
    }
    return chatId
  }

  const thread: ChatThread = {
    id: chatId,
    members: [userId1, userId2].sort(),
    title: options.title || 'Direct Chat',
    updatedAt: new Date().toISOString(),
    memberNames: {
      ...(options.userName1 ? { [userId1]: options.userName1 } : {}),
      ...(options.userName2 ? { [userId2]: options.userName2 } : {}),
    },
  }

  store.threads = [thread, ...store.threads]
  store.messages[chatId] = store.messages[chatId] ?? []
  writeStore(store)
  return chatId
}

export function subscribeToMessages(
  chatId: string,
  callback: (messages: ChatMessage[]) => void,
  onError?: (err: Error) => void,
): () => void {
  const emit = () => {
    try {
      const store = readStore()
      const messages = [...(store.messages[chatId] ?? [])].sort((left, right) => {
        const leftTime = new Date(left.createdAt || 0).getTime()
        const rightTime = new Date(right.createdAt || 0).getTime()
        return leftTime - rightTime
      })
      callback(messages)
    } catch (caughtError) {
      onError?.(caughtError instanceof Error ? caughtError : new Error('Unable to read chat messages.'))
    }
  }

  emit()
  return notifySubscriber(emit)
}

export async function sendChatMessage(chatId: string, senderId: string, text: string): Promise<void> {
  const content = text.trim()
  if (!content) return

  const store = readStore()
  const now = new Date().toISOString()
  const nextMessage: ChatMessage = {
    id: `${chatId}:${Date.now()}`,
    senderId,
    text: content,
    createdAt: now,
  }

  store.messages[chatId] = [...(store.messages[chatId] ?? []), nextMessage]
  const threadIndex = store.threads.findIndex((thread) => thread.id === chatId)
  if (threadIndex >= 0) {
    const existingThread = store.threads[threadIndex]
    if (!existingThread) {
      writeStore(store)
      return
    }

    store.threads[threadIndex] = {
      ...existingThread,
      lastMessage: content,
      updatedAt: now,
    }
  }

  writeStore(store)
}

export const sendMessage = sendChatMessage

export async function mergeChatMemberNames(chatId: string, memberNames: Record<string, string>): Promise<void> {
  const store = readStore()
  const threadIndex = store.threads.findIndex((thread) => thread.id === chatId)
  if (threadIndex < 0) return
  const existingThread = store.threads[threadIndex]
  if (!existingThread) return

  store.threads[threadIndex] = {
    ...existingThread,
    memberNames: {
      ...(existingThread.memberNames ?? {}),
      ...memberNames,
    },
  }

  writeStore(store)
}
