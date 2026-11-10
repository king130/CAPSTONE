import axios from 'axios'

import apiClient from './apiClient'

export const OJT_MOA_TEMPLATE_SLUG = 'ojt-moa'

export const OJT_MOA_DEFAULT_SUBJECT = 'OJT Memorandum of Agreement'

export const OJT_MOA_DEFAULT_PURPOSE =
  'This agreement formalizes a partnership for On-the-Job Training (OJT) placements. ' +
  'It covers how students will be assigned to your organization, supervised during their internship, ' +
  'and evaluated according to our academic requirements for the agreed period.'

export class AgreementSubmitError extends Error {
  fieldErrors: Record<string, string>

  constructor(message: string, fieldErrors: Record<string, string> = {}) {
    super(message)
    this.name = 'AgreementSubmitError'
    this.fieldErrors = fieldErrors
  }
}

const API_FIELD_TO_FORM: Record<string, string> = {
  subject: 'subject',
  purpose: 'purpose',
  startDate: 'startDate',
  endDate: 'endDate',
  notes: 'notes',
  schoolId: 'partnerUserId',
  companyId: 'partnerUserId',
  contract_type_id: 'selectedTypeId',
  dynamic_fields: 'dynamicFields',
}

function firstErrorMessage(value: unknown): string | undefined {
  if (Array.isArray(value) && value[0]) return String(value[0])
  if (typeof value === 'string' && value.trim()) return value.trim()
  if (value && typeof value === 'object') {
    for (const nested of Object.values(value as Record<string, unknown>)) {
      const msg = firstErrorMessage(nested)
      if (msg) return msg
    }
  }
  return undefined
}

export function mapAgreementValidationErrors(error: unknown): Record<string, string> {
  if (!axios.isAxiosError(error)) return {}

  const errs = (error.response?.data as { errors?: Record<string, unknown> } | undefined)?.errors
  if (!errs || typeof errs !== 'object') return {}

  const mapped: Record<string, string> = {}
  for (const [apiKey, raw] of Object.entries(errs)) {
    const message = firstErrorMessage(raw)
    if (!message) continue

    const formKey = API_FIELD_TO_FORM[apiKey] ?? apiKey
    if (!mapped[formKey]) mapped[formKey] = message

    if (apiKey.startsWith('dynamic_fields.') || apiKey.includes('.')) {
      const dynamicKey = apiKey.replace(/^dynamic_fields\./, '')
      mapped[`dynamic:${dynamicKey}`] = message
    }
  }

  return mapped
}

export function getAgreementSubmitErrorMessage(error: unknown, fallback = 'Could not send the agreement.') {
  if (error instanceof AgreementSubmitError) {
    return error.message || fallback
  }
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string } | undefined
    if (data?.message?.trim()) return data.message.trim()
  }
  if (error instanceof Error && error.message.trim()) return error.message
  return fallback
}

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
  baseContractTypeId?: string | null
  isActive?: boolean
  sortOrder?: number
}

export interface ManagedContractTypesResponse {
  customTypes: DynamicContractType[]
  globalTypes: DynamicContractType[]
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

export interface SubmittedContractResponse {
  data: {
    id: string
    moaReferenceNo?: string
  }
}

export async function fetchContractTypes(partnerUserId: string) {
  const response = await apiClient.get<{ data: DynamicContractType[] }>('/agreement-types', {
    params: { partnerUserId },
  })
  return response.data.data ?? []
}

export async function fetchManagedContractTypes() {
  const response = await apiClient.get<{ data: ManagedContractTypesResponse }>('/agreement-types/manage')
  return response.data.data
}

export async function createContractType(payload: {
  name: string
  description?: string
  baseContractTypeId?: string | null
  fieldsSchema: ContractFieldSchema[]
  defaultValues?: Record<string, unknown>
  isActive?: boolean
}) {
  const response = await apiClient.post<{ data: DynamicContractType }>('/agreement-types', payload)
  return response.data.data
}

export async function updateContractType(
  contractTypeId: string,
  payload: {
    name: string
    description?: string
    fieldsSchema: ContractFieldSchema[]
    defaultValues?: Record<string, unknown>
    isActive?: boolean
  },
) {
  const response = await apiClient.patch<{ data: DynamicContractType }>(`/agreement-types/${contractTypeId}`, payload)
  return response.data.data
}

export async function deleteContractType(contractTypeId: string) {
  await apiClient.delete(`/agreement-types/${contractTypeId}`)
}

export async function submitDynamicContract(payload: ContractSubmitPayload): Promise<SubmittedContractResponse> {
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

  try {
    const response = await apiClient.post<SubmittedContractResponse>('/agreements', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return response.data
  } catch (error) {
    const fieldErrors = mapAgreementValidationErrors(error)
    const message = getAgreementSubmitErrorMessage(error, 'Could not send the agreement.')
    throw new AgreementSubmitError(message, fieldErrors)
  }
}
