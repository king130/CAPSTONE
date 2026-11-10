<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  BriefcaseBusiness,
  Building2,
  CalendarDays,
  Edit3,
  FileText,
  GraduationCap,
  LoaderCircle,
  Mail,
  MapPin,
  UserCircle2,
} from 'lucide-vue-next'

import ApplicationCapacity from '@/components/internships/ApplicationCapacity.vue'
import SkillChips from '@/components/skills/SkillChips.vue'
import SkillsWizard from '@/components/skills/SkillsWizard.vue'
import HostBrowseTabs from '@/components/opportunities/HostBrowseTabs.vue'
import FindInternships from '@/views/FindInternships.vue'
import ApplicationTrackerPanel from '@/components/ApplicationTrackerPanel.vue'
import HoursAgreementPanel from '@/components/HoursAgreementPanel.vue'
import PlacementSchedulePanel from '@/components/PlacementSchedulePanel.vue'
import OjtCalendarPanel from '@/components/OjtCalendarPanel.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import AlertDialog from '@/components/ui/alert-dialog/AlertDialog.vue'
import DropdownMenu from '@/components/ui/dropdown-menu/DropdownMenu.vue'
import DropdownMenuItem from '@/components/ui/dropdown-menu/DropdownMenuItem.vue'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue'
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue'
import FormControl from '@/components/ui/form/FormControl.vue'
import FormItem from '@/components/ui/form/FormItem.vue'
import FormLabel from '@/components/ui/form/FormLabel.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import { useActivePlacement } from '@/composables/useActivePlacement'
import {
  closePlacement,
  submitApplication,
  subscribeApplications,
  type ApplicationRecord,
} from '@/services/applications'
import { applicationStatusLabel } from '@/services/applicationTracker'
import { listDocuments, uploadDocuments, type DocumentRecord } from '@/services/documents'
import { buildProfileAvatarUrl } from '@/services/profileMedia'
import { getInternship, subscribeEligibleInternships, type InternshipRecord } from '@/services/internships'
import { listInterviews, type InterviewRecord } from '@/services/interviews'
import { listAssessments, type AssessmentRecord } from '@/services/assessments'
import {
  deleteDtr,
  dtrStatusLabel,
  dtrRowStatus,
  dtrStatusDetail,
  isPendingDtr,
  isRejectedOrRevision,
  listDtrPlacements,
  listDtrSubmissions,
  resubmitDtr,
  submitDtr,
  type DtrPlacement,
  type DtrSubmission,
} from '@/services/dtrService'
import { extractDtrSuggestions } from '@/utils/dtrExtract'
import { fetchOJTProgress, listInternOJTLogs, type OJTLog } from '@/services/ojtService'
import {
  listCertificates,
  printCertificateHtml,
  viewCertificateHtml,
  type CertificateRecord,
} from '@/services/certificates'
import { listSavedInternships } from '@/services/savedInternships'
import { updateCurrentUserProfile } from '@/services/auth'
import {
  fetchStudentSkills,
  saveStudentSkills,
  type SelectedSkill,
} from '@/services/skills'
import { useAuthStore } from '@/stores/auth'
import { findInternshipIdForFocus, resolveInternshipFocusId } from '@/utils/internshipFocus'

type InternView =
  | 'settings'
  | 'opportunities'
  | 'openings'
  | 'documents'
  | 'internship'
  | 'placement'
  | 'dashboard'
  | 'saved'

interface ProfileForm {
  displayName: string
  email: string
  schoolName: string
  course: string
  yearLevel: string
  contactNumber: string
}

interface ApplicationDialogState {
  internshipId: string
  title: string
  hostName: string
  requiredDocuments: string[]
  requiresResume: boolean
}

const authStore = useAuthStore()
const { success, error } = useToast()
const { hasActivePlacement, placementBlockMessage, activePlacement, refreshActivePlacement } = useActivePlacement()
const route = useRoute()
const router = useRouter()
const activeView = ref<InternView>('settings')
const applications = ref<ApplicationRecord[]>([])
const internships = ref<InternshipRecord[]>([])
const savedDocuments = ref<DocumentRecord[]>([])
const savedInternships = ref<InternshipRecord[]>([])
const interviewsByApplication = ref<Record<string, InterviewRecord[]>>({})
const assessmentsByApplication = ref<Record<string, AssessmentRecord[]>>({})
const dtrsByApplication = ref<Record<string, DtrSubmission[]>>({})
const dtrPlacements = ref<DtrPlacement[]>([])
const allDtrs = ref<DtrSubmission[]>([])
const dtrSubmitting = ref(false)
const dtrApplicationId = ref('')
const dtrPeriodStart = ref('')
const dtrPeriodEnd = ref('')
const dtrHours = ref('')
const dtrRemarks = ref('')
const dtrFile = ref<File | null>(null)
const dtrReadingFile = ref(false)
const dtrFileMessage = ref('')
const dtrResubmitTarget = ref<DtrSubmission | null>(null)
const dtrDeleteTarget = ref<DtrSubmission | null>(null)
const declineConfirmOpen = ref(false)
const ojtRequiredHours = ref<number | null>(null)
const ojtLogsByApplication = ref<Record<string, OJTLog[]>>({})
const coverByInternship = ref<Record<string, string | null>>({})
const certificates = ref<CertificateRecord[]>([])
const certificatesLoading = ref(false)
const certificatesError = ref<string | null>(null)
const certificateActionId = ref<string | null>(null)
const applicationsLoading = ref(true)
const internshipsLoading = ref(true)
const savedInternshipsLoading = ref(false)
const applicationExtrasLoading = ref(false)
const documentsLoading = ref(true)
const profileDialogOpen = ref(false)
const savingProfile = ref(false)
const applyingInternshipId = ref<string | null>(null)
const applicationDialogOpen = ref(false)
const selectedOpportunity = ref<ApplicationDialogState | null>(null)
const applicationResume = ref('')
const selectedDocuments = ref<string[]>([])
const selectedRequirementDocuments = ref<string[]>([])
const selectedSavedDocumentIds = ref<string[]>([])
const selectedSavedResumeId = ref('')
const selectedSavedEndorsementId = ref('')
const endorsementFile = ref<File | null>(null)
const endorsementPreviewUrl = ref<string | null>(null)
const endorsementUploadProgress = ref(0)
const endorsementDragOver = ref(false)
const resumeFile = ref<File | null>(null)
const supportingFiles = ref<File[]>([])
const documentUploadFiles = ref<File[]>([])
const documentUploadCategory = ref('general')
const uploadingSavedDocuments = ref(false)
const uploadingEndorsement = ref(false)
const profileForm = ref<ProfileForm>({
  displayName: '',
  email: '',
  schoolName: '',
  course: '',
  yearLevel: '',
  contactNumber: '',
})

let unsubscribeApplications: (() => void) | null = null
let unsubscribeInternships: (() => void) | null = null

function stopSubscriptions() {
  unsubscribeApplications?.()
  unsubscribeInternships?.()
  unsubscribeApplications = null
  unsubscribeInternships = null
}

function startSubscriptions(userId?: string) {
  stopSubscriptions()

  if (!userId) {
    applications.value = []
    internships.value = []
    savedDocuments.value = []
    certificates.value = []
    certificatesError.value = null
    applicationsLoading.value = false
    internshipsLoading.value = false
    documentsLoading.value = false
    certificatesLoading.value = false
    return
  }

  applicationsLoading.value = true
  internshipsLoading.value = true
  documentsLoading.value = true

  unsubscribeApplications = subscribeApplications(userId, (items) => {
    applications.value = items
    applicationsLoading.value = false
    void loadApplicationExtras()
  })

  unsubscribeInternships = subscribeEligibleInternships((items) => {
    internships.value = items
    internshipsLoading.value = false
  })

  void loadSavedDocuments()
  void loadSavedInternshipsList()
}

async function loadSavedInternshipsList() {
  if (!authStore.user?.uid) {
    savedInternships.value = []
    return
  }
  savedInternshipsLoading.value = true
  try {
    const result = await listSavedInternships({ page: 1, per_page: 50 })
    savedInternships.value = result.data
  } catch {
    savedInternships.value = []
  } finally {
    savedInternshipsLoading.value = false
  }
}

async function loadCertificates() {
  certificatesLoading.value = true
  certificatesError.value = null
  try {
    certificates.value = await listCertificates()
  } catch (caughtError) {
    certificates.value = []
    certificatesError.value =
      caughtError instanceof Error && caughtError.message
        ? caughtError.message
        : 'Unable to load certificates.'
    error(caughtError, { fallback: 'Unable to load certificates.' })
  } finally {
    certificatesLoading.value = false
  }
}

async function openStudentCertificate(certificateId: string) {
  certificateActionId.value = certificateId
  try {
    await viewCertificateHtml(certificateId)
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to open your certificate.' })
  } finally {
    certificateActionId.value = null
  }
}

async function printStudentCertificate(certificateId: string) {
  certificateActionId.value = certificateId
  try {
    await printCertificateHtml(certificateId)
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to open the print dialog for your certificate.' })
  } finally {
    certificateActionId.value = null
  }
}

async function loadApplicationExtras() {
  if (!applications.value.length) {
    interviewsByApplication.value = {}
    assessmentsByApplication.value = {}
    dtrsByApplication.value = {}
    allDtrs.value = []
    dtrPlacements.value = []
    ojtLogsByApplication.value = {}
    coverByInternship.value = {}
    void loadCertificates()
    return
  }

  applicationExtrasLoading.value = true
  try {
    const [interviews, logs, assessments, dtrs, placements, progress] = await Promise.all([
      listInterviews().catch(() => [] as InterviewRecord[]),
      listInternOJTLogs().catch(() => [] as OJTLog[]),
      listAssessments().catch(() => [] as AssessmentRecord[]),
      listDtrSubmissions().catch(() => [] as DtrSubmission[]),
      listDtrPlacements().catch(() => [] as DtrPlacement[]),
      fetchOJTProgress().catch(() => null),
    ])
    allDtrs.value = dtrs
    dtrPlacements.value = placements
    void loadCertificates()
    ojtRequiredHours.value = progress?.requiredHours ?? null

    const interviewMap: Record<string, InterviewRecord[]> = {}
    for (const interview of interviews) {
      const key = String(interview.applicationId)
      if (!interviewMap[key]) interviewMap[key] = []
      interviewMap[key].push(interview)
    }
    interviewsByApplication.value = interviewMap

    const assessmentMap: Record<string, AssessmentRecord[]> = {}
    for (const assessment of assessments) {
      const key = String(assessment.applicationId)
      if (!assessmentMap[key]) assessmentMap[key] = []
      assessmentMap[key].push(assessment)
    }
    assessmentsByApplication.value = assessmentMap

    const dtrMap: Record<string, DtrSubmission[]> = {}
    for (const submission of dtrs) {
      const key = String(submission.applicationId)
      if (!dtrMap[key]) dtrMap[key] = []
      dtrMap[key].push(submission)
    }
    dtrsByApplication.value = dtrMap

    const logMap: Record<string, OJTLog[]> = {}
    for (const log of logs) {
      const key = String(log.applicationId || '')
      if (!key) continue
      if (!logMap[key]) logMap[key] = []
      logMap[key].push(log)
    }
    ojtLogsByApplication.value = logMap

    const ids = Array.from(new Set(applications.value.map((app) => String(app.internshipId)).filter(Boolean)))
    const coverEntries = await Promise.all(
      ids.map(async (id) => {
        const fromEligible = internships.value.find((item) => item.id === id)
        if (fromEligible?.coverImage) return [id, fromEligible.coverImage] as const
        const detail = await getInternship(id).catch(() => null)
        return [id, detail?.coverImage ?? null] as const
      }),
    )
    coverByInternship.value = Object.fromEntries(coverEntries)
  } finally {
    applicationExtrasLoading.value = false
  }
}

const applicationCards = computed(() =>
  [...applications.value]
    .sort((a, b) => new Date(b.createdAt || 0).getTime() - new Date(a.createdAt || 0).getTime())
    .map((application) => ({
      application,
      coverImage: coverByInternship.value[String(application.internshipId)] ?? null,
    })),
)

const browsePageTitle = ref('Partner Companies')

const pageTitle = computed(() => {
  const titles: Record<InternView, string> = {
    settings: 'Profile',
    opportunities: browsePageTitle.value,
    openings: 'Internship Opportunities',
    saved: 'Saved',
    documents: 'Documents',
    internship: 'Applications',
    placement: 'Placement',
    dashboard: 'Interviews',
  }

  return titles[activeView.value]
})

const currentProfile = computed(() => authStore.user?.profile as Record<string, unknown> | undefined)
const profileSkills = ref<SelectedSkill[]>([])
const skillsWizardOpen = ref(false)
const savingSkills = ref(false)

const profileSkillChips = computed(() => {
  if (profileSkills.value.length) return profileSkills.value
  const raw = currentProfile.value?.skills
  return Array.isArray(raw) ? (raw as SelectedSkill[]) : []
})

async function loadProfileSkills() {
  try {
    const res = await fetchStudentSkills()
    profileSkills.value = res.data || []
  } catch {
    const raw = currentProfile.value?.skills
    profileSkills.value = Array.isArray(raw) ? (raw as SelectedSkill[]) : []
  }
}

async function onProfileSkillsSaved(next: SelectedSkill[]) {
  savingSkills.value = true
  try {
    const res = await saveStudentSkills(
      next.map((skill) => ({ id: skill.id, level: skill.level || 'beginner' })),
    )
    profileSkills.value = res.data || next
    await authStore.refreshUser()
    success('Skills saved', { description: 'Your skills are now on your profile.' })
  } catch (err) {
    error(err, { fallback: 'Could not save skills. Please try again.' })
  } finally {
    savingSkills.value = false
  }
}

const profileAvatarUrl = computed(() => {
  const currentUser = authStore.user
  const profile = currentProfile.value
  if (currentUser?.uid && profile?.avatarPath) {
    return buildProfileAvatarUrl(currentUser.uid, currentUser.updatedAt)
  }
  return ''
})

const userInitials = computed(() => {
  const name = authStore.user?.displayName || authStore.user?.email || 'User'
  return name
    .split(' ')
    .filter(Boolean)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

function applicationInterviews(applicationId: string | number) {
  return interviewsByApplication.value[String(applicationId)] ?? []
}

function interviewTrackerPriority(application: ApplicationRecord) {
  const interviews = applicationInterviews(application.id)
  if (interviews.some((interview) => interview.status === 'proposed' || interview.status === 'pending')) return 3
  if (interviews.some((interview) => interview.status === 'confirmed' || interview.status === 'scheduled')) return 2
  if (interviews.some((interview) => interview.status === 'completed')) return 1
  return 0
}

const latestApplication = computed(() => {
  return [...applications.value].sort((a, b) => {
    const priority = interviewTrackerPriority(b) - interviewTrackerPriority(a)
    if (priority !== 0) return priority
    return new Date(b.updatedAt || b.createdAt || 0).getTime() - new Date(a.updatedAt || a.createdAt || 0).getTime()
  })[0]
})

const currentCourse = computed(() => String(currentProfile.value?.course || '').trim())

function isResumeCategory(document: DocumentRecord) {
  return ['application-resume', 'internship-application'].includes(String(document.category || '').toLowerCase())
}

function isEndorsementCategory(document: DocumentRecord) {
  return ['application-endorsement', 'endorsement', 'endorsement-letter'].includes(
    String(document.category || '').toLowerCase(),
  )
}

const approvedDocuments = computed(() => savedDocuments.value.filter((document) => document.status === 'approved'))

const approvedResumeDocuments = computed(() => approvedDocuments.value.filter((document) => isResumeCategory(document)))

const approvedEndorsementDocuments = computed(() =>
  approvedDocuments.value.filter((document) => isEndorsementCategory(document)),
)

const approvedSupportingDocuments = computed(() =>
  approvedDocuments.value.filter((document) => !isResumeCategory(document) && !isEndorsementCategory(document)),
)

const endorsementChecklistStatus = computed(() => {
  if (endorsementFile.value || selectedSavedEndorsementId.value || selectedRequirementDocuments.value.includes('Endorsement')) {
    return 'uploaded' as const
  }
  return 'missing' as const
})

const placementSummary = computed(() => {
  const accepted = applications.value.find((application) =>
    ['accepted', 'approved', 'active'].includes(String(application.status || '').trim().toLowerCase()),
  )

  return {
    host: accepted?.companyName || accepted?.schoolName || 'Placement not assigned yet',
    role: accepted?.internshipTitle || 'No accepted placement yet',
    updatedAt: formatDate(accepted?.updatedAt || accepted?.createdAt),
    status: accepted ? badgeLabel(accepted.status) : 'Pending',
  }
})

async function onDtrFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  const next = input.files?.[0] ?? null
  dtrFile.value = next
  dtrFileMessage.value = ''
  if (!next) return

  dtrHours.value = ''
  dtrPeriodStart.value = ''
  dtrPeriodEnd.value = ''
  dtrReadingFile.value = true
  try {
    const suggestion = await extractDtrSuggestions(next)
    const found = suggestion.hours != null || suggestion.periodStart || suggestion.periodEnd
    if (!found) {
      dtrFileMessage.value = 'This file could not be read. Enter the hours and dates manually.'
      return
    }
    if (suggestion.hours != null) dtrHours.value = String(suggestion.hours)
    if (suggestion.periodStart) dtrPeriodStart.value = suggestion.periodStart
    if (suggestion.periodEnd) dtrPeriodEnd.value = suggestion.periodEnd
    dtrFileMessage.value = 'Suggested from the file. Review and correct the hours and dates before submitting.'
  } catch {
    dtrHours.value = ''
    dtrPeriodStart.value = ''
    dtrPeriodEnd.value = ''
    dtrFileMessage.value = 'This file could not be read. Enter the hours and dates manually.'
  } finally {
    dtrReadingFile.value = false
  }
}

function startDtrResubmit(row: DtrSubmission) {
  dtrResubmitTarget.value = row
  dtrApplicationId.value = String(row.applicationId)
  dtrPeriodStart.value = row.periodStart
  dtrPeriodEnd.value = row.periodEnd
  dtrHours.value = String(row.submittedHours)
  dtrRemarks.value = row.remarks ?? ''
  dtrFile.value = null
  dtrFileMessage.value = ''
}

function resetDtrForm() {
  dtrResubmitTarget.value = null
  dtrApplicationId.value = ''
  dtrPeriodStart.value = ''
  dtrPeriodEnd.value = ''
  dtrHours.value = ''
  dtrRemarks.value = ''
  dtrFile.value = null
  dtrFileMessage.value = ''
}

function askDeleteDtr(row: DtrSubmission) {
  dtrDeleteTarget.value = row
}

async function confirmDeleteDtr() {
  const row = dtrDeleteTarget.value
  if (!row) return
  try {
    await deleteDtr(row.id)
    success('DTR deleted.')
    await loadApplicationExtras()
  } catch (caught) {
    error(caught, { fallback: 'Unable to delete this DTR.' })
  } finally {
    dtrDeleteTarget.value = null
  }
}

async function submitStudentDtr() {
  if (!dtrFile.value || !dtrApplicationId.value || !dtrPeriodStart.value || !dtrPeriodEnd.value || !dtrHours.value) {
    error('Choose a placement, period, hours, and DTR file.')
    return
  }
  dtrSubmitting.value = true
  try {
    const payload = {
      applicationId: Number(dtrApplicationId.value),
      periodStart: dtrPeriodStart.value,
      periodEnd: dtrPeriodEnd.value,
      submittedHours: Number(dtrHours.value),
      remarks: dtrRemarks.value,
      file: dtrFile.value,
    }
    if (dtrResubmitTarget.value) {
      await resubmitDtr(dtrResubmitTarget.value.id, payload)
      success('DTR resubmitted. Status is Submitted.')
    } else {
      await submitDtr(payload)
      success('DTR uploaded. Status is Submitted.')
    }
    resetDtrForm()
    await loadApplicationExtras()
  } catch (caught) {
    error(caught, { fallback: 'Unable to upload the DTR.' })
  } finally {
    dtrSubmitting.value = false
  }
}

const placementCertificates = computed(() =>
  [...certificates.value].sort((a, b) => {
    const aTime = new Date(a.issuedAt || a.createdAt || 0).getTime()
    const bTime = new Date(b.issuedAt || b.createdAt || 0).getTime()
    return bTime - aTime
  }),
)

const appliedInternshipIds = computed(() => new Set(applications.value.map((application) => String(application.internshipId))))

function formatDate(value?: string) {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? '—'
    : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function badgeVariant(status?: string) {
  const normalized = String(status || '').trim().toLowerCase()
  if (['endorsed'].includes(normalized)) return 'outline'
  if (['approved', 'accepted', 'active'].includes(normalized)) return 'success'
  if (['rejected', 'declined', 'cancelled', 'withdrawn_placed_elsewhere', 'placement_cancelled', 'placement_declined'].includes(normalized)) {
    return 'destructive'
  }
  if (normalized === 'school_rejected' || normalized === 'placement_ended') return 'destructive'
  return 'warning'
}

function applicationProgressLabel(application: ApplicationRecord) {
  return applicationStatusLabel(application.status, application.trackerStage, application.withdrawalReason)
}

function badgeLabel(status?: string) {
  return applicationStatusLabel(status)
}

function internViewFromRouteName(name: unknown): InternView {
  if (name === 'intern-opportunities') return 'opportunities'
  if (name === 'intern-openings') return 'openings'
  if (name === 'intern-saved') return 'saved'
  if (name === 'intern-documents') return 'documents'
  if (name === 'intern-applications') return 'internship'
  if (name === 'intern-placement') return 'placement'
  if (name === 'intern-tracker') return 'dashboard'
  return 'settings'
}

function isPartneredForSaved(item: InternshipRecord): boolean {
  if (typeof item.partneredWithMySchool === 'boolean') return item.partneredWithMySchool
  return item.hostType === 'school'
}

function applyFromSaved(item: InternshipRecord) {
  if (hasActivePlacement.value) {
    error(placementBlockMessage.value || 'You already have an active placement.')
    return
  }
  if (!isPartneredForSaved(item)) return
  void router.push({ name: 'intern-openings', query: { focus: item.id } })
}

function hydrateProfileForm() {
  const profile = currentProfile.value
  profileForm.value = {
    displayName: authStore.user?.displayName || '',
    email: authStore.user?.email || '',
    schoolName: String(profile?.schoolName || ''),
    course: String(profile?.course || ''),
    yearLevel: String(profile?.yearLevel || ''),
    contactNumber: String(profile?.contactNumber || ''),
  }
}

function openProfileDialog() {
  hydrateProfileForm()
  profileDialogOpen.value = true
}

function openApplicationDialog(opportunityId: string) {
  const opportunity = internships.value.find((item) => String(item.id) === String(opportunityId))
  if (!opportunity) return

  selectedOpportunity.value = {
    internshipId: String(opportunity.id),
    title: opportunity.title || 'Untitled opportunity',
    hostName: opportunity.companyName || opportunity.hostName || opportunity.schoolName || 'Host',
    requiredDocuments: ['Endorsement'],
    requiresResume: false,
  }
  const savedResume = savedDocuments.value.find((document) => isResumeCategory(document) && document.fileUrl)
  applicationResume.value = savedResume?.fileUrl || ''
  selectedDocuments.value = []
  selectedRequirementDocuments.value = []
  selectedSavedDocumentIds.value = []
  selectedSavedResumeId.value = ''
  selectedSavedEndorsementId.value = ''
  const savedEndorsement = approvedEndorsementDocuments.value.find((document) => document.fileUrl)
  if (savedEndorsement) {
    selectedSavedEndorsementId.value = savedEndorsement.id
    selectedRequirementDocuments.value = ['Endorsement']
  }
  resumeFile.value = null
  endorsementFile.value = null
  if (endorsementPreviewUrl.value) URL.revokeObjectURL(endorsementPreviewUrl.value)
  endorsementPreviewUrl.value = null
  supportingFiles.value = []

  applicationDialogOpen.value = true
}

/** Tracks which `?focus=` internship id was already opened so async reloads do not reopen it. */
const consumedOpportunityFocusId = ref<string | null>(null)

function tryFocusOpportunityFromQuery() {
  if (activeView.value !== 'opportunities' && activeView.value !== 'openings') return

  const focusId = resolveInternshipFocusId(route.query.focus)
  if (!focusId) {
    consumedOpportunityFocusId.value = null
    return
  }

  if (consumedOpportunityFocusId.value === focusId) return

  if (hasActivePlacement.value) {
    consumedOpportunityFocusId.value = focusId
    error(placementBlockMessage.value || 'You already have an active placement.')
    return
  }

  const matchedId = findInternshipIdForFocus(internships.value, focusId)
  if (!matchedId) return

  consumedOpportunityFocusId.value = focusId
  openApplicationDialog(matchedId)
}

async function declineActivePlacement() {
  if (!activePlacement.value?.applicationId) return
  try {
    await closePlacement(activePlacement.value.applicationId, 'declined')
    await refreshActivePlacement()
    success('Placement declined', {
      description: 'You can apply to other openings again. Earlier withdrawn applications stay withdrawn.',
    })
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to decline this placement.' })
  }
}

function toggleRequirementDocument(documentName: string) {
  if (selectedRequirementDocuments.value.includes(documentName)) {
    selectedRequirementDocuments.value = selectedRequirementDocuments.value.filter((item) => item !== documentName)
    return
  }

  selectedRequirementDocuments.value = [...selectedRequirementDocuments.value, documentName]
}

function toggleSavedDocument(documentId: string) {
  if (selectedSavedDocumentIds.value.includes(documentId)) {
    selectedSavedDocumentIds.value = selectedSavedDocumentIds.value.filter((item) => item !== documentId)
    return
  }

  selectedSavedDocumentIds.value = [...selectedSavedDocumentIds.value, documentId]
}

function selectSavedResume(documentId: string) {
  if (selectedSavedResumeId.value === documentId) {
    selectedSavedResumeId.value = ''
    applicationResume.value = ''
    return
  }

  selectedSavedResumeId.value = documentId
  const selectedResume = approvedResumeDocuments.value.find((document) => document.id === documentId)
  applicationResume.value = selectedResume?.fileUrl || ''
}

const applicationRequirementsComplete = computed(() => {
  if (!selectedOpportunity.value) return false
  return (
    !!endorsementFile.value ||
    !!selectedSavedEndorsementId.value ||
    selectedRequirementDocuments.value.includes('Endorsement')
  )
})

function handleResumeFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  resumeFile.value = input.files?.[0] ?? null
  if (resumeFile.value) {
    selectedSavedResumeId.value = ''
  }
}

function selectSavedEndorsement(documentId: string) {
  if (selectedSavedEndorsementId.value === documentId) {
    selectedSavedEndorsementId.value = ''
    selectedRequirementDocuments.value = selectedRequirementDocuments.value.filter((item) => item !== 'Endorsement')
    return
  }
  selectedSavedEndorsementId.value = documentId
  endorsementFile.value = null
  if (endorsementPreviewUrl.value) URL.revokeObjectURL(endorsementPreviewUrl.value)
  endorsementPreviewUrl.value = null
  if (!selectedRequirementDocuments.value.includes('Endorsement')) {
    selectedRequirementDocuments.value = [...selectedRequirementDocuments.value, 'Endorsement']
  }
}

function handleEndorsementFile(file: File | null) {
  endorsementFile.value = file
  if (endorsementPreviewUrl.value) URL.revokeObjectURL(endorsementPreviewUrl.value)
  endorsementPreviewUrl.value = null
  if (!file) {
    selectedRequirementDocuments.value = selectedRequirementDocuments.value.filter((item) => item !== 'Endorsement')
    return
  }
  selectedSavedEndorsementId.value = ''
  if (file.type.startsWith('image/')) {
    endorsementPreviewUrl.value = URL.createObjectURL(file)
  }
  if (!selectedRequirementDocuments.value.includes('Endorsement')) {
    selectedRequirementDocuments.value = [...selectedRequirementDocuments.value, 'Endorsement']
  }
}

function handleEndorsementFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  handleEndorsementFile(input.files?.[0] ?? null)
  input.value = ''
}

function onEndorsementDrop(event: DragEvent) {
  event.preventDefault()
  endorsementDragOver.value = false
  handleEndorsementFile(event.dataTransfer?.files?.[0] ?? null)
}

function clearEndorsementSelection() {
  handleEndorsementFile(null)
  selectedSavedEndorsementId.value = ''
}

function handleSupportingFilesChange(event: Event) {
  const input = event.target as HTMLInputElement
  supportingFiles.value = Array.from(input.files ?? [])
}

function handleSavedDocumentFilesChange(event: Event) {
  const input = event.target as HTMLInputElement
  documentUploadFiles.value = Array.from(input.files ?? [])
}

async function loadSavedDocuments() {
  documentsLoading.value = true
  try {
    savedDocuments.value = await listDocuments()
  } catch {
    savedDocuments.value = []
  } finally {
    documentsLoading.value = false
  }
}

async function uploadSavedDocumentFiles() {
  if (!documentUploadFiles.value.length) return

  uploadingSavedDocuments.value = true
  try {
    await uploadDocuments({
      files: documentUploadFiles.value,
      category: documentUploadCategory.value || 'general',
    })
    documentUploadFiles.value = []
    await loadSavedDocuments()
    success('Documents uploaded.', {
      description: 'Your saved documents are ready to use for applications.',
    })
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to upload your documents.',
    })
  } finally {
    uploadingSavedDocuments.value = false
  }
}

async function saveProfile() {
  savingProfile.value = true
  try {
    // School-managed identity/enrollment fields are read-only; only contact may be updated.
    const updatedProfile = await updateCurrentUserProfile({
      profile: {
        contactNumber: profileForm.value.contactNumber,
      },
      profileSetupComplete: true,
    })
    authStore.setUserProfile(updatedProfile)
    profileDialogOpen.value = false
    success('Profile updated.', {
      description: 'Your contact information has been saved.',
    })
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to save your profile.',
    })
  } finally {
    savingProfile.value = false
  }
}

async function requestOpportunity(opportunityId: string) {
  const opportunity = internships.value.find((item) => String(item.id) === String(opportunityId))
  if (!opportunity || !authStore.user?.uid) return

  await refreshActivePlacement()
  if (hasActivePlacement.value) {
    error(placementBlockMessage.value || 'You already have an active placement.')
    return
  }

  if (appliedInternshipIds.value.has(String(opportunity.id))) {
    error('You already have an application for this opportunity.', {
      fallback: 'You already requested this opportunity.',
    })
    return
  }

  applyingInternshipId.value = String(opportunity.id)
  try {
    let resumeValue = applicationResume.value.trim() || null
    let endorsementUrl =
      approvedEndorsementDocuments.value.find((document) => document.id === selectedSavedEndorsementId.value)?.fileUrl ||
      null

    if (endorsementFile.value) {
      uploadingEndorsement.value = true
      endorsementUploadProgress.value = 20
      const tick = window.setInterval(() => {
        endorsementUploadProgress.value = Math.min(90, endorsementUploadProgress.value + 15)
      }, 160)
      try {
        const [uploadedEndorsement] = await uploadDocuments({
          files: [endorsementFile.value],
          category: 'application-endorsement',
        })
        endorsementUrl = uploadedEndorsement?.fileUrl || endorsementUrl
        endorsementUploadProgress.value = 100
      } finally {
        window.clearInterval(tick)
        uploadingEndorsement.value = false
      }
    }

    if (!endorsementUrl) {
      error('An endorsement letter is required before you can submit.')
      return
    }

    if (resumeFile.value) {
      const [uploadedResume] = await uploadDocuments({
        files: [resumeFile.value],
        category: 'application-resume',
      })
      resumeValue = uploadedResume?.fileUrl || resumeValue
    }

    const documentValues = [endorsementUrl]
    selectedDocuments.value = documentValues

    await submitApplication({
      studentId: authStore.user.uid,
      internshipId: String(opportunity.id),
      companyId: opportunity.companyId || '',
      companyName: opportunity.companyName || opportunity.hostName || '',
      schoolName: String(currentProfile.value?.schoolName || ''),
      internshipTitle: opportunity.title || '',
      studentName: authStore.user.displayName || authStore.user.email || 'Student',
      studentEmail: authStore.user.email || '',
      studentCourse: currentCourse.value,
      status: 'submitted',
      resume: resumeValue,
      endorsementUrl,
      documents: documentValues,
      documentsPending: false,
    })

    success(opportunity.hostType === 'school' ? 'Endorsement request submitted.' : 'Application submitted.', {
      description:
        opportunity.hostType === 'school'
          ? 'Your school can now review and process this placement request.'
          : 'Your application is now in the review queue.',
    })

    applications.value = [
      {
        id: `local-${opportunity.id}`,
        internshipId: String(opportunity.id),
        studentId: authStore.user.uid,
        companyId: opportunity.companyId || '',
        companyName: opportunity.companyName || opportunity.hostName || '',
        schoolName: String(currentProfile.value?.schoolName || ''),
        internshipTitle: opportunity.title || '',
        studentName: authStore.user.displayName || authStore.user.email || 'Student',
        studentEmail: authStore.user.email || '',
        studentCourse: currentCourse.value,
        status: 'submitted',
        resume: resumeValue,
        documents: documentValues,
        documentsPending: false,
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
      },
      ...applications.value,
    ]
    applicationDialogOpen.value = false
    selectedOpportunity.value = null
    applicationResume.value = ''
    selectedDocuments.value = []
    selectedRequirementDocuments.value = []
    selectedSavedDocumentIds.value = []
    selectedSavedResumeId.value = ''
    resumeFile.value = null
    supportingFiles.value = []
    await loadSavedDocuments()
  } catch (caughtError) {
    error(caughtError, {
      fallback: opportunity.hostType === 'school' ? 'Unable to submit endorsement request.' : 'Unable to submit application.',
    })
  } finally {
    applyingInternshipId.value = null
  }
}

function handleMenuClick(menuItem: string) {
  const allowedViews: InternView[] = [
    'settings',
    'opportunities',
    'openings',
    'saved',
    'documents',
    'internship',
    'placement',
    'dashboard',
  ]
  const nextView = allowedViews.includes(menuItem as InternView) ? (menuItem as InternView) : 'settings'
  activeView.value = nextView
  if (nextView === 'saved') void loadSavedInternshipsList()
  if (nextView === 'internship') void loadApplicationExtras()
  const targetName =
    nextView === 'opportunities'
      ? 'intern-opportunities'
      : nextView === 'openings'
        ? 'intern-openings'
        : nextView === 'saved'
          ? 'intern-saved'
          : nextView === 'documents'
            ? 'intern-documents'
            : nextView === 'internship'
              ? 'intern-applications'
              : nextView === 'placement'
                ? 'intern-placement'
                : nextView === 'dashboard'
                  ? 'intern-tracker'
                  : 'intern'
  void router.push({ name: targetName })
}

onMounted(() => {
  activeView.value = internViewFromRouteName(route.name)
  hydrateProfileForm()
  void loadProfileSkills()
  startSubscriptions(authStore.user?.uid)
})

onUnmounted(() => {
  stopSubscriptions()
})

watch(
  () => route.name,
  (name) => {
    activeView.value = internViewFromRouteName(name)
    if (activeView.value === 'saved') void loadSavedInternshipsList()
    if (activeView.value === 'internship') void loadApplicationExtras()
    tryFocusOpportunityFromQuery()
  },
)

watch(
  () => authStore.user?.uid,
  (userId) => {
    hydrateProfileForm()
    startSubscriptions(userId)
  },
)

watch(
  [() => route.query.focus, internships, activeView],
  () => {
    tryFocusOpportunityFromQuery()
  },
  { flush: 'post' },
)
</script>

<template>
  <MainLayout role="student" :title="pageTitle" :active-item="activeView" @navigate="handleMenuClick($event.key)">
    <div class="space-y-6">
      <div v-if="activeView === 'settings'" class="space-y-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-3">
            <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl bg-accent text-lg font-semibold text-primary">
              <img v-if="profileAvatarUrl" :src="profileAvatarUrl" alt="Profile avatar" class="h-full w-full object-cover" />
              <span v-else>{{ userInitials }}</span>
            </div>
            <div class="min-w-0">
              <h2 class="truncate text-xl font-semibold text-foreground">{{ authStore.user?.displayName || 'Intern Profile' }}</h2>
              <p class="mt-0.5 truncate text-sm text-muted-foreground">{{ authStore.user?.email }}</p>
            </div>
          </div>
          <Button class="min-h-11 w-full gap-2 whitespace-nowrap sm:w-auto" @click="openProfileDialog">
            <Edit3 class="h-4 w-4" />
            Edit Profile
          </Button>
        </div>

        <section class="rounded-xl border border-border bg-card">
          <h3 class="border-b border-border px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Personal</h3>
          <dl class="divide-y divide-border">
            <div class="flex min-h-11 items-center justify-between gap-3 px-4 py-3">
              <dt class="text-sm text-muted-foreground">Email</dt>
              <dd class="truncate text-sm font-medium text-foreground">{{ authStore.user?.email || 'Not set' }}</dd>
            </div>
            <div class="flex min-h-11 items-center justify-between gap-3 px-4 py-3">
              <dt class="text-sm text-muted-foreground">Contact</dt>
              <dd class="truncate text-sm font-medium text-foreground">{{ currentProfile?.contactNumber || 'Not set' }}</dd>
            </div>
          </dl>
        </section>

        <section class="rounded-xl border border-border bg-card">
          <h3 class="border-b border-border px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">School</h3>
          <dl class="divide-y divide-border">
            <div class="flex min-h-11 items-center justify-between gap-3 px-4 py-3">
              <dt class="text-sm text-muted-foreground">School</dt>
              <dd class="truncate text-right text-sm font-medium text-foreground">{{ currentProfile?.schoolName || 'Not set' }}</dd>
            </div>
            <div class="flex min-h-11 items-center justify-between gap-3 px-4 py-3">
              <dt class="text-sm text-muted-foreground">Intern code</dt>
              <dd class="font-mono text-sm font-semibold text-foreground">{{ currentProfile?.internCode || '—' }}</dd>
            </div>
            <div class="flex min-h-11 items-center justify-between gap-3 px-4 py-3">
              <dt class="text-sm text-muted-foreground">Course</dt>
              <dd class="truncate text-right text-sm font-medium text-foreground">{{ currentProfile?.course || 'Not set' }}</dd>
            </div>
            <div class="flex min-h-11 items-center justify-between gap-3 px-4 py-3">
              <dt class="text-sm text-muted-foreground">Year level</dt>
              <dd class="text-sm font-medium text-foreground">{{ currentProfile?.yearLevel || 'Not set' }}</dd>
            </div>
          </dl>
        </section>

        <section class="rounded-xl border border-border bg-card">
          <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Skills</h3>
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="min-h-9"
              :disabled="savingSkills"
              @click="skillsWizardOpen = true"
            >
              {{ savingSkills ? 'Saving…' : profileSkillChips.length ? 'Edit skills' : 'Add skills' }}
            </Button>
          </div>
          <div class="px-4 py-3">
            <SkillChips
              :skills="profileSkillChips"
              mode="intern"
              empty-text="No skills yet. Add skills so schools and companies can see what you know."
            />
          </div>
        </section>

        <section class="rounded-xl border border-border bg-card">
          <h3 class="border-b border-border px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Documents</h3>
          <div class="px-4 py-3">
            <RouterLink
              :to="{ name: 'intern-documents' }"
              class="inline-flex min-h-11 w-full items-center justify-center rounded-md border border-border px-3 text-sm font-semibold text-foreground hover:bg-accent"
            >
              Open documents
            </RouterLink>
          </div>
        </section>
      </div>

      <div v-else-if="activeView === 'opportunities'" class="sm:mx-0">
        <HostBrowseTabs partner-scoped @update:title="browsePageTitle = $event" />
      </div>

      <div v-else-if="activeView === 'openings'" class="sm:mx-0">
        <FindInternships embedded />
      </div>

      <div v-else-if="activeView === 'documents'" class="space-y-6">
        <div>
          <h2 class="text-xl font-semibold text-foreground md:text-2xl">Documents</h2>
          <p class="text-sm text-muted-foreground">Upload your resume and supporting files once, then reuse them for applications.</p>
        </div>
        <div class="space-y-6">
          <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-border bg-muted/60 p-4">
              <p class="text-sm font-medium text-foreground">Saved files</p>
              <p class="mt-3 text-3xl font-semibold text-foreground">{{ savedDocuments.length }}</p>
              <p class="mt-2 text-sm text-muted-foreground">Documents ready to reuse in future applications.</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/40">
              <p class="text-sm font-medium text-emerald-900 dark:text-emerald-200">Resume ready</p>
              <p class="mt-3 text-3xl font-semibold text-foreground">
                {{ savedDocuments.some((document) => ['application-resume', 'internship-application'].includes(String(document.category || '').toLowerCase())) ? 'Yes' : 'No' }}
              </p>
              <p class="mt-2 text-sm text-muted-foreground">A saved resume can auto-fill your next application.</p>
            </div>
            <div class="rounded-2xl border border-violet-200 bg-violet-50 p-4 dark:border-violet-800 dark:bg-violet-950/40">
              <p class="text-sm font-medium text-violet-900 dark:text-violet-200">Pending review</p>
              <p class="mt-3 text-3xl font-semibold text-foreground">
                {{ savedDocuments.filter((document) => document.status === 'pending').length }}
              </p>
              <p class="mt-2 text-sm text-muted-foreground">Files that still need to be reviewed or checked.</p>
            </div>
          </div>

          <div class="overflow-hidden rounded-3xl border border-border bg-card">
            <div class="grid gap-0 lg:grid-cols-[1.2fr_0.8fr]">
              <div class="space-y-4 bg-muted/40 p-6 dark:bg-muted/20">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">Document Hub</p>
                  <h3 class="mt-2 text-2xl font-semibold text-foreground">Keep your application files ready</h3>
                  <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Upload your resume, internship forms, and supporting requirements once so every application starts faster.
                  </p>
                </div>

                <FormItem>
                  <FormLabel for="savedDocumentFiles">Import Files</FormLabel>
                  <FormControl>
                    <input
                      id="savedDocumentFiles"
                      type="file"
                      multiple
                      accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                      class="block w-full rounded-2xl border border-border bg-background px-4 py-4 text-sm text-foreground shadow-sm file:mr-4 file:rounded-xl file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                      @change="handleSavedDocumentFilesChange"
                    />
                  </FormControl>
                </FormItem>

                <div class="flex flex-wrap items-end gap-4">
                  <FormItem class="min-w-[220px] flex-1">
                    <FormLabel for="savedDocumentCategory">Category</FormLabel>
                    <FormControl>
                      <Select id="savedDocumentCategory" v-model="documentUploadCategory">
                        <option value="general">General</option>
                        <option value="application-resume">Application Resume</option>
                        <option value="internship-application">Internship Application</option>
                        <option value="supporting-document">Supporting Document</option>
                      </Select>
                    </FormControl>
                  </FormItem>
                  <Button
                    :disabled="uploadingSavedDocuments || !documentUploadFiles.length"
                    class="min-h-11 min-w-[160px] whitespace-nowrap"
                    @click="uploadSavedDocumentFiles"
                  >
                    <LoaderCircle v-if="uploadingSavedDocuments" class="h-4 w-4 animate-spin" />
                    <span>{{ uploadingSavedDocuments ? 'Uploading...' : 'Upload Documents' }}</span>
                  </Button>
                </div>
              </div>

              <div class="border-t border-slate-200 bg-slate-50 px-6 py-6 text-slate-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 lg:border-l lg:border-t-0">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Quick Guide</p>
                <div class="mt-4 space-y-4">
                  <div class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-700 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-200">
                    <p class="font-medium">1. Upload your resume</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Use `Application Resume` so the system can find it first when you apply.</p>
                  </div>
                  <div class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-700 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-200">
                    <p class="font-medium">2. Add school requirements</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Store endorsement forms, portfolio files, and supporting documents here.</p>
                  </div>
                  <div class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-700 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-200">
                    <p class="font-medium">3. Apply faster</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Saved files are reused during internship applications whenever possible.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-xl font-semibold text-foreground">Saved Files</h3>
                <p class="text-sm text-muted-foreground">Open or review the files you already uploaded for internships.</p>
              </div>
            </div>

            <div v-if="documentsLoading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <div v-for="index in 6" :key="index" class="rounded-2xl border border-border bg-card p-5">
                <Skeleton class="h-5 w-40" />
                <Skeleton class="mt-4 h-4 w-28" />
                <Skeleton class="mt-6 h-9 w-24" />
              </div>
            </div>

            <div v-else-if="savedDocuments.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <div
                v-for="document in savedDocuments"
                :key="document.id"
                class="rounded-2xl border border-border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-accent text-primary">
                    <FileText class="h-5 w-5" />
                  </div>
                  <Badge :variant="document.status === 'approved' ? 'success' : document.status === 'rejected' ? 'destructive' : 'warning'">
                    {{ badgeLabel(document.status) }}
                  </Badge>
                </div>
                <div class="mt-4 space-y-2">
                  <a :href="document.fileUrl" target="_blank" rel="noopener" class="block text-base font-semibold text-foreground hover:text-primary">
                    {{ document.fileName }}
                  </a>
                  <p class="text-sm text-muted-foreground">{{ document.fileType || 'File' }}</p>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                  <span class="inline-flex rounded-full bg-muted px-3 py-1 text-xs font-medium text-foreground">
                    {{ document.category }}
                  </span>
                  <span class="inline-flex rounded-full bg-accent px-3 py-1 text-xs font-medium text-primary">
                    {{ formatDate(document.createdAt) }}
                  </span>
                </div>
                <div class="mt-5">
                  <a
                    :href="document.fileUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center text-sm font-medium text-primary hover:text-primary"
                  >
                    Open file
                  </a>
                </div>
              </div>
            </div>

            <div v-else class="rounded-3xl border border-dashed border-border bg-accent/70 px-6 py-12 text-center">
              <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-card text-primary shadow-sm">
                <FileText class="h-6 w-6" />
              </div>
              <h3 class="mt-4 text-lg font-semibold text-foreground">No saved documents yet</h3>
              <p class="mt-2 text-sm text-muted-foreground">
                Upload your resume and internship requirements here so future applications are much easier.
              </p>
            </div>
          </div>

          <div class="rounded-2xl border border-border bg-muted p-4 text-sm text-foreground">
            <div class="flex items-center gap-2 font-medium text-foreground">
              <FileText class="h-4 w-4" />
              Application reuse
            </div>
            <p class="mt-2">
              When you apply for an opportunity, the system now checks your saved documents first and pre-fills your resume and supporting files when available.
            </p>
          </div>
        </div>
      </div>

      <div v-else-if="activeView === 'saved'" class="space-y-4">
        <div>
          <h2 class="text-xl font-semibold text-foreground md:text-2xl">Saved</h2>
          <p class="text-sm text-muted-foreground">Your wishlist of openings to revisit later.</p>
        </div>
        <div>
          <div v-if="savedInternshipsLoading" class="grid gap-4 sm:grid-cols-2">
            <Skeleton v-for="index in 4" :key="`saved-skel-${index}`" class="h-40 w-full rounded-xl" />
          </div>
          <div v-else-if="savedInternships.length" class="grid gap-4 sm:grid-cols-2">
            <article
              v-for="item in savedInternships"
              :key="item.id"
              class="overflow-hidden rounded-xl border border-border bg-card"
            >
              <div class="aspect-[16/10] bg-muted">
                <img
                  v-if="item.coverImage"
                  :src="item.coverImage"
                  :alt="`${item.companyName || item.hostName || 'Company'} logo`"
                  class="h-full w-full object-contain p-4"
                  loading="lazy"
                />
                <div v-else class="flex h-full items-center justify-center text-muted-foreground">
                  <Building2 class="h-7 w-7 opacity-70" />
                </div>
              </div>
              <div class="space-y-3 p-4">
                <div>
                  <p class="text-sm text-muted-foreground">{{ item.companyName || item.hostName || 'Host organization' }}</p>
                  <h3 class="font-semibold text-foreground">{{ item.title }}</h3>
                </div>
                <ApplicationCapacity
                  :slots-available="item.slotsAvailable"
                  :slots-remaining="item.slotsRemaining"
                  :filled-slots="item.filledSlots"
                  compact
                />
                <div class="flex flex-wrap gap-2">
                  <Button
                    size="sm"
                    :disabled="!isPartneredForSaved(item) || hasActivePlacement"
                    :title="hasActivePlacement ? placementBlockMessage : undefined"
                    @click="applyFromSaved(item)"
                  >
                    {{
                      hasActivePlacement
                        ? 'Already placed'
                        : isPartneredForSaved(item)
                          ? 'Apply'
                          : 'Not yet available'
                    }}
                  </Button>
                  <RouterLink
                    :to="{ name: 'internship-detail', params: { id: item.id } }"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-border bg-background px-3 text-sm font-medium text-foreground hover:bg-accent"
                  >
                    Details
                  </RouterLink>
                </div>
              </div>
            </article>
          </div>
          <div v-else class="rounded-xl border border-dashed border-border px-6 py-14 text-center">
            <p class="text-sm text-muted-foreground">No saved internships yet. Tap the heart on a listing to save it here.</p>
            <RouterLink
              to="/intern/openings"
              class="mt-4 inline-flex min-h-11 w-full items-center justify-center whitespace-nowrap rounded-md border border-border bg-background px-4 text-sm font-semibold text-foreground hover:bg-accent sm:w-auto"
            >
              Browse internships
            </RouterLink>
          </div>
        </div>
      </div>

      <div v-else-if="activeView === 'internship'" class="space-y-4">
        <div>
          <h2 class="text-xl font-semibold text-foreground md:text-2xl">Applications</h2>
          <p class="text-sm text-muted-foreground">Track each application from submission through OJT.</p>
        </div>
        <div v-if="applicationsLoading || applicationExtrasLoading" class="space-y-4">
          <Skeleton v-for="index in 3" :key="`app-skel-${index}`" class="h-48 w-full rounded-xl" />
        </div>
        <template v-else-if="applicationCards.length">
          <article
            v-for="card in applicationCards"
            :key="card.application.id"
            class="overflow-hidden rounded-xl border border-border bg-card"
          >
            <div class="grid gap-0 md:grid-cols-[180px_minmax(0,1fr)]">
              <div class="hidden bg-muted md:block md:min-h-full">
                <img
                  v-if="card.coverImage"
                  :src="card.coverImage"
                  :alt="`${card.application.companyName || 'Company'} logo`"
                  class="h-full w-full object-contain p-4"
                  loading="lazy"
                />
                <div v-else class="flex h-full min-h-36 items-center justify-center text-muted-foreground">
                  <Building2 class="h-7 w-7 opacity-70" />
                </div>
              </div>
              <div class="space-y-4 p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm text-muted-foreground">{{ card.application.companyName || 'Host organization' }}</p>
                    <h3 class="text-lg font-semibold leading-snug text-foreground">
                      {{ card.application.internshipTitle || 'Untitled position' }}
                    </h3>
                    <p class="mt-1 text-xs text-muted-foreground">Applied {{ formatDate(card.application.createdAt) }}</p>
                  </div>
                  <Badge :variant="badgeVariant(card.application.trackerStage || card.application.status)">{{ applicationProgressLabel(card.application) }}</Badge>
                </div>
                <ApplicationTrackerPanel
                  :application-id="String(card.application.id)"
                  mode="student"
                  @updated="loadApplicationExtras"
                />
                <RouterLink
                  v-if="card.application.internshipId"
                  :to="{ name: 'internship-detail', params: { id: card.application.internshipId } }"
                  class="inline-flex h-11 w-full items-center justify-center whitespace-nowrap rounded-md border border-border bg-background px-3 text-sm font-semibold text-foreground hover:bg-accent sm:w-auto"
                >
                  View details
                </RouterLink>
              </div>
            </div>
          </article>
        </template>
        <div v-else class="rounded-xl border border-dashed border-border px-6 py-14 text-center text-sm text-muted-foreground">
          No applications yet. Start exploring internship opportunities to populate this list.
        </div>
      </div>

      <div v-else-if="activeView === 'placement'" class="space-y-4">
        <div>
          <h2 class="text-xl font-semibold text-foreground md:text-2xl">Placement</h2>
          <p class="text-sm text-muted-foreground">See your accepted placement and the latest assignment details shared by your school or host.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl bg-muted p-4">
            <p class="text-sm font-medium text-muted-foreground">Host</p>
            <p class="mt-3 text-sm font-medium text-foreground">{{ placementSummary.host }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <p class="text-sm font-medium text-muted-foreground">Role</p>
            <p class="mt-3 text-sm font-medium text-foreground">{{ placementSummary.role }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <p class="text-sm font-medium text-muted-foreground">Status</p>
            <p class="mt-3 text-sm font-medium text-foreground">{{ placementSummary.status }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <p class="text-sm font-medium text-muted-foreground">Latest Update</p>
            <p class="mt-3 text-sm font-medium text-foreground">{{ placementSummary.updatedAt }}</p>
          </div>

          <div
            v-if="hasActivePlacement"
            class="rounded-2xl border border-border bg-accent p-4 sm:col-span-2 xl:col-span-4"
          >
            <p class="text-sm font-medium text-foreground">Active placement</p>
            <p class="mt-2 text-sm leading-6 text-foreground">
              {{ placementBlockMessage }}
              You cannot apply elsewhere until this placement is cancelled, declined, or ended.
            </p>
            <DropdownMenu>
              <template #trigger>
                <Button class="mt-3 min-h-11" variant="outline">More actions</Button>
              </template>
              <template #default="{ close }">
                <DropdownMenuItem
                  class="text-destructive hover:text-destructive"
                  @click="declineConfirmOpen = true; close()"
                >
                  Decline this placement
                </DropdownMenuItem>
              </template>
            </DropdownMenu>
          </div>

          <div class="rounded-2xl border border-border bg-accent p-4 sm:col-span-2 xl:col-span-4">
            <p class="text-sm font-medium text-foreground">Placement notes</p>
            <p class="mt-2 text-sm leading-6 text-foreground">
              This area is ready for supervisor details, required internship hours, school endorsement status, and school-based placement records such as BSED practice teaching assignments.
            </p>
          </div>

          <div class="rounded-2xl border border-border p-4 sm:col-span-2 xl:col-span-4">
            <p class="text-sm font-medium text-foreground">Daily Time Record (DTR)</p>
            <Badge variant="warning" class="mt-2">Draft</Badge>
            <p class="mt-1 text-sm text-muted-foreground">
              The form stays a draft until you upload. After upload the status is Submitted, awaiting company review.
            </p>
            <div v-if="dtrPlacements.length" class="mt-4 grid gap-3 md:grid-cols-2">
              <label class="space-y-1 text-sm">
                <span class="font-medium text-foreground">Accepted placement</span>
                <Select v-model="dtrApplicationId" :disabled="Boolean(dtrResubmitTarget)">
                  <option value="">Select placement</option>
                  <option
                    v-for="placement in dtrPlacements"
                    :key="placement.applicationId"
                    :value="String(placement.applicationId)"
                  >
                    {{ placement.internshipTitle }}{{ placement.companyName ? ` — ${placement.companyName}` : '' }}
                  </option>
                </Select>
              </label>
              <label class="space-y-1 text-sm">
                <span class="font-medium text-foreground">Hours</span>
                <Input v-model="dtrHours" type="number" min="0.25" max="500" step="0.25" />
              </label>
              <label class="space-y-1 text-sm">
                <span class="font-medium text-foreground">Period start</span>
                <Input v-model="dtrPeriodStart" type="date" />
              </label>
              <label class="space-y-1 text-sm">
                <span class="font-medium text-foreground">Period end</span>
                <Input v-model="dtrPeriodEnd" type="date" />
              </label>
              <label class="space-y-1 text-sm md:col-span-2">
                <span class="font-medium text-foreground">DTR file (PDF, JPG, PNG)</span>
                <input
                  class="flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-[16px] md:text-sm"
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                  :disabled="dtrReadingFile"
                  @change="onDtrFileChange"
                />
                <p v-if="dtrReadingFile" class="text-sm text-muted-foreground">Reading the file…</p>
                <p v-else-if="dtrFileMessage" class="text-sm text-muted-foreground">{{ dtrFileMessage }}</p>
              </label>
              <label class="space-y-1 text-sm md:col-span-2">
                <span class="font-medium text-foreground">Remarks</span>
                <Input v-model="dtrRemarks" placeholder="Optional note" />
              </label>
              <div class="flex gap-2 md:col-span-2">
                <Button :disabled="dtrSubmitting || dtrReadingFile" @click="submitStudentDtr">
                  <LoaderCircle v-if="dtrSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                  {{ dtrResubmitTarget ? 'Resubmit DTR' : 'Upload DTR' }}
                </Button>
                <Button v-if="dtrResubmitTarget" variant="outline" @click="resetDtrForm">Cancel</Button>
              </div>
            </div>
            <p v-else class="mt-3 text-sm text-muted-foreground">
              DTR upload becomes available after you have an accepted placement.
            </p>
            <ul v-if="allDtrs.length" class="mt-4 space-y-2">
              <li
                v-for="row in allDtrs"
                :key="row.id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border bg-muted/40 px-3 py-2 text-sm"
              >
                <div>
                  <p class="font-medium text-foreground">{{ row.internshipTitle || 'Internship' }}</p>
                  <p class="text-xs text-muted-foreground">
                    {{ row.periodStart }} – {{ row.periodEnd }} · {{ row.submittedHours }} hrs ·
                    {{ dtrStatusLabel(dtrRowStatus(row)) }}
                    <template v-if="dtrStatusDetail(row)"> · {{ dtrStatusDetail(row) }}</template>
                  </p>
                </div>
                <div class="flex gap-2">
                  <Button
                    v-if="isPendingDtr(row.status)"
                    size="sm"
                    variant="outline"
                    @click="askDeleteDtr(row)"
                  >
                    Delete
                  </Button>
                  <Button
                    v-if="isRejectedOrRevision(row.status)"
                    size="sm"
                    variant="outline"
                    @click="startDtrResubmit(row)"
                  >
                    Correct and resubmit
                  </Button>
                </div>
              </li>
            </ul>
          </div>

          <div class="rounded-2xl border border-border p-4 sm:col-span-2 xl:col-span-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="text-sm font-medium text-foreground">Certificate of completion</p>
                <p class="mt-1 text-sm text-muted-foreground">
                  When your school issues a certificate for a completed placement, open the HTML certificate here. Use Print / Save as PDF in your browser to keep a copy.
                </p>
              </div>
              <Badge v-if="placementCertificates.length" variant="secondary">
                {{ placementCertificates.length }} issued
              </Badge>
            </div>

            <div v-if="certificatesLoading" class="mt-4 space-y-2">
              <Skeleton class="h-16 w-full rounded-xl" />
            </div>
            <div
              v-else-if="certificatesError"
              class="mt-4 rounded-xl border border-destructive/40 bg-destructive/5 px-4 py-6 text-sm text-destructive"
              role="alert"
            >
              {{ certificatesError }}
            </div>
            <div
              v-else-if="!placementCertificates.length"
              class="mt-4 rounded-xl border border-dashed border-border px-4 py-6 text-sm text-muted-foreground"
            >
              No certificate has been issued for your placements yet.
            </div>
            <ul v-else class="mt-4 space-y-3">
              <li
                v-for="certificate in placementCertificates"
                :key="certificate.id"
                class="flex flex-col gap-3 rounded-xl border border-border bg-muted/40 p-4 sm:flex-row sm:items-center sm:justify-between"
              >
                <div class="min-w-0 space-y-1">
                  <p class="text-sm font-medium text-foreground">
                    {{ certificate.internshipTitle || placementSummary.role }}
                  </p>
                  <p class="text-xs text-muted-foreground">
                    {{ certificate.certificateNumber }}
                    <span v-if="certificate.issuedAt"> · Issued {{ formatDate(certificate.issuedAt) }}</span>
                  </p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="certificateActionId === certificate.id"
                    @click="openStudentCertificate(certificate.id)"
                  >
                    <LoaderCircle v-if="certificateActionId === certificate.id" class="mr-2 h-4 w-4 animate-spin" />
                    View Certificate
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    :disabled="certificateActionId === certificate.id"
                    @click="printStudentCertificate(certificate.id)"
                  >
                    Print Certificate
                  </Button>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div v-else class="space-y-4">
        <div>
          <h2 class="text-xl font-semibold text-foreground md:text-2xl">Interviews</h2>
          <p class="text-sm text-muted-foreground">Follow each milestone from application through the final decision.</p>
        </div>
        <div v-if="latestApplication" class="text-sm font-medium text-foreground">
          {{ latestApplication.internshipTitle || 'Application' }}
          <span v-if="latestApplication.companyName" class="font-normal text-muted-foreground">
            · {{ latestApplication.companyName }}
          </span>
        </div>
        <p v-else class="rounded-2xl border border-dashed border-border p-6 text-sm text-muted-foreground">
          Submit an internship application to start tracking your progress.
        </p>

        <div class="rounded-2xl border border-border bg-accent p-4 text-sm text-foreground">
          <div class="flex items-center gap-2 font-medium">
            <CalendarDays class="h-4 w-4" />
            Latest update
          </div>
          <p class="mt-2">
            {{ latestApplication ? `Last activity recorded on ${formatDate(latestApplication.updatedAt || latestApplication.createdAt)}.` : 'No application timeline yet. Once you apply, your stepper will update automatically.' }}
          </p>
        </div>

        <div v-if="latestApplication?.hoursMismatch" class="rounded-2xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm">
          <p class="font-semibold text-foreground">Hours differ from your school requirement</p>
          <p class="mt-1 text-muted-foreground">
            School {{ latestApplication.schoolRequiredHours ?? '—' }}h · Host {{ latestApplication.hostRequiredHours ?? '—' }}h
          </p>
        </div>
        <HoursAgreementPanel
          v-if="latestApplication?.id"
          :application-id="latestApplication.id"
          mode="student"
          :hours-mismatch="!!latestApplication.hoursMismatch"
          :school-required-hours="latestApplication.schoolRequiredHours"
          :host-required-hours="latestApplication.hostRequiredHours"
        />
        <ApplicationTrackerPanel
          v-if="latestApplication?.id"
          :application-id="latestApplication.id"
          mode="student"
        />
        <PlacementSchedulePanel
          v-if="latestApplication?.id"
          :application-id="latestApplication.id"
          mode="student"
          :accepted="String(latestApplication.status || '').toLowerCase() === 'accepted'"
        />
        <OjtCalendarPanel
          v-if="latestApplication?.id && String(latestApplication.status || '').toLowerCase() === 'accepted'"
          :application-id="latestApplication.id"
          mode="student"
        />
      </div>
    </div>

    <Dialog v-model:open="profileDialogOpen">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>Edit Profile</DialogTitle>
          <p class="text-sm text-muted-foreground">
            School-managed details are read-only. You can update your contact number here; change your password in Settings.
          </p>
        </DialogHeader>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
          <FormItem class="sm:col-span-2">
            <FormLabel for="profileName">Full Name</FormLabel>
            <FormControl>
              <Input id="profileName" v-model="profileForm.displayName" readonly disabled />
            </FormControl>
          </FormItem>
          <FormItem>
            <FormLabel for="profileEmail">Email</FormLabel>
            <FormControl>
              <Input id="profileEmail" v-model="profileForm.email" readonly disabled />
            </FormControl>
          </FormItem>
          <FormItem>
            <FormLabel for="profileContact">Contact Number</FormLabel>
            <FormControl>
              <Input id="profileContact" v-model="profileForm.contactNumber" placeholder="Enter your contact number" />
            </FormControl>
          </FormItem>
          <FormItem>
            <FormLabel for="profileSchool">School Name</FormLabel>
            <FormControl>
              <Input id="profileSchool" v-model="profileForm.schoolName" readonly disabled />
            </FormControl>
          </FormItem>
          <FormItem>
            <FormLabel for="profileCourse">Course</FormLabel>
            <FormControl>
              <Input id="profileCourse" v-model="profileForm.course" readonly disabled />
            </FormControl>
          </FormItem>
          <FormItem class="sm:col-span-2">
            <FormLabel for="profileYearLevel">Year Level</FormLabel>
            <FormControl>
              <Select id="profileYearLevel" v-model="profileForm.yearLevel" disabled>
                <option value="">Select year level</option>
                <option value="3rd Year">3rd Year</option>
                <option value="4th Year">4th Year</option>
              </Select>
            </FormControl>
          </FormItem>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <Button variant="outline" @click="close">Cancel</Button>
          <Button :disabled="savingProfile" @click="saveProfile">
            <LoaderCircle v-if="savingProfile" class="h-4 w-4 animate-spin" />
            <span>{{ savingProfile ? 'Saving...' : 'Save Changes' }}</span>
          </Button>
        </div>
      </template>
    </Dialog>

    <Dialog v-model:open="applicationDialogOpen">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>Apply with endorsement</DialogTitle>
        </DialogHeader>

        <div v-if="selectedOpportunity" class="mt-6 space-y-6">
          <div class="rounded-2xl border border-border bg-muted px-4 py-3 text-sm text-foreground">
            <p class="font-semibold">Required document: Endorsement</p>
            <p class="mt-1 text-muted-foreground">
              Accepted: PDF, JPG, PNG. Max 10 MB. Drag and drop or tap to upload (camera or file picker on mobile).
              Your profile resume is optional information for the host — it is not required here.
            </p>
          </div>

          <div
            class="rounded-2xl border border-border bg-card p-4"
            :class="endorsementDragOver ? 'border-primary bg-primary/5' : ''"
            @dragover.prevent="endorsementDragOver = true"
            @dragleave="endorsementDragOver = false"
            @drop="onEndorsementDrop"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p class="text-sm font-semibold text-foreground">Endorsement letter</p>
                <p class="text-xs text-muted-foreground">
                  Status:
                  {{ endorsementChecklistStatus === 'uploaded' ? 'Uploaded' : 'Missing' }}
                </p>
              </div>
              <Badge :variant="endorsementChecklistStatus === 'uploaded' ? 'success' : 'outline'">
                {{ endorsementChecklistStatus === 'uploaded' ? 'Uploaded' : 'Missing' }}
              </Badge>
            </div>

            <div v-if="approvedEndorsementDocuments.length" class="mt-4 space-y-2">
              <p class="text-xs font-medium text-muted-foreground">Reuse a saved endorsement</p>
              <button
                v-for="document in approvedEndorsementDocuments"
                :key="document.id"
                type="button"
                class="flex w-full items-center justify-between rounded-xl border border-border px-3 py-2 text-left text-sm"
                :class="selectedSavedEndorsementId === document.id ? 'border-primary bg-accent' : 'bg-background'"
                @click="selectSavedEndorsement(document.id)"
              >
                <span class="truncate text-foreground">{{ document.fileName }}</span>
                <Badge variant="outline">{{ selectedSavedEndorsementId === document.id ? 'Selected' : 'Reuse' }}</Badge>
              </button>
            </div>

            <label class="mt-4 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border px-4 py-8 text-sm text-muted-foreground">
              <span>Drop endorsement here or tap to upload</span>
              <input
                class="sr-only"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf"
                capture="environment"
                @change="handleEndorsementFileChange"
              />
            </label>

            <div v-if="endorsementFile || selectedSavedEndorsementId" class="mt-3 flex flex-wrap items-center gap-2 text-sm">
              <p class="text-foreground">
                {{
                  endorsementFile?.name ||
                  approvedEndorsementDocuments.find((d) => d.id === selectedSavedEndorsementId)?.fileName ||
                  'Endorsement selected'
                }}
              </p>
              <Button size="sm" variant="ghost" type="button" @click="clearEndorsementSelection">Remove</Button>
            </div>
            <img
              v-if="endorsementPreviewUrl"
              :src="endorsementPreviewUrl"
              alt="Endorsement preview"
              class="mt-3 max-h-40 rounded-md object-contain"
            />
            <div v-if="uploadingEndorsement" class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted">
              <div class="h-full bg-primary transition-all" :style="{ width: `${endorsementUploadProgress}%` }" />
            </div>
          </div>

          <div class="rounded-2xl border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
            <p class="font-medium text-foreground">Optional profile resume</p>
            <p class="mt-1">
              {{
                applicationResume || approvedResumeDocuments.length
                  ? 'A resume from your profile can be shown to the host. It is not required to submit.'
                  : 'No profile resume on file. You can still submit with only the endorsement.'
              }}
            </p>
          </div>

          <div class="flex justify-end gap-3">
            <Button variant="outline" @click="close">Cancel / save progress</Button>
            <Button
              :disabled="!applicationRequirementsComplete || !selectedOpportunity || applyingInternshipId === selectedOpportunity.internshipId"
              @click="selectedOpportunity && requestOpportunity(selectedOpportunity.internshipId)"
            >
              <LoaderCircle
                v-if="selectedOpportunity && applyingInternshipId === selectedOpportunity.internshipId"
                class="h-4 w-4 animate-spin"
              />
              <span>Submit Application</span>
            </Button>
          </div>
        </div>
      </template>
    </Dialog>

    <AlertDialog
      :open="dtrDeleteTarget !== null"
      title="Delete this DTR?"
      :description="dtrDeleteTarget ? `${dtrDeleteTarget.periodStart} to ${dtrDeleteTarget.periodEnd}, ${dtrDeleteTarget.submittedHours} hours. This cannot be undone.` : ''"
      action-label="Delete"
      action-variant="destructive"
      @update:open="(open: boolean) => { if (!open) dtrDeleteTarget = null }"
      @action="confirmDeleteDtr"
    />

    <AlertDialog
      :open="declineConfirmOpen"
      title="Decline this placement?"
      description="You can apply to other openings again. Earlier withdrawn applications stay withdrawn."
      action-label="Decline placement"
      action-variant="destructive"
      @update:open="declineConfirmOpen = $event"
      @action="declineActivePlacement(); declineConfirmOpen = false"
    />

    <SkillsWizard
      v-model="profileSkills"
      v-model:open="skillsWizardOpen"
      mode="intern"
      :limit="15"
      @done="onProfileSkillsSaved"
    />
  </MainLayout>
</template>
