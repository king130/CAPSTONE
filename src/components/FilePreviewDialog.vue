<script setup lang="ts">
import { ExternalLink, LoaderCircle, Minus, Plus, RotateCcw, X } from 'lucide-vue-next'
import { computed, onUnmounted, ref, watch } from 'vue'

import Button from '@/components/ui/button/Button.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { ApiError } from '@/services/http'
import { downloadBlob, downloadFromSource, resolvePreviewSource } from '@/services/fileBlob'
import { formatFileSize, resolvePreviewKind, type PreviewKind } from '@/utils/filePreview'

const props = withDefaults(
  defineProps<{
    open: boolean
    fileName: string
    mimeType?: string | null
    fileSize?: number | null
    /** Public URL or authenticated API path (`/api/...` or `/ojt-dtrs/1/file`). */
    source?: string | null
    /** Custom loader for id-based downloads (verification docs, etc.). */
    loader?: (() => Promise<{ blob: Blob; contentType?: string }>) | null
  }>(),
  {
    open: false,
    mimeType: null,
    fileSize: null,
    source: null,
    loader: null,
  },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const loading = ref(false)
const errorMessage = ref('')
const previewUrl = ref<string | null>(null)
const resolvedMime = ref('')
const previewKind = ref<PreviewKind>('unsupported')
const zoom = ref(1)
const isMdUp = ref(typeof window !== 'undefined' ? window.matchMedia('(min-width: 768px)').matches : true)

let revokeCurrent: (() => void) | null = null
let loadGeneration = 0

const metaLine = computed(() => {
  const parts: string[] = []
  const mime = resolvedMime.value || props.mimeType || ''
  if (mime) parts.push(mime)
  const size = formatFileSize(props.fileSize)
  if (size) parts.push(size)
  return parts.join(' · ')
})

const showInlineFrame = computed(
  () => isMdUp.value && previewKind.value === 'pdf' && Boolean(previewUrl.value) && !errorMessage.value,
)

function syncViewport() {
  isMdUp.value = window.matchMedia('(min-width: 768px)').matches
}

function closeDialog() {
  emit('update:open', false)
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && props.open) {
    event.preventDefault()
    closeDialog()
  }
}

function clearPreview() {
  revokeCurrent?.()
  revokeCurrent = null
  previewUrl.value = null
  resolvedMime.value = ''
  previewKind.value = 'unsupported'
  zoom.value = 1
  errorMessage.value = ''
}

async function loadPreview() {
  const generation = ++loadGeneration
  clearPreview()
  if (!props.open) return

  loading.value = true
  try {
    if (props.loader) {
      const result = await props.loader()
      if (generation !== loadGeneration) return
      const objectUrl = URL.createObjectURL(result.blob)
      revokeCurrent = () => URL.revokeObjectURL(objectUrl)
      previewUrl.value = objectUrl
      resolvedMime.value = result.contentType || result.blob.type || props.mimeType || ''
    } else if (props.source) {
      const resolved = await resolvePreviewSource(props.source)
      if (generation !== loadGeneration) {
        resolved.revoke()
        return
      }
      revokeCurrent = resolved.revoke
      previewUrl.value = resolved.objectUrl || resolved.directUrl
      resolvedMime.value = resolved.contentType || props.mimeType || ''
    } else {
      throw new ApiError('No file source provided.', 400)
    }

    previewKind.value = resolvePreviewKind({
      contentType: resolvedMime.value || props.mimeType,
      fileName: props.fileName,
    })
  } catch (caught) {
    if (generation !== loadGeneration) return
    if (caught instanceof ApiError && (caught.status === 403 || caught.status === 404)) {
      errorMessage.value =
        caught.status === 403
          ? 'You do not have access to this file.'
          : 'This file could not be found.'
    } else {
      errorMessage.value = 'Could not load this file. You can still download it.'
    }
  } finally {
    if (generation === loadGeneration) loading.value = false
  }
}

async function onDownload() {
  try {
    if (props.loader) {
      const result = await props.loader()
      await downloadBlob(result.blob, props.fileName || 'download')
      return
    }
    if (props.source) {
      await downloadFromSource(props.source, props.fileName || 'download')
    }
  } catch {
    errorMessage.value = 'Could not download this file.'
  }
}

function openInNewTab() {
  if (!previewUrl.value) return
  window.open(previewUrl.value, '_blank', 'noopener,noreferrer')
}

function zoomIn() {
  zoom.value = Math.min(3, Number((zoom.value + 0.25).toFixed(2)))
}

function zoomOut() {
  zoom.value = Math.max(0.5, Number((zoom.value - 0.25).toFixed(2)))
}

function resetZoom() {
  zoom.value = 1
}

watch(
  () => [props.open, props.source, props.fileName, props.loader] as const,
  ([open]) => {
    if (open) void loadPreview()
    else {
      loadGeneration += 1
      clearPreview()
    }
  },
)

watch(
  () => props.open,
  (open) => {
    if (typeof window === 'undefined') return
    if (open) {
      window.addEventListener('keydown', onKeydown)
      window.addEventListener('resize', syncViewport)
      syncViewport()
    } else {
      window.removeEventListener('keydown', onKeydown)
      window.removeEventListener('resize', syncViewport)
    }
  },
)

onUnmounted(() => {
  loadGeneration += 1
  clearPreview()
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', onKeydown)
    window.removeEventListener('resize', syncViewport)
  }
})
</script>

<template>
  <Teleport to="body">
    <transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="open" class="fixed inset-0 z-modal bg-slate-950/50 backdrop-blur-sm" @click="closeDialog" />
    </transition>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-4 opacity-0 sm:scale-95"
      enter-to-class="translate-y-0 opacity-100 sm:scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100 sm:scale-100"
      leave-to-class="translate-y-4 opacity-0 sm:scale-95"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-modal overflow-y-auto p-3 sm:p-4"
        role="dialog"
        aria-modal="true"
        :aria-label="fileName || 'File preview'"
        @click.self="closeDialog"
      >
        <div class="mx-auto my-2 flex min-h-full w-full max-w-5xl items-center justify-center sm:my-6">
          <div class="flex w-full max-h-[92vh] flex-col overflow-hidden rounded-2xl border border-border bg-background shadow-2xl">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border/60 px-4 py-3 sm:px-5">
              <div class="min-w-0 space-y-1">
                <p class="truncate text-base font-semibold text-foreground">{{ fileName || 'Untitled file' }}</p>
                <p v-if="metaLine" class="truncate text-xs text-muted-foreground">{{ metaLine }}</p>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <Button
                  v-if="!isMdUp && previewUrl && !errorMessage"
                  type="button"
                  size="sm"
                  @click="openInNewTab"
                >
                  <ExternalLink class="h-4 w-4" />
                  Open in new tab
                </Button>
                <Button type="button" size="sm" variant="outline" :disabled="loading" @click="onDownload">
                  Download
                </Button>
                <Button
                  v-if="isMdUp && previewUrl && !errorMessage"
                  type="button"
                  size="sm"
                  variant="outline"
                  @click="openInNewTab"
                >
                  <ExternalLink class="h-4 w-4" />
                  Open in new tab
                </Button>
                <Button type="button" size="icon" variant="ghost" class="text-muted-foreground" @click="closeDialog">
                  <X class="h-4 w-4" />
                </Button>
              </div>
            </div>

            <div class="min-h-0 flex-1 overflow-auto bg-muted/30 p-3 sm:p-4">
              <div v-if="loading" class="space-y-3">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                  <LoaderCircle class="h-4 w-4 animate-spin" />
                  Loading preview…
                </div>
                <Skeleton class="h-[50vh] w-full rounded-xl" />
              </div>

              <div
                v-else-if="errorMessage"
                class="flex min-h-[40vh] flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border bg-card px-6 py-10 text-center"
              >
                <p class="text-sm text-muted-foreground">{{ errorMessage }}</p>
                <Button type="button" size="sm" @click="onDownload">Download</Button>
              </div>

              <div
                v-else-if="previewKind === 'unsupported'"
                class="flex min-h-[40vh] flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border bg-card px-6 py-10 text-center"
              >
                <p class="text-sm font-medium text-foreground">Preview not available for this file type</p>
                <p class="text-sm text-muted-foreground">You can download the file to open it on your device.</p>
                <Button type="button" size="sm" @click="onDownload">Download</Button>
              </div>

              <div v-else-if="previewKind === 'image' && previewUrl" class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                  <Button type="button" size="sm" variant="outline" @click="zoomOut">
                    <Minus class="h-4 w-4" />
                  </Button>
                  <Button type="button" size="sm" variant="outline" @click="zoomIn">
                    <Plus class="h-4 w-4" />
                  </Button>
                  <Button type="button" size="sm" variant="outline" @click="resetZoom">
                    <RotateCcw class="h-4 w-4" />
                    Reset
                  </Button>
                  <span class="text-xs text-muted-foreground">{{ Math.round(zoom * 100) }}%</span>
                </div>
                <div class="flex min-h-[50vh] items-center justify-center overflow-auto rounded-xl border border-border bg-card p-4">
                  <img
                    :src="previewUrl"
                    :alt="fileName || 'Preview'"
                    class="max-h-[70vh] max-w-full object-contain transition-transform"
                    :style="{ transform: `scale(${zoom})` }"
                  />
                </div>
              </div>

              <div v-else-if="previewKind === 'pdf' && previewUrl" class="space-y-3">
                <div
                  v-if="!isMdUp"
                  class="flex min-h-[40vh] flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border bg-card px-6 py-10 text-center"
                >
                  <p class="text-sm text-muted-foreground">
                    On smaller screens, open the PDF in a new tab for the best reading experience.
                  </p>
                  <Button type="button" @click="openInNewTab">
                    <ExternalLink class="h-4 w-4" />
                    Open in new tab
                  </Button>
                </div>
                <iframe
                  v-if="showInlineFrame"
                  :src="previewUrl"
                  :title="fileName || 'PDF preview'"
                  class="h-[70vh] w-full rounded-xl border border-border bg-card"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
