<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import Swal from '@/services/swal'
import {
  AtSign,
  BookOpenText,
  ChevronDown,
  Download,
  IdCard,
  LoaderCircle,
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
  type SchoolStudentRow,
} from '@/services/schoolStudents'

interface StudentFormState {
  firstName: string
  lastName: string
  emailFormat: EmailFormat
  studentNumber: string
  course: string
  yearLevel: string
  yearLevelChoice: string
  customYearLevel: string
}

type EmailFormat =
  | 'first.last'
  | 'last.first'
  | 'firstinitial.last'
  | 'first.lastinitial'

const YEAR_LEVEL_OPTIONS = ['1st Year', '2nd Year', '3rd Year', '4th Year']
const CUSTOM_YEAR_LEVEL_VALUE = 'other'

const EMAIL_FORMAT_OPTIONS: Array<{ value: EmailFormat; label: string; example: string }> = [
  { value: 'first.last', label: 'firstname.lastname', example: 'juan.delacruz' },
  { value: 'last.first', label: 'lastname.firstname', example: 'delacruz.juan' },
  { value: 'firstinitial.last', label: 'f.lastname', example: 'j.delacruz' },
  { value: 'first.lastinitial', label: 'firstname.l', example: 'juan.d' },
]

interface BulkImportResult {
  created: number
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
    customYearLevel: '',
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

function parseBulkRows(input: string) {
  return input
    .split(/\r?\n/)
    .map((line, index) => ({ raw: line.trim(), line: index + 1 }))
    .filter((entry) => entry.raw.length > 0)
    .filter((entry) => !entry.raw.toLowerCase().startsWith('email,'))
    .map((entry) => {
      const [email = '', studentName = '', studentNumber = '', course = '', yearLevel = ''] = entry.raw
        .split(',')
        .map((part) => part.trim())

      return {
        line: entry.line,
        email,
        studentName,
        studentNumber,
        course,
        yearLevel,
      }
    })
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
const deleteTarget = ref<SchoolStudentRow | null>(null)
const studentForm = ref<StudentFormState>(defaultStudentForm())
const bulkCsv = ref('')
const latestInvites = ref<RecentInvite[]>([])
const latestBulkImport = ref<BulkImportResult | null>(null)
const students = ref<SchoolStudentRow[]>([])
const openDropdown = ref<'emailFormat' | 'course' | 'yearLevel' | null>(null)

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
  const raw = schoolProfile.value?.courses
  return Array.isArray(raw) ? raw.map((value) => String(value).trim()).filter(Boolean) : []
})
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
const selectedYearLevelLabel = computed(() => {
  if (studentForm.value.yearLevelChoice === CUSTOM_YEAR_LEVEL_VALUE) {
    return studentForm.value.customYearLevel || 'Other'
  }

  return studentForm.value.yearLevel || 'Select year level'
})
const isCustomYearLevel = computed(() => studentForm.value.yearLevelChoice === CUSTOM_YEAR_LEVEL_VALUE)

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
  closeDropdowns()
}

function selectYearLevel(value: string) {
  studentForm.value.yearLevelChoice = value
  if (value === CUSTOM_YEAR_LEVEL_VALUE) {
    studentForm.value.yearLevel = studentForm.value.customYearLevel.trim()
  } else {
    studentForm.value.yearLevel = value
    studentForm.value.customYearLevel = ''
  }
  closeDropdowns()
}

function updateCustomYearLevel(value: string) {
  studentForm.value.customYearLevel = value
  if (studentForm.value.yearLevelChoice === CUSTOM_YEAR_LEVEL_VALUE) {
    studentForm.value.yearLevel = value.trim()
  }
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

async function createStudent(close: () => void) {
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
        <div><strong>Student Number:</strong> ${studentForm.value.studentNumber || 'Not set'}</div>
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
      studentNumber: studentForm.value.studentNumber || undefined,
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
  const rows = parseBulkRows(bulkCsv.value)
  if (!rows.length) {
    error('Add at least one CSV row before importing.')
    return
  }

  const confirmation = await Swal.fire({
    icon: 'question',
    title: 'Import student accounts?',
    html: `
      <div style="text-align:left;display:grid;gap:8px;">
        <div><strong>Rows ready:</strong> ${rows.length}</div>
        <div>This will create student accounts for every valid row in your CSV list.</div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Import Accounts',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2563eb',
  })

  if (!confirmation.isConfirmed) return

  importing.value = true
  const result: BulkImportResult = { created: 0, failed: [] }
  const invites: RecentInvite[] = []

  for (const row of rows) {
    if (!row.email) {
      result.failed.push({ line: row.line, message: 'Missing email address.' })
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
    success(`Imported ${result.created} student account${result.created === 1 ? '' : 's'}.`)
  }
  if (result.failed.length > 0) {
    error(`Some rows failed to import (${result.failed.length}).`)
  }

  if (result.failed.length === 0) {
    bulkCsv.value = ''
    close()
  }

  importing.value = false
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

      <Card v-if="studentOverage" class="border-amber-200 bg-amber-50 shadow-sm">
        <CardHeader>
          <h2 class="text-xl font-semibold text-amber-900">Student Limit Reached</h2>
          <p class="text-sm text-amber-800">
            {{ studentOverage.message }}
          </p>
        </CardHeader>
      </Card>

      <Card v-if="latestInvites.length" class="border-emerald-200 bg-emerald-50 shadow-sm">
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
            class="rounded-xl border border-emerald-200 bg-white p-4"
          >
            <p class="font-semibold text-slate-900">{{ invite.studentName }}</p>
            <p class="mt-2 text-sm text-slate-600">{{ invite.email }}</p>
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
            Created {{ latestBulkImport.created }} account{{ latestBulkImport.created === 1 ? '' : 's' }}.
          </p>
          <div v-if="latestBulkImport.failed.length" class="space-y-2 rounded-xl border border-red-200 bg-red-50 p-4">
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
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
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
                  <TableCell class="font-mono text-xs font-semibold text-slate-700">{{ student.internCode || 'Pending' }}</TableCell>
                  <TableCell class="font-medium">{{ student.email }}</TableCell>
                  <TableCell>{{ student.studentName || 'Not set' }}</TableCell>
                  <TableCell>{{ student.studentNumber || 'Not set' }}</TableCell>
                  <TableCell>{{ student.course || 'Not set' }}</TableCell>
                  <TableCell>{{ student.yearLevel || 'Not set' }}</TableCell>
                  <TableCell>
                    <Badge :variant="statusBadgeVariant(student.status)">
                      {{ student.status || 'Pending Setup' }}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div class="flex flex-wrap gap-2">
                      <Button
                        v-if="normalizeStatus(student.status) === 'pending'"
                        variant="outline"
                        size="sm"
                        @click="resendSetupLink(student)"
                      >
                        <Mail class="h-4 w-4" />
                        Resend Link
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
        <DialogHeader>
          <DialogTitle>Create Student Account</DialogTitle>
          <p class="text-sm text-muted-foreground">
            Create a student account using your school email domain. The student will receive a setup link to create their own password.
          </p>
        </DialogHeader>
        <div class="mt-6 space-y-5">
          <div class="rounded-2xl border border-sky-200 bg-gradient-to-r from-sky-50 via-white to-cyan-50 px-4 py-4">
            <div class="flex items-start gap-3">
              <div class="rounded-2xl bg-sky-100 p-3 text-sky-700">
                <Sparkles class="h-5 w-5" />
              </div>
              <div>
                <p class="text-sm font-semibold text-slate-950">Smart student setup</p>
                <p class="mt-1 text-sm text-slate-600">
                  Fill in the student name, then choose the email format, course, and year level. The school email is generated automatically for you.
                </p>
              </div>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
          <FormItem class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <FormLabel class="text-slate-700">First Name</FormLabel>
            <FormControl>
              <Input v-model="studentForm.firstName" type="text" placeholder="Juan" class="mt-1 border-slate-200 bg-slate-50/80" />
            </FormControl>
          </FormItem>
          <FormItem class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <FormLabel class="text-slate-700">Last Name</FormLabel>
            <FormControl>
              <Input v-model="studentForm.lastName" placeholder="Dela Cruz" class="mt-1 border-slate-200 bg-slate-50/80" />
            </FormControl>
          </FormItem>
          <FormItem class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <FormLabel class="text-slate-700">Email Format</FormLabel>
            <FormControl>
              <div class="relative mt-1" data-student-dropdown>
                <AtSign class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-sky-600" />
                <button
                  type="button"
                  class="dropdown-trigger w-full rounded-xl border border-slate-200 bg-slate-50/90 py-3 pl-10 pr-10 text-left text-sm font-medium text-slate-800 shadow-sm transition focus:border-sky-300 focus:ring-2 focus:ring-sky-100"
                  @click="toggleDropdown('emailFormat')"
                >
                  <span>{{ selectedEmailFormat.label }} ({{ selectedEmailFormat.example }})</span>
                </button>
                <ChevronDown
                  class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 transition"
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
          <FormItem class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <FormLabel class="text-slate-700">School Email</FormLabel>
            <FormControl>
              <Input
                :model-value="generatedEmail || 'Fill first and last name to generate email'"
                readonly
                class="mt-1 border-sky-200 bg-sky-50/80 font-medium text-sky-900"
              />
            </FormControl>
          </FormItem>
          <FormItem class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <FormLabel class="text-slate-700">Student Number</FormLabel>
            <FormControl>
              <div class="relative mt-1">
                <IdCard class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                <Input v-model="studentForm.studentNumber" placeholder="2024-0001" class="border-slate-200 bg-slate-50/80 pl-10" />
              </div>
            </FormControl>
          </FormItem>
          <FormItem class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <FormLabel class="text-slate-700">Course</FormLabel>
            <FormControl>
              <div class="relative mt-1" data-student-dropdown>
                <BookOpenText class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                <button
                  type="button"
                  class="dropdown-trigger w-full rounded-xl border border-slate-200 bg-slate-50/90 py-3 pl-10 pr-10 text-left text-sm font-medium text-slate-800 shadow-sm transition focus:border-emerald-300 focus:ring-2 focus:ring-emerald-100"
                  @click="toggleDropdown('course')"
                >
                  <span>{{ studentForm.course || 'Select a saved school course' }}</span>
                </button>
                <ChevronDown
                  class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 transition"
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
          <FormItem class="md:col-span-2 rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm">
            <FormLabel class="text-slate-700">Year Level</FormLabel>
            <FormControl>
              <div class="relative mt-1" data-student-dropdown>
                <UserRoundPlus class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-violet-600" />
                <button
                  type="button"
                  class="dropdown-trigger w-full rounded-xl border border-slate-200 bg-slate-50/90 py-3 pl-10 pr-10 text-left text-sm font-medium text-slate-800 shadow-sm transition focus:border-violet-300 focus:ring-2 focus:ring-violet-100"
                  @click="toggleDropdown('yearLevel')"
                >
                  <span>{{ selectedYearLevelLabel }}</span>
                </button>
                <ChevronDown
                  class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 transition"
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
                    v-for="yearLevel in YEAR_LEVEL_OPTIONS"
                    :key="yearLevel"
                    type="button"
                    class="dropdown-option"
                    :class="{ active: studentForm.yearLevelChoice === yearLevel }"
                    @click="selectYearLevel(yearLevel)"
                  >
                    <span class="dropdown-option-title">{{ yearLevel }}</span>
                  </button>
                  <button
                    type="button"
                    class="dropdown-option"
                    :class="{ active: studentForm.yearLevelChoice === CUSTOM_YEAR_LEVEL_VALUE }"
                    @click="selectYearLevel(CUSTOM_YEAR_LEVEL_VALUE)"
                  >
                    <span class="dropdown-option-title">Other</span>
                    <span class="dropdown-option-copy">Use a custom year level</span>
                  </button>
                </div>
              </div>
            </FormControl>
          </FormItem>
          <FormItem
            v-if="isCustomYearLevel"
            class="md:col-span-2 rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm"
          >
            <FormLabel class="text-slate-700">Custom Year Level</FormLabel>
            <FormControl>
              <Input
                :model-value="studentForm.customYearLevel"
                placeholder="e.g., 6th Year, Senior High, Graduate Level"
                class="mt-1 border-slate-200 bg-slate-50/80"
                @update:modelValue="updateCustomYearLevel(String($event))"
              />
            </FormControl>
          </FormItem>
          <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <p><strong>School email domain:</strong> {{ schoolEmailDomain || 'Set your official school email in Settings first' }}</p>
            <p v-if="!schoolCourseOptions.length" class="mt-1">
              Add your school courses in Settings first so admins can pick them here instead of typing manually.
            </p>
          </div>
          <div
            v-if="generatedEmail"
            class="md:col-span-2 rounded-2xl border border-sky-200 bg-gradient-to-r from-sky-50 via-white to-emerald-50 px-4 py-4"
          >
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Preview</p>
                <p class="mt-1 text-lg font-semibold text-slate-950">{{ generatedEmail }}</p>
                <p class="mt-1 text-sm text-slate-600">
                  {{ studentForm.firstName }} {{ studentForm.lastName }} • {{ studentForm.course || 'Choose a course' }} • {{ studentForm.yearLevel || 'Choose year level' }}
                </p>
              </div>
              <div class="rounded-xl bg-white/80 px-4 py-3 text-sm text-slate-600 shadow-sm">
                <p class="font-semibold text-slate-900">Auto-generated</p>
                <p class="mt-1">Uses your school domain and selected name format.</p>
              </div>
            </div>
          </div>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <Button variant="outline" @click="close">Cancel</Button>
          <Button
            :disabled="saving || !studentForm.firstName.trim() || !studentForm.lastName.trim() || !generatedEmail || !studentForm.course || !studentForm.yearLevel"
            @click="createStudent(close)"
          >
            <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" />
            <span>{{ saving ? 'Creating...' : 'Create Account' }}</span>
          </Button>
        </div>
      </template>
    </Dialog>

    <Dialog v-model:open="bulkDialogOpen">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>Import Students</DialogTitle>
          <p class="text-sm text-muted-foreground">
            Paste CSV rows using: `email,name,student number,course,year level`
          </p>
        </DialogHeader>
        <div class="mt-6 space-y-4">
          <Textarea
            v-model="bulkCsv"
            :rows="10"
            placeholder="student1@example.com,Juan Dela Cruz,2024-0001,BS Computer Science,4th Year&#10;student2@example.com,Maria Santos,2024-0002,BS Information Technology,3rd Year"
          />
          <div class="flex justify-end gap-3">
            <Button variant="outline" @click="close">Cancel</Button>
            <Button :disabled="importing" @click="runBulkImport(close)">
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
  border: 1px solid #dbeafe;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.98));
  box-shadow: 0 24px 60px -28px rgba(15, 23, 42, 0.35);
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
}

.dropdown-option:hover {
  background: #eff6ff;
  transform: translateY(-1px);
}

.dropdown-option.active {
  background: linear-gradient(135deg, #dbeafe 0%, #ecfeff 100%);
  color: #1d4ed8;
}

.dropdown-option-title {
  font-size: 0.92rem;
  font-weight: 700;
  color: #0f172a;
}

.dropdown-option.active .dropdown-option-title {
  color: #1d4ed8;
}

.dropdown-option-copy {
  font-size: 0.78rem;
  color: #64748b;
}
</style>
