<script setup lang="ts">
import { FileText, LoaderCircle } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

import FilePreviewActions from '@/components/FilePreviewActions.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue'
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Table from '@/components/ui/table/Table.vue'
import TableBody from '@/components/ui/table/TableBody.vue'
import TableCell from '@/components/ui/table/TableCell.vue'
import TableHead from '@/components/ui/table/TableHead.vue'
import TableHeader from '@/components/ui/table/TableHeader.vue'
import TableRow from '@/components/ui/table/TableRow.vue'
import Textarea from '@/components/ui/textarea/Textarea.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import AlertDialog from '@/components/ui/alert-dialog/AlertDialog.vue'
import {
  approveDtr,
  deleteDtr,
  dtrStatusLabel,
  dtrRowStatus,
  dtrStatusDetail,
  isPendingDtr,
  isAwaitingHost,
  isAwaitingSchool,
  isRejectedOrRevision,
  listDtrPlacements,
  listDtrSubmissions,
  rejectDtr,
  resubmitDtr,
  submitDtr,
  type DtrDisplayStatus,
  type DtrPlacement,
  type DtrSubmission,
} from '@/services/dtrService'
import { hasAnyPermission, hasPermission } from '@/services/permissions'
import { useAuthStore } from '@/stores/auth'
import { extractDtrSuggestions } from '@/utils/dtrExtract'
import { formatDate, toTitleCaseName } from '@/utils/formatDisplay'

const authStore = useAuthStore()
const route = useRoute()
const { success, error } = useToast()

const isCompany = computed(() => route.name === 'company-dtr')
const isAdmin = computed(() => route.name === 'admin-dtr')
const isMunicipalHall = computed(
  () => String(authStore.user?.activeOrganization?.type || '').toLowerCase() === 'municipal_hall',
)
const hostLabel = computed(() => (isMunicipalHall.value ? 'Municipal Hall' : 'Company'))
const role = computed(() => (isAdmin.value ? 'admin' : isCompany.value ? 'company' : 'school'))
const canCoordinatorReview = computed(() => !isCompany.value && hasPermission('org.approve_ojt_logs'))
const canCompanyReview = computed(
  () =>
    isCompany.value &&
    hasAnyPermission([
      'org.approve_ojt_logs',
      'org.manage_internships',
      'manage_internships',
      'org.manage_applications',
      'review_applications',
    ]),
)

const loading = ref(true)
const submitting = ref(false)
const placements = ref<DtrPlacement[]>([])
const submissions = ref<DtrSubmission[]>([])

const applicationId = ref('')
const periodStart = ref('')
const periodEnd = ref('')
const submittedHours = ref('')
const remarks = ref('')
const file = ref<File | null>(null)
const readingFile = ref(false)
const fileMessage = ref('')
const resubmitTarget = ref<DtrSubmission | null>(null)

const rejectTarget = ref<DtrSubmission | null>(null)
const deleteTarget = ref<DtrSubmission | null>(null)
const rejectReason = ref('')
const rejectAsRevision = ref(false)
const rejectOpen = computed({
  get: () => rejectTarget.value !== null,
  set: (open: boolean) => {
    if (!open) {
      rejectTarget.value = null
      rejectReason.value = ''
      rejectAsRevision.value = false
    }
  },
})

async function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  const next = input.files?.[0] ?? null
  file.value = next
  fileMessage.value = ''
  if (!next) return

  periodStart.value = ''
  periodEnd.value = ''
  submittedHours.value = ''
  readingFile.value = true
  try {
    const suggestion = await extractDtrSuggestions(next)
    const found = suggestion.hours != null || suggestion.periodStart || suggestion.periodEnd
    if (!found) {
      fileMessage.value = 'This file could not be read. Enter the hours and dates manually.'
      return
    }
    if (suggestion.hours != null) submittedHours.value = String(suggestion.hours)
    if (suggestion.periodStart) periodStart.value = suggestion.periodStart
    if (suggestion.periodEnd) periodEnd.value = suggestion.periodEnd
    fileMessage.value = 'Suggested from the file. Review and correct the hours and dates before submitting.'
  } catch {
    periodStart.value = ''
    periodEnd.value = ''
    submittedHours.value = ''
    fileMessage.value = 'This file could not be read. Enter the hours and dates manually.'
  } finally {
    readingFile.value = false
  }
}

async function load() {
  loading.value = true
  try {
    const [rows, accepted] = await Promise.all([
      listDtrSubmissions(),
      isCompany.value ? listDtrPlacements() : Promise.resolve([]),
    ])
    submissions.value = rows
    placements.value = accepted
  } catch (caught) {
    error(caught, { fallback: 'Unable to load DTR submissions.' })
  } finally {
    loading.value = false
  }
}

function resetForm() {
  applicationId.value = ''
  periodStart.value = ''
  periodEnd.value = ''
  submittedHours.value = ''
  remarks.value = ''
  file.value = null
  fileMessage.value = ''
  resubmitTarget.value = null
}

async function onSubmit() {
  if (!file.value || !applicationId.value || !periodStart.value || !periodEnd.value || !submittedHours.value) {
    error('Choose an intern, period, hours, and DTR file.')
    return
  }

  submitting.value = true
  try {
    const payload = {
      applicationId: Number(applicationId.value),
      periodStart: periodStart.value,
      periodEnd: periodEnd.value,
      submittedHours: Number(submittedHours.value),
      remarks: remarks.value,
      file: file.value,
    }
    if (resubmitTarget.value) {
      await resubmitDtr(resubmitTarget.value.id, payload)
      success('DTR resubmitted. Status is Approved.')
    } else {
      await submitDtr(payload)
      success('DTR entered. Status is Approved.')
    }
    resetForm()
    await load()
  } catch (caught) {
    error(caught, { fallback: 'Unable to submit the DTR.' })
  } finally {
    submitting.value = false
  }
}

function startResubmit(row: DtrSubmission) {
  resubmitTarget.value = row
  applicationId.value = String(row.applicationId)
  periodStart.value = row.periodStart
  periodEnd.value = row.periodEnd
  submittedHours.value = String(row.submittedHours)
  remarks.value = row.remarks ?? ''
  file.value = null
  fileMessage.value = ''
}

function askDelete(row: DtrSubmission) {
  deleteTarget.value = row
}

async function confirmDelete() {
  const row = deleteTarget.value
  if (!row) return
  try {
    await deleteDtr(row.id)
    submissions.value = submissions.value.filter((item) => item.id !== row.id)
    success('DTR deleted.')
  } catch (caught) {
    error(caught, { fallback: 'Unable to delete this DTR.' })
  } finally {
    deleteTarget.value = null
  }
}

async function onApprove(row: DtrSubmission) {
  try {
    const updated = await approveDtr(row.id)
    success('DTR approved.')
    // Prefer server response so status is correct immediately.
    const idx = submissions.value.findIndex((item) => item.id === row.id)
    if (idx >= 0 && updated) submissions.value[idx] = updated
    await load()
  } catch (caught) {
    error(caught, { fallback: 'Unable to approve this DTR.' })
  }
}

async function onReject() {
  if (!rejectTarget.value) return
  if (!rejectReason.value.trim()) {
    error('A rejection reason is required.')
    return
  }
  try {
    await rejectDtr(rejectTarget.value.id, rejectReason.value.trim(), rejectAsRevision.value)
    success('DTR rejected.')
    rejectOpen.value = false
    await load()
  } catch (caught) {
    error(caught, { fallback: 'Unable to reject this DTR.' })
  }
}

function badgeVariant(status: DtrDisplayStatus) {
  if (status === 'approved') return 'success' as const
  if (status === 'rejected' || status === 'revision_required') return 'destructive' as const
  return 'warning' as const
}

function canApproveRow(row: DtrSubmission): boolean {
  if (isAdmin.value && (isAwaitingHost(row) || isAwaitingSchool(row))) return true
  if (canCompanyReview.value && isAwaitingHost(row)) return true
  if (canCoordinatorReview.value && isAwaitingSchool(row)) return true
  return false
}

function canRejectRow(row: DtrSubmission): boolean {
  return canApproveRow(row)
}

onMounted(() => {
  void load()
  void authStore
})
</script>

<template>
  <MainLayout
    :role="role"
    :title="isAdmin ? 'DTR Records' : isCompany ? 'DTR Submissions' : 'DTR Review'"
    :active-item="isAdmin ? 'admin-dtr' : isCompany ? 'dtr-submissions' : 'dtr-review'"
  >
    <div class="space-y-6">
      <Card>
        <CardHeader class="flex flex-row items-center gap-2">
          <FileText class="h-5 w-5 text-primary" />
          <h2 class="text-lg font-semibold text-foreground">
            {{ isCompany ? `${hostLabel} DTR queue` : isAdmin ? 'All DTR records' : 'Coordinator review' }}
          </h2>
        </CardHeader>
        <CardContent>
          <div v-if="loading" class="flex items-center gap-2 text-sm text-muted-foreground">
            <LoaderCircle class="h-4 w-4 animate-spin" />
            Loading submissions
          </div>
          <p v-else-if="submissions.length === 0" class="text-sm text-muted-foreground">
            No DTR submissions yet.
          </p>
          <Table v-else>
            <TableHeader>
              <TableRow>
                <TableHead>Intern</TableHead>
                <TableHead>Host</TableHead>
                <TableHead>Period</TableHead>
                <TableHead>Hours</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>File</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="row in submissions" :key="row.id">
                <TableCell>
                  <div class="font-medium">{{ toTitleCaseName(row.internName) || row.internName }}</div>
                  <div class="text-xs text-muted-foreground">{{ row.internshipTitle }}</div>
                </TableCell>
                <TableCell>{{ row.company }}</TableCell>
                <TableCell>{{ formatDate(row.periodStart) }} – {{ formatDate(row.periodEnd) }}</TableCell>
                <TableCell>{{ row.submittedHours }}</TableCell>
                <TableCell>
                  <Badge :variant="badgeVariant(dtrRowStatus(row) as DtrDisplayStatus)">{{ dtrStatusLabel(dtrRowStatus(row)) }}</Badge>
                  <p v-if="dtrStatusDetail(row)" class="mt-1 text-xs text-muted-foreground">{{ dtrStatusDetail(row) }}</p>
                  <p v-if="row.companyReviewedBy" class="mt-1 text-xs text-muted-foreground">
                    Company: {{ toTitleCaseName(row.companyReviewedBy) || row.companyReviewedBy }}
                  </p>
                  <p v-if="row.reviewedBy" class="mt-1 text-xs text-muted-foreground">
                    Coordinator: {{ toTitleCaseName(row.reviewedBy) || row.reviewedBy }}
                  </p>
                  <p v-if="row.rejectionReason" class="mt-1 max-w-xs text-xs text-muted-foreground">
                    {{ row.rejectionReason }}
                  </p>
                </TableCell>
                <TableCell>
                  <div class="space-y-1">
                    <p class="max-w-[14rem] truncate text-xs text-muted-foreground">{{ row.fileName }}</p>
                    <FilePreviewActions
                      :file-name="row.fileName || `dtr-${row.id}.pdf`"
                      :api-path="`/ojt-dtrs/${row.id}/file`"
                    />
                  </div>
                </TableCell>
                <TableCell class="space-x-2">
                  <template v-if="canApproveRow(row)">
                    <Button size="sm" @click="onApprove(row)">Approve</Button>
                    <Button size="sm" variant="outline" @click="rejectTarget = row">Reject</Button>
                  </template>
                  <Button
                    v-if="isCompany && isRejectedOrRevision(row.status)"
                    size="sm"
                    variant="outline"
                    @click="startResubmit(row)"
                  >
                    Correct and resubmit
                  </Button>
                  <Button
                    v-if="isPendingDtr(row.status)"
                    size="sm"
                    variant="outline"
                    @click="askDelete(row)"
                  >
                    Delete
                  </Button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card v-if="isCompany">
        <details class="group" :open="Boolean(resubmitTarget)">
          <summary class="cursor-pointer list-none px-6 pt-6">
            <h2 class="text-lg font-semibold text-foreground">
              {{ resubmitTarget ? 'Resubmit DTR' : 'Add a DTR for an intern' }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
              DTRs you add yourself are approved automatically.
            </p>
          </summary>
          <div class="grid gap-4 px-6 pb-6 pt-4 md:grid-cols-2">
            <label class="space-y-2 text-sm">
              <span class="font-medium text-foreground">Accepted intern</span>
              <Select v-model="applicationId" :disabled="Boolean(resubmitTarget)">
                <option value="">Select an intern</option>
                <option v-for="placement in placements" :key="placement.applicationId" :value="String(placement.applicationId)">
                  {{ toTitleCaseName(placement.internName) || placement.internName }} — {{ placement.internshipTitle }}
                </option>
              </Select>
            </label>
            <label class="space-y-2 text-sm">
              <span class="font-medium text-foreground">Hours on this DTR</span>
              <Input v-model="submittedHours" type="number" min="0.25" max="500" step="0.25" />
            </label>
            <label class="space-y-2 text-sm">
              <span class="font-medium text-foreground">Period start</span>
              <Input v-model="periodStart" type="date" />
            </label>
            <label class="space-y-2 text-sm">
              <span class="font-medium text-foreground">Period end</span>
              <Input v-model="periodEnd" type="date" />
            </label>
            <label class="space-y-2 text-sm md:col-span-2">
              <span class="font-medium text-foreground">DTR file</span>
              <input
                class="flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                :disabled="readingFile"
                @change="onFileChange"
              />
              <p v-if="readingFile" class="text-sm text-muted-foreground">Reading the file…</p>
              <p v-else-if="fileMessage" class="text-sm text-muted-foreground">{{ fileMessage }}</p>
            </label>
            <label class="space-y-2 text-sm md:col-span-2">
              <span class="font-medium text-foreground">Remarks</span>
              <Textarea v-model="remarks" :rows="3" placeholder="Optional note for the coordinator" />
            </label>
            <div class="flex gap-2 md:col-span-2">
              <Button :disabled="submitting || readingFile" @click="onSubmit">
                <LoaderCircle v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                {{ resubmitTarget ? 'Resubmit DTR' : 'Submit DTR' }}
              </Button>
              <Button v-if="resubmitTarget" variant="outline" @click="resetForm">Cancel</Button>
            </div>
          </div>
        </details>
      </Card>
    </div>

    <Dialog v-model:open="rejectOpen">
      <DialogHeader>
        <DialogTitle>Reject DTR</DialogTitle>
      </DialogHeader>
      <div class="space-y-3 p-6 pt-0">
        <Textarea v-model="rejectReason" :rows="4" placeholder="Explain what needs to be corrected" />
        <div class="flex justify-end gap-2">
          <Button variant="outline" @click="rejectOpen = false">Cancel</Button>
          <Button @click="onReject">Reject submission</Button>
        </div>
      </div>
    </Dialog>

    <AlertDialog
      :open="deleteTarget !== null"
      title="Delete this DTR?"
      :description="deleteTarget ? `${toTitleCaseName(deleteTarget.internName) || deleteTarget.internName}, ${formatDate(deleteTarget.periodStart)} to ${formatDate(deleteTarget.periodEnd)}, ${deleteTarget.submittedHours} hours. This cannot be undone.` : ''"
      action-label="Delete"
      action-variant="destructive"
      @update:open="(open) => { if (!open) deleteTarget = null }"
      @action="confirmDelete"
    />
  </MainLayout>
</template>
