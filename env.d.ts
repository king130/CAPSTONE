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
    control_input: HTMLInputElement
    options: Record<string, unknown>
    optgroups: Record<string, unknown>
    destroy(): void
    on(event: string, handler: (...args: unknown[]) => void): void
    getValue(): string | string[]
    setValue(value: string[] | string, silent?: boolean): void
    sync(get_settings?: boolean): void
    addOption(data: { value: string; text: string; optgroup?: string }): void
    addOptionGroup(id: string, data: { label: string; value: string }): void
    focus(): void
    open(): void
    clear(silent?: boolean): void
    clearOptions(): void
    refreshOptions(triggerDropdown?: boolean): void
    setTextboxValue(value: string): void
    blur(): void
  }
}
