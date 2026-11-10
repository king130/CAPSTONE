<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  BriefcaseBusiness,
  CheckCircle2,
  Clock3,
  Eye,
  FileText,
  LoaderCircle,
  MessageSquare,
  Search,
  School as SchoolIcon,
  UserRoundPlus,
  Users,
  XCircle,
} from 'lucide-vue-next'

import AlertDialog from '@/components/ui/alert-dialog/AlertDialog.vue'
import HostBrowseTabs from '@/components/opportunities/HostBrowseTabs.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue'
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue'
import Input from '@/components/ui/input/Input.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Table from '@/components/ui/table/Table.vue'
import TableBody from '@/components/ui/table/TableBody.vue'
import TableCell from '@/components/ui/table/TableCell.vue'
import TableHead from '@/components/ui/table/TableHead.vue'
import TableHeader from '@/components/ui/table/TableHeader.vue'
import TableRow from '@/components/ui/table/TableRow.vue'
import ApplicationTrackerPanel from '@/components/ApplicationTrackerPanel.vue'
import FilePreviewActions from '@/components/FilePreviewActions.vue'
import SkillChips from '@/components/skills/SkillChips.vue'
import { useToast } from '@/composables/useToast'
import type { SelectedSkill } from '@/services/skills'
import MainLayout from '@/layouts/MainLayout.vue'
import { assignedPermissionAllows, routePermissionRequirements } from '@/layouts/navigation'
import {
  listApplications,
  subscribeAllApplications,
  updateApplicationStatus,
  type ApplicationRecord,
} from '@/services/applications'
import { subscribeSchoolContracts, type ContractRecord } from '@/services/contracts'
import { subscribeInternships, type InternshipRecord } from '@/services/internships'
import {
  bulkGenerateCertificates,
  generateCertificate,
  listCertificateEligible,
  printCertificateHtml,
  viewCertificateHtml,
  type CertificateEligibility,
} from '@/services/certificates'
import { exportReport, fetchKpiReport, fetchStudentProgress, type KpiReport, type StudentProgressRow } from '@/services/kpi'
import { hasPermission } from '@/services/permissions'
import { subscribeSchoolStudents, type SchoolStudentRow } from '@/services/schoolStudents'
import { listCourseRequirements, type CourseRequirementMeta } from '@/services/schoolPrograms'
import { verificationStatusLabel } from '@/services/organizationVerification'
import { useAuthStore } from '@/stores/auth'
import { statusLabel } from '@/utils/statusLabels'

type SchoolView = 'dashboard' | 'student-interns' | 'opportunities' | 'applications' | 'placements' | 'reports'
type SchoolDecisionAction = 'endorsed' | 'school_rejected' | 'accepted' | 'rejected'
type PendingDecision = { applicationId: string; action: SchoolDecisionAction; internName: string } | null
type EndorsementDetail = {
  id: string
  internName: string
  email: string
  course: string
  company: string
  school: string
  internshipTitle: string
  appliedAt: string
  resume?: string | null
  status: string
  hostType: string
  isSchoolHosted: boolean
  hoursMismatch?: boolean
  schoolRequiredHours?: number | null
  hostRequiredHours?: number | null
  skills?: SelectedSkill[]
}

function mapApplicationSkills(application: ApplicationRecord): SelectedSkill[] {
  return (application.studentSkills || []).map((skill) => ({
    id: skill.id,
    name: skill.name,
    category: skill.category || 'Other',
    status: (skill.status as SelectedSkill['status']) || 'approved',
    level: (skill.level as SelectedSkill['level']) || 'beginner',
  }))
}

const currentView = ref<SchoolView>('dashboard')
const { success, error } = useToast()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const students = ref<SchoolStudentRow[]>([])
const applications = ref<ApplicationRecord[]>([])
const internships = ref<InternshipRecord[]>([])
const courseSetupMeta = ref<CourseRequirementMeta | null>(null)
const contracts = ref<ContractRecord[]>([])
const loadingStudents = ref(true)
const loadingApplications = ref(true)
const loadingInternships = ref(true)
const loadingContracts = ref(true)
const searchQuery = ref('')
const pendingDecision = ref<PendingDecision>(null)
const submittingDecision = ref(false)
const selectedEndorsement = ref<EndorsementDetail | null>(null)
const endorsementDialogMode = ref<'profile' | 'tracker'>('profile')
const studentProgress = ref<StudentProgressRow[]>([])
const loadingProgress = ref(false)
const kpi = ref<KpiReport | null>(null)
const exportingReport = ref(false)
const certificateCandidates = ref<CertificateEligibility[]>([])
const loadingCertificates = ref(false)
const selectedCertificateIds = ref<string[]>([])
const bulkPreviewOpen = ref(false)
const bulkConfirmOpen = ref(false)
const bulkIssuing = ref(false)
const bulkResultSummary = ref('')

let unsubscribeStudents: (() => void) | null = null
let unsubscribeInternships: (() => void) | null = null
let unsubscribeContracts: (() => void) | null = null
let unsubscribeApplications: (() => void) | null = null

function stopSchoolSubscriptions() {
  unsubscribeStudents?.()
  unsubscribeInternships?.()
  unsubscribeContracts?.()
  unsubscribeApplications?.()
  unsubscribeStudents = null
  unsubscribeInternships = null
  unsubscribeContracts = null
  unsubscribeApplications = null
}

function startSchoolSubscriptions(userId?: string) {
  stopSchoolSubscriptions()

  loadingStudents.value = true
  loadingApplications.value = true
  loadingInternships.value = true
  loadingContracts.value = true

  unsubscribeStudents = subscribeSchoolStudents('', (items) => {
    students.value = items
    loadingStudents.value = false
  })

  unsubscribeInternships = subscribeInternships((items) => {
    internships.value = items
    loadingInternships.value = false
  })

  if (userId) {
    unsubscribeContracts = subscribeSchoolContracts(userId, (items) => {
      contracts.value = items
      loadingContracts.value = false
    })
  } else {
    contracts.value = []
    loadingContracts.value = false
  }

  unsubscribeApplications = subscribeAllApplications((items) => {
    applications.value = items
    loadingApplications.value = false
  })
}

const pageTitle = computed(() => {
  const titles: Record<SchoolView, string> = {
    dashboard: 'Dashboard',
    'student-interns': 'Interns List',
    opportunities: 'Browse',
    applications: 'Endorsements',
    placements: 'Placements',
    reports: 'Reports',
  }
  return titles[currentView.value]
})

const activeItem = computed(() => currentView.value)

const searchableInternRows = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return students.value
    .map((student) => {
      const relatedApplication = applications.value.find((application) => application.studentId === student.id || application.studentEmail?.toLowerCase() === student.email.toLowerCase())

      return {
        id: student.id,
        name: student.studentName || 'Unnamed Intern',
        course: student.course || 'Not set',
        company: relatedApplication?.companyName || 'No application yet',
        status: relatedApplication
          ? normalizeStatus(relatedApplication.status)
          : 'no_application',
        email: student.email,
        skills: (student.skills || []).map((skill) => ({
          id: skill.id,
          name: skill.name,
          category: skill.category || 'Other',
          status: (skill.status as SelectedSkill['status']) || 'approved',
          level: (skill.level as SelectedSkill['level']) || 'beginner',
        })),
      }
    })
    .filter((row) =>
      !query ||
      [row.name, row.course, row.company, row.email, ...row.skills.map((skill) => skill.name)].some((value) =>
        value.toLowerCase().includes(query),
      ),
    )
})

const activeContractCompanyIds = computed(() =>
  new Set(
    contracts.value
      .filter((contract) => String(contract.status || '').trim().toLowerCase() === 'active')
      .map((contract) => String(contract.companyId || '').trim())
      .filter(Boolean),
  ),
)

const visibleOpportunities = computed(() => {
  const schoolUserId = String(authStore.user?.uid || '').trim()

  return internships.value.filter((internship) => {
    if (internship.hostType === 'school') {
      return String(internship.schoolId || '').trim() === schoolUserId
    }

    return activeContractCompanyIds.value.has(String(internship.companyId || '').trim())
  })
})

const opportunityRows = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return visibleOpportunities.value
    .map((internship) => ({
      id: internship.id,
      title: internship.title || 'Untitled placement',
      hostName: internship.companyName || internship.hostName || internship.schoolName || 'Host not set',
      hostType:
        internship.hostType === 'school'
          ? 'School'
          : internship.hostType === 'municipal_hall'
            ? 'Municipal Hall'
            : 'Company',
      location: internship.location || 'Location not set',
      status: normalizeOpportunityStatus(internship.status),
      programs: (internship.eligibleCourses || []).join(', ') || 'Open to endorsed students',
      slots: internship.slotsAvailable || 0,
    }))
    .filter((row) =>
      !query ||
      [row.title, row.hostName, row.hostType, row.location, row.programs].some((value) =>
        value.toLowerCase().includes(query),
      ),
    )
})

function isSchoolHostedApplication(application: ApplicationRecord): boolean {
  const hostType = String(application.hostType || '').trim().toLowerCase()
  if (hostType === 'school') return true
  if (hostType === 'company') return false
  return !application.companyName && !application.companyId
}

function applicationHostLabel(application: ApplicationRecord): string {
  return (
    application.hostName ||
    application.companyName ||
    application.schoolName ||
    (isSchoolHostedApplication(application) ? 'School-hosted placement' : 'Pending assignment')
  )
}

const pendingEndorsements = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return applications.value
    .filter((application) => normalizeStatus(application.status) === 'submitted')
    .map((application) => ({
      id: application.id,
      internName: application.studentName || 'Unnamed Intern',
      email: application.studentEmail || 'No email provided',
      course: application.studentCourse || 'Not set',
      company: applicationHostLabel(application),
      internshipTitle: application.internshipTitle || 'Untitled position',
      appliedAt: formatDate(application.createdAt),
      resume: application.resume,
      school: application.schoolName || 'Current school',
      status: normalizeStatus(application.status),
      hostType: String(application.hostType || (isSchoolHostedApplication(application) ? 'school' : 'company')),
      isSchoolHosted: isSchoolHostedApplication(application),
      hoursMismatch: !!application.hoursMismatch,
      schoolRequiredHours: application.schoolRequiredHours ?? null,
      hostRequiredHours: application.hostRequiredHours ?? null,
      skills: mapApplicationSkills(application),
    }))
    .filter((row) =>
      !query ||
      [row.internName, row.email, row.course, row.company, row.school, row.internshipTitle].some((value) =>
        value.toLowerCase().includes(query),
      ),
    )
})

const withdrawnPlacedElsewhere = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return applications.value
    .filter((application) => normalizeStatus(application.status) === 'withdrawn_placed_elsewhere')
    .map((application) => ({
      id: application.id,
      internName: application.studentName || 'Unnamed Intern',
      email: application.studentEmail || 'No email provided',
      course: application.studentCourse || 'Not set',
      company: applicationHostLabel(application),
      internshipTitle: application.internshipTitle || 'Untitled position',
      appliedAt: formatDate(application.createdAt),
      resume: application.resume,
      school: application.schoolName || 'Current school',
      status: 'withdrawn_placed_elsewhere' as const,
      withdrawalReason: application.withdrawalReason || 'Placed elsewhere',
      hostType: String(application.hostType || (isSchoolHostedApplication(application) ? 'school' : 'company')),
      isSchoolHosted: isSchoolHostedApplication(application),
      hoursMismatch: !!application.hoursMismatch,
      schoolRequiredHours: application.schoolRequiredHours ?? null,
      hostRequiredHours: application.hostRequiredHours ?? null,
      skills: mapApplicationSkills(application),
    }))
    .filter((row) =>
      !query ||
      [row.internName, row.email, row.course, row.company, row.school, row.internshipTitle, row.withdrawalReason].some((value) =>
        value.toLowerCase().includes(query),
      ),
    )
})

const pendingSchoolHostedAcceptances = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return applications.value
    .filter(
      (application) =>
        normalizeStatus(application.status) === 'endorsed' && isSchoolHostedApplication(application),
    )
    .map((application) => ({
      id: application.id,
      internName: application.studentName || 'Unnamed Intern',
      email: application.studentEmail || 'No email provided',
      course: application.studentCourse || 'Not set',
      company: applicationHostLabel(application),
      internshipTitle: application.internshipTitle || 'Untitled position',
      appliedAt: formatDate(application.createdAt),
      resume: application.resume,
      school: application.schoolName || 'Current school',
      status: normalizeStatus(application.status),
      hostType: 'school',
      isSchoolHosted: true,
      skills: mapApplicationSkills(application),
    }))
    .filter((row) =>
      !query ||
      [row.internName, row.email, row.course, row.company, row.school, row.internshipTitle].some((value) =>
        value.toLowerCase().includes(query),
      ),
    )
})

const schoolDisplayName = computed(() => {
  const user = authStore.user as Record<string, unknown> | null
  const school = (user?.school ?? null) as Record<string, unknown> | null
  const profile = (user?.profile ?? null) as Record<string, unknown> | null
  return (
    String(school?.schoolName || school?.name || profile?.schoolName || user?.name || 'School workspace')
  )
})

const openOpportunitiesCount = computed(
  () => visibleOpportunities.value.filter((internship) => normalizeOpportunityStatus(internship.status) === 'active').length,
)

const dashboardStats = computed(() => {
  const totalInterns = students.value.length
  const pendingCount = applications.value.filter((application) => normalizeStatus(application.status) === 'submitted').length
  const activePlacements = applications.value.filter((application) => normalizeStatus(application.status) === 'accepted').length

  return [
    {
      label: 'Total Interns',
      value: totalInterns,
      icon: Users,
      iconClass: 'bg-accent text-primary',
      caption: 'On your roster',
    },
    {
      label: 'Pending Endorsements',
      value: pendingCount,
      icon: Clock3,
      iconClass: 'bg-amber-100 text-amber-700',
      caption: 'Awaiting school action',
    },
    {
      label: 'Active Placements',
      value: activePlacements,
      icon: BriefcaseBusiness,
      iconClass: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
      caption: 'Accepted by partners',
    },
    {
      label: 'Open Opportunities',
      value: openOpportunitiesCount.value,
      icon: BriefcaseBusiness,
      iconClass: 'bg-violet-100 text-violet-700',
      caption: 'Visible to students',
    },
  ]
})

const attentionEndorsements = computed(() => pendingEndorsements.value.slice(0, 5))

function canOpenRoute(routeName: string) {
  const required = routePermissionRequirements(routeName)
  const assigned = authStore.user?.memberships?.[0]?.permissions ?? []
  return !required || assignedPermissionAllows(assigned, required)
}

const compactQuickActions = computed(() => [
  {
    label: 'Student Accounts',
    icon: UserRoundPlus,
    route: 'school-students',
    action: () => router.push({ name: 'school-students' }),
  },
  {
    label: 'Agreements',
    icon: FileText,
    route: 'agreements',
    action: () => router.push({ name: 'agreements' }),
  },
  {
    label: 'Browse',
    icon: BriefcaseBusiness,
    route: 'school-opportunities',
    action: () => router.push({ name: 'school-opportunities' }),
  },
  {
    label: 'Messages',
    icon: MessageSquare,
    action: () => window.dispatchEvent(new CustomEvent('chat:open')),
  },
].filter((item) => !item.route || canOpenRoute(item.route)))

const placementCertificateByApplication = computed(() => {
  const map = new Map<string, CertificateEligibility>()
  for (const row of certificateCandidates.value) {
    map.set(String(row.applicationId), row)
  }
  return map
})

const placementRows = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return applications.value
    .filter((application) => {
      const status = normalizeStatus(application.status)
      return status === 'accepted' || status === 'placement_ended'
    })
    .map((application) => {
      const cert = placementCertificateByApplication.value.get(String(application.id))
      return {
        id: application.id,
        internName: application.studentName || 'Unnamed Intern',
        program: application.studentCourse || 'Program not set',
        host: application.companyName || application.schoolName || 'Placement not assigned',
        placementType: application.companyName ? 'Company-hosted' : 'School-hosted',
        updatedAt: formatDate(application.updatedAt || application.createdAt),
        status: normalizeStatus(application.status),
        certificateId: cert?.certificateId || null,
        alreadyIssued: Boolean(cert?.alreadyIssued),
        certificateEligible: Boolean(cert?.eligible),
      }
    })
    .filter((row) =>
      !query ||
      [row.internName, row.program, row.host, row.placementType].some((value) =>
        value.toLowerCase().includes(query),
      ),
    )
})

const reportCards = computed(() => {
  const accepted = applications.value.filter((application) => normalizeStatus(application.status) === 'accepted').length
  const pending = applications.value.filter((application) => normalizeStatus(application.status) === 'submitted').length
  const schoolHosted = visibleOpportunities.value.filter((internship) => internship.hostType === 'school').length
  const companyHosted = visibleOpportunities.value.filter((internship) => internship.hostType !== 'school').length

  return [
    { label: 'Placements Confirmed', value: accepted, copy: 'Students already accepted by company partners.' },
    { label: 'Pending Endorsements', value: pending, copy: 'Students still waiting for school endorsement.' },
    { label: 'School-hosted Roles', value: schoolHosted, copy: 'Opportunities that can support education or campus-based OJT.' },
    { label: 'Company-hosted Roles', value: companyHosted, copy: 'Industry placements currently visible to your students.' },
  ]
})

function normalizeStatus(status?: string | null) {
  const normalized = String(status || 'submitted').trim().toLowerCase()
  if (['submitted', 'pending'].includes(normalized)) return 'submitted'
  if (normalized === 'endorsed') return 'endorsed'
  if (['accepted', 'approved', 'active', 'registered'].includes(normalized)) return 'accepted'
  if (normalized === 'withdrawn_placed_elsewhere') return 'withdrawn_placed_elsewhere'
  if (['placement_cancelled', 'placement_declined', 'placement_ended'].includes(normalized)) return normalized
  if (['school_rejected', 'rejected', 'declined', 'disabled'].includes(normalized)) return 'rejected'
  return 'submitted'
}

function normalizeOpportunityStatus(status?: string | null) {
  const normalized = String(status || 'draft').trim().toLowerCase()
  if (normalized === 'active') return 'active'
  if (normalized === 'closed') return 'closed'
  return 'draft'
}

function schoolViewFromRouteName(name: unknown): SchoolView {
  if (name === 'school-interns') return 'student-interns'
  if (name === 'school-opportunities') return 'opportunities'
  if (name === 'school-endorsements') return 'applications'
  if (name === 'school-placements') return 'placements'
  if (name === 'school-reports') return 'reports'
  return 'dashboard'
}

function badgeLabel(status: string) {
  if (status === 'no_application' || status === 'none' || status === '') return 'No application'
  if (status === 'accepted') return 'Accepted'
  if (status === 'endorsed') return 'Endorsed'
  if (status === 'withdrawn_placed_elsewhere') return 'Withdrawn — placed elsewhere'
  if (status === 'placement_cancelled') return 'Placement cancelled'
  if (status === 'placement_declined') return 'Placement declined'
  if (status === 'placement_ended') return 'Placement ended'
  if (status === 'rejected') return 'Rejected'
  if (status === 'submitted') return 'Submitted'
  return status.replaceAll('_', ' ')
}

function badgeVariant(status: string) {
  if (status === 'no_application' || status === 'none' || status === '') return 'outline'
  if (status === 'accepted') return 'success'
  if (status === 'endorsed') return 'outline'
  if (status === 'rejected' || status === 'withdrawn_placed_elsewhere' || status.startsWith('placement_')) {
    return 'destructive'
  }
  return 'warning'
}

function formatKpiValue(value: number | null | undefined, hasData: boolean, suffix = ''): string {
  if (!hasData || value == null) return '—'
  return `${value}${suffix}`
}

function opportunityBadgeLabel(status: string) {
  if (status === 'active') return 'Open'
  if (status === 'closed') return 'Closed'
  return 'Draft'
}

function opportunityBadgeVariant(status: string) {
  if (status === 'active') return 'success'
  if (status === 'closed') return 'destructive'
  return 'warning'
}

function formatDate(value?: string) {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? '—'
    : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

async function loadApplications() {
  loadingApplications.value = true
  try {
    applications.value = await listApplications()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to load endorsements.' })
  } finally {
    loadingApplications.value = false
  }
}

function openDecisionDialog(applicationId: string, action: SchoolDecisionAction, internName: string) {
  pendingDecision.value = { applicationId, action, internName }
}

function openEndorsementDetail(endorsement: EndorsementDetail, mode: 'profile' | 'tracker' = 'profile') {
  endorsementDialogMode.value = mode
  selectedEndorsement.value = endorsement
}

function decisionSuccessMessage(action: SchoolDecisionAction): string {
  if (action === 'endorsed') return 'Student endorsed.'
  if (action === 'school_rejected') return 'Endorsement rejected.'
  if (action === 'accepted') return 'School-hosted placement accepted.'
  return 'Application rejected.'
}

function decisionDialogTitle(action: SchoolDecisionAction | undefined): string {
  if (action === 'endorsed') return 'Endorse application?'
  if (action === 'school_rejected') return 'Reject endorsement?'
  if (action === 'accepted') return 'Accept school-hosted application?'
  if (action === 'rejected') return 'Reject school-hosted application?'
  return 'Update application?'
}

function decisionDialogDescription(decision: PendingDecision): string {
  if (!decision) return ''
  if (decision.action === 'endorsed') {
    return `Endorse ${decision.internName}'s application at the school stage?`
  }
  if (decision.action === 'school_rejected') {
    return `Reject ${decision.internName}'s application at the school stage?`
  }
  if (decision.action === 'accepted') {
    return `Accept ${decision.internName} for this school-hosted placement?`
  }
  return `Reject ${decision.internName}'s school-hosted application?`
}

function decisionActionLabel(action: SchoolDecisionAction | undefined): string {
  if (action === 'endorsed') return 'Endorse'
  if (action === 'accepted') return 'Accept'
  return 'Reject'
}

async function confirmDecision() {
  const decision = pendingDecision.value
  if (!decision) return
  submittingDecision.value = true
  // AlertDialog closes (and clears pendingDecision) before this async work finishes.
  try {
    await updateApplicationStatus(decision.applicationId, decision.action)
    await loadApplications()
    success(decisionSuccessMessage(decision.action), {
      description: `${decision.internName} has been updated.`,
    })
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to update application.',
    })
  } finally {
    submittingDecision.value = false
    pendingDecision.value = null
  }
}

function handleMenuClick(item: string) {
  const next = ['dashboard', 'student-interns', 'opportunities', 'applications', 'placements', 'reports'].includes(item)
    ? (item as SchoolView)
    : 'dashboard'
  currentView.value = next
  const targetName =
    next === 'student-interns'
      ? 'school-interns'
      : next === 'opportunities'
        ? 'school-opportunities'
      : next === 'applications'
        ? 'school-endorsements'
        : next === 'placements'
          ? 'school-placements'
          : next === 'reports'
            ? 'school-reports'
        : 'school'
  void router.push({ name: targetName })
}

async function loadStudentProgress() {
  loadingProgress.value = true
  try {
    studentProgress.value = await fetchStudentProgress()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to load student OJT progress.' })
    studentProgress.value = []
  } finally {
    loadingProgress.value = false
  }
}

async function loadKpi() {
  try {
    kpi.value = await fetchKpiReport()
  } catch {
    kpi.value = null
  }
}

async function downloadReport(type: 'placements' | 'ojt' | 'assessments') {
  exportingReport.value = true
  try {
    const blob = await exportReport(type)
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `${type}-report.csv`
    anchor.click()
    URL.revokeObjectURL(url)
    success('Report downloaded.')
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to export report.' })
  } finally {
    exportingReport.value = false
  }
}

async function issueCertificate(applicationId: string) {
  if (!hasPermission('org.approve_certificates')) {
    error('Missing permission to issue certificates.')
    return
  }
  try {
    await generateCertificate(applicationId)
    success('Certificate of completion issued.')
    await loadCertificateCandidates()
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to generate certificate. Ensure the placement is accepted or ended and OJT hours meet the requirement.',
    })
  }
}

async function viewPlacementCertificate(certificateId: string) {
  try {
    await viewCertificateHtml(certificateId)
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to open the certificate.' })
  }
}

async function printPlacementCertificate(certificateId: string) {
  try {
    await printCertificateHtml(certificateId)
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to print the certificate.' })
  }
}

const eligibleCertificateRows = computed(() =>
  certificateCandidates.value.filter((row) => row.eligible),
)

const selectedEligibleCertificates = computed(() =>
  eligibleCertificateRows.value.filter((row) => selectedCertificateIds.value.includes(row.applicationId)),
)

function toggleCertificateSelection(applicationId: string, checked: boolean) {
  if (checked) {
    if (!selectedCertificateIds.value.includes(applicationId)) {
      selectedCertificateIds.value = [...selectedCertificateIds.value, applicationId]
    }
    return
  }
  selectedCertificateIds.value = selectedCertificateIds.value.filter((id) => id !== applicationId)
}

function toggleSelectAllCertificates(checked: boolean) {
  selectedCertificateIds.value = checked
    ? eligibleCertificateRows.value.map((row) => row.applicationId)
    : []
}

async function loadCertificateCandidates() {
  if (!hasPermission('org.approve_certificates')) {
    certificateCandidates.value = []
    return
  }
  loadingCertificates.value = true
  try {
    certificateCandidates.value = await listCertificateEligible()
    selectedCertificateIds.value = selectedCertificateIds.value.filter((id) =>
      certificateCandidates.value.some((row) => row.applicationId === id && row.eligible),
    )
  } catch (caughtError) {
    certificateCandidates.value = []
    error(caughtError, { fallback: 'Unable to load certificate eligibility.' })
  } finally {
    loadingCertificates.value = false
  }
}

function openBulkPreview() {
  if (!selectedEligibleCertificates.value.length) {
    error('Select at least one eligible student.')
    return
  }
  bulkPreviewOpen.value = true
}

async function confirmBulkIssue() {
  if (!selectedEligibleCertificates.value.length) return
  bulkIssuing.value = true
  try {
    const result = await bulkGenerateCertificates(
      selectedEligibleCertificates.value.map((row) => row.applicationId),
    )
    const issuedCount = result.issued.length
    const skippedCount = result.skipped.length
    const failedCount = result.failed.length
    bulkResultSummary.value = [
      issuedCount ? `Issued ${issuedCount}` : '',
      skippedCount ? `Skipped ${skippedCount}` : '',
      failedCount ? `Failed ${failedCount}` : '',
    ].filter(Boolean).join(' · ')
    if (issuedCount) success(`Issued ${issuedCount} certificate${issuedCount === 1 ? '' : 's'}.`)
    if (skippedCount || failedCount) {
      const reasons = [...result.skipped, ...result.failed]
        .map((row) => `${row.applicationId}: ${row.reason}`)
        .slice(0, 5)
        .join(' ')
      error(reasons || 'Some certificates were skipped.')
    }
    bulkConfirmOpen.value = false
    bulkPreviewOpen.value = false
    selectedCertificateIds.value = []
    await loadCertificateCandidates()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to issue certificates in bulk.' })
  } finally {
    bulkIssuing.value = false
  }
}

async function loadCourseSetupWarnings() {
  if (!hasPermission('org.manage_students')) {
    courseSetupMeta.value = null
    return
  }
  try {
    const { meta } = await listCourseRequirements()
    courseSetupMeta.value = meta
  } catch {
    courseSetupMeta.value = null
  }
}

onMounted(() => {
  currentView.value = schoolViewFromRouteName(route.name)
  startSchoolSubscriptions(authStore.user?.uid)
  if (currentView.value === 'dashboard' || currentView.value === 'reports' || currentView.value === 'placements') {
    void loadKpi()
  }
  if (currentView.value === 'reports' || currentView.value === 'placements') {
    void loadStudentProgress()
  }
  if (currentView.value === 'placements') {
    void loadCertificateCandidates()
  }
  void loadCourseSetupWarnings()
})

onUnmounted(() => {
  stopSchoolSubscriptions()
})

watch(
  () => route.name,
  (name) => {
    currentView.value = schoolViewFromRouteName(name)
  },
)

watch(currentView, (view) => {
  if (view === 'dashboard' || view === 'reports' || view === 'placements') {
    void loadKpi()
  }
  if (view === 'reports' || view === 'placements') {
    void loadStudentProgress()
  }
  if (view === 'placements') {
    void loadCertificateCandidates()
  }
  // Force a fresh /applications pull when opening Endorsements (polling alone can stay empty after a failed fetch).
  if (view === 'applications') {
    void loadApplications()
  }
})

watch(
  () => authStore.user?.uid,
  (userId) => {
    startSchoolSubscriptions(userId)
  },
)
</script>

<template>
  <MainLayout role="school" :title="pageTitle" :active-item="activeItem" @navigate="handleMenuClick($event.key)">
    <div class="space-y-6">
      <template v-if="currentView === 'dashboard'">
        <section class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
          <div class="border-b border-border bg-card px-6 py-6 sm:px-8 dark:bg-card">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-accent text-primary">
                  <SchoolIcon class="h-5 w-5" />
                </div>
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Overview</p>
                  <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h2 class="text-2xl font-semibold tracking-tight text-foreground">{{ schoolDisplayName }}</h2>
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
                    {{ dashboardStats[1]?.value || 0 }} endorsement{{ (dashboardStats[1]?.value || 0) === 1 ? '' : 's' }} waiting ·
                    {{ dashboardStats[2]?.value || 0 }} active placement{{ (dashboardStats[2]?.value || 0) === 1 ? '' : 's' }}
                  </p>
                </div>
              </div>
              <div class="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" @click="handleMenuClick('applications')">Review endorsements</Button>
                <Button size="sm" @click="handleMenuClick('reports')">Open reports</Button>
              </div>
            </div>
          </div>

          <div class="grid gap-px bg-muted sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="stat in dashboardStats" :key="stat.label" class="bg-card px-5 py-5">
              <template v-if="loadingStudents || loadingApplications || loadingInternships">
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

        <div
          v-if="courseSetupMeta?.hasSetupWarnings"
          class="rounded-2xl border border-amber-500/40 bg-amber-500/10 px-5 py-4 text-sm text-foreground"
        >
          <p class="font-semibold">Course setup needs attention</p>
          <p class="mt-1 text-muted-foreground">
            Configure required OJT hours and eligible year levels for each course in Settings.
            Students in incomplete courses cannot apply.
          </p>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-muted-foreground">
            <li v-for="item in courseSetupMeta.incompleteCourses" :key="item.id">
              {{ item.courseName }} is incomplete
            </li>
            <li v-if="courseSetupMeta.flaggedStudentCount > 0">
              {{ courseSetupMeta.flaggedStudentCount }} student{{
                courseSetupMeta.flaggedStudentCount === 1 ? '' : 's'
              }}
              {{ courseSetupMeta.flaggedStudentCount === 1 ? 'has' : 'have' }}
              a year level that does not match their course (records were not changed).
            </li>
          </ul>
          <Button class="mt-3" size="sm" variant="outline" @click="router.push({ name: 'settings' })">
            Open course settings
          </Button>
        </div>

        <section v-if="kpi" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Placement rate</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">
              {{ formatKpiValue(kpi.placementRate, (kpi.studentCount ?? 0) > 0 || (kpi.totalApplications ?? 0) > 0, '%') }}
            </p>
          </div>
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Agreements</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">{{ kpi.totalAgreements }}</p>
          </div>
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Avg approved OJT hrs</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">
              {{ formatKpiValue(kpi.avgApprovedOjtHours, kpi.avgApprovedOjtHours != null && (kpi.ojtStudentCount ?? 0) > 0) }}
            </p>
          </div>
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Avg assessment score</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">
              {{ formatKpiValue(kpi.avgAssessmentScore, kpi.avgAssessmentScore != null && (kpi.assessmentCount ?? 0) > 0) }}
            </p>
          </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
          <section class="rounded-2xl border border-border bg-card shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
              <div>
                <h3 class="text-lg font-semibold text-foreground">Needs attention</h3>
                <p class="text-sm text-muted-foreground">Pending endorsements that need a school decision.</p>
              </div>
              <Button size="sm" variant="outline" @click="handleMenuClick('applications')">View all</Button>
            </div>
            <div class="divide-y divide-border">
              <template v-if="loadingApplications">
                <div v-for="index in 3" :key="index" class="space-y-2 px-5 py-4">
                  <Skeleton class="h-4 w-40" />
                  <Skeleton class="h-3 w-56" />
                </div>
              </template>
              <template v-else-if="attentionEndorsements.length">
                <div
                  v-for="item in attentionEndorsements"
                  :key="item.id"
                  class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-foreground">{{ item.internName }}</p>
                    <p class="truncate text-sm text-muted-foreground">
                      {{ item.internshipTitle }} · {{ item.company }}
                    </p>
                  </div>
                  <Button size="sm" variant="outline" class="shrink-0" @click="handleMenuClick('applications')">
                    Review
                  </Button>
                </div>
              </template>
              <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                No pending endorsements right now.
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-foreground">Quick actions</h3>
            <p class="mt-1 text-sm text-muted-foreground">Jump to common school workflows.</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
              <button
                v-for="action in compactQuickActions"
                :key="action.label"
                type="button"
                class="flex items-center gap-3 rounded-xl border border-border px-4 py-3 text-left transition hover:border-border hover:bg-accent/60"
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

      <Card v-else-if="currentView === 'opportunities'" class="border-border/80 shadow-sm">
        <CardContent class="pt-6">
          <HostBrowseTabs />
        </CardContent>
      </Card>

      <Card v-else-if="currentView === 'student-interns'" class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Interns List</h2>
            <p class="text-sm text-muted-foreground">Search enrolled interns, review their course, and see the company tied to the latest application.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="searchQuery" placeholder="Search interns..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <Table class="min-w-[40rem]">
              <TableHeader>
                <TableRow>
                  <TableHead>Intern Name</TableHead>
                  <TableHead>Course</TableHead>
                  <TableHead>Company Applied To</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="loadingStudents || loadingApplications">
                  <TableRow v-for="index in 5" :key="index">
                    <TableCell><Skeleton class="h-5 w-36" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-28" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-40" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-24" /></TableCell>
                  </TableRow>
                </template>
                <template v-else-if="searchableInternRows.length">
                  <TableRow v-for="intern in searchableInternRows" :key="intern.id">
                    <TableCell>
                      <div class="space-y-2">
                        <div class="space-y-1">
                          <p class="font-medium text-foreground">{{ intern.name }}</p>
                          <p class="text-sm text-muted-foreground">{{ intern.email }}</p>
                        </div>
                        <SkillChips
                          :skills="intern.skills"
                          mode="intern"
                          empty-text="No skills listed"
                        />
                      </div>
                    </TableCell>
                    <TableCell class="text-foreground">{{ intern.course }}</TableCell>
                    <TableCell class="text-foreground">{{ intern.company }}</TableCell>
                    <TableCell>
                      <Badge :variant="badgeVariant(intern.status)">{{ badgeLabel(intern.status) }}</Badge>
                    </TableCell>
                  </TableRow>
                </template>
                <TableRow v-else>
                  <TableCell colspan="4" class="py-10 text-center text-muted-foreground">
                    No intern records matched your search.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card v-else-if="currentView === 'placements'" class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Placements</h2>
            <p class="text-sm text-muted-foreground">Track students who already have company-accepted placements and review where each intern has been assigned.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="searchQuery" placeholder="Search placements..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent class="space-y-6">
          <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <Table class="min-w-[56rem]">
              <TableHeader>
                <TableRow>
                  <TableHead>Intern</TableHead>
                  <TableHead>Program</TableHead>
                  <TableHead>Host</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Updated</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead class="text-right">Certificate</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="loadingApplications">
                  <TableRow v-for="index in 4" :key="index">
                    <TableCell><Skeleton class="h-5 w-36" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-28" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-40" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-28" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-24" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-20" /></TableCell>
                    <TableCell><Skeleton class="ml-auto h-5 w-20" /></TableCell>
                  </TableRow>
                </template>
                <template v-else-if="placementRows.length">
                  <TableRow v-for="placement in placementRows" :key="placement.id">
                    <TableCell class="font-medium text-foreground">{{ placement.internName }}</TableCell>
                    <TableCell class="text-foreground">{{ placement.program }}</TableCell>
                    <TableCell class="text-foreground">{{ placement.host }}</TableCell>
                    <TableCell class="text-foreground">{{ placement.placementType }}</TableCell>
                    <TableCell class="text-muted-foreground">{{ placement.updatedAt }}</TableCell>
                    <TableCell>
                      <Badge :variant="badgeVariant(placement.status)">{{ badgeLabel(placement.status) }}</Badge>
                    </TableCell>
                    <TableCell class="text-right">
                      <div class="flex flex-wrap justify-end gap-2">
                        <template v-if="placement.alreadyIssued && placement.certificateId">
                          <Button size="sm" variant="outline" @click="viewPlacementCertificate(placement.certificateId)">
                            View Certificate
                          </Button>
                          <Button size="sm" @click="printPlacementCertificate(placement.certificateId)">
                            Print Certificate
                          </Button>
                        </template>
                        <Button
                          v-else-if="hasPermission('org.approve_certificates')"
                          size="sm"
                          variant="outline"
                          :disabled="placement.status === 'placement_ended' && !placement.certificateEligible && !placement.alreadyIssued"
                          @click="issueCertificate(placement.id)"
                        >
                          Issue
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                </template>
                <TableRow v-else>
                  <TableCell colspan="7" class="py-10 text-center text-muted-foreground">
                    {{
                      searchQuery.trim()
                        ? 'No accepted or ended placements matched your search.'
                        : 'No placements yet.'
                    }}
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <div
            v-if="hasPermission('org.approve_certificates')"
            class="rounded-xl border border-border bg-card p-4 sm:p-5"
          >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h3 class="text-lg font-semibold text-foreground">Bulk certificates</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                  Students who completed required OJT hours. Select some or all, preview, then issue in one action.
                </p>
                <p v-if="bulkResultSummary" class="mt-2 text-sm text-foreground">{{ bulkResultSummary }}</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" :disabled="loadingCertificates" @click="loadCertificateCandidates">
                  Refresh
                </Button>
                <Button
                  size="sm"
                  :disabled="!selectedEligibleCertificates.length || bulkIssuing"
                  @click="openBulkPreview"
                >
                  Preview &amp; issue ({{ selectedEligibleCertificates.length }})
                </Button>
              </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead class="w-12">
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-border"
                        :checked="eligibleCertificateRows.length > 0 && selectedEligibleCertificates.length === eligibleCertificateRows.length"
                        :disabled="!eligibleCertificateRows.length"
                        @change="toggleSelectAllCertificates(($event.target as HTMLInputElement).checked)"
                      />
                    </TableHead>
                    <TableHead>Student</TableHead>
                    <TableHead>Internship</TableHead>
                    <TableHead>Hours</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <template v-if="loadingCertificates">
                    <TableRow v-for="index in 3" :key="index">
                      <TableCell><Skeleton class="h-4 w-4" /></TableCell>
                      <TableCell><Skeleton class="h-5 w-36" /></TableCell>
                      <TableCell><Skeleton class="h-5 w-40" /></TableCell>
                      <TableCell><Skeleton class="h-5 w-24" /></TableCell>
                      <TableCell><Skeleton class="h-5 w-28" /></TableCell>
                    </TableRow>
                  </template>
                  <template v-else-if="certificateCandidates.length">
                    <TableRow v-for="row in certificateCandidates" :key="row.applicationId">
                      <TableCell>
                        <input
                          type="checkbox"
                          class="h-4 w-4 rounded border-border"
                          :disabled="!row.eligible"
                          :checked="selectedCertificateIds.includes(row.applicationId)"
                          @change="toggleCertificateSelection(row.applicationId, ($event.target as HTMLInputElement).checked)"
                        />
                      </TableCell>
                      <TableCell class="font-medium text-foreground">{{ row.studentName }}</TableCell>
                      <TableCell class="text-foreground">
                        <p>{{ row.internshipTitle }}</p>
                        <p class="text-xs text-muted-foreground">{{ row.hostName }}</p>
                      </TableCell>
                      <TableCell class="text-foreground">
                        {{ Math.round(row.approvedHours) }} / {{ Math.round(row.requiredHours) }}
                      </TableCell>
                      <TableCell>
                        <Badge v-if="row.eligible" variant="secondary">Ready</Badge>
                        <span v-else class="text-sm text-muted-foreground">{{ row.skipReason || 'Not eligible' }}</span>
                      </TableCell>
                    </TableRow>
                  </template>
                  <TableRow v-else>
                    <TableCell colspan="5" class="py-8 text-center text-muted-foreground">
                      No accepted placements with completed hours yet.
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card v-else-if="currentView === 'reports'" class="border-border/80 shadow-sm">
        <CardHeader>
          <h2 class="text-2xl font-semibold text-foreground">Reports</h2>
          <p class="text-sm text-muted-foreground">Use these summaries to monitor endorsement flow, school-based opportunities, and placement coverage.</p>
        </CardHeader>
        <CardContent class="space-y-6">
          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card v-for="report in reportCards" :key="report.label" class="border-border/70 shadow-none">
              <CardContent class="p-5">
                <p class="text-sm font-medium text-muted-foreground">{{ report.label }}</p>
                <p class="mt-2 text-3xl font-semibold text-foreground">{{ report.value }}</p>
                <p class="mt-3 text-sm text-muted-foreground">{{ report.copy }}</p>
              </CardContent>
            </Card>
          </div>

          <div v-if="kpi" class="grid gap-4 md:grid-cols-4">
            <Card class="border-border/70 shadow-none">
              <CardContent class="p-4">
                <p class="text-xs font-medium text-muted-foreground">KPI placement rate</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">
                  {{ formatKpiValue(kpi.placementRate, (kpi.studentCount ?? 0) > 0 || (kpi.totalApplications ?? 0) > 0, '%') }}
                </p>
              </CardContent>
            </Card>
            <Card class="border-border/70 shadow-none">
              <CardContent class="p-4">
                <p class="text-xs font-medium text-muted-foreground">Agreements</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ kpi.totalAgreements }}</p>
              </CardContent>
            </Card>
            <Card class="border-border/70 shadow-none">
              <CardContent class="p-4">
                <p class="text-xs font-medium text-muted-foreground">Avg approved OJT hrs</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">
                  {{ formatKpiValue(kpi.avgApprovedOjtHours, kpi.avgApprovedOjtHours != null && (kpi.ojtStudentCount ?? 0) > 0) }}
                </p>
              </CardContent>
            </Card>
            <Card class="border-border/70 shadow-none">
              <CardContent class="p-4">
                <p class="text-xs font-medium text-muted-foreground">Avg assessment score</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">
                  {{ formatKpiValue(kpi.avgAssessmentScore, kpi.avgAssessmentScore != null && (kpi.assessmentCount ?? 0) > 0) }}
                </p>
              </CardContent>
            </Card>
          </div>

          <div class="flex flex-wrap gap-2">
            <Button size="sm" variant="outline" :disabled="exportingReport" @click="downloadReport('placements')">
              Export placements
            </Button>
            <Button size="sm" variant="outline" :disabled="exportingReport" @click="downloadReport('ojt')">
              Export OJT hours
            </Button>
            <Button size="sm" variant="outline" :disabled="exportingReport" @click="downloadReport('assessments')">
              Export assessments
            </Button>
          </div>

          <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="text-lg font-semibold text-foreground">Student OJT Progress</h3>
                <p class="text-sm text-muted-foreground">Approved hours, application status, and latest assessment for department monitoring.</p>
              </div>
              <Button variant="outline" size="sm" :disabled="loadingProgress" @click="loadStudentProgress">
                <LoaderCircle v-if="loadingProgress" class="h-4 w-4 animate-spin" />
                <span>Refresh</span>
              </Button>
            </div>
            <div class="overflow-x-auto rounded-xl border border-border/70">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Student</TableHead>
                    <TableHead>Application</TableHead>
                    <TableHead>Approved Hours</TableHead>
                    <TableHead>Progress</TableHead>
                    <TableHead>Latest Assessment</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <template v-if="loadingProgress">
                    <TableRow v-for="index in 3" :key="index">
                      <TableCell colspan="5"><Skeleton class="h-6 w-full" /></TableCell>
                    </TableRow>
                  </template>
                  <template v-else-if="studentProgress.length">
                    <TableRow v-for="row in studentProgress" :key="row.studentId">
                      <TableCell>
                        <div class="font-medium text-foreground">{{ row.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.course || '—' }}</div>
                      </TableCell>
                      <TableCell>
                        <div class="text-sm text-foreground">{{ row.internshipTitle || '—' }}</div>
                        <Badge class="mt-1" :variant="badgeVariant(row.applicationStatus || 'no_application')">
                          {{ badgeLabel(row.applicationStatus || 'no_application') }}
                        </Badge>
                      </TableCell>
                      <TableCell class="text-foreground">
                        {{ row.approvedOjtHours }} / {{ row.requiredOjtHours }}
                      </TableCell>
                      <TableCell class="text-foreground">{{ row.ojtPercentComplete }}%</TableCell>
                      <TableCell class="text-muted-foreground">
                        <template v-if="row.latestAssessment">
                          {{ statusLabel(row.latestAssessment.stage) }}
                          <span v-if="row.latestAssessment.overallScore != null">
                            · {{ row.latestAssessment.overallScore }}
                          </span>
                        </template>
                        <span v-else>—</span>
                      </TableCell>
                    </TableRow>
                  </template>
                  <TableRow v-else>
                    <TableCell colspan="5" class="py-8 text-center text-muted-foreground">
                      No student progress records yet.
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card v-else class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Endorsements</h2>
            <p class="text-sm text-muted-foreground">
              Endorse company-hosted applications to the partner, and accept or reject school-hosted placements after endorsement.
            </p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="searchQuery" placeholder="Search pending endorsements..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent class="space-y-6">
          <template v-if="loadingApplications">
            <Card v-for="index in 3" :key="index" class="border-border/70 shadow-none">
              <CardContent class="space-y-3 p-5">
                <Skeleton class="h-5 w-40" />
                <Skeleton class="h-4 w-56" />
                <Skeleton class="h-4 w-32" />
                <div class="flex gap-3 pt-2">
                  <Skeleton class="h-10 w-24" />
                  <Skeleton class="h-10 w-24" />
                </div>
              </CardContent>
            </Card>
          </template>
          <template v-else>
            <div class="space-y-4">
              <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Awaiting endorsement</h3>
              <template v-if="pendingEndorsements.length">
                <Card v-for="endorsement in pendingEndorsements" :key="endorsement.id" class="border-border/70 shadow-none">
                  <CardContent class="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">
                    <div class="space-y-2">
                      <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-semibold text-foreground">{{ endorsement.internName }}</h3>
                        <Badge variant="warning">Submitted</Badge>
                        <Badge v-if="endorsement.isSchoolHosted" variant="outline">School-hosted</Badge>
                      </div>
                      <p class="text-sm text-muted-foreground">{{ endorsement.course }} · {{ endorsement.company }}</p>
                      <p class="text-sm text-muted-foreground">Applied {{ endorsement.appliedAt }}</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                      <Button
                        variant="ghost"
                        class="w-full gap-2 sm:w-auto"
                        :disabled="submittingDecision"
                        @click="openEndorsementDetail(endorsement, 'profile')"
                      >
                        <Eye class="h-4 w-4" />
                        View Profile
                      </Button>
                      <Button
                        variant="outline"
                        class="w-full gap-2 sm:w-auto"
                        :disabled="submittingDecision"
                        @click="openEndorsementDetail(endorsement, 'tracker')"
                      >
                        Open tracker
                      </Button>
                      <Button
                        class="w-full gap-2 sm:w-auto"
                        :disabled="submittingDecision"
                        @click="openDecisionDialog(endorsement.id, 'endorsed', endorsement.internName)"
                      >
                        <CheckCircle2 class="h-4 w-4" />
                        Endorse
                      </Button>
                      <Button
                        variant="outline"
                        class="w-full gap-2 sm:w-auto"
                        :disabled="submittingDecision"
                        @click="openDecisionDialog(endorsement.id, 'school_rejected', endorsement.internName)"
                      >
                        <XCircle class="h-4 w-4" />
                        Reject
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              </template>
              <p v-else class="text-sm text-muted-foreground">
                No submitted applications are waiting for school endorsement.
              </p>
            </div>

            <div class="space-y-4">
              <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">School-hosted — awaiting acceptance</h3>
              <template v-if="pendingSchoolHostedAcceptances.length">
                <Card
                  v-for="endorsement in pendingSchoolHostedAcceptances"
                  :key="endorsement.id"
                  class="border-border/70 shadow-none"
                >
                  <CardContent class="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">
                    <div class="space-y-2">
                      <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-semibold text-foreground">{{ endorsement.internName }}</h3>
                        <Badge variant="outline">Endorsed</Badge>
                        <Badge variant="outline">School-hosted</Badge>
                      </div>
                      <p class="text-sm text-muted-foreground">{{ endorsement.course }} · {{ endorsement.company }}</p>
                      <p class="text-sm text-muted-foreground">Applied {{ endorsement.appliedAt }}</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                      <Button
                        variant="ghost"
                        class="w-full gap-2 sm:w-auto"
                        :disabled="submittingDecision"
                        @click="openEndorsementDetail(endorsement, 'profile')"
                      >
                        <Eye class="h-4 w-4" />
                        View Profile
                      </Button>
                      <Button
                        variant="outline"
                        class="w-full gap-2 sm:w-auto"
                        :disabled="submittingDecision"
                        @click="openEndorsementDetail(endorsement, 'tracker')"
                      >
                        Open tracker
                      </Button>
                      <Button
                        class="w-full gap-2 sm:w-auto"
                        :disabled="submittingDecision"
                        @click="openDecisionDialog(endorsement.id, 'accepted', endorsement.internName)"
                      >
                        <CheckCircle2 class="h-4 w-4" />
                        Accept
                      </Button>
                      <Button
                        variant="outline"
                        class="w-full gap-2 sm:w-auto"
                        :disabled="submittingDecision"
                        @click="openDecisionDialog(endorsement.id, 'rejected', endorsement.internName)"
                      >
                        <XCircle class="h-4 w-4" />
                        Reject
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              </template>
              <p v-else class="text-sm text-muted-foreground">
                No endorsed school-hosted applications are waiting for acceptance.
              </p>
            </div>

            <div class="space-y-4">
              <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Withdrawn — placed elsewhere</h3>
              <template v-if="withdrawnPlacedElsewhere.length">
                <Card
                  v-for="endorsement in withdrawnPlacedElsewhere"
                  :key="endorsement.id"
                  class="border-border/70 shadow-none"
                >
                  <CardContent class="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">
                    <div class="space-y-2">
                      <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-semibold text-foreground">{{ endorsement.internName }}</h3>
                        <Badge variant="destructive">{{ badgeLabel(endorsement.status) }}</Badge>
                      </div>
                      <p class="text-sm text-muted-foreground">
                        {{ endorsement.internshipTitle }} · {{ endorsement.company }}
                      </p>
                      <p class="text-sm text-muted-foreground">
                        Reason: {{ endorsement.withdrawalReason }} · Applied {{ endorsement.appliedAt }}
                      </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                      <Button
                        variant="ghost"
                        class="w-full gap-2 sm:w-auto"
                        @click="openEndorsementDetail(endorsement, 'profile')"
                      >
                        <Eye class="h-4 w-4" />
                        View Profile
                      </Button>
                      <Button
                        variant="outline"
                        class="w-full gap-2 sm:w-auto"
                        @click="openEndorsementDetail(endorsement, 'tracker')"
                      >
                        Open tracker
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              </template>
              <p v-else class="text-sm text-muted-foreground">
                No applications were withdrawn because the intern placed elsewhere.
              </p>
            </div>
          </template>
        </CardContent>
      </Card>
    </div>

    <AlertDialog
      :open="!!pendingDecision"
      :title="decisionDialogTitle(pendingDecision?.action)"
      :description="decisionDialogDescription(pendingDecision)"
      :action-label="decisionActionLabel(pendingDecision?.action)"
      :action-variant="pendingDecision?.action === 'endorsed' || pendingDecision?.action === 'accepted' ? 'default' : 'outline'"
      @update:open="(open) => { if (!open) pendingDecision = null }"
      @action="confirmDecision"
    />

    <Dialog :open="bulkPreviewOpen" @update:open="(open) => { bulkPreviewOpen = open }">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>Preview certificates</DialogTitle>
          <p class="text-sm text-muted-foreground">
            Review the {{ selectedEligibleCertificates.length }} student{{ selectedEligibleCertificates.length === 1 ? '' : 's' }} selected for issuance.
          </p>
        </DialogHeader>
        <div class="mt-4 max-h-72 space-y-2 overflow-y-auto">
          <div
            v-for="row in selectedEligibleCertificates"
            :key="row.applicationId"
            class="rounded-lg border border-border px-3 py-2 text-sm"
          >
            <p class="font-medium text-foreground">{{ row.studentName }}</p>
            <p class="text-muted-foreground">
              {{ row.internshipTitle }} · {{ Math.round(row.approvedHours) }}/{{ Math.round(row.requiredHours) }} hrs
            </p>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <Button variant="outline" @click="close">Cancel</Button>
          <Button @click="bulkConfirmOpen = true">Continue</Button>
        </div>
      </template>
    </Dialog>

    <AlertDialog
      :open="bulkConfirmOpen"
      title="Issue certificates?"
      :description="`This will issue certificates for ${selectedEligibleCertificates.length} student${selectedEligibleCertificates.length === 1 ? '' : 's'}. Ineligible or already-issued rows are skipped automatically.`"
      action-label="Issue certificates"
      :action-variant="'default'"
      @update:open="(open) => { bulkConfirmOpen = open }"
      @action="confirmBulkIssue"
    />

    <Dialog :open="!!selectedEndorsement" @update:open="(open) => { if (!open) selectedEndorsement = null }">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>
            {{ endorsementDialogMode === 'profile' ? 'Applicant profile' : 'Application tracker' }}
          </DialogTitle>
          <p class="text-sm text-muted-foreground">
            {{
              endorsementDialogMode === 'profile'
                ? `${selectedEndorsement?.internName || 'This applicant'}'s details.`
                : 'Progress stages for this application.'
            }}
          </p>
        </DialogHeader>

        <div v-if="selectedEndorsement" class="mt-6 space-y-4">
          <div v-if="endorsementDialogMode === 'profile'" class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-muted p-4">
              <p class="text-sm text-muted-foreground">Applicant</p>
              <p class="mt-2 font-medium text-foreground">{{ selectedEndorsement.internName }}</p>
              <p class="text-sm text-muted-foreground">{{ selectedEndorsement.email }}</p>
            </div>
            <div class="rounded-xl bg-muted p-4">
              <p class="text-sm text-muted-foreground">Internship</p>
              <p class="mt-2 font-medium text-foreground">{{ selectedEndorsement.internshipTitle }}</p>
              <p class="text-sm text-muted-foreground">{{ selectedEndorsement.company }}</p>
            </div>
            <div class="rounded-xl bg-muted p-4">
              <p class="text-sm text-muted-foreground">Application date</p>
              <p class="mt-2 font-medium text-foreground">{{ selectedEndorsement.appliedAt }}</p>
            </div>
            <div class="rounded-xl bg-muted p-4">
              <p class="text-sm text-muted-foreground">Resume</p>
              <div v-if="selectedEndorsement.resume" class="mt-2">
                <FilePreviewActions
                  file-name="Resume"
                  :source="selectedEndorsement.resume"
                />
              </div>
              <p v-else class="mt-2 font-medium text-foreground">No resume on file</p>
            </div>
            <div class="rounded-xl bg-muted p-4 sm:col-span-2">
              <p class="text-sm text-muted-foreground">Skills</p>
              <div class="mt-2">
                <SkillChips
                  :skills="selectedEndorsement.skills || []"
                  mode="intern"
                  empty-text="This student has not added skills yet."
                />
              </div>
            </div>
          </div>

          <div v-if="endorsementDialogMode === 'profile'" class="rounded-xl border border-border/70 bg-card p-4">
            <p class="text-sm text-muted-foreground">Application status</p>
            <p class="mt-2">
              <Badge :variant="badgeVariant(selectedEndorsement.status)">{{ badgeLabel(selectedEndorsement.status) }}</Badge>
            </p>
          </div>

          <ApplicationTrackerPanel
            v-if="endorsementDialogMode === 'tracker'"
            :application-id="selectedEndorsement.id"
            mode="school"
            :allow-schedule="!!selectedEndorsement.isSchoolHosted && selectedEndorsement.status !== 'submitted'"
          />
        </div>

        <div class="mt-6 flex justify-end">
          <Button variant="outline" @click="close">Close</Button>
        </div>
      </template>
    </Dialog>

    <div
      v-if="submittingDecision"
      class="fixed bottom-[calc(1.5rem+env(safe-area-inset-bottom))] right-4 z-dropdown flex max-w-[calc(100vw-2rem)] items-center gap-2 rounded-full bg-foreground px-4 py-2 text-sm text-background shadow-lg sm:right-6"
    >
      <LoaderCircle class="h-4 w-4 animate-spin" />
      Saving decision...
    </div>
  </MainLayout>
</template>
