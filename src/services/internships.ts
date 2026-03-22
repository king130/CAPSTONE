import {
  addDoc,
  collection,
  deleteDoc,
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
  description?: string
  companyId: string
  companyName: string
  location: string
  type: string
  duration: string
  slotsAvailable: number
  eligibleCourses?: string[]
  requirements?: string[]
  allowance?: string
  contactInfo?: string
  /** Review decision made by the school/admin. */
  approvalStatus?: 'pending' | 'approved' | 'declined'
  approvalNotes?: string
  status: 'active' | 'draft' | 'closed'
  createdAt?: unknown
  updatedAt?: unknown
}

export function subscribeInternships(callback: (items: InternshipRecord[]) => void) {
  const internshipsRef = collection(db, 'internships')
  const q = query(internshipsRef, orderBy('createdAt', 'desc'))
  return onSnapshot(
    q,
    (snapshot) => {
      const items = snapshot.docs.map((docSnap) => ({
        id: docSnap.id,
        ...(docSnap.data() as Omit<InternshipRecord, 'id'>),
      }))
      callback(items)
    },
    (err) => {
      console.warn('Internships subscription error:', err?.message || err)
      callback([])
    }
  )
}

export function subscribeActiveInternships(callback: (items: InternshipRecord[]) => void) {
  return subscribeInternships((items) => {
    const active = items.filter((i) => i.status === 'active')
    callback(active)
  })
}

export function subscribeCompanyInternships(companyId: string, callback: (items: InternshipRecord[]) => void) {
  return subscribeInternships((items) => {
    const company = items.filter((i) => i.companyId === companyId)
    callback(company)
  })
}

export async function getInternship(internshipId: string) {
  const snapshot = await getDoc(doc(db, 'internships', internshipId))
  return snapshot.exists() ? (snapshot.data() as InternshipRecord) : null
}

export type CreateInternshipPayload = Omit<InternshipRecord, 'id' | 'createdAt' | 'updatedAt'>

export async function createInternship(payload: CreateInternshipPayload) {
  const internshipsRef = collection(db, 'internships')
  const docRef = await addDoc(internshipsRef, {
    ...payload,
    status: payload.status || 'active',
    createdAt: serverTimestamp(),
    updatedAt: serverTimestamp(),
  })
  return docRef.id
}

export async function updateInternship(internshipId: string, payload: Partial<InternshipRecord>) {
  const internshipRef = doc(db, 'internships', internshipId)
  await updateDoc(internshipRef, { ...payload, updatedAt: serverTimestamp() })
}

export async function deleteInternship(internshipId: string) {
  const internshipRef = doc(db, 'internships', internshipId)
  await deleteDoc(internshipRef)
}
