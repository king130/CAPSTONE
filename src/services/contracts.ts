import {
  addDoc,
  collection,
  getDocs,
  onSnapshot,
  orderBy,
  query,
  serverTimestamp,
  updateDoc,
  where,
  doc,
} from 'firebase/firestore'
import { db } from './firebase'

export interface ContractRecord {
  id: string
  companyId: string
  companyName: string
  schoolId: string
  schoolName: string
  requestedByRole?: 'school' | 'company'
  status: 'pending' | 'active' | 'rejected' | 'cancelled'
  subject?: string
  contractType?: string
  moaReferenceNo?: string
  purpose?: string
  startDate?: string
  endDate?: string
  internshipSlots?: number
  studentPrograms?: string
  courseAllocations?: Array<{
    course: string
    slots: number
  }>
  companyResponsibilities?: string
  schoolResponsibilities?: string
  terms?: string
  schoolContactName?: string
  schoolContactEmail?: string
  companyContactName?: string
  companyContactEmail?: string
  notes?: string
  attachments?: Array<{
    name: string
    size: number
    type: string
  }>
  createdAt?: unknown
  updatedAt?: unknown
  rejectedReason?: string
  cancelledReason?: string
  cancelledAt?: unknown
  cancelledByRole?: 'school' | 'company'
}

function generateMoaReferenceNo() {
  const now = new Date()
  const y = now.getFullYear()
  const m = String(now.getMonth() + 1).padStart(2, '0')
  const d = String(now.getDate()).padStart(2, '0')
  const rand = Math.random().toString(16).slice(2, 6).toUpperCase()
  return `MOA-${y}${m}${d}-${rand}`
}

/** Create a contract request between school and company (MOA-style). */
export async function createContractRequest(payload: {
  companyId: string
  companyName: string
  schoolId: string
  schoolName: string
  requestedByRole?: 'school' | 'company'
  subject?: string
  contractType?: string
  moaReferenceNo?: string
  purpose?: string
  startDate?: string
  endDate?: string
  internshipSlots?: number
  studentPrograms?: string
  courseAllocations?: Array<{
    course: string
    slots: number
  }>
  companyResponsibilities?: string
  schoolResponsibilities?: string
  terms?: string
  schoolContactName?: string
  schoolContactEmail?: string
  companyContactName?: string
  companyContactEmail?: string
  notes?: string
  attachments?: Array<{
    name: string
    size: number
    type: string
  }>
}) {
  const ref = collection(db, 'contracts')

  // Prevent duplicate pending requests for the same school/company pair (school-side requirement).
  if ((payload.requestedByRole || 'school') === 'school') {
    const pendingQ = query(
      ref,
      where('schoolId', '==', payload.schoolId),
      where('companyId', '==', payload.companyId),
      where('status', '==', 'pending')
    )
    const pendingSnap = await getDocs(pendingQ)
    if (!pendingSnap.empty) {
      throw new Error('You already have a pending contract request with this company.')
    }
  }

  const moaReferenceNo = payload.moaReferenceNo?.trim() || generateMoaReferenceNo()
  const docRef = await addDoc(ref, {
    ...payload,
    moaReferenceNo,
    status: 'pending',
    createdAt: serverTimestamp(),
    updatedAt: serverTimestamp(),
  })
  return docRef.id
}

/** Subscribe to contracts for a company. */
export function subscribeCompanyContracts(
  companyId: string,
  callback: (items: ContractRecord[]) => void
) {
  const ref = collection(db, 'contracts')
  const q = query(
    ref,
    where('companyId', '==', companyId),
    orderBy('createdAt', 'desc')
  )
  return onSnapshot(
    q,
    (snapshot) => {
      const items = snapshot.docs.map((docSnap) => ({
        id: docSnap.id,
        ...(docSnap.data() as Omit<ContractRecord, 'id'>),
      }))
      callback(items)
    },
    (err) => {
      console.warn('Company contracts subscription error:', err?.message || err)
      callback([])
    }
  )
}

/** Subscribe to contracts for a school. */
export function subscribeSchoolContracts(
  schoolId: string,
  callback: (items: ContractRecord[]) => void
) {
  const ref = collection(db, 'contracts')
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
        ...(docSnap.data() as Omit<ContractRecord, 'id'>),
      }))
      callback(items)
    },
    (err) => {
      console.warn('School contracts subscription error:', err?.message || err)
      callback([])
    }
  )
}

/** Accept a contract request. */
export async function acceptContract(contractId: string) {
  const ref = doc(db, 'contracts', contractId)
  await updateDoc(ref, {
    status: 'active',
    updatedAt: serverTimestamp(),
  })
}

/** Reject a contract request. */
export async function rejectContract(contractId: string, reason?: string) {
  const ref = doc(db, 'contracts', contractId)
  await updateDoc(ref, {
    status: 'rejected',
    rejectedReason: reason || null,
    updatedAt: serverTimestamp(),
  })
}

/** Cancel a contract (works for pending or active). */
export async function cancelContract(contractId: string, cancelledByRole: 'school' | 'company', reason?: string) {
  const ref = doc(db, 'contracts', contractId)
  await updateDoc(ref, {
    status: 'cancelled',
    cancelledByRole,
    cancelledReason: reason || null,
    cancelledAt: serverTimestamp(),
    updatedAt: serverTimestamp(),
  })
}
