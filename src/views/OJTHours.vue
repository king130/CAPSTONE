<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import {
  BarChart3,
  BriefcaseBusiness,
  Check,
  Clock3,
  Download,
  FileClock,
  Filter,
  LoaderCircle,
  Pencil,
  ShieldAlert,
  Trash2,
  X,
} from 'lucide-vue-next'
import { useForm } from 'vee-validate'
import { computed, onMounted, ref, watch } from 'vue'
import { z } from 'zod'

import AlertDialog from '@/components/ui/alert-dialog/AlertDialog.vue'
import Avatar from '@/components/ui/avatar/Avatar.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue'
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue'
import FormControl from '@/components/ui/form/FormControl.vue'
import FormField from '@/components/ui/form/FormField.vue'
import FormItem from '@/components/ui/form/FormItem.vue'
import FormLabel from '@/components/ui/form/FormLabel.vue'
import FormMessage from '@/components/ui/form/FormMessage.vue'
import HoverCard from '@/components/ui/hover-card/HoverCard.vue'
import Input from '@/components/ui/input/Input.vue'
import Progress from '@/components/ui/progress/Progress.vue'
import Select from '@/components/ui/select/Select.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Table from '@/components/ui/table/Table.vue'
import TableBody from '@/components/ui/table/TableBody.vue'
import TableCell from '@/components/ui/table/TableCell.vue'
import TableHead from '@/components/ui/table/TableHead.vue'
import TableHeader from '@/components/ui/table/TableHeader.vue'
import TableRow from '@/components/ui/table/TableRow.vue'
import Tabs from '@/components/ui/tabs/Tabs.vue'
import TabsContent from '@/components/ui/tabs/TabsContent.vue'
import TabsList from '@/components/ui/tabs/TabsList.vue'
import TabsTrigger from '@/components/ui/tabs/TabsTrigger.vue'
import Textarea from '@/components/ui/textarea/Textarea.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import {
  approveOJTLog,
  createOJTLog,
  deleteOJTLog,
  exportSchoolOJTLogsCsv,
  fetchAdminOJTAnalytics,
  fetchAdminOJTLogs,
  fetchSchoolOJTSummaries,
  listCompanyOJTLogs,
  listInternOJTLogs,
  rejectOJTLog,
  updateOJTLog,
  type InternOJTSummary,
  type OJTAdminAnalytics,
  type OJTAdminFilters,
  type OJTCreateLogPayload,
  type OJTLog,
  type OJTProgress,
} from '@/services/ojtService'
import { fetchSettings } from '@/services/settings'
import { useAuthStore } from '@/stores/auth'

type WorkspaceRole = 'student' | 'company' | 'school' | 'admin'
type CompanyTab = 'pending' | 'approved' | 'rejected'

interface OJTLogForm {
  date: string
  timeIn: string
  timeOut: string
  tasksDone: string
  mood: '' | 'productive' | 'okay' | 'difficult' | 'great'
}

interface RejectForm {
  reason: string
}

interface ProgressStatCard {
  label: string
  value: string
  toneClass: string
}

interface CompanySummaryCard {
  internId: number
  internName: string
  company: string
  school: string
  approvedHours: number
  requiredHours: number
  status: 'completed' | 'on_track' | 'at_risk' | 'not_started'
  initials: string
  percent: number
}

interface AdminSummaryCard {
  label: string
  value: string
  icon: object
  description: string
}

interface AdminFilterForm {
  school: string
  company: string
  status: string
  semesterYear: string
  dateFrom: string
  dateTo: string
  search: string
}

function todayIsoString() {
  return new Date().toISOString().slice(0, 10)
}

function timeToMinutes(value: string) {
  const [hours = 0, minutes = 0] = value.split(':').map(Number)
  return hours * 60 + minutes
}

function calculateHours(timeIn: string, timeOut: string) {
  return (timeToMinutes(timeOut) - timeToMinutes(timeIn)) / 60
}

function initialsFromName(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

function formatDate(value: string) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Not available'
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatDateTime(value: string) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Not available'
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function formatHours(value: number) {
  return `${value.toFixed(1)} hrs`
}

function formatPercent(value: number) {
  return `${Math.round(value)}%`
}

function progressIndicatorClass(percent: number) {
  if (percent >= 75) return 'bg-emerald-500'
  if (percent >= 50) return 'bg-amber-500'
  return 'bg-red-500'
}

function statusBadgeVariant(status: string) {
  if (status === 'approved' || status === 'completed' || status === 'on_track') return 'success'
  if (status === 'rejected' || status === 'at_risk') return 'destructive'
  if (status === 'pending' || status === 'not_started') return 'warning'
  return 'outline'
}

function statusLabel(status: string) {
  return status
    .split('_')
    .join(' ')
    .split(' ')
    .filter(Boolean)
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
    .join(' ')
}

function roleFromUser(role: string | null | undefined): WorkspaceRole {
  if (role === 'admin' || role === 'company' || role === 'school') return role
  return 'student'
}

function buildProgressFromLogs(logs: OJTLog[], requiredHours: number): OJTProgress {
  const totalLogged = logs.reduce((sum, log) => sum + log.hoursRendered, 0)
  const totalApproved = logs
    .filter((log) => log.status === 'approved')
    .reduce((sum, log) => sum + log.hoursRendered, 0)
  const totalPending = logs
    .filter((log) => log.status === 'pending')
    .reduce((sum, log) => sum + log.hoursRendered, 0)
  const totalRejected = logs
    .filter((log) => log.status === 'rejected')
    .reduce((sum, log) => sum + log.hoursRendered, 0)
  const percentComplete = requiredHours > 0 ? Math.min(100, (totalApproved / requiredHours) * 100) : 0

  return {
    totalLogged,
    totalApproved,
    totalPending,
    totalRejected,
    requiredHours,
    percentComplete,
  }
}

function checkboxChecked(event: Event) {
  return (event.target as HTMLInputElement).checked
}

function setCreateMood(value: string) {
  setCreateFieldValue('mood', value as OJTLogForm['mood'])
}

function setEditMood(value: string) {
  setEditFieldValue('mood', value as OJTLogForm['mood'])
}

const authStore = useAuthStore()
const { success, error } = useToast()

const currentRole = computed<WorkspaceRole>(() => roleFromUser(authStore.user?.role))
const pageTitle = computed(() => 'OJT Hours')
const today = todayIsoString()

const internLoading = ref(true)
const companyLoading = ref(true)
const schoolLoading = ref(true)
const adminLoading = ref(true)
const logSubmitting = ref(false)
const editSubmitting = ref(false)
const rejectSubmitting = ref(false)
const bulkApproveSubmitting = ref(false)
const schoolExporting = ref(false)

const internProgress = ref<OJTProgress>({
  totalLogged: 0,
  totalApproved: 0,
  totalPending: 0,
  totalRejected: 0,
  requiredHours: 0,
  percentComplete: 0,
})
const internLogs = ref<OJTLog[]>([])
const internDateFrom = ref('')
const internDateTo = ref('')
const internHistoryPage = ref(1)
const editDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const selectedLog = ref<OJTLog | null>(null)

const companyTab = ref<CompanyTab>('pending')
const companyRequiredHours = ref(0)
const companyLogsByStatus = ref<Record<CompanyTab, OJTLog[]>>({
  pending: [],
  approved: [],
  rejected: [],
})
const selectedCompanyLogIds = ref<number[]>([])
const rejectDialogOpen = ref(false)
const rejectTargetLog = ref<OJTLog | null>(null)

const schoolSummaries = ref<InternOJTSummary[]>([])
const schoolSearch = ref('')
const schoolDetailDialogOpen = ref(false)
const schoolDetailLoading = ref(false)
const selectedSchoolIntern = ref<InternOJTSummary | null>(null)
const schoolDetailLogs = ref<OJTLog[]>([])

const adminFilterForm = ref<AdminFilterForm>({
  school: '',
  company: '',
  status: '',
  semesterYear: '',
  dateFrom: '',
  dateTo: '',
  search: '',
})
const appliedAdminFilters = ref<AdminFilterForm>({
  school: '',
  company: '',
  status: '',
  semesterYear: '',
  dateFrom: '',
  dateTo: '',
  search: '',
})
const adminLogs = ref<OJTLog[]>([])
const adminAnalytics = ref<OJTAdminAnalytics>({
  hoursPerWeek: [],
  completionBySchool: [],
})
const adminPage = ref(1)
const adminDetailDialogOpen = ref(false)
const selectedAdminLog = ref<OJTLog | null>(null)

const internLogSchema = toTypedSchema(
  z
    .object({
      date: z.string().min(1, 'Date is required.'),
      timeIn: z.string().min(1, 'Time in is required.'),
      timeOut: z.string().min(1, 'Time out is required.'),
      tasksDone: z.string().min(1, 'Tasks done is required.'),
      mood: z.enum(['', 'productive', 'okay', 'difficult', 'great']).optional(),
    })
    .superRefine((values, context) => {
      if (!values.timeIn || !values.timeOut) return
      const hours = calculateHours(values.timeIn, values.timeOut)
      if (hours <= 0) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['timeOut'],
          message: 'Time out must be after time in.',
        })
      }
      if (hours > 12) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['timeOut'],
          message: 'Daily logs cannot exceed 12 hours.',
        })
      }
    }),
)

const rejectSchema = toTypedSchema(
  z.object({
    reason: z.string().min(1, 'A rejection reason is required.'),
  }),
)

const {
  handleSubmit: handleCreateSubmit,
  errors: createErrors,
  setFieldValue: setCreateFieldValue,
  resetForm: resetCreateForm,
} = useForm<OJTLogForm>({
  validationSchema: internLogSchema,
  initialValues: {
    date: today,
    timeIn: '',
    timeOut: '',
    tasksDone: '',
    mood: '',
  },
})

const {
  handleSubmit: handleEditSubmit,
  errors: editErrors,
  setFieldValue: setEditFieldValue,
  resetForm: resetEditForm,
} = useForm<OJTLogForm>({
  validationSchema: internLogSchema,
  initialValues: {
    date: today,
    timeIn: '',
    timeOut: '',
    tasksDone: '',
    mood: '',
  },
})

const {
  handleSubmit: handleRejectSubmit,
  errors: rejectErrors,
  setFieldValue: setRejectFieldValue,
  resetForm: resetRejectForm,
} = useForm<RejectForm>({
  validationSchema: rejectSchema,
  initialValues: {
    reason: '',
  },
})

const hasTodaysLog = computed(() => internLogs.value.some((log) => log.date === today))

const internProgressCards = computed<ProgressStatCard[]>(() => [
  { label: 'Total Logged', value: formatHours(internProgress.value.totalLogged), toneClass: 'text-slate-900' },
  { label: 'Approved Hours', value: formatHours(internProgress.value.totalApproved), toneClass: 'text-emerald-600' },
  { label: 'Pending Hours', value: formatHours(internProgress.value.totalPending), toneClass: 'text-amber-600' },
  { label: 'Rejected Hours', value: formatHours(internProgress.value.totalRejected), toneClass: 'text-red-600' },
])

const filteredInternLogs = computed(() => {
  return internLogs.value.filter((log) => {
    if (internDateFrom.value && log.date < internDateFrom.value) return false
    if (internDateTo.value && log.date > internDateTo.value) return false
    return true
  })
})

const internHistoryPageSize = 6
const paginatedInternLogs = computed(() => {
  const start = (internHistoryPage.value - 1) * internHistoryPageSize
  return filteredInternLogs.value.slice(start, start + internHistoryPageSize)
})
const internHistoryTotalPages = computed(() => Math.max(1, Math.ceil(filteredInternLogs.value.length / internHistoryPageSize)))

const activeCompanyLogs = computed(() => companyLogsByStatus.value[companyTab.value])
const companySummaryCards = computed<CompanySummaryCard[]>(() => {
  const approvedLogs = companyLogsByStatus.value.approved
  const map = new Map<number, CompanySummaryCard>()

  approvedLogs.forEach((log) => {
    const existing = map.get(log.internId)
    const approvedHours = (existing?.approvedHours ?? 0) + log.hoursRendered
    const percent = companyRequiredHours.value ? Math.min(100, (approvedHours / companyRequiredHours.value) * 100) : 0
    const status: CompanySummaryCard['status'] =
      approvedHours >= companyRequiredHours.value && companyRequiredHours.value > 0
        ? 'completed'
        : percent >= 60
          ? 'on_track'
          : approvedHours > 0
            ? 'at_risk'
            : 'not_started'

    map.set(log.internId, {
      internId: log.internId,
      internName: log.internName,
      company: log.company ?? '',
      school: log.school ?? '',
      approvedHours,
      requiredHours: companyRequiredHours.value,
      status,
      initials: initialsFromName(log.internName),
      percent,
    })
  })

  return Array.from(map.values()).sort((left, right) => left.internName.localeCompare(right.internName))
})

const schoolStats = computed(() => {
  const total = schoolSummaries.value.length
  const completed = schoolSummaries.value.filter((item) => item.status === 'completed').length
  const inProgress = schoolSummaries.value.filter((item) => item.status === 'on_track' || item.status === 'at_risk').length
  const notYetStarted = schoolSummaries.value.filter((item) => item.status === 'not_started').length
  return { total, completed, inProgress, notYetStarted }
})

const filteredSchoolSummaries = computed(() => {
  const search = schoolSearch.value.trim().toLowerCase()
  if (!search) return schoolSummaries.value
  return schoolSummaries.value.filter((item) => item.internName.toLowerCase().includes(search) || item.company.toLowerCase().includes(search))
})

const adminFilteredLogs = computed(() => adminLogs.value)
const adminPageSize = 8
const paginatedAdminLogs = computed(() => {
  const start = (adminPage.value - 1) * adminPageSize
  return adminFilteredLogs.value.slice(start, start + adminPageSize)
})
const adminTotalPages = computed(() => Math.max(1, Math.ceil(adminFilteredLogs.value.length / adminPageSize)))

const adminSchoolOptions = computed(() =>
  Array.from(new Set(adminLogs.value.map((log) => log.school).filter((value): value is string => Boolean(value)))).sort(),
)
const adminCompanyOptions = computed(() =>
  Array.from(new Set(adminLogs.value.map((log) => log.company).filter((value): value is string => Boolean(value)))).sort(),
)
const maxAdminHoursPoint = computed(() =>
  Math.max(...adminAnalytics.value.hoursPerWeek.map((item) => item.hours), 1),
)

const adminSummaryCards = computed<AdminSummaryCard[]>(() => {
  const totalLogs = adminLogs.value.length
  const totalApprovedHours = adminLogs.value.filter((log) => log.status === 'approved').reduce((sum, log) => sum + log.hoursRendered, 0)
  const completedInterns = new Set(adminLogs.value.filter((log) => log.status === 'approved').map((log) => log.internId)).size
  const pendingLogs = adminLogs.value.filter((log) => log.status === 'pending').length

  return [
    { label: 'Total Logs Submitted', value: String(totalLogs), icon: FileClock, description: 'Entries currently returned by the active filters.' },
    { label: 'Total Hours Approved', value: formatHours(totalApprovedHours), icon: Clock3, description: 'Approved work hours across all visible records.' },
    { label: 'Completed OJT Interns', value: String(completedInterns), icon: Check, description: 'Interns with approved progress in the filtered result set.' },
    { label: 'Pending Logs Count', value: String(pendingLogs), icon: ShieldAlert, description: 'Logs still waiting for a company decision.' },
  ]
})

function openEditDialog(log: OJTLog) {
  selectedLog.value = log
  resetEditForm({
    values: {
      date: log.date,
      timeIn: log.timeIn,
      timeOut: log.timeOut,
      tasksDone: log.tasksDone,
      mood: log.mood ?? '',
    },
  })
  editDialogOpen.value = true
}

function openDeleteDialog(log: OJTLog) {
  selectedLog.value = log
  deleteDialogOpen.value = true
}

function openRejectDialog(log: OJTLog) {
  rejectTargetLog.value = log
  resetRejectForm({
    values: {
      reason: '',
    },
  })
  rejectDialogOpen.value = true
}

function applyAdminFiltersPayload(filters: AdminFilterForm): OJTAdminFilters {
  return {
    school: filters.school || undefined,
    company: filters.company || undefined,
    status: filters.status || undefined,
    semesterYear: filters.semesterYear || undefined,
    dateFrom: filters.dateFrom || undefined,
    dateTo: filters.dateTo || undefined,
    search: filters.search || undefined,
  }
}

async function loadInternData() {
  internLoading.value = true
  try {
    const [logs, settings] = await Promise.all([
      listInternOJTLogs(),
      fetchSettings().catch(() => null),
    ])
    const sortedLogs = [...logs].sort((left, right) => right.date.localeCompare(left.date))
    const requiredHours = Number(settings?.systemSettings?.requiredHours ?? 0)
    internLogs.value = sortedLogs
    internProgress.value = buildProgressFromLogs(sortedLogs, requiredHours)
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to load your OJT hours right now.' })
  } finally {
    internLoading.value = false
  }
}

async function loadCompanyData() {
  companyLoading.value = true
  try {
    const [pending, approved, rejected, settings] = await Promise.all([
      listCompanyOJTLogs('pending'),
      listCompanyOJTLogs('approved'),
      listCompanyOJTLogs('rejected'),
      fetchSettings().catch(() => null),
    ])
    companyLogsByStatus.value = { pending, approved, rejected }
    companyRequiredHours.value = Number(settings?.systemSettings?.requiredHours ?? 0)
    selectedCompanyLogIds.value = selectedCompanyLogIds.value.filter((logId) => pending.some((log) => log.id === logId))
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to load company OJT approvals.' })
  } finally {
    companyLoading.value = false
  }
}

async function loadSchoolData() {
  schoolLoading.value = true
  try {
    schoolSummaries.value = await fetchSchoolOJTSummaries()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to load school OJT progress.' })
  } finally {
    schoolLoading.value = false
  }
}

async function loadAdminData() {
  adminLoading.value = true
  try {
    const filters = applyAdminFiltersPayload(appliedAdminFilters.value)
    const [logs, analytics] = await Promise.all([fetchAdminOJTLogs(filters), fetchAdminOJTAnalytics()])
    adminLogs.value = logs
    adminAnalytics.value = analytics
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to load admin OJT analytics.' })
  } finally {
    adminLoading.value = false
  }
}

async function refreshCurrentRoleData() {
  if (currentRole.value === 'student') return loadInternData()
  if (currentRole.value === 'company') return loadCompanyData()
  if (currentRole.value === 'school') return loadSchoolData()
  return loadAdminData()
}

const submitNewLog = handleCreateSubmit(async (values) => {
  logSubmitting.value = true
  try {
    const payload: OJTCreateLogPayload = {
      date: values.date,
      timeIn: values.timeIn,
      timeOut: values.timeOut,
      tasksDone: values.tasksDone,
      mood: values.mood || undefined,
    }
    await createOJTLog(payload)
    success('Hours logged successfully')
    resetCreateForm({
      values: {
        date: today,
        timeIn: '',
        timeOut: '',
        tasksDone: '',
        mood: '',
      },
    })
    await loadInternData()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to submit your OJT log.' })
  } finally {
    logSubmitting.value = false
  }
})

const submitEditedLog = handleEditSubmit(async (values) => {
  if (!selectedLog.value) return
  editSubmitting.value = true
  try {
    await updateOJTLog(selectedLog.value.id, {
      date: values.date,
      timeIn: values.timeIn,
      timeOut: values.timeOut,
      tasksDone: values.tasksDone,
      mood: values.mood || undefined,
    })
    success('Log updated successfully')
    editDialogOpen.value = false
    await loadInternData()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to update the selected log.' })
  } finally {
    editSubmitting.value = false
  }
})

async function confirmDeleteLog() {
  if (!selectedLog.value) return
  try {
    await deleteOJTLog(selectedLog.value.id)
    success('Pending log deleted successfully')
    deleteDialogOpen.value = false
    selectedLog.value = null
    await loadInternData()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to delete that OJT log.' })
  }
}

async function approveSingleLog(logId: number) {
  try {
    await approveOJTLog(logId)
    success('Log approved successfully')
    await loadCompanyData()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to approve the selected log.' })
  }
}

const submitRejectReason = handleRejectSubmit(async (values) => {
  if (!rejectTargetLog.value) return
  rejectSubmitting.value = true
  try {
    await rejectOJTLog(rejectTargetLog.value.id, { reason: values.reason })
    success('Log rejected successfully')
    rejectDialogOpen.value = false
    rejectTargetLog.value = null
    await loadCompanyData()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to reject the selected log.' })
  } finally {
    rejectSubmitting.value = false
  }
})

async function approveSelectedLogs() {
  if (!selectedCompanyLogIds.value.length) return
  bulkApproveSubmitting.value = true
  try {
    await Promise.all(selectedCompanyLogIds.value.map((logId) => approveOJTLog(logId)))
    success('Selected logs approved successfully')
    selectedCompanyLogIds.value = []
    await loadCompanyData()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to approve the selected logs.' })
  } finally {
    bulkApproveSubmitting.value = false
  }
}

function toggleCompanySelection(logId: number, checked: boolean) {
  if (checked) {
    if (!selectedCompanyLogIds.value.includes(logId)) {
      selectedCompanyLogIds.value = [...selectedCompanyLogIds.value, logId]
    }
    return
  }
  selectedCompanyLogIds.value = selectedCompanyLogIds.value.filter((item) => item !== logId)
}

function toggleSelectAllCompanyLogs(checked: boolean) {
  selectedCompanyLogIds.value = checked ? activeCompanyLogs.value.map((log) => log.id) : []
}

async function openSchoolInternDetail(summary: InternOJTSummary) {
  selectedSchoolIntern.value = summary
  schoolDetailDialogOpen.value = true
  schoolDetailLoading.value = true
  try {
    schoolDetailLogs.value = await listInternOJTLogs({ internId: summary.internId })
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to load that intern log history.' })
  } finally {
    schoolDetailLoading.value = false
  }
}

async function exportSchoolCsv() {
  schoolExporting.value = true
  try {
    const blob = await exportSchoolOJTLogsCsv()
    const objectUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = objectUrl
    link.download = `school-ojt-progress-${today}.csv`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(objectUrl)
    success('CSV exported successfully')
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to export the school OJT report.' })
  } finally {
    schoolExporting.value = false
  }
}

function applyAdminFilters() {
  appliedAdminFilters.value = { ...adminFilterForm.value }
  adminPage.value = 1
  void loadAdminData()
}

function resetAdminFilters() {
  adminFilterForm.value = {
    school: '',
    company: '',
    status: '',
    semesterYear: '',
    dateFrom: '',
    dateTo: '',
    search: '',
  }
  appliedAdminFilters.value = { ...adminFilterForm.value }
  adminPage.value = 1
  void loadAdminData()
}

function openAdminLogDetail(log: OJTLog) {
  selectedAdminLog.value = log
  adminDetailDialogOpen.value = true
}

watch(filteredInternLogs, () => {
  if (internHistoryPage.value > internHistoryTotalPages.value) {
    internHistoryPage.value = internHistoryTotalPages.value
  }
})

watch(adminFilteredLogs, () => {
  if (adminPage.value > adminTotalPages.value) {
    adminPage.value = adminTotalPages.value
  }
})

onMounted(() => {
  resetCreateForm({
    values: {
      date: today,
      timeIn: '',
      timeOut: '',
      tasksDone: '',
      mood: '',
    },
  })
  void refreshCurrentRoleData()
})
</script>

<template>
  <MainLayout :role="currentRole" :title="pageTitle" active-item="ojt-hours">
    <div class="space-y-6">
      <template v-if="currentRole === 'student'">
        <Card class="border-border/80 shadow-sm">
          <CardHeader>
            <h2 class="text-2xl font-semibold text-foreground">Log Today's Hours</h2>
            <p class="text-sm text-muted-foreground">
              Add your work hours, tasks completed, and a quick reflection for today.
            </p>
          </CardHeader>
          <CardContent>
            <div v-if="internLoading" class="grid gap-4 md:grid-cols-2">
              <Skeleton class="h-24 rounded-xl md:col-span-2" />
              <Skeleton class="h-24 rounded-xl" />
              <Skeleton class="h-24 rounded-xl" />
              <Skeleton class="h-28 rounded-xl md:col-span-2" />
              <Skeleton class="h-24 rounded-xl" />
              <Skeleton class="h-11 rounded-xl md:col-span-2" />
            </div>

            <form v-else class="grid gap-4 md:grid-cols-2" @submit="submitNewLog">
              <FormField v-slot="{ componentField, errorMessage }" name="date">
                <FormItem>
                  <FormLabel for="ojtDate">Date</FormLabel>
                  <FormControl>
                    <Input
                      id="ojtDate"
                      type="date"
                      :disabled="hasTodaysLog"
                      v-bind="componentField"
                      @update:modelValue="setCreateFieldValue('date', $event)"
                    />
                  </FormControl>
                  <p v-if="hasTodaysLog" class="text-sm text-muted-foreground">
                    You already submitted a log for today.
                  </p>
                  <FormMessage :message="errorMessage || createErrors.date" />
                </FormItem>
              </FormField>

              <FormField v-slot="{ componentField, errorMessage }" name="mood">
                <FormItem>
                  <FormLabel for="ojtMood">Mood / Reflection</FormLabel>
                  <FormControl>
                    <Select
                      id="ojtMood"
                      v-bind="componentField"
                      @update:modelValue="setCreateMood($event)"
                    >
                      <option value="">Select mood</option>
                      <option value="productive">Productive</option>
                      <option value="okay">Okay</option>
                      <option value="difficult">Difficult</option>
                      <option value="great">Great</option>
                    </Select>
                  </FormControl>
                  <FormMessage :message="errorMessage || createErrors.mood" />
                </FormItem>
              </FormField>

              <FormField v-slot="{ componentField, errorMessage }" name="timeIn">
                <FormItem>
                  <FormLabel for="timeIn">Time In</FormLabel>
                  <FormControl>
                    <Input
                      id="timeIn"
                      type="time"
                      v-bind="componentField"
                      @update:modelValue="setCreateFieldValue('timeIn', $event)"
                    />
                  </FormControl>
                  <FormMessage :message="errorMessage || createErrors.timeIn" />
                </FormItem>
              </FormField>

              <FormField v-slot="{ componentField, errorMessage }" name="timeOut">
                <FormItem>
                  <FormLabel for="timeOut">Time Out</FormLabel>
                  <FormControl>
                    <Input
                      id="timeOut"
                      type="time"
                      v-bind="componentField"
                      @update:modelValue="setCreateFieldValue('timeOut', $event)"
                    />
                  </FormControl>
                  <FormMessage :message="errorMessage || createErrors.timeOut" />
                </FormItem>
              </FormField>

              <FormField v-slot="{ componentField, errorMessage }" name="tasksDone">
                <FormItem class="md:col-span-2">
                  <FormLabel for="tasksDone">Tasks Done</FormLabel>
                  <FormControl>
                    <Textarea
                      id="tasksDone"
                      :model-value="String(componentField.modelValue ?? '')"
                      :rows="5"
                      placeholder="Summarize what you worked on today"
                      @update:modelValue="setCreateFieldValue('tasksDone', $event)"
                    />
                  </FormControl>
                  <FormMessage :message="errorMessage || createErrors.tasksDone" />
                </FormItem>
              </FormField>

              <div class="md:col-span-2">
                <Button type="submit" class="w-full" :disabled="logSubmitting || hasTodaysLog">
                  <LoaderCircle v-if="logSubmitting" class="h-4 w-4 animate-spin" />
                  <span>{{ logSubmitting ? 'Submitting...' : 'Submit Log' }}</span>
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <Card class="border-border/80 shadow-sm">
          <CardHeader>
            <h2 class="text-2xl font-semibold text-foreground">Progress Overview</h2>
            <p class="text-sm text-muted-foreground">
              Track your completed hours against the required OJT target.
            </p>
          </CardHeader>
          <CardContent class="space-y-5">
            <div v-if="internLoading" class="space-y-4">
              <Skeleton class="h-6 w-56" />
              <Skeleton class="h-4 w-full rounded-full" />
              <div class="grid gap-4 md:grid-cols-4">
                <Skeleton v-for="index in 4" :key="index" class="h-24 rounded-xl" />
              </div>
            </div>

            <template v-else>
              <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-lg font-semibold">
                  {{ internProgress.totalApproved }} of {{ internProgress.requiredHours }} hours completed
                  ({{ formatPercent(internProgress.percentComplete) }})
                </p>
                <Badge :variant="internProgress.percentComplete >= 75 ? 'success' : internProgress.percentComplete >= 50 ? 'warning' : 'destructive'">
                  {{ internProgress.percentComplete >= 100 ? 'Completed' : 'In Progress' }}
                </Badge>
              </div>
              <Progress
                :value="internProgress.percentComplete"
                :indicator-class="progressIndicatorClass(internProgress.percentComplete)"
                class="h-4"
              />

              <div class="grid gap-4 md:grid-cols-4">
                <Card v-for="card in internProgressCards" :key="card.label" class="border-border/70">
                  <CardContent class="space-y-2 p-4">
                    <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                    <p class="text-2xl font-semibold" :class="card.toneClass">{{ card.value }}</p>
                  </CardContent>
                </Card>
              </div>
            </template>
          </CardContent>
        </Card>

        <Card class="border-border/80 shadow-sm">
          <CardHeader class="gap-4">
            <div>
              <h2 class="text-2xl font-semibold text-foreground">Log History</h2>
              <p class="text-sm text-muted-foreground">
                Review submitted logs, update pending entries, and remove drafts that should not be sent.
              </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <Input v-model="internDateFrom" type="date" />
              <Input v-model="internDateTo" type="date" />
            </div>
          </CardHeader>
          <CardContent>
            <div v-if="internLoading" class="space-y-3">
              <Skeleton v-for="index in 6" :key="index" class="h-14 rounded-xl" />
            </div>

            <div v-else class="overflow-hidden rounded-xl border border-border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Date</TableHead>
                    <TableHead>Time In</TableHead>
                    <TableHead>Time Out</TableHead>
                    <TableHead>Hours</TableHead>
                    <TableHead>Tasks</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="log in paginatedInternLogs" :key="log.id">
                    <TableCell class="font-medium">{{ formatDate(log.date) }}</TableCell>
                    <TableCell>{{ log.timeIn }}</TableCell>
                    <TableCell>{{ log.timeOut }}</TableCell>
                    <TableCell>{{ formatHours(log.hoursRendered) }}</TableCell>
                    <TableCell class="max-w-xs truncate">{{ log.tasksDone }}</TableCell>
                    <TableCell>
                      <HoverCard v-if="log.status === 'rejected' && log.rejectionReason">
                        <template #trigger>
                          <Badge :variant="statusBadgeVariant(log.status)">{{ statusLabel(log.status) }}</Badge>
                        </template>
                        <div class="space-y-1">
                          <p class="font-semibold">Rejection reason</p>
                          <p class="text-muted-foreground">{{ log.rejectionReason }}</p>
                        </div>
                      </HoverCard>
                      <Badge v-else :variant="statusBadgeVariant(log.status)">{{ statusLabel(log.status) }}</Badge>
                    </TableCell>
                    <TableCell>
                      <div class="flex gap-2">
                        <Button v-if="log.status === 'pending'" variant="ghost" size="sm" @click="openEditDialog(log)">
                          <Pencil class="h-4 w-4" />
                          Edit
                        </Button>
                        <Button
                          v-if="log.status === 'pending'"
                          variant="ghost"
                          size="sm"
                          class="text-red-600 hover:text-red-600"
                          @click="openDeleteDialog(log)"
                        >
                          <Trash2 class="h-4 w-4" />
                          Delete
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                  <TableRow v-if="!paginatedInternLogs.length">
                    <TableCell colspan="7" class="py-10 text-center text-muted-foreground">
                      No OJT logs match the selected date range.
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>

            <div class="mt-4 flex items-center justify-between text-sm text-muted-foreground">
              <p>Page {{ internHistoryPage }} of {{ internHistoryTotalPages }}</p>
              <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="internHistoryPage <= 1" @click="internHistoryPage -= 1">
                  Previous
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  :disabled="internHistoryPage >= internHistoryTotalPages"
                  @click="internHistoryPage += 1"
                >
                  Next
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </template>

      <template v-else-if="currentRole === 'company'">
        <Card class="border-border/80 shadow-sm">
          <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h2 class="text-2xl font-semibold text-foreground">Company Log Approvals</h2>
              <p class="text-sm text-muted-foreground">
                Review incoming intern logs, act on pending submissions, and monitor approved progress.
              </p>
            </div>
            <Button
              class="w-full lg:w-auto"
              :disabled="!selectedCompanyLogIds.length || bulkApproveSubmitting || companyTab !== 'pending'"
              @click="approveSelectedLogs"
            >
              <LoaderCircle v-if="bulkApproveSubmitting" class="h-4 w-4 animate-spin" />
              <span>Approve Selected</span>
            </Button>
          </CardHeader>
          <CardContent>
            <Tabs v-model="companyTab">
              <TabsList class="grid w-full grid-cols-3">
                <TabsTrigger value="pending">Pending</TabsTrigger>
                <TabsTrigger value="approved">Approved</TabsTrigger>
                <TabsTrigger value="rejected">Rejected</TabsTrigger>
              </TabsList>

              <TabsContent value="pending">
                <div v-if="companyLoading" class="space-y-3">
                  <Skeleton v-for="index in 5" :key="index" class="h-14 rounded-xl" />
                </div>
                <div v-else class="overflow-hidden rounded-xl border border-border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead class="w-12">
                          <input
                            type="checkbox"
                            :checked="activeCompanyLogs.length > 0 && selectedCompanyLogIds.length === activeCompanyLogs.length"
                            @change="toggleSelectAllCompanyLogs(checkboxChecked($event))"
                          />
                        </TableHead>
                        <TableHead>Intern Name</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead>Time In</TableHead>
                        <TableHead>Time Out</TableHead>
                        <TableHead>Hours</TableHead>
                        <TableHead>Tasks Done</TableHead>
                        <TableHead>Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      <TableRow v-for="log in activeCompanyLogs" :key="log.id">
                        <TableCell>
                          <input
                            type="checkbox"
                            :checked="selectedCompanyLogIds.includes(log.id)"
                            @change="toggleCompanySelection(log.id, checkboxChecked($event))"
                          />
                        </TableCell>
                        <TableCell class="font-medium">{{ log.internName }}</TableCell>
                        <TableCell>{{ formatDate(log.date) }}</TableCell>
                        <TableCell>{{ log.timeIn }}</TableCell>
                        <TableCell>{{ log.timeOut }}</TableCell>
                        <TableCell>{{ formatHours(log.hoursRendered) }}</TableCell>
                        <TableCell class="max-w-xs truncate">{{ log.tasksDone }}</TableCell>
                        <TableCell>
                          <div class="flex gap-2">
                            <Button size="sm" @click="approveSingleLog(log.id)">
                              <Check class="h-4 w-4" />
                              Approve
                            </Button>
                            <Button size="sm" variant="destructive" @click="openRejectDialog(log)">
                              <X class="h-4 w-4" />
                              Reject
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                      <TableRow v-if="!activeCompanyLogs.length">
                        <TableCell colspan="8" class="py-10 text-center text-muted-foreground">
                          No pending OJT logs are waiting for review.
                        </TableCell>
                      </TableRow>
                    </TableBody>
                  </Table>
                </div>
              </TabsContent>

              <TabsContent value="approved">
                <div v-if="companyLoading" class="space-y-3">
                  <Skeleton v-for="index in 4" :key="index" class="h-14 rounded-xl" />
                </div>
                <div v-else class="overflow-hidden rounded-xl border border-border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Intern Name</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead>Hours</TableHead>
                        <TableHead>Tasks Done</TableHead>
                        <TableHead>Status</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      <TableRow v-for="log in activeCompanyLogs" :key="log.id">
                        <TableCell class="font-medium">{{ log.internName }}</TableCell>
                        <TableCell>{{ formatDate(log.date) }}</TableCell>
                        <TableCell>{{ formatHours(log.hoursRendered) }}</TableCell>
                        <TableCell class="max-w-xs truncate">{{ log.tasksDone }}</TableCell>
                        <TableCell>
                          <Badge :variant="statusBadgeVariant(log.status)">{{ statusLabel(log.status) }}</Badge>
                        </TableCell>
                      </TableRow>
                      <TableRow v-if="!activeCompanyLogs.length">
                        <TableCell colspan="5" class="py-10 text-center text-muted-foreground">
                          No approved logs yet.
                        </TableCell>
                      </TableRow>
                    </TableBody>
                  </Table>
                </div>
              </TabsContent>

              <TabsContent value="rejected">
                <div v-if="companyLoading" class="space-y-3">
                  <Skeleton v-for="index in 4" :key="index" class="h-14 rounded-xl" />
                </div>
                <div v-else class="overflow-hidden rounded-xl border border-border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Intern Name</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead>Hours</TableHead>
                        <TableHead>Tasks Done</TableHead>
                        <TableHead>Reason</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      <TableRow v-for="log in activeCompanyLogs" :key="log.id">
                        <TableCell class="font-medium">{{ log.internName }}</TableCell>
                        <TableCell>{{ formatDate(log.date) }}</TableCell>
                        <TableCell>{{ formatHours(log.hoursRendered) }}</TableCell>
                        <TableCell class="max-w-xs truncate">{{ log.tasksDone }}</TableCell>
                        <TableCell class="max-w-xs text-sm text-muted-foreground">
                          {{ log.rejectionReason || 'No reason provided.' }}
                        </TableCell>
                      </TableRow>
                      <TableRow v-if="!activeCompanyLogs.length">
                        <TableCell colspan="5" class="py-10 text-center text-muted-foreground">
                          No rejected logs yet.
                        </TableCell>
                      </TableRow>
                    </TableBody>
                  </Table>
                </div>
              </TabsContent>
            </Tabs>
          </CardContent>
        </Card>

        <Card class="border-border/80 shadow-sm">
          <CardHeader>
            <h2 class="text-2xl font-semibold text-foreground">Intern Summary</h2>
            <p class="text-sm text-muted-foreground">
              Use approved hours to spot interns who are on track, need attention, or already completed OJT.
            </p>
          </CardHeader>
          <CardContent>
            <div v-if="companyLoading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <Skeleton v-for="index in 6" :key="index" class="h-40 rounded-xl" />
            </div>
            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <Card v-for="summary in companySummaryCards" :key="summary.internId" class="border-border/70">
                <CardContent class="space-y-4 p-5">
                  <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                      <Avatar :fallback="summary.initials" :alt="summary.internName" />
                      <div>
                        <p class="font-semibold">{{ summary.internName }}</p>
                        <p class="text-sm text-muted-foreground">{{ summary.school || 'School not available' }}</p>
                      </div>
                    </div>
                    <Badge :variant="statusBadgeVariant(summary.status)">{{ statusLabel(summary.status) }}</Badge>
                  </div>
                  <Progress :value="summary.percent" :indicator-class="progressIndicatorClass(summary.percent)" />
                  <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{{ formatHours(summary.approvedHours) }} approved</span>
                    <span>{{ summary.requiredHours }} required</span>
                  </div>
                </CardContent>
              </Card>
              <Card v-if="!companySummaryCards.length" class="border-dashed">
                <CardContent class="flex min-h-40 items-center justify-center p-6 text-center text-muted-foreground">
                  Intern progress cards will appear after approved OJT logs start coming in.
                </CardContent>
              </Card>
            </div>
          </CardContent>
        </Card>
      </template>

      <template v-else-if="currentRole === 'school'">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <Card class="border-border/80 shadow-sm">
            <CardContent class="space-y-2 p-5">
              <p class="text-sm text-muted-foreground">Total Interns</p>
              <Skeleton v-if="schoolLoading" class="h-8 w-20" />
              <p v-else class="text-3xl font-semibold">{{ schoolStats.total }}</p>
            </CardContent>
          </Card>
          <Card class="border-border/80 shadow-sm">
            <CardContent class="space-y-2 p-5">
              <p class="text-sm text-muted-foreground">Completed OJT</p>
              <Skeleton v-if="schoolLoading" class="h-8 w-20" />
              <p v-else class="text-3xl font-semibold text-emerald-600">{{ schoolStats.completed }}</p>
            </CardContent>
          </Card>
          <Card class="border-border/80 shadow-sm">
            <CardContent class="space-y-2 p-5">
              <p class="text-sm text-muted-foreground">In Progress</p>
              <Skeleton v-if="schoolLoading" class="h-8 w-20" />
              <p v-else class="text-3xl font-semibold text-amber-600">{{ schoolStats.inProgress }}</p>
            </CardContent>
          </Card>
          <Card class="border-border/80 shadow-sm">
            <CardContent class="space-y-2 p-5">
              <p class="text-sm text-muted-foreground">Not Yet Started</p>
              <Skeleton v-if="schoolLoading" class="h-8 w-20" />
              <p v-else class="text-3xl font-semibold text-red-600">{{ schoolStats.notYetStarted }}</p>
            </CardContent>
          </Card>
        </div>

        <Card class="border-border/80 shadow-sm">
          <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h2 class="text-2xl font-semibold text-foreground">Interns Progress</h2>
              <p class="text-sm text-muted-foreground">
                Search by intern or company, then drill into each intern's read-only OJT history.
              </p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
              <Input v-model="schoolSearch" placeholder="Search intern or company" class="sm:w-72" />
              <Button variant="outline" :disabled="schoolExporting" @click="exportSchoolCsv">
                <LoaderCircle v-if="schoolExporting" class="h-4 w-4 animate-spin" />
                <Download v-else class="h-4 w-4" />
                <span>Export CSV</span>
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div v-if="schoolLoading" class="space-y-3">
              <Skeleton v-for="index in 6" :key="index" class="h-14 rounded-xl" />
            </div>
            <div v-else class="overflow-hidden rounded-xl border border-border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Intern Name</TableHead>
                    <TableHead>Company</TableHead>
                    <TableHead>Total Logged</TableHead>
                    <TableHead>Approved Hours</TableHead>
                    <TableHead>Required Hours</TableHead>
                    <TableHead>Progress</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="summary in filteredSchoolSummaries" :key="summary.internId">
                    <TableCell>
                      <button class="font-medium text-primary hover:underline" @click="openSchoolInternDetail(summary)">
                        {{ summary.internName }}
                      </button>
                    </TableCell>
                    <TableCell>{{ summary.company || 'Not assigned' }}</TableCell>
                    <TableCell>{{ formatHours(summary.totalLoggedHours ?? summary.approvedHours) }}</TableCell>
                    <TableCell>{{ formatHours(summary.approvedHours) }}</TableCell>
                    <TableCell>{{ summary.requiredHours }}</TableCell>
                    <TableCell class="min-w-48">
                      <div class="space-y-2">
                        <Progress
                          :value="summary.requiredHours ? (summary.approvedHours / summary.requiredHours) * 100 : 0"
                          :indicator-class="progressIndicatorClass(summary.requiredHours ? (summary.approvedHours / summary.requiredHours) * 100 : 0)"
                        />
                        <p class="text-xs text-muted-foreground">
                          {{ formatPercent(summary.requiredHours ? (summary.approvedHours / summary.requiredHours) * 100 : 0) }}
                        </p>
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge :variant="statusBadgeVariant(summary.status)">{{ statusLabel(summary.status) }}</Badge>
                    </TableCell>
                  </TableRow>
                  <TableRow v-if="!filteredSchoolSummaries.length">
                    <TableCell colspan="7" class="py-10 text-center text-muted-foreground">
                      No interns match the current school OJT search.
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </template>

      <template v-else>
        <Card class="border-border/80 shadow-sm">
          <CardHeader class="flex flex-col gap-4">
            <div>
              <h2 class="text-2xl font-semibold text-foreground">Filters</h2>
              <p class="text-sm text-muted-foreground">
                Refine the system-wide OJT log feed by organization, status, term, date, or search keyword.
              </p>
            </div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
              <Select v-model="adminFilterForm.school">
                <option value="">All schools</option>
                <option v-for="school in adminSchoolOptions" :key="school" :value="school">{{ school }}</option>
              </Select>
              <Select v-model="adminFilterForm.company">
                <option value="">All companies</option>
                <option v-for="company in adminCompanyOptions" :key="company" :value="company">{{ company }}</option>
              </Select>
              <Select v-model="adminFilterForm.status">
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
              </Select>
              <Input v-model="adminFilterForm.semesterYear" placeholder="Semester / Year" />
              <Input v-model="adminFilterForm.dateFrom" type="date" />
              <Input v-model="adminFilterForm.dateTo" type="date" />
              <Input v-model="adminFilterForm.search" placeholder="Search intern, school, or company" class="md:col-span-2" />
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
              <Button @click="applyAdminFilters">
                <Filter class="h-4 w-4" />
                Apply Filters
              </Button>
              <Button variant="ghost" @click="resetAdminFilters">Reset</Button>
            </div>
          </CardHeader>
        </Card>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <Card v-for="card in adminSummaryCards" :key="card.label" class="border-border/80 shadow-sm">
            <CardContent class="space-y-4 p-5">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                  <Skeleton v-if="adminLoading" class="mt-2 h-8 w-24" />
                  <p v-else class="mt-2 text-3xl font-semibold">{{ card.value }}</p>
                </div>
                <div class="rounded-xl bg-primary/10 p-3 text-primary">
                  <component :is="card.icon" class="h-5 w-5" />
                </div>
              </div>
              <p class="text-sm text-muted-foreground">{{ card.description }}</p>
            </CardContent>
          </Card>
        </div>

        <Card class="border-border/80 shadow-sm">
          <CardHeader>
            <h2 class="text-2xl font-semibold text-foreground">Master Logs</h2>
            <p class="text-sm text-muted-foreground">
              Browse submitted OJT logs across the system and open any row for full detail.
            </p>
          </CardHeader>
          <CardContent>
            <div v-if="adminLoading" class="space-y-3">
              <Skeleton v-for="index in 6" :key="index" class="h-14 rounded-xl" />
            </div>
            <div v-else class="overflow-hidden rounded-xl border border-border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Intern</TableHead>
                    <TableHead>School</TableHead>
                    <TableHead>Company</TableHead>
                    <TableHead>Date</TableHead>
                    <TableHead>Hours</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Submitted At</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="log in paginatedAdminLogs" :key="log.id" class="cursor-pointer" @click="openAdminLogDetail(log)">
                    <TableCell class="font-medium">{{ log.internName }}</TableCell>
                    <TableCell>{{ log.school || 'Not available' }}</TableCell>
                    <TableCell>{{ log.company || 'Not available' }}</TableCell>
                    <TableCell>{{ formatDate(log.date) }}</TableCell>
                    <TableCell>{{ formatHours(log.hoursRendered) }}</TableCell>
                    <TableCell>
                      <Badge :variant="statusBadgeVariant(log.status)">{{ statusLabel(log.status) }}</Badge>
                    </TableCell>
                    <TableCell>{{ formatDateTime(log.submittedAt || log.createdAt) }}</TableCell>
                  </TableRow>
                  <TableRow v-if="!paginatedAdminLogs.length">
                    <TableCell colspan="7" class="py-10 text-center text-muted-foreground">
                      No OJT logs match the current admin filters.
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>

            <div class="mt-4 flex items-center justify-between text-sm text-muted-foreground">
              <p>Page {{ adminPage }} of {{ adminTotalPages }}</p>
              <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="adminPage <= 1" @click="adminPage -= 1">Previous</Button>
                <Button variant="outline" size="sm" :disabled="adminPage >= adminTotalPages" @click="adminPage += 1">Next</Button>
              </div>
            </div>
          </CardContent>
        </Card>

        <div class="grid gap-6 xl:grid-cols-2">
          <Card class="border-border/80 shadow-sm">
            <CardHeader>
              <div class="flex items-center gap-2">
                <BarChart3 class="h-5 w-5 text-primary" />
                <h2 class="text-xl font-semibold text-foreground">Hours Logged Per Week</h2>
              </div>
            </CardHeader>
            <CardContent>
              <div v-if="adminLoading" class="space-y-3">
                <Skeleton v-for="index in 5" :key="index" class="h-10 rounded-xl" />
              </div>
              <div v-else class="space-y-4">
                <div v-for="point in adminAnalytics.hoursPerWeek" :key="point.label" class="space-y-2">
                  <div class="flex items-center justify-between text-sm">
                    <span>{{ point.label }}</span>
                    <span class="font-medium">{{ formatHours(point.hours) }}</span>
                  </div>
                  <div class="h-3 overflow-hidden rounded-full bg-slate-200">
                    <div
                      class="h-full rounded-full bg-primary"
                      :style="{ width: `${Math.min(100, adminAnalytics.hoursPerWeek.length ? (point.hours / maxAdminHoursPoint) * 100 : 0)}%` }"
                    />
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="border-border/80 shadow-sm">
            <CardHeader>
              <div class="flex items-center gap-2">
                <BriefcaseBusiness class="h-5 w-5 text-primary" />
                <h2 class="text-xl font-semibold text-foreground">OJT Completion By School</h2>
              </div>
            </CardHeader>
            <CardContent>
              <div v-if="adminLoading" class="space-y-3">
                <Skeleton v-for="index in 5" :key="index" class="h-10 rounded-xl" />
              </div>
              <div v-else class="space-y-4">
                <div v-for="point in adminAnalytics.completionBySchool" :key="point.school" class="space-y-2">
                  <div class="flex items-center justify-between text-sm">
                    <span>{{ point.school }}</span>
                    <span class="font-medium">{{ formatPercent(point.completionRate) }}</span>
                  </div>
                  <div class="h-3 overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${Math.min(100, point.completionRate)}%` }" />
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </template>
      <Dialog v-model:open="editDialogOpen">
        <template #default="{ close }">
          <DialogHeader>
            <DialogTitle>Edit Pending Log</DialogTitle>
            <p class="text-sm text-muted-foreground">
              Update the details before the company reviews this pending submission.
            </p>
          </DialogHeader>
          <form class="mt-6 grid gap-4 md:grid-cols-2" @submit="submitEditedLog">
            <FormField v-slot="{ componentField, errorMessage }" name="date">
              <FormItem>
                <FormLabel>Date</FormLabel>
                <FormControl>
                  <Input type="date" v-bind="componentField" @update:modelValue="setEditFieldValue('date', $event)" />
                </FormControl>
                <FormMessage :message="errorMessage || editErrors.date" />
              </FormItem>
            </FormField>
            <FormField v-slot="{ componentField, errorMessage }" name="mood">
              <FormItem>
                <FormLabel>Mood / Reflection</FormLabel>
                <FormControl>
                  <Select v-bind="componentField" @update:modelValue="setEditMood($event)">
                    <option value="">Select mood</option>
                    <option value="productive">Productive</option>
                    <option value="okay">Okay</option>
                    <option value="difficult">Difficult</option>
                    <option value="great">Great</option>
                  </Select>
                </FormControl>
                <FormMessage :message="errorMessage || editErrors.mood" />
              </FormItem>
            </FormField>
            <FormField v-slot="{ componentField, errorMessage }" name="timeIn">
              <FormItem>
                <FormLabel>Time In</FormLabel>
                <FormControl>
                  <Input type="time" v-bind="componentField" @update:modelValue="setEditFieldValue('timeIn', $event)" />
                </FormControl>
                <FormMessage :message="errorMessage || editErrors.timeIn" />
              </FormItem>
            </FormField>
            <FormField v-slot="{ componentField, errorMessage }" name="timeOut">
              <FormItem>
                <FormLabel>Time Out</FormLabel>
                <FormControl>
                  <Input type="time" v-bind="componentField" @update:modelValue="setEditFieldValue('timeOut', $event)" />
                </FormControl>
                <FormMessage :message="errorMessage || editErrors.timeOut" />
              </FormItem>
            </FormField>
            <FormField v-slot="{ componentField, errorMessage }" name="tasksDone">
              <FormItem class="md:col-span-2">
                <FormLabel>Tasks Done</FormLabel>
                <FormControl>
                  <Textarea
                    :model-value="String(componentField.modelValue ?? '')"
                    :rows="5"
                    @update:modelValue="setEditFieldValue('tasksDone', $event)"
                  />
                </FormControl>
                <FormMessage :message="errorMessage || editErrors.tasksDone" />
              </FormItem>
            </FormField>
            <div class="flex justify-end gap-3 md:col-span-2">
              <Button variant="outline" @click="close">Cancel</Button>
              <Button type="submit" :disabled="editSubmitting">
                <LoaderCircle v-if="editSubmitting" class="h-4 w-4 animate-spin" />
                <span>{{ editSubmitting ? 'Saving...' : 'Save Changes' }}</span>
              </Button>
            </div>
          </form>
        </template>
      </Dialog>

      <AlertDialog
        :open="deleteDialogOpen"
        title="Delete Pending Log?"
        description="This removes the selected pending OJT log permanently."
        action-label="Delete Log"
        action-variant="destructive"
        @update:open="deleteDialogOpen = $event"
        @action="confirmDeleteLog"
      />

      <Dialog v-model:open="rejectDialogOpen">
        <template #default="{ close }">
          <DialogHeader>
            <DialogTitle>Reject OJT Log</DialogTitle>
            <p class="text-sm text-muted-foreground">
              Add a required reason so the intern knows what needs to be corrected.
            </p>
          </DialogHeader>
          <form class="mt-6 space-y-4" @submit="submitRejectReason">
            <FormField v-slot="{ componentField, errorMessage }" name="reason">
              <FormItem>
                <FormLabel>Rejection Reason</FormLabel>
                <FormControl>
                  <Textarea
                    :model-value="String(componentField.modelValue ?? '')"
                    :rows="5"
                    placeholder="Explain why this OJT log is being rejected"
                    @update:modelValue="setRejectFieldValue('reason', $event)"
                  />
                </FormControl>
                <FormMessage :message="errorMessage || rejectErrors.reason" />
              </FormItem>
            </FormField>
            <div class="flex justify-end gap-3">
              <Button variant="outline" @click="close">Cancel</Button>
              <Button type="submit" variant="destructive" :disabled="rejectSubmitting">
                <LoaderCircle v-if="rejectSubmitting" class="h-4 w-4 animate-spin" />
                <span>{{ rejectSubmitting ? 'Rejecting...' : 'Reject Log' }}</span>
              </Button>
            </div>
          </form>
        </template>
      </Dialog>

      <Dialog v-model:open="schoolDetailDialogOpen">
        <template #default>
          <DialogHeader>
            <DialogTitle>{{ selectedSchoolIntern?.internName || 'Intern OJT History' }}</DialogTitle>
            <p class="text-sm text-muted-foreground">
              Full read-only OJT log history for the selected intern.
            </p>
          </DialogHeader>
          <div class="mt-6 overflow-hidden rounded-xl border border-border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Time In</TableHead>
                  <TableHead>Time Out</TableHead>
                  <TableHead>Hours</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Tasks</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="schoolDetailLoading">
                  <TableRow v-for="index in 4" :key="index">
                    <TableCell colspan="6"><Skeleton class="h-10 rounded-xl" /></TableCell>
                  </TableRow>
                </template>
                <template v-else>
                  <TableRow v-for="log in schoolDetailLogs" :key="log.id">
                    <TableCell>{{ formatDate(log.date) }}</TableCell>
                    <TableCell>{{ log.timeIn }}</TableCell>
                    <TableCell>{{ log.timeOut }}</TableCell>
                    <TableCell>{{ formatHours(log.hoursRendered) }}</TableCell>
                    <TableCell>
                      <Badge :variant="statusBadgeVariant(log.status)">{{ statusLabel(log.status) }}</Badge>
                    </TableCell>
                    <TableCell class="max-w-xs truncate">{{ log.tasksDone }}</TableCell>
                  </TableRow>
                  <TableRow v-if="!schoolDetailLogs.length">
                    <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
                      No OJT logs are available for this intern yet.
                    </TableCell>
                  </TableRow>
                </template>
              </TableBody>
            </Table>
          </div>
        </template>
      </Dialog>

      <Dialog v-model:open="adminDetailDialogOpen">
        <template #default>
          <DialogHeader>
            <DialogTitle>Log Detail</DialogTitle>
            <p class="text-sm text-muted-foreground">
              Review the full context for this OJT entry, including schedule, status, and submission timing.
            </p>
          </DialogHeader>
          <div v-if="selectedAdminLog" class="mt-6 grid gap-4 md:grid-cols-2">
            <Card class="border-border/70">
              <CardContent class="space-y-2 p-4">
                <p class="text-sm text-muted-foreground">Intern</p>
                <p class="font-semibold">{{ selectedAdminLog.internName }}</p>
              </CardContent>
            </Card>
            <Card class="border-border/70">
              <CardContent class="space-y-2 p-4">
                <p class="text-sm text-muted-foreground">School</p>
                <p class="font-semibold">{{ selectedAdminLog.school || 'Not available' }}</p>
              </CardContent>
            </Card>
            <Card class="border-border/70">
              <CardContent class="space-y-2 p-4">
                <p class="text-sm text-muted-foreground">Company</p>
                <p class="font-semibold">{{ selectedAdminLog.company || 'Not available' }}</p>
              </CardContent>
            </Card>
            <Card class="border-border/70">
              <CardContent class="space-y-2 p-4">
                <p class="text-sm text-muted-foreground">Status</p>
                <Badge :variant="statusBadgeVariant(selectedAdminLog.status)">
                  {{ statusLabel(selectedAdminLog.status) }}
                </Badge>
              </CardContent>
            </Card>
            <Card class="border-border/70">
              <CardContent class="space-y-2 p-4">
                <p class="text-sm text-muted-foreground">Date and Time</p>
                <p class="font-semibold">
                  {{ formatDate(selectedAdminLog.date) }} | {{ selectedAdminLog.timeIn }} - {{ selectedAdminLog.timeOut }}
                </p>
              </CardContent>
            </Card>
            <Card class="border-border/70">
              <CardContent class="space-y-2 p-4">
                <p class="text-sm text-muted-foreground">Submitted At</p>
                <p class="font-semibold">{{ formatDateTime(selectedAdminLog.submittedAt || selectedAdminLog.createdAt) }}</p>
              </CardContent>
            </Card>
            <Card class="border-border/70 md:col-span-2">
              <CardContent class="space-y-2 p-4">
                <p class="text-sm text-muted-foreground">Tasks Done</p>
                <p class="leading-7">{{ selectedAdminLog.tasksDone }}</p>
              </CardContent>
            </Card>
            <Card v-if="selectedAdminLog.rejectionReason" class="border-red-200 bg-red-50 md:col-span-2">
              <CardContent class="space-y-2 p-4">
                <p class="text-sm font-medium text-red-600">Rejection Reason</p>
                <p class="leading-7 text-red-700">{{ selectedAdminLog.rejectionReason }}</p>
              </CardContent>
            </Card>
          </div>
        </template>
      </Dialog>
    </div>
  </MainLayout>
</template>
