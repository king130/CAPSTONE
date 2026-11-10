import { apiFetch } from './http'

export interface ChatPeer {
  id: string
  name: string
  role: string | null
}

export interface ChatAttachment {
  name: string | null
  mime: string | null
  size: number | null
  url: string
}

export interface ChatLatestMessage {
  id: string
  body: string
  senderId: string
  createdAt: string | null
  hasAttachment?: boolean
}

export interface ChatConversation {
  id: string
  peer: ChatPeer | null
  latestMessage: ChatLatestMessage | null
  lastReadAt: string | null
  peerLastReadAt?: string | null
  unreadCount: number
  updatedAt: string | null
  createdAt: string | null
}

export interface ChatMessage {
  id: string
  conversationId: string
  senderId: string
  body: string
  createdAt: string | null
  attachment?: ChatAttachment | null
}

/** @deprecated Prefer ChatMessage.body — kept for UI compatibility mapping */
export type ChatThread = ChatConversation

export class ChatApiError extends Error {
  readonly status: number

  constructor(message: string, status = 0) {
    super(message)
    this.name = 'ChatApiError'
    this.status = status
  }

  get isForbidden(): boolean {
    return this.status === 403 || this.message === 'Forbidden.'
  }
}

function inferChatErrorStatus(message: string): number {
  const normalized = message.trim().toLowerCase()
  if (normalized === 'forbidden.' || normalized === 'forbidden' || normalized === 'account disabled') {
    return 403
  }
  if (normalized.includes('unauthenticated') || normalized === 'unauthorized') {
    return 401
  }
  if (normalized.includes('verification is pending') || normalized.includes('under review')) {
    return 403
  }
  return 0
}

async function chatFetch<T>(path: string, init: RequestInit = {}): Promise<T> {
  try {
    return await apiFetch<T>(path, init)
  } catch (caught) {
    const message = caught instanceof Error ? caught.message : 'Request failed'
    throw new ChatApiError(message, inferChatErrorStatus(message))
  }
}

export interface ChatEligiblePartner {
  uid: string
  displayName: string
  role: string | null
  orgName?: string
  email?: string
  courses?: string[]
  verificationStatus?: string | null
}

export async function listConversations(): Promise<ChatConversation[]> {
  const res = await chatFetch<{ data: ChatConversation[] }>('/conversations')
  return Array.isArray(res.data) ? res.data : []
}

/** Relationship-eligible, approved counterparties for the new-chat picker. */
export async function listEligibleChatPartners(): Promise<ChatEligiblePartner[]> {
  const res = await chatFetch<{ data: ChatEligiblePartner[] }>('/conversations/eligible-partners')
  return Array.isArray(res.data) ? res.data : []
}

export async function openConversation(peerUserId: string | number): Promise<ChatConversation> {
  const res = await chatFetch<{ data: ChatConversation }>('/conversations', {
    method: 'POST',
    body: JSON.stringify({ peerUserId: Number(peerUserId) }),
  })
  return res.data
}

export async function listMessages(conversationId: string): Promise<ChatMessage[]> {
  const res = await chatFetch<{ data: ChatMessage[] }>(`/conversations/${conversationId}/messages`)
  return Array.isArray(res.data) ? res.data : []
}

export async function sendChatMessage(
  conversationId: string,
  body: string,
  attachment?: File | null,
): Promise<ChatMessage> {
  if (attachment) {
    const form = new FormData()
    if (body.trim()) form.append('body', body.trim())
    form.append('attachment', attachment)
    const res = await chatFetch<{ data: ChatMessage }>(`/conversations/${conversationId}/messages`, {
      method: 'POST',
      body: form,
    })
    return res.data
  }

  const res = await chatFetch<{ data: ChatMessage }>(`/conversations/${conversationId}/messages`, {
    method: 'POST',
    body: JSON.stringify({ body }),
  })
  return res.data
}

/** @deprecated Use sendChatMessage(conversationId, body) — sender is server-enforced */
export async function sendMessage(conversationId: string, _senderId: string, text: string): Promise<ChatMessage> {
  return sendChatMessage(conversationId, text)
}

export async function markConversationRead(conversationId: string): Promise<{ conversationId: string; lastReadAt: string | null }> {
  const res = await chatFetch<{ data: { conversationId: string; lastReadAt: string | null } }>(
    `/conversations/${conversationId}/read`,
    { method: 'PATCH' },
  )
  return res.data
}
