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
  companyId?: string
  internshipTitle?: string
  studentName?: string
  studentEmail?: string
  studentCourse?: string
  status: string
  resume?: string | null
  documents?: string[] | null
  documentsPending?: boolean
  createdAt?: string
  updatedAt?: string
}

export function subscribeApplications(userId: string, callback: (items: ApplicationRecord[]) => void) {
  const applicationsRef = collection(db, 'applications')
  const q = query(applicationsRef, where('studentId', '==', userId), orderBy('createdAt', 'desc'))
  return onSnapshot(
    q,
    (snapshot) => {
      const items = snapshot.docs.map((docSnap) => ({
        id: docSnap.id,
        ...(docSnap.data() as Omit<ApplicationRecord, 'id'>),
      }))
      callback(items)
    },
    (err) => {
      console.warn('Applications subscription error:', err?.message || err)
      callback([])
    }
  )
}

/** Subscribe to applications for a company's internships (by companyId). */
export function subscribeCompanyApplications(
  companyId: string,
  callback: (items: ApplicationRecord[]) => void,
  onError?: (err: Error) => void
) {
  const applicationsRef = collection(db, 'applications')
  const q = query(applicationsRef, where('companyId', '==', companyId))
  return onSnapshot(
    q,
    (snapshot) => {
      const items = snapshot.docs.map((docSnap) => ({
        id: docSnap.id,
        ...(docSnap.data() as Omit<ApplicationRecord, 'id'>),
      }))
      items.sort((a, b) => {
        const aT = (a.createdAt as { toMillis?: () => number })?.toMillis?.() ?? 0
        const bT = (b.createdAt as { toMillis?: () => number })?.toMillis?.() ?? 0
        return bT - aT
      })
      callback(items)
    },
    (err) => {
      console.warn('subscribeCompanyApplications error:', err?.message || err)
      onError?.(err)
      callback([])
    }
  )
}

/** Subscribe to applications for a company's internships (by internship IDs). Fallback when companyId is missing. */
export function subscribeCompanyApplicationsByInternships(
  internshipIds: string[],
  callback: (items: ApplicationRecord[]) => void,
  onError?: (err: Error) => void
) {
  if (internshipIds.length === 0) {
    callback([])
    return () => {}
  }
  const ids = internshipIds.slice(0, 30).map(String)
  const applicationsRef = collection(db, 'applications')
  const q = query(applicationsRef, where('internshipId', 'in', ids))
  return onSnapshot(
    q,
    (snapshot) => {
      const items = snapshot.docs.map((docSnap) => ({
        id: docSnap.id,
        ...(docSnap.data() as Omit<ApplicationRecord, 'id'>),
      }))
      items.sort((a, b) => {
        const aT = (a.createdAt as { toMillis?: () => number })?.toMillis?.() ?? 0
        const bT = (b.createdAt as { toMillis?: () => number })?.toMillis?.() ?? 0
        return bT - aT
      })
      callback(items)
    },
    (err) => {
      console.warn('subscribeCompanyApplicationsByInternships error:', err?.message || err)
      onError?.(err)
      callback([])
    }
  )
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

/** Update documents on an existing application (for future document upload). */
export async function updateApplicationDocuments(
  applicationId: string,
  updates: { resume?: string | null; documents?: string[] | null; documentsPending?: boolean }
) {
  const appRef = doc(db, 'applications', applicationId)
  await updateDoc(appRef, {
    ...updates,
    updatedAt: serverTimestamp(),
  })
}
