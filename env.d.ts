/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Base URL for Laravel API, e.g. http://localhost:8000/api — optional if Vite proxy is used */
  readonly VITE_API_BASE_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

declare module 'tom-select' {
  export default class TomSelect {
    constructor(element: HTMLSelectElement | string, settings?: unknown)
    destroy(): void
    on(event: string, handler: (...args: unknown[]) => void): void
    getValue(): string | string[]
    setValue(value: string[] | string, silent?: boolean): void
    sync(get_settings?: boolean): void
  }
}
