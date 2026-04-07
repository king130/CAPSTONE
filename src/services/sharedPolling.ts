type Subscriber<T> = (items: T) => void

export interface SharedPollingResource<T> {
  subscribe: (subscriber: Subscriber<T>) => () => void
  refresh: () => Promise<void>
  clear: () => void
  getSnapshot: () => T
}

export function createSharedPollingResource<T>({
  intervalMs,
  load,
  initialValue,
}: {
  intervalMs: number
  load: () => Promise<T>
  initialValue: T
}): SharedPollingResource<T> {
  let currentValue = initialValue
  let timerId: number | null = null
  let inflight: Promise<void> | null = null
  const subscribers = new Set<Subscriber<T>>()

  function emit() {
    for (const subscriber of subscribers) {
      subscriber(currentValue)
    }
  }

  async function refresh() {
    if (inflight) {
      return inflight
    }

    inflight = (async () => {
      try {
        currentValue = await load()
      } catch {
        currentValue = initialValue
      } finally {
        emit()
        inflight = null
      }
    })()

    return inflight
  }

  function start() {
    if (timerId !== null) {
      return
    }

    void refresh()
    timerId = window.setInterval(() => {
      void refresh()
    }, intervalMs)
  }

  function stop() {
    if (timerId !== null) {
      window.clearInterval(timerId)
      timerId = null
    }
  }

  function subscribe(subscriber: Subscriber<T>) {
    subscribers.add(subscriber)
    subscriber(currentValue)
    start()

    return () => {
      subscribers.delete(subscriber)
      if (subscribers.size === 0) {
        stop()
      }
    }
  }

  function clear() {
    currentValue = initialValue
    stop()
  }

  return {
    subscribe,
    refresh,
    clear,
    getSnapshot: () => currentValue,
  }
}
