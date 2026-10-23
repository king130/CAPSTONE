<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { FileUp, LoaderCircle, RefreshCw, Trash2, Upload } from 'lucide-vue-next'

import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import { useToast } from '@/composables/useToast'
import {
  checklistStatusLabel,
  deleteOrganizationVerificationDocument,
  downloadOrganizationVerificationDocument,
  listOrganizationVerificationDocuments,
  normalizeVerificationChecklist,
  resubmitOrganizationVerificationDocument,
  submitOrganizationVerificationForReview,
  uploadOrganizationVerificationDocument,
  verificationStatusLabel,
  type ChecklistStatus,
  type OrganizationVerificationMeta,
  type VerificationChecklistItem,
} from '@/services/organizationVerification'

const props = withDefaults(
  defineProps<{
    /** When true, show optional notice for already-approved orgs. */
    showApprovedNotice?: boolean
    readOnly?: boolean
  }>(),
  {
    showApprovedNotice: true,
    readOnly: false,
  },
)

const emit = defineEmits<{
  refreshed: [meta: OrganizationVerificationMeta]
}>()

const { success, error } = useToast()

const loading = ref(false)
const loadError = ref<string | null>(null)
const uploadingType = ref<string | null>(null)
const submitting = ref(false)
const dragOverType = ref<string | null>(null)
const progress = ref(0)
const previewUrl = ref<string | null>(null)
const previewName = ref('')
const meta = ref<OrganizationVerificationMeta | null>(null)
const checklist = ref<VerificationChecklistItem[]>([])

const canMutate = computed(
  () => !props.readOnly && (meta.value?.canMutateDocuments !== false) && Boolean(meta.value),
)
const limitsLabel = computed(() => {
  const lim = meta.value?.limits
  if (!lim) return 'PDF, JPG, or PNG · Max 10 MB'
  const exts = (lim.acceptedExtensions || []).map((e) => e.toUpperCase()).join(', ')
  const mb = Math.round((lim.maxKilobytes || 10240) / 1024)
  return `${exts} · Max ${mb} MB`
})
const requiredComplete = computed(() => {
  if (typeof meta.value?.requiredComplete === 'boolean') return meta.value.requiredComplete
  const required = checklist.value.filter((item) => item.required)
  return required.length > 0 && required.every((item) => Boolean(item.document))
})
const orgStatus = computed(() => String(meta.value?.verificationStatus || 'pending').toLowerCase())
const isApproved = computed(() => orgStatus.value === 'approved')
const hasEmptyChecklist = computed(
  () => !loading.value && !loadError.value && Boolean(meta.value) && checklist.value.length === 0,
)

function statusBadgeVariant(status: ChecklistStatus) {
  if (status === 'approved') return 'success' as const
  if (status === 'needs_fix') return 'destructive' as const
  if (status === 'under_review') return 'warning' as const
  if (status === 'uploaded') return 'secondary' as const
  return 'outline' as const
}

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const res = await listOrganizationVerificationDocuments()
    meta.value = res.meta
    checklist.value = normalizeVerificationChecklist(res.meta, res.data || [])
    if (!checklist.value.length) {
      loadError.value =
        'We could not load the document checklist for your organization. Tap Retry, or contact support if this keeps happening.'
    }
    emit('refreshed', res.meta)
  } catch (caught) {
    checklist.value = []
    meta.value = null
    loadError.value = 'Unable to load verification documents. Check your connection and try again.'
    error(caught, { fallback: loadError.value })
  } finally {
    loading.value = false
  }
}

function clearPreview() {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = null
  previewName.value = ''
}

function setPreview(file: File) {
  clearPreview()
  previewName.value = file.name
  if (file.type.startsWith('image/')) {
    previewUrl.value = URL.createObjectURL(file)
  }
}

async function uploadForType(type: string, file: File, existingId?: string | null) {
  if (!canMutate.value) {
    error('Only the organization owner can upload verification documents.')
    return
  }

  uploadingType.value = type
  progress.value = 15
  const tick = window.setInterval(() => {
    progress.value = Math.min(90, progress.value + 12)
  }, 180)

  try {
    setPreview(file)
    if (existingId) {
      await resubmitOrganizationVerificationDocument(existingId, file, type)
      success('Document replaced.')
    } else {
      await uploadOrganizationVerificationDocument(type, file)
      success('Document uploaded.')
    }
    progress.value = 100
    await load()
  } catch (caught) {
    error(caught, { fallback: 'Unable to upload the document.' })
  } finally {
    window.clearInterval(tick)
    uploadingType.value = null
    progress.value = 0
  }
}

function onFilePicked(type: string, event: Event, existingId?: string | null) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  void uploadForType(type, file, existingId)
}

function onDrop(type: string, event: DragEvent, existingId?: string | null) {
  event.preventDefault()
  dragOverType.value = null
  const file = event.dataTransfer?.files?.[0]
  if (!file) return
  void uploadForType(type, file, existingId)
}

async function removeDoc(item: VerificationChecklistItem) {
  if (!item.document || !canMutate.value) return
  if (item.document.status === 'approved') {
    error('Approved documents cannot be removed.')
    return
  }
  try {
    await deleteOrganizationVerificationDocument(item.document.id)
    success('Document removed.')
    clearPreview()
    await load()
  } catch (caught) {
    error(caught, { fallback: 'Unable to remove the document.' })
  }
}

async function downloadDoc(item: VerificationChecklistItem) {
  if (!item.document) return
  try {
    await downloadOrganizationVerificationDocument(item.document.id, item.document.fileName)
  } catch (caught) {
    error(caught, { fallback: 'Unable to download the document.' })
  }
}

async function submitForReview() {
  if (!requiredComplete.value) {
    error('Upload all required documents before submitting for review.')
    return
  }
  submitting.value = true
  try {
    await submitOrganizationVerificationForReview()
    success('Submitted for admin review.')
    await load()
  } catch (caught) {
    error(caught, { fallback: 'Unable to submit for review.' })
  } finally {
    submitting.value = false
  }
}

watch(
  () => props.readOnly,
  () => {
    void load()
  },
)

onMounted(() => {
  void load()
})

onBeforeUnmount(() => {
  clearPreview()
})

defineExpose({ reload: load, meta })
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="font-semibold text-foreground">Verification documents</p>
        <p class="mt-1 text-sm text-muted-foreground">
          Upload one file for each item below. Accepted types: {{ limitsLabel }}.
          Drag and drop, or tap Upload (camera or file picker on mobile).
        </p>
      </div>
      <Badge v-if="meta" variant="outline">{{ verificationStatusLabel(orgStatus) }}</Badge>
    </div>

    <div
      v-if="showApprovedNotice && meta?.documentsMissingNotice"
      class="rounded-xl border border-border bg-muted/50 px-4 py-3 text-sm text-foreground"
    >
      {{ meta.documentsMissingNotice }}
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Loading checklist…</p>

    <div
      v-else-if="loadError || hasEmptyChecklist"
      class="space-y-3 rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-4"
    >
      <p class="text-sm text-foreground">
        {{ loadError || 'We could not load the document checklist for your organization.' }}
      </p>
      <Button type="button" variant="outline" size="sm" @click="load">
        <RefreshCw class="mr-1.5 h-3.5 w-3.5" />
        Retry
      </Button>
    </div>

    <ul v-else class="space-y-3">
      <li
        v-for="item in checklist"
        :key="item.type"
        class="rounded-xl border border-border bg-background px-4 py-3"
        :class="dragOverType === item.type ? 'border-primary bg-primary/5' : ''"
        @dragover.prevent="canMutate && (dragOverType = item.type)"
        @dragleave="dragOverType = null"
        @drop="onDrop(item.type, $event, item.document?.id)"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0 flex-1 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
              <p class="text-sm font-medium text-foreground">{{ item.label }}</p>
              <Badge :variant="statusBadgeVariant(item.checklistStatus)">
                {{
                  item.document
                    ? checklistStatusLabel(item.checklistStatus) === 'Uploaded'
                      ? 'Submitted'
                      : checklistStatusLabel(item.checklistStatus)
                    : checklistStatusLabel(item.checklistStatus)
                }}
              </Badge>
              <span v-if="!item.required" class="text-xs text-muted-foreground">Optional</span>
              <span v-else class="text-xs text-muted-foreground">Required</span>
            </div>
            <p v-if="item.why" class="text-xs text-muted-foreground">{{ item.why }}</p>
            <p v-if="item.example" class="text-xs text-muted-foreground">{{ item.example }}</p>
            <p class="text-xs text-muted-foreground">Accepted: PDF, JPG, or PNG</p>
            <p v-if="item.document" class="truncate text-xs font-medium text-foreground">
              Submitted file: {{ item.document.fileName }}
              <template v-if="item.document.rejectionReason">
                · {{ item.document.rejectionReason }}
              </template>
            </p>
            <p v-else class="text-xs text-muted-foreground">No file uploaded yet</p>
          </div>

          <div class="flex flex-wrap gap-2">
            <Button
              v-if="item.document"
              size="sm"
              variant="outline"
              type="button"
              @click="downloadDoc(item)"
            >
              Preview / download
            </Button>
            <label
              v-if="canMutate && item.document?.status !== 'approved'"
              class="inline-flex cursor-pointer"
            >
              <span
                class="inline-flex h-8 items-center justify-center gap-1 rounded-md border border-input bg-background px-3 text-xs font-medium text-foreground hover:bg-accent"
              >
                <Upload class="h-3.5 w-3.5" />
                {{ item.document ? 'Replace' : 'Upload' }}
              </span>
              <input
                class="sr-only"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf"
                capture="environment"
                :disabled="uploadingType === item.type"
                @change="onFilePicked(item.type, $event, item.document?.id)"
              />
            </label>
            <Button
              v-if="canMutate && item.document && item.document.status !== 'approved'"
              size="sm"
              variant="ghost"
              type="button"
              @click="removeDoc(item)"
            >
              <Trash2 class="h-3.5 w-3.5" />
            </Button>
          </div>
        </div>

        <div
          v-if="canMutate && !item.document"
          class="mt-3 flex items-center justify-center gap-2 rounded-lg border border-dashed border-border px-3 py-6 text-sm text-muted-foreground"
        >
          <FileUp class="h-4 w-4" />
          Drop a file here or use Upload
        </div>

        <div v-if="uploadingType === item.type" class="mt-3 space-y-1">
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <LoaderCircle class="h-3.5 w-3.5 animate-spin" />
            Uploading… {{ progress }}%
          </div>
          <div class="h-1.5 overflow-hidden rounded-full bg-muted">
            <div class="h-full bg-primary transition-all" :style="{ width: `${progress}%` }" />
          </div>
        </div>
      </li>
    </ul>

    <div v-if="previewUrl || previewName" class="rounded-xl border border-border bg-muted/40 p-3">
      <p class="text-xs font-medium text-foreground">Last selected preview</p>
      <p class="text-xs text-muted-foreground">{{ previewName }}</p>
      <img v-if="previewUrl" :src="previewUrl" alt="Upload preview" class="mt-2 max-h-40 rounded-md object-contain" />
    </div>

    <div v-if="canMutate && !isApproved" class="flex flex-wrap gap-2">
      <Button :disabled="!requiredComplete || submitting" type="button" @click="submitForReview">
        {{ submitting ? 'Submitting…' : 'Submit for review' }}
      </Button>
      <p v-if="!requiredComplete" class="self-center text-xs text-muted-foreground">
        Upload every required document to enable submit.
      </p>
    </div>

    <p v-if="meta && !canMutate && !readOnly" class="text-xs text-muted-foreground">
      Only the organization owner can upload, replace, or remove verification documents.
    </p>
  </div>
</template>
