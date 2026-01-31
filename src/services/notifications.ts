import {
  addDoc,
  collection,
  onSnapshot,
  orderBy,
  query,
  serverTimestamp,
  updateDoc,
  where,
  doc,
} from 'firebase/firestore'
import { db } from './firebase'

export interface NotificationItem {
  id: string
  userId: string
  title: string
  body: string
  readAt?: string | null
  createdAt?: string
}

export function subscribeToNotifications(userId: string, callback: (items: NotificationItem[]) => void) {
  const notificationsRef = collection(db, 'notifications')
  const q = query(notificationsRef, where('userId', '==', userId), orderBy('createdAt', 'desc'))
  return onSnapshot(q, (snapshot) => {
    const items = snapshot.docs.map((docSnap) => ({
      id: docSnap.id,
      ...(docSnap.data() as Omit<NotificationItem, 'id'>),
    }))
    callback(items)
  })
}

export async function sendNotification(userId: string, title: string, body: string) {
  const notificationsRef = collection(db, 'notifications')
  await addDoc(notificationsRef, {
    userId,
    title,
    body,
    readAt: null,
    createdAt: serverTimestamp(),
  })
}

export async function markNotificationRead(notificationId: string) {
  const notificationRef = doc(db, 'notifications', notificationId)
  await updateDoc(notificationRef, { readAt: serverTimestamp() })
}
