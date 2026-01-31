import {
  addDoc,
  collection,
  doc,
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
}

export interface ChatMessage {
  id: string
  senderId: string
  text: string
  createdAt?: string
}

export function subscribeToChats(userId: string, callback: (threads: ChatThread[]) => void) {
  const chatsRef = collection(db, 'chats')
  const q = query(chatsRef, where('members', 'array-contains', userId), orderBy('updatedAt', 'desc'))
  return onSnapshot(q, (snapshot) => {
    const threads = snapshot.docs.map((docSnap) => ({
      id: docSnap.id,
      ...(docSnap.data() as Omit<ChatThread, 'id'>),
    }))
    callback(threads)
  })
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

export function subscribeToMessages(chatId: string, callback: (messages: ChatMessage[]) => void) {
  const messagesRef = collection(db, 'chats', chatId, 'messages')
  const q = query(messagesRef, orderBy('createdAt', 'asc'))
  return onSnapshot(q, (snapshot) => {
    const messages = snapshot.docs.map((docSnap) => ({
      id: docSnap.id,
      ...(docSnap.data() as Omit<ChatMessage, 'id'>),
    }))
    callback(messages)
  })
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
