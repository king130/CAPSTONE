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
  status: 'pending' | 'active' | 'rejected'
  contractType?: string
  purpose?: string
  startDate?: string
  endDate?: string
  companyResponsibilities?: string
  schoolResponsibilities?: string
  terms?: string
  createdAt?: unknown
  updatedAt?: unknown
  rejectedReason?: string
}

/** Create a contract request from company to school (MOA-style). */
export async function createContractRequest(payload: {
  companyId: string
  companyName: string
  schoolId: string
  schoolName: string
  contractType?: string
  purpose?: string
  startDate?: string
  endDate?: string
  companyResponsibilities?: string
  schoolResponsibilities?: string
  terms?: string
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

/** Accept a contract (school only). */
export async function acceptContract(contractId: string) {
  const ref = doc(db, 'contracts', contractId)
  await updateDoc(ref, {
    status: 'active',
    updatedAt: serverTimestamp(),
  })
}

/** Reject a contract (school only). */
export async function rejectContract(contractId: string, reason?: string) {
  const ref = doc(db, 'contracts', contractId)
  await updateDoc(ref, {
    status: 'rejected',
    rejectedReason: reason || null,
    updatedAt: serverTimestamp(),
  })
}
