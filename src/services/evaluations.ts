export type EvaluationStatus = 'PENDING' | 'OVERDUE' | 'COMPLETED'

export interface EvaluationRecord {
  id: string
  companyId: string
  studentId: string
  studentName?: string
  supervisor: string
  type: string
  dueDate: unknown
  status: EvaluationStatus
  createdAt?: unknown
  updatedAt?: unknown
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

export async function createEvaluation(_payload: CreateEvaluationPayload): Promise<string> {
  return ''
}

export function subscribeCompanyEvaluations(
  _companyId: string,
  callback: (items: EvaluationRecord[]) => void,
  _onError?: (err: Error) => void
): () => void {
  callback([])
  return () => {}
}
