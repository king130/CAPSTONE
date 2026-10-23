import { nextTick, onBeforeUnmount, ref, watch, type CSSProperties, type Ref } from 'vue'

type OverlayAlign = 'start' | 'end' | 'center'

/**
 * Positions a teleported panel against its trigger so it is not clipped by
 * overflow or trapped in a parent stacking context.
 */
export function useAnchoredOverlay(open: Ref<boolean>, align: OverlayAlign = 'end', onDismiss?: () => void) {
  const triggerRef = ref<HTMLElement | null>(null)
  const panelRef = ref<HTMLElement | null>(null)
  const panelStyle = ref<CSSProperties>({})

  function updatePosition() {
    const trigger = triggerRef.value
    if (!trigger || !open.value) return

    const rect = trigger.getBoundingClientRect()
    const margin = 8
    const offset = 8
    const panel = panelRef.value
    const panelHeight = panel?.offsetHeight ?? 0
    let top = rect.bottom + offset

    if (panelHeight > 0 && top + panelHeight > window.innerHeight - margin) {
      const above = rect.top - offset - panelHeight
      if (above >= margin) top = above
    }

    const style: CSSProperties = {
      position: 'fixed',
      top: `${Math.max(margin, top)}px`,
      zIndex: 'var(--z-dropdown)',
      maxWidth: `calc(100vw - ${margin * 2}px)`,
      maxHeight: `calc(100vh - ${margin * 2}px)`,
      overflowY: 'auto',
    }

    const panelWidth = panel?.offsetWidth ?? 0

    if (align === 'end') {
      let right = Math.max(margin, window.innerWidth - rect.right)
      if (panelWidth > 0) {
        const leftEdge = window.innerWidth - right - panelWidth
        if (leftEdge < margin) {
          right = Math.max(margin, window.innerWidth - margin - panelWidth)
        }
      }
      style.right = `${right}px`
    } else if (align === 'start') {
      style.left = `${Math.max(margin, Math.min(rect.left, window.innerWidth - margin))}px`
    } else {
      const half = panelWidth > 0 ? panelWidth / 2 : 0
      const center = rect.left + rect.width / 2
      const left = half > 0 ? Math.min(Math.max(center, margin + half), window.innerWidth - margin - half) : center
      style.left = `${left}px`
      style.transform = 'translateX(-50%)'
    }

    panelStyle.value = style
  }

  function onLayoutChange() {
    updatePosition()
  }

  function containsTarget(target: EventTarget | null) {
    if (!(target instanceof Node)) return false
    return Boolean(triggerRef.value?.contains(target) || panelRef.value?.contains(target))
  }

  function onKeydown(event: KeyboardEvent) {
    if (event.key !== 'Escape') return
    if (onDismiss) onDismiss()
    else open.value = false
  }

  watch(open, async (isOpen) => {
    if (!isOpen) return
    await nextTick()
    if (!open.value) return
    updatePosition()
    requestAnimationFrame(() => {
      if (open.value) updatePosition()
    })
    window.addEventListener('resize', onLayoutChange)
    window.addEventListener('scroll', onLayoutChange, true)
    document.addEventListener('keydown', onKeydown)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('resize', onLayoutChange)
    window.removeEventListener('scroll', onLayoutChange, true)
    document.removeEventListener('keydown', onKeydown)
  })

  watch(open, (isOpen) => {
    if (isOpen) return
    window.removeEventListener('resize', onLayoutChange)
    window.removeEventListener('scroll', onLayoutChange, true)
    document.removeEventListener('keydown', onKeydown)
  })

  return {
    triggerRef,
    panelRef,
    panelStyle,
    containsTarget,
    updatePosition,
  }
}
