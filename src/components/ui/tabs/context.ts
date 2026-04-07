import type { InjectionKey, Ref } from 'vue'

export interface TabsContextValue {
  modelValue: Ref<string>
  setValue: (value: string) => void
}

export const tabsContextKey: InjectionKey<TabsContextValue> = Symbol('tabsContext')
