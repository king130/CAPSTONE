import {
  addDoc,
  collection,
  doc,
  getDoc,
  onSnapshot,
  orderBy,
  query,
  serverTimestamp,
  updateDoc,
} from 'firebase/firestore'
import { db } from './firebase'

export interface InternshipRecord {
  id: string
  title: string
  companyName: string
  location: string
  type: string
  duration: string
  slotsAvailable: number
  requirements?: string[]
}

export function subscribeInternships(callback: (items: InternshipRecord[]) => void) {
  const internshipsRef = collection(db, 'internships')
  const q = query(internshipsRef, orderBy('createdAt', 'desc'))
  return onSnapshot(q, (snapshot) => {
    const items = snapshot.docs.map((docSnap) => ({
      id: docSnap.id,
      ...(docSnap.data() as Omit<InternshipRecord, 'id'>),
    }))
    callback(items)
  })
}

export async function getInternship(internshipId: string) {
  const snapshot = await getDoc(doc(db, 'internships', internshipId))
  return snapshot.exists() ? (snapshot.data() as InternshipRecord) : null
}

export async function createInternship(payload: Omit<InternshipRecord, 'id'>) {
  const internshipsRef = collection(db, 'internships')
  const docRef = await addDoc(internshipsRef, {
    ...payload,
    createdAt: serverTimestamp(),
  })
  return docRef.id
}

export async function updateInternship(internshipId: string, payload: Partial<InternshipRecord>) {
  const internshipRef = doc(db, 'internships', internshipId)
  await updateDoc(internshipRef, { ...payload, updatedAt: serverTimestamp() })
}
