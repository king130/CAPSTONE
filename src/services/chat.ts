export interface ChatThread {
  id: string
  members: string[]
  title: string
  lastMessage?: string
  updatedAt?: string
  memberNames?: Record<string, string>
}

export interface ChatMessage {
  id: string
  senderId: string
  text: string
  createdAt?: string
}

export function subscribeToChats(
  _userId: string,
  callback: (threads: ChatThread[]) => void,
  _onError?: (err: Error) => void
): () => void {
  callback([])
  return () => {}
}

export async function ensureDefaultChat(_userId: string): Promise<string> {
  return 'local'
}

export async function createOrGetDirectChat(
  _userId1: string,
  _userId2: string,
  _options?: { title?: string; userName1?: string; userName2?: string }
): Promise<string> {
  return 'local'
}

export function subscribeToMessages(
  _chatId: string,
  callback: (messages: ChatMessage[]) => void,
  _onError?: (err: Error) => void
): () => void {
  callback([])
  return () => {}
}

export async function sendChatMessage(_chatId: string, _text: string): Promise<void> {}

export const sendMessage = sendChatMessage

export async function mergeChatMemberNames(
  _chatId: string,
  _memberNames: Record<string, string>
): Promise<void> {}
