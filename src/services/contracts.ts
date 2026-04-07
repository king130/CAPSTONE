import { apiFetch } from './http'
import { createSharedPollingResource } from './sharedPolling'

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
  contractTypeId?: string | null
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
  dynamicFields?: Record<string, unknown>
  schemaSnapshot?: unknown[]
  metadata?: Record<string, unknown>
  attachments?: Array<{
    name: string
    size: number
    type: string
    url?: string
    path?: string
    disk?: string
  }>
  createdAt?: unknown
  updatedAt?: unknown
  rejectedReason?: string
  cancelledReason?: string
  cancelledAt?: unknown
  cancelledByRole?: 'school' | 'company'
}

export interface CreateContractPayload {
  companyId?: string
  companyName?: string
  schoolId?: string
  schoolName?: string
  requestedByRole: 'school' | 'company'
  subject: string
  contractType?: string
  contractTypeId?: string
  contractTypeLabel?: string
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
  dynamicFields?: Record<string, unknown>
  attachments?: Array<{
    name: string
    size: number
    type: string
  }>
  files?: File[]
}

async function loadAllContracts(): Promise<ContractRecord[]> {
  const res = await apiFetch<{ data: ContractRecord[] }>('/contracts')
  return res.data ?? []
}

const contractsResource = createSharedPollingResource<ContractRecord[]>({
  intervalMs: 15000,
  load: loadAllContracts,
  initialValue: [],
})

export async function createContractRequest(payload: CreateContractPayload): Promise<ContractRecord> {
  const form = new FormData()
  form.append('requestedByRole', payload.requestedByRole)
  form.append('subject', payload.subject)

  if (payload.contractType) form.append('contractType', payload.contractType)
  if (payload.contractTypeId) form.append('contract_type_id', payload.contractTypeId)
  if (payload.contractTypeLabel) form.append('contract_type_label', payload.contractTypeLabel)
  if (payload.companyId) form.append('companyId', payload.companyId)
  if (payload.schoolId) form.append('schoolId', payload.schoolId)
  if (payload.moaReferenceNo) form.append('moaReferenceNo', payload.moaReferenceNo)
  if (payload.purpose) form.append('purpose', payload.purpose)
  if (payload.startDate) form.append('startDate', payload.startDate)
  if (payload.endDate) form.append('endDate', payload.endDate)
  if (typeof payload.internshipSlots === 'number') {
    form.append('internshipSlots', String(payload.internshipSlots))
  }
  if (payload.studentPrograms) form.append('studentPrograms', payload.studentPrograms)
  if (payload.courseAllocations?.length) {
    form.append('courseAllocations', JSON.stringify(payload.courseAllocations))
  }
  if (payload.companyResponsibilities) form.append('companyResponsibilities', payload.companyResponsibilities)
  if (payload.schoolResponsibilities) form.append('schoolResponsibilities', payload.schoolResponsibilities)
  if (payload.terms) form.append('terms', payload.terms)
  if (payload.schoolContactName) form.append('schoolContactName', payload.schoolContactName)
  if (payload.schoolContactEmail) form.append('schoolContactEmail', payload.schoolContactEmail)
  if (payload.companyContactName) form.append('companyContactName', payload.companyContactName)
  if (payload.companyContactEmail) form.append('companyContactEmail', payload.companyContactEmail)
  if (payload.notes) form.append('notes', payload.notes)
  if (payload.dynamicFields) {
    form.append('dynamic_fields', JSON.stringify(payload.dynamicFields))
  }

  const attachments =
    payload.attachments ??
    payload.files?.map((file) => ({
      name: file.name,
      size: file.size,
      type: file.type || 'application/octet-stream',
    }))

  if (attachments?.length) {
    form.append('attachments', JSON.stringify(attachments))
  }

  for (const file of payload.files ?? []) {
    form.append('files[]', file)
  }

  const res = await apiFetch<{ data: ContractRecord }>('/contracts', {
    method: 'POST',
    body: form,
  })
  await contractsResource.refresh()
  return res.data
}

export function subscribeCompanyContracts(
  companyId: string,
  callback: (items: ContractRecord[]) => void
): () => void {
  return contractsResource.subscribe((items) => {
    callback(items.filter((item) => item.companyId === companyId))
  })
}

export function subscribeSchoolContracts(
  schoolId: string,
  callback: (items: ContractRecord[]) => void
): () => void {
  return contractsResource.subscribe((items) => {
    callback(items.filter((item) => item.schoolId === schoolId))
  })
}

export async function acceptContract(contractId: string): Promise<void> {
  await apiFetch(`/contracts/${contractId}/accept`, {
    method: 'PATCH',
  })
  await contractsResource.refresh()
}

export async function rejectContract(contractId: string, reason?: string): Promise<void> {
  await apiFetch(`/contracts/${contractId}/reject`, {
    method: 'PATCH',
    body: JSON.stringify({ reason }),
  })
  await contractsResource.refresh()
}

export async function cancelContract(
  contractId: string,
  cancelledByRole: 'school' | 'company',
  reason?: string
): Promise<void> {
  await apiFetch(`/contracts/${contractId}/cancel`, {
    method: 'PATCH',
    body: JSON.stringify({ cancelledByRole, reason }),
  })
  await contractsResource.refresh()
}
