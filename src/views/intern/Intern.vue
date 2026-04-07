<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  BriefcaseBusiness,
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
import { subscribeEligibleInternships, type InternshipRecord } from '@/services/internships'
import { updateCurrentUserProfile } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'

type InternView = 'settings' | 'opportunities' | 'documents' | 'internship' | 'placement' | 'dashboard'

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
const applicationsLoading = ref(true)
const internshipsLoading = ref(true)
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
  })

  unsubscribeInternships = subscribeEligibleInternships((items) => {
    internships.value = items
    internshipsLoading.value = false
  })

  void loadSavedDocuments()
}

const pageTitle = computed(() => {
  const titles: Record<InternView, string> = {
    settings: 'My Profile',
    opportunities: 'Opportunities',
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

const contractedCompanyInternships = computed(() =>
  internships.value.filter((internship) => internship.hostType === 'company'),
)

const opportunityRows = computed(() => {
  const query = searchOpportunities.value.trim().toLowerCase()
  const course = currentCourse.value.toLowerCase()

  return contractedCompanyInternships.value
    .map((internship) => {
      const eligibleCourses = internship.eligibleCourses || []
      const matched = !course || eligibleCourses.length === 0 || eligibleCourses.some((item) => item.toLowerCase().includes(course))

      return {
        id: internship.id,
        title: internship.title || 'Untitled opportunity',
        hostName: internship.companyName || internship.hostName || internship.schoolName || 'Host not set',
        hostType: internship.hostType === 'school' ? 'School-hosted' : 'Company-hosted',
        location: internship.location || 'Location not set',
        duration: internship.duration || 'TBA',
        programs: eligibleCourses.join(', ') || 'Open to multiple programs',
        matched,
      }
    })
    .filter((row) =>
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
  if (name === 'intern-documents') return 'documents'
  if (name === 'intern-applications') return 'internship'
  if (name === 'intern-placement') return 'placement'
  if (name === 'intern-tracker') return 'dashboard'
  return 'settings'
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
    hostName: opportunity.companyName || opportunity.hostName || 'Company',
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
  const allowedViews: InternView[] = ['settings', 'opportunities', 'documents', 'internship', 'placement', 'dashboard']
  const nextView = allowedViews.includes(menuItem as InternView) ? (menuItem as InternView) : 'settings'
  activeView.value = nextView
  const targetName =
    nextView === 'opportunities'
      ? 'intern-opportunities'
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
            <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl bg-sky-100 text-lg font-semibold text-sky-700">
              <img v-if="profileAvatarUrl" :src="profileAvatarUrl" alt="Profile avatar" class="h-full w-full object-cover" />
              <span v-else>{{ userInitials }}</span>
            </div>
            <div>
              <h2 class="text-2xl font-semibold text-slate-950">{{ authStore.user?.displayName || 'Intern Profile' }}</h2>
              <p class="mt-1 text-sm text-slate-600">{{ authStore.user?.email }}</p>
            </div>
          </div>
          <Button class="gap-2" @click="openProfileDialog">
            <Edit3 class="h-4 w-4" />
            Edit Profile
          </Button>
        </CardHeader>
        <CardContent class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <div class="rounded-2xl bg-slate-50 p-4">
            <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
              <GraduationCap class="h-4 w-4 text-sky-700" />
              School Name
            </div>
            <p class="mt-3 text-sm font-medium text-slate-950">{{ currentProfile?.schoolName || 'Not set' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
              <UserCircle2 class="h-4 w-4 text-sky-700" />
              Intern Code
            </div>
            <p class="mt-3 font-mono text-sm font-semibold text-slate-950">{{ currentProfile?.internCode || 'Not assigned yet' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
              <BriefcaseBusiness class="h-4 w-4 text-sky-700" />
              Course
            </div>
            <p class="mt-3 text-sm font-medium text-slate-950">{{ currentProfile?.course || 'Not set' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
              <UserCircle2 class="h-4 w-4 text-sky-700" />
              Year Level
            </div>
            <p class="mt-3 text-sm font-medium text-slate-950">{{ currentProfile?.yearLevel || 'Not set' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
              <Mail class="h-4 w-4 text-sky-700" />
              Email
            </div>
            <p class="mt-3 text-sm font-medium text-slate-950">{{ authStore.user?.email || 'Not set' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
              <MapPin class="h-4 w-4 text-sky-700" />
              Contact Number
            </div>
            <p class="mt-3 text-sm font-medium text-slate-950">{{ currentProfile?.contactNumber || 'Not set' }}</p>
          </div>
        </CardContent>
      </Card>

      <Card v-else-if="activeView === 'opportunities'" class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-slate-950">Opportunities</h2>
            <p class="text-sm text-slate-600">Browse internship posts from companies that are actively contracted with your school.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <Input v-model="searchOpportunities" placeholder="Search opportunities..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4 text-sm text-sky-900">
            <p class="font-medium">Contracted company posts</p>
            <p class="mt-2">
              {{
                currentCourse
                  ? `Showing internship posts from partner companies connected to your school. Matches for ${currentCourse} are highlighted so you can compare them quickly.`
                  : 'Set your course in your profile to get better matching for your school’s contracted company posts.'
              }}
            </p>
          </div>

          <div class="rounded-xl border border-border bg-white">
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
                        <p class="font-medium text-slate-950">{{ opportunity.title }}</p>
                        <p class="text-sm text-slate-500">{{ opportunity.location }}</p>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div class="space-y-1">
                        <p class="text-slate-700">{{ opportunity.hostName }}</p>
                        <p class="text-sm text-slate-500">Contracted company</p>
                      </div>
                    </TableCell>
                    <TableCell class="text-slate-700">{{ opportunity.programs }}</TableCell>
                    <TableCell>
                      <Badge :variant="opportunity.matched ? 'success' : 'outline'">
                        {{ opportunity.matched ? 'Matches your course' : 'Check course fit' }}
                      </Badge>
                    </TableCell>
                    <TableCell class="text-slate-700">{{ opportunity.duration }}</TableCell>
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
                    No internship posts from contracted companies are available yet.
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
            <h2 class="text-2xl font-semibold text-slate-950">My Documents</h2>
            <p class="text-sm text-slate-600">Upload your resume and supporting files here once, then reuse them for internship applications.</p>
          </div>
        </CardHeader>
        <CardContent class="space-y-6">
          <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4">
              <p class="text-sm font-medium text-sky-900">Saved files</p>
              <p class="mt-3 text-3xl font-semibold text-slate-950">{{ savedDocuments.length }}</p>
              <p class="mt-2 text-sm text-slate-600">Documents ready to reuse in future applications.</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
              <p class="text-sm font-medium text-emerald-900">Resume ready</p>
              <p class="mt-3 text-3xl font-semibold text-slate-950">
                {{ savedDocuments.some((document) => ['application-resume', 'internship-application'].includes(String(document.category || '').toLowerCase())) ? 'Yes' : 'No' }}
              </p>
              <p class="mt-2 text-sm text-slate-600">A saved resume can auto-fill your next application.</p>
            </div>
            <div class="rounded-2xl border border-violet-100 bg-violet-50 p-4">
              <p class="text-sm font-medium text-violet-900">Pending review</p>
              <p class="mt-3 text-3xl font-semibold text-slate-950">
                {{ savedDocuments.filter((document) => document.status === 'pending').length }}
              </p>
              <p class="mt-2 text-sm text-slate-600">Files that still need to be reviewed or checked.</p>
            </div>
          </div>

          <div class="overflow-hidden rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-cyan-50">
            <div class="grid gap-0 lg:grid-cols-[1.2fr_0.8fr]">
              <div class="space-y-4 p-6">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Document Hub</p>
                  <h3 class="mt-2 text-2xl font-semibold text-slate-950">Keep your application files ready</h3>
                  <p class="mt-2 text-sm leading-6 text-slate-600">
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
                      class="block w-full rounded-2xl border border-sky-200 bg-white px-4 py-4 text-sm shadow-sm file:mr-4 file:rounded-xl file:border-0 file:bg-sky-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-sky-700"
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

              <div class="border-t border-sky-100 bg-slate-950 px-6 py-6 text-white lg:border-l lg:border-t-0">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-200">Quick Guide</p>
                <div class="mt-4 space-y-4">
                  <div class="rounded-2xl bg-white/10 p-4">
                    <p class="font-medium">1. Upload your resume</p>
                    <p class="mt-2 text-sm text-slate-200">Use `Application Resume` so the system can find it first when you apply.</p>
                  </div>
                  <div class="rounded-2xl bg-white/10 p-4">
                    <p class="font-medium">2. Add school requirements</p>
                    <p class="mt-2 text-sm text-slate-200">Store endorsement forms, portfolio files, and supporting documents here.</p>
                  </div>
                  <div class="rounded-2xl bg-white/10 p-4">
                    <p class="font-medium">3. Apply faster</p>
                    <p class="mt-2 text-sm text-slate-200">Saved files are reused during internship applications whenever possible.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-xl font-semibold text-slate-950">Saved Files</h3>
                <p class="text-sm text-slate-600">Open or review the files you already uploaded for internships.</p>
              </div>
            </div>

            <div v-if="documentsLoading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <div v-for="index in 6" :key="index" class="rounded-2xl border border-border bg-white p-5">
                <Skeleton class="h-5 w-40" />
                <Skeleton class="mt-4 h-4 w-28" />
                <Skeleton class="mt-6 h-9 w-24" />
              </div>
            </div>

            <div v-else-if="savedDocuments.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <div
                v-for="document in savedDocuments"
                :key="document.id"
                class="rounded-2xl border border-border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
                    <FileText class="h-5 w-5" />
                  </div>
                  <Badge :variant="document.status === 'approved' ? 'success' : document.status === 'rejected' ? 'destructive' : 'warning'">
                    {{ badgeLabel(document.status) }}
                  </Badge>
                </div>
                <div class="mt-4 space-y-2">
                  <a :href="document.fileUrl" target="_blank" rel="noopener" class="block text-base font-semibold text-slate-950 hover:text-sky-700">
                    {{ document.fileName }}
                  </a>
                  <p class="text-sm text-slate-500">{{ document.fileType || 'File' }}</p>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                  <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                    {{ document.category }}
                  </span>
                  <span class="inline-flex rounded-full bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700">
                    {{ formatDate(document.createdAt) }}
                  </span>
                </div>
                <div class="mt-5">
                  <a
                    :href="document.fileUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center text-sm font-medium text-sky-700 hover:text-sky-800"
                  >
                    Open file
                  </a>
                </div>
              </div>
            </div>

            <div v-else class="rounded-3xl border border-dashed border-sky-200 bg-sky-50/70 px-6 py-12 text-center">
              <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-sky-700 shadow-sm">
                <FileText class="h-6 w-6" />
              </div>
              <h3 class="mt-4 text-lg font-semibold text-slate-950">No saved documents yet</h3>
              <p class="mt-2 text-sm text-slate-600">
                Upload your resume and internship requirements here so future applications are much easier.
              </p>
            </div>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
            <div class="flex items-center gap-2 font-medium text-slate-900">
              <FileText class="h-4 w-4" />
              Application reuse
            </div>
            <p class="mt-2">
              When you apply for an opportunity, the system now checks your saved documents first and pre-fills your resume and supporting files when available.
            </p>
          </div>
        </CardContent>
      </Card>

      <Card v-else-if="activeView === 'internship'" class="border-border/80 shadow-sm">
        <CardHeader>
          <h2 class="text-2xl font-semibold text-slate-950">My Applications</h2>
          <p class="text-sm text-slate-600">Review the companies you applied to and track where each application stands.</p>
        </CardHeader>
        <CardContent>
          <div class="rounded-xl border border-border bg-white">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Company</TableHead>
                  <TableHead>Position</TableHead>
                  <TableHead>Date Applied</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="applicationsLoading">
                  <TableRow v-for="index in 4" :key="index">
                    <TableCell><Skeleton class="h-5 w-28" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-40" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-24" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-20" /></TableCell>
                  </TableRow>
                </template>
                <template v-else-if="applications.length">
                  <TableRow v-for="application in applications" :key="application.id">
                    <TableCell class="font-medium text-slate-950">{{ application.companyName || 'Pending assignment' }}</TableCell>
                    <TableCell class="text-slate-700">{{ application.internshipTitle || 'Untitled position' }}</TableCell>
                    <TableCell class="text-slate-600">{{ formatDate(application.createdAt) }}</TableCell>
                    <TableCell>
                      <Badge :variant="badgeVariant(application.status)">{{ badgeLabel(application.status) }}</Badge>
                    </TableCell>
                  </TableRow>
                </template>
                <TableRow v-else>
                  <TableCell colspan="4" class="py-10 text-center text-muted-foreground">
                    No applications yet. Start exploring internship opportunities to populate this table.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card v-else-if="activeView === 'placement'" class="border-border/80 shadow-sm">
        <CardHeader>
          <h2 class="text-2xl font-semibold text-slate-950">Placement</h2>
          <p class="text-sm text-slate-600">See your accepted placement and the latest assignment details shared by your school or host.</p>
        </CardHeader>
        <CardContent class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-medium text-slate-500">Host</p>
            <p class="mt-3 text-sm font-medium text-slate-950">{{ placementSummary.host }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-medium text-slate-500">Role</p>
            <p class="mt-3 text-sm font-medium text-slate-950">{{ placementSummary.role }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-medium text-slate-500">Status</p>
            <p class="mt-3 text-sm font-medium text-slate-950">{{ placementSummary.status }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-medium text-slate-500">Latest Update</p>
            <p class="mt-3 text-sm font-medium text-slate-950">{{ placementSummary.updatedAt }}</p>
          </div>

          <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4 sm:col-span-2 xl:col-span-4">
            <p class="text-sm font-medium text-sky-900">Placement notes</p>
            <p class="mt-2 text-sm leading-6 text-slate-700">
              This area is ready for supervisor details, required internship hours, school endorsement status, and school-based placement records such as BSED practice teaching assignments.
            </p>
          </div>
        </CardContent>
      </Card>

      <Card v-else class="border-border/80 shadow-sm">
        <CardHeader>
          <h2 class="text-2xl font-semibold text-slate-950">Status Tracker</h2>
          <p class="text-sm text-slate-600">Follow each milestone from initial application through the final decision.</p>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-4">
            <div
              v-for="(step, index) in statusSteps"
              :key="step.label"
              class="relative rounded-2xl border border-border bg-slate-50 p-5"
            >
              <div v-if="index < statusSteps.length - 1" class="absolute left-[1.7rem] top-[4.4rem] h-10 w-px bg-slate-200" />
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
                          : 'bg-slate-200 text-slate-600'
                  "
                >
                  <CheckCircle2 v-if="step.complete && step.tone !== 'destructive'" class="h-5 w-5" />
                  <XCircle v-else-if="step.tone === 'destructive' && step.active" class="h-5 w-5" />
                  <CircleDashed v-else class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-lg font-semibold text-slate-950">{{ step.label }}</h3>
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
                  <p class="mt-2 text-sm leading-6 text-slate-600">{{ step.description }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4 text-sm text-sky-900">
            <div class="flex items-center gap-2 font-medium">
              <CalendarDays class="h-4 w-4" />
              Latest update
            </div>
            <p class="mt-2">
              {{ latestApplication ? `Last activity recorded on ${formatDate(latestApplication.updatedAt || latestApplication.createdAt)}.` : 'No application timeline yet. Once you apply, your stepper will update automatically.' }}
            </p>
          </div>
        </CardContent>
      </Card>
    </div>

    <Dialog v-model:open="profileDialogOpen">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>Edit Profile</DialogTitle>
          <p class="text-sm text-slate-600">Update the details that schools and companies will see in your intern workspace.</p>
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
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <h4 class="text-lg font-semibold text-slate-950">Use Approved Documents</h4>
                  <p class="mt-1 text-sm text-slate-600">Choose from the documents already approved in your document hub.</p>
                </div>
                <Badge variant="outline">{{ approvedDocuments.length }} ready</Badge>
              </div>

              <div class="mt-5 space-y-4">
                <div>
                  <p class="text-sm font-medium text-slate-700">Approved resume</p>
                  <div v-if="approvedResumeDocuments.length" class="mt-3 grid gap-3">
                    <button
                      v-for="document in approvedResumeDocuments"
                      :key="document.id"
                      type="button"
                      class="w-full rounded-2xl border p-4 text-left transition"
                      :class="
                        selectedSavedResumeId === document.id
                          ? 'border-sky-400 bg-sky-50 shadow-sm'
                          : 'border-slate-200 bg-slate-50 hover:border-sky-200 hover:bg-white'
                      "
                      @click="selectSavedResume(document.id)"
                    >
                      <div class="flex items-start justify-between gap-3">
                        <div>
                          <p class="font-medium text-slate-950">{{ document.fileName }}</p>
                          <p class="mt-1 text-sm text-slate-500">{{ document.category }}</p>
                        </div>
                        <Badge variant="success">Approved</Badge>
                      </div>
                    </button>
                  </div>
                  <p v-else class="mt-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                    No approved resume yet. You can import one below for this application.
                  </p>
                </div>

                <div>
                  <p class="text-sm font-medium text-slate-700">Approved supporting files</p>
                  <div v-if="approvedSupportingDocuments.length" class="mt-3 grid gap-3 md:grid-cols-2">
                    <button
                      v-for="document in approvedSupportingDocuments"
                      :key="document.id"
                      type="button"
                      class="rounded-2xl border p-4 text-left transition"
                      :class="
                        selectedSavedDocumentIds.includes(document.id)
                          ? 'border-emerald-300 bg-emerald-50 shadow-sm'
                          : 'border-slate-200 bg-slate-50 hover:border-emerald-200 hover:bg-white'
                      "
                      @click="toggleSavedDocument(document.id)"
                    >
                      <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                          <p class="truncate font-medium text-slate-950">{{ document.fileName }}</p>
                          <p class="mt-1 text-sm text-slate-500">{{ document.category }}</p>
                        </div>
                        <Badge variant="success">{{ selectedSavedDocumentIds.includes(document.id) ? 'Selected' : 'Approved' }}</Badge>
                      </div>
                    </button>
                  </div>
                  <p v-else class="mt-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                    No approved supporting files yet. You can still import new documents below.
                  </p>
                </div>
              </div>
            </div>

            <div class="space-y-6">
              <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <h4 class="text-lg font-semibold text-slate-950">Requirement Checklist</h4>
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
                    class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700 transition hover:border-sky-200 hover:bg-white"
                  >
                    <input
                      type="checkbox"
                      :checked="selectedRequirementDocuments.includes(document)"
                      @change="toggleRequirementDocument(document)"
                    />
                    <span class="font-medium text-slate-900">{{ document }}</span>
                  </label>
                </div>
                <p v-else class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                  This opportunity has no additional required documents listed.
                </p>
              </div>

              <div class="rounded-3xl border border-emerald-100 bg-emerald-50/70 p-5">
                <h4 class="text-lg font-semibold text-slate-950">Application Summary</h4>
                <div class="mt-4 space-y-3 text-sm text-slate-700">
                  <div class="flex items-center justify-between gap-4">
                    <span>Resume source</span>
                    <span class="font-medium text-slate-950">
                      {{ resumeFile ? 'Imported now' : selectedSavedResumeId ? 'Approved document' : applicationResume ? 'Resume link added' : 'Missing' }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between gap-4">
                    <span>Approved files selected</span>
                    <span class="font-medium text-slate-950">{{ selectedSavedDocumentIds.length }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-4">
                    <span>New files imported</span>
                    <span class="font-medium text-slate-950">{{ supportingFiles.length }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-white via-sky-50/70 to-cyan-50 p-5 shadow-sm">
            <div>
              <h4 class="text-lg font-semibold text-slate-950">Import New Files</h4>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
              <FormItem class="lg:col-span-2">
                <FormLabel for="applicationResumeFile">Import Resume File</FormLabel>
                <FormControl>
                  <input
                    id="applicationResumeFile"
                    type="file"
                    accept=".pdf,.doc,.docx"
                    class="block w-full rounded-2xl border border-sky-200 bg-white px-4 py-4 text-sm shadow-sm transition file:mr-4 file:rounded-xl file:border-0 file:bg-slate-950 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white hover:border-sky-300 hover:file:bg-sky-700"
                    @change="handleResumeFileChange"
                  />
                </FormControl>
                <p v-if="resumeFile" class="mt-2 text-sm text-slate-600">Selected: {{ resumeFile.name }}</p>
              </FormItem>

              <FormItem class="lg:col-span-2">
                <FormLabel for="applicationSupportingFiles">Import Supporting Files</FormLabel>
                <FormControl>
                  <input
                    id="applicationSupportingFiles"
                    type="file"
                    multiple
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                    class="block w-full rounded-2xl border border-sky-200 bg-white px-4 py-4 text-sm shadow-sm transition file:mr-4 file:rounded-xl file:border-0 file:bg-slate-950 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white hover:border-sky-300 hover:file:bg-sky-700"
                    @change="handleSupportingFilesChange"
                  />
                </FormControl>
                <p v-if="supportingFiles.length" class="mt-2 text-sm text-slate-600">
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
