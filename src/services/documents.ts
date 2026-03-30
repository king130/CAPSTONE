import { apiFetch } from './http'

export type DocumentStatus = 'approved' | 'pending' | 'rejected'

export interface DocumentRecord {
  id: string
  category: string
  fileName: string
  fileUrl: string
  fileType?: string | null
  fileSize?: number | null
  storageProvider?: string | null
  status: DocumentStatus
  createdAt?: string
  updatedAt?: string
}

export async function listDocuments(category?: string): Promise<DocumentRecord[]> {
  const query = category ? `?category=${encodeURIComponent(category)}` : ''
  const response = await apiFetch<{ data?: DocumentRecord[] }>(`/documents${query}`)
  return response.data ?? []
}

export async function uploadDocuments(payload: {
  files: File[]
  category?: string
  status?: DocumentStatus
}): Promise<DocumentRecord[]> {
  const form = new FormData()
  if (payload.category) form.append('category', payload.category)
  if (payload.status) form.append('status', payload.status)
  payload.files.forEach((file) => {
    form.append('files[]', file)
  })

  const response = await apiFetch<{ data?: DocumentRecord[] }>('/documents', {
    method: 'POST',
    body: form,
  })
  return response.data ?? []
}
