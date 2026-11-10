<script setup lang="ts">
import { Download, Eye } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import FilePreviewDialog from '@/components/FilePreviewDialog.vue'
import Button from '@/components/ui/button/Button.vue'
import { downloadFromSource, fetchApiFileBlob } from '@/services/fileBlob'
import { formatFileSize } from '@/utils/filePreview'

const props = withDefaults(
  defineProps<{
    fileName: string
    mimeType?: string | null
    fileSize?: number | null
    /** Public URL or authenticated API path. */
    source?: string | null
    /** Custom loader (e.g. verification doc by id). */
    loader?: (() => Promise<{ blob: Blob; contentType?: string }>) | null
    /** Optional API path used only when `loader` is omitted and `source` is empty. */
    apiPath?: string | null
    size?: 'sm' | 'default'
    previewLabel?: string
    downloadLabel?: string
  }>(),
  {
    mimeType: null,
    fileSize: null,
    source: null,
    loader: null,
    apiPath: null,
    size: 'sm',
    previewLabel: 'Preview',
    downloadLabel: 'Download',
  },
)

const open = ref(false)

const effectiveLoader = computed(() => {
  if (props.loader) return props.loader
  if (props.apiPath) {
    return () => fetchApiFileBlob(props.apiPath!)
  }
  return null
})

const canOpen = computed(() => Boolean(props.source || effectiveLoader.value))

const sizeHint = computed(() => formatFileSize(props.fileSize))

async function onDownload() {
  if (effectiveLoader.value) {
    const result = await effectiveLoader.value()
    const objectUrl = URL.createObjectURL(result.blob)
    const anchor = document.createElement('a')
    anchor.href = objectUrl
    anchor.download = props.fileName || 'download'
    anchor.click()
    URL.revokeObjectURL(objectUrl)
    return
  }
  if (props.source) {
    await downloadFromSource(props.source, props.fileName || 'download')
  }
}
</script>

<template>
  <div class="inline-flex flex-wrap items-center gap-2">
    <Button
      type="button"
      :size="size"
      variant="outline"
      :disabled="!canOpen"
      @click="open = true"
    >
      <Eye class="h-3.5 w-3.5" />
      {{ previewLabel }}
    </Button>
    <Button
      type="button"
      :size="size"
      variant="outline"
      :disabled="!canOpen"
      @click="onDownload"
    >
      <Download class="h-3.5 w-3.5" />
      {{ downloadLabel }}
    </Button>
    <span v-if="sizeHint" class="text-xs text-muted-foreground">{{ sizeHint }}</span>

    <FilePreviewDialog
      v-model:open="open"
      :file-name="fileName"
      :mime-type="mimeType"
      :file-size="fileSize"
      :source="source"
      :loader="effectiveLoader"
    />
  </div>
</template>
