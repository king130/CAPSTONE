<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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
import { caviteBarangaysByLocation, caviteLocationGroups } from '@/config/courseCatalog'
import ApplicationAssessmentPanel from '@/components/ApplicationAssessmentPanel.vue'
import ApplicationInterviewPanel from '@/components/ApplicationInterviewPanel.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import { subscribeCompanyApplications, updateApplicationStatus, type ApplicationRecord } from '@/services/applications'
import { fetchKpiReport, type KpiReport } from '@/services/kpi'
import {
  createInternship,
  deleteInternship,
  subscribeCompanyInternships,
  updateInternship,
  type InternshipRecord,
} from '@/services/internships'
import { useAuthStore } from '@/stores/auth'

type CompanyView = 'dashboard' | 'internships' | 'applications'
type ApplicantAction = 'accepted' | 'rejected'
type JobWizardStep = 'basic' | 'details' | 'programs' | 'requirements'

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
  slotsAvailable: number
  status: 'active' | 'draft' | 'closed'
  allowance: string
  startDate: string
  endDate: string
  scheduleDays: string[]
  scheduleStartTime: string
  scheduleEndTime: string
  scheduleType: string
  weeklyHours: string
  isFlexible: boolean
  tasks: string
  requiredSkillsText: string
  internGains: string
  requiredDocumentsText: string
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
const loadingJobs = ref(true)
const loadingApplications = ref(true)
const searchJobs = ref('')
const searchApplicants = ref('')
const jobEditorOpen = ref(false)
const jobDetailsOpen = ref(false)
const savingJob = ref(false)
const applicantDialogOpen = ref(false)
const selectedJob = ref<(InternshipRecord & { applicationCount?: number }) | null>(null)
const selectedApplicant = ref<ApplicationRecord | null>(null)
const pendingDeleteJobId = ref<string | null>(null)
const deletingJobId = ref<string | null>(null)
const processingApplicantId = ref<string | null>(null)
const editingJobId = ref<string | null>(null)
const kpi = ref<KpiReport | null>(null)
const jobEditorRef = ref<HTMLElement | null>(null)
const currentJobStep = ref<JobWizardStep>('basic')
const chipInput = ref<ChipFieldState>({
  requiredSkill: '',
  requiredDocument: '',
})

const jobForm = ref<JobFormState>(defaultJobForm())

let unsubscribeInternships: (() => void) | null = null
let unsubscribeApplications: (() => void) | null = null

const pageTitle = computed(() => {
  const titles: Record<CompanyView, string> = {
    dashboard: 'Dashboard',
    internships: 'Posted Jobs',
    applications: 'Applicants',
  }
  return titles[currentView.value]
})

const companyName = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return (
    String(profile?.companyName || profile?.name || authStore.user?.displayName || authStore.user?.email || 'Company')
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
const statusOptions = [
  { value: 'active', label: 'Open', description: 'Visible to schools and ready for endorsements.' },
  { value: 'draft', label: 'Draft', description: 'Saved privately until you finish the posting.' },
  { value: 'closed', label: 'Closed', description: 'No longer accepting applicants.' },
]
const wizardSteps: Array<{ id: JobWizardStep; label: string; caption: string }> = [
  { id: 'basic', label: 'Basic Information', caption: 'Title, company details, work setup, and Cavite location' },
  { id: 'details', label: 'Internship Details', caption: 'Hours, slots, dates, schedule, and role overview' },
  { id: 'programs', label: 'Accepted Programs', caption: 'Choose which courses from the company profile are accepted' },
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
      iconClass: 'bg-emerald-100 text-emerald-700',
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

const compactQuickActions = computed(() => [
  {
    label: 'Post a job',
    icon: Plus,
    action: () => {
      void openCreateJobDialog()
    },
  },
  {
    label: 'Review applicants',
    icon: Users,
    action: () => handleMenuClick('applications'),
  },
  {
    label: 'Agreements',
    icon: FileText,
    action: () => router.push({ name: 'agreements' }),
  },
  {
    label: 'Messages',
    icon: MessageSquare,
    action: () => window.dispatchEvent(new CustomEvent('chat:open')),
  },
])

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

const applicantRows = computed(() => {
  const query = searchApplicants.value.trim().toLowerCase()

  return applications.value.filter((application) => {
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
    duration: '486 hours',
    slotsAvailable: 1,
    status: 'active',
    allowance: '',
    startDate: '',
    endDate: '',
    scheduleDays: [],
    scheduleStartTime: '',
    scheduleEndTime: '',
    scheduleType: 'fixed',
    weeklyHours: '',
    isFlexible: false,
    tasks: '',
    requiredSkillsText: '',
    internGains: '',
    requiredDocumentsText: '',
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

function normalizeJobStatus(status?: string | null) {
  const normalized = String(status || 'draft').trim().toLowerCase()
  if (normalized === 'closed') return 'closed'
  if (normalized === 'active') return 'active'
  return 'draft'
}

function normalizeApplicationStatus(status?: string | null) {
  const normalized = String(status || 'submitted').trim().toLowerCase()
  if (['submitted', 'pending'].includes(normalized)) return 'submitted'
  if (normalized === 'endorsed') return 'endorsed'
  if (['accepted', 'approved', 'active'].includes(normalized)) return 'accepted'
  if (['rejected', 'declined', 'cancelled'].includes(normalized)) return 'rejected'
  if (normalized === 'school_rejected') return 'school_rejected'
  return 'submitted'
}

function badgeLabel(status?: string | null) {
  const normalized = normalizeApplicationStatus(status)
  if (normalized === 'submitted') return 'Awaiting School Endorsement'
  if (normalized === 'endorsed') return 'Endorsed'
  if (normalized === 'accepted') return 'Accepted'
  if (normalized === 'school_rejected') return 'Rejected By School'
  return 'Rejected'
}

function jobBadgeVariant(status?: string | null) {
  const normalized = normalizeJobStatus(status)
  if (normalized === 'active') return 'success'
  if (normalized === 'closed') return 'destructive'
  return 'warning'
}

function applicantBadgeVariant(status?: string | null) {
  const normalized = normalizeApplicationStatus(status)
  if (normalized === 'accepted') return 'success'
  if (normalized === 'rejected') return 'destructive'
  if (normalized === 'school_rejected') return 'outline'
  return 'warning'
}

function formatDate(value?: string) {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? '—'
    : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
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

function buildSchedule() {
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
    duration: job.duration || '486 hours',
    slotsAvailable: Number(job.slotsAvailable || 1),
    status: normalizeJobStatus(job.status),
    allowance: job.allowance || '',
    startDate: job.startDate || '',
    endDate: job.endDate || '',
    scheduleDays: job.scheduleDays?.length ? [...job.scheduleDays] : scheduleParts.scheduleDays,
    scheduleStartTime: job.timeIn || scheduleParts.scheduleStartTime,
    scheduleEndTime: job.timeOut || scheduleParts.scheduleEndTime,
    scheduleType: job.scheduleType || 'fixed',
    weeklyHours: job.weeklyHours != null ? String(job.weeklyHours) : '',
    isFlexible: Boolean(job.isFlexible),
    tasks: job.tasks || '',
    requiredSkillsText: (job.requiredSkills || job.requirements || []).join(', '),
    internGains: job.internGains || '',
    requiredDocumentsText: (job.requiredDocuments || []).join(', '),
    applicationInstructions: job.applicationInstructions || '',
    contactInfo: job.contactInfo || companyContactInfo.value,
    eligibleCoursesText: (job.eligibleCourses || companyAcceptedCourses.value).join(', '),
  }
  chipInput.value = { requiredSkill: '', requiredDocument: '' }
  editingJobId.value = job.id
  currentJobStep.value = 'basic'
}

async function openJobEditor() {
  jobEditorOpen.value = true
  await nextTick()
  jobEditorRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function closeJobEditor() {
  jobEditorOpen.value = false
  fillJobForm()
}

async function openCreateJobDialog() {
  fillJobForm()
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

async function refreshApplications() {
  loadingApplications.value = false
}

async function saveJob() {
  savingJob.value = true
  try {
    const eligibleCourses = asList(jobForm.value.eligibleCoursesText)
    const requiredSkills = asList(jobForm.value.requiredSkillsText)
    const requiredDocuments = asList(jobForm.value.requiredDocumentsText)
    const payload = {
      companyId: authStore.user?.uid || '',
      companyName: companyName.value,
      industry: companyIndustry.value || undefined,
      title: jobForm.value.title.trim(),
      description: jobForm.value.description.trim(),
      location: buildLocation(),
      type: jobForm.value.type.trim(),
      duration: jobForm.value.duration.trim(),
      slotsAvailable: Number(jobForm.value.slotsAvailable || 1),
      status: jobForm.value.status,
      eligibleCourses: eligibleCourses.length ? eligibleCourses : companyAcceptedCourses.value,
      requirements: requiredSkills,
      allowance: jobForm.value.allowance.trim() || undefined,
      startDate: jobForm.value.startDate || undefined,
      endDate: jobForm.value.endDate || undefined,
      schedule: buildSchedule() || undefined,
      scheduleType: jobForm.value.scheduleType || undefined,
      weeklyHours: jobForm.value.weeklyHours ? Number(jobForm.value.weeklyHours) : undefined,
      scheduleDays: jobForm.value.scheduleDays,
      timeIn: jobForm.value.scheduleStartTime || undefined,
      timeOut: jobForm.value.scheduleEndTime || undefined,
      isFlexible: jobForm.value.isFlexible,
      tasks: jobForm.value.tasks.trim() || undefined,
      requiredSkills: requiredSkills,
      internGains: jobForm.value.internGains.trim() || undefined,
      requiredDocuments: requiredDocuments,
      applicationInstructions: jobForm.value.applicationInstructions.trim() || undefined,
      contactInfo: companyContactInfo.value || jobForm.value.contactInfo.trim() || undefined,
    }

    if (editingJobId.value) {
      await updateInternship(editingJobId.value, payload)
    } else {
      await createInternship(payload)
    }

    success(editingJobId.value ? 'Job updated.' : 'Job created.', {
      description: 'Your posted jobs list has been refreshed.',
    })
    closeJobEditor()
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to save the job posting.',
    })
  } finally {
    savingJob.value = false
  }
}

async function confirmDeleteJob() {
  if (!pendingDeleteJobId.value) return
  deletingJobId.value = pendingDeleteJobId.value
  try {
    await deleteInternship(pendingDeleteJobId.value)
    success('Job deleted.', {
      description: 'The posting has been removed.',
    })
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to delete the job posting.',
    })
  } finally {
    deletingJobId.value = null
    pendingDeleteJobId.value = null
  }
}

function openApplicantProfile(application: ApplicationRecord) {
  selectedApplicant.value = application
  applicantDialogOpen.value = true
}

async function updateApplicant(applicationId: string, status: ApplicantAction) {
  processingApplicantId.value = applicationId
  try {
    await updateApplicationStatus(applicationId, status)
    await refreshApplications()
    success(status === 'accepted' ? 'Applicant accepted.' : 'Applicant rejected.', {
      description: 'The application status has been updated.',
    })
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
})

onUnmounted(() => {
  unsubscribeInternships?.()
  unsubscribeApplications?.()
})

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
          <div class="border-b border-border bg-gradient-to-r from-emerald-50/80 via-card to-accent/60 px-6 py-6 sm:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                  <Building2 class="h-5 w-5" />
                </div>
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Overview</p>
                  <h2 class="mt-1 text-2xl font-semibold tracking-tight text-foreground">{{ companyName }}</h2>
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
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Placement rate</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">{{ kpi.placementRate }}%</p>
          </div>
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Agreements</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">{{ kpi.totalAgreements }}</p>
          </div>
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Avg approved OJT hrs</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">{{ kpi.avgApprovedOjtHours }}</p>
          </div>
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Avg assessment score</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">{{ kpi.avgAssessmentScore }}</p>
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
                    <p class="font-medium text-foreground">{{ item.studentName || 'Unnamed applicant' }}</p>
                    <p class="truncate text-sm text-muted-foreground">
                      {{ item.internshipTitle || 'Untitled role' }} · {{ item.schoolName || 'School' }}
                    </p>
                  </div>
                  <Button size="sm" variant="outline" class="shrink-0" @click="openApplicantProfile(item)">
                    Review
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
                class="flex items-center gap-3 rounded-xl border border-border px-4 py-3 text-left transition hover:border-emerald-200 hover:bg-emerald-50/60"
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
                    {{ editingJobId ? 'Edit Job Posting' : 'Create Job Posting' }}
                  </h3>
                  <p class="text-sm text-muted-foreground">
                    Build a complete internship posting with the role fit, Cavite location, accepted programs, and contact flow schools need to review.
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
                    <p class="mt-1 text-sm text-muted-foreground">Start with the subject or title, then confirm the company profile details and Cavite location.</p>
                  </div>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobTitle">Subject or Title</FormLabel>
                    <FormControl>
                      <Input id="jobTitle" v-model="jobForm.title" placeholder="Software Engineering Intern" />
                    </FormControl>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobCompanyName">Company Name</FormLabel>
                    <FormControl>
                      <Input id="jobCompanyName" v-model="jobForm.companyName" readonly class="bg-muted text-muted-foreground" />
                    </FormControl>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobIndustry">Industry Based on Company Profile</FormLabel>
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
                          :class="jobForm.type === option.value ? 'border-primary/40 bg-accent shadow-sm' : 'border-border bg-muted hover:border-border'"
                          @click="jobForm.type = option.value"
                        >
                          <p class="text-sm font-semibold text-foreground">{{ option.label }}</p>
                          <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ option.description }}</p>
                        </button>
                      </div>
                    </FormControl>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobLocationArea">City or Municipality in Cavite</FormLabel>
                    <FormControl>
                      <select
                        id="jobLocationArea"
                        v-model="jobForm.locationArea"
                        class="flex h-12 w-full rounded-xl border border-input bg-background px-4 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                      >
                        <option value="">Select a location</option>
                        <optgroup v-for="group in caviteLocationGroups" :key="group.label" :label="group.label">
                          <option v-for="option in group.options" :key="option" :value="option">{{ option }}</option>
                        </optgroup>
                      </select>
                    </FormControl>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobBarangay">Barangay</FormLabel>
                    <FormControl>
                      <select
                        id="jobBarangay"
                        v-model="jobForm.barangay"
                        class="flex h-12 w-full rounded-xl border border-input bg-background px-4 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        :disabled="!jobForm.locationArea"
                      >
                        <option value="">{{ availableBarangays.length ? 'Select a barangay' : 'Select a city or municipality first' }}</option>
                        <option v-for="option in availableBarangays" :key="option" :value="option">{{ option }}</option>
                      </select>
                    </FormControl>
                  </FormItem>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobLocationDetails">Specific Address or Landmark</FormLabel>
                    <FormControl>
                      <Input id="jobLocationDetails" v-model="jobForm.locationDetails" placeholder="Building, street, or landmark" />
                    </FormControl>
                  </FormItem>
                </section>

                <section v-else-if="currentJobStep === 'details'" class="space-y-6">
                  <div class="grid gap-4 rounded-2xl border border-border bg-card p-5 sm:grid-cols-2 lg:grid-cols-3">
                  <div class="sm:col-span-2 lg:col-span-3">
                    <p class="text-sm font-semibold text-foreground">Internship Details</p>
                    <p class="mt-1 text-sm text-muted-foreground">Keep the duration, slots, and allowance, but use guided date and schedule inputs to avoid repetitive typing.</p>
                  </div>
                  <FormItem>
                    <FormLabel for="jobDuration">Duration</FormLabel>
                    <FormControl>
                      <select
                        id="jobDuration"
                        v-model="jobForm.duration"
                        class="flex h-12 w-full rounded-xl border border-input bg-background px-4 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                      >
                        <option v-for="option in commonDurationOptions" :key="option" :value="option">{{ option }}</option>
                      </select>
                    </FormControl>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobSlots">Slots</FormLabel>
                    <FormControl>
                      <Input id="jobSlots" v-model.number="jobForm.slotsAvailable" type="number" min="1" />
                    </FormControl>
                  </FormItem>
                  <FormItem>
                    <FormLabel for="jobAllowance">Allowance</FormLabel>
                    <FormControl>
                      <Input id="jobAllowance" v-model="jobForm.allowance" placeholder="PHP 5,000 / month" />
                    </FormControl>
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
                  <FormItem class="sm:col-span-2 lg:col-span-3">
                    <FormLabel>Status</FormLabel>
                    <FormControl>
                      <div class="grid gap-3 sm:grid-cols-3">
                        <button
                          v-for="option in statusOptions"
                          :key="option.value"
                          type="button"
                          class="rounded-2xl border p-4 text-left transition"
                          :class="jobForm.status === option.value ? 'border-primary/40 bg-accent shadow-sm' : 'border-border bg-muted hover:border-border'"
                          @click="jobForm.status = option.value as JobFormState['status']"
                        >
                          <p class="text-sm font-semibold text-foreground">{{ option.label }}</p>
                          <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ option.description }}</p>
                        </button>
                      </div>
                    </FormControl>
                  </FormItem>
                  </div>

                  <div class="grid gap-4 rounded-2xl border border-border bg-card p-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                      <p class="text-sm font-semibold text-foreground">Schedule</p>
                      <p class="mt-1 text-sm text-muted-foreground">Pick the internship days and time range directly.</p>
                    </div>
                    <div class="sm:col-span-2 grid gap-3 md:grid-cols-4 xl:grid-cols-7">
                      <button
                        v-for="day in weekdayOptions"
                        :key="day"
                        type="button"
                        class="rounded-2xl border px-4 py-3 text-sm font-medium transition"
                        :class="jobForm.scheduleDays.includes(day) ? 'border-primary/40 bg-accent text-foreground' : 'border-border bg-muted text-foreground hover:border-border'"
                        @click="toggleScheduleDay(day)"
                      >
                        {{ day }}
                      </button>
                    </div>
                    <FormItem>
                      <FormLabel for="jobScheduleStart">Start Time</FormLabel>
                      <FormControl>
                        <Input id="jobScheduleStart" v-model="jobForm.scheduleStartTime" type="time" />
                      </FormControl>
                    </FormItem>
                    <FormItem>
                      <FormLabel for="jobScheduleEnd">End Time</FormLabel>
                      <FormControl>
                        <Input id="jobScheduleEnd" v-model="jobForm.scheduleEndTime" type="time" />
                      </FormControl>
                    </FormItem>
                    <FormItem>
                      <FormLabel for="jobScheduleType">Schedule Type</FormLabel>
                      <FormControl>
                        <select
                          id="jobScheduleType"
                          v-model="jobForm.scheduleType"
                          class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                          <option value="fixed">Fixed</option>
                          <option value="rotating">Rotating</option>
                          <option value="flexible">Flexible</option>
                        </select>
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
                        class="flex min-h-[110px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        placeholder="Describe the role, team, and internship environment."
                      />
                    </FormControl>
                  </FormItem>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobTasks">Key Tasks and Responsibilities</FormLabel>
                    <FormControl>
                      <textarea
                        id="jobTasks"
                        v-model="jobForm.tasks"
                        rows="4"
                        class="flex min-h-[110px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        placeholder="List the core tasks the intern will handle."
                      />
                    </FormControl>
                  </FormItem>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobRequiredSkills">Required Skills or Tools</FormLabel>
                    <FormControl>
                      <div class="space-y-4 rounded-2xl border border-border bg-muted/80 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row">
                          <Input
                            id="jobRequiredSkills"
                            v-model="chipInput.requiredSkill"
                            class="bg-card"
                            placeholder="Add a skill like Communication, Git, or Excel"
                            @keydown.enter.prevent="commitChipInput('requiredSkill')"
                          />
                          <Button type="button" variant="outline" class="shrink-0 bg-card" @click="commitChipInput('requiredSkill')">
                            Add Skill
                          </Button>
                        </div>
                        <div v-if="asList(jobForm.requiredSkillsText).length" class="flex flex-wrap gap-2">
                          <span
                            v-for="skill in asList(jobForm.requiredSkillsText)"
                            :key="skill"
                            class="inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-1.5 text-xs font-semibold text-foreground shadow-sm"
                          >
                            {{ skill }}
                            <button
                              type="button"
                              class="rounded-full px-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                              @click="removeListItem('requiredSkillsText', skill)"
                            >
                              x
                            </button>
                          </span>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">No required skills added yet.</p>
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
                        class="flex min-h-[90px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        placeholder="Describe the mentorship, tools, and experience the intern will gain."
                      />
                    </FormControl>
                  </FormItem>
                  </section>
                </section>

                <section v-else-if="currentJobStep === 'programs'" class="grid gap-4 rounded-2xl border border-border bg-card p-5">
                  <div class="sm:col-span-2">
                    <p class="text-sm font-semibold text-foreground">Accepted Programs</p>
                    <p class="mt-1 text-sm text-muted-foreground">These courses come from the company account so you only choose which of your accepted programs apply to this post.</p>
                  </div>
                  <div
                    v-if="companyAcceptedCourses.length"
                    class="rounded-2xl border border-border bg-accent px-4 py-3 text-sm text-foreground"
                  >
                    Company profile courses: {{ companyAcceptedCourses.join(', ') }}
                  </div>
                  <div v-if="companyAcceptedCourses.length" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <button
                      v-for="course in companyAcceptedCourses"
                      :key="course"
                      type="button"
                      class="rounded-2xl border p-4 text-left transition"
                      :class="asList(jobForm.eligibleCoursesText).includes(course) ? 'border-primary/40 bg-accent shadow-sm' : 'border-border bg-muted hover:border-border'"
                      @click="asList(jobForm.eligibleCoursesText).includes(course) ? removeListItem('eligibleCoursesText', course) : addListItem('eligibleCoursesText', course)"
                    >
                      <p class="text-sm font-semibold text-foreground">{{ course }}</p>
                      <p class="mt-1 text-xs text-muted-foreground">
                        {{ asList(jobForm.eligibleCoursesText).includes(course) ? 'Included in this post' : 'Click to include this course' }}
                      </p>
                    </button>
                  </div>
                  <div v-else class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    No accepted courses are saved on the company account yet. Add them in the company settings page first.
                  </div>
                  <div v-if="asList(jobForm.eligibleCoursesText).length" class="rounded-2xl border border-border bg-muted px-4 py-3 text-sm text-foreground">
                    Selected for this post: {{ asList(jobForm.eligibleCoursesText).join(', ') }}
                  </div>
                </section>

                <section v-else class="grid gap-4 rounded-2xl border border-border bg-card p-5 sm:grid-cols-2">
                  <div class="sm:col-span-2">
                    <p class="text-sm font-semibold text-foreground">Requirements</p>
                    <p class="mt-1 text-sm text-muted-foreground">Document requirements, how to apply, and who schools or students should contact.</p>
                  </div>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobRequiredDocuments">Required Documents</FormLabel>
                    <FormControl>
                      <div class="space-y-4 rounded-2xl border border-amber-100 bg-amber-50/70 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row">
                          <Input
                            id="jobRequiredDocuments"
                            v-model="chipInput.requiredDocument"
                            class="bg-card"
                            placeholder="Add a document like Resume, MOA, or Endorsement Letter"
                            @keydown.enter.prevent="commitChipInput('requiredDocument')"
                          />
                          <Button type="button" variant="outline" class="shrink-0 bg-card" @click="commitChipInput('requiredDocument')">
                            Add Document
                          </Button>
                        </div>
                        <div v-if="asList(jobForm.requiredDocumentsText).length" class="flex flex-wrap gap-2">
                          <span
                            v-for="document in asList(jobForm.requiredDocumentsText)"
                            :key="document"
                            class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-card px-3 py-1.5 text-xs font-semibold text-amber-700 shadow-sm"
                          >
                            {{ document }}
                            <button
                              type="button"
                              class="rounded-full px-1 text-amber-700 transition hover:bg-amber-100 hover:text-amber-900"
                              @click="removeListItem('requiredDocumentsText', document)"
                            >
                              x
                            </button>
                          </span>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">No document requirements added yet.</p>
                      </div>
                    </FormControl>
                  </FormItem>
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="jobApplicationInstructions">How to Apply</FormLabel>
                    <FormControl>
                      <textarea
                        id="jobApplicationInstructions"
                        v-model="jobForm.applicationInstructions"
                        rows="3"
                        class="flex min-h-[90px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
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
                <div class="text-sm text-muted-foreground">Step {{ wizardStepIndex + 1 }} of {{ wizardSteps.length }}</div>
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
                  <Button v-else type="button" :disabled="savingJob || !jobForm.title.trim()" @click="saveJob">
                    <LoaderCircle v-if="savingJob" class="h-4 w-4 animate-spin" />
                    <span>{{ savingJob ? 'Saving...' : editingJobId ? 'Save Changes' : 'Create Job' }}</span>
                  </Button>
                </div>
              </div>
            </CardContent>
            </Card>
          </div>

          <div class="rounded-xl border border-border bg-card">
            <Table>
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
                    <TableCell><Skeleton class="h-5 w-12" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-12" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-20" /></TableCell>
                    <TableCell><Skeleton class="ml-auto h-9 w-9 rounded-md" /></TableCell>
                  </TableRow>
                </template>
                <template v-else-if="jobRows.length">
                  <TableRow v-for="job in jobRows" :key="job.id">
                    <TableCell>
                      <div class="space-y-1">
                        <p class="font-medium text-foreground">{{ job.title }}</p>
                        <p class="text-sm text-muted-foreground">{{ job.location || 'Location not set' }}</p>
                      </div>
                    </TableCell>
                    <TableCell class="text-foreground">{{ job.slotsAvailable }}</TableCell>
                    <TableCell class="text-foreground">{{ job.applicationCount }}</TableCell>
                    <TableCell>
                      <Badge :variant="jobBadgeVariant(job.status)">{{ badgeLabel(job.status) }}</Badge>
                    </TableCell>
                    <TableCell class="text-right">
                      <div class="flex justify-end gap-2">
                        <Button variant="ghost" size="sm" class="gap-2" @click="openViewJobDialog(job)">
                          <Eye class="h-4 w-4" />
                          <span>View</span>
                        </Button>
                        <Button variant="ghost" size="sm" class="gap-2" @click="openEditJobDialog(job)">
                          <Pencil class="h-4 w-4" />
                          <span>Edit</span>
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          class="gap-2 text-red-600 hover:text-red-600"
                          :disabled="deletingJobId === job.id"
                          @click="pendingDeleteJobId = job.id"
                        >
                          <Trash2 class="h-4 w-4" />
                          <span>{{ deletingJobId === job.id ? 'Deleting...' : 'Delete' }}</span>
                        </Button>
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
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="searchApplicants" placeholder="Search applicants..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="rounded-xl border border-border bg-card">
            <Table>
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
                        <p class="font-medium text-foreground">{{ applicant.studentName || 'Unnamed intern' }}</p>
                        <p class="text-sm text-muted-foreground">{{ applicant.internshipTitle || 'Untitled role' }}</p>
                      </div>
                    </TableCell>
                    <TableCell class="text-foreground">{{ applicant.schoolName || 'School not provided' }}</TableCell>
                    <TableCell class="text-muted-foreground">{{ formatDate(applicant.createdAt) }}</TableCell>
                    <TableCell>
                      <Badge :variant="applicantBadgeVariant(applicant.status)">{{ badgeLabel(applicant.status) }}</Badge>
                    </TableCell>
                    <TableCell>
                      <div class="flex justify-end gap-2">
                        <Button variant="ghost" class="gap-2" @click="openApplicantProfile(applicant)">
                          <Eye class="h-4 w-4" />
                          View Profile
                        </Button>
                        <Button
                          class="gap-2"
                          :disabled="processingApplicantId === applicant.id || !canCompanyReview(applicant.status)"
                          @click="updateApplicant(applicant.id, 'accepted')"
                        >
                          <LoaderCircle v-if="processingApplicantId === applicant.id && normalizeApplicationStatus(applicant.status) !== 'accepted'" class="h-4 w-4 animate-spin" />
                          <CheckCircle2 v-else class="h-4 w-4" />
                          Accept
                        </Button>
                        <Button
                          variant="outline"
                          class="gap-2"
                          :disabled="processingApplicantId === applicant.id || !canCompanyReview(applicant.status)"
                          @click="updateApplicant(applicant.id, 'rejected')"
                        >
                          <XCircle class="h-4 w-4" />
                          Reject
                        </Button>
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

    <Dialog v-model:open="applicantDialogOpen">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>Applicant Profile</DialogTitle>
          <p class="text-sm text-muted-foreground">Review the applicant details before you decide how to move their application forward.</p>
        </DialogHeader>

        <div v-if="selectedApplicant" class="mt-6 grid gap-4 sm:grid-cols-2">
          <div class="rounded-2xl bg-muted p-4">
            <p class="text-sm font-medium text-muted-foreground">Intern Name</p>
            <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedApplicant.studentName || 'Not provided' }}</p>
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
              <Badge :variant="applicantBadgeVariant(selectedApplicant.status)">{{ badgeLabel(selectedApplicant.status) }}</Badge>
            </div>
          </div>
        </div>

        <div v-if="selectedApplicant" class="mt-6 space-y-4">
          <ApplicationInterviewPanel :application-id="selectedApplicant.id" mode="manage" />
          <ApplicationAssessmentPanel :application-id="selectedApplicant.id" />
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <Button variant="outline" @click="close">Close</Button>
          <Button
            :disabled="!selectedApplicant || processingApplicantId === selectedApplicant?.id || !canCompanyReview(selectedApplicant?.status)"
            @click="selectedApplicant && updateApplicant(selectedApplicant.id, 'accepted'); close()"
          >
            Accept
          </Button>
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
              <p class="text-sm font-medium text-muted-foreground">Company</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedJob.companyName || 'Not provided' }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">Status</p>
              <div class="mt-2">
                <Badge :variant="jobBadgeVariant(selectedJob.status)">{{ badgeLabel(selectedJob.status) }}</Badge>
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
              <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedJob.slotsAvailable || 0 }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">Applicants</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedJob.applicationCount ?? 0 }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4">
              <p class="text-sm font-medium text-muted-foreground">Allowance</p>
              <p class="mt-2 text-sm font-semibold text-foreground">{{ selectedJob.allowance || 'Not provided' }}</p>
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
              <p class="mt-2 text-sm text-foreground">{{ (selectedJob.requiredSkills || selectedJob.requirements || []).join(', ') || 'Not provided' }}</p>
            </div>
            <div class="rounded-2xl bg-muted p-4 sm:col-span-2">
              <p class="text-sm font-medium text-muted-foreground">Required Documents</p>
              <p class="mt-2 text-sm text-foreground">{{ (selectedJob.requiredDocuments || []).join(', ') || 'Not provided' }}</p>
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
      :open="!!pendingDeleteJobId"
      title="Delete job posting?"
      description="This will permanently remove the selected job posting from your company workspace."
      action-label="Delete"
      @update:open="(open) => { if (!open) pendingDeleteJobId = null }"
      @action="confirmDeleteJob"
    />
  </MainLayout>
</template>
