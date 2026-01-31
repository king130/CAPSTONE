import {
  addDoc,
  collection,
  doc,
  onSnapshot,
  orderBy,
  query,
  serverTimestamp,
  updateDoc,
  where,
} from 'firebase/firestore'
import { db } from './firebase'

export interface ApplicationRecord {
  id: string
  internshipId: string
  studentId: string
  status: string
  createdAt?: string
}

export function subscribeApplications(userId: string, callback: (items: ApplicationRecord[]) => void) {
  const applicationsRef = collection(db, 'applications')
  const q = query(applicationsRef, where('studentId', '==', userId), orderBy('createdAt', 'desc'))
  return onSnapshot(q, (snapshot) => {
    const items = snapshot.docs.map((docSnap) => ({
      id: docSnap.id,
      ...(docSnap.data() as Omit<ApplicationRecord, 'id'>),
    }))
    callback(items)
  })
}

export async function submitApplication(payload: Omit<ApplicationRecord, 'id'>) {
  const applicationsRef = collection(db, 'applications')
  const docRef = await addDoc(applicationsRef, {
    ...payload,
    createdAt: serverTimestamp(),
  })
  return docRef.id
}

export async function updateApplicationStatus(applicationId: string, status: string) {
  const appRef = doc(db, 'applications', applicationId)
  await updateDoc(appRef, { status, updatedAt: serverTimestamp() })
}
