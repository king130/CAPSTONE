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
  status: 'pending' | 'active' | 'rejected'
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
}

/** Create a contract request between school and company (MOA-style). */
export async function createContractRequest(payload: {
  companyId: string
  companyName: string
  schoolId: string
  schoolName: string
  requestedByRole?: 'school' | 'company'
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
  const docRef = await addDoc(ref, {
    ...payload,
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
  return onSnapshot(q, (snapshot) => {
    const items = snapshot.docs.map((docSnap) => ({
      id: docSnap.id,
      ...(docSnap.data() as Omit<ContractRecord, 'id'>),
    }))
    callback(items)
  })
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
  return onSnapshot(q, (snapshot) => {
    const items = snapshot.docs.map((docSnap) => ({
      id: docSnap.id,
      ...(docSnap.data() as Omit<ContractRecord, 'id'>),
    }))
    callback(items)
  })
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
