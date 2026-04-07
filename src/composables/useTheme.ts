import { computed } from 'vue'
import { useColorMode } from '@vueuse/core'

const colorMode = useColorMode({
  selector: 'html',
  attribute: 'class',
  initialValue: 'light',
  storageKey: 'ojt-color-mode',
  modes: {
    light: '',
    dark: 'dark',
  },
})

export function useTheme() {
  const isDark = computed(() => colorMode.value === 'dark')

  function toggleTheme() {
    colorMode.value = isDark.value ? 'light' : 'dark'
  }

  return {
    colorMode,
    isDark,
    toggleTheme,
  }
}
