<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import Swal from '@/services/swal'
import {
  AtSign,
  BookOpenText,
  ChevronDown,
  Download,
  IdCard,
  LoaderCircle,
  Copy,
  Mail,
  Sparkles,
  Search,
  Trash2,
  Upload,
  UserRoundPlus,
} from 'lucide-vue-next'

import AlertDialog from '@/components/ui/alert-dialog/AlertDialog.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue'
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue'
import FormControl from '@/components/ui/form/FormControl.vue'
import FormItem from '@/components/ui/form/FormItem.vue'
import FormLabel from '@/components/ui/form/FormLabel.vue'
import Input from '@/components/ui/input/Input.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Table from '@/components/ui/table/Table.vue'
import TableBody from '@/components/ui/table/TableBody.vue'
import TableCell from '@/components/ui/table/TableCell.vue'
import TableHead from '@/components/ui/table/TableHead.vue'
import TableHeader from '@/components/ui/table/TableHeader.vue'
import TableRow from '@/components/ui/table/TableRow.vue'
import Textarea from '@/components/ui/textarea/Textarea.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import { useAuthStore } from '@/stores/auth'
import {
  addSchoolStudent,
  exportSchoolStudentsCsv,
  removeSchoolStudent,
  resendSchoolStudentSetupLink,
  subscribeSchoolStudents,
  updateSchoolStudent,
  type SchoolStudentRow,
} from '@/services/schoolStudents'
import { normalizeStudentNumber, studentNumberErrorMessage } from '@/utils/studentNumber'
import {
  STUDENT_CSV_COLUMNS,
  applyStudentCsvDefaults,
  downloadStudentCsvTemplate,
  formatFileSize,
  isDuplicateStudentCsvRow,
  parseStudentCsv,
  type StudentCsvImportMode,
} from '@/utils/studentCsvImport'
import {
  DEFAULT_ELIGIBLE_YEAR_LEVELS,
  listCourseRequirements,
  type CourseRequirement,
} from '@/services/schoolPrograms'

interface StudentFormState {
  firstName: string
  lastName: string
  emailFormat: EmailFormat
  studentNumber: string
  course: string
  yearLevel: string
  yearLevelChoice: string
}

type EmailFormat =
  | 'first.last'
  | 'last.first'
  | 'firstinitial.last'
  | 'first.lastinitial'

const courseRequirements = ref<CourseRequirement[]>([])

const EMAIL_FORMAT_OPTIONS: Array<{ value: EmailFormat; label: string; example: string }> = [
  { value: 'first.last', label: 'firstname.lastname', example: 'juan.delacruz' },
  { value: 'last.first', label: 'lastname.firstname', example: 'delacruz.juan' },
  { value: 'firstinitial.last', label: 'f.lastname', example: 'j.delacruz' },
  { value: 'first.lastinitial', label: 'firstname.l', example: 'juan.d' },
]

interface BulkImportResult {
  created: number
  skipped: number
  failed: Array<{ line: number; message: string }>
}

interface RecentInvite {
  email: string
  studentName: string
  sent: boolean
}

function defaultStudentForm(): StudentFormState {
  return {
    firstName: '',
    lastName: '',
    emailFormat: 'first.last',
    studentNumber: '',
    course: '',
    yearLevel: '',
    yearLevelChoice: '',
  }
}

function normalizeStatus(status?: string) {
  const normalized = String(status || 'Pending Setup').trim().toLowerCase()
  if (normalized === 'registered') return 'registered'
  if (normalized === 'disabled') return 'disabled'
  return 'pending'
}

function statusBadgeVariant(status?: string) {
  const normalized = normalizeStatus(status)
  if (normalized === 'registered') return 'success'
  if (normalized === 'disabled') return 'destructive'
  return 'warning'
}

const { success, error } = useToast()
const authStore = useAuthStore()

const loading = ref(true)
const saving = ref(false)
const exporting = ref(false)
const importing = ref(false)
const search = ref('')
const createDialogOpen = ref(false)
const bulkDialogOpen = ref(false)
const editDialogOpen = ref(false)
const editingStudent = ref<SchoolStudentRow | null>(null)
const studentNumberFieldError = ref('')
const editForm = ref({
  studentName: '',
  studentNumber: '',
  course: '',
  yearLevel: '',
})
const editNumberError = ref('')
const deleteTarget = ref<SchoolStudentRow | null>(null)
const studentForm = ref<StudentFormState>(defaultStudentForm())
const bulkCsv = ref('')
const bulkImportMode = ref<StudentCsvImportMode>('skip_duplicates')
const bulkDefaultCourse = ref('')
const bulkDefaultYearLevel = ref('')
const bulkCsvFile = ref<File | null>(null)
const bulkFileDragOver = ref(false)
const bulkFileInput = ref<HTMLInputElement | null>(null)
const latestInvites = ref<RecentInvite[]>([])
const latestBulkImport = ref<BulkImportResult | null>(null)
const students = ref<SchoolStudentRow[]>([])
const openDropdown = ref<'emailFormat' | 'course' | 'yearLevel' | null>(null)

const studentCsvColumnHint = STUDENT_CSV_COLUMNS.map((column) => column.header).join(',')

const bulkParsed = computed(() => parseStudentCsv(bulkCsv.value))
const bulkPreviewRows = computed(() =>
  bulkParsed.value.rows.map((row) =>
    applyStudentCsvDefaults(row, {
      course: bulkDefaultCourse.value,
      yearLevel: bulkDefaultYearLevel.value,
    }),
  ),
)
const bulkYearLevelOptions = computed(() => eligibleYearsForCourse(bulkDefaultCourse.value))

function resetBulkImportState() {
  bulkCsv.value = ''
  bulkCsvFile.value = null
  bulkFileDragOver.value = false
  bulkImportMode.value = 'skip_duplicates'
  bulkDefaultCourse.value = ''
  bulkDefaultYearLevel.value = ''
  if (bulkFileInput.value) bulkFileInput.value.value = ''
}

function removeBulkCsvFile() {
  bulkCsvFile.value = null
  if (bulkFileInput.value) bulkFileInput.value.value = ''
}

async function readBulkCsvFile(file: File) {
  const name = file.name.toLowerCase()
  if (!name.endsWith('.csv') && file.type !== 'text/csv' && file.type !== 'application/vnd.ms-excel') {
    error('Please choose a .csv file.')
    return
  }
  try {
    const text = await file.text()
    bulkCsvFile.value = file
    bulkCsv.value = text
  } catch {
    error('Could not read that CSV file.')
  }
}

function onBulkFileInputChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) void readBulkCsvFile(file)
}

function onBulkDrop(event: DragEvent) {
  event.preventDefault()
  bulkFileDragOver.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) void readBulkCsvFile(file)
}

let unsubscribeStudents: (() => void) | null = null

const filteredStudents = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return students.value
  return students.value.filter((student) =>
    [student.internCode, student.email, student.studentName, student.studentNumber, student.course, student.yearLevel]
      .some((value) => String(value || '').toLowerCase().includes(query)),
  )
})

const schoolProfile = computed(() => (authStore.user?.profile as Record<string, unknown> | undefined) ?? {})
const schoolCourseOptions = computed(() => {
  const fromRequirements = courseRequirements.value.map((item) => item.courseName)
  const raw = schoolProfile.value?.courses
  const fromProfile = Array.isArray(raw) ? raw.map((value) => String(value).trim()).filter(Boolean) : []
  return Array.from(new Set([...fromRequirements, ...fromProfile]))
})

const yearLevelOptionsForSelectedCourse = computed(() => {
  const course = studentForm.value.course.trim().toLowerCase()
  if (!course) return [...DEFAULT_ELIGIBLE_YEAR_LEVELS]
  const match = courseRequirements.value.find((item) => item.courseName.trim().toLowerCase() === course)
  if (match?.eligibleYearLevels?.length) return match.eligibleYearLevels
  return [...DEFAULT_ELIGIBLE_YEAR_LEVELS]
})

function eligibleYearsForCourse(courseName: string): string[] {
  const key = courseName.trim().toLowerCase()
  const match = courseRequirements.value.find((item) => item.courseName.trim().toLowerCase() === key)
  if (match?.eligibleYearLevels?.length) return match.eligibleYearLevels
  return [...DEFAULT_ELIGIBLE_YEAR_LEVELS]
}
const schoolEmailDomain = computed(() => {
  const official = String(schoolProfile.value?.officialSchoolEmail ?? '').trim().toLowerCase()
  const fallback = String(authStore.user?.email ?? '').trim().toLowerCase()
  const source = official || fallback
  const domain = source.includes('@') ? source.split('@')[1] : ''
  return domain
})
const generatedEmail = computed(() => {
  const first = slugPart(studentForm.value.firstName)
  const last = slugPart(studentForm.value.lastName)
  const domain = schoolEmailDomain.value

  if (!first || !last || !domain) {
    return ''
  }

  let localPart = ''
  switch (studentForm.value.emailFormat) {
    case 'last.first':
      localPart = `${last}.${first}`
      break
    case 'firstinitial.last':
      localPart = `${first.charAt(0)}.${last}`
      break
    case 'first.lastinitial':
      localPart = `${first}.${last.charAt(0)}`
      break
    default:
      localPart = `${first}.${last}`
      break
  }

  return `${localPart}@${domain}`
})
const studentOverage = computed(() =>
  authStore.user?.subscription?.overages?.items?.find((item) => item.key === 'school.students') ?? null,
)
const selectedEmailFormat = computed(() => {
  const found = EMAIL_FORMAT_OPTIONS.find((option) => option.value === studentForm.value.emailFormat)
  return found ?? EMAIL_FORMAT_OPTIONS[0]!
})
const selectedYearLevelLabel = computed(() => studentForm.value.yearLevel || 'Select year level')

function toggleDropdown(name: 'emailFormat' | 'course' | 'yearLevel') {
  openDropdown.value = openDropdown.value === name ? null : name
}

function closeDropdowns() {
  openDropdown.value = null
}

function selectEmailFormat(value: EmailFormat) {
  studentForm.value.emailFormat = value
  closeDropdowns()
}

function selectCourse(value: string) {
  studentForm.value.course = value
  const allowed = eligibleYearsForCourse(value)
  if (studentForm.value.yearLevel && !allowed.includes(studentForm.value.yearLevel)) {
    studentForm.value.yearLevel = ''
    studentForm.value.yearLevelChoice = ''
  }
  closeDropdowns()
}

watch(
  () => studentForm.value.course,
  () => {
    const allowed = yearLevelOptionsForSelectedCourse.value
    if (studentForm.value.yearLevel && !allowed.includes(studentForm.value.yearLevel)) {
      studentForm.value.yearLevel = ''
      studentForm.value.yearLevelChoice = ''
    }
  },
)

watch(bulkDialogOpen, (open) => {
  if (!open) resetBulkImportState()
})

watch(bulkDefaultCourse, (course) => {
  const allowed = eligibleYearsForCourse(course)
  if (bulkDefaultYearLevel.value && !allowed.includes(bulkDefaultYearLevel.value)) {
    bulkDefaultYearLevel.value = ''
  }
})

function selectYearLevel(value: string) {
  studentForm.value.yearLevelChoice = value
  studentForm.value.yearLevel = value
  closeDropdowns()
}

function handleGlobalPointer(event: MouseEvent) {
  const target = event.target as HTMLElement | null
  if (!target?.closest('[data-student-dropdown]')) {
    closeDropdowns()
  }
}

function slugPart(value: string) {
  return value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, '')
    .replace(/\s+/g, '')
}

function openEditStudent(student: SchoolStudentRow) {
  editingStudent.value = student
  editForm.value = {
    studentName: student.studentName || '',
    studentNumber: student.studentNumber || '',
    course: student.course || '',
    yearLevel: student.yearLevel || '',
  }
  editNumberError.value = ''
  editDialogOpen.value = true
}

async function saveEditedStudent(close: () => void) {
  if (!editingStudent.value) return
  const numberError = studentNumberErrorMessage(editForm.value.studentNumber, true)
  editNumberError.value = numberError || ''
  if (numberError) return

  saving.value = true
  try {
    await updateSchoolStudent(editingStudent.value.id, {
      studentName: editForm.value.studentName.trim() || undefined,
      studentNumber: normalizeStudentNumber(editForm.value.studentNumber),
      course: editForm.value.course || undefined,
      yearLevel: editForm.value.yearLevel || undefined,
    })
    success('Student account updated.')
    editingStudent.value = null
    close()
  } catch (caughtError) {
    const message = caughtError instanceof Error ? caughtError.message : 'Could not update the student account.'
    if (/student number/i.test(message)) {
      editNumberError.value = message
    }
    error(caughtError, { fallback: 'Could not update the student account.' })
  } finally {
    saving.value = false
  }
}

async function createStudent(close: () => void) {
  const numberError = studentNumberErrorMessage(studentForm.value.studentNumber, true)
  studentNumberFieldError.value = numberError || ''
  if (numberError) return

  const confirmation = await Swal.fire({
    icon: 'question',
    title: 'Create this student account?',
    html: `
      <div style="text-align:left;display:grid;gap:10px;">
        <div><strong>Name:</strong> ${studentForm.value.firstName} ${studentForm.value.lastName}</div>
        <div><strong>School Email:</strong> ${generatedEmail.value}</div>
        <div><strong>Intern Code:</strong> This will be generated automatically</div>
        <div><strong>Course:</strong> ${studentForm.value.course}</div>
        <div><strong>Year Level:</strong> ${studentForm.value.yearLevel}</div>
        <div><strong>Student Number:</strong> ${normalizeStudentNumber(studentForm.value.studentNumber)}</div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Create Account',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2563eb',
  })

  if (!confirmation.isConfirmed) return

  saving.value = true
  try {
    const created = await addSchoolStudent('', generatedEmail.value, {
      studentName: `${studentForm.value.firstName} ${studentForm.value.lastName}`.trim() || undefined,
      studentNumber: normalizeStudentNumber(studentForm.value.studentNumber),
      course: studentForm.value.course || undefined,
      yearLevel: studentForm.value.yearLevel || undefined,
    })

    latestInvites.value = [
      {
        email: created.email,
        studentName: created.studentName || created.email,
        sent: created.inviteSent !== false,
      },
      ...latestInvites.value,
    ].slice(0, 5)

    success('Student account created.', {
      description: created.inviteSent === false
        ? 'The account was created, but the email setup link could not be sent.'
        : 'A secure account setup link was sent to the student email.',
    })
    await authStore.refreshUser()
    await Swal.fire({
      icon: 'success',
      title: created.inviteSent === false ? 'Student Account Created' : 'Setup Link Sent',
      html: `
        <div style="text-align:left;display:grid;gap:10px;">
          <div style="padding:12px 14px;border-radius:12px;background:#eff6ff;border:1px solid #bfdbfe;">
            <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#1d4ed8;">School Email</div>
            <div style="margin-top:4px;font-size:15px;font-weight:600;color:#0f172a;">${created.email}</div>
          </div>
          <div style="padding:12px 14px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;">
            <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#475569;">Intern Code</div>
            <div style="margin-top:4px;font-size:15px;font-weight:700;color:#0f172a;">${created.internCode || 'Generated after save'}</div>
          </div>
          <div style="padding:12px 14px;border-radius:12px;background:${created.inviteSent === false ? '#fff7ed' : '#ecfdf5'};border:1px solid ${created.inviteSent === false ? '#fdba74' : '#a7f3d0'};">
            <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:${created.inviteSent === false ? '#c2410c' : '#047857'};">${created.inviteSent === false ? 'Email Delivery' : 'Account Setup'}</div>
            <div style="margin-top:4px;font-size:14px;font-weight:600;color:${created.inviteSent === false ? '#9a3412' : '#064e3b'};">${created.inviteSent === false ? 'Invite email could not be sent. You can resend the setup link from the roster.' : 'The student will receive a link to create their own password and confirm the account.'}</div>
          </div>
          <div style="padding:12px 14px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:13px;">
            Admins no longer need to share temporary passwords manually.
          </div>
        </div>
      `,
      confirmButtonText: 'Done',
      confirmButtonColor: '#2563eb',
    })
    studentForm.value = defaultStudentForm()
    close()
  } catch (caughtError) {
    const message = caughtError instanceof Error ? caughtError.message : 'Could not create the student account.'
    if (/student number/i.test(message)) {
      studentNumberFieldError.value = message
    }
    error(caughtError, {
      fallback: 'Could not create the student account.',
    })
  } finally {
    saving.value = false
  }
}

async function confirmDeleteStudent() {
  if (!deleteTarget.value) return
  try {
    await removeSchoolStudent(deleteTarget.value.id)
    await authStore.refreshUser()
    success('Student account removed.')
    deleteTarget.value = null
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Could not remove the student account.',
    })
  }
}

async function runBulkImport(close: () => void) {
  const { rows: parsedRows, issues } = parseStudentCsv(bulkCsv.value)
  const rows = parsedRows.map((row) =>
    applyStudentCsvDefaults(row, {
      course: bulkDefaultCourse.value,
      yearLevel: bulkDefaultYearLevel.value,
    }),
  )

  if (!rows.length && !issues.length) {
    error('Add at least one CSV row or choose a .csv file before importing.')
    return
  }

  if (!rows.length) {
    error(`CSV has ${issues.length} invalid row${issues.length === 1 ? '' : 's'}. Fix them before importing.`)
    return
  }

  const modeLabel =
    bulkImportMode.value === 'skip_duplicates' ? 'Skip duplicates' : 'Add new only (duplicates fail)'

  const confirmation = await Swal.fire({
    icon: 'question',
    title: 'Import student accounts?',
    html: `
      <div style="text-align:left;display:grid;gap:8px;">
        <div><strong>Rows ready:</strong> ${rows.length}</div>
        <div><strong>Mode:</strong> ${modeLabel}</div>
        <div>New accounts still use the normal setup-link email flow.</div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Import Accounts',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2563eb',
  })

  if (!confirmation.isConfirmed) return

  importing.value = true
  const result: BulkImportResult = { created: 0, skipped: 0, failed: [...issues] }
  const invites: RecentInvite[] = []

  for (const row of rows) {
    if (row.yearLevel) {
      const allowed = eligibleYearsForCourse(row.course || '')
      if (!allowed.includes(row.yearLevel)) {
        result.failed.push({
          line: row.line,
          message: `Year level must be one of: ${allowed.join(', ')}.`,
        })
        continue
      }
    }

    const duplicate = isDuplicateStudentCsvRow(row, students.value)
    if (duplicate) {
      if (bulkImportMode.value === 'skip_duplicates') {
        result.skipped += 1
        continue
      }
      result.failed.push({
        line: row.line,
        message: 'Duplicate email or student number already exists in the roster.',
      })
      continue
    }

    try {
      const created = await addSchoolStudent('', row.email, {
        studentName: row.studentName || undefined,
        studentNumber: row.studentNumber || undefined,
        course: row.course || undefined,
        yearLevel: row.yearLevel || undefined,
      })
      result.created += 1
      invites.push({
        email: created.email,
        studentName: created.studentName || created.email,
        sent: created.inviteSent !== false,
      })
    } catch (caughtError) {
      result.failed.push({
        line: row.line,
        message: caughtError instanceof Error ? caughtError.message : 'Import failed.',
      })
    }
  }

  latestBulkImport.value = result
  latestInvites.value = [...invites, ...latestInvites.value].slice(0, 10)

  if (result.created > 0) {
    await authStore.refreshUser()
    success(
      `Imported ${result.created} student account${result.created === 1 ? '' : 's'}` +
        (result.skipped ? ` · skipped ${result.skipped} duplicate${result.skipped === 1 ? '' : 's'}` : '') +
        '.',
    )
  } else if (result.skipped > 0 && result.failed.length === 0) {
    success(`No new accounts created. Skipped ${result.skipped} duplicate${result.skipped === 1 ? '' : 's'}.`)
  }
  if (result.failed.length > 0) {
    error(`Some rows failed to import (${result.failed.length}).`)
  }

  if (result.failed.length === 0) {
    resetBulkImportState()
    close()
  }

  importing.value = false
}

function toTitleCaseName(value: string | null | undefined): string {
  const raw = String(value || '').trim()
  if (!raw) return 'Not set'
  return raw
    .toLowerCase()
    .split(/\s+/)
    .map((part) => (part ? part[0]!.toUpperCase() + part.slice(1) : part))
    .join(' ')
}

async function resendSetupLink(student: SchoolStudentRow) {
  try {
    const invite = await resendSchoolStudentSetupLink(student.id)
    await authStore.refreshUser()
    if (invite.sent) {
      success('Setup link sent again.', {
        description: `A fresh account setup email was sent to ${student.email}.`,
      })
    } else {
      error(invite.errorMessage || 'Could not send the setup link.')
    }
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Could not resend the setup link.',
    })
  }
}

async function copySetupLink(student: SchoolStudentRow) {
  try {
    const invite = await resendSchoolStudentSetupLink(student.id)
    await authStore.refreshUser()
    if (!invite.setupUrl) {
      error('Setup link was not returned by the server. Use Resend Link to email it instead.')
      return
    }
    await navigator.clipboard.writeText(invite.setupUrl)
    success('Setup link copied.', {
      description: invite.sent
        ? 'Copied to clipboard. A fresh setup email was also sent.'
        : 'Copied to clipboard. Email delivery failed, but the link is ready to share.',
    })
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Could not copy the setup link.',
    })
  }
}

async function exportStudents() {
  exporting.value = true
  try {
    const blob = await exportSchoolStudentsCsv('')
    const objectUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = objectUrl
    link.download = 'school-students.csv'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(objectUrl)
    success('Student roster exported.')
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Could not export the student roster.',
    })
  } finally {
    exporting.value = false
  }
}

onMounted(() => {
  unsubscribeStudents = subscribeSchoolStudents('', (items) => {
    students.value = items
    loading.value = false
  })
  void listCourseRequirements()
    .then(({ requirements }) => {
      courseRequirements.value = requirements
    })
    .catch(() => {
      courseRequirements.value = []
    })
  window.addEventListener('click', handleGlobalPointer)
})

onUnmounted(() => {
  unsubscribeStudents?.()
  window.removeEventListener('click', handleGlobalPointer)
})
</script>

<template>
  <MainLayout role="school" title="Student Accounts" active-item="student-accounts">
    <div class="space-y-6">
      <Card class="border-border/80 shadow-sm">
        <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Student Account Management</h2>
            <p class="text-sm text-muted-foreground">
              Create student accounts, send setup emails, bulk import roster entries, and export the current list.
            </p>
          </div>
          <div class="flex flex-col gap-3 sm:flex-row">
            <Button variant="outline" :disabled="exporting" @click="exportStudents">
              <LoaderCircle v-if="exporting" class="h-4 w-4 animate-spin" />
              <Download v-else class="h-4 w-4" />
              <span>Export CSV</span>
            </Button>
            <Button variant="outline" @click="bulkDialogOpen = true">
              <Upload class="h-4 w-4" />
              <span>Import Students</span>
            </Button>
            <Button @click="createDialogOpen = true">
              <UserRoundPlus class="h-4 w-4" />
              <span>Create Student Account</span>
            </Button>
          </div>
        </CardHeader>
      </Card>

      <Card v-if="studentOverage" class="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40 shadow-sm">
        <CardHeader>
          <h2 class="text-xl font-semibold text-amber-900">Student Limit Reached</h2>
          <p class="text-sm text-amber-800">
            {{ studentOverage.message }}
          </p>
        </CardHeader>
      </Card>

      <Card v-if="latestInvites.length" class="border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30 shadow-sm">
        <CardHeader>
          <h2 class="text-xl font-semibold text-emerald-900">Latest Student Invite Emails</h2>
          <p class="text-sm text-emerald-700">
            Students receive a secure setup link by email and create their own password from there.
          </p>
        </CardHeader>
        <CardContent class="grid gap-3 md:grid-cols-2">
          <div
            v-for="invite in latestInvites"
            :key="invite.email"
            class="rounded-xl border border-emerald-200 bg-card p-4"
          >
            <p class="font-semibold text-foreground">{{ invite.studentName }}</p>
            <p class="mt-2 text-sm text-muted-foreground">{{ invite.email }}</p>
            <p class="mt-2 text-sm font-medium" :class="invite.sent ? 'text-emerald-700' : 'text-amber-700'">
              {{ invite.sent ? 'Setup link sent successfully.' : 'Invite email needs to be resent.' }}
            </p>
          </div>
        </CardContent>
      </Card>

      <Card v-if="latestBulkImport" class="border-border/80 shadow-sm">
        <CardHeader>
          <h2 class="text-xl font-semibold text-foreground">Last Import Result</h2>
        </CardHeader>
        <CardContent class="space-y-3">
          <p class="text-sm text-muted-foreground">
            Created {{ latestBulkImport.created }} account{{ latestBulkImport.created === 1 ? '' : 's' }}
            <template v-if="latestBulkImport.skipped">
              · skipped {{ latestBulkImport.skipped }} duplicate{{ latestBulkImport.skipped === 1 ? '' : 's' }}
            </template>.
          </p>
          <div v-if="latestBulkImport.failed.length" class="space-y-2 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40 p-4">
            <p class="font-medium text-red-700">Failed rows</p>
            <p
              v-for="failure in latestBulkImport.failed"
              :key="`${failure.line}-${failure.message}`"
              class="text-sm text-red-600"
            >
              Line {{ failure.line }}: {{ failure.message }}
            </p>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Student Roster</h2>
            <p class="text-sm text-muted-foreground">
              Search by intern code, email, name, course, or year level.
            </p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="search" placeholder="Search student accounts or intern code..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent>
          <div v-if="loading" class="space-y-3">
            <Skeleton v-for="index in 6" :key="index" class="h-14 rounded-xl" />
          </div>
          <div v-else class="overflow-hidden rounded-xl border border-border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Intern Code</TableHead>
                  <TableHead>Email</TableHead>
                  <TableHead>Name</TableHead>
                  <TableHead>Student Number</TableHead>
                  <TableHead>Course</TableHead>
                  <TableHead>Year Level</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="student in filteredStudents" :key="student.id">
                  <TableCell class="font-mono text-xs font-semibold text-foreground">{{ student.internCode || 'Pending' }}</TableCell>
                  <TableCell class="font-medium">{{ student.email }}</TableCell>
                  <TableCell>{{ toTitleCaseName(student.studentName) }}</TableCell>
                  <TableCell>
                    <span v-if="student.studentNumber">{{ student.studentNumber }}</span>
                    <Badge v-else variant="warning">Needs student number</Badge>
                  </TableCell>
                  <TableCell>{{ student.course || 'Not set' }}</TableCell>
                  <TableCell>{{ student.yearLevel || 'Not set' }}</TableCell>
                  <TableCell>
                    <Badge :variant="statusBadgeVariant(student.status)">
                      {{ student.status || 'Pending Setup' }}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div class="flex flex-wrap gap-2">
                      <Button variant="outline" size="sm" @click="openEditStudent(student)">
                        Edit
                      </Button>
                      <Button
                        v-if="normalizeStatus(student.status) === 'pending'"
                        variant="outline"
                        size="sm"
                        @click="resendSetupLink(student)"
                      >
                        <Mail class="h-4 w-4" />
                        Resend Link
                      </Button>
                      <Button
                        v-if="normalizeStatus(student.status) === 'pending'"
                        variant="outline"
                        size="sm"
                        @click="copySetupLink(student)"
                      >
                        <Copy class="h-4 w-4" />
                        Copy setup link
                      </Button>
                      <Button variant="ghost" size="sm" class="text-red-600 hover:text-red-600" @click="deleteTarget = student">
                        <Trash2 class="h-4 w-4" />
                        Remove
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
                <TableRow v-if="!filteredStudents.length">
                  <TableCell colspan="8" class="py-10 text-center text-muted-foreground">
                    No student accounts matched your search.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>

    <Dialog v-model:open="createDialogOpen">
      <template #default="{ close }">
        <div class="flex max-h-[min(90vh,44rem)] flex-col">
        <DialogHeader class="shrink-0">
          <DialogTitle>Create Student Account</DialogTitle>
          <p class="text-sm text-muted-foreground">
            Create a student account using your school email domain. The student will receive a setup link to create their own password.
          </p>
        </DialogHeader>
        <div class="mt-6 min-h-0 flex-1 space-y-5 overflow-y-auto pr-1">
          <div class="rounded-2xl border border-border bg-card px-4 py-4">
            <div class="flex items-start gap-3">
              <div class="rounded-2xl bg-accent p-3 text-primary">
                <Sparkles class="h-5 w-5" />
              </div>
              <div>
                <p class="text-sm font-semibold text-foreground">Smart student setup</p>
                <p class="mt-1 text-sm text-muted-foreground">
                  Fill in the student name, then choose the email format, course, and year level. The school email is generated automatically for you.
                </p>
              </div>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
          <FormItem class="rounded-2xl border border-border bg-card/80 p-4 shadow-sm">
            <FormLabel class="text-foreground">First Name</FormLabel>
            <FormControl>
              <Input v-model="studentForm.firstName" type="text" placeholder="Juan" class="mt-1 border-border bg-muted/80 placeholder:text-muted-foreground/40" />
            </FormControl>
          </FormItem>
          <FormItem class="rounded-2xl border border-border bg-card/80 p-4 shadow-sm">
            <FormLabel class="text-foreground">Last Name</FormLabel>
            <FormControl>
              <Input v-model="studentForm.lastName" placeholder="Dela Cruz" class="mt-1 border-border bg-muted/80 placeholder:text-muted-foreground/40" />
            </FormControl>
          </FormItem>
          <FormItem class="rounded-2xl border border-border bg-card/80 p-4 shadow-sm">
            <FormLabel class="text-foreground">Email Format</FormLabel>
            <FormControl>
              <div class="relative mt-1" data-student-dropdown>
                <AtSign class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary" />
                <button
                  type="button"
                  class="dropdown-trigger w-full rounded-xl border border-border bg-muted/90 py-3 pl-10 pr-10 text-left text-sm font-medium text-foreground shadow-sm transition focus:border-ring focus:ring-2 focus:ring-ring/20"
                  @click="toggleDropdown('emailFormat')"
                >
                  <span>{{ selectedEmailFormat.label }} ({{ selectedEmailFormat.example }})</span>
                </button>
                <ChevronDown
                  class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground transition"
                  :class="{ 'rotate-180': openDropdown === 'emailFormat' }"
                />
                <div v-if="openDropdown === 'emailFormat'" class="dropdown-menu">
                  <button
                    v-for="option in EMAIL_FORMAT_OPTIONS"
                    :key="option.value"
                    type="button"
                    class="dropdown-option"
                    :class="{ active: studentForm.emailFormat === option.value }"
                    @click="selectEmailFormat(option.value)"
                  >
                    <span class="dropdown-option-title">{{ option.label }}</span>
                    <span class="dropdown-option-copy">{{ option.example }}</span>
                  </button>
                </div>
              </div>
            </FormControl>
          </FormItem>
          <FormItem class="rounded-2xl border border-border bg-card/80 p-4 shadow-sm">
            <FormLabel class="text-foreground">School Email</FormLabel>
            <FormControl>
              <Input
                :model-value="generatedEmail || 'Fill first and last name to generate email'"
                readonly
                class="mt-1 border-border bg-accent/80 font-medium text-foreground"
              />
            </FormControl>
          </FormItem>
          <FormItem class="rounded-2xl border border-border bg-card/80 p-4 shadow-sm">
            <FormLabel class="text-foreground">
              Student Number <span class="text-red-600" aria-hidden="true">*</span>
            </FormLabel>
            <FormControl>
              <div class="relative mt-1">
                <IdCard class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  v-model="studentForm.studentNumber"
                  placeholder="2024-0001"
                  class="border-border bg-muted/80 pl-10 placeholder:text-muted-foreground/40"
                  :aria-invalid="Boolean(studentNumberFieldError)"
                  @update:model-value="studentNumberFieldError = ''"
                />
              </div>
            </FormControl>
            <p v-if="studentNumberFieldError" class="mt-2 text-sm text-red-600">{{ studentNumberFieldError }}</p>
            <p v-else class="mt-2 text-xs text-muted-foreground">Required. Letters, numbers, and - / . _ only. Unique within this school.</p>
          </FormItem>
          <FormItem class="rounded-2xl border border-border bg-card/80 p-4 shadow-sm">
            <FormLabel class="text-foreground">Course</FormLabel>
            <FormControl>
              <div class="relative mt-1" data-student-dropdown>
                <BookOpenText class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                <button
                  type="button"
                  class="dropdown-trigger w-full rounded-xl border border-border bg-muted/90 py-3 pl-10 pr-10 text-left text-sm font-medium text-foreground shadow-sm transition focus:border-emerald-300 focus:ring-2 focus:ring-emerald-100"
                  @click="toggleDropdown('course')"
                >
                  <span>{{ studentForm.course || 'Select a saved school course' }}</span>
                </button>
                <ChevronDown
                  class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground transition"
                  :class="{ 'rotate-180': openDropdown === 'course' }"
                />
                <div v-if="openDropdown === 'course'" class="dropdown-menu">
                  <button
                    type="button"
                    class="dropdown-option"
                    :class="{ active: !studentForm.course }"
                    @click="selectCourse('')"
                  >
                    <span class="dropdown-option-title">Select a saved school course</span>
                  </button>
                  <button
                    v-for="course in schoolCourseOptions"
                    :key="course"
                    type="button"
                    class="dropdown-option"
                    :class="{ active: studentForm.course === course }"
                    @click="selectCourse(course)"
                  >
                    <span class="dropdown-option-title">{{ course }}</span>
                  </button>
                </div>
              </div>
            </FormControl>
          </FormItem>
          <FormItem class="md:col-span-2 rounded-2xl border border-border bg-card/80 p-4 shadow-sm">
            <FormLabel class="text-foreground">Year Level</FormLabel>
            <FormControl>
              <div class="relative mt-1" data-student-dropdown>
                <UserRoundPlus class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-violet-600" />
                <button
                  type="button"
                  class="dropdown-trigger w-full rounded-xl border border-border bg-muted/90 py-3 pl-10 pr-10 text-left text-sm font-medium text-foreground shadow-sm transition focus:border-violet-300 focus:ring-2 focus:ring-violet-100"
                  @click="toggleDropdown('yearLevel')"
                >
                  <span>{{ selectedYearLevelLabel }}</span>
                </button>
                <ChevronDown
                  class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground transition"
                  :class="{ 'rotate-180': openDropdown === 'yearLevel' }"
                />
                <div v-if="openDropdown === 'yearLevel'" class="dropdown-menu">
                  <button
                    type="button"
                    class="dropdown-option"
                    :class="{ active: !studentForm.yearLevelChoice }"
                    @click="selectYearLevel('')"
                  >
                    <span class="dropdown-option-title">Select year level</span>
                  </button>
                  <button
                    v-for="yearLevel in yearLevelOptionsForSelectedCourse"
                    :key="yearLevel"
                    type="button"
                    class="dropdown-option"
                    :class="{ active: studentForm.yearLevelChoice === yearLevel }"
                    @click="selectYearLevel(yearLevel)"
                  >
                    <span class="dropdown-option-title">{{ yearLevel }}</span>
                  </button>
                </div>
              </div>
            </FormControl>
          </FormItem>
          <div class="md:col-span-2 rounded-xl border border-border bg-muted px-4 py-3 text-sm text-muted-foreground">
            <p><strong>School email domain:</strong> {{ schoolEmailDomain || 'Set your official school email in Settings first' }}</p>
            <p v-if="!schoolCourseOptions.length" class="mt-1">
              Add your school courses in Settings first so admins can pick them here instead of typing manually.
            </p>
          </div>
          <div
            v-if="generatedEmail"
            class="md:col-span-2 rounded-2xl border border-border bg-gradient-to-r from-accent via-card to-emerald-50 px-4 py-4"
          >
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">Preview</p>
                <p class="mt-1 text-lg font-semibold text-foreground">{{ generatedEmail }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                  {{ studentForm.firstName }} {{ studentForm.lastName }} • {{ studentForm.course || 'Choose a course' }} • {{ studentForm.yearLevel || 'Choose year level' }}
                </p>
              </div>
              <div class="rounded-xl bg-card/80 px-4 py-3 text-sm text-muted-foreground shadow-sm">
                <p class="font-semibold text-foreground">Auto-generated</p>
                <p class="mt-1">Uses your school domain and selected name format.</p>
              </div>
            </div>
          </div>
          </div>
        </div>
        <div class="mt-4 flex shrink-0 justify-end gap-3 border-t border-border bg-card pt-4">
          <Button variant="outline" @click="close">Cancel</Button>
          <Button
            :disabled="saving || !studentForm.firstName.trim() || !studentForm.lastName.trim() || !generatedEmail || !studentForm.course || !studentForm.yearLevel || !normalizeStudentNumber(studentForm.studentNumber)"
            @click="createStudent(close)"
          >
            <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" />
            <span>{{ saving ? 'Creating...' : 'Create Account' }}</span>
          </Button>
        </div>
        </div>
      </template>
    </Dialog>

    <Dialog v-model:open="editDialogOpen">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>Edit Student Account</DialogTitle>
          <p class="text-sm text-muted-foreground">
            Update enrollment details for {{ editingStudent?.email || 'this student' }}.
          </p>
        </DialogHeader>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
          <FormItem class="md:col-span-2">
            <FormLabel>Full Name</FormLabel>
            <FormControl>
              <Input v-model="editForm.studentName" placeholder="Juan Dela Cruz" />
            </FormControl>
          </FormItem>
          <FormItem class="md:col-span-2">
            <FormLabel>
              Student Number <span class="text-red-600" aria-hidden="true">*</span>
            </FormLabel>
            <FormControl>
              <Input
                v-model="editForm.studentNumber"
                placeholder="2024-0001"
                :aria-invalid="Boolean(editNumberError)"
                @update:model-value="editNumberError = ''"
              />
            </FormControl>
            <p v-if="editNumberError" class="mt-2 text-sm text-red-600">{{ editNumberError }}</p>
            <p v-else class="mt-2 text-xs text-muted-foreground">Required and unique within this school.</p>
          </FormItem>
          <FormItem>
            <FormLabel>Course</FormLabel>
            <FormControl>
              <select v-model="editForm.course" class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm">
                <option value="">Select course</option>
                <option v-for="course in schoolCourseOptions" :key="course" :value="course">{{ course }}</option>
              </select>
            </FormControl>
          </FormItem>
          <FormItem>
            <FormLabel>Year Level</FormLabel>
            <FormControl>
              <select v-model="editForm.yearLevel" class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm">
                <option value="">Select year level</option>
                <option v-for="level in eligibleYearsForCourse(editForm.course)" :key="level" :value="level">{{ level }}</option>
              </select>
            </FormControl>
          </FormItem>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <Button variant="outline" @click="close">Cancel</Button>
          <Button :disabled="saving || !normalizeStudentNumber(editForm.studentNumber)" @click="saveEditedStudent(close)">
            <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" />
            <span>{{ saving ? 'Saving...' : 'Save Changes' }}</span>
          </Button>
        </div>
      </template>
    </Dialog>

    <Dialog v-model:open="bulkDialogOpen">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>Import Students</DialogTitle>
          <p class="text-sm text-muted-foreground">
            Columns: <code class="text-xs">{{ studentCsvColumnHint }}</code>
          </p>
        </DialogHeader>
        <div class="mt-6 space-y-4">
          <div class="flex flex-wrap gap-2">
            <Button type="button" variant="outline" size="sm" @click="downloadStudentCsvTemplate()">
              <Download class="h-4 w-4" />
              <span>Download template</span>
            </Button>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <FormItem>
              <FormLabel for="bulk-import-mode">Selection of import</FormLabel>
              <FormControl>
                <select
                  id="bulk-import-mode"
                  v-model="bulkImportMode"
                  class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                >
                  <option value="skip_duplicates">Skip duplicates (default)</option>
                  <option value="add_new_only">Add new only (duplicates fail)</option>
                </select>
              </FormControl>
            </FormItem>
            <FormItem>
              <FormLabel for="bulk-default-course">Default course (if CSV omits)</FormLabel>
              <FormControl>
                <select
                  id="bulk-default-course"
                  v-model="bulkDefaultCourse"
                  class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                >
                  <option value="">No default</option>
                  <option v-for="course in schoolCourseOptions" :key="course" :value="course">{{ course }}</option>
                </select>
              </FormControl>
            </FormItem>
            <FormItem class="sm:col-span-2">
              <FormLabel for="bulk-default-year">Default year level (if CSV omits)</FormLabel>
              <FormControl>
                <select
                  id="bulk-default-year"
                  v-model="bulkDefaultYearLevel"
                  class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                >
                  <option value="">No default</option>
                  <option v-for="level in bulkYearLevelOptions" :key="level" :value="level">{{ level }}</option>
                </select>
              </FormControl>
            </FormItem>
          </div>

          <div
            class="rounded-xl border border-dashed px-4 py-6 text-center transition"
            :class="bulkFileDragOver ? 'border-primary bg-primary/5' : 'border-border bg-muted/30'"
            @dragenter.prevent="bulkFileDragOver = true"
            @dragover.prevent="bulkFileDragOver = true"
            @dragleave.prevent="bulkFileDragOver = false"
            @drop="onBulkDrop"
          >
            <input
              ref="bulkFileInput"
              type="file"
              accept=".csv,text/csv"
              class="hidden"
              @change="onBulkFileInputChange"
            />
            <Upload class="mx-auto h-8 w-8 text-muted-foreground" />
            <p class="mt-2 text-sm font-medium text-foreground">Drag and drop a .csv file here</p>
            <p class="mt-1 text-xs text-muted-foreground">or</p>
            <Button type="button" variant="outline" size="sm" class="mt-2" @click="bulkFileInput?.click()">
              Browse files
            </Button>
            <div v-if="bulkCsvFile" class="mt-4 flex items-center justify-center gap-3 text-sm">
              <span class="text-foreground">{{ bulkCsvFile.name }} · {{ formatFileSize(bulkCsvFile.size) }}</span>
              <Button type="button" variant="ghost" size="sm" class="h-8 gap-1 text-destructive" @click="removeBulkCsvFile">
                <Trash2 class="h-4 w-4" />
                Remove
              </Button>
            </div>
          </div>

          <FormItem>
            <FormLabel for="bulk-csv-paste">Or paste CSV (fallback)</FormLabel>
            <FormControl>
              <Textarea
                id="bulk-csv-paste"
                v-model="bulkCsv"
                :rows="8"
                :placeholder="`${studentCsvColumnHint}\nstudent1@example.com,Juan Dela Cruz,2024-0001,BS Computer Science,4th Year`"
              />
            </FormControl>
          </FormItem>

          <div class="rounded-xl border border-border bg-muted/20 px-3 py-2 text-sm text-muted-foreground">
            <p>
              Preview: {{ bulkPreviewRows.length }} valid row{{ bulkPreviewRows.length === 1 ? '' : 's' }}
              <template v-if="bulkParsed.issues.length">
                · {{ bulkParsed.issues.length }} row error{{ bulkParsed.issues.length === 1 ? '' : 's' }}
              </template>
            </p>
            <ul v-if="bulkParsed.issues.length" class="mt-2 space-y-1 text-xs text-destructive">
              <li v-for="issue in bulkParsed.issues.slice(0, 8)" :key="`${issue.line}-${issue.message}`">
                Line {{ issue.line }}: {{ issue.message }}
              </li>
            </ul>
          </div>

          <div class="flex justify-end gap-3">
            <Button variant="outline" @click="close">Cancel</Button>
            <Button :disabled="importing || (!bulkPreviewRows.length && !bulkParsed.issues.length)" @click="runBulkImport(close)">
              <LoaderCircle v-if="importing" class="h-4 w-4 animate-spin" />
              <span>{{ importing ? 'Importing...' : 'Import Accounts' }}</span>
            </Button>
          </div>
        </div>
      </template>
    </Dialog>

    <AlertDialog
      :open="!!deleteTarget"
      title="Remove student account?"
      :description="deleteTarget ? `This will remove ${deleteTarget.email}. Activated accounts may need to be disabled instead.` : ''"
      action-label="Remove"
      action-variant="destructive"
      @update:open="(open) => { if (!open) deleteTarget = null }"
      @action="confirmDeleteStudent"
    />
  </MainLayout>
</template>

<style scoped>
.dropdown-trigger {
  outline: none;
}

.dropdown-menu {
  position: absolute;
  z-index: 30;
  top: calc(100% + 10px);
  left: 0;
  right: 0;
  max-height: 260px;
  overflow-y: auto;
  border-radius: 18px;
  border: 1px solid hsl(var(--border));
  background: hsl(var(--popover));
  color: hsl(var(--popover-foreground));
  box-shadow: 0 24px 60px -28px hsl(var(--foreground) / 0.35);
  padding: 8px;
}

.dropdown-option {
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
  padding: 12px 14px;
  border-radius: 14px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.2s ease;
  color: hsl(var(--foreground));
}

.dropdown-option:hover {
  background: hsl(var(--accent));
  transform: translateY(-1px);
}

.dropdown-option.active {
  background: hsl(var(--accent));
  color: hsl(var(--accent-foreground));
}

.dropdown-option-title {
  font-size: 0.92rem;
  font-weight: 700;
  color: hsl(var(--foreground));
}

.dropdown-option.active .dropdown-option-title {
  color: hsl(var(--primary));
}

.dropdown-option-copy {
  font-size: 0.78rem;
  color: hsl(var(--muted-foreground));
}
</style>
