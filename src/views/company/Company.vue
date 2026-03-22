<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import Sidebar from '../../components/Sidebar.vue'
import CompanySettings from '../../components/CompanySettings.vue'
import SubscriptionManager from '../../components/SubscriptionManager.vue'
import FloatingChatWidget from '../../components/FloatingChatWidget.vue'
import { useAuthStore } from '@/stores/auth'
import Swal from 'sweetalert2'
import TomSelect from 'tom-select'
import 'tom-select/dist/css/tom-select.css'
import { ensurePublicProfile } from '@/services/profilesPublic'
import {
  subscribeCompanyContracts,
  acceptContract,
  rejectContract,
  cancelContract,
  type ContractRecord,
} from '@/services/contracts'
import { createInternship, subscribeCompanyInternships, updateInternship, deleteInternship, type InternshipRecord } from '@/services/internships'
import {
  subscribeCompanyApplications,
  subscribeCompanyApplicationsByInternships,
  updateApplicationStatus,
  type ApplicationRecord,
} from '@/services/applications'
import { createEvaluation, subscribeCompanyEvaluations, type EvaluationRecord, type EvaluationStatus } from '@/services/evaluations'
import { subscribeAllSchoolPrograms, type ProgramOption } from '@/services/schoolPrograms'
import { 
  DocumentIcon, 
  ClockIcon, 
  CheckIcon, 
  UserIcon, 
  CalendarIcon, 
  ExclamationTriangleIcon,
  EyeIcon,
  XMarkIcon,
  ArrowDownTrayIcon,
  BellIcon,
  MagnifyingGlassIcon,
  MapPinIcon,
  StarIcon,
  ChartBarIcon,
  ChartBarSquareIcon,
  PlusIcon,
  ClipboardDocumentListIcon,
  FolderIcon,
  AcademicCapIcon,
  LockClosedIcon,
  EnvelopeIcon,
  PhoneIcon,
  PencilIcon,
  TrashIcon,
  CloudIcon,
  InformationCircleIcon,
  EllipsisVerticalIcon,
  UsersIcon
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
const router = useRouter()
const companyName = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return (profile?.companyName as string) || 
         (profile?.name as string) || 
         authStore.user?.displayName || 
         authStore.user?.email?.split('@')[0] || 
         'Company'
})

const companyInitials = computed(() => {
  return companyName.value
    .split(' ')
    .filter(Boolean)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
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

// TEMPORARY DATA: Notification dropdown state - this is a UI state variable
const showNotifications = ref(false)

// TEMPORARY DATA: Static notifications for dropdown - replace with real data from backend
const notifications = ref([
  {
    id: 1,
    title: 'Application Update',
    message: 'Your application to TechCorp has been reviewed',
    time: '2 hours ago',
    unread: true
  },
  {
    id: 2,
    title: 'Interview Scheduled',
    message: 'Interview scheduled for May 20 at 2:00 PM',
    time: '5 hours ago',
    unread: true
  },
  {
    id: 3,
    title: 'Document Reminder',
    message: 'Please upload your resume',
    time: '1 day ago',
    unread: false
  },
  {
    id: 4,
    title: 'New Internship Match',
    message: 'You have 3 new internship matches',
    time: '2 days ago',
    unread: false
  }
])

function toggleNotifications() {
  showNotifications.value = !showNotifications.value
}

const currentView = ref('dashboard')

// Internship details view state
const showInternshipDetailsModal = ref(false)
const selectedInternshipForDetails = ref<InternshipRecord | null>(null)

// Edit mode state
const isEditMode = ref(false)
const editingInternshipId = ref<string | null>(null)

const handleNavChange = (nav: string) => {
  // Don't set currentView for logout as it's handled in Sidebar
  if (nav !== 'logout') {
    currentView.value = nav
  }
}

// TEMPORARY DATA: Sample recent activities for dashboard display - replace with real data from backend
const recentActivities = ref([
  {
    id: 1,
    text: 'New application received for Software Developer Intern',
    time: '2 hours ago',
    icon: 'document',
    type: 'application'
  },
  {
    id: 2,
    text: 'John Doe timed in today',
    time: '5 hours ago',
    icon: 'clock',
    type: 'time'
  },
  {
    id: 3,
    text: "Maria Santos' internship marked as completed",
    time: '1 day ago',
    icon: 'check',
    type: 'completion'
  }
])

// TEMPORARY DATA: Sample notifications for dashboard display - replace with real data from backend
const dashboardNotifications = ref([
  {
    id: 1,
    text: 'New application for Software Developer Intern',
    time: '2 hours ago',
    icon: 'document',
    action: 'View Application',
    unread: true
  },
  {
    id: 2,
    text: 'Maria Santos is nearing internship completion (92%)',
    time: '5 hours ago',
    icon: 'calendar',
    action: 'View Progress',
    unread: true
  },
  {
    id: 3,
    text: 'Missing time log entry from John Doe - Oct 22',
    time: '1 day ago',
    icon: 'clock',
    action: 'Remind Intern',
    unread: true
  }
])

// TEMPORARY DATA: Sample interns near completion for dashboard display - replace with real data from backend
const nearCompletion = ref([
  { name: 'Sarah Chen', progress: 95, date: 'Oct 30, 2024' },
  { name: 'John Martinez', progress: 88, date: 'Nov 5, 2024' }
])

// TEMPORARY DATA: Internships view filter data - replace with real data from backend
const selectedSkills = ref(['Python', 'Data Analysis'])
const locationFilter = ref('')
const startDate = ref('2024-06-01')
const endDate = ref('2024-12-31')
const companyTypes = ref<string[]>([])
const duration = ref('4-6 Months')
const searchQuery = ref('')

// Company's posted internships - from Firebase
const internshipsList = ref<Array<InternshipRecord & { logo?: string; company?: string; match?: number; recommended?: boolean; skills?: string[] }>>([])
let unsubCompanyInternships: (() => void) | null = null

type ApprovalState = 'draft' | 'pending' | 'approved' | 'declined'

const editingInternshipRecord = computed(() => {
  if (!isEditMode.value || !editingInternshipId.value) return null
  return internshipsList.value.find((i) => i.id === editingInternshipId.value) || null
})

const internshipApprovalState = computed<ApprovalState>(() => {
  const record = editingInternshipRecord.value
  if (!record) return 'draft'

  if (record?.approvalStatus === 'approved') return 'approved'
  if (record?.approvalStatus === 'declined') return 'declined'
  if (record?.approvalStatus === 'pending') return 'pending'

  if (record?.status === 'draft') return 'draft'
  if (record?.status === 'active') return 'pending'
  return 'pending'
})

const internshipApprovalLabel = computed(() => {
  switch (internshipApprovalState.value) {
    case 'approved':
      return 'Approved'
    case 'declined':
      return 'Declined'
    case 'draft':
      return 'Draft'
    default:
      return 'Pending Review'
  }
})

const internshipApprovalHint = computed(() => {
  switch (internshipApprovalState.value) {
    case 'approved':
      return 'Your internship post is approved and visible to students.'
    case 'declined':
      return 'Your internship post was declined. Review notes and resubmit.'
    case 'draft':
      return 'This post is saved as a draft and not submitted for review yet.'
    default:
      return 'Your internship post is awaiting review.'
  }
})

// Company's applications (Quick Apply from students) - from Firebase
const applications = ref<ApplicationRecord[]>([])
let unsubCompanyApplications: (() => void) | null = null
let unsubByInternships: (() => void) | null = null

function mergeApplications(byCompanyId: ApplicationRecord[], byInternships: ApplicationRecord[]) {
  const seen = new Set<string>()
  const merged: ApplicationRecord[] = []
  for (const a of [...byCompanyId, ...byInternships]) {
    if (!seen.has(a.id)) {
      seen.add(a.id)
      merged.push(a)
    }
  }
  merged.sort((a, b) => {
    const aTime = (a.createdAt as { toMillis?: () => number })?.toMillis?.() ?? 0
    const bTime = (b.createdAt as { toMillis?: () => number })?.toMillis?.() ?? 0
    return bTime - aTime
  })
  return merged
}

const applicationsByCompanyId = ref<ApplicationRecord[]>([])
const applicationsByInternships = ref<ApplicationRecord[]>([])

// Contracts (company-school)
const companyContracts = ref<ContractRecord[]>([])
const expandedReceivedContractId = ref<string | null>(null)
let unsubCompanyContracts: (() => void) | null = null

watch(
  () => [applicationsByCompanyId.value, applicationsByInternships.value],
  () => {
    applications.value = mergeApplications(applicationsByCompanyId.value, applicationsByInternships.value)
  },
  { deep: true, immediate: true }
)

function setupSubscriptions(uid: string) {
  if (unsubCompanyInternships) unsubCompanyInternships()
  if (unsubCompanyApplications) unsubCompanyApplications()
  if (unsubByInternships) unsubByInternships()
  if (unsubCompanyContracts) unsubCompanyContracts()
  if (unsubCompanyEvaluations) unsubCompanyEvaluations()

  ensurePublicProfile(uid, {
    displayName: companyName.value,
    role: 'company',
    orgName: companyName.value,
    email:
      (((authStore.user?.profile as Record<string, unknown> | undefined)?.companyEmail as string | undefined) ||
        authStore.user?.email) ??
      undefined,
    courses: ((authStore.user?.profile as Record<string, unknown> | undefined)?.courses as string[] | undefined) || [],
  }).catch(() => {})

  unsubCompanyContracts = subscribeCompanyContracts(uid, (items) => {
    companyContracts.value = items
  })

  unsubCompanyInternships = subscribeCompanyInternships(uid, (items) => {
    internshipsList.value = items.map((i) => ({
      ...i,
      logo: (i.companyName || 'CO').slice(0, 2).toUpperCase(),
      company: i.companyName,
      match: 100,
      recommended: false,
      skills: i.requirements || [],
    }))
    const ids = items.map((i) => i.id)
    if (unsubByInternships) unsubByInternships()
    unsubByInternships = subscribeCompanyApplicationsByInternships(ids, (apps) => {
      applicationsByInternships.value = apps
    })
  })

  unsubCompanyApplications = subscribeCompanyApplications(uid, (items) => {
    applicationsByCompanyId.value = items
  })

  unsubCompanyEvaluations = subscribeCompanyEvaluations(uid, (items) => {
    evaluations.value = items
  })
}

let stopAuthWatch: (() => void) | null = null

onMounted(() => {
  function trySetup() {
    const uid = authStore.user?.uid
    if (uid && authStore.user?.role === 'company') {
      setupSubscriptions(uid)
    }
  }
  
  // Always subscribe to school programs, even if not authenticated yet
  unsubSchoolPrograms = subscribeAllSchoolPrograms((programs) => {
    availableSchoolPrograms.value = programs
  })
  
  trySetup()
  stopAuthWatch = watch(
    () => [authStore.initializing, authStore.user?.uid, authStore.user?.role] as const,
    () => {
      if (!authStore.initializing) trySetup()
    },
    { immediate: true }
  )
})

onUnmounted(() => {
  destroyCourseTomSelect()
  destroySkillsTomSelect()
  if (stopAuthWatch) stopAuthWatch()
  if (unsubCompanyInternships) unsubCompanyInternships()
  if (unsubCompanyApplications) unsubCompanyApplications()
  if (unsubByInternships) unsubByInternships()
  if (unsubCompanyContracts) unsubCompanyContracts()
  if (unsubSchoolPrograms) unsubSchoolPrograms()
  if (unsubCompanyEvaluations) unsubCompanyEvaluations()
})

function removeSkill(skill: string) {
  selectedSkills.value = selectedSkills.value.filter(s => s !== skill)
}

function clearAllFilters() {
  selectedSkills.value = []
  locationFilter.value = ''
  startDate.value = '2024-06-01'
  endDate.value = '2024-12-31'
  companyTypes.value = []
  duration.value = '4-6 Months'
}

// Create internship form state
const showCreateForm = ref(false)
const currentFormStep = ref(1)
const formTitle = ref('')
const formDescription = ref('')
const formSkills = ref<string[]>([])
const slotsAvailable = ref(1)
const startDateForm = ref('')
const endDateForm = ref('')
const formLocation = ref('')
const formMunicipality = ref('')
const formBarangay = ref('')
const formDuration = ref('')
const formType = ref('on-site')
const formDepartment = ref('')
const formHoursPerWeek = ref(20)
const formAllowance = ref('')
const hasAllowance = ref(false)

// Cavite Municipalities and their Barangays
const caviteBarangays: Record<string, string[]> = {
  'Bacoor': ['Alima', 'Aniban I', 'Aniban II', 'Aniban III', 'Aniban IV', 'Aniban V', 'Banalo', 'Bayanan', 'Campo Santo', 'Daang Bukid', 'Digman', 'Dulong Bayan', 'Habay I', 'Habay II', 'Kaingin', 'Ligas I', 'Ligas II', 'Ligas III', 'Mabolo I', 'Mabolo II', 'Mabolo III', 'Maliksi I', 'Maliksi II', 'Maliksi III', 'Mambog I', 'Mambog II', 'Mambog III', 'Mambog IV', 'Mambog V', 'Molino I', 'Molino II', 'Molino III', 'Molino IV', 'Molino V', 'Molino VI', 'Molino VII', 'Niog I', 'Niog II', 'Niog III', 'Panapaan I', 'Panapaan II', 'Panapaan III', 'Panapaan IV', 'Panapaan V', 'Panapaan VI', 'Panapaan VII', 'Panapaan VIII', 'Queens Row Central', 'Queens Row East', 'Queens Row West', 'Real I', 'Real II', 'Salinas I', 'Salinas II', 'Salinas III', 'Salinas IV', 'San Nicolas I', 'San Nicolas II', 'San Nicolas III', 'Sineguelasan', 'Tabing Dagat', 'Talaba I', 'Talaba II', 'Talaba III', 'Talaba IV', 'Talaba V', 'Talaba VI', 'Talaba VII', 'Zapote I', 'Zapote II', 'Zapote III', 'Zapote IV', 'Zapote V'],
  'Cavite City': ['Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5', 'Barangay 6', 'Barangay 7', 'Barangay 8', 'Barangay 9', 'Barangay 10', 'Barangay 11', 'Barangay 12', 'Barangay 13', 'Barangay 14', 'Barangay 15', 'Barangay 16', 'Barangay 17', 'Barangay 18', 'Barangay 19', 'Barangay 20', 'Barangay 21', 'Barangay 22', 'Barangay 23', 'Barangay 24', 'Barangay 25', 'Barangay 26', 'Barangay 27', 'Barangay 28', 'Barangay 29', 'Barangay 30', 'Barangay 31', 'Barangay 32', 'Barangay 33', 'Barangay 34', 'Barangay 35', 'Barangay 36', 'Barangay 37', 'Barangay 38', 'Barangay 39', 'Barangay 40', 'Barangay 41', 'Barangay 42', 'Barangay 43', 'Barangay 44', 'Barangay 45', 'Barangay 46', 'Barangay 47', 'Barangay 48', 'Barangay 49', 'Barangay 50', 'Barangay 51', 'Barangay 52', 'Barangay 53', 'Barangay 54', 'Barangay 55', 'Barangay 56', 'Barangay 57', 'Barangay 58', 'Barangay 59', 'Barangay 60', 'Barangay 61', 'Barangay 62', 'Barangay 63', 'Barangay 64', 'Barangay 65', 'Barangay 66', 'Barangay 67', 'Barangay 68', 'Barangay 69', 'Barangay 70', 'Barangay 71', 'Barangay 72', 'Barangay 73', 'Barangay 74', 'Barangay 75', 'Barangay 76', 'Barangay 77', 'Barangay 78', 'Barangay 79', 'Barangay 80', 'Barangay 81', 'Barangay 82', 'Barangay 83', 'Barangay 84'],
  'Dasmariñas': ['Salawag', 'Paliparan I', 'Paliparan II', 'Paliparan III', 'Burol I', 'Burol II', 'Burol III', 'Langkaan I', 'Langkaan II', 'Salitran I', 'Salitran II', 'Salitran III', 'Salitran IV', 'San Agustin I', 'San Agustin II', 'San Agustin III', 'Sampaloc I', 'Sampaloc II', 'Sampaloc III', 'Sampaloc IV', 'Sampaloc V', 'Victoria Reyes', 'Zone I', 'Zone II', 'Zone III', 'Zone IV'],
  'General Trias': ['Alingaro', 'Arnaldo', 'Bacao I', 'Bacao II', 'Bagumbayan', 'Biclatan', 'Buenavista I', 'Buenavista II', 'Buenavista III', 'Corregidor', 'Dulong Bayan', 'Gov. Ferrer', 'Javalera', 'Manggahan', 'Navarro', 'Ninety Sixth', 'Panungyanan', 'Pasong Camachile I', 'Pasong Camachile II', 'Pasong Kawayan I', 'Pasong Kawayan II', 'Pinagtipunan', 'Prinza', 'San Francisco', 'San Gabriel', 'San Juan I', 'San Juan II', 'Santa Clara', 'Santiago', 'Tapia', 'Tejero', 'Vibora'],
  'Imus': ['Alapan I-A', 'Alapan I-B', 'Alapan I-C', 'Alapan II-A', 'Alapan II-B', 'Anabu I-A', 'Anabu I-B', 'Anabu I-C', 'Anabu I-D', 'Anabu I-E', 'Anabu I-F', 'Anabu I-G', 'Anabu II-A', 'Anabu II-B', 'Anabu II-C', 'Anabu II-D', 'Anabu II-E', 'Anabu II-F', 'Bayan Luma I', 'Bayan Luma II', 'Bayan Luma III', 'Bayan Luma IV', 'Bayan Luma V', 'Bayan Luma VI', 'Bayan Luma VII', 'Bayan Luma VIII', 'Bucandala I', 'Bucandala II', 'Bucandala III', 'Bucandala IV', 'Bucandala V', 'Buhay na Tubig', 'Carsadang Bago I', 'Carsadang Bago II', 'Magdalo', 'Maharlika', 'Malagasang I-A', 'Malagasang I-B', 'Malagasang I-C', 'Malagasang I-D', 'Malagasang I-E', 'Malagasang I-F', 'Malagasang I-G', 'Malagasang II-A', 'Malagasang II-B', 'Malagasang II-C', 'Malagasang II-D', 'Malagasang II-E', 'Malagasang II-F', 'Mariano Espeleta I', 'Mariano Espeleta II', 'Mariano Espeleta III', 'Medicion I-A', 'Medicion I-B', 'Medicion I-C', 'Medicion I-D', 'Medicion II-A', 'Medicion II-B', 'Medicion II-C', 'Medicion II-D', 'Medicion II-E', 'Medicion II-F', 'Pag-Asa I', 'Pag-Asa II', 'Pag-Asa III', 'Palico I', 'Palico II', 'Palico III', 'Palico IV', 'Pasong Buaya I', 'Pasong Buaya II', 'Pinagbuklod', 'Poblacion I-A', 'Poblacion I-B', 'Poblacion I-C', 'Poblacion II-A', 'Poblacion II-B', 'Poblacion III-A', 'Poblacion III-B', 'Poblacion IV-A', 'Poblacion IV-B', 'Poblacion IV-C', 'Poblacion IV-D', 'Tanzang Luma I', 'Tanzang Luma II', 'Tanzang Luma III', 'Tanzang Luma IV', 'Tanzang Luma V', 'Tanzang Luma VI', 'Toclong I-A', 'Toclong I-B', 'Toclong I-C', 'Toclong II-A', 'Toclong II-B'],
  'Tagaytay': ['Asisan', 'Bagong Tubig', 'Calabuso', 'Dapdap East', 'Dapdap West', 'Francisco', 'Guinhawa North', 'Guinhawa South', 'Iruhin Central', 'Iruhin East', 'Iruhin South', 'Iruhin West', 'Kaybagal Central', 'Kaybagal North', 'Kaybagal South', 'Mag-Asawang Ilat', 'Maharlika East', 'Maharlika West', 'Maitim 2nd Central', 'Maitim 2nd East', 'Maitim 2nd West', 'Mendez Crossing East', 'Mendez Crossing West', 'Neogan', 'Patutong Malaki North', 'Patutong Malaki South', 'Sambong', 'San Jose', 'Silang Crossing East', 'Silang Crossing West', 'Sungay East', 'Sungay West', 'Tolentino East', 'Tolentino West', 'Zambal'],
  'Trece Martires': ['Aguado', 'Cabezas', 'Cabuco', 'Conchu', 'De Ocampo', 'Gregorio', 'Inocencio', 'Lapidario', 'Llavac', 'Luciano', 'Osorio', 'Perez', 'San Agustin'],
  'Alfonso': ['Amuyong', 'Barangay I', 'Barangay II', 'Barangay III', 'Barangay IV', 'Bilog', 'Buck Estate', 'Esperanza Ibaba', 'Esperanza Ilaya', 'Kaytitinga I', 'Kaytitinga II', 'Kaytitinga III', 'Luksuhin Ilaya', 'Luksuhin Ibaba', 'Mangas I', 'Mangas II', 'Marahan I', 'Marahan II', 'Pajo', 'Palumlum', 'Sikat', 'Sulsugin', 'Taywanak'],
  'Amadeo': ['Barangay I', 'Barangay II', 'Barangay III', 'Barangay IV', 'Barangay V', 'Barangay VI', 'Barangay VII', 'Barangay VIII', 'Barangay IX', 'Banaybanay', 'Bucal', 'Buho', 'Dagatan', 'Halang', 'Loma', 'Maymangga', 'Minantok Kanluran', 'Minantok Silangan', 'Pangil', 'Salaban', 'Talon', 'Tamacan'],
  'Carmona': ['Bancal', 'Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5', 'Barangay 6', 'Barangay 7', 'Cabilang Baybay', 'Lantic', 'Mabuhay', 'Maduya', 'Milagrosa', 'Barangay Zone I', 'Barangay Zone II', 'Barangay Zone III', 'Barangay Zone IV'],
  'General Emilio Aguinaldo': ['A. Dalusag', 'Batas Dao', 'Castaños Cerca', 'Castaños Lejos', 'Kabulusan', 'Kaymisas', 'Kaypaaba', 'Lumipa', 'Narvaez', 'Poblacion I', 'Poblacion II', 'Poblacion III', 'Poblacion IV', 'Tabora'],
  'General Mariano Alvarez': ['Aldiano Olaes', 'Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5', 'Barangay 6', 'Barangay 7', 'Benjamin Tirona', 'Bernardo Pulido', 'Epifanio Malia', 'Fiorello Calimag', 'Francisco De Castro', 'Francisco Reyes', 'Gavino Maderan', 'Gregoria De Jesus', 'Inocencio Salud', 'Jacinto Lumbreras', 'Kapitan Kua', 'Koronel Jose P. Elises', 'Macario Dacon', 'Marcelino Memije', 'Nicolasa Virata', 'Pantaleon Granados', 'Ramon Cruz', 'San Gabriel', 'San Jose', 'Severino De Las Alas', 'Tiniente Tiago'],
  'Indang': ['Agus-us', 'Alulod', 'Banaba Cerca', 'Banaba Lejos', 'Bancod', 'Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Buna Cerca', 'Buna Lejos I', 'Buna Lejos II', 'Calumpang Cerca', 'Calumpang Lejos I', 'Calumpang Lejos II', 'Carasuchi', 'Daine I', 'Daine II', 'Guyam Malaki', 'Guyam Munti', 'Harasan', 'Kayquit I', 'Kayquit II', 'Kayquit III', 'Kaytambog', 'Kaytapos', 'Limbon', 'Lumampong Balagbag', 'Lumampong Halayhay', 'Mahabangkahoy Cerca', 'Mahabangkahoy Lejos', 'Mataas na Lupa', 'Pulo', 'Tambo Balagbag', 'Tambo Ilaya', 'Tambo Kulit'],
  'Kawit': ['Balsahan-Bisita', 'Batong Dalig', 'Binakayan-Aplaya', 'Binakayan-Kanluran', 'Congbalay-Legaspi', 'Gahak', 'Kaingen', 'Magdalo', 'Manggahan-Lawin', 'Marulas', 'Panamitan', 'Poblacion', 'Pulvorista', 'Putol', 'San Sebastian', 'Santa Isabel', 'Tabon I', 'Tabon II', 'Tabon III', 'Toclong', 'Tramo-Bantayan', 'Wakas I', 'Wakas II'],
  'Magallanes': ['Barangay I', 'Barangay II', 'Barangay III', 'Barangay IV', 'Bend', 'Medina', 'Pacheco', 'Ramirez', 'Tua', 'Urdaneta'],
  'Maragondon': ['Balite I', 'Balite II', 'Bancaan', 'Bucal I', 'Bucal II', 'Caingin', 'Garita I', 'Garita II', 'Layong Mabilog', 'Mabato', 'Pantihan I', 'Pantihan II', 'Pantihan III', 'Patungan', 'Poblacion I-A', 'Poblacion I-B', 'Poblacion II-A', 'Poblacion II-B', 'Poblacion III-A', 'Poblacion III-B', 'San Miguel I', 'San Miguel II', 'Talipusngo', 'Tulay Kanluran', 'Tulay Silangan'],
  'Mendez': ['Anuling Cerca I', 'Anuling Cerca II', 'Anuling Lejos I', 'Anuling Lejos II', 'Asis I', 'Asis II', 'Asis III', 'Banaybanay I', 'Banaybanay II', 'Bukal', 'Galicia I', 'Galicia II', 'Galicia III', 'Miguel Mojica', 'Palocpoc I', 'Palocpoc II', 'Poblacion I', 'Poblacion II', 'Poblacion III', 'Poblacion IV'],
  'Naic': ['Bagong Kalsada', 'Balsahan', 'Bancaan', 'Bucana Malaki', 'Bucana Sasahan', 'Calubcob', 'Capt. C. Nazareno', 'Gomez-Zamora', 'Halang', 'Humbac', 'Ibayo Estacion', 'Ibayo Silangan', 'Kanluran', 'Labac', 'Latoria', 'Mabolo', 'Makina', 'Malainen Bago', 'Malainen Luma', 'Molino', 'Munting Mapino', 'Muzon', 'Palangue Central', 'Palangue Proper', 'Poblacion', 'Sabang', 'San Roque', 'Santulan', 'Sapa', 'Timalan Balsahan', 'Timalan Concepcion'],
  'Noveleta': ['Magdiwang', 'Poblacion', 'Salcedo I', 'Salcedo II', 'San Antonio I', 'San Antonio II', 'San Jose I', 'San Jose II', 'San Juan I', 'San Juan II', 'San Rafael I', 'San Rafael II', 'San Rafael III', 'San Rafael IV', 'Santa Rosa I', 'Santa Rosa II'],
  'Rosario': ['Bagbag I', 'Bagbag II', 'Kanluran', 'Ligtong I', 'Ligtong II', 'Ligtong III', 'Ligtong IV', 'Muzon I', 'Muzon II', 'Poblacion', 'Sapa I', 'Sapa II', 'Sapa III', 'Sapa IV', 'Silangan I', 'Silangan II', 'Tejeros Convention', 'Wawa I', 'Wawa II', 'Wawa III'],
  'Silang': ['Acacia', 'Adlas', 'Anahaw I', 'Anahaw II', 'Balite I', 'Balite II', 'Balite III', 'Batas', 'Biga I', 'Biga II', 'Biluso', 'Bucal', 'Buho', 'Bulihan', 'Cabangaan', 'Carmen', 'Hukay', 'Iba', 'Inchican', 'Ipil I', 'Ipil II', 'Kaong', 'Lalaan I', 'Lalaan II', 'Litlit', 'Lucsuhin', 'Lumil', 'Maguyam', 'Malabag', 'Mataas na Burol', 'Narra I', 'Narra II', 'Narra III', 'Paligawan', 'Pasong Langka', 'Pooc I', 'Pooc II', 'Pulong Bunga', 'Pulong Saging', 'Puting Kahoy', 'Sabutan', 'San Miguel I', 'San Miguel II', 'San Vicente I', 'San Vicente II', 'Santa Rosa I', 'Santa Rosa II', 'Santol', 'Tartaria', 'Tibig', 'Toledo', 'Tubuan I', 'Tubuan II', 'Tubuan III', 'Ulat', 'Yakal'],
  'Tanza': ['Amaya I', 'Amaya II', 'Amaya III', 'Amaya IV', 'Amaya V', 'Amaya VI', 'Amaya VII', 'Bagtas', 'Biga', 'Biwas', 'Bucal', 'Bunga', 'Calibuyo', 'Capipisa', 'Daang Amaya I', 'Daang Amaya II', 'Daang Amaya III', 'Halayhay', 'Julugan I', 'Julugan II', 'Julugan III', 'Julugan IV', 'Julugan V', 'Julugan VI', 'Julugan VII', 'Julugan VIII', 'Lambingan', 'Mulawin', 'Paradahan I', 'Paradahan II', 'Punta I', 'Punta II', 'Sahud Ulan', 'Sanja Mayor', 'Santol', 'Tanauan', 'Tres Cruses'],
  'Ternate': ['Bucana', 'Poblacion I-A', 'Poblacion I-B', 'Poblacion II', 'Poblacion III', 'San Jose', 'San Juan I', 'San Juan II', 'Sapang I', 'Sapang II']
}

const availableBarangays = computed(() => {
  return caviteBarangays[formMunicipality.value] || []
})

const postingLocation = computed(() => {
  const municipality = formMunicipality.value.trim()
  const barangay = formBarangay.value.trim()
  if (barangay && municipality) return `${barangay}, ${municipality}, Cavite`
  if (municipality) return `${municipality}, Cavite`
  return ''
})

watch(
  () => [formMunicipality.value, formBarangay.value] as const,
  () => {
    formLocation.value = postingLocation.value
  },
  { immediate: true }
)

const selectedCourses = ref<string[]>([])
const courseSelectRef = ref<HTMLSelectElement | null>(null)
const skillsSelectRef = ref<HTMLSelectElement | null>(null)

// Dynamic program options from schools
const availableSchoolPrograms = ref<ProgramOption[]>([])
let unsubSchoolPrograms: (() => void) | null = null

const defaultCourseSkillMap: Record<string, string[]> = {
  BSIT: ['HTML/CSS', 'JavaScript', 'TypeScript', 'Vue.js', 'React', 'Node.js', 'PHP', 'SQL', 'Git', 'REST APIs'],
  BSCS: ['Python', 'Java', 'C++', 'Data Structures', 'Algorithms', 'SQL', 'Git', 'Testing', 'REST APIs'],
  BSECE: ['Embedded Systems', 'C', 'Microcontrollers', 'Circuit Design', 'PCB', 'IoT', 'Signal Processing', 'MATLAB'],
  BSED: ['Lesson Planning', 'Classroom Management', 'Content Writing', 'Presentation Skills', 'Documentation'],
  ANY: ['Communication', 'Teamwork', 'Problem Solving', 'Time Management'],
}

function uniqueValues(values: string[]) {
  return Array.from(new Set(values.map((value) => value.trim()).filter(Boolean)))
}

const availableCourseOptions = computed(() => {
  const schoolPrograms = availableSchoolPrograms.value.map(p => p.shortName)
  const staticPrograms = Object.keys(defaultCourseSkillMap)
  const selectedPrograms = selectedCourses.value
  
  return uniqueValues([
    ...schoolPrograms,
    ...staticPrograms,
    ...selectedPrograms,
  ])
})

const availableSkillOptions = computed(() => uniqueValues([
  ...selectedCourses.value.flatMap((course) => defaultCourseSkillMap[course] || []),
  ...formSkills.value,
]))

let courseTomSelectInstance: TomSelect | null = null
let skillsTomSelectInstance: TomSelect | null = null

// Reactive key to force re-rendering of select elements
const selectKey = ref(0)

const minimumGPA = ref('')
const yearLevel = ref('')
const additionalRequirements = ref('')
const requiredSkills = ref('')
const primaryResponsibilities = ref('')
const learningObjectives = ref('')
const projectsDeliverables = ref('')
const applicationInstructions = ref('')
const contactInfo = ref('')
const hasEmailContact = ref(false)
const emailContact = ref('')
const hasSocialMediaContact = ref(false)
const socialMediaContact = ref('')
const hasSmsContact = ref(false)
const smsContact = ref('')
const submittingInternship = ref(false)

function normalizeTomSelectValues(raw: string | string[] | null | undefined) {
  return uniqueValues(
    Array.isArray(raw)
      ? raw.map((value) => String(value))
      : typeof raw === 'string'
        ? raw.split(',')
        : []
  )
}

function destroyCourseTomSelect() {
  if (!courseTomSelectInstance) return
  try {
    // Destroy the instance
    courseTomSelectInstance.destroy()
  } catch (error) {
    console.warn('Error destroying course TomSelect:', error)
  } finally {
    courseTomSelectInstance = null
  }
}

function destroySkillsTomSelect() {
  if (!skillsTomSelectInstance) return
  try {
    // Destroy the instance
    skillsTomSelectInstance.destroy()
  } catch (error) {
    console.warn('Error destroying skills TomSelect:', error)
  } finally {
    skillsTomSelectInstance = null
  }
}

function reinitializeTomSelect() {
  console.log('Reinitializing Tom Select...')
  
  // Clean up any existing instances
  destroyCourseTomSelect()
  destroySkillsTomSelect()
  
  // Increment key to force re-render of select elements
  selectKey.value++
  
  // Force re-initialization on next tick
  nextTick(() => {
    if (currentFormStep.value === 2) {
      initializeTomSelectForStep2()
    }
  })
}

async function initializeTomSelectForStep2() {
  console.log('Initializing Tom Select for step 2...')
  
  // Wait longer to ensure DOM is fully ready
  await nextTick()
  await new Promise(resolve => setTimeout(resolve, 200))
  
  // Initialize Course Tom Select
  const courseEl = courseSelectRef.value
  console.log('Course element:', courseEl, 'exists:', !!courseEl)
  
  if (courseEl && !(courseEl as any).tomselect) {
    try {
      // Clear any existing instance
      if (courseTomSelectInstance) {
        destroyCourseTomSelect()
      }
      
      courseTomSelectInstance = new TomSelect(courseEl, {
        plugins: ['remove_button'],
        persist: false,
        create(input: string) {
          const value = input.trim().toUpperCase()
          return value ? { value, text: value } : false
        },
        closeAfterSelect: true,
        maxItems: null,
        placeholder: 'Select or type courses/programs...'
      })

      console.log('Course Tom Select initialized successfully')

      // Set initial values if any
      if (selectedCourses.value.length) {
        courseTomSelectInstance.setValue(selectedCourses.value, true)
      }

      // Handle changes
      courseTomSelectInstance.on('change', () => {
        selectedCourses.value = normalizeTomSelectValues(courseTomSelectInstance?.getValue())
      })
    } catch (error) {
      console.error('Error initializing course Tom Select:', error)
    }
  }

  // Initialize Skills Tom Select
  const skillsEl = skillsSelectRef.value
  console.log('Skills element:', skillsEl, 'exists:', !!skillsEl)
  
  if (skillsEl && !(skillsEl as any).tomselect) {
    try {
      // Clear any existing instance
      if (skillsTomSelectInstance) {
        destroySkillsTomSelect()
      }
      
      skillsTomSelectInstance = new TomSelect(skillsEl, {
        plugins: ['remove_button'],
        persist: false,
        create(input: string) {
          const value = input.trim()
          return value ? { value, text: value } : false
        },
        closeAfterSelect: true,
        maxItems: null,
        placeholder: 'Select or type required skills...'
      })

      console.log('Skills Tom Select initialized successfully')

      // Set initial values if any
      if (formSkills.value.length) {
        skillsTomSelectInstance.setValue(formSkills.value, true)
      }

      // Handle changes
      skillsTomSelectInstance.on('change', () => {
        const newValues = normalizeTomSelectValues(skillsTomSelectInstance?.getValue())
        formSkills.value = newValues
      })
    } catch (error) {
      console.error('Error initializing skills Tom Select:', error)
    }
  }
}

watch(
  () => selectedCourses.value.join('|'),
  () => {
    selectedCourses.value = uniqueValues(selectedCourses.value)
  }
)

watch(
  () => currentFormStep.value,
  async (step, _prev, onCleanup) => {
    let cancelled = false
    onCleanup(() => {
      cancelled = true
    })

    console.log(`Step watcher triggered: ${step}`)

    if (step === 2) {
      await nextTick()
      if (cancelled || currentFormStep.value !== 2) return
      
      console.log('About to initialize Tom Select for step 2')
      initializeTomSelectForStep2()
    } else {
      // Clean up Tom Select instances when leaving step 2
      destroyCourseTomSelect()
      destroySkillsTomSelect()
    }
  },
  { immediate: true }
)

watch(
  () => selectedCourses.value.join('|'),
  (courses) => {
    if (!courseTomSelectInstance) return
    try {
      const selectedValues = normalizeTomSelectValues(courseTomSelectInstance.getValue())
      if (selectedValues.join('|') !== courses) {
        courseTomSelectInstance.setValue(selectedCourses.value, true)
      }
    } catch (error) {
      console.warn('Error updating course Tom Select values:', error)
    }
  }
)

watch(
  () => formSkills.value.join('|'),
  (skills) => {
    requiredSkills.value = formSkills.value.join(', ')

    if (!skillsTomSelectInstance) return
    
    try {
      const selectedValues = normalizeTomSelectValues(skillsTomSelectInstance.getValue())
      if (selectedValues.join('|') !== skills) {
        skillsTomSelectInstance.setValue(formSkills.value, true)
      }
    } catch (error) {
      console.warn('Error updating skills Tom Select values:', error)
    }
  }
)

// Clear allowance input when checkbox is unchecked
watch(hasAllowance, (newValue) => {
  if (!newValue) {
    formAllowance.value = ''
  }
})

// Clear contact inputs when checkboxes are unchecked
watch(hasEmailContact, (newValue) => {
  if (!newValue) {
    emailContact.value = ''
  }
})

watch(hasSocialMediaContact, (newValue) => {
  if (!newValue) {
    socialMediaContact.value = ''
  }
})

watch(hasSmsContact, (newValue) => {
  if (!newValue) {
    smsContact.value = ''
  }
})

function openCreateForm() {
  // Reset edit mode when creating new internship
  isEditMode.value = false
  editingInternshipId.value = null
  
  showCreateForm.value = true
  currentView.value = 'create-internship'
  currentFormStep.value = 1
}

function closeCreateForm() {
  // Clean up Tom Select instances
  destroyCourseTomSelect()
  destroySkillsTomSelect()
  
  // Reset edit mode state
  isEditMode.value = false
  editingInternshipId.value = null
  
  showCreateForm.value = false
  currentView.value = 'internships'
  currentFormStep.value = 1
}

function nextFormStep() {
  if (currentFormStep.value < 4) {
    currentFormStep.value++
  }
}

function prevFormStep() {
  if (currentFormStep.value > 1) {
    currentFormStep.value--
  }
}

async function saveDraft() {
  await submitInternship('draft')
}

async function submitForApproval() {
  await submitInternship('active')
}

async function submitInternship(status: 'active' | 'draft') {
  const uid = authStore.user?.uid
  if (!uid) {
    await Swal.fire({ icon: 'error', title: 'Not logged in', text: 'Please log in to post an internship.' })
    return
  }

  const trimmedTitle = formTitle.value.trim()
  if (!trimmedTitle) {
    await Swal.fire({ icon: 'warning', title: 'Missing title', text: 'Please enter an internship title.' })
    return
  }

  const isActive = status === 'active'
  if (isActive && !formDescription.value.trim()) {
    await Swal.fire({ icon: 'warning', title: 'Missing description', text: 'Please enter an internship description.' })
    return
  }
  if (isActive && (!formMunicipality.value.trim() || !formBarangay.value.trim())) {
    await Swal.fire({ icon: 'warning', title: 'Missing location', text: 'Please select both city/municipality and barangay.' })
    return
  }
  if (isActive && formSkills.value.length === 0) {
    await Swal.fire({ icon: 'warning', title: 'Missing skills', text: 'Please add at least one required skill or technology.' })
    return
  }

  // Remove the hours validation since schools will set this
  submittingInternship.value = true
  
  try {
    const requirements = formSkills.value.length
      ? uniqueValues(formSkills.value)
      : requiredSkills.value.split(/[,\n]/).map((s) => s.trim()).filter(Boolean)

    // Build contact information from selected methods
    const contactMethods = []
    if (hasEmailContact.value && emailContact.value) {
      contactMethods.push(`Email: ${emailContact.value}`)
    }
    if (hasSocialMediaContact.value && socialMediaContact.value) {
      contactMethods.push(`Social Media: ${socialMediaContact.value}`)
    }
    if (hasSmsContact.value && smsContact.value) {
      contactMethods.push(`SMS/Phone: ${smsContact.value}`)
    }

    const internshipData = {
      title: trimmedTitle,
      description: formDescription.value.trim() || undefined,
      companyId: uid,
      companyName: companyName.value,
      location: postingLocation.value || 'TBD',
      type: formType.value,
      duration: formDuration.value ? `${formDuration.value} weeks` : 'TBD',
      slotsAvailable: typeof slotsAvailable.value === 'number' ? slotsAvailable.value : parseInt(String(slotsAvailable.value), 10) || 1,
      eligibleCourses: selectedCourses.value.length ? uniqueValues(selectedCourses.value) : undefined,
      requirements: requirements.length ? requirements : undefined,
      allowance: hasAllowance.value && formAllowance.value ? formAllowance.value : undefined,
      contactInfo: contactMethods.length ? contactMethods.join(' | ') : undefined,
      status,
    }

    if (isEditMode.value && editingInternshipId.value) {
      // Update existing internship
      await updateInternship(editingInternshipId.value, internshipData)
      
      await Swal.fire({
        icon: 'success',
        title: 'Internship Updated!',
        text: 'Your internship has been successfully updated.',
        confirmButtonColor: '#2563eb',
      })
    } else {
      // Create new internship
      await createInternship({
        ...internshipData,
        approvalStatus: status === 'active' ? 'pending' : undefined,
      })
      
      await Swal.fire({
        icon: 'success',
        title: status === 'active' ? 'Internship Posted!' : 'Draft Saved',
        text: status === 'active' ? 'Your internship is now visible to students.' : 'Your draft has been saved.',
        confirmButtonColor: '#2563eb',
      })
    }

    closeCreateForm()
    resetCreateForm()
  } catch (err) {
    await Swal.fire({
      icon: 'error',
      title: isEditMode.value ? 'Failed to update' : 'Failed to post',
      text: err instanceof Error ? err.message : 'Could not save internship.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    submittingInternship.value = false
  }
}

function resetCreateForm() {
  // Clean up Tom Select instances first
  destroyCourseTomSelect()
  destroySkillsTomSelect()
  
  // Reset edit mode state
  isEditMode.value = false
  editingInternshipId.value = null
  
  // Reset form data
  formTitle.value = ''
  formDescription.value = ''
  formLocation.value = ''
  formMunicipality.value = ''
  formBarangay.value = ''
  formDuration.value = ''
  formType.value = 'on-site'
  formDepartment.value = ''
  formSkills.value = []
  slotsAvailable.value = 1
  formHoursPerWeek.value = 20
  formAllowance.value = ''
  hasAllowance.value = false
  selectedCourses.value = []
  minimumGPA.value = ''
  requiredSkills.value = ''
  yearLevel.value = ''
  additionalRequirements.value = ''
  primaryResponsibilities.value = ''
  learningObjectives.value = ''
  projectsDeliverables.value = ''
  applicationInstructions.value = ''
  contactInfo.value = ''
  hasEmailContact.value = false
  emailContact.value = ''
  hasSocialMediaContact.value = false
  socialMediaContact.value = ''
  hasSmsContact.value = false
  smsContact.value = ''
  startDateForm.value = ''
  endDateForm.value = ''
  
  // Reset select key to force re-render
  selectKey.value++
}

function openInternshipDetails(internship: InternshipRecord) {
  selectedInternshipForDetails.value = internship
  showInternshipDetailsModal.value = true
}

function closeInternshipDetails() {
  showInternshipDetailsModal.value = false
  selectedInternshipForDetails.value = null
}

function editInternship(internshipId: string) {
  // Close the details modal first
  closeInternshipDetails()
  
  // Find the internship to edit
  const internship = internshipsList.value.find(i => i.id === internshipId)
  if (internship) {
    // Set edit mode
    isEditMode.value = true
    editingInternshipId.value = internshipId
    
    // Populate the form with existing data
    formTitle.value = internship.title
    formDescription.value = internship.description || ''
    formLocation.value = internship.location
    formType.value = internship.type
    formDuration.value = internship.duration.replace(' weeks', '') || ''
    slotsAvailable.value = internship.slotsAvailable
    selectedCourses.value = internship.eligibleCourses || []
    formSkills.value = internship.requirements || []
    formAllowance.value = internship.allowance || ''
    hasAllowance.value = !!(internship.allowance && internship.allowance.trim())
    
    // Parse contact information
    if (internship.contactInfo) {
      const contactMethods = internship.contactInfo.split(' | ')
      contactMethods.forEach((method: string) => {
        if (method.startsWith('Email: ')) {
          hasEmailContact.value = true
          emailContact.value = method.replace('Email: ', '')
        } else if (method.startsWith('Social Media: ')) {
          hasSocialMediaContact.value = true
          socialMediaContact.value = method.replace('Social Media: ', '')
        } else if (method.startsWith('SMS/Phone: ')) {
          hasSmsContact.value = true
          smsContact.value = method.replace('SMS/Phone: ', '')
        }
      })
    }
    
    // Parse location if it's in the format "Barangay, Municipality, Cavite"
    const locationParts = internship.location?.split(', ')
    if (locationParts && locationParts.length >= 2) {
      formBarangay.value = locationParts[0] || ''
      formMunicipality.value = locationParts[1] || ''
    }
    
    // Open the create form in edit mode
    showCreateForm.value = true
    currentView.value = 'create-internship'
    currentFormStep.value = 1
  }
}

async function deleteInternshipPosting(internshipId: string) {
  const internship = internshipsList.value.find((i) => i.id === internshipId)
  const title = internship?.title || 'this internship'

  const res = await Swal.fire({
    icon: 'warning',
    title: 'Delete internship posting?',
    text: `This will permanently delete "${title}".`,
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Cancel',
    cancelButtonColor: '#64748b',
  })

  if (!res.isConfirmed) return

  try {
    await deleteInternship(internshipId)
    await Swal.fire({
      icon: 'success',
      title: 'Deleted',
      text: 'Internship posting deleted successfully.',
      timer: 1400,
      showConfirmButton: false,
    })
  } catch (err: any) {
    await Swal.fire({
      icon: 'error',
      title: 'Delete failed',
      text: err?.message || 'Could not delete internship posting.',
      confirmButtonColor: '#2563eb',
    })
  }
}

function viewInternshipApplications(internshipId: string) {
  // Close the details modal first
  closeInternshipDetails()
  
  // Set the filter to show applications for this specific internship
  selectedInternshipFilter.value = internshipId
  
  // Navigate to applications view
  currentView.value = 'applications'
}

// TEMPORARY DATA: Sample attendance records for reports view - replace with real data from backend
const attendanceRecords = ref([
  { date: 'Nov 28, 2024', in: '8:00 AM', out: '5:00 PM', hours: '8.0', status: 'Present' },
  { date: 'Nov 27, 2024', in: '8:00 AM', out: '5:00 PM', hours: '8.0', status: 'Present' },
  { date: 'Nov 26, 2024', in: '8:00 AM', out: '5:00 PM', hours: '8.0', status: 'Present' },
  { date: 'Nov 25, 2024', in: '8:00 AM', out: '5:00 PM', hours: '8.0', status: 'Present' },
  { date: 'Nov 22, 2024', in: '8:00 AM', out: '5:00 PM', hours: '8.0', status: 'Present' },
])

// TEMPORARY DATA: Sample journal entries for reports view - replace with real data from backend
const journalEntries = ref([
  { week: 24, title: 'Project Deployment & Final Testing', date: 'Nov 25, 2024' },
  { week: 23, title: 'User Acceptance Testing Phase', date: 'Nov 18, 2024' },
  { week: 22, title: 'Backend API Development', date: 'Nov 11, 2024' },
  { week: 21, title: 'Frontend Component Integration', date: 'Nov 4, 2024' },
  { week: 20, title: 'Database Schema Design', date: 'Oct 28, 2024' },
])

// TEMPORARY DATA: Sample performance metrics for reports view - replace with real data from backend
const performanceMetrics = ref([
  { skill: 'Technical Skills', rating: 4.8 },
  { skill: 'Communication', rating: 4.7 },
  { skill: 'Team Collaboration', rating: 5.0 },
  { skill: 'Initiative', rating: 4.6 },
  { skill: 'Problem Solving', rating: 4.9 },
])

// Applications view filter state
const selectedInternshipFilter = ref('')
const searchStudent = ref('')

// Filtered applications for display
const filteredApplications = computed(() => {
  let list = applications.value
  if (selectedInternshipFilter.value) {
    list = list.filter((a) => String(a.internshipId) === String(selectedInternshipFilter.value))
  }
  if (searchStudent.value.trim()) {
    const q = searchStudent.value.toLowerCase().trim()
    list = list.filter(
      (a) =>
        (a.studentName || '').toLowerCase().includes(q) ||
        (a.studentEmail || '').toLowerCase().includes(q) ||
        (a.studentCourse || '').toLowerCase().includes(q)
    )
  }
  return list
})

function formatApplicationDate(createdAt: unknown): string {
  if (!createdAt) return '—'
  const d = (createdAt as { toDate?: () => Date })?.toDate?.() ?? new Date(String(createdAt))
  return d instanceof Date && !isNaN(d.getTime())
    ? d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
    : '—'
}

async function handleAcceptApplication(app: ApplicationRecord) {
  try {
    await updateApplicationStatus(app.id, 'accepted')
    await Swal.fire({ icon: 'success', title: 'Accepted', text: 'Application accepted.', confirmButtonColor: '#2563eb' })
  } catch (err) {
    await Swal.fire({ icon: 'error', title: 'Failed', text: err instanceof Error ? err.message : 'Could not update.' })
  }
}

async function handleDeclineApplication(app: ApplicationRecord) {
  try {
    await updateApplicationStatus(app.id, 'declined')
    await Swal.fire({ icon: 'success', title: 'Declined', text: 'Application declined.', confirmButtonColor: '#2563eb' })
  } catch (err) {
    await Swal.fire({ icon: 'error', title: 'Failed', text: err instanceof Error ? err.message : 'Could not update.' })
  }
}

async function handleAcceptContractRequest(contract: ContractRecord) {
  const result = await Swal.fire({
    icon: 'question',
    title: 'Accept contract request?',
    text: `Accept MOA request from ${contract.schoolName}?`,
    showCancelButton: true,
    confirmButtonText: 'Accept',
    confirmButtonColor: '#059669',
    cancelButtonText: 'Cancel',
  })
  if (!result.isConfirmed) return

  try {
    await acceptContract(contract.id)
    await Swal.fire({
      icon: 'success',
      title: 'Contract accepted',
      text: 'The contract is now active.',
      confirmButtonColor: '#2563eb',
    })
  } catch (err) {
    await Swal.fire({
      icon: 'error',
      title: 'Could not accept contract',
      text: err instanceof Error ? err.message : 'Please try again.',
      confirmButtonColor: '#2563eb',
    })
  }
}

async function handleRejectContractRequest(contract: ContractRecord) {
  const result = await Swal.fire({
    title: 'Reject contract request?',
    text: `Reject MOA request from ${contract.schoolName}?`,
    icon: 'warning',
    input: 'text',
    inputLabel: 'Reason (optional)',
    inputPlaceholder: 'Enter rejection reason',
    showCancelButton: true,
    confirmButtonText: 'Reject',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Cancel',
  })
  if (!result.isConfirmed) return

  try {
    await rejectContract(contract.id, (result.value as string | undefined)?.trim())
    await Swal.fire({
      icon: 'success',
      title: 'Contract rejected',
      text: 'The contract request was rejected.',
      confirmButtonColor: '#2563eb',
    })
  } catch (err) {
    await Swal.fire({
      icon: 'error',
      title: 'Could not reject contract',
      text: err instanceof Error ? err.message : 'Please try again.',
      confirmButtonColor: '#2563eb',
    })
  }
}

function displayContractStatus(status?: string) {
  const s = (status || 'pending').toLowerCase()
  if (s === 'active') return 'approved'
  return s
}

function displayContractStatusLabel(status?: string) {
  const s = displayContractStatus(status)
  return s.charAt(0).toUpperCase() + s.slice(1)
}

async function handleCancelContract(contract: ContractRecord) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Cancel contract?',
    input: 'textarea',
    inputLabel: 'Reason (optional)',
    inputPlaceholder: 'Add a short reason for cancellation...',
    showCancelButton: true,
    confirmButtonText: 'Cancel contract',
    cancelButtonText: 'Keep it',
    confirmButtonColor: '#dc2626',
  })

  if (!result.isConfirmed) return

  try {
    await cancelContract(contract.id, 'company', (result.value as string | undefined)?.trim())
    await Swal.fire({
      icon: 'success',
      title: 'Cancelled',
      text: 'The contract has been cancelled.',
      confirmButtonColor: '#2563eb',
    })
  } catch (err) {
    await Swal.fire({
      icon: 'error',
      title: 'Could not cancel contract',
      text: err instanceof Error ? err.message : 'Please try again.',
      confirmButtonColor: '#2563eb',
    })
  }
}

// TEMPORARY DATA: Sample journal activity for journals view - replace with real data from backend
const recentJournalActivity = ref([
  { type: 'completed' as const, title: 'Completed: Backend API Integration', description: 'Applied Python, Django, RESTful API principles. 8 hours.', status: 'Approved' as const, date: 'May 15, 2024' },
  { type: 'completed' as const, title: 'Completed: Frontend UI Refinement', description: 'Applied React.js, CSS Grid. 7.5 hours.', status: 'Approved' as const, date: 'May 14, 2024' },
  { type: 'pending' as const, title: 'Pending: Database Schema Design', description: 'To record: SQL, Data Modeling. 6 hours.', status: 'Pending' as const, date: 'May 16, 2024' },
  { type: 'completed' as const, title: 'Completed: Initial Platform Setup', description: 'Applied Git, Docker, DevOps basics. 8 hours.', status: 'Approved' as const, date: 'May 08, 2024' }
])

// TEMPORARY DATA: Journal calendar state - these are UI state variables for calendar navigation
const journalMonth = ref(new Date(2026, 0, 1)) // January 2026
const journalWeekStart = ref(new Date(2024, 4, 13)) // Monday May 13, 2024

// TEMPORARY DATA: Sample calendar activity map - replace with real data from backend
const calendarActivityMap: Record<string, string[]> = {
  '2026-1-1': ['blue'], '2026-1-2': ['green', 'blue'], '2026-1-3': ['blue'], '2026-1-6': ['blue'],
  '2026-1-7': ['blue'], '2026-1-8': ['blue', 'green'], '2026-1-9': ['blue'], '2026-1-13': ['blue'],
  '2026-1-14': ['blue'], '2026-1-15': ['green', 'orange'], '2026-1-16': ['blue', 'orange'], '2026-1-17': ['blue', 'orange'],
  '2024-5-14': ['blue'], '2024-5-15': ['green'], '2024-5-16': ['orange'], '2024-5-17': ['orange']
}

function getActivityDots(iso: string): string[] {
  return calendarActivityMap[iso] || []
}

function toIsoKey(d: Date): string {
  return `${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`
}

function toggleReceivedContractDetails(id: string) {
  expandedReceivedContractId.value = expandedReceivedContractId.value === id ? null : id
}

function prevJournalMonth() {
  const d = new Date(journalMonth.value)
  d.setMonth(d.getMonth() - 1)
  journalMonth.value = d
}

function nextJournalMonth() {
  const d = new Date(journalMonth.value)
  d.setMonth(d.getMonth() + 1)
  journalMonth.value = d
}

function prevJournalWeek() {
  const d = new Date(journalWeekStart.value)
  d.setDate(d.getDate() - 7)
  journalWeekStart.value = d
}

function nextJournalWeek() {
  const d = new Date(journalWeekStart.value)
  d.setDate(d.getDate() + 7)
  journalWeekStart.value = d
}

const journalMonthLabel = computed(() => {
  return journalMonth.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const journalWeekLabel = computed(() => {
  const d = journalWeekStart.value
  return `Week of ${d.toLocaleDateString('en-US', { month: 'long', day: 'numeric' })}`
})

const monthGrid = computed(() => {
  const d = new Date(journalMonth.value.getFullYear(), journalMonth.value.getMonth(), 1)
  const startDay = d.getDay()
  const begin = new Date(d)
  begin.setDate(begin.getDate() - startDay)
  const cells: { date: Date; isCurrentMonth: boolean }[] = []
  for (let i = 0; i < 42; i++) {
    const cellDate = new Date(begin)
    cellDate.setDate(cellDate.getDate() + i)
    cells.push({ date: cellDate, isCurrentMonth: cellDate.getMonth() === journalMonth.value.getMonth() })
  }
  return cells
})

const weekRow = computed(() => {
  const start = new Date(journalWeekStart.value)
  const cells: { date: Date }[] = []
  for (let i = 0; i < 7; i++) {
    const cellDate = new Date(start)
    cellDate.setDate(cellDate.getDate() + i)
    cells.push({ date: cellDate })
  }
  return cells
})

// TEMPORARY DATA: Add journal entry panel state - these are UI state variables
const showAddJournalPanel = ref(false)
const journalEntryDate = ref('17/01/2026')
const journalEntryTasks = ref('')
const journalEntryHours = ref('8')
const journalEntrySkills = ref('')

function openAddJournalPanel() {
  showAddJournalPanel.value = true
}

function closeAddJournalPanel() {
  showAddJournalPanel.value = false
}

function saveJournalDraft() {
  // TODO: persist draft
  console.log('Save journal draft', { date: journalEntryDate.value, tasks: journalEntryTasks.value, hours: journalEntryHours.value, skills: journalEntrySkills.value })
  closeAddJournalPanel()
}

function submitJournalForReview() {
  // TODO: submit for review
  console.log('Submit journal for review', { date: journalEntryDate.value, tasks: journalEntryTasks.value, hours: journalEntryHours.value, skills: journalEntrySkills.value })
  closeAddJournalPanel()
}

// Company profile data from auth store
const companyProfile = computed(() => {
  const p = (authStore.user?.profile || {}) as Record<string, unknown>
  const filled = [
    !!p.companyName,
    !!p.companyType,
    !!p.industryType,
    !!p.companyAddress,
    !!p.companyEmail,
    !!p.contactPersonName,
    !!p.contactPersonTitle,
    !!p.contactPersonEmail,
    !!p.companyContactNumber,
  ].filter(Boolean).length
  const profileCompletion = Math.min(100, Math.round((filled / 9) * 100))
  return {
    name: (p.companyName as string) || companyName.value,
    industry: (p.industryType as string) || '—',
    email: (p.companyEmail as string) || authStore.user?.email || '—',
    phone: (p.companyContactNumber as string) || '—',
    address: (p.companyAddress as string) || '—',
    website: (p.website as string) || '—',
    businessType: (p.companyType as string) || '—',
    founded: (p.founded as string) || '—',
    companySize: (p.companySize as string) || '—',
    primaryDepartments: (p.primaryDepartments as string) || '—',
    preferredBackground: (p.preferredBackground as string) || '—',
    internshipDuration: (p.internshipDuration as string) || '—',
    availableSlots: (p.availableSlots as string) || '—',
    description: (p.description as string) || 'Complete your profile to add a company description.',
    totalInternsHosted: (p.totalInternsHosted as number) || 0,
    averageDuration: (p.averageDuration as string) || '—',
    completionRate: (p.completionRate as string) || '—',
    studentSatisfaction: (p.studentSatisfaction as number) || 0,
    profileCompletion,
  }
})

function editProfile() {
  router.push('/profile')
}

// TEMPORARY DATA: Time tracking state - UI state variables
const timeTrackingStatus = ref<'All' | 'PRESENT' | 'LATE' | 'ONGOING' | 'INCOMPLETE'>('All')
const timeTrackingSearch = ref('')
const timeTrackingSelectedDate = ref(new Date())
const selectedTimeTrackingRecordId = ref<number | null>(null)

const timeTrackingDayLabel = computed(() => {
  return new Intl.DateTimeFormat('en-US', { weekday: 'long' }).format(timeTrackingSelectedDate.value)
})

const timeTrackingDateLabel = computed(() => {
  return new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric', year: 'numeric' }).format(timeTrackingSelectedDate.value)
})

type TimeLogStatus = 'PRESENT' | 'LATE' | 'ONGOING' | 'INCOMPLETE'

const TIME_LOG_DASH = '—'
const showAdjustTimeModal = ref(false)
const adjustTimeError = ref<string | null>(null)
const adjustTimeForm = ref<{ timeIn: string; timeOut: string; status: TimeLogStatus }>({
  timeIn: '',
  timeOut: '',
  status: 'PRESENT',
})

const adjustTimeClockOutDisabled = computed(() => {
  return adjustTimeForm.value.status === 'ONGOING' || adjustTimeForm.value.status === 'INCOMPLETE'
})

function minutesFromTimeInput(value: string) {
  const [hh, mm] = value.split(':')
  const hours = Number(hh)
  const minutes = Number(mm)
  if (!Number.isFinite(hours) || !Number.isFinite(minutes)) return null
  return hours * 60 + minutes
}

function computeDurationHours(timeIn: string, timeOut: string) {
  const start = minutesFromTimeInput(timeIn)
  const end = minutesFromTimeInput(timeOut)
  if (start == null || end == null) return null
  const diff = end - start
  if (diff < 0) return null
  return diff / 60
}

function parseDisplayTimeToInput(displayValue: string) {
  if (!displayValue) return ''
  if (displayValue.trim() === TIME_LOG_DASH) return ''
  const match = displayValue.trim().match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i)
  if (!match) return ''

  let hours = Number(match[1])
  const minutes = Number(match[2])
  const period = match[3].toUpperCase()

  if (period === 'AM') {
    if (hours === 12) hours = 0
  } else {
    if (hours !== 12) hours += 12
  }

  const hh = String(hours).padStart(2, '0')
  const mm = String(minutes).padStart(2, '0')
  return `${hh}:${mm}`
}

function formatInputTimeToDisplay(inputValue: string) {
  if (!inputValue) return TIME_LOG_DASH
  const [hh, mm] = inputValue.split(':')
  let hours = Number(hh)
  const minutes = Number(mm)
  if (!Number.isFinite(hours) || !Number.isFinite(minutes)) return TIME_LOG_DASH

  const period = hours >= 12 ? 'PM' : 'AM'
  hours = hours % 12
  if (hours === 0) hours = 12
  return `${hours}:${String(minutes).padStart(2, '0')} ${period}`
}

function statusToColor(status: TimeLogStatus) {
  switch (status) {
    case 'PRESENT':
      return 'present'
    case 'ONGOING':
      return 'ongoing'
    case 'LATE':
      return 'late'
    case 'INCOMPLETE':
      return 'incomplete'
    default:
      return 'present'
  }
}

const adjustTimeTotalPreview = computed(() => {
  if (adjustTimeForm.value.status === 'ONGOING') return 'Ongoing'
  if (adjustTimeForm.value.status === 'INCOMPLETE') return TIME_LOG_DASH
  if (!adjustTimeForm.value.timeIn || !adjustTimeForm.value.timeOut) return TIME_LOG_DASH
  const hours = computeDurationHours(adjustTimeForm.value.timeIn, adjustTimeForm.value.timeOut)
  if (hours == null) return TIME_LOG_DASH
  return `${hours.toFixed(2)} hrs`
})

function openAdjustTimeModal() {
  const record = selectedTimeTrackingRecord.value
  if (!record) return

  adjustTimeError.value = null
  adjustTimeForm.value = {
    timeIn: parseDisplayTimeToInput(record.timeIn),
    timeOut: parseDisplayTimeToInput(record.timeOut),
    status: record.status as TimeLogStatus,
  }

  // Keep clock-out empty for ongoing/incomplete to avoid confusion
  if (adjustTimeForm.value.status === 'ONGOING' || adjustTimeForm.value.status === 'INCOMPLETE') {
    adjustTimeForm.value.timeOut = ''
  }

  showAdjustTimeModal.value = true
}

function closeAdjustTimeModal() {
  showAdjustTimeModal.value = false
  adjustTimeError.value = null
}

function saveAdjustTime() {
  const record = selectedTimeTrackingRecord.value
  if (!record) return

  adjustTimeError.value = null

  if (!adjustTimeForm.value.timeIn) {
    adjustTimeError.value = 'Clock in is required.'
    return
  }

  const nextStatus = adjustTimeForm.value.status
  const nextTimeIn = formatInputTimeToDisplay(adjustTimeForm.value.timeIn)

  let nextTimeOut = TIME_LOG_DASH
  let nextTotal = TIME_LOG_DASH

  if (nextStatus === 'ONGOING') {
    nextTimeOut = TIME_LOG_DASH
    nextTotal = 'Ongoing'
  } else if (nextStatus === 'INCOMPLETE') {
    nextTimeOut = TIME_LOG_DASH
    nextTotal = TIME_LOG_DASH
  } else {
    if (!adjustTimeForm.value.timeOut) {
      adjustTimeError.value = 'Clock out is required for Present/Late.'
      return
    }

    const hours = computeDurationHours(adjustTimeForm.value.timeIn, adjustTimeForm.value.timeOut)
    if (hours == null) {
      adjustTimeError.value = 'Clock out must be after clock in.'
      return
    }

    nextTimeOut = formatInputTimeToDisplay(adjustTimeForm.value.timeOut)
    nextTotal = `${hours.toFixed(2)} hrs`
  }

  const target = timeTrackingRecords.value.find((r) => r.id === record.id)
  if (!target) return

  target.timeIn = nextTimeIn
  target.timeOut = nextTimeOut
  target.totalHours = nextTotal
  target.status = nextStatus
  target.statusColor = statusToColor(nextStatus)

  closeAdjustTimeModal()
  void Swal.fire({
    icon: 'success',
    title: 'Time log updated',
    text: `Saved changes for ${record.name}.`,
    timer: 1600,
    showConfirmButton: false,
  })
}

async function sendTimeReminder() {
  const record = selectedTimeTrackingRecord.value
  if (!record) return

  const result = await Swal.fire({
    icon: 'question',
    title: 'Send reminder?',
    text: `Send a time log reminder to ${record.name}?`,
    showCancelButton: true,
    confirmButtonText: 'Send',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2563eb',
  })

  if (!result.isConfirmed) return

  // TODO: wire to real notification/email/SMS service
  await Swal.fire({
    icon: 'success',
    title: 'Reminder sent',
    text: `Reminder sent to ${record.name}.`,
    timer: 1600,
    showConfirmButton: false,
  })
}

function handleAdjustTimeKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && showAdjustTimeModal.value) {
    closeAdjustTimeModal()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleAdjustTimeKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleAdjustTimeKeydown)
})

const timeTrackingRecords = ref([
  {
    id: 1,
    name: 'Sarah Chen',
    internId: 'ID: 2024-001',
    role: 'Software Intern',
    date: 'Oct 24, 2024',
    timeIn: '8:05 AM',
    timeOut: '5:00 PM',
    totalHours: '8.92 hrs',
    status: 'PRESENT',
    statusColor: 'present'
  },
  {
    id: 2,
    name: 'Miguel Santos',
    internId: 'ID: 2024-002',
    role: 'Data Intern',
    date: 'Oct 24, 2024',
    timeIn: '9:12 AM',
    timeOut: '—',
    totalHours: 'Ongoing',
    status: 'ONGOING',
    statusColor: 'ongoing'
  },
  {
    id: 3,
    name: 'Anna Reyes',
    internId: 'ID: 2024-003',
    role: 'UI/UX Intern',
    date: 'Oct 24, 2024',
    timeIn: '8:45 AM',
    timeOut: '5:15 PM',
    totalHours: '8.50 hrs',
    status: 'LATE',
    statusColor: 'late'
  },
  {
    id: 4,
    name: 'Carlos Torres',
    internId: 'ID: 2024-004',
    role: 'IT Support Intern',
    date: 'Oct 24, 2024',
    timeIn: '8:00 AM',
    timeOut: '—',
    totalHours: '—',
    status: 'INCOMPLETE',
    statusColor: 'incomplete'
  },
  {
    id: 5,
    name: 'Maria Garcia',
    internId: 'ID: 2024-005',
    role: 'Marketing Intern',
    date: 'Oct 24, 2024',
    timeIn: '7:55 AM',
    timeOut: '5:10 PM',
    totalHours: '9.25 hrs',
    status: 'PRESENT',
    statusColor: 'present'
  },
  {
    id: 6,
    name: 'James Rivera',
    internId: 'ID: 2024-006',
    role: 'QA Intern',
    date: 'Oct 24, 2024',
    timeIn: '8:30 AM',
    timeOut: '—',
    totalHours: 'Ongoing',
    status: 'ONGOING',
    statusColor: 'ongoing'
  },
  {
    id: 7,
    name: 'Lisa Mendoza',
    internId: 'ID: 2024-007',
    role: 'Finance Intern',
    date: 'Oct 24, 2024',
    timeIn: '9:20 AM',
    timeOut: '5:05 PM',
    totalHours: '7.75 hrs',
    status: 'LATE',
    statusColor: 'late'
  },
  {
    id: 8,
    name: 'Roberto Cruz',
    internId: 'ID: 2024-008',
    role: 'Business Analyst Intern',
    date: 'Oct 24, 2024',
    timeIn: '8:10 AM',
    timeOut: '4:58 PM',
    totalHours: '8.80 hrs',
    status: 'PRESENT',
    statusColor: 'present'
  },
  {
    id: 9,
    name: 'Patricia Lim',
    internId: 'ID: 2024-009',
    role: 'Operations Intern',
    date: 'Oct 24, 2024',
    timeIn: '8:02 AM',
    timeOut: '—',
    totalHours: 'Ongoing',
    status: 'ONGOING',
    statusColor: 'ongoing'
  },
  {
    id: 10,
    name: 'Daniel Flores',
    internId: 'ID: 2024-010',
    role: 'HR Intern',
    date: 'Oct 24, 2024',
    timeIn: '7:50 AM',
    timeOut: '5:20 PM',
    totalHours: '9.50 hrs',
    status: 'PRESENT',
    statusColor: 'present'
  }
])

function shiftTimeTrackingDate(days: number) {
  const next = new Date(timeTrackingSelectedDate.value)
  next.setDate(next.getDate() + days)
  timeTrackingSelectedDate.value = next
}

function selectTimeTrackingRecord(recordId: number) {
  selectedTimeTrackingRecordId.value = recordId
}

const selectedTimeTrackingRecord = computed(() => {
  if (selectedTimeTrackingRecordId.value == null) return null
  return timeTrackingRecords.value.find((r) => r.id === selectedTimeTrackingRecordId.value) ?? null
})

const filteredTimeTrackingRecords = computed(() => {
  const query = timeTrackingSearch.value.trim().toLowerCase()

  return timeTrackingRecords.value.filter((r) => {
    const matchesStatus = timeTrackingStatus.value === 'All' ? true : r.status === timeTrackingStatus.value
    if (!matchesStatus) return false

    if (!query) return true
    return (
      r.name.toLowerCase().includes(query) ||
      r.internId.toLowerCase().includes(query) ||
      r.role.toLowerCase().includes(query)
    )
  })
})

function getNameInitials(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]!.toUpperCase())
    .join('')
}

watch(
  timeTrackingRecords,
  (records) => {
    if (selectedTimeTrackingRecordId.value == null && records.length > 0) {
      selectedTimeTrackingRecordId.value = records[0]!.id
    }
  },
  { immediate: true }
)

// Evaluation view data
const evaluationMonth = ref(new Date(2026, 0, 1)) // January 2026
const evaluationSearch = ref('')
const evaluations = ref<EvaluationRecord[]>([])
let unsubCompanyEvaluations: (() => void) | null = null

const startOfWeek = computed(() => {
  const d = new Date()
  const day = d.getDay() // 0 (Sun) - 6 (Sat)
  const diff = d.getDate() - day
  return new Date(d.getFullYear(), d.getMonth(), diff)
})

const endOfWeek = computed(() => {
  const s = new Date(startOfWeek.value)
  s.setDate(s.getDate() + 7)
  return s
})

function evaluationDueDateAsDate(value: unknown) {
  const asTs = value as { toDate?: () => Date }
  if (asTs?.toDate) return asTs.toDate()
  const d = new Date(String(value))
  return d instanceof Date && !isNaN(d.getTime()) ? d : null
}

function isOverdue(dueDate: Date) {
  const today = new Date()
  const startToday = new Date(today.getFullYear(), today.getMonth(), today.getDate())
  return dueDate.getTime() < startToday.getTime()
}

function evaluationStatusColor(status: EvaluationStatus) {
  switch (status) {
    case 'COMPLETED':
      return 'completed'
    case 'OVERDUE':
      return 'overdue'
    case 'PENDING':
    default:
      return 'pending'
  }
}

function formatDueShort(due: Date) {
  const dd = String(due.getDate()).padStart(2, '0')
  const mm = String(due.getMonth() + 1).padStart(2, '0')
  const yy = String(due.getFullYear()).slice(-2)
  return `${dd}/${mm}/${yy}`
}

const evaluationRows = computed(() => {
  return evaluations.value.map((e) => {
    const due = evaluationDueDateAsDate(e.dueDate)
    const computedStatus: EvaluationStatus =
      e.status === 'COMPLETED'
        ? 'COMPLETED'
        : due && isOverdue(due)
          ? 'OVERDUE'
          : 'PENDING'

    const dueLabel = due ? formatDueShort(due) : '—'

    return {
      ...e,
      status: computedStatus,
      statusColor: evaluationStatusColor(computedStatus),
      dueLabel,
      studentLabel: e.studentName || '—',
    }
  })
})

const filteredEvaluationRows = computed(() => {
  const q = evaluationSearch.value.trim().toLowerCase()
  if (!q) return evaluationRows.value
  return evaluationRows.value.filter((e) => {
    return (
      (e.studentName || '').toLowerCase().includes(q) ||
      (e.supervisor || '').toLowerCase().includes(q) ||
      (e.type || '').toLowerCase().includes(q)
    )
  })
})

const evaluationsThisWeek = computed(() => {
  const s = startOfWeek.value.getTime()
  const end = endOfWeek.value.getTime()
  return evaluationRows.value.filter((e) => {
    if (e.status === 'COMPLETED') return false
    const due = evaluationDueDateAsDate(e.dueDate)
    if (!due) return false
    const t = due.getTime()
    return t >= s && t < end
  }).length
})

const pendingMyAction = computed(() => {
  return evaluationRows.value.filter((e) => e.status !== 'COMPLETED').length
})

const totalEvaluationsCompleted = computed(() => {
  return evaluationRows.value.filter((e) => e.status === 'COMPLETED').length
})
const showNewEvaluationModal = ref(false)

// New evaluation form data
const newEvaluationForm = ref({
  studentId: '',
  supervisor: '',
  evaluationType: '',
  dueDate: '2026-01-17'
})
const schedulingEvaluation = ref(false)

const acceptedInternOptions = computed(() => {
  const map = new Map<string, { id: string; name: string }>()
  for (const app of applications.value) {
    if (app.status !== 'accepted') continue
    const id = app.studentId
    if (!id) continue
    const name = app.studentName || app.studentEmail || id
    if (!map.has(id)) map.set(id, { id, name })
  }
  return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name))
})

const scheduleEvaluationDisabled = computed(() => {
  return (
    schedulingEvaluation.value ||
    !newEvaluationForm.value.studentId ||
    !newEvaluationForm.value.supervisor ||
    !newEvaluationForm.value.evaluationType ||
    !newEvaluationForm.value.dueDate
  )
})

const supervisorOptions = ref([
  'Mr. Smith',
  'Ms. Johnson',
  'Dr. Brown',
  'Prof. Davis',
  'Mr. Wilson'
])

const evaluationTypeOptions = ref([
  'Midterm',
  'Final',
  'Monthly',
  'Weekly',
  'Project-based'
])



// Calendar data for evaluation view
const evaluationCalendarData: Record<string, boolean> = {
  '2026-1-2': true,
  '2026-1-8': true,
  '2026-1-15': true,
  '2026-1-16': true
}

function hasEvaluationDot(date: Date): boolean {
  const key = `${date.getFullYear()}-${date.getMonth() + 1}-${date.getDate()}`
  return evaluationCalendarData[key] || false
}

function prevEvaluationMonth() {
  const d = new Date(evaluationMonth.value)
  d.setMonth(d.getMonth() - 1)
  evaluationMonth.value = d
}

function nextEvaluationMonth() {
  const d = new Date(evaluationMonth.value)
  d.setMonth(d.getMonth() + 1)
  evaluationMonth.value = d
}

const evaluationMonthLabel = computed(() => {
  return evaluationMonth.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const evaluationMonthGrid = computed(() => {
  const d = new Date(evaluationMonth.value.getFullYear(), evaluationMonth.value.getMonth(), 1)
  const startDay = d.getDay()
  const begin = new Date(d)
  begin.setDate(begin.getDate() - startDay)
  const cells: { date: Date; isCurrentMonth: boolean }[] = []
  for (let i = 0; i < 42; i++) {
    const cellDate = new Date(begin)
    cellDate.setDate(cellDate.getDate() + i)
    cells.push({ date: cellDate, isCurrentMonth: cellDate.getMonth() === evaluationMonth.value.getMonth() })
  }
  return cells
})

function setNewEvaluation() {
  showNewEvaluationModal.value = true
}

function closeNewEvaluationModal() {
  showNewEvaluationModal.value = false
  // Reset form
  newEvaluationForm.value = {
    studentId: '',
    supervisor: '',
    evaluationType: '',
    dueDate: '2026-01-17'
  }
}

async function scheduleEvaluation() {
  const companyId = authStore.user?.uid
  if (!companyId) return

  if (scheduleEvaluationDisabled.value) {
    await Swal.fire({
      icon: 'warning',
      title: 'Missing info',
      text: 'Please select a student, supervisor, type, and due date.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  const selected = acceptedInternOptions.value.find((s) => s.id === newEvaluationForm.value.studentId)
  if (!selected) {
    await Swal.fire({
      icon: 'warning',
      title: 'Student not found',
      text: 'Please re-select a student.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  const due = new Date(`${newEvaluationForm.value.dueDate}T00:00:00`)
  if (isNaN(due.getTime())) {
    await Swal.fire({
      icon: 'warning',
      title: 'Invalid date',
      text: 'Please choose a valid due date.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  try {
    schedulingEvaluation.value = true
    await createEvaluation({
      companyId,
      studentId: selected.id,
      studentName: selected.name,
      supervisor: newEvaluationForm.value.supervisor,
      type: newEvaluationForm.value.evaluationType,
      dueDate: due,
      status: 'PENDING',
    })
    closeNewEvaluationModal()
    await Swal.fire({
      icon: 'success',
      title: 'Scheduled',
      text: `Evaluation scheduled for ${selected.name}.`,
      confirmButtonColor: '#2563eb',
    })
  } catch (err) {
    await Swal.fire({
      icon: 'error',
      title: 'Failed to schedule',
      text: err instanceof Error ? err.message : 'Could not schedule evaluation.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    schedulingEvaluation.value = false
  }
}

function startEvaluation(evaluationId: number) {
  console.log('Start evaluation for:', evaluationId)
}

function viewPending() {
  console.log('View pending evaluations')
}

// TEMPORARY DATA: Dashboard settings state - these are UI state variables for settings toggles
const settingsEmailNotif = ref(true)
const settingsNewAppAlerts = ref(true)
const settingsInternReminders = ref(true)
const settingsDailySummary = ref(false)
const settingsDefaultDashboardView = ref('Applications')
const settingsShowCompletionPct = ref(false)
const settingsAutoRefresh = ref(false)
const settingsDefaultPostDuration = ref('60 days')
const settingsApplicationAutoClose = ref('After 10 applications')
const settingsTwoFactorEnabled = ref(false)

function saveSettings() {
  console.log('Settings saved')
  // Add save logic here
  currentView.value = 'dashboard'
}
</script>

<template>
  <div class="dashboard">
    <!-- Sidebar Component -->
    <Sidebar @nav-changed="handleNavChange" />

    <!-- Main Content Area -->
    <div class="main-wrapper">
      <!-- Dashboard View -->
      <div v-if="currentView === 'dashboard'">
        <!-- Top Header -->
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-dashboard.png" alt="Dashboard" class="header-icon-img" />
            <h1>Dashboard</h1>
          </div>
          <div class="header-right">
            <div class="notification-wrapper">
              <BellIcon class="notification-icon-bell" />
            </div>
            
            <div class="avatar" @click="handleNavChange('profile')" title="View Profile">{{ userInitials }}</div>
          </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
        <!-- Summary Cards -->
        <div class="summary-cards">
          <div class="summary-card">
            <div class="card-icon ongoing">
              <UserIcon class="icon-size" />
            </div>
            <div class="card-number">17</div>
            <div class="card-label">Ongoing</div>
          </div>
          <div class="summary-card">
            <div class="card-icon near">
              <ClockIcon class="icon-size" />
            </div>
            <div class="card-number">2</div>
            <div class="card-label">Near Completion</div>
          </div>
          <div class="summary-card">
            <div class="card-icon completed">
              <CheckIcon class="icon-size" />
            </div>
            <div class="card-number">9</div>
            <div class="card-label">Completed</div>
          </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
          <!-- Left Column -->
          <div class="left-column">
            <!-- Recent Activities -->
            <section class="content-section">
              <h2>Recent Activities</h2>
              <div class="activities-list">
                <div
                  v-for="activity in recentActivities"
                  :key="activity.id"
                  class="activity-item"
                >
                  <span class="activity-icon">
                    <DocumentIcon v-if="activity.icon === 'document'" class="icon-size" />
                    <ClockIcon v-else-if="activity.icon === 'clock'" class="icon-size" />
                    <CheckIcon v-else-if="activity.icon === 'check'" class="icon-size" />
                  </span>
                  <div class="activity-content">
                    <p class="activity-text">{{ activity.text }}</p>
                    <span class="activity-time">{{ activity.time }}</span>
                  </div>
                </div>
              </div>
            </section>

            <!-- Internship Progress Overview -->
            <section class="content-section">
              <div class="section-header">
                <div>
                  <h2>Internship Progress Overview</h2>
                  <p class="section-subtitle">28 Total Active Internships</p>
                </div>
              </div>

              <!-- Progress Bar Chart -->
              <div class="progress-chart">
                <div class="chart-bar">
                  <div class="bar-segment ongoing" style="width: 60.7%">
                    <span>17 Ongoing</span>
                  </div>
                  <div class="bar-segment completed" style="width: 32.1%">
                    <span>9 Completed</span>
                  </div>
                  <div class="bar-segment near" style="width: 7.2%">
                    <span>2 Near</span>
                  </div>
                </div>
              </div>

              <!-- Near Completion Interns -->
              <div class="near-completion">
                <h3>Near Completion Interns</h3>
                <div
                  v-for="intern in nearCompletion"
                  :key="intern.name"
                  class="intern-progress"
                >
                  <div class="intern-header">
                    <span class="intern-name">{{ intern.name }}</span>
                    <span class="intern-date">{{ intern.date }}</span>
                  </div>
                  <div class="progress-bar-wrapper">
                    <div
                      class="progress-bar near"
                      :style="{ width: intern.progress + '%' }"
                    ></div>
                    <span class="progress-text">{{ intern.progress }}%</span>
                  </div>
                </div>
              </div>

              <a href="#" class="view-all-link">
                View All Active Interns
                <span>→</span>
              </a>
            </section>
          </div>

          <!-- Right Column -->
          <div class="right-column">
            <!-- Recent Notifications -->
            <section class="content-section">
              <div class="section-header">
              </div>

              <div class="notifications-list">
                <div
                  v-for="notification in dashboardNotifications"
                  :key="notification.id"
                  class="notification-item"
                  :class="{ unread: notification.unread }"
                >
                  <span class="notification-icon">
                    <DocumentIcon v-if="notification.icon === 'document'" class="icon-size" />
                    <CalendarIcon v-else-if="notification.icon === 'calendar'" class="icon-size" />
                    <ClockIcon v-else-if="notification.icon === 'clock'" class="icon-size" />
                  </span>
                  <div class="notification-content">
                    <p class="notification-text">{{ notification.text }}</p>
                    <span class="notification-time">{{ notification.time }}</span>
                    <a href="#" class="notification-action">{{ notification.action }}</a>
                  </div>
                </div>
              </div>
            </section>

            <!-- Quick Actions Card -->
            <section class="content-section quick-actions-card">
              <h2>Quick Actions</h2>
              <div class="action-buttons-grid">
                <button class="action-btn-card" @click="handleNavChange('internships')">
                  <div class="action-icon-wrapper">
                    <PlusIcon class="action-icon-svg" />
                  </div>
                  <span class="action-text">Post New Internship</span>
                </button>
                <button class="action-btn-card">
                  <div class="action-icon-wrapper">
                    <ClipboardDocumentListIcon class="action-icon-svg" />
                  </div>
                  <span class="action-text">Review Applications</span>
                </button>
                <button class="action-btn-card">
                  <div class="action-icon-wrapper">
                    <ChartBarSquareIcon class="action-icon-svg" />
                  </div>
                  <span class="action-text">Generate Report</span>
                </button>
                <button class="action-btn-card">
                  <div class="action-icon-wrapper">
                    <AcademicCapIcon class="action-icon-svg" />
                  </div>
                  <span class="action-text">View Interns</span>
                </button>
              </div>
            </section>
          </div>
        </div>
      </main>
      </div> <!-- End Dashboard View -->

      <!-- Internships View -->
      <div v-if="currentView === 'internships'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-internship.png" alt="Internship Posting" class="header-icon-img" />
            <h1>Internship Posting</h1>
          </div>
          <div class="header-right">
            <div class="notification-wrapper">
              <BellIcon class="notification-icon-bell" />
            </div>
            <div class="avatar" @click="handleNavChange('profile')" title="View Profile">{{ userInitials }}</div>
          </div>
        </header>
        <main class="main-content">
          <div class="internships-layout">
            <!-- Internships Table -->
            <div class="internships-column">
              <div class="internships-header">
                <h2 class="internships-title">Your Posted Internships</h2>
                <div class="search-section">
                  <div class="search-wrapper">
                    <span class="search-icon">
                      <MagnifyingGlassIcon class="icon-size" />
                    </span>
                    <input
                      type="text"
                      v-model="searchQuery"
                      placeholder="Search your internship postings..."
                      class="search-input"
                    />
                  </div>
                  <button class="btn-post-internship" @click="openCreateForm">Post New Internship</button>
                </div>
              </div>

              <div class="internships-table-card">
                <div class="internships-table-wrap">
                  <table class="internships-table">
                    <colgroup>
                      <col class="col-internship" />
                      <col class="col-location" />
                      <col class="col-duration" />
                      <col class="col-slots" />
                      <col class="col-status" />
                      <col class="col-posted" />
                      <col class="col-actions" />
                    </colgroup>
                    <thead>
                      <tr>
                        <th>Internship</th>
                        <th>Location</th>
                        <th>Duration</th>
                        <th>Slots</th>
                        <th>Status</th>
                        <th>Posted</th>
                        <th class="internships-actions-col">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="internship in internshipsList" :key="internship.id" class="internships-row">
                        <td class="internship-main-cell">
                          <div class="internship-main-title">{{ internship.title }}</div>
                          <div class="internship-main-sub">{{ internship.description || '—' }}</div>
                        </td>
                        <td class="internship-cell">{{ internship.location }}</td>
                        <td class="internship-cell nowrap">{{ internship.duration }}</td>
                        <td class="internship-cell nowrap">{{ internship.slotsAvailable }}</td>
                        <td class="internship-cell">
                          <span class="internship-status" :class="internship.status">
                            {{ internship.status.charAt(0).toUpperCase() + internship.status.slice(1) }}
                          </span>
                        </td>
                        <td class="internship-cell nowrap">{{ formatApplicationDate(internship.createdAt) }}</td>
                        <td class="internship-actions-cell">
                          <div class="internship-action-buttons">
                            <button type="button" class="internship-action-btn view" @click="openInternshipDetails(internship)">
                              <EyeIcon class="internship-action-icon" />
                              <span class="internship-action-text">View</span>
                            </button>
                            <button type="button" class="internship-action-btn edit" @click="editInternship(internship.id)">
                              <PencilIcon class="internship-action-icon" />
                              <span class="internship-action-text">Edit</span>
                            </button>
                            <button type="button" class="internship-action-btn delete" @click="deleteInternshipPosting(internship.id)">
                              <TrashIcon class="internship-action-icon" />
                              <span class="internship-action-text">Delete</span>
                            </button>
                          </div>
                        </td>
                      </tr>
                      <tr v-if="internshipsList.length === 0">
                        <td colspan="7" class="internships-empty">No internship postings found.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>

      <!-- Applications View -->
      <div v-if="currentView === 'applications'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-application.png" alt="Student Applications" class="header-icon-img" />
            <h1>Student Applications</h1>
          </div>
          <div class="header-right">
            <div class="notification-wrapper">
              <BellIcon class="notification-icon-bell" />
            </div>
            <div class="avatar" @click="handleNavChange('profile')" title="View Profile">{{ userInitials }}</div>
          </div>
        </header>
        <main class="main-content">
          <div class="applications-layout">
            <!-- Applications Dashboard Header -->
            <div class="applications-dashboard-header">
              <div class="dashboard-title-section">
                <h2 class="dashboard-title">Application Management</h2>
                <p class="dashboard-subtitle">Review and manage student applications for your internship positions</p>
              </div>
              
              <!-- Statistics Cards -->
              <div class="stats-grid">
                <div class="stat-card-modern">
                  <div class="stat-icon-wrapper total">
                    <ClipboardDocumentListIcon class="stat-icon" />
                  </div>
                  <div class="stat-content">
                    <div class="stat-number">{{ filteredApplications.length }}</div>
                    <div class="stat-label">Total Applications</div>
                  </div>
                </div>
                
                <div class="stat-card-modern">
                  <div class="stat-icon-wrapper pending">
                    <ClockIcon class="stat-icon" />
                  </div>
                  <div class="stat-content">
                    <div class="stat-number">{{ filteredApplications.filter(app => app.status === 'pending').length }}</div>
                    <div class="stat-label">Pending Review</div>
                  </div>
                </div>
                
                <div class="stat-card-modern">
                  <div class="stat-icon-wrapper accepted">
                    <CheckIcon class="stat-icon" />
                  </div>
                  <div class="stat-content">
                    <div class="stat-number">{{ filteredApplications.filter(app => app.status === 'accepted').length }}</div>
                    <div class="stat-label">Accepted</div>
                  </div>
                </div>
                
                <div class="stat-card-modern">
                  <div class="stat-icon-wrapper declined">
                    <XMarkIcon class="stat-icon" />
                  </div>
                  <div class="stat-content">
                    <div class="stat-number">{{ filteredApplications.filter(app => app.status === 'declined').length }}</div>
                    <div class="stat-label">Declined</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Filters and Search -->
            <div class="applications-controls">
              <div class="controls-left">
                <div class="filter-dropdown">
                  <select v-model="selectedInternshipFilter" class="modern-select">
                    <option value="">All Internships</option>
                    <option v-for="i in internshipsList" :key="i.id" :value="i.id">{{ i.title }}</option>
                  </select>
                </div>
              </div>
              
              <div class="controls-right">
                <div class="search-wrapper">
                  <MagnifyingGlassIcon class="search-icon-modern" />
                  <input
                    type="text"
                    v-model="searchStudent"
                    placeholder="Search students..."
                    class="search-input-modern"
                  />
                </div>
              </div>
            </div>

            <!-- Applications Grid -->
            <div class="applications-grid-modern">
              <div v-if="filteredApplications.length === 0" class="empty-state-modern">
                <div class="empty-icon-modern">
                  <ClipboardDocumentListIcon class="empty-icon-svg-modern" />
                </div>
                <h3 class="empty-title-modern">No Applications Found</h3>
                <p class="empty-description-modern">Student applications will appear here when they apply to your internships.</p>
              </div>

              <div
                v-for="app in filteredApplications"
                :key="app.id"
                class="application-card-modern"
              >
                <!-- Card Header -->
                <div class="card-header-modern">
                  <div class="student-profile">
                    <div class="student-avatar-modern">
                      {{ (app.studentName || app.studentEmail || 'S').charAt(0).toUpperCase() }}
                    </div>
                    <div class="student-info-modern">
                      <h3 class="student-name-modern">{{ app.studentName || app.studentEmail || 'Student' }}</h3>
                      <p class="student-course-modern">{{ app.studentCourse || 'Course not specified' }}</p>
                    </div>
                  </div>
                  <div class="status-wrapper">
                    <span class="status-badge-modern" :class="(app.status || 'pending').toLowerCase()">
                      {{ (app.status || 'pending').charAt(0).toUpperCase() + (app.status || 'pending').slice(1) }}
                    </span>
                  </div>
                </div>

                <!-- Application Details -->
                <div class="card-body-modern">
                  <div class="application-info">
                    <div class="info-row">
                      <span class="info-label">Position Applied:</span>
                      <span class="info-value">{{ app.internshipTitle || 'Unknown Position' }}</span>
                    </div>
                    <div class="info-row">
                      <span class="info-label">Application Date:</span>
                      <span class="info-value">{{ formatApplicationDate(app.createdAt) }}</span>
                    </div>
                    <div class="info-row">
                      <span class="info-label">Skills Match:</span>
                      <div class="skills-match-modern">
                        <div class="match-progress">
                          <div class="match-bar-modern" :style="{ width: '75%' }"></div>
                        </div>
                        <span class="match-text">75% Match</span>
                      </div>
                    </div>
                  </div>

                  <!-- Documents Section -->
                  <div class="documents-section" v-if="app.resume">
                    <div class="document-item-modern">
                      <DocumentIcon class="document-icon-modern" />
                      <span class="document-name-modern">Resume.pdf</span>
                      <button class="download-btn-modern" title="Download Resume">
                        <ArrowDownTrayIcon class="download-icon-modern" />
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Card Actions -->
                <div class="card-actions-modern">
                  <button class="action-btn-secondary" title="View Full Profile">
                    <EyeIcon class="action-icon-modern" />
                    View Profile
                  </button>
                  <button 
                    class="action-btn-decline"
                    :class="{ disabled: app.status === 'accepted' }"
                    title="Decline Application"
                    @click="handleDeclineApplication(app)"
                  >
                    <XMarkIcon class="action-icon-modern" />
                    Decline
                  </button>
                  <button
                    class="action-btn-accept"
                    :class="{ disabled: app.status === 'accepted' }"
                    title="Accept Application"
                    @click="handleAcceptApplication(app)"
                  >
                    <CheckIcon class="action-icon-modern" />
                    Accept
                  </button>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>

      <!-- Contracts View (Company-School) -->
      <div v-if="currentView === 'contracts'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-docs.png" alt="Contracts" class="header-icon-img" />
            <h1>School Contracts</h1>
          </div>
          <div class="header-right">
            <div class="avatar" @click="handleNavChange('profile')" title="View Profile">{{ userInitials }}</div>
          </div>
        </header>
        <main class="main-content">
          <div class="applications-container">
            <h2 class="applications-title">Contract Requests From Schools</h2>
            <div v-if="companyContracts.length === 0" class="empty-applications">
              No contract requests yet from schools.
            </div>
            <div v-else class="contracts-table-wrap">
              <table class="contracts-table">
                <thead>
                  <tr>
                    <th>Ref</th>
                    <th>School</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="c in companyContracts" :key="c.id">
                    <tr>
                      <td class="mono">{{ c.moaReferenceNo || '-' }}</td>
                      <td>{{ c.schoolName }}</td>
                      <td>{{ c.subject || '-' }}</td>
                      <td>
                        <span class="status-badge-app" :class="displayContractStatus(c.status)">{{ displayContractStatusLabel(c.status) }}</span>
                      </td>
                      <td>{{ formatApplicationDate(c.createdAt) }}</td>
                      <td class="actions-cell">
                        <button type="button" class="table-btn" @click="toggleReceivedContractDetails(c.id)">
                          {{ expandedReceivedContractId === c.id ? 'Hide' : 'View' }}
                        </button>
                        <button
                          v-if="(c.status || 'pending') === 'pending'"
                          type="button"
                          class="table-btn ok"
                          @click="handleAcceptContractRequest(c)"
                        >
                          Accept
                        </button>
                        <button
                          v-if="(c.status || 'pending') === 'pending'"
                          type="button"
                          class="table-btn danger"
                          @click="handleRejectContractRequest(c)"
                        >
                          Reject
                        </button>
                        <button
                          v-if="(c.status || 'pending') === 'active'"
                          type="button"
                          class="table-btn danger"
                          @click="handleCancelContract(c)"
                        >
                          Cancel
                        </button>
                      </td>
                    </tr>
                    <tr v-if="expandedReceivedContractId === c.id" class="details-row">
                      <td colspan="6">
                        <div class="details-panel">
                          <div class="details-grid">
                            <div><strong>Type:</strong> {{ c.contractType || 'MOA' }}</div>
                            <div><strong>Duration:</strong> {{ c.startDate && c.endDate ? `${c.startDate} - ${c.endDate}` : (c.startDate || c.endDate || '-') }}</div>
                            <div><strong>Slots:</strong> {{ c.internshipSlots || '-' }}</div>
                            <div><strong>Programs:</strong> {{ c.studentPrograms || '-' }}</div>
                          </div>
                          <div v-if="c.courseAllocations && c.courseAllocations.length" class="contract-section">
                            <strong>Course Slots:</strong>
                            <p v-for="item in c.courseAllocations" :key="`${c.id}-${item.course}`">{{ item.course }}: {{ item.slots }}</p>
                          </div>
                          <div v-if="c.purpose" class="contract-section">
                            <strong>Purpose:</strong>
                            <p>{{ c.purpose }}</p>
                          </div>
                          <div v-if="c.notes" class="contract-section">
                            <strong>Notes:</strong>
                            <p>{{ c.notes }}</p>
                          </div>
                          <div v-if="c.rejectedReason && (c.status || 'pending') === 'rejected'" class="contract-section">
                            <strong>Rejected Reason:</strong>
                            <p>{{ c.rejectedReason }}</p>
                          </div>
                          <div v-if="(c.status || 'pending') === 'cancelled'" class="contract-section">
                            <strong>Cancelled:</strong>
                            <p>{{ c.cancelledReason || 'No reason provided.' }}</p>
                          </div>
                          <div v-if="c.attachments && c.attachments.length" class="contract-section">
                            <strong>Files:</strong>
                            <p v-for="a in c.attachments" :key="`${c.id}-${a.name}`">{{ a.name }}</p>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </main>
      </div>
      <!-- Journals View (Journal Overview) -->
      <div v-if="currentView === 'journals'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-journal.png" alt="Journal Overview" class="header-icon-img" />
            <h1>Journal Overview</h1>
          </div>
          <div class="header-right">
            <button class="btn-add-journal" @click="openAddJournalPanel"> Add New Journal Entry</button>
            <BellIcon class="notification-icon-bell" />
            <div class="avatar" @click="handleNavChange('profile')" title="View Profile">{{ userInitials }}</div>
          </div>
        </header>
        <main class="main-content">
          <!-- Calendars row -->
          <div class="journals-calendars">
            <!-- Month view -->
            <div class="journal-card journal-calendar-month">
              <div class="journal-card-header">
                <h3 class="journal-card-title">{{ journalMonthLabel }}</h3>
                <div class="calendar-nav">
                  <button type="button" class="cal-nav-btn" @click="prevJournalMonth" aria-label="Previous month">‹</button>
                  <button type="button" class="cal-nav-btn" @click="nextJournalMonth" aria-label="Next month">›</button>
                </div>
              </div>
              <div class="calendar-grid month-grid">
                <div v-for="dow in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="dow" class="cal-head">{{ dow }}</div>
                <div
                  v-for="(cell, i) in monthGrid"
                  :key="i"
                  class="cal-cell"
                  :class="{ 'other-month': !cell.isCurrentMonth }"
                >
                  <span class="cal-num">{{ cell.date.getDate() }}</span>
                  <div class="cal-dots">
                    <span
                      v-for="(c, j) in getActivityDots(toIsoKey(cell.date))"
                      :key="j"
                      class="cal-dot"
                      :class="c"
                    ></span>
                  </div>
                </div>
              </div>
            </div>
            <!-- Week view -->
            <div class="journal-card journal-calendar-week">
              <div class="journal-card-header">
                <h3 class="journal-card-title">{{ journalWeekLabel }}</h3>
                <div class="calendar-nav">
                  <button type="button" class="cal-nav-btn" @click="prevJournalWeek" aria-label="Previous week">‹</button>
                  <button type="button" class="cal-nav-btn" @click="nextJournalWeek" aria-label="Next week">›</button>
                </div>
              </div>
              <div class="calendar-grid week-grid">
                <div v-for="d in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']" :key="d" class="cal-head">{{ d }}</div>
                <div
                  v-for="(cell, i) in weekRow"
                  :key="i"
                  class="cal-cell week-cell"
                >
                  <span class="cal-num">{{ cell.date.getDate() }}</span>
                  <div class="cal-dots">
                    <span
                      v-for="(c, j) in getActivityDots(toIsoKey(cell.date))"
                      :key="j"
                      class="cal-dot"
                      :class="c"
                    ></span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Journal Activity -->
          <div class="journal-card journal-activity-card">
            <h3 class="journal-activity-title">Recent Journal Activity</h3>
            <div class="journal-activity-list">
              <div
                v-for="(entry, idx) in recentJournalActivity"
                :key="idx"
                class="journal-activity-item"
              >
                <span class="journal-entry-icon" :class="entry.type">
                  <DocumentIcon v-if="entry.type === 'completed'" class="icon-size" />
                  <ExclamationTriangleIcon v-else class="icon-size" />
                </span>
                <div class="journal-entry-body">
                  <div class="journal-entry-title">{{ entry.title }}</div>
                  <div class="journal-entry-desc">{{ entry.description }}</div>
                  <div v-if="entry.status === 'Approved'" class="journal-entry-status approved">
                    <CheckIcon class="icon-size-sm" /> Approved
                  </div>
                  <div v-else class="journal-entry-status pending">Pending</div>
                </div>
                <div class="journal-entry-date">{{ entry.date }}</div>
              </div>
            </div>
          </div>
        </main>

        <!-- Add Journal Entry panel (slide-in from right) -->
        <Teleport to="body">
          <div v-if="showAddJournalPanel" class="add-journal-layer">
            <div class="add-journal-backdrop" @click="closeAddJournalPanel" aria-hidden="true"></div>
            <div class="add-journal-panel" role="dialog" aria-labelledby="add-journal-title">
              <div class="add-journal-header">
                <h2 id="add-journal-title" class="add-journal-title">Add Journal Entry</h2>
                <button type="button" class="add-journal-close" @click="closeAddJournalPanel" aria-label="Close"><XMarkIcon class="icon-size" /></button>
              </div>
              <div class="add-journal-body">
                <div class="add-journal-field">
                  <label class="add-journal-label">Date</label>
                  <div class="add-journal-input-wrap">
                    <input v-model="journalEntryDate" type="text" class="add-journal-input" placeholder="dd/mm/yyyy" />
                    <span class="add-journal-input-icon">
                      <CalendarIcon class="icon-size" />
                    </span>
                  </div>
                </div>
                <div class="add-journal-field">
                  <label class="add-journal-label">Tasks Performed</label>
                  <textarea
                    v-model="journalEntryTasks"
                    class="add-journal-textarea"
                    placeholder="Describe the tasks you performed today. Be specific about your contributions and achievements."
                    rows="4"
                  ></textarea>
                </div>
                <div class="add-journal-field">
                  <label class="add-journal-label">Hours Rendered</label>
                  <input v-model="journalEntryHours" type="text" class="add-journal-input" placeholder="e.g. 8" />
                </div>
                <div class="add-journal-field">
                  <label class="add-journal-label">Skills Applied or Learned</label>
                  <textarea v-model="journalEntrySkills" class="add-journal-textarea" rows="4" placeholder="List skills used or learned"></textarea>
                </div>
              </div>
              <div class="add-journal-actions">
                <button type="button" class="add-journal-btn add-journal-btn-draft" @click="saveJournalDraft" disabled title="Coming soon">Save Draft</button>
                <button type="button" class="add-journal-btn add-journal-btn-submit" @click="submitJournalForReview" disabled title="Coming soon">Submit for Review</button>
              </div>
            </div>
          </div>
        </Teleport>
      </div>

      <!-- Reports View -->
      <div v-if="currentView === 'reports'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-reports.png" alt="Report & Certification" class="header-icon-img" />
            <h1>Report & Certification</h1>
          </div>
          <div class="header-right">
            <div class="notification-wrapper">
              <BellIcon class="notification-icon-bell" />
            </div>
            <div class="avatar" @click="handleNavChange('profile')" title="View Profile">{{ userInitials }}</div>
          </div>
        </header>
        <main class="main-content">
          <!-- Summary Cards -->
          <div class="summary-cards-reports">
            <div class="summary-card-report-combined">
              <div class="metric-item">
                <div class="card-number">600</div>
                <div class="card-label">Hours</div>
              </div>
              <div class="metric-item">
                <div class="card-number">100%</div>
                <div class="card-label">Attendance</div>
              </div>
              <div class="metric-item">
                <div class="card-number">24</div>
                <div class="card-label">Journals</div>
              </div>
              <div class="metric-item">
                <div class="card-number">4.8/5</div>
                <div class="card-label">Rating</div>
              </div>
            </div>
          </div>

          <div class="reports-layout">
            <!-- Left Column -->
            <div class="reports-left-column">
              <!-- Attendance & Hours Report -->
              <div class="report-card">
                <div class="report-card-header">
                  <div class="report-icon">
                    <CalendarIcon class="icon-size" />
                  </div>
                  <div>
                    <h3 class="report-title">Attendance & Hours Report</h3>
                    <p class="report-subtitle">120 days | 600 hours | 0 absences</p>
                  </div>
                </div>
                <div class="attendance-table">
                  <table>
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>In</th>
                        <th>Out</th>
                        <th>Hours</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="record in attendanceRecords" :key="record.date">
                        <td>{{ record.date }}</td>
                        <td>{{ record.in }}</td>
                        <td>{{ record.out }}</td>
                        <td>{{ record.hours }}</td>
                        <td><span class="status-badge present">{{ record.status }}</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="report-card-actions">
                  <button class="btn-outline">Preview</button>
                  <button class="btn-primary">Generate PDF</button>
                </div>
                <div class="report-card-footer">
                  Last generated Nov 28, 2024 at 2:30 PM
                </div>
              </div>

              <!-- Weekly Journal Compilation -->
              <div class="report-card">
                <div class="report-card-header">
                  <div class="report-icon">
                    <DocumentIcon class="icon-size" />
                  </div>
                  <div>
                    <h3 class="report-title">Weekly Journal Compilation</h3>
                    <p class="report-subtitle">24 weeks | All submitted | 100% approved</p>
                  </div>
                </div>
                <div class="journal-list">
                  <div
                    v-for="journal in journalEntries"
                    :key="journal.week"
                    class="journal-item"
                  >
                    <div class="journal-week">Week {{ journal.week }}</div>
                    <div class="journal-content">
                      <div class="journal-title">{{ journal.title }}</div>
                      <div class="journal-date">{{ journal.date }}</div>
                    </div>
                  </div>
                </div>
                <div class="report-card-actions">
                  <button class="btn-outline">View All</button>
                  <button class="btn-primary">Generate Compilation PDF</button>
                </div>
                <div class="report-card-footer">
                  Last updated: Nov 25, 2024 at 11:45 AM
                </div>
              </div>

              <!-- Performance Evaluation Summary -->
              <div class="report-card">
                <div class="report-card-header">
                  <div class="report-icon">
                    <StarIcon class="icon-size" />
                  </div>
                  <div>
                    <h3 class="report-title">Performance Evaluation Summary</h3>
                    <p class="report-subtitle">Overall: 4.8/5 | Excellent rating</p>
                  </div>
                </div>
                <div class="performance-list">
                  <div
                    v-for="metric in performanceMetrics"
                    :key="metric.skill"
                    class="performance-item"
                  >
                    <div class="performance-label">{{ metric.skill }}</div>
                    <div class="performance-bar-wrapper">
                      <div
                        class="performance-bar"
                        :style="{ width: (metric.rating / 5) * 100 + '%' }"
                      ></div>
                    </div>
                    <div class="performance-rating">{{ metric.rating }}</div>
                  </div>
                </div>
                <div class="report-card-actions">
                  <button class="btn-outline">Full Evaluation</button>
                  <button class="btn-primary">Download PDF</button>
                </div>
                <div class="report-card-footer">
                  Evaluated by: John Martinez, Nov 26, 2024
                </div>
              </div>

              <!-- All-in-One Report Package -->
              <div class="report-card">
                <div class="report-card-header">
                  <div class="report-icon"><FolderIcon class="icon-size" /></div>
                  <div>
                    <h3 class="report-title">All-in-One Report Package</h3>
                    <p class="report-subtitle">Complete internship documentation including attendance, journals, and evaluation</p>
                  </div>
                </div>
                <div class="report-card-actions">
                  <button class="btn-primary">Generate Complete Package</button>
                </div>
                <div class="report-card-footer">
                  Estimated file size: ~15 MB PDF
                </div>
              </div>
            </div>

            <!-- Right Column -->
            <div class="reports-right-column">
              <!-- Official Certificate -->
              <div class="certificate-section">
                <h3 class="certificate-title">Official Certificate</h3>
                <div class="status-pill completed">COMPLETED</div>
                <div class="certificate-preview">
                  <div class="certificate-content">
                    <div class="certificate-icon"><AcademicCapIcon style="width: 48px; height: 48px;" /></div>
                    <div class="certificate-text">
                      <div class="certificate-header">CERTIFICATE OF COMPLETION</div>
                      <div class="certificate-name">Maria Clara Santos</div>
                      <div class="certificate-degree">Bachelor of Science in Information Technology</div>
                      <div class="certificate-school">Cavite State University</div>
                      <div class="certificate-internship">
                        has successfully completed an internship at
                      </div>
                      <div class="certificate-company">TechSolutions Inc.</div>
                      <div class="certificate-period">June 1, 2024 - November 28, 2024</div>
                      <div class="certificate-hours">600 Hours Rendered</div>
                    </div>
                  </div>
                </div>
                <div class="certificate-actions">
                  <button class="btn-primary">Generate Certificate</button>
                  <button class="btn-outline">Preview Full Size</button>
                  <button class="btn-outline">Email Certificate</button>
                </div>
                <div class="certificate-verified">
                  <span class="lock-icon"><LockClosedIcon class="icon-size-sm" /></span>
                  Digitally signed and verifiable
                </div>
              </div>

              <!-- Student Information -->
              <div class="student-info-section">
                <h3 class="section-title">Student Information</h3>
                <div class="info-item">
                  <span class="info-label">Student:</span>
                  <span class="info-value">Maria Clara Santos</span>
                </div>
                <div class="info-item">
                  <span class="info-label">ID:</span>
                  <span class="info-value">2020-00123</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Course:</span>
                  <span class="info-value">BS Information Technology</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Company:</span>
                  <span class="info-value">TechSolutions Inc.</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Period:</span>
                  <span class="info-value">Jun 1 - Nov 28, 2024</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Hours:</span>
                  <span class="info-value">600 hours</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Status:</span>
                  <span class="info-value verified">
                    <span class="check-icon">
                      <CheckIcon class="icon-size-sm" />
                    </span>
                    Verified
                  </span>
                </div>
              </div>

              <!-- Honors -->
              <div class="honors-section">
                <h3 class="section-title">Honors</h3>
                <div class="honors-badges">
                  <span class="honor-badge perfect">Perfect Attendance</span>
                  <span class="honor-badge excellent">Excellent Rating</span>
                  <span class="honor-badge complete">Complete Documentation</span>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>

      <!-- Create Internship Form View -->
      <div v-if="currentView === 'create-internship'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-post_new_internship.png" alt="Post New Internship" class="header-icon-img" />
            <h1>{{ isEditMode ? 'Edit Internship' : 'Post New Internship' }}</h1>
          </div>
          <div class="header-right">
            <div class="notification-wrapper">
              <BellIcon class="notification-icon-bell" />
            </div>
            <div class="avatar" @click="handleNavChange('profile')" title="View Profile">{{ userInitials }}</div>
          </div>
        </header>
        <main class="main-content">
          <div class="create-internship-layout">
            <!-- Left Column: Form -->
            <div class="form-column">
              <!-- Internship Details Section -->
              <div class="form-card" v-if="currentFormStep === 1">
                <div class="form-card-header">
                  <img src="/icons/icon-internship_details.png" alt="Internship Details" class="section-icon" />
                  <h2 class="section-title">Internship Details</h2>
                  <span class="status-badge in-progress">In Progress</span>
                </div>
                <p class="section-description">Provide the core information about your internship opportunity.</p>
                
                <div class="form-group">
                  <label class="form-label">Internship Title <span class="required">*</span></label>
                  <input
                    type="text"
                    v-model="formTitle"
                    placeholder="e.g. Software Engineering Intern"
                    class="form-input"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Department / Team <span class="required">*</span></label>
                  <select v-model="formDepartment" class="form-select">
                    <option value="">Select Department</option>
                    <option value="engineering">Engineering</option>
                    <option value="marketing">Marketing</option>
                    <option value="design">Design</option>
                    <option value="business">Business</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Internship Description <span class="required">*</span></label>
                  <textarea
                    v-model="formDescription"
                    placeholder="Describe the internship duties, responsibilities, projects, learning environment"
                    class="form-textarea"
                    rows="6"
                  ></textarea>
                </div>

                <div class="form-group">
                  <label class="form-label">Internship Type</label>
                  <div class="radio-group">
                    <label class="radio-option">
                      <input type="radio" name="internshipType" value="on-site" v-model="formType" />
                      <span class="radio-label">On-site</span>
                    </label>
                    <label class="radio-option">
                      <input type="radio" name="internshipType" value="remote" v-model="formType" />
                      <span class="radio-label">Remote</span>
                    </label>
                    <label class="radio-option">
                      <input type="radio" name="internshipType" value="hybrid" v-model="formType" />
                      <span class="radio-label">Hybrid</span>
                    </label>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label"> CV/municipality <span class="required">*</span></label>
                  <select v-model="formMunicipality" class="form-select" @change="formBarangay = ''">
                    <option value="">Select City/Municipality in Cavite</option>
                    <option value="Bacoor">Bacoor City</option>
                    <option value="Cavite City">Cavite City</option>
                    <option value="Dasmariñas">Dasmariñas City</option>
                    <option value="General Trias">General Trias City</option>
                    <option value="Imus">Imus City</option>
                    <option value="Tagaytay">Tagaytay City</option>
                    <option value="Trece Martires">Trece Martires City</option>
                    <option value="Alfonso">Alfonso</option>
                    <option value="Amadeo">Amadeo</option>
                    <option value="Carmona">Carmona</option>
                    <option value="General Emilio Aguinaldo">General Emilio Aguinaldo</option>
                    <option value="General Mariano Alvarez">General Mariano Alvarez</option>
                    <option value="Indang">Indang</option>
                    <option value="Kawit">Kawit</option>
                    <option value="Magallanes">Magallanes</option>
                    <option value="Maragondon">Maragondon</option>
                    <option value="Mendez">Mendez</option>
                    <option value="Naic">Naic</option>
                    <option value="Noveleta">Noveleta</option>
                    <option value="Rosario">Rosario</option>
                    <option value="Silang">Silang</option>
                    <option value="Tanza">Tanza</option>
                    <option value="Ternate">Ternate</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Barangay <span class="required">*</span></label>
                  <select v-model="formBarangay" class="form-select" :disabled="!formMunicipality">
                    <option value="">{{ formMunicipality ? 'Select Barangay' : 'Select municipality first' }}</option>
                    <option v-for="barangay in availableBarangays" :key="barangay" :value="barangay">
                      {{ barangay }}
                    </option>
                  </select>
                </div>

                <div class="form-actions">
                  <button class="btn-next-section" @click="nextFormStep" v-if="currentFormStep === 1">
                    Next Section
                    <span class="arrow-icon">→</span>
                  </button>
                </div>
              </div>

              <!-- Qualifications & Responsibilities Section (Step 2) -->
              <div class="form-card" v-show="currentFormStep === 2">
                <div class="form-card-header">
                  <img src="/icons/icon-student.png" alt="Qualifications" class="section-icon" />
                  <h2 class="section-title">Qualifications & Responsibilities</h2>
                  <span class="status-badge in-progress">In Progress</span>
                </div>
                <p class="section-description">Define the requirements and responsibilities for this internship position.</p>
                
                <div class="form-group">
                  <label class="form-label">Preferred College Programs</label>
                  <select
                    ref="courseSelectRef"
                    :key="`course-select-${selectKey}`"
                    multiple
                    class="form-select-multiple"
                    placeholder="Select or type courses/programs..."
                  >
                    <option v-for="course in availableCourseOptions" :key="course" :value="course">
                      {{ course }}
                    </option>
                  </select>
                  <p class="form-hint">Select from programs offered by partner schools, or type custom ones. Schools will use this to match suitable students.</p>
                </div>

                <div class="form-group">
                  <div class="info-message">
                    <InformationCircleIcon class="info-icon" />
                    <div>
                      <strong>Note:</strong> Program options are loaded from partner schools in the system. 
                      Internship hours and final requirements are set by schools during the approval process.
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Required Skills & Technologies <span class="required">*</span></label>
                  <select
                    ref="skillsSelectRef"
                    :key="`skills-select-${selectKey}`"
                    multiple
                    class="form-select-multiple"
                    placeholder="Select or type required skills..."
                  >
                    <option v-for="skill in availableSkillOptions" :key="skill" :value="skill">
                      {{ skill }}
                    </option>
                  </select>
                  <p class="form-hint">Select suggested skills from the chosen courses or type custom skills like PHP, Laravel, SAP, or anything else.</p>
                </div>

                <div class="form-row">
                  <div class="form-group half-width">
                    <label class="form-label">Year Level <span class="required">*</span></label>
                    <select v-model="yearLevel" class="form-select">
                      <option value="">Select Year Level</option>
                      <option value="3rd-year">3rd Year</option>
                      <option value="4th-year">4th Year</option>
                      <option value="any">Any Year Level</option>
                    </select>
                  </div>

                  <div class="form-group half-width">
                    <label class="form-label">Start Date <span class="required">*</span></label>
                    <div class="date-input-wrapper">
                      <input
                        type="date"
                        v-model="startDateForm"
                        class="form-input"
                      />
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Additional Requirements</label>
                  <textarea
                    v-model="additionalRequirements"
                    placeholder="Any additional requirements, certifications, or qualifications (optional)"
                    class="form-textarea"
                    rows="3"
                  ></textarea>
                </div>

                <div class="form-group" v-if="false">
                  <!-- Duration removed: now handled via per-course completion hours -->
                  <input
                    type="number"
                    v-model="formDuration"
                    placeholder="12"
                    class="form-input duration-input"
                  />
                  <span class="input-suffix">weeks</span>
                </div>

                <div class="form-group" v-if="false">
                  <label class="form-label">Required Internship Hours / Week <span class="required">*</span></label>
                  <input
                    type="number"
                    v-model="formHoursPerWeek"
                    placeholder="20"
                    class="form-input"
                  />
                </div>

                <div class="form-actions">
                  <button class="btn-prev-section" @click="prevFormStep">
                    <span class="arrow-icon">←</span>
                    Previous Section
                  </button>
                  <button class="btn-next-section" @click="nextFormStep">
                    Next Section
                    <span class="arrow-icon">→</span>
                  </button>
                </div>
              </div>

              <!-- Tasks & Responsibilities Section (Step 3) -->
              <div class="form-card" v-if="currentFormStep === 3">
                <div class="form-card-header">
                  <img src="/icons/icon-internship.png" alt="Tasks" class="section-icon blue-icon" />
                  <h2 class="section-title">Tasks & Responsibilities</h2>
                  <span class="status-badge in-progress">In Progress</span>
                </div>
                <p class="section-description">Outline the specific tasks and responsibilities for this internship.</p>
                
                <div class="form-group">
                  <label class="form-label">Primary Responsibilities <span class="required">*</span></label>
                  <textarea
                    v-model="primaryResponsibilities"
                    placeholder="Describe the main tasks and responsibilities the intern will handle"
                    class="form-textarea"
                    rows="5"
                  ></textarea>
                </div>

                <div class="form-group">
                  <label class="form-label">Learning Objectives</label>
                  <textarea
                    v-model="learningObjectives"
                    placeholder="What will the intern learn from this experience? What skills will they develop?"
                    class="form-textarea"
                    rows="4"
                  ></textarea>
                </div>

                <div class="form-group">
                  <label class="form-label">Projects & Deliverables</label>
                  <textarea
                    v-model="projectsDeliverables"
                    placeholder="Specific projects or deliverables the intern will work on"
                    class="form-textarea"
                    rows="4"
                  ></textarea>
                </div>

                <div class="form-actions">
                  <button class="btn-prev-section" @click="prevFormStep">
                    <span class="arrow-icon">←</span>
                    Previous Section
                  </button>
                  <button class="btn-next-section" @click="nextFormStep">
                    Next Section
                    <span class="arrow-icon">→</span>
                  </button>
                </div>
              </div>

              <!-- Availability & Slots Section (Step 4) -->
              <div class="form-card availability-slots-section" v-if="currentFormStep === 4">
                <div class="form-card-header">
                  <img src="/icons/icon-application.png" alt="Availability" class="section-icon blue-icon" />
                  <h2 class="section-title">Availability & Slots</h2>
                  <span class="status-badge in-progress">In Progress</span>
                </div>
                <p class="section-description">Set the availability and application details for this internship.</p>
                
                <div class="form-row">
                  <div class="form-group half-width">
                    <label class="form-label">Available Slots <span class="required">*</span></label>
                    <input
                      type="number"
                      v-model="slotsAvailable"
                      placeholder="1"
                      min="1"
                      class="form-input"
                    />
                  </div>

                  <div class="form-group half-width">
                    <label class="form-label">Application Deadline <span class="required">*</span></label>
                    <div class="date-input-wrapper">
                      <input
                        type="date"
                        v-model="endDateForm"
                        class="form-input"
                      />
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Application Instructions</label>
                  <textarea
                    v-model="applicationInstructions"
                    placeholder="Special instructions for applicants (e.g., required documents, portfolio requirements)"
                    class="form-textarea"
                    rows="4"
                  ></textarea>
                </div>
                <div class="form-group">
                  <div class="allowance-container">
                    <div class="allowance-header">
                      <input
                        type="checkbox"
                        id="hasAllowance"
                        v-model="hasAllowance"
                        class="form-checkbox"
                      />
                      <label for="hasAllowance" class="allowance-label">This internship provides an allowance (optional)</label>
                    </div>
                    
                    <div v-if="hasAllowance" class="allowance-input-section">
                      <label class="form-label">Allowance Amount</label>
                      <input
                        type="text"
                        v-model="formAllowance"
                        placeholder="e.g. ₱5,000/month, ₱15,000 total, etc."
                        class="form-input"
                      />
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Contact Information</label>
                  <p class="form-description">Select the contact methods students can use to inquire about this internship</p>
                  
                  <div class="contact-methods-container">
                    <!-- Email Contact -->
                    <div class="contact-method-row">
                      <div class="contact-method-header">
                        <input
                          type="checkbox"
                          id="hasEmailContact"
                          v-model="hasEmailContact"
                          class="form-checkbox"
                        />
                        <label for="hasEmailContact" class="contact-method-label">Email</label>
                      </div>
                      <div v-if="hasEmailContact" class="contact-method-input">
                        <input
                          type="email"
                          v-model="emailContact"
                          placeholder="e.g. hr@company.com or john.doe@company.com"
                          class="form-input"
                        />
                      </div>
                    </div>

                    <!-- Social Media Contact -->
                    <div class="contact-method-row">
                      <div class="contact-method-header">
                        <input
                          type="checkbox"
                          id="hasSocialMediaContact"
                          v-model="hasSocialMediaContact"
                          class="form-checkbox"
                        />
                        <label for="hasSocialMediaContact" class="contact-method-label">Social Media</label>
                      </div>
                      <div v-if="hasSocialMediaContact" class="contact-method-input">
                        <input
                          type="text"
                          v-model="socialMediaContact"
                          placeholder="e.g. @company_official, facebook.com/company"
                          class="form-input"
                        />
                      </div>
                    </div>

                    <!-- SMS Contact -->
                    <div class="contact-method-row">
                      <div class="contact-method-header">
                        <input
                          type="checkbox"
                          id="hasSmsContact"
                          v-model="hasSmsContact"
                          class="form-checkbox"
                        />
                        <label for="hasSmsContact" class="contact-method-label">SMS/Phone</label>
                      </div>
                      <div v-if="hasSmsContact" class="contact-method-input">
                        <input
                          type="tel"
                          v-model="smsContact"
                          placeholder="e.g. +63 912 345 6789"
                          class="form-input"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                <div class="form-actions">
                  <button class="btn-prev-section" @click="prevFormStep">
                    <span class="arrow-icon">←</span>
                    Previous Section
                  </button>
                  <button class="btn-save-draft" @click="saveDraft" :disabled="submittingInternship">
                    <img src="/icons/icon-save.png" alt="Save" class="btn-icon" />
                    {{ isEditMode ? 'Save Changes' : 'Save Draft' }}
                  </button>
                  <button class="btn-next-section" @click="submitForApproval" :disabled="submittingInternship">
                    {{ submittingInternship ? (isEditMode ? 'Updating...' : 'Posting...') : (isEditMode ? 'Update Internship' : 'Submit for Approval') }}
                    <span class="arrow-icon">
                      <CheckIcon class="icon-size-sm" />
                    </span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Right Column: Progress Sidebar -->
            <div class="progress-column">
              <div class="progress-card">
                <h3 class="progress-title">{{ isEditMode ? 'Edit Internship' : 'Your Internship Post' }}</h3>
                
                <div class="progress-steps">
                  <div class="progress-step" :class="{ active: currentFormStep === 1 }">
                    <div class="step-indicator">
                      <span class="step-number">1</span>
                    </div>
                    <div class="step-content">
                      <div class="step-title">Internship Details</div>
                      <div class="step-status" :class="currentFormStep > 1 ? 'completed' : (currentFormStep === 1 ? 'in-progress' : 'pending')">
                        {{ currentFormStep > 1 ? 'Completed' : (currentFormStep === 1 ? 'In Progress' : 'Pending') }}
                      </div>
                    </div>
                  </div>
                  
                  <div class="progress-step" :class="{ active: currentFormStep === 2 }">
                    <div class="step-indicator">
                      <span class="step-number">2</span>
                    </div>
                    <div class="step-content">
                      <div class="step-title">Qualifications</div>
                      <div class="step-status" :class="currentFormStep > 2 ? 'completed' : (currentFormStep === 2 ? 'in-progress' : 'pending')">
                        {{ currentFormStep > 2 ? 'Completed' : (currentFormStep === 2 ? 'In Progress' : 'Pending') }}
                      </div>
                    </div>
                  </div>
                  
                  <div class="progress-step" :class="{ active: currentFormStep === 3 }">
                    <div class="step-indicator">
                      <span class="step-number">3</span>
                    </div>
                    <div class="step-content">
                      <div class="step-title">Tasks & Responsibilities</div>
                      <div class="step-status" :class="currentFormStep > 3 ? 'completed' : (currentFormStep === 3 ? 'in-progress' : 'pending')">
                        {{ currentFormStep > 3 ? 'Completed' : (currentFormStep === 3 ? 'In Progress' : 'Pending') }}
                      </div>
                    </div>
                  </div>
                  
                  <div class="progress-step" :class="{ active: currentFormStep === 4 }">
                    <div class="step-indicator">
                      <span class="step-number">4</span>
                    </div>
                    <div class="step-content">
                      <div class="step-title">Availability & Slots</div>
                      <div class="step-status" :class="currentFormStep > 4 ? 'completed' : (currentFormStep === 4 ? 'in-progress' : 'pending')">
                        {{ currentFormStep > 4 ? 'Completed' : (currentFormStep === 4 ? 'In Progress' : 'Pending') }}
                      </div>
                    </div>
                  </div>
                </div>

                <button class="btn-save-draft" @click="saveDraft" :disabled="submittingInternship">
                  <img src="/icons/icon-save.png" alt="Save" class="btn-icon" />
                  {{ isEditMode ? 'Save Changes' : 'Save as Draft' }}
                </button>
              </div>

              <div class="approval-status-card">
                <div class="approval-status-header">
                  <h4 class="approval-status-title">Post Approval</h4>
                  <span class="approval-status-badge" :class="internshipApprovalState">
                    <CheckIcon v-if="internshipApprovalState === 'approved'" class="approval-status-icon" />
                    <XMarkIcon v-else-if="internshipApprovalState === 'declined'" class="approval-status-icon" />
                    <ClockIcon v-else class="approval-status-icon" />
                    {{ internshipApprovalLabel }}
                  </span>
                </div>

                <p class="approval-status-hint">{{ internshipApprovalHint }}</p>
                <p v-if="editingInternshipRecord?.approvalNotes" class="approval-status-notes">
                  {{ editingInternshipRecord.approvalNotes }}
                </p>
              </div>
            </div>
          </div>
        </main>
      </div>

      <!-- Profile View -->
      <div v-if="currentView === 'profile'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-profile.png" alt="Profile" class="header-icon-img" />
            <h1>Profile</h1>
          </div>
          <div class="header-right">
            <div class="notification-wrapper">
              <BellIcon class="notification-icon-bell" />
            </div>
            <div class="avatar" @click="handleNavChange('profile')" title="View Profile">{{ userInitials }}</div>
          </div>
        </header>
        <main class="main-content">
          <!-- Company Profile Header -->
          <div class="profile-header">
            <div class="profile-company-info">
              <div class="company-logo-large">{{ companyInitials }}</div>
              <div class="company-details">
                <h2 class="company-name-large">{{ companyProfile.name }}</h2>
                <p class="company-industry">{{ companyProfile.industry }}</p>
                <div class="company-contact">
                  <div class="contact-item">
                    <span class="contact-icon"><EnvelopeIcon class="icon-size" /></span>
                    <span>{{ companyProfile.email }}</span>
                  </div>
                  <div class="contact-item">
                    <span class="contact-icon"><PhoneIcon class="icon-size" /></span>
                    <span>{{ companyProfile.phone }}</span>
                  </div>
                  <div class="contact-item">
                    <span class="contact-icon"><MapPinIcon class="icon-size" /></span>
                    <span>{{ companyProfile.address }}</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="profile-stats-section">
              <div class="profile-description-preview">
                <p class="description-text">{{ companyProfile.description }}</p>
              </div>
              <button class="btn-edit-profile" @click="editProfile">
                <span class="edit-icon"><PencilIcon class="icon-size-sm" /></span>
                Edit Profile
              </button>
            </div>
          </div>

          <!-- Profile Content Grid -->
          <div class="profile-content-grid">
            <!-- Company Details -->
            <div class="profile-card">
              <div class="profile-card-header">
                <h3 class="profile-card-title">Company Details</h3>
                <button class="card-edit-btn" @click="editProfile" title="Edit Company Details"><PencilIcon class="icon-size-sm" /></button>
              </div>
              <div class="profile-details">
                <div class="detail-row">
                  <span class="detail-label">Company Size</span>
                  <span class="detail-value">{{ companyProfile.companySize }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Founded</span>
                  <span class="detail-value">{{ companyProfile.founded }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Website</span>
                  <span class="detail-value">{{ companyProfile.website }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Business Type</span>
                  <span class="detail-value">{{ companyProfile.businessType }}</span>
                </div>
              </div>
            </div>

            <!-- Internship Focus -->
            <div class="profile-card">
              <div class="profile-card-header">
                <h3 class="profile-card-title">Internship Focus</h3>
                <button class="card-edit-btn" @click="editProfile" title="Edit Internship Focus"><PencilIcon class="icon-size-sm" /></button>
              </div>
              <div class="profile-details">
                <div class="detail-row">
                  <span class="detail-label">Primary Departments</span>
                  <span class="detail-value">{{ companyProfile.primaryDepartments }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Preferred Background</span>
                  <span class="detail-value">{{ companyProfile.preferredBackground }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Internship Duration</span>
                  <span class="detail-value">{{ companyProfile.internshipDuration }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Available Slots</span>
                  <span class="detail-value">{{ companyProfile.availableSlots }}</span>
                </div>
              </div>
            </div>

            <!-- Internship History Summary -->
            <div class="profile-card">
              <h3 class="profile-card-title">Internship History Summary</h3>
              <div class="profile-details">
                <div class="detail-row">
                  <span class="detail-label">Total Interns Hosted</span>
                  <span class="detail-value highlight-green">{{ companyProfile.totalInternsHosted }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Average Duration</span>
                  <span class="detail-value">{{ companyProfile.averageDuration }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Completion Rate</span>
                  <span class="detail-value highlight-green">{{ companyProfile.completionRate }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Student Satisfaction</span>
                  <span class="detail-value">{{ companyProfile.studentSatisfaction }} / 5.0 <StarIcon class="icon-size-sm" style="display: inline-block; vertical-align: middle;" /></span>
                </div>
              </div>
            </div>

          </div>

          <!-- Profile Completion -->
          <div class="profile-completion">
            <div class="completion-header">
              <h3 class="completion-title">Profile Completion</h3>
              <span class="completion-percentage">{{ companyProfile.profileCompletion }}%</span>
            </div>
            <div class="completion-bar-wrapper">
              <div 
                class="completion-bar" 
                :style="{ width: companyProfile.profileCompletion + '%' }"
              ></div>
            </div>
            <p class="completion-subtitle">Complete your profile to attract more qualified interns</p>
            <div class="completion-tasks">
              <div class="completion-task">
                <span class="task-icon">🟠</span>
                <span class="task-text">Add detailed company description</span>
              </div>
              <div class="completion-task">
                <span class="task-icon">🟠</span>
                <span class="task-text">Upload company certifications</span>
              </div>
              <div class="completion-task">
                <span class="task-icon">🟠</span>
                <span class="task-text">Add company culture photos</span>
              </div>
            </div>
          </div>
        </main>
      </div>

      <!-- Intern Time Ins View -->
      <div v-if="currentView === 'time-tracking'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-timetracking.png" alt="Intern Time Ins" class="header-icon-img" />
            <h1>Intern Time Ins</h1>
          </div>
          <div class="header-right">
            <div class="notification-wrapper">
              <BellIcon class="notification-icon-bell" />
            </div>
            <div class="avatar" @click="handleNavChange('profile')" title="View Profile">{{ userInitials }}</div>
          </div>
        </header>
        <main class="main-content">
          <div class="time-tracking-board">
            <div class="time-tracking-board-top">
              <div class="time-tracking-board-title">
                <span class="board-day">{{ timeTrackingDayLabel }}</span>
                <span class="board-sep">|</span>
                <span class="board-context">Intern Time Logs</span>
              </div>

              <div class="time-tracking-board-tools">
                <button type="button" class="board-nav-btn" @click="shiftTimeTrackingDate(-1)" aria-label="Previous day">&lsaquo;</button>
                <div class="board-date">
                  <span class="board-date-main">{{ timeTrackingDateLabel }}</span>
                </div>
                <button type="button" class="board-nav-btn" @click="shiftTimeTrackingDate(1)" aria-label="Next day">&rsaquo;</button>

                <select v-model="timeTrackingStatus" class="board-select" aria-label="Status filter">
                  <option value="All">All</option>
                  <option value="PRESENT">Present</option>
                  <option value="LATE">Late</option>
                  <option value="ONGOING">Ongoing</option>
                  <option value="INCOMPLETE">Incomplete</option>
                </select>

                <div class="board-search">
                  <MagnifyingGlassIcon class="board-search-icon" />
                  <input v-model="timeTrackingSearch" type="text" placeholder="Search interns..." />
                </div>
              </div>
            </div>

            <div class="time-tracking-board-body">
              <div class="time-tracking-list" role="region" aria-label="Intern time logs">
                <table class="time-tracking-table-desktop">
                  <thead>
                    <tr>
                      <th class="col-intern">Intern</th>
                      <th class="col-role">Role</th>
                      <th class="col-time">Clock In</th>
                      <th class="col-time">Clock Out</th>
                      <th class="col-total">Total Time</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="record in filteredTimeTrackingRecords"
                      :key="record.id"
                      class="time-row"
                      :class="{ selected: record.id === selectedTimeTrackingRecordId }"
                      @click="selectTimeTrackingRecord(record.id)"
                    >
                      <td class="cell-intern">
                        <div class="intern-cell">
                          <div class="intern-avatar" aria-hidden="true">{{ getNameInitials(record.name) }}</div>
                          <div class="intern-meta">
                            <div class="tt-intern-name">{{ record.name }}</div>
                            <div class="tt-intern-id">{{ record.internId }}</div>
                          </div>
                        </div>
                      </td>
                      <td class="cell-role">{{ record.role }}</td>
                      <td class="cell-time">{{ record.timeIn }}</td>
                      <td class="cell-time">{{ record.timeOut }}</td>
                      <td class="cell-total">
                        <div class="total-cell">
                          <span class="total-text">{{ record.totalHours }}</span>
                          <span class="status-dot" :class="record.statusColor" :title="record.status"></span>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <aside class="time-tracking-details" aria-label="Selected intern details">
                <div class="details-card">
                  <div class="details-header">Details</div>
                  <div class="details-subtitle">{{ timeTrackingDayLabel }} | {{ timeTrackingDateLabel }}</div>

                  <div v-if="selectedTimeTrackingRecord" class="details-body">
                    <div class="details-person">
                      <div class="details-avatar" aria-hidden="true">{{ getNameInitials(selectedTimeTrackingRecord.name) }}</div>
                      <div class="details-person-meta">
                        <div class="details-name">{{ selectedTimeTrackingRecord.name }}</div>
                        <div class="details-id">{{ selectedTimeTrackingRecord.internId }} &bull; {{ selectedTimeTrackingRecord.role }}</div>
                      </div>
                    </div>

                    <div class="details-grid">
                      <div class="details-item">
                        <div class="details-label">Clock In</div>
                        <div class="details-value">{{ selectedTimeTrackingRecord.timeIn }}</div>
                      </div>
                      <div class="details-item">
                        <div class="details-label">Clock Out</div>
                        <div class="details-value">{{ selectedTimeTrackingRecord.timeOut }}</div>
                      </div>
                      <div class="details-item">
                        <div class="details-label">Total Time</div>
                        <div class="details-value">{{ selectedTimeTrackingRecord.totalHours }}</div>
                      </div>
                      <div class="details-item">
                        <div class="details-label">Status</div>
                        <div class="details-value">
                          <span class="status-badge-tracking" :class="selectedTimeTrackingRecord.statusColor">
                            {{ selectedTimeTrackingRecord.status }}
                          </span>
                        </div>
                      </div>
                    </div>

                    <div class="details-actions">
                      <button type="button" class="details-btn primary" @click="openAdjustTimeModal">Adjust time</button>
                      <button type="button" class="details-btn" @click="sendTimeReminder">Send reminder</button>
                    </div>
                  </div>

                  <div v-else class="details-empty">
                    Select an intern to view their time log.
                  </div>
                </div>
              </aside>
            </div>
          </div>

          <div v-if="showAdjustTimeModal" class="tt-modal-overlay" @click.self="closeAdjustTimeModal">
            <div class="tt-modal" role="dialog" aria-modal="true" aria-label="Adjust intern time log">
              <div class="tt-modal-header">
                <div>
                  <div class="tt-modal-title">Adjust Time</div>
                  <div v-if="selectedTimeTrackingRecord" class="tt-modal-subtitle">
                    {{ selectedTimeTrackingRecord.name }} &bull; {{ timeTrackingDayLabel }} | {{ timeTrackingDateLabel }}
                  </div>
                </div>
                <button type="button" class="tt-modal-close" @click="closeAdjustTimeModal" aria-label="Close">&times;</button>
              </div>

              <div class="tt-modal-body">
                <div class="tt-modal-grid">
                  <label class="tt-field">
                    <span class="tt-field-label">Clock In</span>
                    <input v-model="adjustTimeForm.timeIn" type="time" />
                  </label>

                  <label class="tt-field">
                    <span class="tt-field-label">Clock Out</span>
                    <input v-model="adjustTimeForm.timeOut" type="time" :disabled="adjustTimeClockOutDisabled" />
                  </label>

                  <label class="tt-field">
                    <span class="tt-field-label">Status</span>
                    <select v-model="adjustTimeForm.status">
                      <option value="PRESENT">Present</option>
                      <option value="LATE">Late</option>
                      <option value="ONGOING">Ongoing</option>
                      <option value="INCOMPLETE">Incomplete</option>
                    </select>
                  </label>

                  <div class="tt-field tt-preview">
                    <span class="tt-field-label">Total Time</span>
                    <div class="tt-preview-value">{{ adjustTimeTotalPreview }}</div>
                  </div>
                </div>

                <div v-if="adjustTimeError" class="tt-modal-error">{{ adjustTimeError }}</div>
              </div>

              <div class="tt-modal-footer">
                <button type="button" class="tt-modal-btn" @click="closeAdjustTimeModal">Cancel</button>
                <button type="button" class="tt-modal-btn primary" @click="saveAdjustTime">Save changes</button>
              </div>
            </div>
          </div>
        </main>
      </div>

      <!-- Evaluation View -->
      <div v-if="currentView === 'evaluation'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-evaluation.png" alt="Evaluation Management" class="header-icon-img" />
            <h1>Evaluation Management</h1>
          </div>
          <div class="header-right">
            <button class="btn-set-evaluation" @click="setNewEvaluation">
              <span class="btn-icon">
                <PlusIcon class="icon-size" />
              </span>
              Set New Evaluation
            </button>
            <BellIcon class="notification-icon-bell" />
            <div class="avatar" @click="handleNavChange('profile')" title="View Profile">{{ userInitials }}</div>
          </div>
        </header>
        <main class="main-content">
          <div class="evaluation-layout">
            <!-- Left Column: Calendar -->
            <div class="evaluation-left-column">
              <div class="evaluation-calendar-card">
                <div class="calendar-header">
                  <h3 class="calendar-title">{{ evaluationMonthLabel }}</h3>
                  <div class="calendar-nav">
                    <button type="button" class="cal-nav-btn" @click="prevEvaluationMonth" aria-label="Previous month">‹</button>
                    <button type="button" class="cal-nav-btn" @click="nextEvaluationMonth" aria-label="Next month">›</button>
                  </div>
                </div>
                <div class="evaluation-calendar-grid">
                  <div v-for="dow in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="dow" class="cal-head">{{ dow }}</div>
                  <div
                    v-for="(cell, i) in evaluationMonthGrid"
                    :key="i"
                    class="eval-cal-cell"
                    :class="{ 'other-month': !cell.isCurrentMonth }"
                  >
                    <span class="cal-num">{{ cell.date.getDate() }}</span>
                    <div v-if="hasEvaluationDot(cell.date)" class="eval-dot"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Column: Stats and Actions -->
            <div class="evaluation-right-column">
              <!-- Evaluations Due This Week -->
              <div class="eval-stat-card">
                <div class="stat-header">
                  <h4 class="stat-title">Evaluations Due This Week</h4>
                  <button class="view-pending-btn" @click="viewPending" disabled title="Coming soon">
                    View Pending →
                  </button>
                </div>
                <div class="stat-number">{{ evaluationsThisWeek }}</div>
                <div class="stat-subtitle">90% completion rate</div>
              </div>

              <!-- Pending My Action -->
              <div class="eval-stat-card">
                <div class="stat-header">
                  <h4 class="stat-title">Pending My Action</h4>
                </div>
                <div class="stat-number">{{ pendingMyAction }}</div>
              </div>

              <!-- Total Evaluations Completed -->
              <div class="eval-stat-card">
                <div class="stat-header">
                  <h4 class="stat-title">Total Evaluations Completed</h4>
                </div>
                <div class="stat-number large">{{ totalEvaluationsCompleted }}</div>
                <div class="stat-subtitle">Great progress!</div>
              </div>
            </div>
          </div>

          <!-- Pending Evaluations Table -->
          <div class="pending-evaluations-section">
            <div class="section-header-eval">
              <div class="section-title-with-icon">
                <span class="section-icon">
                  <ClipboardDocumentListIcon class="icon-size" />
                </span>
                <h3 class="section-title">Pending Evaluations</h3>
              </div>
              <div class="search-wrapper-eval">
                <span class="search-icon">
                  <MagnifyingGlassIcon class="icon-size" />
                </span>
                <input
                  v-model="evaluationSearch"
                  type="text"
                  placeholder="Search Student, Supervisor..."
                  class="search-input-eval"
                />
              </div>
            </div>

            <div class="evaluations-table-container">
              <table class="evaluations-table">
                <thead>
                  <tr>
                    <th>STUDENT NAME</th>
                    <th>SUPERVISOR</th>
                    <th>TYPE</th>
                    <th>DUE DATE</th>
                    <th>STATUS</th>
                    <th>ACTION</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="evaluation in filteredEvaluationRows" :key="evaluation.id" class="evaluation-row">
                    <td class="td-student">{{ evaluation.studentLabel }}</td>
                    <td class="td-supervisor">{{ evaluation.supervisor }}</td>
                    <td class="td-type">{{ evaluation.type }}</td>
                    <td class="td-date">{{ evaluation.dueLabel }}</td>
                    <td class="td-status">
                      <span class="status-badge-eval" :class="evaluation.statusColor">
                        {{ evaluation.status }}
                      </span>
                    </td>
                    <td class="td-action">
                      <button 
                        class="btn-start-evaluation"
                        @click="startEvaluation(evaluation.id)"
                        disabled
                        title="Coming soon"
                      >
                        Start Evaluation
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </main>

        <!-- New Evaluation Modal -->
        <Teleport to="body">
          <div v-if="showNewEvaluationModal" class="modal-overlay">
            <div class="modal-backdrop" @click="closeNewEvaluationModal"></div>
            <div class="evaluation-modal">
              <div class="modal-header">
                <h3 class="modal-title">Set New Evaluation Schedule</h3>
                <button class="modal-close-btn" @click="closeNewEvaluationModal"><XMarkIcon class="icon-size" /></button>
              </div>
              
              <div class="modal-body">
                <p class="modal-description">This form would allow you to schedule a new evaluation.</p>
                
                <div class="form-group">
                  <label class="form-label">Select Student:</label>
                  <select v-model="newEvaluationForm.studentId" class="form-select">
                    <option value="">---Choose a student---</option>
                    <option v-for="student in acceptedInternOptions" :key="student.id" :value="student.id">
                      {{ student.name }}
                    </option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Select Supervisor:</label>
                  <select v-model="newEvaluationForm.supervisor" class="form-select">
                    <option value="">---Choose a supervisor---</option>
                    <option v-for="supervisor in supervisorOptions" :key="supervisor" :value="supervisor">
                      {{ supervisor }}
                    </option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Evaluation Type:</label>
                  <select v-model="newEvaluationForm.evaluationType" class="form-select">
                    <option value="">---Choose type---</option>
                    <option v-for="type in evaluationTypeOptions" :key="type" :value="type">
                      {{ type }}
                    </option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Due Date:</label>
                  <div class="date-input-wrapper">
                    <input 
                      v-model="newEvaluationForm.dueDate" 
                      type="date" 
                      class="form-input date-input"
                    />
                    <span class="date-icon">
                      <CalendarIcon class="icon-size" />
                    </span>
                  </div>
                </div>
              </div>

              <div class="modal-footer">
                <button
                  class="btn-schedule-evaluation"
                  @click="scheduleEvaluation"
                  :disabled="scheduleEvaluationDisabled"
                >
                  {{ schedulingEvaluation ? 'Scheduling...' : 'Schedule Evaluation' }}
                </button>
              </div>
            </div>
          </div>
        </Teleport>
      </div>

      <!-- Subscription View -->
      <SubscriptionManager v-if="currentView === 'subscription'" role="company" />

      <!-- Settings View -->
      <CompanySettings v-if="currentView === 'settings'" />

    </div> <!-- End Main Wrapper -->

    <!-- Floating Chat Widget -->
    <FloatingChatWidget userType="company" />

    <!-- Internship Details Modal -->
    <div v-if="showInternshipDetailsModal && selectedInternshipForDetails" class="modal-overlay" @click="closeInternshipDetails">
      <div class="modal-content internship-details-modal" @click.stop>
        <!-- Modal Header with Company Branding -->
        <div class="modal-header-enhanced">
          <div class="header-content">
            <div class="company-avatar">
              {{ companyInitials }}
            </div>
            <div class="header-info">
              <h1 class="internship-title">{{ selectedInternshipForDetails.title }}</h1>
              <div class="company-info">
                <span class="company-name">{{ selectedInternshipForDetails.companyName }}</span>
                <span class="status-badge" :class="selectedInternshipForDetails.status">
                  {{ selectedInternshipForDetails.status.charAt(0).toUpperCase() + selectedInternshipForDetails.status.slice(1) }}
                </span>
              </div>
            </div>
          </div>
          <button class="modal-close-enhanced" @click="closeInternshipDetails">
            <XMarkIcon class="close-icon" />
          </button>
        </div>
        
        <div class="modal-body-enhanced">
          <!-- Key Information Cards -->
          <div class="key-info-grid">
            <div class="info-card">
              <div class="info-card-icon location">
                <MapPinIcon />
              </div>
              <div class="info-card-content">
                <div class="info-card-label">Location</div>
                <div class="info-card-value">{{ selectedInternshipForDetails.location }}</div>
              </div>
            </div>
            
            <div class="info-card">
              <div class="info-card-icon duration">
                <ClockIcon />
              </div>
              <div class="info-card-content">
                <div class="info-card-label">Duration</div>
                <div class="info-card-value">{{ selectedInternshipForDetails.duration }}</div>
              </div>
            </div>
            
            <div class="info-card">
              <div class="info-card-icon type">
                <CloudIcon />
              </div>
              <div class="info-card-content">
                <div class="info-card-label">Work Type</div>
                <div class="info-card-value">{{ selectedInternshipForDetails.type }}</div>
              </div>
            </div>
            
            <div class="info-card">
              <div class="info-card-icon slots">
                <UserIcon />
              </div>
              <div class="info-card-content">
                <div class="info-card-label">Available Slots</div>
                <div class="info-card-value">{{ selectedInternshipForDetails.slotsAvailable }}</div>
              </div>
            </div>
          </div>

          <!-- Description Section -->
          <div class="content-section" v-if="selectedInternshipForDetails.description">
            <div class="section-header">
              <InformationCircleIcon class="section-icon" />
              <h3>About This Internship</h3>
            </div>
            <div class="description-card">
              <p>{{ selectedInternshipForDetails.description }}</p>
            </div>
          </div>

          <!-- Allowance Section -->
          <div class="content-section" v-if="selectedInternshipForDetails.allowance">
            <div class="section-header">
              <StarIcon class="section-icon" />
              <h3>Compensation</h3>
            </div>
            <div class="allowance-card">
              <div class="allowance-amount">{{ selectedInternshipForDetails.allowance }}</div>
              <div class="allowance-label">Monthly Allowance</div>
            </div>
          </div>

          <!-- Programs and Skills Grid -->
          <div class="programs-skills-grid">
            <!-- Eligible Programs -->
            <div class="content-section" v-if="selectedInternshipForDetails.eligibleCourses && selectedInternshipForDetails.eligibleCourses.length > 0">
              <div class="section-header">
                <AcademicCapIcon class="section-icon" />
                <h3>Eligible Programs</h3>
              </div>
              <div class="tags-container">
                <span v-for="course in selectedInternshipForDetails.eligibleCourses" :key="course" class="program-tag-enhanced">
                  {{ course }}
                </span>
              </div>
            </div>

            <!-- Required Skills -->
            <div class="content-section" v-if="selectedInternshipForDetails.requirements && selectedInternshipForDetails.requirements.length > 0">
              <div class="section-header">
                <ChartBarIcon class="section-icon" />
                <h3>Required Skills</h3>
              </div>
              <div class="tags-container">
                <span v-for="skill in selectedInternshipForDetails.requirements" :key="skill" class="skill-tag-enhanced">
                  {{ skill }}
                </span>
              </div>
            </div>
          </div>

          <!-- Contact Information -->
          <div class="content-section" v-if="selectedInternshipForDetails.contactInfo">
            <div class="section-header">
              <EnvelopeIcon class="section-icon" />
              <h3>Contact Information</h3>
            </div>
            <div class="contact-methods-card">
              <div v-for="method in selectedInternshipForDetails.contactInfo.split(' | ')" :key="method" class="contact-method">
                <div v-if="method.startsWith('Email: ')" class="contact-item email">
                  <EnvelopeIcon class="contact-icon" />
                  <div class="contact-details">
                    <div class="contact-type">Email</div>
                    <div class="contact-value">{{ method.replace('Email: ', '') }}</div>
                  </div>
                </div>
                <div v-else-if="method.startsWith('Social Media: ')" class="contact-item social">
                  <CloudIcon class="contact-icon" />
                  <div class="contact-details">
                    <div class="contact-type">Social Media</div>
                    <div class="contact-value">{{ method.replace('Social Media: ', '') }}</div>
                  </div>
                </div>
                <div v-else-if="method.startsWith('SMS/Phone: ')" class="contact-item phone">
                  <PhoneIcon class="contact-icon" />
                  <div class="contact-details">
                    <div class="contact-type">SMS/Phone</div>
                    <div class="contact-value">{{ method.replace('SMS/Phone: ', '') }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Posting Timeline -->
          <div class="content-section">
            <div class="section-header">
              <CalendarIcon class="section-icon" />
              <h3>Posting Timeline</h3>
            </div>
            <div class="timeline-card">
              <div class="timeline-item">
                <div class="timeline-dot created"></div>
                <div class="timeline-content">
                  <div class="timeline-label">Posted</div>
                  <div class="timeline-value">{{ formatApplicationDate(selectedInternshipForDetails.createdAt) }}</div>
                </div>
              </div>
              <div class="timeline-item" v-if="selectedInternshipForDetails.updatedAt">
                <div class="timeline-dot updated"></div>
                <div class="timeline-content">
                  <div class="timeline-label">Last Updated</div>
                  <div class="timeline-value">{{ formatApplicationDate(selectedInternshipForDetails.updatedAt) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="modal-footer-enhanced">
          <button class="btn-secondary-enhanced" @click="viewInternshipApplications(selectedInternshipForDetails?.id || '')">
            <ClipboardDocumentListIcon class="btn-icon" />
            View Applications
          </button>
          <button class="btn-edit-enhanced" @click="editInternship(selectedInternshipForDetails?.id || '')">
            <PencilIcon class="btn-icon" />
            Edit Posting
          </button>
        </div>
      </div>
    </div>
  </div> <!-- End Dashboard -->

</template>

<style scoped>
/* Heroicons sizing */
.icon-size {
  width: 20px;
  height: 20px;
}

.icon-size-sm {
  width: 16px;
  height: 16px;
  display: inline-block;
  vertical-align: middle;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-right: 8px;
}

.filter-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
}

.section-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #2563eb;
  margin-right: 8px;
}

.dashboard {
  display: flex;
  min-height: 100vh;
  margin: 0;
  padding: 0;
  background: #f5f7fb;
  font-family:
    Inter,
    system-ui,
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    Roboto,
    Arial,
    sans-serif;
}

/* Main Wrapper */
.main-wrapper {
  flex: 1;
  margin-left: 280px;
  display: flex;
  flex-direction: column;
}

/* Top Header */
.top-header {
  background: #dbeafe;
  padding: 16px 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #bfdbfe;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.back-btn {
  background: none;
  border: none;
  font-size: 24px;
  color: #1e40af;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  transition: background 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.back-btn:hover {
  background: rgba(30, 64, 175, 0.1);
}

.header-icon {
  font-size: 24px;
  color: #1e40af;
}

.top-header h1 {
  font-size: 24px;
  font-weight: 700;
  color: #1e40af;
  margin: 0;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.notification-icon {
  width: 20px;
  height: 20px;
  object-fit: contain;
  cursor: pointer;
  background: transparent;
  border: none;
  padding: 0;
  margin: 0;
}

.notification-icon-bell {
  width: 24px;
  height: 24px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s ease;
}

.notification-icon-bell:hover {
  color: #2563eb;
  transform: scale(1.1);
}

.notification-wrapper {
  position: relative;
}

.notification-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #ef4444;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
}

.notification-dropdown {
  position: absolute;
  top: calc(100% + 12px);
  right: 0;
  width: 360px;
  max-height: 480px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
  z-index: 1000;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.notification-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
}

.notification-header h3 {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.notification-count {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
}

.notification-list {
  flex: 1;
  overflow-y: auto;
  max-height: 360px;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px 20px;
  border-bottom: 1px solid #f3f4f6;
  cursor: pointer;
  transition: background 0.2s;
  position: relative;
}

.notification-item:hover {
  background: #f9fafb;
}

.notification-item:last-child {
  border-bottom: none;
}

.notification-item.unread {
  background: #eff6ff;
}

.notification-item.unread:hover {
  background: #dbeafe;
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-content h4 {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.notification-content p,
.notification-text {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 4px 0;
  line-height: 1.4;
}

.notification-time {
  font-size: 11px;
  color: #9ca3af;
}

.notification-action {
  font-size: 12px;
  color: #2563eb;
  font-weight: 500;
  text-decoration: none;
  margin-top: 4px;
  display: inline-block;
}

.notification-action:hover {
  text-decoration: underline;
}

.unread-dot {
  width: 8px;
  height: 8px;
  background: #2563eb;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 4px;
}

.notification-footer {
  padding: 12px 20px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
}

.view-all-btn {
  width: 100%;
  padding: 8px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.view-all-btn:hover {
  background: #1d4ed8;
}

.notification-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-size {
  width: 18px;
  height: 18px;
  color: #6b7280;
}

.company-name {
  font-size: 14px;
  color: #1e40af;
  font-weight: 500;
}

.avatar {
  width: 36px;
  height: 36px;
  background: #3b82f6;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.avatar:hover {
  background: #2563eb;
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Main Content */
.main-content {
  padding: 24px 32px;
  flex: 1;
}

/* Quick Actions Card */
.quick-actions-card {
  margin-top: 24px;
}

.action-buttons-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.action-btn-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 24px 16px;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.action-btn-card:hover {
  background: #f1f5f9;
  border-color: #cbd5e1;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.action-icon-wrapper {
  width: 48px;
  height: 48px;
  background: #dbeafe;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.action-icon-svg {
  width: 24px;
  height: 24px;
  color: #2563eb;
}

.action-text {
  font-size: 14px;
  font-weight: 500;
  color: #475569;
  text-align: center;
}

/* Summary Cards */
.summary-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-bottom: 32px;
}

.summary-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  position: relative;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  transition: all 0.3s ease;
}

.summary-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.card-icon {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.3s ease;
}

.summary-card:hover .card-icon {
  transform: scale(1.1);
}

.card-icon.ongoing {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  color: #1e40af;
}

.card-icon.near {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  color: #92400e;
}

.card-icon.completed {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  color: #065f46;
}

.card-number {
  font-size: 36px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 4px;
}

.card-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

/* Content Grid */
.content-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 24px;
}

/* Content Sections */
.content-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  transition: box-shadow 0.3s ease;
}

.content-section:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.content-section h2 {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 16px 0;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.section-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin-top: 4px;
}

/* Recent Activities */
.activities-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.activity-item {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 8px;
  border-radius: 8px;
  transition: all 0.2s ease;
}

.activity-item:hover {
  background: #f9fafb;
  transform: translateX(4px);
}

.activity-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
  border-radius: 10px;
  flex-shrink: 0;
  color: #4b5563;
  transition: all 0.2s ease;
}

.activity-item:hover .activity-icon {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  color: #1e40af;
  transform: scale(1.05);
}

.activity-content {
  flex: 1;
}

.activity-text {
  font-size: 14px;
  color: #374151;
  margin: 0 0 4px 0;
}

.activity-time {
  font-size: 12px;
  color: #9ca3af;
}

/* Progress Chart */
.progress-chart {
  margin: 20px 0;
}

.chart-bar {
  display: flex;
  height: 40px;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 12px;
}

.bar-segment {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  padding: 0 8px;
}

.bar-segment.ongoing {
  background: #2563eb;
}

.bar-segment.completed {
  background: #10b981;
}

.bar-segment.near {
  background: #f59e0b;
}

/* Near Completion */
.near-completion {
  margin-top: 24px;
}

.near-completion h3 {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 16px 0;
}

.intern-progress {
  margin-bottom: 16px;
}

.intern-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.intern-name {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}

.intern-date {
  font-size: 12px;
  color: #6b7280;
}

.progress-bar-wrapper {
  position: relative;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.progress-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s;
}

.progress-bar.near {
  background: #f59e0b;
}

.progress-text {
  position: absolute;
  right: 4px;
  top: -18px;
  font-size: 11px;
  color: #6b7280;
  font-weight: 600;
}

.view-all-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 16px;
  color: #2563eb;
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  transition: color 0.2s;
}

.view-all-link:hover {
  color: #1d4ed8;
}

/* Notifications */
.notification-badge {
  background: #ef4444;
  color: #fff;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
}

.notifications-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.notification-item {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 12px;
  border-radius: 8px;
  transition: all 0.2s ease;
}

.notification-item:hover {
  background: #f9fafb;
  transform: translateX(4px);
}

.notification-item.unread {
  background: #eff6ff;
}

.notification-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
  border-radius: 10px;
  flex-shrink: 0;
  color: #4b5563;
  transition: all 0.2s ease;
}

.notification-item:hover .notification-icon {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  color: #1e40af;
  transform: scale(1.05);
}

.notification-content {
  flex: 1;
}

.notification-text {
  font-size: 14px;
  color: #374151;
  margin: 0 0 4px 0;
}

.notification-time {
  font-size: 12px;
  color: #9ca3af;
  display: block;
  margin-bottom: 8px;
}

.notification-action {
  color: #2563eb;
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  transition: color 0.2s;
}

.notification-action:hover {
  color: #1d4ed8;
}

/* Responsive */
@media (max-width: 1024px) {
  .content-grid {
    grid-template-columns: 1fr;
  }

  .summary-cards {
    grid-template-columns: 1fr;
  }
}

.placeholder-content {
  text-align: center;
  padding: 60px 20px;
  max-width: 600px;
  margin: 0 auto;
}

.placeholder-content h2 {
  font-size: 28px;
  color: #111827;
  margin-bottom: 16px;
}

.placeholder-content p {
  font-size: 16px;
  color: #6b7280;
  margin-bottom: 32px;
}

.placeholder-actions {
  display: flex;
  gap: 16px;
  justify-content: center;
  flex-wrap: wrap;
}

@media (max-width: 768px) {
  .sidebar {
    width: 200px;
  }

  .main-wrapper {
    margin-left: 0;
  }
}

/* Internships View Styles */
.internships-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

.filters-column {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  height: fit-content;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.filters-column:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  border-color: #cbd5e1;
}

.filters-title {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 24px 0;
}

.filter-group {
  margin-bottom: 24px;
}

.filter-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 8px;
}

.filter-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #f9fafb;
  transition: all 0.2s;
  box-sizing: border-box;
}

.filter-input:focus {
  outline: none;
  border-color: #2563eb;
  background: #fff;
}

.date-inputs {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.date-input {
  width: 100%;
  position: relative;
  padding-right: 40px;
}

.date-input::-webkit-calendar-picker-indicator {
  position: absolute;
  right: 12px;
  cursor: pointer;
}

.selected-skills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}

.skill-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #2563eb;
  color: #fff;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
}

.skill-remove {
  background: none;
  border: none;
  color: #fff;
  font-size: 18px;
  cursor: pointer;
  padding: 0;
  width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  transition: opacity 0.2s;
}

.skill-remove:hover {
  opacity: 0.7;
}

.no-skills {
  font-size: 13px;
  color: #9ca3af;
  margin: 8px 0 0 0;
}

.checkbox-group label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #374151;
  cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: #2563eb;
}

.filter-buttons {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 24px;
}

.btn-apply-filters {
  width: 100%;
  padding: 12px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-apply-filters:hover {
  background: #1d4ed8;
}

.btn-clear-all {
  width: 100%;
  padding: 12px;
  background: #fff;
  color: #2563eb;
  border: 1px solid #2563eb;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-clear-all:hover {
  background: #eff6ff;
}

.internships-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.internships-header {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.internships-title {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.search-section {
  display: flex;
  gap: 12px;
  align-items: center;
}

.search-wrapper {
  flex: 1;
  position: relative;
}

.search-icon {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  display: flex;
  align-items: center;
  justify-content: center;
}

.search-input {
  width: 100%;
  padding: 12px 16px 12px 44px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #f9fafb;
  transition: all 0.2s;
  box-sizing: border-box;
}

.search-input:focus {
  outline: none;
  border-color: #2563eb;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.btn-post-internship {
  padding: 12px 20px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.2s;
}

.btn-post-internship:hover {
  background: #1d4ed8;
}

.internships-table-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.internships-table-wrap { overflow-x: auto; position: relative; }

.internships-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  min-width: 760px;
  table-layout: fixed;
}

.internships-table .col-internship { width: 34%; }
.internships-table .col-location { width: 20%; }
.internships-table .col-duration { width: 10%; }
.internships-table .col-slots { width: 7%; }
.internships-table .col-status { width: 10%; }
.internships-table .col-posted { width: 9%; }
.internships-table .col-actions { width: 10%; }

.internships-table thead th {
  background: #f8fafc;
  color: #334155;
  font-size: 0.78rem;
  font-weight: 900;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 12px 14px;
  text-align: left;
  border-bottom: 1px solid #eef2f7;
}

.internships-row td {
  padding: 12px 14px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: top;
  color: #0f172a;
  font-size: 0.9rem;
}

.internships-row:hover td { background: #fafcff; }

.internship-main-cell { min-width: 260px; }
.internship-main-title { font-weight: 900; color: #0f172a; }
.internship-main-sub {
  margin-top: 4px;
  color: #64748b;
  font-size: 0.85rem;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.internship-cell { color: #0f172a; overflow: hidden; text-overflow: ellipsis; }
.internship-cell.nowrap { white-space: nowrap; }

/* Keep the main "Internship" column visible at 100% scaling. */
.internships-table thead th:first-child,
.internships-row td:first-child {
  position: sticky;
  left: 0;
  z-index: 3;
  background: #fff;
}
.internships-table thead th:first-child {
  background: #f8fafc;
  z-index: 5;
}
.internships-row:hover td:first-child { background: #fafcff; }
.internships-row td:first-child { box-shadow: 10px 0 18px rgba(15, 23, 42, 0.04); }

/* Optional: keep Actions visible too. */
.internships-table thead th:last-child,
.internships-row td:last-child {
  position: sticky;
  right: 0;
  z-index: 4;
  background: #fff;
}
.internships-table thead th:last-child {
  background: #f8fafc;
  z-index: 6;
}
.internships-row:hover td:last-child { background: #fafcff; }
.internships-row td:last-child { box-shadow: -10px 0 18px rgba(15, 23, 42, 0.04); }

.internship-status {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 900;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  border: 1px solid transparent;
}
.internship-status.active { background: #dcfce7; color: #166534; border-color: rgba(22, 101, 52, 0.15); }
.internship-status.draft { background: #fef3c7; color: #92400e; border-color: rgba(146, 64, 14, 0.18); }
.internship-status.closed { background: #fee2e2; color: #991b1b; border-color: rgba(153, 27, 27, 0.18); }

.internships-actions-col { width: 1%; white-space: nowrap; }
.internship-actions-cell { white-space: nowrap; vertical-align: middle; }
.internship-action-buttons { display: inline-flex; gap: 8px; flex-wrap: nowrap; }
.internship-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 9px;
  border-radius: 10px;
  border: 1px solid #cbd5e1;
  background: #fff;
  cursor: pointer;
  font-weight: 900;
  font-size: 0.82rem;
  color: #0f172a;
}
.internship-action-btn:hover { background: #f8fafc; border-color: #94a3b8; }
.internship-action-btn.view { background: #f8fafc; }
.internship-action-btn.edit { color: #1d4ed8; border-color: rgba(37, 99, 235, 0.25); background: rgba(37, 99, 235, 0.06); }
.internship-action-btn.edit:hover { background: rgba(37, 99, 235, 0.10); }
.internship-action-btn.delete { color: #991b1b; border-color: rgba(220, 38, 38, 0.25); background: rgba(220, 38, 38, 0.06); }
.internship-action-btn.delete:hover { background: rgba(220, 38, 38, 0.10); }
.internship-action-icon { width: 16px; height: 16px; }
.internship-action-text { display: inline; }

.internships-empty { padding: 22px 14px; color: #64748b; text-align: center; }

@media (max-width: 1280px) {
  /* Free up space: hide Posted column, make actions icon-only. */
  .internships-table thead th:nth-child(6),
  .internships-row td:nth-child(6) {
    display: none;
  }

  .internship-action-text { display: none; }
  .internship-action-btn { padding: 7px 8px; }
}

@media (max-width: 1100px) {
  /* Further compress on smaller widths. */
  .internships-table thead th:nth-child(3),
  .internships-row td:nth-child(3) {
    display: none;
  }

  .internships-table { min-width: 640px; }
}

.internships-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 24px;
}

.company-internship-card {
  background: white;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  overflow: hidden;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.company-internship-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  border-color: #d1d5db;
}

.internship-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 20px 20px 16px 20px;
  border-bottom: 1px solid #f3f4f6;
}

.internship-title-section {
  flex: 1;
}

.internship-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
  line-height: 1.3;
}

.internship-meta {
  display: flex;
  align-items: center;
  gap: 12px;
}

.posted-date {
  font-size: 13px;
  color: #6b7280;
}

.status-indicator {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-indicator.active {
  background: #dcfce7;
  color: #166534;
}

.status-indicator.draft {
  background: #fef3c7;
  color: #92400e;
}

.status-indicator.closed {
  background: #fee2e2;
  color: #991b1b;
}

.internship-actions-menu {
  margin-left: 12px;
}

.action-menu-btn {
  background: none;
  border: none;
  padding: 6px;
  border-radius: 6px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.action-menu-btn:hover {
  background: #f3f4f6;
}

.menu-icon {
  width: 18px;
  height: 18px;
  color: #6b7280;
}

.draft-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  background: #fffbeb;
  border-bottom: 1px solid #fde68a;
  color: #92400e;
  font-size: 14px;
}

.draft-icon {
  width: 16px;
  height: 16px;
  color: #f59e0b;
}

.internship-details {
  padding: 20px;
}

.detail-row {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 16px;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: #6b7280;
}

.detail-icon {
  width: 16px;
  height: 16px;
  color: #9ca3af;
}

.internship-description {
  font-size: 14px;
  color: #374151;
  line-height: 1.5;
  margin-bottom: 16px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.requirements-section {
  margin-top: 16px;
}

.requirements-label {
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  display: block;
  margin-bottom: 8px;
}

.skills-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.skill-chip {
  background: #eff6ff;
  color: #1d4ed8;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
}

.more-skills {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
}

.internship-stats {
  display: flex;
  justify-content: space-around;
  padding: 16px 20px;
  background: #f9fafb;
  border-top: 1px solid #f3f4f6;
  border-bottom: 1px solid #f3f4f6;
}

.stat-item {
  text-align: center;
}

.stat-number {
  display: block;
  font-size: 20px;
  font-weight: 700;
  color: #1f2937;
  line-height: 1;
}

.stat-label {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
  margin-top: 4px;
}

.internship-footer {
  padding: 16px 20px;
}

.footer-actions {
  display: flex;
  gap: 8px;
}

.btn-secondary-small {
  flex: 1;
  background: white;
  color: #374151 !important;
  border: 1px solid #d1d5db;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-secondary-small:hover {
  background: #f9fafb;
  border-color: #9ca3af;
  color: #1f2937 !important;
}

.btn-primary-small {
  flex: 1;
  background: #2563eb;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}

.btn-primary-small:hover {
  background: #1d4ed8;
}

@media (max-width: 1200px) {
  .internships-layout {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .company-internship-card {
    margin: 0 -4px;
  }
  
  .internship-header {
    padding: 16px;
  }
  
  .internship-details {
    padding: 16px;
  }
  
  .detail-row {
    flex-direction: column;
    gap: 8px;
  }
  
  .internship-stats {
    padding: 12px 16px;
  }
  
  .internship-footer {
    padding: 12px 16px;
  }
  
  .footer-actions {
    flex-direction: column;
  }
}

/* Create Internship Form Styles */
.create-form-container {
  max-width: 900px;
  margin: 0 auto;
  background: #fff;
  border-left: 4px solid #dbeafe;
  border-right: 4px solid #dbeafe;
  padding: 40px;
  border-radius: 8px;
}

.form-section {
  margin-bottom: 40px;
}

.section-heading {
  font-size: 18px;
  font-weight: 700;
  color: #2563eb;
  margin: 0 0 24px 0;
}

.form-group {
  margin-bottom: 24px;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 8px;
}

.form-input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
  transition: all 0.2s;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Checkbox Styles */
.checkbox-group {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.checkbox-label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  user-select: none;
  line-height: 1.4;
}

.form-checkbox {
  width: 16px;
  height: 16px;
  accent-color: #2563eb;
  cursor: pointer;
}

.form-checkbox {
  width: 18px;
  height: 18px;
  accent-color: #2563eb;
  cursor: pointer;
  flex-shrink: 0;
}

.checkbox-label {
  font-size: 16px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  user-select: none;
  line-height: 1.5;
  white-space: nowrap;
}

/* Allowance Section - Clean Layout */
.allowance-container {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fafafa;
  padding: 16px;
}

.allowance-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.allowance-label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  user-select: none;
}

.allowance-input-section {
  margin-left: 28px;
  animation: slideDown 0.2s ease-out;
}

.allowance-input-section .form-label {
  display: block;
  margin-bottom: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.allowance-input-section .form-input {
  width: 100%;
  max-width: 300px;
  background: white;
  border: 1px solid #d1d5db;
  padding: 10px 12px;
  border-radius: 6px;
  font-size: 14px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.allowance-input-section .form-input:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Responsive Design */
@media (max-width: 768px) {
  .contact-method-input {
    margin-left: 0;
    margin-top: 12px;
  }
  
  .allowance-input-section {
    margin-left: 0;
    margin-top: 12px;
  }
  
  .contact-method-input .form-input,
  .allowance-input-section .form-input {
    max-width: none;
  }
  
  .contact-methods-container,
  .allowance-container {
    padding: 12px;
  }
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Contact Method Styles */
.form-description {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 20px;
  line-height: 1.5;
}

/* Form Group Spacing */
.form-group {
  margin-bottom: 24px;
}

.form-group:last-child {
  margin-bottom: 0;
}

.form-description {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 16px;
  line-height: 1.5;
}

/* General Form Input Styling */
.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: white;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 8px;
}

/* Contact Methods - Clean Layout */
.contact-methods-container {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fafafa;
  padding: 16px;
}

.contact-method-row {
  margin-bottom: 16px;
}

.contact-method-row:last-child {
  margin-bottom: 0;
}

.contact-method-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.contact-method-label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  user-select: none;
  min-width: 100px;
}

.contact-method-input {
  margin-left: 28px;
  animation: slideDown 0.2s ease-out;
}

.contact-method-input .form-input {
  width: 100%;
  max-width: 400px;
  background: white;
  border: 1px solid #d1d5db;
  padding: 10px 12px;
  border-radius: 6px;
  font-size: 14px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.contact-method-input .form-input:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
  transition: all 0.2s;
  box-sizing: border-box;
  font-family: inherit;
  resize: vertical;
  min-height: 120px;
}

.form-textarea:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.course-hours-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.course-hours-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
}

.course-hours-name {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}

.course-hours-input {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 240px;
}

/* Rich Text Editor */
.rich-text-editor {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
}

.editor-toolbar {
  display: flex;
  gap: 8px;
  padding: 8px 12px;
  background: #1e40af;
  border-bottom: 1px solid #e5e7eb;
}

.toolbar-btn {
  width: 32px;
  height: 32px;
  background: transparent;
  border: none;
  color: #fff;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  border-radius: 4px;
  transition: background 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.toolbar-btn:hover {
  background: rgba(255, 255, 255, 0.1);
}

.editor-textarea {
  width: 100%;
  min-height: 200px;
  padding: 16px;
  border: none;
  font-size: 14px;
  font-family: inherit;
  resize: vertical;
  box-sizing: border-box;
}

.editor-textarea:focus {
  outline: none;
}

.char-counter {
  text-align: right;
  padding: 8px 16px;
  font-size: 12px;
  color: #9ca3af;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
}

/* Skills Section */
.skills-container {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skills-tags-display {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.skill-tag-display {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: #2563eb;
  color: #fff;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 500;
}

.skill-remove-btn {
  background: none;
  border: none;
  color: #fff;
  font-size: 18px;
  cursor: pointer;
  padding: 0;
  width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  border-radius: 50%;
  transition: background 0.2s;
}

.skill-remove-btn:hover {
  background: rgba(255, 255, 255, 0.2);
}

.skill-input {
  margin-top: 0;
}

/* Date Inputs */
.date-inputs-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.date-input-group {
  display: flex;
  flex-direction: column;
}

.date-label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 8px;
}

.date-input-wrapper {
  position: relative;
}

.date-input {
  padding-right: 40px;
}

.date-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: #6b7280;
  display: flex;
  align-items: center;
  justify-content: center;
}

.calendar-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 18px;
  pointer-events: none;
}

/* Form Actions */
.form-actions {
  display: flex;
  gap: 16px;
  justify-content: flex-end;
  margin-top: 32px;
  padding-top: 24px;
  border-top: 1px solid #e5e7eb;
}

.btn-save-draft {
  padding: 12px 24px;
  background: #fff;
  color: #2563eb;
  border: 1px solid #2563eb;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-save-draft:hover {
  background: #eff6ff;
}

.btn-submit {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: #1e40af;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-submit:hover {
  background: #1e3a8a;
}

.submit-icon {
  font-size: 16px;
}

@media (max-width: 768px) {
  .create-form-container {
    padding: 24px;
  }

  .date-inputs-row {
    grid-template-columns: 1fr;
  }

  .form-actions {
    flex-direction: column;
  }

  .btn-save-draft,
  .btn-submit {
    width: 100%;
  }
}

/* Reports View Styles */
.summary-cards-reports {
  display: flex;
  gap: 16px;
  margin-bottom: 32px;
}

.summary-card-report-combined {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
  display: flex;
  justify-content: space-around;
  align-items: center;
  width: 100%;
  gap: 32px;
}

.summary-card-report-combined:hover {
  border-color: #2563eb;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
  transform: translateY(-2px);
}

.metric-item {
  text-align: center;
  flex: 1;
}

.card-number {
  font-size: 36px;
  font-weight: 700;
  color: #1e3a8a;
  margin-bottom: 8px;
}

.card-label {
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
  text-transform: capitalize;
}

.reports-layout {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 24px;
}

.reports-left-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.report-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.report-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  transform: translateY(-2px);
  border-color: #cbd5e1;
}

.report-card-header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 20px;
}

.report-icon {
  font-size: 24px;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #eff6ff;
  border-radius: 8px;
  flex-shrink: 0;
}

.report-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 4px 0;
}

.report-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.attendance-table {
  margin: 20px 0;
  overflow-x: auto;
}

.attendance-table table {
  width: 100%;
  border-collapse: collapse;
}

.attendance-table th {
  text-align: left;
  padding: 12px;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  border-bottom: 1px solid #e5e7eb;
}

.attendance-table td {
  padding: 12px;
  font-size: 14px;
  color: #374151;
  border-bottom: 1px solid #f3f4f6;
}

.status-badge {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.status-badge.present {
  background: #d1fae5;
  color: #065f46;
}

.journal-list {
  margin: 20px 0;
}

.journal-item {
  display: flex;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid #f3f4f6;
}

.journal-item:last-child {
  border-bottom: none;
}

.journal-week {
  font-weight: 600;
  color: #2563eb;
  font-size: 14px;
  min-width: 70px;
}

.journal-content {
  flex: 1;
}

.journal-title {
  font-size: 14px;
  color: #111827;
  margin-bottom: 4px;
}

.journal-date {
  font-size: 12px;
  color: #6b7280;
}

.performance-list {
  margin: 20px 0;
}

.performance-item {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.performance-label {
  font-size: 14px;
  color: #374151;
  min-width: 140px;
}

.performance-bar-wrapper {
  flex: 1;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.performance-bar {
  height: 100%;
  background: #2563eb;
  border-radius: 4px;
  transition: width 0.3s;
}

.performance-rating {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  min-width: 40px;
  text-align: right;
}

.report-card-actions {
  display: flex;
  gap: 12px;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
}

.btn-outline {
  padding: 10px 20px;
  background: #fff;
  color: #2563eb;
  border: 1px solid #2563eb;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-outline:hover {
  background: #eff6ff;
}

.btn-primary {
  padding: 10px 20px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-primary:hover {
  background: #1d4ed8;
}

.report-card-footer {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid #f3f4f6;
  font-size: 12px;
  color: #9ca3af;
  text-align: center;
}

.reports-right-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.certificate-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.certificate-section:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  transform: translateY(-2px);
  border-color: #cbd5e1;
}

.certificate-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 16px 0;
}

.status-pill {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  margin-bottom: 20px;
}

.status-pill.completed {
  background: #d1fae5;
  color: #065f46;
}

.certificate-preview {
  background: #f9fafb;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  padding: 32px;
  margin-bottom: 20px;
  position: relative;
}

.certificate-content {
  text-align: center;
  position: relative;
}

.certificate-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.certificate-text {
  color: #111827;
}

.certificate-header {
  font-size: 16px;
  font-weight: 700;
  letter-spacing: 2px;
  margin-bottom: 24px;
  color: #1e40af;
}

.certificate-name {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 8px;
}

.certificate-degree {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 16px;
}

.certificate-school {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 16px;
}

.certificate-internship {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 8px;
}

.certificate-company {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 16px;
}

.certificate-period {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 8px;
}

.certificate-hours {
  font-size: 14px;
  font-weight: 600;
  color: #2563eb;
  margin-top: 16px;
}

.certificate-actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 16px;
}

.certificate-actions .btn-primary,
.certificate-actions .btn-outline {
  width: 100%;
}

.certificate-verified {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #6b7280;
  text-align: center;
  justify-content: center;
}

.lock-icon {
  font-size: 14px;
}

.student-info-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.student-info-section:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  transform: translateY(-2px);
  border-color: #cbd5e1;
}

.section-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 20px 0;
}

.info-item {
  display: flex;
  justify-content: space-between;
  padding: 12px 0;
  border-bottom: 1px solid #f3f4f6;
}

.info-item:last-child {
  border-bottom: none;
}

.info-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

.info-value {
  font-size: 14px;
  color: #111827;
  font-weight: 600;
  text-align: right;
}

.info-value.verified {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #10b981;
}

.check-icon {
  font-size: 16px;
}

.honors-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.honors-section:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  transform: translateY(-2px);
  border-color: #cbd5e1;
}

.honors-badges {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.honor-badge {
  display: inline-block;
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  text-align: center;
}

.honor-badge.perfect {
  background: #d1fae5;
  color: #065f46;
}

.honor-badge.excellent {
  background: #e9d5ff;
  color: #6b21a8;
}

.honor-badge.complete {
  background: #e9d5ff;
  color: #6b21a8;
}

@media (max-width: 1200px) {
  .reports-layout {
    grid-template-columns: 1fr;
  }

  .summary-cards-reports {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .summary-cards-reports {
    grid-template-columns: 1fr;
  }

  .attendance-table {
    font-size: 12px;
  }

  .attendance-table th,
  .attendance-table td {
    padding: 8px;
  }
}

/* Applications View Styles - Modern Design */
.applications-layout {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

/* Dashboard Header */
.applications-dashboard-header {
  margin-bottom: 32px;
}

.dashboard-title-section {
  margin-bottom: 24px;
}

.dashboard-title {
  font-size: 28px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.dashboard-subtitle {
  font-size: 16px;
  color: #6b7280;
  margin: 0;
}

/* Statistics Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.stat-card-modern {
  background: white;
  border-radius: 16px;
  padding: 24px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 16px;
  transition: all 0.2s ease;
}

.stat-card-modern:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

.stat-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon-wrapper.total {
  background: #dbeafe;
}

.stat-icon-wrapper.pending {
  background: #fef3c7;
}

.stat-icon-wrapper.accepted {
  background: #dcfce7;
}

.stat-icon-wrapper.declined {
  background: #fee2e2;
}

.stat-icon {
  width: 24px;
  height: 24px;
}

.stat-icon-wrapper.total .stat-icon {
  color: #2563eb;
}

.stat-icon-wrapper.pending .stat-icon {
  color: #d97706;
}

.stat-icon-wrapper.accepted .stat-icon {
  color: #059669;
}

.stat-icon-wrapper.declined .stat-icon {
  color: #dc2626;
}

.stat-content {
  flex: 1;
}

.stat-number {
  font-size: 32px;
  font-weight: 700;
  color: #1f2937;
  line-height: 1;
  margin-bottom: 4px;
}

.stat-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

/* Controls Section */
.applications-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  gap: 20px;
}

.controls-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.controls-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.modern-select {
  padding: 12px 16px;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  font-size: 14px;
  background: white;
  color: #1f2937;
  cursor: pointer;
  transition: all 0.2s;
  min-width: 200px;
}

.modern-select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.search-wrapper {
  position: relative;
  min-width: 300px;
}

.search-icon-modern {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  width: 20px;
  height: 20px;
  color: #9ca3af;
  pointer-events: none;
}

.search-input-modern {
  width: 100%;
  padding: 12px 16px 12px 48px;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  font-size: 14px;
  background: white;
  transition: all 0.2s;
  box-sizing: border-box;
}

.search-input-modern:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Applications Grid */
.applications-grid-modern {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
  gap: 24px;
}

.empty-state-modern {
  grid-column: 1 / -1;
  text-align: center;
  padding: 80px 20px;
  background: white;
  border-radius: 16px;
  border: 1px solid #e5e7eb;
}

.empty-icon-modern {
  margin: 0 auto 20px;
  width: 80px;
  height: 80px;
  background: #f3f4f6;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-icon-svg-modern {
  width: 40px;
  height: 40px;
  color: #9ca3af;
}

.empty-title-modern {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.empty-description-modern {
  font-size: 16px;
  color: #6b7280;
  margin: 0;
}

/* Application Cards */
.application-card-modern {
  background: white;
  border-radius: 16px;
  border: 1px solid #e5e7eb;
  overflow: hidden;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.application-card-modern:hover {
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  transform: translateY(-2px);
  border-color: #d1d5db;
}

.card-header-modern {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 24px 20px 24px;
  border-bottom: 1px solid #f3f4f6;
}

.student-profile {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
}

.student-avatar-modern {
  width: 56px;
  height: 56px;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: white;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 20px;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.student-info-modern {
  flex: 1;
}

.student-name-modern {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.student-course-modern {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.status-wrapper {
  margin-left: 16px;
}

.status-badge-modern {
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}

.status-badge-modern.pending {
  background: #fef3c7;
  color: #92400e;
}

.status-badge-modern.accepted {
  background: #dcfce7;
  color: #166534;
}

.status-badge-modern.declined {
  background: #fee2e2;
  color: #991b1b;
}

.card-body-modern {
  padding: 24px;
}

.application-info {
  margin-bottom: 20px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.info-row:last-child {
  margin-bottom: 0;
}

.info-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

.info-value {
  font-size: 14px;
  color: #1f2937;
  font-weight: 600;
}

.skills-match-modern {
  display: flex;
  align-items: center;
  gap: 12px;
}

.match-progress {
  width: 100px;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.match-bar-modern {
  height: 100%;
  background: linear-gradient(90deg, #10b981, #059669);
  border-radius: 4px;
  transition: width 0.3s;
}

.match-text {
  font-size: 12px;
  font-weight: 600;
  color: #059669;
}

.documents-section {
  padding-top: 16px;
  border-top: 1px solid #f3f4f6;
}

.document-item-modern {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.document-icon-modern {
  width: 20px;
  height: 20px;
  color: #6b7280;
}

.document-name-modern {
  flex: 1;
  font-size: 14px;
  color: #374151;
  font-weight: 500;
}

.download-btn-modern {
  background: none;
  border: none;
  color: #2563eb;
  cursor: pointer;
  padding: 8px;
  border-radius: 6px;
  transition: all 0.2s;
}

.download-btn-modern:hover {
  background: #eff6ff;
}

.download-icon-modern {
  width: 18px;
  height: 18px;
}

.card-actions-modern {
  display: flex;
  gap: 12px;
  padding: 20px 24px;
  border-top: 1px solid #f3f4f6;
  background: #fafbfc;
}

.action-btn-secondary {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 16px;
  background: white;
  color: #374151;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn-secondary:hover {
  background: #f9fafb;
  border-color: #9ca3af;
  transform: translateY(-1px);
}

.action-btn-decline {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 16px;
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn-decline:hover:not(.disabled) {
  background: #fecaca;
  border-color: #f87171;
  transform: translateY(-1px);
}

.action-btn-accept {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 16px;
  background: #dcfce7;
  color: #166534;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn-accept:hover:not(.disabled) {
  background: #bbf7d0;
  border-color: #86efac;
  transform: translateY(-1px);
}

.action-btn-decline.disabled,
.action-btn-accept.disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.action-icon-modern {
  width: 18px;
  height: 18px;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .applications-grid-modern {
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  }
  
  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  }
  
  .applications-controls {
    flex-direction: column;
    align-items: stretch;
    gap: 16px;
  }
  
  .controls-left,
  .controls-right {
    justify-content: stretch;
  }
  
  .search-wrapper {
    min-width: unset;
  }
}

@media (max-width: 768px) {
  .applications-layout {
    padding: 16px;
  }
  
  .applications-grid-modern {
    grid-template-columns: 1fr;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .stat-card-modern {
    padding: 20px;
  }
  
  .card-actions-modern {
    flex-direction: column;
    gap: 8px;
  }
  
  .dashboard-title {
    font-size: 24px;
  }
  
  .stat-number {
    font-size: 28px;
  }
}

/* Journals View (Journal Overview) */
.btn-add-journal {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 999px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.2s;
  margin-right: 8px;
}

.btn-add-journal .add-icon {
  width: 20px;
  height: 20px;
  border: 1.5px solid currentColor;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  line-height: 1;
}

.btn-add-journal:hover {
  background: #1d4ed8;
}

.journals-calendars {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  margin-bottom: 24px;
}

.journal-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.journal-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.journal-card-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.calendar-nav {
  display: flex;
  gap: 4px;
}

.cal-nav-btn {
  background: none;
  border: 1px solid #e5e7eb;
  color: #374151;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.cal-nav-btn:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}

.calendar-grid {
  display: grid;
  gap: 4px;
}

.month-grid {
  grid-template-columns: repeat(7, 1fr);
}

.month-grid .cal-head {
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  padding: 6px 0;
}

.month-grid .cal-cell {
  aspect-ratio: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: 8px;
  min-height: 36px;
}

.month-grid .cal-cell.other-month .cal-num {
  color: #d1d5db;
}

.cal-num {
  font-size: 14px;
  font-weight: 500;
  color: #111827;
}

.cal-dots {
  display: flex;
  gap: 2px;
  justify-content: center;
  flex-wrap: wrap;
  margin-top: 2px;
}

.cal-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.cal-dot.blue {
  background: #3b82f6;
}

.cal-dot.green {
  background: #10b981;
}

.cal-dot.orange {
  background: #f59e0b;
}

.week-grid {
  grid-template-columns: repeat(7, 1fr);
}

.week-grid .cal-head {
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  padding: 8px 0;
}

.week-grid .cal-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 12px 8px;
  border-radius: 8px;
}

.journal-activity-card {
  padding: 24px;
}

.journal-activity-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 20px 0;
}

.journal-activity-list {
  display: flex;
  flex-direction: column;
}

.journal-activity-item {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 16px 0;
  border-bottom: 1px solid #f3f4f6;
}

.journal-activity-item:last-child {
  border-bottom: none;
}

.journal-entry-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}

.journal-entry-icon.completed {
  background: #dbeafe;
  color: #1e40af;
}

.journal-entry-icon.pending {
  background: #fef3c7;
  color: #b45309;
}

.journal-entry-body {
  flex: 1;
  min-width: 0;
}

.journal-entry-title {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 4px;
}

.journal-entry-desc {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 6px;
}

.journal-entry-status {
  font-size: 13px;
  font-weight: 600;
}

.journal-entry-status.approved {
  color: #059669;
}

.journal-entry-status.pending {
  color: #b45309;
}

.journal-entry-date {
  font-size: 14px;
  color: #6b7280;
  white-space: nowrap;
  flex-shrink: 0;
}

@media (max-width: 1024px) {
  .journals-calendars {
    grid-template-columns: 1fr;
  }
}

/* Add Journal Entry panel */
.add-journal-layer {
  position: fixed;
  inset: 0;
  z-index: 1500;
}

.add-journal-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.25);
  cursor: pointer;
  animation: add-journal-fade-in 0.2s ease-out;
}

.add-journal-panel {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(480px, 50vw);
  max-width: 95vw;
  background: #fff;
  box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: add-journal-slide-in 0.25s ease-out;
}

@keyframes add-journal-fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes add-journal-slide-in {
  from { transform: translateX(100%); }
  to { transform: translateX(0); }
}

.add-journal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.add-journal-title {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.add-journal-close {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #374151;
  font-size: 20px;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.add-journal-close:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}

.add-journal-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.add-journal-field {
  margin-bottom: 20px;
}

.add-journal-field:last-child {
  margin-bottom: 0;
}

.add-journal-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;
}

.add-journal-input-wrap {
  position: relative;
}

.add-journal-input {
  width: 100%;
  padding: 10px 40px 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
  transition: all 0.2s;
  box-sizing: border-box;
}

.add-journal-input:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.add-journal-input-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: #6b7280;
  display: flex;
  align-items: center;
  justify-content: center;
}

.add-journal-textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  resize: vertical;
  min-height: 100px;
  box-sizing: border-box;
  transition: all 0.2s;
}

.add-journal-textarea:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.add-journal-textarea::placeholder {
  color: #9ca3af;
}

.add-journal-actions {
  display: flex;
  gap: 12px;
  padding: 20px 24px;
  border-top: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.add-journal-btn {
  flex: 1;
  padding: 12px 20px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.add-journal-btn-draft {
  background: #fff;
  color: #2563eb;
  border: 1px solid #2563eb;
}

.add-journal-btn-draft:hover {
  background: #eff6ff;
}

.add-journal-btn-submit {
  background: #2563eb;
  color: #fff;
  border: none;
}

.add-journal-btn-submit:hover {
  background: #1d4ed8;
}

/* Dashboard Settings panel */
.settings-main {
  min-height: 200px;
}

.settings-placeholder {
  color: #6b7280;
  font-size: 14px;
  margin: 0;
}

.settings-panel-layer {
  position: fixed;
  inset: 0;
  z-index: 1500;
}

.settings-panel-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.25);
  cursor: pointer;
  animation: add-journal-fade-in 0.2s ease-out;
}

.settings-panel {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(480px, 50vw);
  max-width: 95vw;
  background: #fff;
  box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 0 12px 0 0;
  animation: add-journal-slide-in 0.25s ease-out;
}

.settings-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.settings-panel-title {
  font-size: 20px;
  font-weight: 700;
  color: #1e40af;
  margin: 0;
}

.settings-panel-close {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: #2563eb;
  color: #fff;
  font-size: 20px;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}

.settings-panel-close:hover {
  background: #1d4ed8;
}

.settings-panel-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.settings-section {
  margin-bottom: 28px;
}

.settings-section:last-of-type {
  margin-bottom: 0;
}

.settings-section-title {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 16px 0;
}

.settings-toggle-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 16px;
}

.settings-toggle-row:last-child {
  margin-bottom: 0;
}

.settings-toggle-label {
  flex: 1;
  min-width: 0;
}

.settings-toggle-name {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 2px;
}

.settings-toggle-desc {
  display: block;
  font-size: 13px;
  color: #6b7280;
}

.settings-toggle {
  width: 48px;
  height: 26px;
  border-radius: 999px;
  border: none;
  background: #d1d5db;
  cursor: pointer;
  padding: 3px;
  flex-shrink: 0;
  transition: background 0.2s;
  position: relative;
}

.settings-toggle.on {
  background: #2563eb;
}

.settings-toggle-knob {
  display: block;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  transition: transform 0.2s;
  transform: translateX(0);
}

.settings-toggle.on .settings-toggle-knob {
  transform: translateX(22px);
}

.settings-field {
  margin-bottom: 16px;
}

.settings-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;
}

.settings-select {
  width: 100%;
  padding: 10px 36px 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
  color: #111827;
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  transition: all 0.2s;
  box-sizing: border-box;
}

.settings-select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.settings-checkbox-row {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: #374151;
  cursor: pointer;
  margin-bottom: 12px;
}

.settings-checkbox-row:last-child {
  margin-bottom: 0;
}

.settings-checkbox-row input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: #2563eb;
}

.settings-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
  margin-bottom: 10px;
}

.settings-btn:last-child {
  margin-bottom: 0;
}

.settings-btn-secondary {
  background: #2563eb;
  color: #fff;
}

.settings-btn-secondary:hover {
  background: #1d4ed8;
}

.settings-btn-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.settings-btn-row .settings-btn {
  margin-bottom: 0;
}

.settings-pill {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  background: #d1fae5;
  color: #065f46;
}

.settings-panel-actions {
  padding: 20px 24px;
  border-top: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.settings-btn-save {
  width: 100%;
  padding: 14px 20px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 15px;
  cursor: pointer;
  transition: background 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.settings-btn-save:hover {
  background: #1d4ed8;
}

/* New Settings Styles */
.header-icon {
  font-size: 20px;
  margin-right: 8px;
}

.settings-container {
  max-width: 1000px;
  margin: 0 auto;
  padding: 32px 24px;
}

.settings-main-title {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 32px 0;
}

.settings-card {
  background: white;
  border-radius: 12px;
  padding: 32px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
}

.password-section {
  max-width: 600px;
}

.password-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.password-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 32px 0;
}

.password-form {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.password-input-container {
  position: relative;
}

.form-input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.password-input {
  padding-right: 48px;
}

.form-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.password-toggle {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 16px;
  color: #6b7280;
  padding: 4px;
}

.password-strength {
  margin-top: 8px;
}

.strength-bar {
  width: 100%;
  height: 4px;
  background: #e5e7eb;
  border-radius: 2px;
  overflow: hidden;
  margin-bottom: 4px;
}

.strength-fill {
  height: 100%;
  border-radius: 2px;
  transition: width 0.3s ease;
}

.strength-fill.weak {
  width: 30%;
  background: #ef4444;
}

.strength-text {
  font-size: 12px;
  color: #ef4444;
}

.form-actions {
  display: flex;
  gap: 12px;
  margin-top: 16px;
}

.btn-cancel {
  padding: 10px 20px;
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-cancel:hover {
  background: #e5e7eb;
  color: #1f2937;
}

.btn-save {
  padding: 10px 20px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-save:hover {
  background: #2563eb;
}

.two-factor-section {
  max-width: 800px;
}

.two-factor-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.two-factor-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 32px 0;
}

.auth-options {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.auth-option {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 20px;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}

.auth-icon {
  width: 48px;
  height: 48px;
  background: #dbeafe;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  flex-shrink: 0;
}

.auth-content {
  flex: 1;
}

.auth-option-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.auth-option-desc {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
  line-height: 1.5;
}

/* Responsive */
@media (max-width: 768px) {
  .settings-container {
    padding: 24px 16px;
  }
  
  .settings-card {
    padding: 24px 16px;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .btn-cancel,
  .btn-save {
    width: 100%;
  }
  
  .auth-option {
    flex-direction: column;
    text-align: center;
  }
}

/* Progress Column - Sticky sidebar */
.progress-column {
  position: sticky;
  top: 100px;
  align-self: start;
  z-index: 0;
}

@media (max-width: 1200px) {
  .progress-column {
    position: static;
    top: auto;
    max-height: none;
    overflow: visible;
  }
}

.progress-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  position: static;
  top: auto;
}

.approval-status-card {
  margin-top: 16px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px 18px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.approval-status-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.approval-status-title { margin: 0; font-size: 14px; font-weight: 800; color: #111827; }
.approval-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  border: 1px solid transparent;
  white-space: nowrap;
}
.approval-status-icon { width: 14px; height: 14px; }
.approval-status-badge.approved { background: #dcfce7; color: #166534; border-color: rgba(22, 101, 52, 0.18); }
.approval-status-badge.declined { background: #fee2e2; color: #991b1b; border-color: rgba(153, 27, 27, 0.18); }
.approval-status-badge.pending { background: #fef3c7; color: #92400e; border-color: rgba(146, 64, 14, 0.18); }
.approval-status-badge.draft { background: #e2e8f0; color: #334155; border-color: rgba(51, 65, 85, 0.15); }

.approval-status-hint { margin: 10px 0 0 0; color: #64748b; font-size: 13px; line-height: 1.35; }
.approval-status-notes {
  margin: 10px 0 0 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #0f172a;
  font-size: 13px;
  line-height: 1.35;
}

.progress-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 20px;
}

.progress-steps {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.progress-step {
  display: flex;
  align-items: center;
  gap: 12px;
}

.step-indicator {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.progress-step.active .step-indicator {
  background: #2563eb;
  color: #fff;
}

.step-number {
  font-weight: 700;
  font-size: 16px;
}

.step-content {
  flex: 1;
}

.step-title {
  font-weight: 600;
  font-size: 14px;
  color: #111827;
  margin-bottom: 4px;
}

.step-status {
  font-size: 12px;
  color: #6b7280;
}

.step-status.completed {
  color: #10b981;
}

.step-status.in-progress {
  color: #2563eb;
}

.step-status.pending {
  color: #9ca3af;
}

/* Profile Cards */
.profile-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.profile-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.profile-card-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.card-edit-btn {
  background: transparent;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px;
  cursor: pointer;
  color: #6b7280;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.card-edit-btn:hover {
  background: #f3f4f6;
  border-color: #2563eb;
  color: #2563eb;
}

.profile-details {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f3f4f6;
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-label {
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
}

.detail-value {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}

/* Profile Header Stats */
.profile-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 32px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.profile-company-info {
  display: flex;
  gap: 20px;
  flex: 1;
}

.company-logo-large {
  width: 80px;
  height: 80px;
  border-radius: 12px;
  background: #2563eb;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  font-weight: 700;
  flex-shrink: 0;
}

.company-logo-large .logo-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 12px;
}

.company-details {
  flex: 1;
}

.company-name-large {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 4px 0;
}

.company-industry {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 16px 0;
}

.company-contact {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #374151;
}

.contact-icon {
  color: #6b7280;
  display: flex;
  align-items: center;
}

.profile-stats-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
  align-items: flex-end;
  max-width: 400px;
}

.profile-description-preview {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 16px;
  width: 100%;
}

.description-text {
  font-size: 14px;
  line-height: 1.6;
  color: #6b7280;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 4;
  line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.btn-edit-profile {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-edit-profile:hover:not(:disabled) {
  background: #1d4ed8;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-edit-profile:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.edit-icon {
  display: flex;
  align-items: center;
}

.profile-content-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 24px;
}

.profile-card-full {
  grid-column: 1 / -1;
}

.company-description {
  font-size: 14px;
  line-height: 1.6;
  color: #374151;
  margin: 0;
}

/* Tom Select Styles */
.form-select-multiple {
  width: 100%;
}

.form-hint {
  font-size: 13px;
  color: #6b7280;
  margin-top: 8px;
  font-style: italic;
}

.info-message {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  color: #1e40af;
  font-size: 14px;
  margin: 0;
}

.info-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  stroke-width: 2;
}

/* Tom Select Custom Styling */
:deep(.ts-wrapper) {
  width: 100%;
}

:deep(.ts-control) {
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
  min-height: 42px;
}

:deep(.ts-control:focus) {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

:deep(.ts-dropdown) {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

:deep(.ts-dropdown .option) {
  padding: 8px 12px;
  font-size: 14px;
}

:deep(.ts-dropdown .option.active) {
  background: #eff6ff;
  color: #2563eb;
}

:deep(.item) {
  background: #2563eb;
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 13px;
  margin: 2px;
}

:deep(.remove) {
  border-left: 1px solid rgba(255, 255, 255, 0.3);
  padding-left: 6px;
  margin-left: 6px;
}

.contracts-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.contracts-table-wrap { overflow-x: auto; }
.contracts-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.contracts-table th, .contracts-table td { padding: 10px 12px; border-bottom: 1px solid #eef2f7; text-align: left; font-size: 0.9rem; color: #111827; vertical-align: top; }
.contracts-table th { background: #f8fafc; font-weight: 700; color: #334155; font-size: 0.85rem; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 0.85rem; }
.actions-cell { display: flex; gap: 8px; flex-wrap: wrap; }
.table-btn { padding: 8px 10px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; font-weight: 700; cursor: pointer; font-size: 0.85rem; }
.table-btn:hover { background: #f8fafc; }
.table-btn.danger { border-color: rgba(220, 38, 38, 0.25); background: rgba(220, 38, 38, 0.08); color: #991b1b; }
.table-btn.ok { border-color: rgba(5, 150, 105, 0.25); background: rgba(5, 150, 105, 0.08); color: #065f46; }
.details-row td { background: #f8fafc; }
.details-panel { padding: 12px 10px; }
.details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 8px 12px; margin-bottom: 10px; color: #334155; font-size: 0.9rem; }

.contract-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.contract-doc-header {
  padding-bottom: 12px;
  margin-bottom: 12px;
  border-bottom: 2px solid #e5e7eb;
  text-align: center;
}

.contract-doc-title {
  display: block;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 1.2px;
  color: #374151;
}

.contract-doc-parties {
  display: block;
  font-size: 0.85rem;
  color: #6b7280;
  margin-top: 4px;
}

.contract-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.contract-info h3 {
  margin: 0;
  font-size: 1.05rem;
  color: #111827;
}

.contract-type {
  margin: 4px 0 8px 0;
  font-size: 0.82rem;
  font-weight: 600;
  color: #4b5563;
  text-transform: uppercase;
}

.contract-purpose {
  margin: 0 0 10px 0;
  font-size: 0.92rem;
  color: #374151;
}

.contract-dates {
  margin: 0 0 10px 0;
  font-size: 0.875rem;
  color: #6b7280;
}

.contract-section {
  margin: 0 0 10px 0;
}

.contract-section strong {
  display: block;
  font-size: 0.8rem;
  color: #6b7280;
  margin-bottom: 4px;
  text-transform: uppercase;
}

.contract-section p {
  margin: 0;
  font-size: 0.9rem;
  color: #374151;
  line-height: 1.5;
}

.contract-actions {
  display: flex;
  gap: 8px;
  margin: 10px 0 12px;
}

.contract-btn {
  border: none;
  border-radius: 8px;
  padding: 8px 14px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.contract-btn-accept {
  background: #059669;
  color: #fff;
}

.contract-btn-accept:hover {
  background: #047857;
}

.contract-btn-reject {
  background: #dc2626;
  color: #fff;
}

.contract-btn-reject:hover {
  background: #b91c1c;
}

/* Enhanced Internship Details Modal Styles */
.internship-details-modal {
  max-width: 900px;
  width: 95vw;
  max-height: 90vh;
  overflow-y: auto;
  border-radius: 20px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

/* Enhanced Modal Header */
.modal-header-enhanced {
  background: linear-gradient(135deg, #f8fafc, #f1f5f9);
  color: #1f2937;
  padding: 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header-content {
  display: flex;
  align-items: center;
  gap: 20px;
  flex: 1;
}

.company-avatar {
  width: 64px;
  height: 64px;
  background: #e5e7eb;
  border: 2px solid #d1d5db;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 700;
  color: #374151;
}

.company-avatar .avatar-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 14px;
}

.header-info {
  flex: 1;
}

.internship-title {
  font-size: 28px;
  font-weight: 700;
  margin: 0 0 8px 0;
  color: #1f2937;
  line-height: 1.2;
}

.company-info {
  display: flex;
  align-items: center;
  gap: 16px;
}

.company-name {
  font-size: 16px;
  font-weight: 500;
  color: #6b7280;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}

.status-badge.active {
  background: #dcfce7;
  color: #166534;
}

.status-badge.draft {
  background: #fef3c7;
  color: #92400e;
}

.status-badge.closed {
  background: #fee2e2;
  color: #991b1b;
}

.status-badge {
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #d1d5db;
}

.modal-close-enhanced {
  background: #f9fafb;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  padding: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.modal-close-enhanced:hover {
  background: #f3f4f6;
  transform: scale(1.05);
}

.close-icon {
  width: 24px;
  height: 24px;
  color: #374151;
}

/* Enhanced Modal Body */
.modal-body-enhanced {
  padding: 32px;
  overflow-y: auto;
  background: #fafbfc;
  max-height: calc(90vh - 200px);
}

/* Key Information Grid */
.key-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 32px;
}

.info-card {
  background: white;
  border-radius: 16px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
  transition: all 0.2s ease;
}

.info-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.info-card-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.info-card-icon.location {
  background: #2563eb;
  color: white;
}

.info-card-icon.duration {
  background: #ec4899;
  color: white;
}

/* Content Sections */
.content-section-modern {
  background: white;
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
}

.section-header-modern {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 2px solid #f3f4f6;
}

.section-icon-modern {
  width: 20px;
  height: 20px;
  color: #2563eb;
}

.section-title-modern {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

/* Description Card */
.description-card-modern {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
}

.description-card-modern p {
  font-size: 15px;
  line-height: 1.6;
  color: #374151;
  margin: 0;
}

/* Allowance Card */
.allowance-card-modern {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
  border-radius: 12px;
  padding: 24px;
  text-align: center;
}

.allowance-amount-modern {
  font-size: 28px;
  font-weight: 700;
  margin-bottom: 8px;
}

.allowance-label-modern {
  font-size: 14px;
  font-weight: 500;
  opacity: 0.9;
}

/* Programs and Skills Grid */
.programs-skills-grid-modern {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

/* Tags Container */
.tags-container-modern {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.program-tag-modern {
  background: #2563eb;
  color: white;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  transition: all 0.2s ease;
}

.program-tag-modern:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.skill-tag-modern {
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #e5e7eb;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s ease;
}

.skill-tag-modern:hover {
  background: #e5e7eb;
  border-color: #d1d5db;
  transform: translateY(-1px);
}

/* Contact Methods */
.contact-methods-card-modern {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
}

.contact-method-modern {
  margin-bottom: 16px;
}

.contact-method-modern:last-child {
  margin-bottom: 0;
}

.contact-item-modern {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: white;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.contact-icon-modern {
  width: 20px;
  height: 20px;
  color: #6b7280;
}

.contact-details-modern {
  flex: 1;
}

.contact-type-modern {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  margin-bottom: 2px;
}

.contact-value-modern {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

/* Timeline Card */
.timeline-card-modern {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
}

.timeline-item-modern {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 0;
}

.timeline-item-modern:not(:last-child) {
  border-bottom: 1px solid #e5e7eb;
}

.timeline-dot-modern {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.timeline-dot-modern.created {
  background: #10b981;
}

.timeline-dot-modern.updated {
  background: #f59e0b;
}

.timeline-content-modern {
  flex: 1;
}

.timeline-label-modern {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  margin-bottom: 2px;
}

.timeline-value-modern {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

/* Modal Footer */
.modal-footer-modern {
  padding: 24px 32px;
  background: white;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
}

.btn-edit-modern {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-edit-modern:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.btn-icon-modern {
  width: 18px;
  height: 18px;
}

/* Responsive Design */
@media (max-width: 768px) {
  .internship-details-modal-modern {
    width: 100vw;
    height: 100vh;
    max-height: 100vh;
    border-radius: 0;
  }
  
  .modal-header-modern {
    padding: 24px 20px;
  }
  
  .modal-body-modern {
    padding: 20px;
  }
  
  .key-info-grid-modern {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  
  .programs-skills-grid-modern {
    grid-template-columns: 1fr;
  }
  
  .internship-title-modern {
    font-size: 24px;
  }
}

.info-card-icon.type {
  background: #06b6d4;
  color: white;
}

.info-card-icon.slots {
  background: #10b981;
  color: white;
}

.info-card-icon svg {
  width: 24px;
  height: 24px;
}

.info-card-content {
  flex: 1;
}

.info-card-label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.info-card-value {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  line-height: 1.2;
}

/* Content Sections */
.content-section {
  background: white;
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 2px solid #f3f4f6;
}

.section-icon {
  width: 20px;
  height: 20px;
  color: #2563eb;
}

.section-header h3 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

/* Description Card */
.description-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
}

.description-card p {
  font-size: 15px;
  line-height: 1.6;
  color: #374151;
  margin: 0;
}

/* Allowance Card */
.allowance-card {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
  border-radius: 12px;
  padding: 24px;
  text-align: center;
}

.allowance-amount {
  font-size: 28px;
  font-weight: 700;
  margin-bottom: 8px;
}

.allowance-label {
  font-size: 14px;
  font-weight: 500;
  opacity: 0.9;
}

/* Programs and Skills Grid */
.programs-skills-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

/* Tags Container */
.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.program-tag-enhanced {
  background: #2563eb;
  color: white;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  transition: all 0.2s ease;
}

.program-tag-enhanced:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.skill-tag-enhanced {
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #e5e7eb;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s ease;
}

.skill-tag-enhanced:hover {
  background: #e5e7eb;
  border-color: #d1d5db;
  transform: translateY(-1px);
}

/* Contact Methods */
.contact-methods-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
}

.contact-method {
  margin-bottom: 16px;
}

.contact-method:last-child {
  margin-bottom: 0;
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: white;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.contact-icon {
  width: 20px;
  height: 20px;
  color: #6b7280;
}

.contact-details {
  flex: 1;
}

.contact-type {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  margin-bottom: 2px;
}

.contact-value {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

/* Timeline Card */
.timeline-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
}

.timeline-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 0;
}

.timeline-item:not(:last-child) {
  border-bottom: 1px solid #e5e7eb;
}

.timeline-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.timeline-dot.created {
  background: #10b981;
}

.timeline-dot.updated {
  background: #f59e0b;
}

.timeline-content {
  flex: 1;
}

.timeline-label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  margin-bottom: 2px;
}

.timeline-value {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

/* Allowance Card */
.allowance-card {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
  border-radius: 12px;
  padding: 24px;
  text-align: center;
}

.allowance-label {
  font-size: 16px;
  font-weight: 500;
  opacity: 0.9;
}

/* Programs and Skills Grid */
.programs-skills-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 24px;
}

/* Tags Container */
.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.program-tag-enhanced {
  background: #2563eb;
  color: white;
  padding: 12px 20px;
  border-radius: 25px;
  font-size: 14px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s ease;
}

.program-tag-enhanced:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.skill-tag-enhanced {
  background: #f3f4f6;
  color: #374151;
  border: 2px solid #e5e7eb;
  padding: 10px 18px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.2s ease;
}

.skill-tag-enhanced:hover {
  background: #e5e7eb;
  border-color: #d1d5db;
  transform: translateY(-1px);
}

/* Timeline Card */
.timeline-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 24px;
}

.timeline-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 0;
}

.timeline-item:not(:last-child) {
  border-bottom: 1px solid #e5e7eb;
}

.timeline-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.timeline-dot.created {
  background: #10b981;
}

.timeline-dot.updated {
  background: #f59e0b;
}

.timeline-content {
  flex: 1;
}

.timeline-label {
  font-size: 14px;
  font-weight: 600;
  color: #6b7280;
  margin-bottom: 4px;
}

.timeline-value {
  font-size: 16px;
  font-weight: 500;
  color: #1f2937;
}

/* Contact Methods Card */
.contact-methods-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.contact-method {
  display: flex;
  align-items: center;
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: white;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  width: 100%;
  transition: all 0.2s ease;
}

.contact-item:hover {
  border-color: #2563eb;
  box-shadow: 0 2px 4px rgba(37, 99, 235, 0.1);
}

.contact-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  color: #2563eb;
}

.contact-details {
  flex: 1;
}

.contact-type {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 2px;
}

.contact-value {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
  word-break: break-all;
}

.contact-item.email .contact-icon {
  color: #dc2626;
}

.contact-item.social .contact-icon {
  color: #7c3aed;
}

.contact-item.phone .contact-icon {
  color: #059669;
}

/* Status Badge */
.status-badge {
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-badge.active {
  background: rgba(16, 185, 129, 0.1);
  color: #065f46;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge.draft {
  background: rgba(245, 158, 11, 0.1);
  color: #92400e;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-badge.closed {
  background: rgba(239, 68, 68, 0.1);
  color: #991b1b;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Enhanced Modal Footer */
.modal-footer-enhanced {
  background: white;
  padding: 24px 40px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 16px;
}

.btn-secondary-enhanced {
  background: white;
  color: #374151;
  border: 1px solid #d1d5db;
  padding: 14px 28px;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s ease;
}

.btn-secondary-enhanced:hover {
  background: #f9fafb;
  border-color: #9ca3af;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.btn-secondary-enhanced .btn-icon {
  width: 18px;
  height: 18px;
}

.btn-edit-enhanced {
  background: #2563eb;
  color: white !important;
  border: none;
  padding: 14px 28px;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-edit-enhanced:hover {
  background: #1d4ed8 !important;
  color: white !important;
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
}

.btn-edit-enhanced .btn-icon {
  width: 18px;
  height: 18px;
  color: white !important;
}

/* Modal Overlay */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-content {
  background: white;
  border-radius: 20px;
  width: 100%;
  display: flex;
  flex-direction: column;
  animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

/* Responsive Design */
@media (max-width: 768px) {
  .key-info-grid {
    grid-template-columns: 1fr;
  }
  
  .programs-skills-grid {
    grid-template-columns: 1fr;
  }
  
  .modal-body-enhanced {
    padding: 24px;
  }
  
  .modal-header-enhanced {
    padding: 24px;
  }
  
  .internship-title {
    font-size: 24px;
  }
  
  .company-avatar {
    width: 56px;
    height: 56px;
    font-size: 20px;
  }
}
</style>
.modal-header-enhanced {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  color: #1f2937;
  padding: 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-radius: 16px 16px 0 0;
}

.header-content {
  display: flex;
  align-items: center;
  gap: 20px;
}

.company-avatar {
  width: 64px;
  height: 64px;
  background: rgba(255, 255, 255, 0.15);
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 700;
  color: white;
}

.header-info {
  flex: 1;
}

.internship-title {
  font-size: 28px;
  font-weight: 700;
  margin: 0 0 8px 0;
  color: #1f2937 !important;
  line-height: 1.2;
}

.company-info {
  display: flex;
  align-items: center;
  gap: 16px;
}

.company-name {
  font-size: 16px;
  color: #6b7280;
  font-weight: 500;
}

.modal-close-enhanced {
  background: #f9fafb;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  padding: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.modal-close-enhanced:hover {
  background: #f3f4f6;
  transform: scale(1.05);
}

.modal-body-enhanced {
  padding: 32px;
  background: #f8fafc;
  flex: 1;
  overflow-y: auto;
}

.key-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.info-card {
  background: white;
  border-radius: 16px;
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  transition: all 0.3s ease;
}

.info-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.15);
}

.info-card-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.info-card-icon.location {
  background: #2563eb;
  color: white;
}

.info-card-icon.duration {
  background: #059669;
  color: white;
}

.info-card-icon.type {
  background: #0891b2;
  color: white;
}

.info-card-icon.slots {
  background: #dc2626;
  color: white;
}

.info-card-icon svg {
  width: 24px;
  height: 24px;
}

.info-card-content {
  flex: 1;
}

.info-card-label {
  font-size: 14px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.info-card-value {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.2;
}

.content-section {
  background: white;
  border-radius: 16px;
  padding: 28px;
  margin-bottom: 24px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 2px solid #f1f5f9;
}

.section-icon {
  width: 24px;
  height: 24px;
  color: #2563eb;
}

.section-header h3 {
  font-size: 20px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.description-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 24px;
}

.description-card p {
  font-size: 16px;
  line-height: 1.7;
  color: #475569;
  margin: 0;
}

.allowance-card {
  background: #059669;
  color: white;
  border-radius: 12px;
  padding: 24px;
  text-align: center;
}

.allowance-label {
  font-size: 16px;
  opacity: 0.9;
  font-weight: 500;
}

.programs-skills-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 24px;
  margin-bottom: 24px;
}

.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.program-tag-enhanced {
  background: #2563eb;
  color: white;
  padding: 10px 18px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s ease;
}

.program-tag-enhanced:hover {
  background: #1d4ed8;
  transform: translateY(-2px);
  box-shadow: 0 8px 25px -8px rgba(37, 99, 235, 0.4);
}

.skill-tag-enhanced {
  background: #f1f5f9;
  color: #374151;
  border: 1px solid #d1d5db;
  padding: 8px 16px;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.3s ease;
}

.skill-tag-enhanced:hover {
  background: #e5e7eb;
  border-color: #9ca3af;
  transform: translateY(-1px);
}

.timeline-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 24px;
}

.timeline-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 0;
}

.timeline-item:not(:last-child) {
  border-bottom: 1px solid #e2e8f0;
  margin-bottom: 12px;
  padding-bottom: 24px;
}

.timeline-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.timeline-dot.created {
  background: #10b981;
}

.timeline-dot.updated {
  background: #f59e0b;
}

.timeline-content {
  flex: 1;
}

.timeline-label {
  font-size: 14px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.timeline-value {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
}

.modal-footer-enhanced {
  background: white;
  border-top: 1px solid #e2e8f0;
  padding: 24px 32px;
  display: flex;
  justify-content: flex-end;
  border-radius: 0 0 16px 16px;
}

.btn-icon {
  width: 20px;
  height: 20px;
}

/* Responsive Design for Enhanced Modal */
@media (max-width: 1024px) {
  .key-info-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .programs-skills-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .modal-header-enhanced {
    padding: 24px;
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  
  .header-content {
    width: 100%;
  }
  
  .company-avatar {
    width: 48px;
    height: 48px;
    font-size: 18px;
  }
  
  .internship-title {
    font-size: 22px;
  }
  
  .modal-body-enhanced {
    padding: 20px;
  }
  
  .key-info-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  
  .info-card {
    padding: 20px;
  }
  
  .content-section {
    padding: 20px;
  }
  
  .modal-footer-enhanced {
    padding: 20px;
  }
  
  .btn-edit-enhanced {
    width: 100%;
    justify-content: center;
  }
}
