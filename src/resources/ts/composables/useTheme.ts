// resources/ts/composables/useTheme.ts
import { ref, onMounted, watchEffect } from 'vue'

const isDark = ref(false)

function applyTheme() {
  if (typeof document === 'undefined') return
  const html = document.documentElement

  if (isDark.value) {
    html.classList.add('dark')
  } else {
    html.classList.remove('dark')
  }
}

onMounted(() => {
  if (typeof window === 'undefined') return

  const saved = window.localStorage.getItem('theme')

  if (saved === 'dark') {
    isDark.value = true
  } else if (saved === 'light') {
    isDark.value = false
  } else {
    // fallback to system preference
    isDark.value = window.matchMedia &&
      window.matchMedia('(prefers-color-scheme: dark)').matches
  }

  applyTheme()
})

watchEffect(() => {
  if (typeof window === 'undefined') return
  applyTheme()
  window.localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
})

export function useTheme() {
  const toggleTheme = () => {
    isDark.value = !isDark.value
  }

  return {
    isDark,
    toggleTheme,
  }
}
