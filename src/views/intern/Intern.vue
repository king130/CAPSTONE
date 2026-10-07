<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  BriefcaseBusiness,
  Building2,
  CalendarDays,
  CheckCircle2,
  CircleDashed,
  Edit3,
  FileText,
  GraduationCap,
  LoaderCircle,
  Mail,
  MapPin,
  Search,
  UserCircle2,
  XCircle,
} from 'lucide-vue-next'

import ApplicationStatusStepper from '@/components/applications/ApplicationStatusStepper.vue'
import ApplicationInterviewPanel from '@/components/ApplicationInterviewPanel.vue'
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
import Select from '@/components/ui/select/Select.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Table from '@/components/ui/table/Table.vue'
import TableBody from '@/components/ui/table/TableBody.vue'
import TableCell from '@/components/ui/table/TableCell.vue'
import TableHead from '@/components/ui/table/TableHead.vue'
import TableHeader from '@/components/ui/table/TableHeader.vue'
import TableRow from '@/components/ui/table/TableRow.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import { submitApplication, subscribeApplications, type ApplicationRecord } from '@/services/applications'
import { listDocuments, uploadDocuments, type DocumentRecord } from '@/services/documents'
import { buildProfileAvatarUrl } from '@/services/profileMedia'
import { getInternship, subscribeEligibleInternships, type InternshipRecord } from '@/services/internships'
import { listInterviews, type InterviewRecord } from '@/services/interviews'
import { listInternOJTLogs, type OJTLog } from '@/services/ojtService'
import { listSavedInternships } from '@/services/savedInternships'
import { updateCurrentUserProfile } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'
import { deriveApplicationStepper } from '@/utils/applicationStepper'
import {
  matchCourseProgram,
  stableSortByCourseMatchLevel,
  type CourseMatchLevel,
} from '@/utils/courseMatch'

type InternView = 'settings' | 'opportunities' | 'documents' | 'internship' | 'placement' | 'dashboard' | 'saved'

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
}

const authStore = useAuthStore()
const { success, error } = useToast()
const route = useRoute()
const router = useRouter()
const activeView = ref<InternView>('settings')
const applications = ref<ApplicationRecord[]>([])
const internships = ref<InternshipRecord[]>([])
const savedDocuments = ref<DocumentRecord[]>([])
const savedInternships = ref<InternshipRecord[]>([])
const interviewsByApplication = ref<Record<string, InterviewRecord[]>>({})
const ojtLogsByApplication = ref<Record<string, OJTLog[]>>({})
const coverByInternship = ref<Record<string, string | null>>({})
const applicationsLoading = ref(true)
const internshipsLoading = ref(true)
const savedInternshipsLoading = ref(false)
const applicationExtrasLoading = ref(false)
const documentsLoading = ref(true)
const profileDialogOpen = ref(false)
const savingProfile = ref(false)
const applyingInternshipId = ref<string | null>(null)
const searchOpportunities = ref('')
const applicationDialogOpen = ref(false)
const selectedOpportunity = ref<ApplicationDialogState | null>(null)
const applicationResume = ref('')
const selectedDocuments = ref<string[]>([])
const selectedRequirementDocuments = ref<string[]>([])
const selectedSavedDocumentIds = ref<string[]>([])
const selectedSavedResumeId = ref('')
const resumeFile = ref<File | null>(null)
const supportingFiles = ref<File[]>([])
const documentUploadFiles = ref<File[]>([])
const documentUploadCategory = ref('general')
const uploadingSavedDocuments = ref(false)
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
    applicationsLoading.value = false
    internshipsLoading.value = false
    documentsLoading.value = false
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

async function loadApplicationExtras() {
  if (!applications.value.length) {
    interviewsByApplication.value = {}
    ojtLogsByApplication.value = {}
    coverByInternship.value = {}
    return
  }

  applicationExtrasLoading.value = true
  try {
    const [interviews, logs] = await Promise.all([
      listInterviews().catch(() => [] as InterviewRecord[]),
      listInternOJTLogs().catch(() => [] as OJTLog[]),
    ])

    const interviewMap: Record<string, InterviewRecord[]> = {}
    for (const interview of interviews) {
      const key = String(interview.applicationId)
      if (!interviewMap[key]) interviewMap[key] = []
      interviewMap[key].push(interview)
    }
    interviewsByApplication.value = interviewMap

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
    .map((application) => {
      const stepper = deriveApplicationStepper({
        status: application.status,
        interviews: interviewsByApplication.value[String(application.id)] ?? [],
        ojtLogs: ojtLogsByApplication.value[String(application.id)] ?? [],
      })
      return {
        application,
        coverImage: coverByInternship.value[String(application.internshipId)] ?? null,
        stepper,
      }
    }),
)

const pageTitle = computed(() => {
  const titles: Record<InternView, string> = {
    settings: 'My Profile',
    opportunities: 'Opportunities',
    saved: 'Saved',
    documents: 'My Documents',
    internship: 'My Applications',
    placement: 'Placement',
    dashboard: 'Status Tracker',
  }

  return titles[activeView.value]
})

const currentProfile = computed(() => authStore.user?.profile as Record<string, unknown> | undefined)

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

const latestApplication = computed(() =>
  [...applications.value].sort((a, b) => new Date(b.createdAt || 0).getTime() - new Date(a.createdAt || 0).getTime())[0],
)

const currentCourse = computed(() => String(currentProfile.value?.course || '').trim())

function isResumeCategory(document: DocumentRecord) {
  return ['application-resume', 'internship-application'].includes(String(document.category || '').toLowerCase())
}

const approvedDocuments = computed(() => savedDocuments.value.filter((document) => document.status === 'approved'))

const approvedResumeDocuments = computed(() => approvedDocuments.value.filter((document) => isResumeCategory(document)))

const approvedSupportingDocuments = computed(() =>
  approvedDocuments.value.filter((document) => !isResumeCategory(document)),
)

function courseMatchBadge(level: CourseMatchLevel): {
  label: string
  variant: 'success' | 'secondary' | 'outline'
} {
  switch (level) {
    case 'strong':
      return { label: 'Matches your course', variant: 'success' }
    case 'related':
      return { label: 'Related to your course', variant: 'secondary' }
    case 'open':
      return { label: 'Open to all courses', variant: 'secondary' }
    case 'unknown':
      return { label: 'Course fit unavailable', variant: 'outline' }
    case 'weak':
    default:
      return { label: 'Check course fit', variant: 'outline' }
  }
}

const opportunityRows = computed(() => {
  const query = searchOpportunities.value.trim().toLowerCase()
  const studentCourse = currentCourse.value || null

  const rows = internships.value.map((internship) => {
    const eligibleCourses = internship.eligibleCourses
    const match = matchCourseProgram(studentCourse, eligibleCourses)
    const badge = courseMatchBadge(match.level)

    return {
      id: internship.id,
      title: internship.title || 'Untitled opportunity',
      hostName: internship.companyName || internship.hostName || internship.schoolName || 'Host not set',
      hostType: internship.hostType === 'school' ? 'School-hosted' : 'Company-hosted',
      location: internship.location || 'Location not set',
      duration: internship.duration || 'TBA',
      programs: (eligibleCourses || []).join(', ') || 'Open to multiple programs',
      courseMatchLevel: match.level,
      courseMatchLabel: badge.label,
      courseMatchVariant: badge.variant,
    }
  })

  return stableSortByCourseMatchLevel(rows, (row) => row.courseMatchLevel).filter(
    (row) =>
      !query ||
      [row.title, row.hostName, row.hostType, row.location, row.programs].some((value) =>
        value.toLowerCase().includes(query),
      ),
  )
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

const appliedInternshipIds = computed(() => new Set(applications.value.map((application) => String(application.internshipId))))

const statusSteps = computed(() => {
  const normalized = String(latestApplication.value?.status || '').trim().toLowerCase()

  if (!latestApplication.value) {
    return [
      { label: 'Applied', active: true, complete: false, tone: 'pending', description: 'Submit an internship application to start tracking your progress.' },
      { label: 'Endorsed', active: false, complete: false, tone: 'pending', description: 'Your school endorsement will appear here once available.' },
      { label: 'Under Review', active: false, complete: false, tone: 'pending', description: 'Company review status will update once your application is assessed.' },
      { label: 'Accepted / Rejected', active: false, complete: false, tone: 'pending', description: 'Final decision will be shown after review.' },
    ]
  }

  const accepted = ['accepted', 'approved', 'active'].includes(normalized)
  const companyRejected = ['rejected', 'declined', 'cancelled'].includes(normalized)
  const schoolRejected = normalized === 'school_rejected'
  const endorsed = ['endorsed', 'accepted', 'approved', 'active', 'rejected', 'declined', 'cancelled'].includes(normalized)
  const underReview = ['endorsed'].includes(normalized)

  return [
    {
      label: 'Applied',
      active: ['submitted', 'pending', ''].includes(normalized),
      complete: true,
      tone: 'success',
      description: `Application submitted for ${latestApplication.value.internshipTitle || 'your target role'}.`,
    },
    {
      label: 'Endorsed',
      active: underReview,
      complete: endorsed,
      tone: schoolRejected ? 'destructive' : endorsed ? 'success' : 'pending',
      description: schoolRejected
        ? 'Your school did not endorse this application.'
        : latestApplication.value.schoolName
          ? `${latestApplication.value.schoolName} endorsed your application to the company.`
          : 'School endorsement details are being prepared.',
    },
    {
      label: 'Under Review',
      active: underReview,
      complete: accepted || companyRejected,
      tone: accepted ? 'success' : companyRejected ? 'destructive' : underReview ? 'warning' : 'pending',
      description: latestApplication.value.companyName
        ? `${latestApplication.value.companyName} is reviewing your application.`
        : 'The company review stage will appear here.',
    },
    {
      label: 'Accepted / Rejected',
      active: accepted || companyRejected || schoolRejected,
      complete: accepted || companyRejected || schoolRejected,
      tone: accepted ? 'success' : companyRejected || schoolRejected ? 'destructive' : 'pending',
      description: accepted
        ? 'Congratulations, your application has been accepted.'
        : companyRejected
          ? 'This application was not selected. You can keep applying to other roles.'
          : schoolRejected
            ? 'Your school rejected this application before it reached company review.'
          : 'Awaiting a final decision.',
    },
  ]
})

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
  if (['rejected', 'declined', 'cancelled'].includes(normalized)) return 'destructive'
  if (normalized === 'school_rejected') return 'destructive'
  return 'warning'
}

function badgeLabel(status?: string) {
  const normalized = String(status || '').trim().toLowerCase()
  if (!normalized) return 'Submitted'
  if (['submitted', 'pending'].includes(normalized)) return 'Submitted'
  if (normalized === 'school_rejected') return 'Rejected By School'
  return normalized
    .split(/[\s_-]+/)
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
    .join(' ')
}

function internViewFromRouteName(name: unknown): InternView {
  if (name === 'intern-opportunities') return 'opportunities'
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
  if (!isPartneredForSaved(item)) return
  void router.push({ name: 'intern-opportunities', query: { focus: item.id } })
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
    requiredDocuments: opportunity.requiredDocuments || [],
  }
  applicationResume.value = ''
  selectedDocuments.value = []
  selectedRequirementDocuments.value = []
  selectedSavedDocumentIds.value = []
  selectedSavedResumeId.value = ''
  resumeFile.value = null
  supportingFiles.value = []

  applicationDialogOpen.value = true
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

  const requiresResume = selectedOpportunity.value.requiredDocuments.some((item) => item.toLowerCase().includes('resume'))
  const hasResume = !requiresResume || applicationResume.value.trim().length > 0 || !!resumeFile.value
  const hasAllDocuments = selectedOpportunity.value.requiredDocuments.every((item) =>
    selectedRequirementDocuments.value.includes(item),
  )

  return hasResume && hasAllDocuments
})

function handleResumeFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  resumeFile.value = input.files?.[0] ?? null
  if (resumeFile.value) {
    selectedSavedResumeId.value = ''
  }
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
    const updatedProfile = await updateCurrentUserProfile({
      displayName: profileForm.value.displayName,
      profile: {
        schoolName: profileForm.value.schoolName,
        course: profileForm.value.course,
        yearLevel: profileForm.value.yearLevel,
        contactNumber: profileForm.value.contactNumber,
      },
      profileSetupComplete: true,
    })
    authStore.setUserProfile(updatedProfile)
    profileDialogOpen.value = false
    success('Profile updated.', {
      description: 'Your intern information has been saved.',
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

  if (appliedInternshipIds.value.has(String(opportunity.id))) {
    error('You already have an application for this opportunity.', {
      fallback: 'You already requested this opportunity.',
    })
    return
  }

  applyingInternshipId.value = String(opportunity.id)
  try {
    let resumeValue = applicationResume.value.trim() || null
    let documentValues = approvedSupportingDocuments.value
      .filter((document) => selectedSavedDocumentIds.value.includes(document.id))
      .map((document) => document.fileUrl)

    if (resumeFile.value) {
      const [uploadedResume] = await uploadDocuments({
        files: [resumeFile.value],
        category: 'application-resume',
      })
      resumeValue = uploadedResume?.fileUrl || resumeValue
    }

    if (supportingFiles.value.length) {
      const uploadedSupportingDocuments = await uploadDocuments({
        files: supportingFiles.value,
        category: 'application-supporting',
      })
      documentValues = [
        ...documentValues,
        ...uploadedSupportingDocuments.map((document) => document.fileUrl),
      ]
    }

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
  const allowedViews: InternView[] = ['settings', 'opportunities', 'saved', 'documents', 'internship', 'placement', 'dashboard']
  const nextView = allowedViews.includes(menuItem as InternView) ? (menuItem as InternView) : 'settings'
  activeView.value = nextView
  if (nextView === 'saved') void loadSavedInternshipsList()
  if (nextView === 'internship') void loadApplicationExtras()
  const targetName =
    nextView === 'opportunities'
      ? 'intern-opportunities'
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
  },
)

watch(
  () => authStore.user?.uid,
  (userId) => {
    hydrateProfileForm()
    startSubscriptions(userId)
  },
)
</script>

<template>
  <MainLayout role="student" :title="pageTitle" :active-item="activeView" @navigate="handleMenuClick($event.key)">
    <div class="space-y-6">
      <Card v-if="activeView === 'settings'" class="border-border/80 shadow-sm">
        <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="flex items-center gap-4">
            <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl bg-accent text-lg font-semibold text-primary">
              <img v-if="profileAvatarUrl" :src="profileAvatarUrl" alt="Profile avatar" class="h-full w-full object-cover" />
              <span v-else>{{ userInitials }}</span>
            </div>
            <div>
              <h2 class="text-2xl font-semibold text-foreground">{{ authStore.user?.displayName || 'Intern Profile' }}</h2>
              <p class="mt-1 text-sm text-muted-foreground">{{ authStore.user?.email }}</p>
            </div>
          </div>
          <Button class="gap-2" @click="openProfileDialog">
            <Edit3 class="h-4 w-4" />
            Edit Profile
          </Button>
        </CardHeader>
        <CardContent class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <div class="rounded-2xl bg-muted p-4">
            <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
              <GraduationCap class="h-4 w-4 text-primary" />
              School Name
            </div>
            <p class="mt-3 text-sm font-medium text-foreground">{{ currentProfile?.schoolName || 'Not set' }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
              <UserCircle2 class="h-4 w-4 text-primary" />
              Intern Code
            </div>
            <p class="mt-3 font-mono text-sm font-semibold text-foreground">{{ currentProfile?.internCode || 'Not assigned yet' }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
              <BriefcaseBusiness class="h-4 w-4 text-primary" />
              Course
            </div>
            <p class="mt-3 text-sm font-medium text-foreground">{{ currentProfile?.course || 'Not set' }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
              <UserCircle2 class="h-4 w-4 text-primary" />
              Year Level
            </div>
            <p class="mt-3 text-sm font-medium text-foreground">{{ currentProfile?.yearLevel || 'Not set' }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
              <Mail class="h-4 w-4 text-primary" />
              Email
            </div>
            <p class="mt-3 text-sm font-medium text-foreground">{{ authStore.user?.email || 'Not set' }}</p>
          </div>
          <div class="rounded-2xl bg-muted p-4">
            <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
              <MapPin class="h-4 w-4 text-primary" />
              Contact Number
            </div>
            <p class="mt-3 text-sm font-medium text-foreground">{{ currentProfile?.contactNumber || 'Not set' }}</p>
          </div>
        </CardContent>
      </Card>

      <Card v-else-if="activeView === 'opportunities'" class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Opportunities</h2>
            <p class="text-sm text-muted-foreground">Browse internship posts that are already available to you through your school.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="searchOpportunities" placeholder="Search opportunities..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="rounded-2xl border border-border bg-accent p-4 text-sm text-foreground">
            <p class="font-medium">Eligible internship posts</p>
            <p class="mt-2">
              {{
                currentCourse
                  ? `Showing school-hosted and partner company posts available to your school. Matches for ${currentCourse} are highlighted so you can compare them quickly.`
                  : 'Set your course in your profile to get better matching for eligible internship posts.'
              }}
            </p>
          </div>

          <div class="rounded-xl border border-border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Opportunity</TableHead>
                  <TableHead>Host</TableHead>
                  <TableHead>Programs</TableHead>
                  <TableHead>Course Match</TableHead>
                  <TableHead>Duration</TableHead>
                  <TableHead>Action</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="internshipsLoading">
                  <TableRow v-for="index in 4" :key="index">
                    <TableCell><Skeleton class="h-5 w-36" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-36" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-40" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-28" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-24" /></TableCell>
                    <TableCell><Skeleton class="h-9 w-32" /></TableCell>
                  </TableRow>
                </template>
                <template v-else-if="opportunityRows.length">
                  <TableRow v-for="opportunity in opportunityRows" :key="opportunity.id">
                    <TableCell>
                      <div class="space-y-1">
                        <p class="font-medium text-foreground">{{ opportunity.title }}</p>
                        <p class="text-sm text-muted-foreground">{{ opportunity.location }}</p>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div class="space-y-1">
                        <p class="text-foreground">{{ opportunity.hostName }}</p>
                        <p class="text-sm text-muted-foreground">{{ opportunity.hostType }}</p>
                      </div>
                    </TableCell>
                    <TableCell class="text-foreground">{{ opportunity.programs }}</TableCell>
                    <TableCell>
                      <Badge :variant="opportunity.courseMatchVariant">
                        {{ opportunity.courseMatchLabel }}
                      </Badge>
                    </TableCell>
                    <TableCell class="text-foreground">{{ opportunity.duration }}</TableCell>
                    <TableCell>
                      <Button
                        variant="outline"
                        class="gap-2"
                        :disabled="applyingInternshipId === opportunity.id || appliedInternshipIds.has(String(opportunity.id))"
                        @click="openApplicationDialog(opportunity.id)"
                      >
                        <LoaderCircle v-if="applyingInternshipId === opportunity.id" class="h-4 w-4 animate-spin" />
                        <BriefcaseBusiness v-else class="h-4 w-4" />
                        {{
                          appliedInternshipIds.has(String(opportunity.id))
                            ? 'Requested'
                            : 'Apply Directly'
                        }}
                      </Button>
                    </TableCell>
                  </TableRow>
                </template>
                <TableRow v-else>
                  <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
                    No eligible internship posts are available yet.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card v-else-if="activeView === 'documents'" class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">My Documents</h2>
            <p class="text-sm text-muted-foreground">Upload your resume and supporting files here once, then reuse them for internship applications.</p>
          </div>
        </CardHeader>
        <CardContent class="space-y-6">
          <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-border bg-accent p-4">
              <p class="text-sm font-medium text-foreground">Saved files</p>
              <p class="mt-3 text-3xl font-semibold text-foreground">{{ savedDocuments.length }}</p>
              <p class="mt-2 text-sm text-muted-foreground">Documents ready to reuse in future applications.</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
              <p class="text-sm font-medium text-emerald-900">Resume ready</p>
              <p class="mt-3 text-3xl font-semibold text-foreground">
                {{ savedDocuments.some((document) => ['application-resume', 'internship-application'].includes(String(document.category || '').toLowerCase())) ? 'Yes' : 'No' }}
              </p>
              <p class="mt-2 text-sm text-muted-foreground">A saved resume can auto-fill your next application.</p>
            </div>
            <div class="rounded-2xl border border-violet-100 bg-violet-50 p-4">
              <p class="text-sm font-medium text-violet-900">Pending review</p>
              <p class="mt-3 text-3xl font-semibold text-foreground">
                {{ savedDocuments.filter((document) => document.status === 'pending').length }}
              </p>
              <p class="mt-2 text-sm text-muted-foreground">Files that still need to be reviewed or checked.</p>
            </div>
          </div>

          <div class="overflow-hidden rounded-3xl border border-border bg-gradient-to-br from-accent via-card to-muted">
            <div class="grid gap-0 lg:grid-cols-[1.2fr_0.8fr]">
              <div class="space-y-4 p-6">
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
                      class="block w-full rounded-2xl border border-border bg-card px-4 py-4 text-sm shadow-sm file:mr-4 file:rounded-xl file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
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
                  <Button :disabled="uploadingSavedDocuments || !documentUploadFiles.length" class="min-w-[160px]" @click="uploadSavedDocumentFiles">
                    <LoaderCircle v-if="uploadingSavedDocuments" class="h-4 w-4 animate-spin" />
                    <span>{{ uploadingSavedDocuments ? 'Uploading...' : 'Upload Documents' }}</span>
                  </Button>
                </div>
              </div>

              <div class="border-t border-border bg-foreground px-6 py-6 text-background lg:border-l lg:border-t-0">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-background/70">Quick Guide</p>
                <div class="mt-4 space-y-4">
                  <div class="rounded-2xl bg-background/10 p-4">
                    <p class="font-medium">1. Upload your resume</p>
                    <p class="mt-2 text-sm text-background/70">Use `Application Resume` so the system can find it first when you apply.</p>
                  </div>
                  <div class="rounded-2xl bg-background/10 p-4">
                    <p class="font-medium">2. Add school requirements</p>
                    <p class="mt-2 text-sm text-background/70">Store endorsement forms, portfolio files, and supporting documents here.</p>
                  </div>
                  <div class="rounded-2xl bg-background/10 p-4">
                    <p class="font-medium">3. Apply faster</p>
                    <p class="mt-2 text-sm text-background/70">Saved files are reused during internship applications whenever possible.</p>
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
        </CardContent>
      </Card>

      <Card v-else-if="activeView === 'saved'" class="border-border/80 shadow-sm">
        <CardHeader>
          <h2 class="text-2xl font-semibold text-foreground">Saved internships</h2>
          <p class="text-sm text-muted-foreground">Your wishlist of openings to revisit later.</p>
        </CardHeader>
        <CardContent>
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
                  :alt="`${item.title} cover`"
                  class="h-full w-full object-cover"
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
                <div class="flex flex-wrap gap-2">
                  <Button size="sm" :disabled="!isPartneredForSaved(item)" @click="applyFromSaved(item)">
                    {{ isPartneredForSaved(item) ? 'Apply' : 'Not yet available' }}
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
              to="/find-internships"
              class="mt-4 inline-flex h-10 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium text-foreground hover:bg-accent"
            >
              Browse internships
            </RouterLink>
          </div>
        </CardContent>
      </Card>

      <Card v-else-if="activeView === 'internship'" class="border-border/80 shadow-sm">
        <CardHeader>
          <h2 class="text-2xl font-semibold text-foreground">My Applications</h2>
          <p class="text-sm text-muted-foreground">Track each booking-style application from submission through OJT.</p>
        </CardHeader>
        <CardContent class="space-y-4">
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
                <div class="aspect-[16/10] bg-muted md:aspect-auto md:min-h-full">
                  <img
                    v-if="card.coverImage"
                    :src="card.coverImage"
                    :alt="`${card.application.internshipTitle || 'Internship'} cover`"
                    class="h-full w-full object-cover"
                    loading="lazy"
                  />
                  <div v-else class="flex h-full min-h-36 items-center justify-center text-muted-foreground">
                    <Building2 class="h-7 w-7 opacity-70" />
                  </div>
                </div>
                <div class="space-y-4 p-4 sm:p-5">
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p class="text-sm text-muted-foreground">{{ card.application.companyName || 'Host organization' }}</p>
                      <h3 class="text-lg font-semibold text-foreground">
                        {{ card.application.internshipTitle || 'Untitled position' }}
                      </h3>
                      <p class="mt-1 text-xs text-muted-foreground">Applied {{ formatDate(card.application.createdAt) }}</p>
                    </div>
                    <Badge :variant="badgeVariant(card.application.status)">{{ badgeLabel(card.application.status) }}</Badge>
                  </div>
                  <ApplicationStatusStepper :result="card.stepper" />
                  <div class="flex flex-wrap gap-2">
                    <RouterLink
                      v-if="card.application.internshipId"
                      :to="{ name: 'internship-detail', params: { id: card.application.internshipId } }"
                      class="inline-flex h-9 items-center justify-center rounded-md border border-border bg-background px-3 text-sm font-medium text-foreground hover:bg-accent"
                    >
                      View details
                    </RouterLink>
                  </div>
                </div>
              </div>
            </article>
          </template>
          <div v-else class="rounded-xl border border-dashed border-border px-6 py-14 text-center text-sm text-muted-foreground">
            No applications yet. Start exploring internship opportunities to populate this list.
          </div>
        </CardContent>
      </Card>

      <Card v-else-if="activeView === 'placement'" class="border-border/80 shadow-sm">
        <CardHeader>
          <h2 class="text-2xl font-semibold text-foreground">Placement</h2>
          <p class="text-sm text-muted-foreground">See your accepted placement and the latest assignment details shared by your school or host.</p>
        </CardHeader>
        <CardContent class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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

          <div class="rounded-2xl border border-border bg-accent p-4 sm:col-span-2 xl:col-span-4">
            <p class="text-sm font-medium text-foreground">Placement notes</p>
            <p class="mt-2 text-sm leading-6 text-foreground">
              This area is ready for supervisor details, required internship hours, school endorsement status, and school-based placement records such as BSED practice teaching assignments.
            </p>
          </div>
        </CardContent>
      </Card>

      <Card v-else class="border-border/80 shadow-sm">
        <CardHeader>
          <h2 class="text-2xl font-semibold text-foreground">Status Tracker</h2>
          <p class="text-sm text-muted-foreground">Follow each milestone from initial application through the final decision.</p>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-4">
            <div
              v-for="(step, index) in statusSteps"
              :key="step.label"
              class="relative rounded-2xl border border-border bg-muted p-5"
            >
              <div v-if="index < statusSteps.length - 1" class="absolute left-[1.7rem] top-[4.4rem] h-10 w-px bg-border" />
              <div class="flex gap-4">
                <div
                  class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                  :class="
                    step.tone === 'success'
                      ? 'bg-emerald-100 text-emerald-700'
                      : step.tone === 'destructive'
                        ? 'bg-red-100 text-red-700'
                        : step.tone === 'warning'
                          ? 'bg-amber-100 text-amber-700'
                          : 'bg-border text-muted-foreground'
                  "
                >
                  <CheckCircle2 v-if="step.complete && step.tone !== 'destructive'" class="h-5 w-5" />
                  <XCircle v-else-if="step.tone === 'destructive' && step.active" class="h-5 w-5" />
                  <CircleDashed v-else class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-lg font-semibold text-foreground">{{ step.label }}</h3>
                    <Badge
                      :variant="
                        step.tone === 'success'
                          ? 'success'
                          : step.tone === 'destructive'
                            ? 'destructive'
                            : step.tone === 'warning'
                              ? 'warning'
                              : 'outline'
                      "
                    >
                      {{ step.complete ? 'Completed' : step.active ? 'In Progress' : 'Waiting' }}
                    </Badge>
                  </div>
                  <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ step.description }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-border bg-accent p-4 text-sm text-foreground">
            <div class="flex items-center gap-2 font-medium">
              <CalendarDays class="h-4 w-4" />
              Latest update
            </div>
            <p class="mt-2">
              {{ latestApplication ? `Last activity recorded on ${formatDate(latestApplication.updatedAt || latestApplication.createdAt)}.` : 'No application timeline yet. Once you apply, your stepper will update automatically.' }}
            </p>
          </div>

          <ApplicationInterviewPanel
            v-if="latestApplication?.id"
            :application-id="latestApplication.id"
            mode="student"
          />
        </CardContent>
      </Card>
    </div>

    <Dialog v-model:open="profileDialogOpen">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>Edit Profile</DialogTitle>
          <p class="text-sm text-muted-foreground">Update the details that schools and companies will see in your intern workspace.</p>
        </DialogHeader>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
          <FormItem class="sm:col-span-2">
            <FormLabel for="profileName">Full Name</FormLabel>
            <FormControl>
              <Input id="profileName" v-model="profileForm.displayName" placeholder="Enter your full name" />
            </FormControl>
          </FormItem>
          <FormItem>
            <FormLabel for="profileEmail">Email</FormLabel>
            <FormControl>
              <Input id="profileEmail" v-model="profileForm.email" disabled />
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
              <Input id="profileSchool" v-model="profileForm.schoolName" placeholder="Enter your school name" />
            </FormControl>
          </FormItem>
          <FormItem>
            <FormLabel for="profileCourse">Course</FormLabel>
            <FormControl>
              <Input id="profileCourse" v-model="profileForm.course" placeholder="Enter your course" />
            </FormControl>
          </FormItem>
          <FormItem class="sm:col-span-2">
            <FormLabel for="profileYearLevel">Year Level</FormLabel>
            <FormControl>
              <Select id="profileYearLevel" v-model="profileForm.yearLevel">
                <option value="">Select year level</option>
                <option value="1st Year">1st Year</option>
                <option value="2nd Year">2nd Year</option>
                <option value="3rd Year">3rd Year</option>
                <option value="4th Year">4th Year</option>
                <option value="5th Year">5th Year</option>
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
          <DialogTitle>Application File Kit</DialogTitle>
        </DialogHeader>

        <div v-if="selectedOpportunity" class="mt-6 space-y-6">
          <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-3xl border border-border bg-card p-5 shadow-sm">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <h4 class="text-lg font-semibold text-foreground">Use Approved Documents</h4>
                  <p class="mt-1 text-sm text-muted-foreground">Choose from the documents already approved in your document hub.</p>
                </div>
                <Badge variant="outline">{{ approvedDocuments.length }} ready</Badge>
              </div>

              <div class="mt-5 space-y-4">
                <div>
                  <p class="text-sm font-medium text-foreground">Approved resume</p>
                  <div v-if="approvedResumeDocuments.length" class="mt-3 grid gap-3">
                    <button
                      v-for="document in approvedResumeDocuments"
                      :key="document.id"
                      type="button"
                      class="w-full rounded-2xl border p-4 text-left transition"
                      :class="
                        selectedSavedResumeId === document.id
                          ? 'border-primary bg-accent shadow-sm'
                          : 'border-border bg-muted hover:border-border hover:bg-card'
                      "
                      @click="selectSavedResume(document.id)"
                    >
                      <div class="flex items-start justify-between gap-3">
                        <div>
                          <p class="font-medium text-foreground">{{ document.fileName }}</p>
                          <p class="mt-1 text-sm text-muted-foreground">{{ document.category }}</p>
                        </div>
                        <Badge variant="success">Approved</Badge>
                      </div>
                    </button>
                  </div>
                  <p v-else class="mt-3 rounded-2xl border border-dashed border-border bg-muted px-4 py-4 text-sm text-muted-foreground">
                    No approved resume yet. You can import one below for this application.
                  </p>
                </div>

                <div>
                  <p class="text-sm font-medium text-foreground">Approved supporting files</p>
                  <div v-if="approvedSupportingDocuments.length" class="mt-3 grid gap-3 md:grid-cols-2">
                    <button
                      v-for="document in approvedSupportingDocuments"
                      :key="document.id"
                      type="button"
                      class="rounded-2xl border p-4 text-left transition"
                      :class="
                        selectedSavedDocumentIds.includes(document.id)
                          ? 'border-emerald-300 bg-emerald-50 shadow-sm'
                          : 'border-border bg-muted hover:border-emerald-500/40 hover:bg-card'
                      "
                      @click="toggleSavedDocument(document.id)"
                    >
                      <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                          <p class="truncate font-medium text-foreground">{{ document.fileName }}</p>
                          <p class="mt-1 text-sm text-muted-foreground">{{ document.category }}</p>
                        </div>
                        <Badge variant="success">{{ selectedSavedDocumentIds.includes(document.id) ? 'Selected' : 'Approved' }}</Badge>
                      </div>
                    </button>
                  </div>
                  <p v-else class="mt-3 rounded-2xl border border-dashed border-border bg-muted px-4 py-4 text-sm text-muted-foreground">
                    No approved supporting files yet. You can still import new documents below.
                  </p>
                </div>
              </div>
            </div>

            <div class="space-y-6">
              <div class="rounded-3xl border border-border bg-card p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <h4 class="text-lg font-semibold text-foreground">Requirement Checklist</h4>
                  </div>
                  <Badge variant="outline">
                    {{ selectedRequirementDocuments.length }}/{{ selectedOpportunity.requiredDocuments.length || 0 }}
                  </Badge>
                </div>

                <div
                  v-if="selectedOpportunity.requiredDocuments.length"
                  class="mt-5 space-y-3"
                >
                  <label
                    v-for="document in selectedOpportunity.requiredDocuments"
                    :key="document"
                    class="flex items-center gap-3 rounded-2xl border border-border bg-muted px-4 py-4 text-sm text-foreground transition hover:border-border hover:bg-card"
                  >
                    <input
                      type="checkbox"
                      :checked="selectedRequirementDocuments.includes(document)"
                      @change="toggleRequirementDocument(document)"
                    />
                    <span class="font-medium text-foreground">{{ document }}</span>
                  </label>
                </div>
                <p v-else class="mt-5 rounded-2xl border border-dashed border-border bg-muted px-4 py-4 text-sm text-muted-foreground">
                  This opportunity has no additional required documents listed.
                </p>
              </div>

              <div class="rounded-3xl border border-emerald-100 bg-emerald-50/70 p-5">
                <h4 class="text-lg font-semibold text-foreground">Application Summary</h4>
                <div class="mt-4 space-y-3 text-sm text-foreground">
                  <div class="flex items-center justify-between gap-4">
                    <span>Resume source</span>
                    <span class="font-medium text-foreground">
                      {{ resumeFile ? 'Imported now' : selectedSavedResumeId ? 'Approved document' : applicationResume ? 'Resume link added' : 'Missing' }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between gap-4">
                    <span>Approved files selected</span>
                    <span class="font-medium text-foreground">{{ selectedSavedDocumentIds.length }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-4">
                    <span>New files imported</span>
                    <span class="font-medium text-foreground">{{ supportingFiles.length }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-border bg-gradient-to-br from-card via-accent/70 to-muted p-5 shadow-sm">
            <div>
              <h4 class="text-lg font-semibold text-foreground">Import New Files</h4>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
              <FormItem class="lg:col-span-2">
                <FormLabel for="applicationResumeFile">Import Resume File</FormLabel>
                <FormControl>
                  <input
                    id="applicationResumeFile"
                    type="file"
                    accept=".pdf,.doc,.docx"
                    class="block w-full rounded-2xl border border-border bg-card px-4 py-4 text-sm shadow-sm transition file:mr-4 file:rounded-xl file:border-0 file:bg-primary file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-primary-foreground hover:border-primary/40 hover:file:bg-primary/90"
                    @change="handleResumeFileChange"
                  />
                </FormControl>
                <p v-if="resumeFile" class="mt-2 text-sm text-muted-foreground">Selected: {{ resumeFile.name }}</p>
              </FormItem>

              <FormItem class="lg:col-span-2">
                <FormLabel for="applicationSupportingFiles">Import Supporting Files</FormLabel>
                <FormControl>
                  <input
                    id="applicationSupportingFiles"
                    type="file"
                    multiple
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                    class="block w-full rounded-2xl border border-border bg-card px-4 py-4 text-sm shadow-sm transition file:mr-4 file:rounded-xl file:border-0 file:bg-primary file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-primary-foreground hover:border-primary/40 hover:file:bg-primary/90"
                    @change="handleSupportingFilesChange"
                  />
                </FormControl>
                <p v-if="supportingFiles.length" class="mt-2 text-sm text-muted-foreground">
                  {{ supportingFiles.length }} file{{ supportingFiles.length === 1 ? '' : 's' }} selected
                </p>
              </FormItem>

              <FormItem class="lg:col-span-2">
                <FormLabel for="applicationResume">Resume Link or File Reference</FormLabel>
                <FormControl>
                  <Input
                    id="applicationResume"
                    v-model="applicationResume"
                    placeholder="Paste a resume link if you are not importing a file"
                  />
                </FormControl>
              </FormItem>
            </div>
          </div>

          <div class="flex justify-end gap-3">
            <Button variant="outline" @click="close">Cancel</Button>
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
  </MainLayout>
</template>
