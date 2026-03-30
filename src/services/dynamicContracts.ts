import apiClient from './apiClient'

export interface ContractFieldSchema {
  key: string
  label: string
  type: 'text' | 'textarea' | 'date' | 'number' | 'select' | 'multiselect' | 'email' | 'checkbox'
  required?: boolean
  options?: string[]
  placeholder?: string
}

export interface DynamicContractType {
  id: string
  name: string
  slug: string
  description?: string
  scope: 'global' | 'organization'
  organizationId?: string | null
  organizationType?: 'school' | 'company' | null
  fieldsSchema: ContractFieldSchema[]
  defaultValues: Record<string, unknown>
  settings?: Record<string, unknown>
}

export interface ContractSubmitPayload {
  requestedByRole: 'school' | 'company'
  subject: string
  contractTypeId?: string
  contractTypeLabel?: string
  schoolId?: string
  companyId?: string
  purpose?: string
  startDate?: string
  endDate?: string
  notes?: string
  courseAllocations?: Array<{ course: string; slots: number }>
  dynamicFields?: Record<string, unknown>
  files?: File[]
}

export async function fetchContractTypes(partnerUserId: string) {
  const response = await apiClient.get<{ data: DynamicContractType[] }>('/contract-types', {
    params: { partnerUserId },
  })
  return response.data.data ?? []
}

export async function submitDynamicContract(payload: ContractSubmitPayload) {
  const form = new FormData()
  form.append('requestedByRole', payload.requestedByRole)
  form.append('subject', payload.subject)
  if (payload.contractTypeId) form.append('contract_type_id', payload.contractTypeId)
  if (payload.contractTypeLabel) form.append('contract_type_label', payload.contractTypeLabel)
  if (payload.schoolId) form.append('schoolId', payload.schoolId)
  if (payload.companyId) form.append('companyId', payload.companyId)
  if (payload.purpose) form.append('purpose', payload.purpose)
  if (payload.startDate) form.append('startDate', payload.startDate)
  if (payload.endDate) form.append('endDate', payload.endDate)
  if (payload.notes) form.append('notes', payload.notes)
  if (payload.courseAllocations?.length) {
    form.append('courseAllocations', JSON.stringify(payload.courseAllocations))
  }
  form.append('dynamic_fields', JSON.stringify(payload.dynamicFields ?? {}))

  for (const file of payload.files ?? []) {
    form.append('files[]', file)
  }

  const response = await apiClient.post('/contracts', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

  return response.data
}
