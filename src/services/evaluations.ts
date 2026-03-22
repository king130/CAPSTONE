import {
  addDoc,
  collection,
  doc,
  onSnapshot,
  orderBy,
  query,
  serverTimestamp,
  Timestamp,
  updateDoc,
  where,
} from 'firebase/firestore'
import { db } from './firebase'

export type EvaluationStatus = 'PENDING' | 'OVERDUE' | 'COMPLETED'

export interface EvaluationRecord {
  id: string
  companyId: string
  studentId: string
  studentName?: string
  supervisor: string
  type: string
  dueDate: Timestamp
  status: EvaluationStatus
  createdAt?: Timestamp
  updatedAt?: Timestamp
}

export interface CreateEvaluationPayload {
  companyId: string
  studentId: string
  studentName?: string
  supervisor: string
  type: string
  dueDate: Date
  status?: EvaluationStatus
}

export function subscribeCompanyEvaluations(
  companyId: string,
  callback: (items: EvaluationRecord[]) => void,
  onError?: (err: Error) => void
) {
  const evaluationsRef = collection(db, 'evaluations')
  const q = query(evaluationsRef, where('companyId', '==', companyId), orderBy('dueDate', 'asc'))
  return onSnapshot(
    q,
    (snapshot) => {
      const items = snapshot.docs.map((docSnap) => ({
        id: docSnap.id,
        ...(docSnap.data() as Omit<EvaluationRecord, 'id'>),
      }))
      callback(items)
    },
    (err) => {
      console.warn('subscribeCompanyEvaluations error:', err?.message || err)
      onError?.(err)
      callback([])
    }
  )
}

export async function createEvaluation(payload: CreateEvaluationPayload) {
  const evaluationsRef = collection(db, 'evaluations')
  const docRef = await addDoc(evaluationsRef, {
    companyId: payload.companyId,
    studentId: payload.studentId,
    studentName: payload.studentName ?? null,
    supervisor: payload.supervisor,
    type: payload.type,
    dueDate: Timestamp.fromDate(payload.dueDate),
    status: payload.status ?? 'PENDING',
    createdAt: serverTimestamp(),
    updatedAt: serverTimestamp(),
  })
  return docRef.id
}

export async function updateEvaluationStatus(evaluationId: string, status: EvaluationStatus) {
  const evaluationRef = doc(db, 'evaluations', evaluationId)
  await updateDoc(evaluationRef, { status, updatedAt: serverTimestamp() })
}

