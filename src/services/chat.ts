import {
  addDoc,
  collection,
  doc,
  getDoc,
  getDocs,
  onSnapshot,
  orderBy,
  query,
  serverTimestamp,
  updateDoc,
  where,
} from 'firebase/firestore'
import { db } from './firebase'

export interface ChatThread {
  id: string
  members: string[]
  title: string
  lastMessage?: string
  updatedAt?: string
  /** Map of uid -> display name for direct chats, so each user sees the other's name */
  memberNames?: Record<string, string>
}

export interface ChatMessage {
  id: string
  senderId: string
  text: string
  createdAt?: string
}

export function subscribeToChats(
  userId: string,
  callback: (threads: ChatThread[]) => void,
  onError?: (err: Error) => void
) {
  const chatsRef = collection(db, 'chats')
  const q = query(chatsRef, where('members', 'array-contains', userId), orderBy('updatedAt', 'desc'))
  return onSnapshot(
    q,
    (snapshot) => {
      const threads = snapshot.docs.map((docSnap) => ({
        id: docSnap.id,
        ...(docSnap.data() as Omit<ChatThread, 'id'>),
      }))
      callback(threads)
    },
    (err) => {
      console.warn('Chat subscription error:', err?.message || err)
      onError?.(err)
      callback([])
    }
  )
}

export async function ensureDefaultChat(userId: string) {
  const chatsRef = collection(db, 'chats')
  const q = query(chatsRef, where('members', 'array-contains', userId))
  const snapshot = await getDocs(q)
  if (!snapshot.empty) {
    return snapshot.docs[0].id
  }
  const chatDoc = await addDoc(chatsRef, {
    title: 'OJT Intern Path Support',
    members: [userId],
    lastMessage: 'Welcome! How can we help you today?',
    updatedAt: serverTimestamp(),
  })
  return chatDoc.id
}

/** Create or get existing direct chat between two users. Stores both names so each sees the other. */
export async function createOrGetDirectChat(
  userId1: string,
  userId2: string,
  options?: { title?: string; userName1?: string; userName2?: string }
): Promise<string> {
  if (userId1 === userId2) {
    throw new Error('Cannot create chat with yourself')
  }
  const members = [userId1, userId2].sort()
  const chatsRef = collection(db, 'chats')
  const q = query(chatsRef, where('members', 'array-contains', userId1))
  const snapshot = await getDocs(q)
  const existing = snapshot.docs.find((d) => {
    const m = (d.data().members as string[]) || []
    return m.length === 2 && m.includes(userId2)
  })
  if (existing) {
    const data = existing.data()
    const memberNames = (data.memberNames as Record<string, string>) || {}
    const needsUpdate =
      (options?.userName1 && !memberNames[userId1]) ||
      (options?.userName2 && !memberNames[userId2])
    if (needsUpdate && (options?.userName1 || options?.userName2)) {
      const updated = { ...memberNames }
      if (options.userName1) updated[userId1] = options.userName1
      if (options.userName2) updated[userId2] = options.userName2
      await updateDoc(doc(db, 'chats', existing.id), {
        memberNames: updated,
        updatedAt: serverTimestamp(),
      })
    }
    return existing.id
  }
  const memberNames: Record<string, string> = {}
  if (options?.userName1) memberNames[userId1] = options.userName1
  if (options?.userName2) memberNames[userId2] = options.userName2
  const displayTitle = options?.title || 'Direct Chat'
  const chatDoc = await addDoc(chatsRef, {
    title: displayTitle,
    members,
    memberNames: Object.keys(memberNames).length > 0 ? memberNames : undefined,
    lastMessage: '',
    updatedAt: serverTimestamp(),
  })
  return chatDoc.id
}

export function subscribeToMessages(
  chatId: string,
  callback: (messages: ChatMessage[]) => void,
  onError?: (err: Error) => void
) {
  const messagesRef = collection(db, 'chats', chatId, 'messages')
  const q = query(messagesRef, orderBy('createdAt', 'asc'))
  return onSnapshot(
    q,
    (snapshot) => {
      const messages = snapshot.docs.map((docSnap) => ({
        id: docSnap.id,
        ...(docSnap.data() as Omit<ChatMessage, 'id'>),
      }))
      callback(messages)
    },
    (err) => {
      console.warn('Messages subscription error:', err?.message || err)
      onError?.(err)
      callback([])
    }
  )
}

/** Merge memberNames into a chat (for backfilling when we fetch a missing name). */
export async function mergeChatMemberNames(
  chatId: string,
  updates: Record<string, string>
): Promise<void> {
  const chatRef = doc(db, 'chats', chatId)
  const snap = await getDoc(chatRef)
  if (!snap.exists()) return
  const data = snap.data()
  const memberNames = { ...((data?.memberNames as Record<string, string>) || {}), ...updates }
  await updateDoc(chatRef, { memberNames, updatedAt: serverTimestamp() })
}

export async function sendMessage(chatId: string, senderId: string, text: string) {
  const messagesRef = collection(db, 'chats', chatId, 'messages')
  await addDoc(messagesRef, {
    senderId,
    text,
    createdAt: serverTimestamp(),
  })
  await updateDoc(doc(db, 'chats', chatId), {
    lastMessage: text,
    updatedAt: serverTimestamp(),
  })
}
