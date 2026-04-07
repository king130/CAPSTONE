import { toast } from 'vue-sonner'

interface ToastOptions {
  description?: string
}

interface ErrorWithMessage {
  message?: string
}

function getErrorMessage(error: unknown, fallback = 'Something went wrong.') {
  if (typeof error === 'string' && error.trim()) {
    return error
  }

  if (error instanceof Error && error.message.trim()) {
    return error.message
  }

  const maybeMessage = error as ErrorWithMessage | null
  if (maybeMessage?.message && maybeMessage.message.trim()) {
    return maybeMessage.message
  }

  return fallback
}

export function useToast() {
  function success(message: string, options?: ToastOptions) {
    toast.success(message, { description: options?.description })
  }

  function error(error: unknown, options?: ToastOptions & { fallback?: string }) {
    toast.error(getErrorMessage(error, options?.fallback), {
      description: options?.description,
    })
  }

  return {
    success,
    error,
  }
}
