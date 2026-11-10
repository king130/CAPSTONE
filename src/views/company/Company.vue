<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  BriefcaseBusiness,
  Building2,
  CalendarDays,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Eye,
  FileText,
  LoaderCircle,
  MessageSquare,
  Pencil,
  Plus,
  Search,
  Trash2,
  Users,
  XCircle,
} from 'lucide-vue-next'

import AlertDialog from '@/components/ui/alert-dialog/AlertDialog.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import RowActionsMenu from '@/components/ui/row-actions-menu/RowActionsMenu.vue'
import StatusBadgeMenu from '@/components/ui/status-badge-menu/StatusBadgeMenu.vue'
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
import Select from '@/components/ui/select/Select.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Table from '@/components/ui/table/Table.vue'
import TableBody from '@/components/ui/table/TableBody.vue'
import TableCell from '@/components/ui/table/TableCell.vue'
import TableHead from '@/components/ui/table/TableHead.vue'
import TableHeader from '@/components/ui/table/TableHeader.vue'
import TableRow from '@/components/ui/table/TableRow.vue'
import { caviteBarangaysByLocation, caviteLocationGroups } from '@/config/courseCatalog'
import { formatAllowanceDisplay } from '@/utils/formatAllowance'
import { formatDate, toTitleCaseName } from '@/utils/formatDisplay'
import ApplicationTrackerPanel from '@/components/ApplicationTrackerPanel.vue'
import FilePreviewActions from '@/components/FilePreviewActions.vue'
import ScheduleEditor from '@/components/ScheduleEditor.vue'
import {
  DAY_OPTIONS,
  slotsFromLegacy,
  type ScheduleSlot,
  validateSlots,
} from '@/utils/scheduleSlots'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import { assignedPermissionAllows, routePermissionRequirements } from '@/layouts/navigation'
import { subscribeCompanyApplications, updateApplicationStatus, type ApplicationRecord } from '@/services/applications'
import { fetchKpiReport, type KpiReport } from '@/services/kpi'
import {
  createInternship,
  deleteInternship,
  subscribeCompanyInternships,
  updateInternship,
  type InternshipRecord,
} from '@/services/internships'
import {
  listCertificates,
  printCertificateHtml,
  viewCertificateHtml,
  type CertificateRecord,
} from '@/services/certificates'
import { verificationStatusLabel } from '@/services/organizationVerification'
import { useAuthStore } from '@/stores/auth'
import { jobStatusLabel, normalizeJobStatus, type JobStatusValue } from '@/utils/jobStatus'
import SkillChips from '@/components/skills/SkillChips.vue'
import SkillsWizard from '@/components/skills/SkillsWizard.vue'
import type { SelectedSkill } from '@/services/skills'
import { recommendedSkillCategoriesForCourses } from '@/utils/courseSkillCategoryDefaults'

type CompanyView = 'dashboard' | 'internships' | 'applications'
type ApplicantAction = 'accepted' | 'rejected'
type JobWizardStep = 'basic' | 'details' | 'programs' | 'requirements'
type JobRow = InternshipRecord & { applicationCount: number }

interface JobFormState {
  title: string
  companyName: string
  industry: string
  description: string
  locationArea: string
  barangay: string
  locationDetails: string
  type: string
  duration: string
  hostRequiredHours: string
  slotsAvailable: number
  status: JobStatusValue
  allowance: string
  startDate: string
  endDate: string
  scheduleDays: string[]
  scheduleStartTime: string
  scheduleEndTime: string
  scheduleSlots: ScheduleSlot[]
  scheduleType: string
  weeklyHours: string
  isFlexible: boolean
  tasks: string
  requiredSkillsText: string
  internGains: string
  requiredDocumentsText: string
  requiresResume: boolean
  applicationInstructions: string
  contactInfo: string
  eligibleCoursesText: string
}

interface ChipFieldState {
  requiredSkill: string
  requiredDocument: string
}

const authStore = useAuthStore()
const { success, error } = useToast()
const route = useRoute()
const router = useRouter()

const currentView = ref<CompanyView>('dashboard')
const internships = ref<InternshipRecord[]>([])
const applications = ref<ApplicationRecord[]>([])
const certificates = ref<CertificateRecord[]>([])
const certificatesLoading = ref(false)
const certificatesError = ref<string | null>(null)
const certificateActionId = ref<string | null>(null)
const loadingJobs = ref(true)
const loadingApplications = ref(true)
const searchJobs = ref('')
const searchApplicants = ref('')
const applicantStatusTab = ref<'all' | 'needs_review' | 'interview' | 'accepted' | 'rejected' | 'withdrawn'>('all')
const hostHoursAdvancedOpen = ref(false)
const jobEditorOpen = ref(false)
const jobDetailsOpen = ref(false)
const savingJob = ref(false)
const applicantDialogOpen = ref(false)
const applicantDialogMode = ref<'profile' | 'tracker'>('profile')
const selectedJob = ref<(InternshipRecord & { applicationCount?: number }) | null>(null)
const selectedApplicant = ref<ApplicationRecord | null>(null)
async function onTrackerUpdated() {
  await refreshApplications()
}
const pendingDeleteJob = ref<{
  id: string
  title: string
  applicationCount: number
  isDraft: boolean
} | null>(null)
const deletingJobId = ref<string | null>(null)
const pendingStatusChange = ref<{
  jobId: string
  title: string
  applicationCount: number
  next: JobStatusValue
  previous: JobStatusValue
} | null>(null)
const statusUpdatingJobId = ref<string | null>(null)
const processingApplicantId = ref<string | null>(null)
const editingJobId = ref<string | null>(null)
const kpi = ref<KpiReport | null>(null)
const jobEditorRef = ref<HTMLElement | null>(null)
const currentJobStep = ref<JobWizardStep>('basic')
const chipInput = ref<ChipFieldState>({
  requiredSkill: '',
  requiredDocument: '',
})
const draftSaveState = ref<'idle' | 'dirty' | 'saving' | 'saved' | 'error'>('idle')
const draftSavedAt = ref<Date | null>(null)
const publishFieldErrors = ref<Record<string, string>>({})
const publishingJob = ref(false)
const suppressAutosave = ref(false)
const jobSkills = ref<SelectedSkill[]>([])
const skillsWizardOpen = ref(false)
const jobSkillsRecommendedCategories = computed(() => {
  const selected = asList(jobForm.value.eligibleCoursesText)
  const courses = selected.length ? selected : companyAcceptedCourses.value
  return recommendedSkillCategoriesForCourses(courses)
})

const jobForm = ref<JobFormState>(defaultJobForm())

let unsubscribeInternships: (() => void) | null = null
let unsubscribeApplications: (() => void) | null = null
let autosaveTimer: ReturnType<typeof setTimeout> | undefined
let lastSavedFingerprint = ''

const isEditingDraft = computed(
  () => !editingJobId.value || normalizeJobStatus(jobForm.value.status) === 'draft',
)

const draftSaveLabel = computed(() => {
  if (!isEditingDraft.value) {
    if (draftSaveState.value === 'dirty') return 'Unsaved changes'
    return ''
  }
  if (draftSaveState.value === 'saving') return 'Saving...'
  if (draftSaveState.value === 'error') return 'Draft save failed'
  if (draftSaveState.value === 'dirty') return 'Unsaved changes'
  if (draftSaveState.value === 'saved' && draftSavedAt.value) {
    const seconds = Math.max(0, Math.round((Date.now() - draftSavedAt.value.getTime()) / 1000))
    if (seconds < 8) return 'Draft saved just now'
    if (seconds < 60) return `Draft saved ${seconds}s ago`
    return `Draft saved ${draftSavedAt.value.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`
  }
  return editingJobId.value ? 'Draft saved' : ''
})

const pageTitle = computed(() => {
  const titles: Record<CompanyView, string> = {
    dashboard: 'Dashboard',
    internships: 'Posted Jobs',
    applications: 'Applicants',
  }
  return titles[currentView.value]
})

const isMunicipalHall = computed(
  () => String(authStore.user?.activeOrganization?.type || '').toLowerCase() === 'municipal_hall',
)

const hostOrgLabel = computed(() => (isMunicipalHall.value ? 'Municipal Hall' : 'Company'))

const companyName = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return (
    String(profile?.companyName || profile?.name || authStore.user?.displayName || authStore.user?.email || hostOrgLabel.value)
      .trim()
  )
})

const companyProfile = computed(() => (authStore.user?.profile as Record<string, unknown> | undefined) ?? {})

const companyIndustry = computed(() => String(companyProfile.value?.industryType || ''))

const companyAcceptedCourses = computed(() => {
  const stored = Array.isArray(companyProfile.value?.courses) ? (companyProfile.value.courses as string[]) : []
  return stored.map((course) => course.trim()).filter(Boolean)
})

const commonDurationOptions = ['200 hours', '300 hours', '486 hours', '600 hours']
const workSetupOptions = [
  { value: 'On-site', label: 'On-site', description: 'Intern reports directly to the workplace.' },
  { value: 'Hybrid', label: 'Hybrid', description: 'Intern splits time between workplace and remote work.' },
  { value: 'Remote', label: 'Remote', description: 'Intern completes tasks fully online.' },
  { value: 'Field Work', label: 'Field Work', description: 'Internship includes travel or site-based work.' },
]
const wizardSteps: Array<{ id: JobWizardStep; label: string; caption: string }> = [
  { id: 'basic', label: 'Basic Information', caption: 'Title, organization details, work setup, and Cavite location' },
  { id: 'details', label: 'Internship Details', caption: 'Hours, slots, dates, schedule, and role overview' },
  { id: 'programs', label: 'Accepted Programs', caption: 'Choose which courses from the organization profile are accepted' },
  { id: 'requirements', label: 'Requirements', caption: 'Documents, how to apply, and contact details' },
]
const weekdayOptions = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']

const companyContactInfo = computed(() => {
  const parts = [
    String(companyProfile.value?.contactPersonName || '').trim(),
    String(companyProfile.value?.contactPersonEmail || companyProfile.value?.companyEmail || authStore.user?.email || '').trim(),
    String(companyProfile.value?.companyContactNumber || '').trim(),
  ].filter(Boolean)

  return parts.join(' | ')
})

const availableBarangays = computed(() => {
  return caviteBarangaysByLocation[jobForm.value.locationArea] ?? []
})

const isLastJobStep = computed(() => wizardSteps[wizardStepIndex.value]?.id === 'requirements')
const isFirstJobStep = computed(() => wizardSteps[wizardStepIndex.value]?.id === 'basic')
const wizardStepIndex = computed(() => wizardSteps.findIndex((step) => step.id === currentJobStep.value))

const dashboardStats = computed(() => {
  const openPositions = internships.value.filter((job) => normalizeJobStatus(job.status) === 'active').length
  const totalApplicants = applications.value.length
  const pendingReview = applications.value.filter((item) => normalizeApplicationStatus(item.status) === 'endorsed').length
  const acceptedInterns = applications.value.filter((item) => normalizeApplicationStatus(item.status) === 'accepted').length

  return [
    {
      label: 'Open Positions',
      value: openPositions,
      icon: BriefcaseBusiness,
      iconClass: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
      caption: 'Accepting applications',
    },
    {
      label: 'Total Applicants',
      value: totalApplicants,
      icon: Users,
      iconClass: 'bg-violet-100 text-violet-700',
      caption: 'Across all postings',
    },
    {
      label: 'Needs Review',
      value: pendingReview,
      icon: Eye,
      iconClass: 'bg-amber-100 text-amber-700',
      caption: 'Endorsed, awaiting decision',
    },
    {
      label: 'Accepted Interns',
      value: acceptedInterns,
      icon: CheckCircle2,
      iconClass: 'bg-accent text-primary',
      caption: 'Moved into placement',
    },
  ]
})

const attentionApplicants = computed(() =>
  applications.value
    .filter((item) => normalizeApplicationStatus(item.status) === 'endorsed')
    .slice(0, 5),
)

function canOpenRoute(routeName: string) {
  const required = routePermissionRequirements(routeName)
  const assigned = authStore.user?.memberships?.[0]?.permissions ?? []
  return !required || assignedPermissionAllows(assigned, required)
}

const compactQuickActions = computed(() => [
  {
    label: 'Post a job',
    icon: Plus,
    allowed: assignedPermissionAllows(authStore.user?.memberships?.[0]?.permissions ?? [], ['org.manage_internships', 'manage_internships']),
    action: () => {
      void openCreateJobDialog()
    },
  },
  {
    label: 'Review applicants',
    icon: Users,
    allowed: canOpenRoute('company-applicants'),
    action: () => handleMenuClick('applications'),
  },
  {
    label: 'Agreements',
    icon: FileText,
    allowed: canOpenRoute('agreements'),
    action: () => router.push({ name: 'agreements' }),
  },
  {
    label: 'Messages',
    icon: MessageSquare,
    allowed: true,
    action: () => window.dispatchEvent(new CustomEvent('chat:open')),
  },
].filter((item) => item.allowed))

const jobRows = computed(() => {
  const query = searchJobs.value.trim().toLowerCase()

  return internships.value
    .map((job) => ({
      ...job,
      applicationCount: applications.value.filter((application) => application.internshipId === job.id).length,
    }))
    .filter((job) =>
      !query ||
      [job.title, job.location, job.type, job.status].some((value) =>
        String(value || '')
          .toLowerCase()
          .includes(query),
      ),
    )
})

function applicantFilterBucket(application: ApplicationRecord): 'needs_review' | 'interview' | 'accepted' | 'rejected' | 'withdrawn' | 'other' {
  const stage = String(application.trackerStage || '').trim().toLowerCase()
  const status = normalizeApplicationStatus(application.status)
  if (
    status === 'withdrawn_placed_elsewhere'
    || stage === 'withdrawn_placed_elsewhere'
    || application.withdrawalReason === 'Placed elsewhere'
  ) {
    return 'withdrawn'
  }
  if (status === 'accepted' || stage === 'accepted') return 'accepted'
  if (
    status === 'rejected'
    || status === 'school_rejected'
    || stage === 'rejected'
    || stage === 'school_rejected'
    || status.startsWith('placement_')
  ) {
    return 'rejected'
  }
  if (['interview_scheduled', 'interviewed', 'assessed'].includes(stage)) return 'interview'
  if (status === 'endorsed' || stage === 'endorsed') return 'needs_review'
  return 'other'
}

const applicantRows = computed(() => {
  const query = searchApplicants.value.trim().toLowerCase()
  const tab = applicantStatusTab.value

  return applications.value.filter((application) => {
    const bucket = applicantFilterBucket(application)
    if (tab !== 'all' && bucket !== tab) return false

    const values = [
      application.studentName,
      application.schoolName,
      application.studentCourse,
      application.internshipTitle,
      application.status,
    ]

    return (
      !query ||
      values.some((value) =>
        String(value || '')
          .toLowerCase()
          .includes(query),
      )
    )
  })
})

function selectAllAcceptedCourses() {
  jobForm.value.eligibleCoursesText = companyAcceptedCourses.value.join(', ')
  onJobFieldBlur()
}

function defaultJobForm(): JobFormState {
  return {
    title: '',
    companyName: '',
    industry: '',
    description: '',
    locationArea: '',
    barangay: '',
    locationDetails: '',
    type: 'On-site',
    duration: '',
    hostRequiredHours: '',
    slotsAvailable: 1,
    status: 'draft',
    allowance: '',
    startDate: '',
    endDate: '',
    scheduleDays: [],
    scheduleStartTime: '',
    scheduleEndTime: '',
    scheduleSlots: [],
    scheduleType: 'fixed',
    weeklyHours: '',
    isFlexible: false,
    tasks: '',
    requiredSkillsText: '',
    internGains: '',
    requiredDocumentsText: '',
    requiresResume: true,
    applicationInstructions: '',
    contactInfo: '',
    eligibleCoursesText: '',
  }
}

function addListItem(
  target: 'eligibleCoursesText' | 'requiredSkillsText' | 'requiredDocumentsText',
  value: string
) {
  const normalized = value.trim()
  if (!normalized) return

  const current = asList(jobForm.value[target])
  const exists = current.some((item) => item.toLowerCase() === normalized.toLowerCase())
  if (exists) return

  jobForm.value[target] = [...current, normalized].join(', ')
}

function removeListItem(
  target: 'eligibleCoursesText' | 'requiredSkillsText' | 'requiredDocumentsText',
  value: string
) {
  jobForm.value[target] = asList(jobForm.value[target])
    .filter((item) => item.toLowerCase() !== value.toLowerCase())
    .join(', ')
}

function commitChipInput(field: keyof ChipFieldState) {
  if (field === 'requiredSkill') {
    addListItem('requiredSkillsText', chipInput.value.requiredSkill)
  } else {
    addListItem('requiredDocumentsText', chipInput.value.requiredDocument)
  }

  chipInput.value[field] = ''
}

function companyViewFromRouteName(name: unknown): CompanyView {
  if (name === 'company-internships') return 'internships'
  if (name === 'company-applicants') return 'applications'
  return 'dashboard'
}

function normalizeApplicationStatus(status?: string | null) {
  const normalized = String(status || 'submitted').trim().toLowerCase()
  if (['submitted', 'pending'].includes(normalized)) return 'submitted'
  if (normalized === 'endorsed') return 'endorsed'
  if (['accepted', 'approved', 'active'].includes(normalized)) return 'accepted'
  if (normalized === 'withdrawn_placed_elsewhere') return 'withdrawn_placed_elsewhere'
  if (['placement_cancelled', 'placement_declined', 'placement_ended'].includes(normalized)) return normalized
  if (['rejected', 'declined', 'cancelled'].includes(normalized)) return 'rejected'
  if (normalized === 'school_rejected') return 'school_rejected'
  return 'submitted'
}

function badgeLabel(status?: string | null) {
  const normalized = normalizeApplicationStatus(status)
  if (normalized === 'submitted') return 'Awaiting School Endorsement'
  if (normalized === 'endorsed') return 'Endorsed'
  if (normalized === 'accepted') return 'Accepted'
  if (normalized === 'withdrawn_placed_elsewhere') return 'Withdrawn — placed elsewhere'
  if (normalized === 'placement_cancelled') return 'Placement cancelled'
  if (normalized === 'placement_declined') return 'Placement declined'
  if (normalized === 'placement_ended') return 'Placement ended'
  if (normalized === 'school_rejected') return 'Rejected By School'
  return 'Rejected'
}

function applicantProgressLabel(applicant: {
  status?: string | null
  trackerStage?: string | null
  withdrawalReason?: string | null
}) {
  const stage = String(applicant.trackerStage || '').trim().toLowerCase()
  if (stage === 'withdrawn_placed_elsewhere' || applicant.withdrawalReason === 'Placed elsewhere') {
    return 'Withdrawn — placed elsewhere'
  }
  if (stage === 'interview_scheduled') return 'Interview Scheduled'
  if (stage === 'interviewed') return 'Interviewed'
  if (stage === 'assessed') return 'Assessed'
  if (stage === 'accepted') return 'Accepted'
  if (stage === 'rejected') return 'Rejected'
  if (stage === 'school_rejected') return 'Rejected By School'
  if (stage === 'placement_cancelled') return 'Placement cancelled'
  if (stage === 'placement_declined') return 'Placement declined'
  if (stage === 'placement_ended') return 'Placement ended'
  return badgeLabel(applicant.status)
}

function applicantBadgeVariant(status?: string | null) {
  const normalized = normalizeApplicationStatus(status)
  if (normalized === 'accepted') return 'success'
  if (
    normalized === 'rejected'
    || normalized === 'withdrawn_placed_elsewhere'
    || normalized.startsWith('placement_')
  ) {
    return 'destructive'
  }
  if (normalized === 'school_rejected') return 'outline'
  return 'warning'
}

function asList(value: string) {
  return value
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
}

function splitLocation(value?: string) {
  const raw = String(value || '').trim()
  if (!raw) {
    return { locationArea: '', barangay: '', locationDetails: '' }
  }

  const matchedArea = caviteLocationGroups
    .flatMap((group) => group.options)
    .find((option) => raw === option || raw.startsWith(`${option} - `))

  if (matchedArea) {
    const remainder = raw.slice(matchedArea.length).replace(/^ - /, '').trim()
    const barangay = (caviteBarangaysByLocation[matchedArea] ?? []).find(
      (option) => remainder === option || remainder.startsWith(`${option} - `),
    )

    return {
      locationArea: matchedArea,
      barangay: barangay || '',
      locationDetails: barangay ? remainder.slice(barangay.length).replace(/^ - /, '').trim() : remainder,
    }
  }

  return { locationArea: '', barangay: '', locationDetails: raw }
}

function buildLocation() {
  const area = jobForm.value.locationArea.trim()
  const barangay = jobForm.value.barangay.trim()
  const details = jobForm.value.locationDetails.trim()
  const parts = [area, barangay, details].filter(Boolean)

  return parts.join(' - ')
}

function syncScheduleFromSlots(slots: ScheduleSlot[]) {
  jobForm.value.scheduleSlots = slots
  jobForm.value.scheduleDays = slots.map(
    (s) => DAY_OPTIONS.find((d) => d.value === s.day)?.label || s.day,
  )
  const starts = new Set(slots.map((s) => s.start))
  const ends = new Set(slots.map((s) => s.end))
  jobForm.value.scheduleStartTime = starts.size === 1 ? slots[0]?.start || '' : slots[0]?.start || ''
  jobForm.value.scheduleEndTime = ends.size === 1 ? slots[0]?.end || '' : slots[0]?.end || ''
}

function buildSchedule() {
  if (jobForm.value.scheduleSlots.length) {
    const days = jobForm.value.scheduleDays.join(', ')
    const time = [jobForm.value.scheduleStartTime, jobForm.value.scheduleEndTime].filter(Boolean).join(' - ')
    return [days, time].filter(Boolean).join(' | ')
  }
  const days = jobForm.value.scheduleDays.join(', ')
  const time = [jobForm.value.scheduleStartTime, jobForm.value.scheduleEndTime].filter(Boolean).join(' - ')
  return [days, time].filter(Boolean).join(' | ')
}

function parseSchedule(value?: string) {
  const raw = String(value || '').trim()
  if (!raw) {
    return {
      scheduleDays: [] as string[],
      scheduleStartTime: '',
      scheduleEndTime: '',
    }
  }

  const [daysPart = '', timePart = ''] = raw.split('|').map((part) => part.trim())
  const scheduleDays = daysPart
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
    .filter((item) => weekdayOptions.includes(item))
  const [scheduleStartTime = '', scheduleEndTime = ''] = timePart.split('-').map((item) => item.trim())

  return {
    scheduleDays,
    scheduleStartTime,
    scheduleEndTime,
  }
}

function toggleScheduleDay(day: string) {
  if (jobForm.value.scheduleDays.includes(day)) {
    jobForm.value.scheduleDays = jobForm.value.scheduleDays.filter((item) => item !== day)
    return
  }

  jobForm.value.scheduleDays = [...jobForm.value.scheduleDays, day]
}

function moveToNextJobStep() {
  const next = wizardSteps[wizardStepIndex.value + 1]
  if (next) currentJobStep.value = next.id
}

function moveToPreviousJobStep() {
  const previous = wizardSteps[wizardStepIndex.value - 1]
  if (previous) currentJobStep.value = previous.id
}

function fillJobForm(job?: InternshipRecord) {
  if (!job) {
    jobForm.value = {
      ...defaultJobForm(),
      companyName: companyName.value,
      industry: companyIndustry.value,
      contactInfo: companyContactInfo.value,
      eligibleCoursesText: '',
    }
    chipInput.value = { requiredSkill: '', requiredDocument: '' }
    jobSkills.value = []
    editingJobId.value = null
    currentJobStep.value = 'basic'
    return
  }

  const locationParts = splitLocation(job.location)
  const scheduleParts = parseSchedule(job.schedule)

  jobForm.value = {
    title: job.title || '',
    companyName: job.companyName || companyName.value,
    industry: job.industry || companyIndustry.value,
    description: job.description || '',
    locationArea: locationParts.locationArea,
    barangay: locationParts.barangay,
    locationDetails: locationParts.locationDetails,
    type: job.type || 'On-site',
    duration: job.duration || '',
    hostRequiredHours: job.hostRequiredHours != null ? String(job.hostRequiredHours) : '',
    slotsAvailable: Number(job.slotsAvailable || 1),
    status: normalizeJobStatus(job.status),
    allowance: job.allowance || '',
    startDate: job.startDate || '',
    endDate: job.endDate || '',
    scheduleDays: job.scheduleDays?.length ? [...job.scheduleDays] : scheduleParts.scheduleDays,
    scheduleStartTime: job.timeIn || scheduleParts.scheduleStartTime,
    scheduleEndTime: job.timeOut || scheduleParts.scheduleEndTime,
    scheduleSlots: slotsFromLegacy({
      scheduleSlots: job.scheduleSlots as ScheduleSlot[] | undefined,
      scheduleDays: job.scheduleDays?.length ? job.scheduleDays : scheduleParts.scheduleDays,
      timeIn: job.timeIn || scheduleParts.scheduleStartTime,
      timeOut: job.timeOut || scheduleParts.scheduleEndTime,
    }),
    scheduleType: job.scheduleType || 'fixed',
    weeklyHours: job.weeklyHours != null ? String(job.weeklyHours) : '',
    isFlexible: Boolean(job.isFlexible),
    tasks: job.tasks || '',
    requiredSkillsText: (job.skills?.length
      ? job.skills.map((skill) => skill.name)
      : job.requiredSkills || job.requirements || []
    ).join(', '),
    internGains: job.internGains || '',
    requiredDocumentsText: (job.requiredDocuments || []).join(', '),
    requiresResume: job.requiresResume !== false,
    applicationInstructions: job.applicationInstructions || '',
    contactInfo: job.contactInfo || companyContactInfo.value,
    eligibleCoursesText: (job.eligibleCourses || companyAcceptedCourses.value).join(', '),
  }
  chipInput.value = { requiredSkill: '', requiredDocument: '' }
  jobSkills.value = (job.skills || []).map((skill) => ({
    id: skill.id,
    name: skill.name,
    category: skill.category || 'Other',
    status: (skill.status as SelectedSkill['status']) || 'approved',
    importance: skill.importance || 'required',
  }))
  editingJobId.value = job.id
  currentJobStep.value = 'basic'
}

function formFingerprint() {
  return JSON.stringify({
    form: jobForm.value,
    chips: chipInput.value,
    skills: jobSkills.value,
    step: currentJobStep.value,
  })
}

function buildJobPayload(status: JobStatusValue) {
  syncScheduleFromSlots(jobForm.value.scheduleSlots)
  const eligibleCourses = asList(jobForm.value.eligibleCoursesText)
  const skillNames = jobSkills.value.map((skill) => skill.name)
  const requiredNames = jobSkills.value
    .filter((skill) => (skill.importance || 'required') === 'required')
    .map((skill) => skill.name)
  const title = jobForm.value.title.trim() || (status === 'draft' ? 'Untitled draft' : '')
  return {
    companyId: authStore.user?.uid || '',
    companyName: companyName.value,
    industry: companyIndustry.value || undefined,
    title,
    description: jobForm.value.description.trim(),
    location: buildLocation(),
    type: jobForm.value.type.trim(),
    duration: jobForm.value.duration.trim(),
    hostRequiredHours: jobForm.value.hostRequiredHours.trim()
      ? Number(jobForm.value.hostRequiredHours)
      : null,
    slotsAvailable: Number(jobForm.value.slotsAvailable || 1),
    status,
    eligibleCourses: eligibleCourses.length ? eligibleCourses : companyAcceptedCourses.value,
    requirements: requiredNames.length ? requiredNames : skillNames,
    allowance: jobForm.value.allowance.trim() || null,
    startDate: jobForm.value.startDate || undefined,
    endDate: jobForm.value.endDate || undefined,
    schedule: buildSchedule() || undefined,
    scheduleType: jobForm.value.scheduleType || undefined,
    weeklyHours: jobForm.value.weeklyHours ? Number(jobForm.value.weeklyHours) : undefined,
    scheduleDays: jobForm.value.scheduleDays,
    timeIn: jobForm.value.scheduleStartTime || undefined,
    timeOut: jobForm.value.scheduleEndTime || undefined,
    scheduleSlots: jobForm.value.scheduleSlots,
    isFlexible: jobForm.value.isFlexible,
    tasks: jobForm.value.tasks.trim() || undefined,
    requiredSkills: skillNames,
    skills: jobSkills.value.map((skill) => ({
      id: skill.id,
      name: skill.name,
      category: skill.category,
      status: skill.status,
      importance: skill.importance || 'required',
    })),
    internGains: jobForm.value.internGains.trim() || undefined,
    requiredDocuments: ['Endorsement'],
    requiresResume: false,
    applicationInstructions: jobForm.value.applicationInstructions.trim() || undefined,
    contactInfo: companyContactInfo.value || jobForm.value.contactInfo.trim() || undefined,
  }
}

function validatePublishFields() {
  const errors: Record<string, string> = {}
  if (!jobForm.value.title.trim() || jobForm.value.title.trim() === 'Untitled draft') {
    errors.title = 'Enter a job title before publishing.'
  }
  if (!jobForm.value.locationArea.trim()) {
    errors.locationArea = 'Choose a Cavite location before publishing.'
  }
  if (!jobForm.value.type.trim()) {
    errors.type = 'Choose a work setup before publishing.'
  }
  if (!jobForm.value.duration.trim()) {
    errors.duration = 'Enter the internship duration before publishing.'
  }
  if (!Number(jobForm.value.slotsAvailable || 0)) {
    errors.slotsAvailable = 'Enter at least one available slot before publishing.'
  }
  if (!jobForm.value.description.trim()) {
    errors.description = 'Add a short role description before publishing.'
  }
  return errors
}

function markFormDirty() {
  if (suppressAutosave.value || !jobEditorOpen.value) return
  if (formFingerprint() === lastSavedFingerprint) {
    if (draftSaveState.value === 'dirty') {
      draftSaveState.value = isEditingDraft.value && editingJobId.value ? 'saved' : 'idle'
    }
    return
  }
  draftSaveState.value = 'dirty'
  // Autosave drafts only — never demote a published job to draft via typing.
  if (isEditingDraft.value) scheduleDraftAutosave()
}

function scheduleDraftAutosave() {
  if (publishingJob.value || savingJob.value) return
  if (autosaveTimer) clearTimeout(autosaveTimer)
  autosaveTimer = setTimeout(() => {
    void saveDraft({ silent: true })
  }, 3000)
}

function onJobFieldBlur() {
  if (!jobEditorOpen.value || !isEditingDraft.value) return
  if (publishingJob.value || savingJob.value) return
  if (autosaveTimer) clearTimeout(autosaveTimer)
  void saveDraft({ silent: true })
}

async function saveDraft(options: { silent?: boolean; force?: boolean } = {}) {
  if (!jobEditorOpen.value) return false
  // Never race a publish/save — that can demote an Open job back to Draft.
  if (publishingJob.value || savingJob.value) return false
  // Decision: autosave/create draft only for new postings or existing drafts.
  // Published jobs with (or without) applications are never rewritten as drafts here.
  if (!isEditingDraft.value) return false
  if (draftSaveState.value === 'saving') return false
  if (!options.force && formFingerprint() === lastSavedFingerprint && editingJobId.value) {
    draftSaveState.value = 'saved'
    return true
  }

  // Keep draft even with empty title (backend stores "Untitled draft").
  draftSaveState.value = 'saving'
  try {
    const payload = buildJobPayload('draft')
    if (editingJobId.value) {
      await updateInternship(editingJobId.value, payload)
    } else {
      const id = await createInternship(payload)
      editingJobId.value = id || editingJobId.value
    }
    jobForm.value.status = 'draft'
    draftSavedAt.value = new Date()
    lastSavedFingerprint = formFingerprint()
    draftSaveState.value = 'saved'
    if (!options.silent) {
      success('Draft saved', { description: 'You can leave and continue later.' })
    }
    return true
  } catch (caughtError) {
    draftSaveState.value = 'error'
    if (!options.silent) {
      error(caughtError, { fallback: 'Unable to save draft.' })
    }
    return false
  }
}

async function openJobEditor() {
  jobEditorOpen.value = true
  publishFieldErrors.value = {}
  suppressAutosave.value = true
  await nextTick()
  lastSavedFingerprint = formFingerprint()
  draftSaveState.value = editingJobId.value ? 'saved' : 'idle'
  draftSavedAt.value = editingJobId.value
    ? internships.value.find((job) => job.id === editingJobId.value)?.updatedAt
      ? new Date(String(internships.value.find((job) => job.id === editingJobId.value)?.updatedAt))
      : null
    : null
  suppressAutosave.value = false
  jobEditorRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function closeJobEditor() {
  if (draftSaveState.value === 'dirty' || draftSaveState.value === 'saving') {
    if (isEditingDraft.value) {
      const leave = window.confirm('You have unsaved changes. Save draft before closing?')
      if (leave) {
        const saved = await saveDraft({ silent: true, force: true })
        if (!saved && !window.confirm('Draft could not be saved. Close anyway?')) return
      } else if (!window.confirm('Close without saving your latest changes?')) {
        return
      }
    } else if (!window.confirm('You have unsaved changes. Close without saving?')) {
      return
    }
  }
  if (autosaveTimer) clearTimeout(autosaveTimer)
  jobEditorOpen.value = false
  publishFieldErrors.value = {}
  draftSaveState.value = 'idle'
  fillJobForm()
  lastSavedFingerprint = formFingerprint()
}

async function openCreateJobDialog() {
  fillJobForm()
  // Editor is rendered on the Posted Jobs view — switch there first.
  currentView.value = 'internships'
  if (route.name !== 'company-internships') {
    await router.push({ name: 'company-internships' })
  }
  await openJobEditor()
}

async function openEditJobDialog(job: InternshipRecord) {
  fillJobForm(job)
  await openJobEditor()
}

function openViewJobDialog(job: InternshipRecord & { applicationCount?: number }) {
  selectedJob.value = job
  jobDetailsOpen.value = true
}

function closeViewJobDialog() {
  jobDetailsOpen.value = false
  selectedJob.value = null
}

async function editSelectedJobFromView() {
  if (!selectedJob.value) return

  const job = selectedJob.value
  closeViewJobDialog()
  await openEditJobDialog(job)
}

function slotsFilledLabel(job: Pick<InternshipRecord, 'filledSlots' | 'slotsAvailable' | 'slotsRemaining'>) {
  const total = Math.max(0, Number(job.slotsAvailable) || 0)
  const remaining = job.slotsRemaining
  const filled =
    job.filledSlots != null
      ? Math.max(0, Number(job.filledSlots) || 0)
      : remaining == null
        ? 0
        : Math.max(0, total - Number(remaining))
  if (!total) return '0 slots'
  return `${Math.min(filled, total)} of ${total} filled`
}

async function requestJobStatusChange(job: JobRow | InternshipRecord, nextStatus: string) {
  const status = normalizeJobStatus(nextStatus)
  const previous = normalizeJobStatus(job.status)
  if (previous === status) return

  const applicationCount =
    'applicationCount' in job
      ? Number(job.applicationCount) || 0
      : applications.value.filter((application) => application.internshipId === job.id).length

  if (previous === 'draft' && status === 'active') {
    await openEditJobDialog(job as InternshipRecord)
    await publishJob()
    return
  }

  if (status === 'draft' && previous !== 'draft' && applicationCount > 0) {
    error('Jobs with applications cannot be converted back to a draft.')
    return
  }

  if (status === 'closed' && applicationCount > 0) {
    pendingStatusChange.value = {
      jobId: job.id,
      title: job.title || 'Untitled job',
      applicationCount,
      next: status,
      previous,
    }
    return
  }

  void applyJobStatusChange(job.id, previous, status)
}

async function applyJobStatusChange(jobId: string, previous: JobStatusValue, status: JobStatusValue) {
  statusUpdatingJobId.value = jobId
  internships.value = internships.value.map((item) =>
    item.id === jobId ? { ...item, status } : item,
  )
  if (selectedJob.value?.id === jobId) {
    selectedJob.value = { ...selectedJob.value, status }
  }

  try {
    await updateInternship(jobId, { status })
    success('Status updated', {
      description: `Job marked as ${jobStatusLabel(status)}.`,
    })
  } catch (caughtError) {
    internships.value = internships.value.map((item) =>
      item.id === jobId ? { ...item, status: previous } : item,
    )
    if (selectedJob.value?.id === jobId) {
      selectedJob.value = { ...selectedJob.value, status: previous }
    }
    error(caughtError, { fallback: 'Unable to update job status. Previous status restored.' })
  } finally {
    statusUpdatingJobId.value = null
    pendingStatusChange.value = null
  }
}

async function confirmPendingStatusChange() {
  if (!pendingStatusChange.value) return
  const { jobId, previous, next } = pendingStatusChange.value
  await applyJobStatusChange(jobId, previous, next)
}

async function refreshApplications() {
  loadingApplications.value = false
}

async function publishJob() {
  if (publishingJob.value || savingJob.value) return
  if (autosaveTimer) clearTimeout(autosaveTimer)
  publishingJob.value = true
  savingJob.value = true
  publishFieldErrors.value = {}
  try {
    const scheduleError = jobForm.value.scheduleSlots.length
      ? validateSlots(jobForm.value.scheduleSlots)
      : null
    if (scheduleError) {
      error(scheduleError)
      return
    }

    const fieldErrors = validatePublishFields()
    publishFieldErrors.value = fieldErrors
    if (Object.keys(fieldErrors).length) {
      error('Complete the required fields before publishing.')
      const firstKey = Object.keys(fieldErrors)[0]
      if (firstKey === 'description' || firstKey === 'tasks') currentJobStep.value = 'details'
      else if (firstKey === 'duration' || firstKey === 'slotsAvailable') currentJobStep.value = 'details'
      else if (firstKey === 'title' || firstKey === 'locationArea' || firstKey === 'type') currentJobStep.value = 'basic'
      return
    }

    const payload = buildJobPayload('active')
    if (editingJobId.value) {
      await updateInternship(editingJobId.value, payload)
    } else {
      await createInternship(payload)
    }

    success('Job published.', {
      description: 'Students can now find this opening.',
    })
    if (autosaveTimer) clearTimeout(autosaveTimer)
    draftSaveState.value = 'idle'
    jobEditorOpen.value = false
    fillJobForm()
    lastSavedFingerprint = formFingerprint()
  } catch (caughtError) {
    const maybe = caughtError as { errors?: Record<string, string[]>; message?: string }
    if (maybe?.errors) {
      const mapped: Record<string, string> = {}
      for (const [key, messages] of Object.entries(maybe.errors)) {
        mapped[key === 'slots_available' ? 'slotsAvailable' : key === 'location' ? 'locationArea' : key] =
          messages[0] || 'Invalid value'
      }
      publishFieldErrors.value = mapped
    }
    error(caughtError, {
      fallback: 'Unable to publish the job posting.',
    })
  } finally {
    savingJob.value = false
    publishingJob.value = false
  }
}

/** Save edits to an already-published job without changing status to draft. */
async function savePublishedJob() {
  if (savingJob.value || publishingJob.value || !editingJobId.value) return
  savingJob.value = true
  publishFieldErrors.value = {}
  try {
    const scheduleError = jobForm.value.scheduleSlots.length
      ? validateSlots(jobForm.value.scheduleSlots)
      : null
    if (scheduleError) {
      error(scheduleError)
      return
    }
    const fieldErrors = validatePublishFields()
    publishFieldErrors.value = fieldErrors
    if (Object.keys(fieldErrors).length) {
      error('Complete the required fields before saving.')
      return
    }
    const status = normalizeJobStatus(jobForm.value.status)
    await updateInternship(editingJobId.value, buildJobPayload(status === 'draft' ? 'active' : status))
    success('Job updated.', { description: 'Your changes have been saved.' })
    draftSaveState.value = 'idle'
    jobEditorOpen.value = false
    fillJobForm()
    lastSavedFingerprint = formFingerprint()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to save the job posting.' })
  } finally {
    savingJob.value = false
  }
}

async function saveJob() {
  if (isEditingDraft.value) await publishJob()
  else await savePublishedJob()
}

async function publishDraftFromTable(job: JobRow) {
  if (publishingJob.value) return
  await openEditJobDialog(job)
  await nextTick()
  await publishJob()
}

function formatSavedAt(value?: unknown) {
  if (!value) return 'Not saved yet'
  const date = new Date(String(value))
  if (Number.isNaN(date.getTime())) return 'Not saved yet'
  return `Saved ${date.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}`
}

function askDeleteJob(job: JobRow) {
  const isDraft = normalizeJobStatus(job.status) === 'draft'
  pendingDeleteJob.value = {
    id: job.id,
    title: job.title || (isDraft ? 'Untitled draft' : 'Untitled job'),
    applicationCount: job.applicationCount,
    isDraft,
  }
}

async function confirmDeleteJob() {
  if (!pendingDeleteJob.value) return
  deletingJobId.value = pendingDeleteJob.value.id
  try {
    await deleteInternship(pendingDeleteJob.value.id)
    success('Job deleted.', {
      description: 'The posting has been removed.',
    })
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to delete the job posting.',
    })
  } finally {
    deletingJobId.value = null
    pendingDeleteJob.value = null
  }
}

const deleteJobDialogDescription = computed(() => {
  const job = pendingDeleteJob.value
  if (!job) return ''
  if (job.isDraft) {
    return `Delete draft “${job.title}”? This permanently removes the unfinished posting. This cannot be undone.`
  }
  const base = `Delete “${job.title}”? This permanently removes the posting from your workspace.`
  if (job.applicationCount > 0) {
    return `${base} This job has ${job.applicationCount} application${job.applicationCount === 1 ? '' : 's'}. Related applicant records may be affected.`
  }
  return base
})

const statusChangeDialogDescription = computed(() => {
  const pending = pendingStatusChange.value
  if (!pending) return ''
  return `Close “${pending.title}”? New students will no longer be able to apply. Your ${pending.applicationCount} existing applicant${pending.applicationCount === 1 ? '' : 's'} will keep their current progress and will not be removed.`
})

function openApplicantProfile(application: ApplicationRecord, mode: 'profile' | 'tracker' = 'profile') {
  selectedApplicant.value = application
  applicantDialogMode.value = mode
  applicantDialogOpen.value = true
}

async function updateApplicant(applicationId: string, status: ApplicantAction) {
  processingApplicantId.value = applicationId
  try {
    const application = applications.value.find((item) => item.id === applicationId)
    await updateApplicationStatus(applicationId, status)
    await refreshApplications()
    success(status === 'accepted' ? 'Applicant accepted.' : 'Applicant rejected.', {
      description: 'The application status has been updated.',
    })

    // Decision: auto-mark Filled only when accepting fills the last remaining slot.
    // We do not rewrite Open jobs just because the list page loaded.
    if (status === 'accepted' && application?.internshipId) {
      const job = internships.value.find((item) => item.id === application.internshipId)
      if (job && normalizeJobStatus(job.status) === 'active') {
        const total = Math.max(0, Number(job.slotsAvailable) || 0)
        const remainingBefore =
          job.slotsRemaining != null
            ? Number(job.slotsRemaining)
            : job.filledSlots != null
              ? Math.max(0, total - Number(job.filledSlots))
              : null
        if (total > 0 && remainingBefore === 1) {
          await applyJobStatusChange(job.id, 'active', 'filled')
        }
      }
    }
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to update applicant status.',
    })
  } finally {
    processingApplicantId.value = null
  }
}

function canCompanyReview(status?: string | null) {
  return normalizeApplicationStatus(status) === 'endorsed'
}

function handleMenuClick(menuItem: string) {
  const allowedViews: CompanyView[] = ['dashboard', 'internships', 'applications']
  const nextView = allowedViews.includes(menuItem as CompanyView) ? (menuItem as CompanyView) : 'dashboard'
  currentView.value = nextView
  const targetName =
    nextView === 'internships'
      ? 'company-internships'
      : nextView === 'applications'
        ? 'company-applicants'
        : 'dashboard'
  void router.push({ name: targetName })
}

onMounted(() => {
  currentView.value = companyViewFromRouteName(route.name)

  const userId = authStore.user?.uid

  if (!userId) {
    loadingJobs.value = false
    loadingApplications.value = false
    return
  }

  unsubscribeInternships = subscribeCompanyInternships(userId, (items) => {
    internships.value = items
    loadingJobs.value = false
  })

  unsubscribeApplications = subscribeCompanyApplications(userId, (items) => {
    applications.value = items
    loadingApplications.value = false
  })

  void fetchKpiReport()
    .then((report) => {
      kpi.value = report
    })
    .catch(() => {
      kpi.value = null
    })

  void loadCompanyCertificates()
})

async function loadCompanyCertificates() {
  certificatesLoading.value = true
  certificatesError.value = null
  try {
    certificates.value = await listCertificates()
  } catch (error) {
    certificates.value = []
    certificatesError.value = error instanceof Error ? error.message : 'Unable to load certificates.'
  } finally {
    certificatesLoading.value = false
  }
}

async function viewCompanyCertificate(certificateId: string) {
  certificateActionId.value = certificateId
  try {
    await viewCertificateHtml(certificateId)
  } catch (err) {
    error(err instanceof Error ? err.message : 'Unable to open certificate.')
  } finally {
    certificateActionId.value = null
  }
}

async function printCompanyCertificate(certificateId: string) {
  certificateActionId.value = certificateId
  try {
    await printCertificateHtml(certificateId)
  } catch (err) {
    error(err instanceof Error ? err.message : 'Unable to print certificate.')
  } finally {
    certificateActionId.value = null
  }
}

onUnmounted(() => {
  unsubscribeInternships?.()
  unsubscribeApplications?.()
  if (autosaveTimer) clearTimeout(autosaveTimer)
  window.removeEventListener('beforeunload', onBeforeUnloadGuard)
})

function onBeforeUnloadGuard(event: BeforeUnloadEvent) {
  if (!jobEditorOpen.value) return
  if (draftSaveState.value !== 'dirty' && draftSaveState.value !== 'saving') return
  event.preventDefault()
  event.returnValue = ''
}

window.addEventListener('beforeunload', onBeforeUnloadGuard)

onBeforeRouteLeave(() => {
  if (!jobEditorOpen.value) return true
  if (draftSaveState.value !== 'dirty' && draftSaveState.value !== 'saving') return true
  return window.confirm('You have unsaved draft changes. Leave this page anyway?')
})

watch(
  jobForm,
  () => {
    markFormDirty()
  },
  { deep: true },
)

watch(
  jobSkills,
  () => {
    markFormDirty()
  },
  { deep: true },
)

watch(
  () => jobForm.value.locationArea,
  (next, previous) => {
    if (next !== previous) {
      jobForm.value.barangay = ''
    }
  },
)

watch(
  () => route.name,
  (name) => {
    currentView.value = companyViewFromRouteName(name)
  },
)
</script>

<template>
  <MainLayout role="company" :title="pageTitle" :active-item="currentView" @navigate="handleMenuClick($event.key)">
    <div class="space-y-6">
      <template v-if="currentView === 'dashboard'">
        <section class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
          <div class="border-b border-border bg-gradient-to-r from-emerald-50/80 dark:from-emerald-950/40 via-card to-accent/60 px-6 py-6 sm:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">
                  <Building2 class="h-5 w-5" />
                </div>
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Overview</p>
                  <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h2 class="text-2xl font-semibold tracking-tight text-foreground">{{ companyName }}</h2>
                    <Badge variant="secondary">
                      {{ hostOrgLabel }}
                    </Badge>
                    <Badge
                      :variant="
                        String(authStore.user?.verificationStatus || '').toLowerCase() === 'approved'
                          ? 'success'
                          : String(authStore.user?.verificationStatus || '').toLowerCase() === 'rejected'
                            ? 'destructive'
                            : 'warning'
                      "
                    >
                      {{ verificationStatusLabel(String(authStore.user?.verificationStatus || 'pending')) }}
                    </Badge>
                  </div>
                  <p class="mt-1 text-sm text-muted-foreground">
                    {{ dashboardStats[2]?.value || 0 }} applicant{{ (dashboardStats[2]?.value || 0) === 1 ? '' : 's' }} need review ·
                    {{ dashboardStats[0]?.value || 0 }} open position{{ (dashboardStats[0]?.value || 0) === 1 ? '' : 's' }}
                  </p>
                </div>
              </div>
              <div class="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" @click="handleMenuClick('applications')">Review applicants</Button>
                <Button size="sm" @click="void openCreateJobDialog()">Post a job</Button>
              </div>
            </div>
          </div>

          <div class="grid gap-px bg-muted sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="stat in dashboardStats" :key="stat.label" class="bg-card px-5 py-5">
              <template v-if="loadingJobs || loadingApplications">
                <Skeleton class="h-9 w-9 rounded-xl" />
                <Skeleton class="mt-4 h-3 w-24" />
                <Skeleton class="mt-2 h-8 w-14" />
              </template>
              <template v-else>
                <div class="flex items-center justify-between gap-3">
                  <div :class="['flex h-9 w-9 items-center justify-center rounded-xl', stat.iconClass]">
                    <component :is="stat.icon" class="h-4 w-4" />
                  </div>
                  <p class="text-xs font-medium text-muted-foreground">{{ stat.caption }}</p>
                </div>
                <p class="mt-4 text-sm font-medium text-muted-foreground">{{ stat.label }}</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight text-foreground">{{ stat.value }}</p>
              </template>
            </div>
          </div>
        </section>

        <section v-if="kpi" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="inline-flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
              Placement rate
              <span
                class="inline-flex h-4 w-4 cursor-help items-center justify-center rounded-full border border-border text-[10px] font-semibold normal-case tracking-normal"
                title="Share of your students or applicants who reached an accepted placement."
              >?</span>
            </p>
            <p class="mt-2 text-2xl font-semibold text-foreground">
              {{ kpi.placementRate == null ? '—' : `${kpi.placementRate}%` }}
            </p>
          </div>
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Agreements</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">{{ kpi.totalAgreements }}</p>
          </div>
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Avg approved OJT hrs</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">
              {{ kpi.avgApprovedOjtHours == null ? '—' : kpi.avgApprovedOjtHours }}
            </p>
          </div>
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="inline-flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
              Avg assessment score
              <span
                class="inline-flex h-4 w-4 cursor-help items-center justify-center rounded-full border border-border text-[10px] font-semibold normal-case tracking-normal"
                title="Average overall assessment score on a 0–100 scale."
              >?</span>
            </p>
            <p class="mt-2 text-2xl font-semibold text-foreground">
              {{ kpi.avgAssessmentScore == null ? '—' : kpi.avgAssessmentScore }}
            </p>
          </div>
        </section>

        <section class="rounded-2xl border border-border bg-card shadow-sm">
          <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
            <div>
              <h3 class="text-lg font-semibold text-foreground">Certificates</h3>
              <p class="text-sm text-muted-foreground">Issued certificates for interns hosted by your company.</p>
            </div>
            <Button size="sm" variant="outline" :disabled="certificatesLoading" @click="void loadCompanyCertificates()">
              Refresh
            </Button>
          </div>
          <div class="divide-y divide-border">
            <div v-if="certificatesLoading" class="space-y-2 px-5 py-4">
              <Skeleton class="h-4 w-48" />
              <Skeleton class="h-3 w-64" />
            </div>
            <p v-else-if="certificatesError" class="px-5 py-4 text-sm text-destructive">{{ certificatesError }}</p>
            <p v-else-if="certificates.length === 0" class="px-5 py-4 text-sm text-muted-foreground">
              No certificates issued yet for your company’s placements.
            </p>
            <div
              v-for="certificate in certificates"
              :key="certificate.id"
              class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
            >
              <div class="min-w-0">
                <p class="font-medium text-foreground">{{ toTitleCaseName(certificate.studentName) || 'Intern' }}</p>
                <p class="text-sm text-muted-foreground">
                  {{ certificate.internshipTitle || 'Internship' }}
                  <span v-if="certificate.issuedAt"> · Issued {{ formatDate(certificate.issuedAt) }}</span>
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  :disabled="certificateActionId === certificate.id"
                  @click="void viewCompanyCertificate(certificate.id)"
                >
                  View Certificate
                </Button>
                <Button
                  size="sm"
                  :disabled="certificateActionId === certificate.id"
                  @click="void printCompanyCertificate(certificate.id)"
                >
                  Print Certificate
                </Button>
              </div>
            </div>
          </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
          <section class="rounded-2xl border border-border bg-card shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
              <div>
                <h3 class="text-lg font-semibold text-foreground">Needs attention</h3>
                <p class="text-sm text-muted-foreground">Applicants waiting for a company decision.</p>
              </div>
              <Button size="sm" variant="outline" @click="handleMenuClick('applications')">View all</Button>
            </div>
            <div class="divide-y divide-slate-100">
              <template v-if="loadingApplications">
                <div v-for="index in 3" :key="index" class="space-y-2 px-5 py-4">
                  <Skeleton class="h-4 w-40" />
                  <Skeleton class="h-3 w-56" />
                </div>
              </template>
              <template v-else-if="attentionApplicants.length">
                <div
                  v-for="item in attentionApplicants"
                  :key="item.id"
                  class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-foreground">{{ toTitleCaseName(item.studentName) || 'Unnamed applicant' }}</p>
                    <p class="truncate text-sm text-muted-foreground">
                      {{ item.internshipTitle || 'Untitled role' }} · {{ item.schoolName || 'School' }}
                    </p>
                  </div>
                  <Button size="sm" variant="outline" class="shrink-0" @click="openApplicantProfile(item, 'tracker')">
                    Open tracker
                  </Button>
                </div>
              </template>
              <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                No applicants need review right now.
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-foreground">Quick actions</h3>
            <p class="mt-1 text-sm text-muted-foreground">Jump to common hiring workflows.</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
              <button
                v-for="action in compactQuickActions"
                :key="action.label"
                type="button"
                class="flex items-center gap-3 rounded-xl border border-border px-4 py-3 text-left transition hover:border-emerald-800 hover:bg-emerald-950/30"
                @click="action.action()"
              >
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-muted text-foreground">
                  <component :is="action.icon" class="h-4 w-4" />
                </span>
                <span class="text-sm font-medium text-foreground">{{ action.label }}</span>
              </button>
            </div>
          </section>
        </div>
      </template>

      <Card v-else-if="currentView === 'internships'" class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h2 class="text-2xl font-semibold text-foreground">Posted Jobs</h2>
              <p class="text-sm text-muted-foreground">Create openings, manage availability, and monitor how many applicants each role is bringing in.</p>
            </div>
            <Button class="gap-2 self-start" @click="openCreateJobDialog">
              <Plus class="h-4 w-4" />
              Post Job
            </Button>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="searchJobs" placeholder="Search posted jobs..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent class="space-y-6">
          <div v-if="jobEditorOpen" ref="jobEditorRef">
            <Card class="border-border bg-accent/40 shadow-none">
            <CardHeader class="space-y-2">
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <h3 class="text-xl font-semibold text-foreground">
                    {{
                      isEditingDraft
                        ? editingJobId
                          ? 'Continue draft'
                          : 'Post a job'
                        : 'Edit Job Posting'
                    }}
                  </h3>
                  <p class="text-sm text-muted-foreground">
                    {{
                      isEditingDraft
                        ? 'Drafts autosave as you type. Only your company can see them until you publish.'
                        : 'Update this posting. Published jobs with applications cannot be converted back to a draft.'
                    }}
                  </p>
                  <p
                    v-if="draftSaveLabel"
                    class="mt-2 text-xs font-medium"
                    :class="draftSaveState === 'error' ? 'text-destructive' : 'text-muted-foreground'"
                    aria-live="polite"
                  >
                    {{ draftSaveLabel }}
                  </p>
                </div>
                <Button type="button" variant="outline" class="self-start" @click="closeJobEditor">
                  Cancel
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div class="space-y-6">
                <div class="grid gap-3 rounded-2xl border border-border bg-card p-4 md:grid-cols-4">
                  <button
                    v-for="(step, index) in wizardSteps"
                    :key="step.id"
                    type="button"
                    class="rounded-2xl border p-4 text-left transition"
                    :class="currentJobStep === step.id ? 'border-primary/40 bg-accent shadow-sm' : 'border-border bg-muted hover:border-border'"
                    @click="currentJobStep = step.id"
                  >
                    <p class="text-xs font-semibold uppercase tracking-[0.2em]" :class="currentJobStep === step.id ? 'text-primary' : 'text-muted-foreground'">
                      Step {{ index + 1 }}
                    </p>
                    <p class="mt-2 text-sm font-semibold text-foreground">{{ step.label }}</p>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ step.caption }}</p>
                  </button>
                </div>

                <section v-if="currentJobStep === 'basic'" class="grid gap-4 rounded-2xl border border-border bg-card p-5 sm:grid-cols-2">
                  <div class="sm:col-span-2">
                    <p class="text-sm font-semibold text-foreground">Basic Information</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                      Start with the job title, then confirm the {{ hostOrgLabel.toLowerCase() }} profile details and Cavite location.
                    </p>
                  </div>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobTitle">Job title</FormLabel>
                    <FormControl>
                      <Input
                        id="jobTitle"
                        v-model="jobForm.title"
                        placeholder="Software Engineering Intern"
                        :aria-invalid="!!publishFieldErrors.title"
                        @blur="onJobFieldBlur"
                      />
                    </FormControl>
                    <p v-if="publishFieldErrors.title" class="mt-1 text-xs text-destructive">{{ publishFieldErrors.title }}</p>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobCompanyName">{{ hostOrgLabel }} Name</FormLabel>
                    <FormControl>
                      <Input id="jobCompanyName" v-model="jobForm.companyName" readonly class="bg-muted text-muted-foreground" />
                    </FormControl>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobIndustry">Industry Based on {{ hostOrgLabel }} Profile</FormLabel>
                    <FormControl>
                      <Input id="jobIndustry" v-model="jobForm.industry" readonly class="bg-muted text-muted-foreground" />
                    </FormControl>
                  </FormItem>
                  <FormItem class="sm:col-span-2">
                    <FormLabel>Work Setup</FormLabel>
                    <FormControl>
                      <div class="grid gap-3 md:grid-cols-4">
                        <button
                          v-for="option in workSetupOptions"
                          :key="option.value"
                          type="button"
                          class="rounded-2xl border p-4 text-left transition"
                          :class="[
                            jobForm.type === option.value ? 'border-primary/40 bg-accent shadow-sm' : 'border-border bg-muted hover:border-border',
                            publishFieldErrors.type ? 'ring-1 ring-destructive/40' : '',
                          ]"
                          @click="jobForm.type = option.value; onJobFieldBlur()"
                        >
                          <p class="text-sm font-semibold text-foreground">{{ option.label }}</p>
                          <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ option.description }}</p>
                        </button>
                      </div>
                    </FormControl>
                    <p v-if="publishFieldErrors.type" class="mt-1 text-xs text-destructive">{{ publishFieldErrors.type }}</p>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobLocationArea">City or Municipality in Cavite</FormLabel>
                    <FormControl>
                      <Select id="jobLocationArea" v-model="jobForm.locationArea" @blur="onJobFieldBlur">
                        <option value="">Select a location</option>
                        <optgroup v-for="group in caviteLocationGroups" :key="group.label" :label="group.label">
                          <option v-for="option in group.options" :key="option" :value="option">{{ option }}</option>
                        </optgroup>
                      </Select>
                    </FormControl>
                    <p v-if="publishFieldErrors.locationArea" class="mt-1 text-xs text-destructive">{{ publishFieldErrors.locationArea }}</p>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobBarangay">Barangay</FormLabel>
                    <FormControl>
                      <Select id="jobBarangay" v-model="jobForm.barangay" :disabled="!jobForm.locationArea" @blur="onJobFieldBlur">
                        <option value="">{{ availableBarangays.length ? 'Select a barangay' : 'Select a city or municipality first' }}</option>
                        <option v-for="option in availableBarangays" :key="option" :value="option">{{ option }}</option>
                      </Select>
                    </FormControl>
                  </FormItem>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobLocationDetails">Specific Address or Landmark</FormLabel>
                    <FormControl>
                      <Input
                        id="jobLocationDetails"
                        v-model="jobForm.locationDetails"
                        placeholder="Building, street, or landmark"
                        @blur="onJobFieldBlur"
                      />
                    </FormControl>
                  </FormItem>
                </section>

                <section v-else-if="currentJobStep === 'details'" class="space-y-6">
                  <div class="grid gap-4 rounded-2xl border border-border bg-card p-5 sm:grid-cols-2 lg:grid-cols-3">
                  <div class="sm:col-span-2 lg:col-span-3">
                    <p class="text-sm font-semibold text-foreground">Internship Details</p>
                    <p class="mt-1 text-sm text-muted-foreground">Set the duration and slots. Allowance can be left blank.</p>
                  </div>
                  <FormItem>
                    <FormLabel for="jobDuration">Duration</FormLabel>
                    <FormControl>
                      <Input
                        id="jobDuration"
                        v-model="jobForm.duration"
                        list="job-duration-suggestions"
                        placeholder="e.g. 120"
                        @blur="onJobFieldBlur"
                      />
                      <datalist id="job-duration-suggestions">
                        <option v-for="option in commonDurationOptions" :key="option" :value="option" />
                      </datalist>
                    </FormControl>
                    <p v-if="publishFieldErrors.duration" class="mt-1 text-xs text-destructive">{{ publishFieldErrors.duration }}</p>
                  </FormItem>
                  <details
                    class="sm:col-span-2 lg:col-span-3 rounded-2xl border border-border bg-muted/40 px-4 py-3"
                    :open="hostHoursAdvancedOpen || !!jobForm.hostRequiredHours"
                    @toggle="hostHoursAdvancedOpen = ($event.target as HTMLDetailsElement).open"
                  >
                    <summary class="cursor-pointer text-sm font-semibold text-foreground">Advanced</summary>
                    <FormItem class="mt-3">
                      <FormLabel for="jobHostHours">Different hours from the school requirement? (optional)</FormLabel>
                      <FormControl>
                        <Input
                          id="jobHostHours"
                          v-model="jobForm.hostRequiredHours"
                          type="number"
                          min="1"
                          max="10000"
                          placeholder="e.g. 300"
                        />
                      </FormControl>
                      <p class="mt-1 text-xs text-muted-foreground">
                        Optional. If this differs from the student’s school course requirement, an hours agreement is created when they apply. Leave blank for no comparison. Max 10,000.
                      </p>
                    </FormItem>
                  </details>
                  <FormItem>
                    <FormLabel for="jobSlots">Slots</FormLabel>
                    <FormControl>
                      <Input
                        id="jobSlots"
                        v-model.number="jobForm.slotsAvailable"
                        type="number"
                        min="1"
                        @blur="onJobFieldBlur"
                      />
                    </FormControl>
                    <p v-if="publishFieldErrors.slotsAvailable" class="mt-1 text-xs text-destructive">{{ publishFieldErrors.slotsAvailable }}</p>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobAllowance">Allowance (optional)</FormLabel>
                    <FormControl>
                      <div class="relative">
                        <span
                          class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-medium text-muted-foreground"
                          aria-hidden="true"
                        >₱</span>
                        <Input
                          id="jobAllowance"
                          v-model="jobForm.allowance"
                          class="pl-9"
                          placeholder="5,000 / month"
                        />
                      </div>
                    </FormControl>
                    <p class="mt-1 text-xs text-muted-foreground">
                      Leave blank if this posting has no allowance.
                    </p>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobStartDate">Start Date</FormLabel>
                    <FormControl>
                      <div class="relative">
                        <CalendarDays class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input id="jobStartDate" v-model="jobForm.startDate" type="date" class="pl-10" />
                      </div>
                    </FormControl>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobEndDate">End Date</FormLabel>
                    <FormControl>
                      <div class="relative">
                        <CalendarDays class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input id="jobEndDate" v-model="jobForm.endDate" type="date" class="pl-10" />
                      </div>
                    </FormControl>
                  </FormItem>
                  </div>

                  <div class="grid gap-4 rounded-2xl border border-border bg-card p-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                      <p class="text-sm font-semibold text-foreground">Default schedule</p>
                      <p class="mt-1 text-sm text-muted-foreground">
                        Days and times copied to accepted students. Leave empty to show “Not set” on the posting.
                      </p>
                    </div>
                    <div class="sm:col-span-2">
                      <ScheduleEditor
                        :model-value="jobForm.scheduleSlots"
                        @update:model-value="syncScheduleFromSlots"
                      />
                    </div>
                    <FormItem>
                      <FormLabel for="jobScheduleType">Schedule Type</FormLabel>
                      <FormControl>
                        <Select id="jobScheduleType" v-model="jobForm.scheduleType">
                          <option value="fixed">Fixed</option>
                          <option value="rotating">Rotating</option>
                          <option value="flexible">Flexible</option>
                        </Select>
                      </FormControl>
                    </FormItem>
                    <FormItem>
                      <FormLabel for="jobWeeklyHours">Weekly Hours</FormLabel>
                      <FormControl>
                        <Input id="jobWeeklyHours" v-model="jobForm.weeklyHours" type="number" min="0" max="168" step="0.5" placeholder="e.g. 40" />
                      </FormControl>
                    </FormItem>
                    <FormItem class="sm:col-span-2">
                      <label class="inline-flex items-center gap-2 text-sm text-foreground">
                        <input v-model="jobForm.isFlexible" type="checkbox" class="h-4 w-4 rounded border-border" />
                        Allow flexible schedule adjustments
                      </label>
                    </FormItem>
                  </div>

                  <section class="grid gap-4 rounded-2xl border border-border bg-card p-5 sm:grid-cols-2">
                  <div class="sm:col-span-2">
                    <p class="text-sm font-semibold text-foreground">Role Description</p>
                    <p class="mt-1 text-sm text-muted-foreground">These fields help schools evaluate whether the internship is aligned with their students and course outcomes.</p>
                  </div>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobDescription">Overview</FormLabel>
                    <FormControl>
                      <textarea
                        id="jobDescription"
                        v-model="jobForm.description"
                        rows="4"
                        class="flex min-h-[110px] w-full rounded-md border border-input bg-background px-3 py-2 text-[16px] ring-offset-background md:text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        :class="publishFieldErrors.description ? 'border-destructive' : ''"
                        placeholder="Describe the role, team, and internship environment."
                        @blur="onJobFieldBlur"
                      />
                    </FormControl>
                    <p v-if="publishFieldErrors.description" class="mt-1 text-xs text-destructive">{{ publishFieldErrors.description }}</p>
                  </FormItem>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobTasks">Key Tasks and Responsibilities</FormLabel>
                    <FormControl>
                      <textarea
                        id="jobTasks"
                        v-model="jobForm.tasks"
                        rows="4"
                        class="flex min-h-[110px] w-full rounded-md border border-input bg-background px-3 py-2 text-[16px] ring-offset-background md:text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        placeholder="List the core tasks the intern will handle."
                      />
                    </FormControl>
                  </FormItem>
                  <FormItem class="sm:col-span-2">
                    <FormLabel>Skills for this role</FormLabel>
                    <FormControl>
                      <div class="space-y-4 rounded-2xl border border-border bg-muted/80 p-4">
                        <p class="text-sm text-muted-foreground">
                          Choose up to 10 skills from the shared list. Mark which ones are required and which are nice to have.
                        </p>
                        <SkillChips
                          :skills="jobSkills"
                          mode="job"
                          empty-text="No skills selected yet."
                        />
                        <Button type="button" variant="outline" class="bg-card" @click="skillsWizardOpen = true">
                          {{ jobSkills.length ? 'Edit skills' : 'Add skills' }}
                        </Button>
                      </div>
                    </FormControl>
                  </FormItem>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobInternGains">What the Intern Will Gain</FormLabel>
                    <FormControl>
                      <textarea
                        id="jobInternGains"
                        v-model="jobForm.internGains"
                        rows="3"
                        class="flex min-h-[90px] w-full rounded-md border border-input bg-background px-3 py-2 text-[16px] ring-offset-background md:text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        placeholder="Describe the mentorship, tools, and experience the intern will gain."
                      />
                    </FormControl>
                  </FormItem>
                  </section>
                </section>

                <section v-else-if="currentJobStep === 'programs'" class="grid gap-4 rounded-2xl border border-border bg-card p-5">
                  <div class="sm:col-span-2 flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p class="text-sm font-semibold text-foreground">Accepted Programs</p>
                      <p class="mt-1 text-sm text-muted-foreground">
                        Select the courses this job is open to. These come from your {{ hostOrgLabel.toLowerCase() }} profile.
                      </p>
                    </div>
                    <Button
                      v-if="companyAcceptedCourses.length"
                      type="button"
                      size="sm"
                      variant="outline"
                      @click="selectAllAcceptedCourses"
                    >
                      Select all
                    </Button>
                  </div>
                  <div v-if="companyAcceptedCourses.length" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <button
                      v-for="course in companyAcceptedCourses"
                      :key="course"
                      type="button"
                      class="rounded-2xl border p-4 text-left transition"
                      :class="asList(jobForm.eligibleCoursesText).includes(course)
                        ? 'border-primary bg-accent shadow-sm ring-2 ring-primary/30'
                        : 'border-border bg-muted hover:border-border'"
                      :aria-pressed="asList(jobForm.eligibleCoursesText).includes(course)"
                      @click="asList(jobForm.eligibleCoursesText).includes(course) ? removeListItem('eligibleCoursesText', course) : addListItem('eligibleCoursesText', course)"
                    >
                      <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold text-foreground">{{ course }}</p>
                        <span
                          class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded border"
                          :class="asList(jobForm.eligibleCoursesText).includes(course)
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-background'"
                          aria-hidden="true"
                        >
                          <CheckCircle2 v-if="asList(jobForm.eligibleCoursesText).includes(course)" class="h-3 w-3" />
                        </span>
                      </div>
                      <p class="mt-1 text-xs text-muted-foreground">
                        {{ asList(jobForm.eligibleCoursesText).includes(course) ? 'Selected' : 'Click to select' }}
                      </p>
                    </button>
                  </div>
                  <div v-else class="rounded-2xl border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900">
                    No accepted courses are saved on the {{ hostOrgLabel.toLowerCase() }} account yet. Add them in settings first.
                  </div>
                </section>

                <section v-else class="grid gap-4 rounded-2xl border border-border bg-card p-5 sm:grid-cols-2">
                  <div class="sm:col-span-2">
                    <p class="text-sm font-semibold text-foreground">Requirements</p>
                    <p class="mt-1 text-sm text-muted-foreground">Document requirements, how to apply, and who schools or students should contact.</p>
                  </div>
                  <div class="sm:col-span-2 rounded-2xl border border-border bg-muted px-4 py-4 text-sm text-foreground">
                    <p class="font-semibold">Required document</p>
                    <p class="mt-1 text-muted-foreground">
                      Students attach their school endorsement letter. Their resume is shown to you automatically.
                    </p>
                    <p class="mt-3 inline-flex rounded-full border border-border bg-background px-3 py-1 text-xs font-semibold">
                      Endorsement
                    </p>
                  </div>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobApplicationInstructions">How to Apply</FormLabel>
                    <FormControl>
                      <textarea
                        id="jobApplicationInstructions"
                        v-model="jobForm.applicationInstructions"
                        rows="3"
                        class="flex min-h-[90px] w-full rounded-md border border-input bg-background px-3 py-2 text-[16px] ring-offset-background md:text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        placeholder="Explain the application steps, review process, or endorsement instructions."
                      />
                    </FormControl>
                  </FormItem>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobContact">Contact Information</FormLabel>
                    <FormControl>
                      <Input id="jobContact" v-model="jobForm.contactInfo" readonly class="bg-muted text-muted-foreground" />
                    </FormControl>
                  </FormItem>
                </section>
              </div>

              <div class="mt-6 flex flex-col gap-3 border-t border-border pt-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1 text-sm text-muted-foreground">
                  <p>Step {{ wizardStepIndex + 1 }} of {{ wizardSteps.length }}</p>
                  <p v-if="draftSaveLabel" class="text-xs" aria-live="polite">{{ draftSaveLabel }}</p>
                </div>
                <div class="flex flex-wrap justify-end gap-3">
                  <Button type="button" variant="outline" @click="closeJobEditor">
                    Cancel
                  </Button>
                  <Button v-if="!isFirstJobStep" type="button" variant="outline" class="gap-2" @click="moveToPreviousJobStep">
                    <ChevronLeft class="h-4 w-4" />
                    Back
                  </Button>
                  <Button v-if="!isLastJobStep" type="button" class="gap-2" @click="moveToNextJobStep">
                    Next
                    <ChevronRight class="h-4 w-4" />
                  </Button>
                  <template v-else>
                    <Button
                      v-if="isEditingDraft"
                      type="button"
                      variant="outline"
                      :disabled="draftSaveState === 'saving'"
                      @click="void saveDraft({ force: true })"
                    >
                      <LoaderCircle v-if="draftSaveState === 'saving'" class="h-4 w-4 animate-spin" />
                      <span>{{ draftSaveState === 'saving' ? 'Saving...' : 'Save as draft' }}</span>
                    </Button>
                    <Button
                      type="button"
                      :disabled="savingJob || publishingJob"
                      @click="isEditingDraft ? publishJob() : savePublishedJob()"
                    >
                      <LoaderCircle v-if="savingJob || publishingJob" class="h-4 w-4 animate-spin" />
                      <span>
                        {{
                          publishingJob || savingJob
                            ? isEditingDraft
                              ? 'Publishing...'
                              : 'Saving...'
                            : isEditingDraft
                              ? 'Publish job'
                              : 'Save Changes'
                        }}
                      </span>
                    </Button>
                  </template>
                </div>
              </div>
            </CardContent>
            </Card>
          </div>

          <div v-if="!jobEditorOpen" class="overflow-x-auto rounded-xl border border-border bg-card">
            <Table class="min-w-[44rem]">
              <TableHeader>
                <TableRow>
                  <TableHead>Job Title</TableHead>
                  <TableHead>Slots</TableHead>
                  <TableHead>Applications</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead class="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="loadingJobs || loadingApplications">
                  <TableRow v-for="index in 5" :key="index">
                    <TableCell><Skeleton class="h-5 w-44" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-20" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-12" /></TableCell>
                    <TableCell><Skeleton class="h-7 w-20 rounded-full" /></TableCell>
                    <TableCell><Skeleton class="ml-auto h-8 w-28 rounded-md" /></TableCell>
                  </TableRow>
                </template>
                <template v-else-if="jobRows.length">
                  <TableRow v-for="job in jobRows" :key="job.id" class="align-middle">
                    <TableCell class="py-3">
                      <div class="min-w-0 max-w-[14rem] space-y-0.5 sm:max-w-none">
                        <p class="truncate font-medium text-foreground">{{ job.title }}</p>
                        <p class="truncate text-sm text-muted-foreground">
                          {{
                            normalizeJobStatus(job.status) === 'draft'
                              ? formatSavedAt(job.updatedAt)
                              : job.location || 'Location not set'
                          }}
                        </p>
                      </div>
                    </TableCell>
                    <TableCell class="whitespace-nowrap py-3 text-sm text-foreground">
                      {{ normalizeJobStatus(job.status) === 'draft' ? '—' : slotsFilledLabel(job) }}
                    </TableCell>
                    <TableCell class="py-3 text-sm text-foreground">
                      {{ normalizeJobStatus(job.status) === 'draft' ? '—' : job.applicationCount }}
                    </TableCell>
                    <TableCell class="py-3">
                      <StatusBadgeMenu
                        :model-value="normalizeJobStatus(job.status)"
                        :disabled="statusUpdatingJobId === job.id || publishingJob"
                        :has-applications="job.applicationCount > 0"
                        :aria-label="`Change status for ${job.title || 'job'}`"
                        @select="requestJobStatusChange(job, $event)"
                      />
                    </TableCell>
                    <TableCell class="py-3 text-right">
                      <div class="inline-flex items-center justify-end gap-1">
                        <template v-if="normalizeJobStatus(job.status) === 'draft'">
                          <Button variant="ghost" size="sm" class="h-8 gap-1.5 px-2" @click="openEditJobDialog(job)">
                            <Pencil class="h-4 w-4" />
                            <span class="hidden sm:inline">Continue editing</span>
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 gap-1.5 px-2"
                            :disabled="publishingJob"
                            @click="publishDraftFromTable(job)"
                          >
                            <CheckCircle2 class="h-4 w-4" />
                            <span class="hidden sm:inline">Publish</span>
                          </Button>
                          <RowActionsMenu
                            :label="`More actions for draft ${job.title || ''}`"
                            :items="[
                              {
                                key: 'delete',
                                label: deletingJobId === job.id ? 'Deleting...' : 'Delete',
                                icon: Trash2,
                                destructive: true,
                                disabled: deletingJobId === job.id,
                              },
                            ]"
                            @select="(key) => key === 'delete' && askDeleteJob(job)"
                          />
                        </template>
                        <template v-else>
                          <Button variant="ghost" size="sm" class="h-8 gap-1.5 px-2" @click="openViewJobDialog(job)">
                            <Eye class="h-4 w-4" />
                            <span class="hidden sm:inline">View</span>
                          </Button>
                          <Button variant="ghost" size="sm" class="h-8 gap-1.5 px-2" @click="openEditJobDialog(job)">
                            <Pencil class="h-4 w-4" />
                            <span class="hidden sm:inline">Edit</span>
                          </Button>
                          <RowActionsMenu
                            :label="`More actions for ${job.title || 'job'}`"
                            :items="[
                              {
                                key: 'delete',
                                label: deletingJobId === job.id ? 'Deleting...' : 'Delete',
                                icon: Trash2,
                                destructive: true,
                                disabled: deletingJobId === job.id,
                              },
                            ]"
                            @select="(key) => key === 'delete' && askDeleteJob(job)"
                          />
                        </template>
                      </div>
                    </TableCell>
                  </TableRow>
                </template>
                <TableRow v-else>
                  <TableCell colspan="5" class="py-10 text-center text-muted-foreground">
                    No posted jobs matched your search.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card v-else class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Applicants</h2>
            <p class="text-sm text-muted-foreground">Review endorsed applicants, open their profile summary, and make the final company decision.</p>
          </div>
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
              <Button
                v-for="tab in [
                  { id: 'all', label: 'All' },
                  { id: 'needs_review', label: 'Needs review' },
                  { id: 'interview', label: 'Interview' },
                  { id: 'accepted', label: 'Accepted' },
                  { id: 'rejected', label: 'Rejected' },
                  { id: 'withdrawn', label: 'Withdrawn' },
                ] as const"
                :key="tab.id"
                size="sm"
                :variant="applicantStatusTab === tab.id ? 'default' : 'outline'"
                @click="applicantStatusTab = tab.id"
              >
                {{ tab.label }}
              </Button>
            </div>
            <div class="relative max-w-md flex-1">
              <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input v-model="searchApplicants" placeholder="Search applicants..." class="pl-9" />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <Table class="min-w-[44rem]">
              <TableHeader>
                <TableRow>
                  <TableHead>Intern Name</TableHead>
                  <TableHead>School</TableHead>
                  <TableHead>Date Applied</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead class="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="loadingApplications">
                  <TableRow v-for="index in 5" :key="index">
                    <TableCell><Skeleton class="h-5 w-40" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-32" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-24" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-20" /></TableCell>
                    <TableCell><Skeleton class="ml-auto h-10 w-52" /></TableCell>
                  </TableRow>
                </template>
                <template v-else-if="applicantRows.length">
                  <TableRow v-for="applicant in applicantRows" :key="applicant.id">
                    <TableCell>
                      <div class="space-y-1">
                        <p class="font-medium text-foreground">{{ toTitleCaseName(applicant.studentName) || 'Unnamed intern' }}</p>
                        <p class="text-sm text-muted-foreground">{{ applicant.internshipTitle || 'Untitled role' }}</p>
                      </div>
                    </TableCell>
                    <TableCell class="text-foreground">{{ applicant.schoolName || 'School not provided' }}</TableCell>
                    <TableCell class="text-muted-foreground">{{ formatDate(applicant.createdAt) }}</TableCell>
                    <TableCell>
                      <Badge :variant="applicantBadgeVariant(applicant.trackerStage || applicant.status)">{{ applicantProgressLabel(applicant) }}</Badge>
                    </TableCell>
                    <TableCell class="text-right">
                      <div class="inline-flex items-center justify-end gap-1">
                        <Button
                          size="sm"
                          class="h-8 gap-1.5 px-2"
                          @click="openApplicantProfile(applicant, 'tracker')"
                        >
                          Review
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          class="h-8 gap-1.5 px-2"
                          @click="openApplicantProfile(applicant, 'profile')"
                        >
                          <Eye class="h-4 w-4" />
                          <span class="hidden sm:inline">View</span>
                        </Button>
                        <RowActionsMenu
                          :label="`More actions for ${applicant.studentName || 'applicant'}`"
                          :items="[{ key: 'tracker', label: 'Open tracker', icon: FileText }]"
                          @select="(key) => key === 'tracker' && openApplicantProfile(applicant, 'tracker')"
                        />
                      </div>
                    </TableCell>
                  </TableRow>
                </template>
                <TableRow v-else>
                  <TableCell colspan="5" class="py-10 text-center text-muted-foreground">
                    No applicants matched your search.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>

    <Dialog v-model:open="applicantDialogOpen" :hide-header-bar="applicantDialogMode === 'profile'">
      <template #default="{ close }">
        <DialogHeader v-if="applicantDialogMode === 'tracker'">
          <DialogTitle>Application tracker</DialogTitle>
          <p class="text-sm text-muted-foreground">Progress stages for this application. Schedule interview, assess, and accept or reject here.</p>
        </DialogHeader>
        <div v-else class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-foreground">Applicant profile</h2>
            <p class="text-sm text-muted-foreground">Personal details and documents for this applicant.</p>
          </div>
          <Button variant="ghost" size="icon" class="shrink-0 text-muted-foreground" @click="close">
            <XCircle class="h-4 w-4" />
          </Button>
        </div>

        <div v-if="selectedApplicant && applicantDialogMode === 'profile'" class="mt-6 grid gap-4 sm:grid-cols-2">
          <div class="rounded-2xl bg-muted p-4">
            <p class="text-sm font-medium text-muted-foreground">Intern Name</p>
            <p class="mt-2 text-sm font-semibold text-foreground">{{ toTitleCaseName(selectedApplicant.studentName) || 'Not provided' }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <p class="text-sm font-medium text-muted-foreground">Email</p>
            <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedApplicant.studentEmail || 'Not provided' }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <p class="text-sm font-medium text-muted-foreground">School</p>
            <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedApplicant.schoolName || 'Not provided' }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <p class="text-sm font-medium text-muted-foreground">Course</p>
            <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedApplicant.studentCourse || 'Not provided' }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <p class="text-sm font-medium text-muted-foreground">Applied Role</p>
            <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedApplicant.internshipTitle || 'Not provided' }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <p class="text-sm font-medium text-muted-foreground">Current Status</p>
            <div class="mt-2">
              <Badge :variant="applicantBadgeVariant(selectedApplicant.trackerStage || selectedApplicant.status)">{{ applicantProgressLabel(selectedApplicant) }}</Badge>
            </div>
          </div>
          <div class="rounded-2xl bg-muted p-4 sm:col-span-2">
            <p class="text-sm font-medium text-muted-foreground">Documents</p>
            <div class="mt-2 space-y-3">
              <div v-if="selectedApplicant.resume" class="flex flex-wrap items-center gap-3">
                <span class="text-sm font-medium text-foreground">Resume</span>
                <FilePreviewActions
                  file-name="Resume"
                  :source="selectedApplicant.resume"
                />
              </div>
              <div
                v-for="(doc, index) in selectedApplicant.documents || []"
                :key="`${doc}-${index}`"
                class="flex flex-wrap items-center gap-3"
              >
                <span class="text-sm font-medium text-foreground">
                  {{ index === 0 ? 'Endorsement letter' : `Document ${index + 1}` }}
                </span>
                <FilePreviewActions
                  :file-name="index === 0 ? 'Endorsement letter' : `Document ${index + 1}`"
                  :source="doc"
                />
              </div>
              <p
                v-if="!selectedApplicant.resume && !(selectedApplicant.documents || []).length"
                class="text-sm text-muted-foreground"
              >
                No resume or endorsement letter attached yet.
              </p>
            </div>
          </div>
          <div class="rounded-2xl bg-muted p-4 sm:col-span-2">
            <p class="text-sm font-medium text-muted-foreground">Skills</p>
            <div class="mt-2">
              <SkillChips
                :skills="(selectedApplicant.studentSkills || []).map((skill) => ({
                  id: skill.id,
                  name: skill.name,
                  category: skill.category || 'Other',
                  status: (skill.status as any) || 'approved',
                  level: (skill.level as any) || 'beginner',
                }))"
                mode="intern"
                empty-text="This applicant has not added skills yet."
              />
            </div>
          </div>
        </div>

        <div v-if="selectedApplicant && applicantDialogMode === 'tracker'" class="mt-6">
          <ApplicationTrackerPanel
            :application-id="selectedApplicant.id"
            mode="manage"
            @updated="onTrackerUpdated"
          />
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <Button variant="outline" @click="close">Close</Button>
        </div>
      </template>
    </Dialog>

    <Dialog v-model:open="jobDetailsOpen">
      <template #default>
        <DialogHeader>
          <DialogTitle>{{ selectedJob?.title || 'Job Posting' }}</DialogTitle>
          <p class="text-sm text-muted-foreground">Review the posted job details without entering edit mode.</p>
        </DialogHeader>

        <div v-if="selectedJob" class="mt-6 space-y-6">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">{{ hostOrgLabel }}</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedJob.companyName || 'Not provided' }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">Status</p>
              <div class="mt-2">
                <StatusBadgeMenu
                  :model-value="normalizeJobStatus(selectedJob.status)"
                  :disabled="statusUpdatingJobId === selectedJob.id"
                  aria-label="Change job status"
                  @select="requestJobStatusChange(selectedJob, $event)"
                />
                <p class="mt-2 text-xs text-muted-foreground">
                  Closed stops new applications. Filled means all slots are taken.
                </p>
              </div>
            </div>
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">Location</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedJob.location || 'Not provided' }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">Work Setup</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedJob.type || 'Not provided' }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">Duration</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedJob.duration || 'Not provided' }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">Slots</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ slotsFilledLabel(selectedJob) }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">Applicants</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedJob.applicationCount ?? 0 }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">Allowance</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ formatAllowanceDisplay(selectedJob.allowance) }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">Start Date</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ formatDate(selectedJob.startDate) }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">End Date</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ formatDate(selectedJob.endDate) }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4 sm:col-span-2">
              <p class="text-sm font-medium text-muted-foreground">Schedule</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedJob.schedule || 'Not provided' }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4 sm:col-span-2">
              <p class="text-sm font-medium text-muted-foreground">Description</p>
              <p class="mt-2 whitespace-pre-line text-sm text-foreground">{{ selectedJob.description || 'Not provided' }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4 sm:col-span-2">
              <p class="text-sm font-medium text-muted-foreground">Tasks</p>
              <p class="mt-2 whitespace-pre-line text-sm text-foreground">{{ selectedJob.tasks || 'Not provided' }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4 sm:col-span-2">
              <p class="text-sm font-medium text-muted-foreground">Accepted Programs</p>
              <p class="mt-2 text-sm text-foreground">{{ (selectedJob.eligibleCourses || []).join(', ') || 'Not provided' }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4 sm:col-span-2">
              <p class="text-sm font-medium text-muted-foreground">Required Skills</p>
              <div class="mt-2">
                <SkillChips
                  :skills="(selectedJob.skills || []).map((skill) => ({
                    id: skill.id,
                    name: skill.name,
                    category: skill.category || 'Other',
                    status: (skill.status as any) || 'approved',
                    importance: skill.importance || 'required',
                  }))"
                  mode="job"
                  :empty-text="(selectedJob.requiredSkills || selectedJob.requirements || []).join(', ') || 'Not provided'"
                />
              </div>
            </div>
            <div class="rounded-2xl bg-muted p-4 sm:col-span-2">
              <p class="text-sm font-medium text-muted-foreground">Required Documents</p>
              <p class="mt-2 text-sm text-foreground">Endorsement</p>
            </div>
            <div class="rounded-2xl bg-muted p-4 sm:col-span-2">
              <p class="text-sm font-medium text-muted-foreground">Intern Gains</p>
              <p class="mt-2 whitespace-pre-line text-sm text-foreground">{{ selectedJob.internGains || 'Not provided' }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4 sm:col-span-2">
              <p class="text-sm font-medium text-muted-foreground">How to Apply</p>
              <p class="mt-2 whitespace-pre-line text-sm text-foreground">{{ selectedJob.applicationInstructions || 'Not provided' }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4 sm:col-span-2">
              <p class="text-sm font-medium text-muted-foreground">Contact Information</p>
              <p class="mt-2 text-sm text-foreground">{{ selectedJob.contactInfo || 'Not provided' }}</p>
            </div>
          </div>

          <div class="flex justify-end gap-3">
            <Button variant="outline" @click="closeViewJobDialog">Close</Button>
            <Button @click="editSelectedJobFromView">
              Edit Job
            </Button>
          </div>
        </div>
      </template>
    </Dialog>

    <AlertDialog
      :open="!!pendingDeleteJob"
      :title="pendingDeleteJob?.isDraft ? 'Delete this draft?' : 'Delete job posting?'"
      :description="deleteJobDialogDescription"
      action-label="Delete"
      action-variant="destructive"
      @update:open="(open) => { if (!open) pendingDeleteJob = null }"
      @action="confirmDeleteJob"
    />

    <AlertDialog
      :open="!!pendingStatusChange"
      title="Close this job?"
      :description="statusChangeDialogDescription"
      action-label="Close job"
      @update:open="(open) => { if (!open) pendingStatusChange = null }"
      @action="confirmPendingStatusChange"
    />

    <SkillsWizard
      v-model="jobSkills"
      v-model:open="skillsWizardOpen"
      mode="job"
      :limit="10"
      :recommended-categories="jobSkillsRecommendedCategories"
      @done="() => markFormDirty()"
    />
  </MainLayout>
</template>
