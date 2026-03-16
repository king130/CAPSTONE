import {
  addDoc,
  collection,
  deleteDoc,
  doc,
  getDoc,
  getDocs,
  onSnapshot,
  orderBy,
  query,
  serverTimestamp,
  where,
} from 'firebase/firestore'
import { db } from './firebase'

export interface SchoolStudentRecord {
  id: string
  schoolId: string
  email: string
  studentName?: string
  studentNumber?: string
  defaultPassword?: string
  course?: string
  yearLevel?: string
  status: 'pending' | 'registered'
  createdAt?: unknown
}

/** Subscribe to student emails for a school. */
export function subscribeSchoolStudents(
  schoolId: string,
  callback: (items: SchoolStudentRecord[]) => void
) {
  const ref = collection(db, 'school_students')
  const q = query(
    ref,
    where('schoolId', '==', schoolId),
    orderBy('createdAt', 'desc')
  )
  return onSnapshot(
    q,
    (snapshot) => {
      const items = snapshot.docs.map((docSnap) => ({
        id: docSnap.id,
        ...(docSnap.data() as Omit<SchoolStudentRecord, 'id'>),
      }))
      callback(items)
    },
    (err) => {
      console.warn('School students subscription error:', err?.message || err)
      callback([])
    }
  )
}

/** Add a student email (the Gmail the school distributes). */
export async function addSchoolStudent(
  schoolId: string,
  email: string,
  options?: {
    studentName?: string
    studentNumber?: string
    defaultPassword?: string
    course?: string
    yearLevel?: string
  }
) {
  const normalizedEmail = email.trim().toLowerCase()
  if (!normalizedEmail) throw new Error('Email is required')

  const ref = collection(db, 'school_students')
  const docRef = await addDoc(ref, {
    schoolId,
    email: normalizedEmail,
    studentName: options?.studentName || null,
    studentNumber: options?.studentNumber || null,
    defaultPassword: options?.defaultPassword || null,
    course: options?.course || null,
    yearLevel: options?.yearLevel || null,
    status: 'pending',
    createdAt: serverTimestamp(),
  })
  return docRef.id
}

/** Remove a student email. */
export async function removeSchoolStudent(id: string) {
  await deleteDoc(doc(db, 'school_students', id))
}

/** Find school that has approved this student email. Returns schoolId and subscriptionCode if found. */
export async function findSchoolByStudentEmail(email: string): Promise<{
  schoolId: string
  subscriptionCode?: string
} | null> {
  const normalizedEmail = email.trim().toLowerCase()
  if (!normalizedEmail) return null

  const ref = collection(db, 'school_students')
  const q = query(ref, where('email', '==', normalizedEmail))
  const snapshot = await getDocs(q)
  if (snapshot.empty) return null

  const firstDoc = snapshot.docs[0]
  if (!firstDoc) return null
  const record = firstDoc.data() as SchoolStudentRecord
  const schoolDoc = await getDoc(doc(db, 'users', record.schoolId))
  const schoolData = schoolDoc.data()
  const subscriptionCode = schoolData?.subscription?.subscriptionCode as string | undefined

  return {
    schoolId: record.schoolId,
    subscriptionCode,
  }
}
