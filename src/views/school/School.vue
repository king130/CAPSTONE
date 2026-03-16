<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import SchoolSidebar from '../../components/SchoolSidebar.vue'
import SchoolSettings from '../../components/SchoolSettings.vue'
import SubscriptionManager from '../../components/SubscriptionManager.vue'
import FloatingChatWidget from '../../components/FloatingChatWidget.vue'
import { useAuthStore } from '@/stores/auth'
import { subscribeSchoolStudents, addSchoolStudent, removeSchoolStudent, type SchoolStudentRecord } from '@/services/schoolStudents'
import { subscribeSchoolContracts, createContractRequest, cancelContract, type ContractRecord } from '@/services/contracts'
import { ensurePublicProfile, listPublicProfiles, subscribePublicProfiles, type PublicProfile } from '@/services/profilesPublic'
import Swal from 'sweetalert2'
import {
  BellIcon,
  HandRaisedIcon,
  MapPinIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  CalendarIcon,
  UsersIcon,
  CurrencyDollarIcon,
  ClipboardDocumentListIcon,
  CheckCircleIcon,
  CheckIcon,
  XMarkIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  TrashIcon,
  EnvelopeIcon,
  EllipsisVerticalIcon,
  PhotoIcon,
  VideoCameraIcon,
  DocumentIcon,
  GlobeAltIcon,
  UserCircleIcon,
  PhoneIcon,
  LockClosedIcon,
  ComputerDesktopIcon,
  DevicePhoneMobileIcon,
  CloudArrowUpIcon,
  PencilSquareIcon,
  CircleStackIcon,
  HandThumbUpIcon,
  ChatBubbleLeftIcon,
  ArrowPathRoundedSquareIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
const schoolName = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return (profile?.institutionName as string) || authStore.user?.displayName || 'School'
})

// Auto-generate school domain from the logged-in school's email
const schoolDomain = computed(() => {
  const userEmail = authStore.user?.email
  if (userEmail && userEmail.includes('@')) {
    return '@' + userEmail.split('@')[1]
  }
  return '@school.edu.ph' // fallback
})

const currentView = ref('dashboard')
const activeSettingsTab = ref('account')

const router = useRouter()
const handleMenuClick = (item: string) => {
  if (item === 'profile') {
    router.push('/profile')
    return
  }
  currentView.value = item
}

const setActiveSettingsTab = (tab: string) => {
  activeSettingsTab.value = tab
}

function formatApplicationDate(createdAt: unknown): string {
  if (!createdAt) return '—'
  const d = (createdAt as { toDate?: () => Date })?.toDate?.() ?? new Date(String(createdAt))
  return d instanceof Date && !isNaN(d.getTime())
    ? d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
    : '—'
}

// Student sidebar state
const showStudentSidebar = ref(false)

// Upload document modal state
const showUploadDocumentModal = ref(false)
const schoolSelectedFiles = ref<File[]>([])

function triggerSchoolFileInput() {
  const fileInput = document.getElementById('school-file-upload-input') as HTMLInputElement
  fileInput?.click()
}

function handleSchoolFileSelect(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files) {
    const filesArray = Array.from(target.files)
    schoolSelectedFiles.value = [...schoolSelectedFiles.value, ...filesArray]
  }
}

function handleSchoolFileDrop(event: DragEvent) {
  event.preventDefault()
  if (event.dataTransfer?.files) {
    const filesArray = Array.from(event.dataTransfer.files)
    schoolSelectedFiles.value = [...schoolSelectedFiles.value, ...filesArray]
  }
}

function handleSchoolDragOver(event: DragEvent) {
  event.preventDefault()
}

function removeSchoolFile(index: number) {
  schoolSelectedFiles.value.splice(index, 1)
}

function formatSchoolFileSize(bytes: number): string {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

function cancelSchoolUpload() {
  showUploadDocumentModal.value = false
  schoolSelectedFiles.value = []
}

async function confirmSchoolUpload() {
  if (schoolSelectedFiles.value.length > 0) {
    showUploadDocumentModal.value = false
    
    await Swal.fire({
      icon: 'success',
      iconColor: '#16a34a',
      title: 'Documents Uploaded Successfully!',
      text: `${schoolSelectedFiles.value.length} file(s) have been uploaded and are now being processed.`,
      confirmButtonText: 'Continue',
      confirmButtonColor: '#3b82f6',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })
    
    schoolSelectedFiles.value = []
  }
}

const selectedStudent = ref<any>(null)

// Student search
const studentSearchQuery = ref('')

const filteredStudents = computed(() => {
  const query = studentSearchQuery.value.trim().toLowerCase()
  if (!query) return studentsData.value
  return studentsData.value.filter((student) => 
    student.name.toLowerCase().includes(query) ||
    student.studentId.toLowerCase().includes(query) ||
    student.course.toLowerCase().includes(query)
  )
})

// Approval modal state
const showApprovalModal = ref(false)
const approvalRemarks = ref('')

// Company sidebar state
const showCompanySidebar = ref(false)
const selectedCompany = ref<any>(null)
const activeCompanyTab = ref('general')

// Submit Report Modal state
const showSubmitReportModal = ref(false)
const reportFormData = ref({
  reportType: '',
  studentName: 'John Doe',
  internshipPeriodStart: '',
  internshipPeriodEnd: '',
  companyName: 'Tech Solutions Inc.'
})

function openSubmitReportModal() {
  console.log('Opening submit report page')
  currentView.value = 'submit-report'
}

function closeSubmitReportModal() {
  currentView.value = 'reports'
  // Reset form data
  reportFormData.value = {
    reportType: '',
    studentName: 'John Doe',
    internshipPeriodStart: '',
    internshipPeriodEnd: '',
    companyName: 'Tech Solutions Inc.'
  }
}

function saveAsDraft() {
  // Handle save as draft logic
  console.log('Saving as draft:', reportFormData.value)
  currentView.value = 'reports'
}

function nextStep() {
  // Handle next step logic
  console.log('Going to next step:', reportFormData.value)
  // For now, just go back to reports
  currentView.value = 'reports'
}

// Sample company data
const companiesData = ref([
  {
    id: 1,
    name: 'Innovate Corp.',
    avatar: 'IC',
    position: 'Software Engineer Intern',
    location: 'New York (Hybrid)',
    match: 92,
    description: 'Join Our dynamic team to develop innovative software solutions. You\'ll work on cutting-edge projects and contribute the projects.',
    skills: ['Python', 'AWS', 'Machine Learning', 'Django'],
    rating: 4.8,
    placement: 95,
    generalInfo: {
      address: 'New York (Hybrid)',
      contactEmail: 'innovative@gmail.com',
      website: 'www.InnovativeCorp.com',
      partnerStatus: 'Active'
    },
    keyContacts: [
      {
        name: 'Acme Corp.',
        title: 'Project Manager',
        email: 'manager@acme.com'
      }
    ],
    internshipOfferings: [
      {
        title: 'Software Engineering',
        requirements: 'Python, Django, SQL, Machine Learning, C++ Project management understanding',
        placement: 2,
        status: 'Active'
      },
      {
        title: 'Data Analyst Intern',
        requirements: 'Requirements: Python, SQL, Machine Learning Local data dynamic team to develop innovative',
        placement: 2,
        status: 'Active'
      },
      {
        title: 'UI/UX Designer Intern',
        requirements: 'Requirements: Figma, Prototyping, Design System Join dynamic team to develop innovative',
        placement: 2,
        status: 'Active'
      },
      {
        title: 'Business Development Intern',
        requirements: 'Requirements: Python, SQL, Machine Learning Local Join dynamic team to develop innovative',
        placement: 2,
        status: 'Active'
      }
    ],
    moaHistory: [
      {
        title: 'MOA_Innovatice Corp.',
        date: '2026-30-1',
        status: 'Active',
        expires: '2026-30-1'
      }
    ],
    internFeedback: [
      {
        name: 'Peter Jones',
        rating: 4,
        feedback: '"Hands-on Experience, learned a lot about site management and safety protocols"'
      }
    ]
  }
])

// Sample student data
const studentsData = ref([
  {
    id: 1,
    name: 'John Doe',
    studentId: '2020-0001',
    course: 'BSIT',
    yearLevel: '4th Year',
    status: 'Registered',
    eligibility: 'Eligible',
    company: 'Electro Systems',
    position: 'Software Development Intern',
    applicationDate: 'January 15, 2024',
    documents: [
      { name: 'Resume.pdf', type: 'pdf' },
      { name: 'Recommendation Letter.docx', type: 'docx' },
      { name: 'Transcript of Records.jpg', type: 'jpg' }
    ],
    timeline: [
      { status: 'Application Submitted', date: 'January 15, 2024 - 2:30 PM', completed: true },
      { status: 'Reviewed by Academic Advisor', date: 'January 16, 2024 - 10:15 AM', completed: true },
      { status: 'Company Interview & Approval', date: 'January 18, 2024 - 3:45 PM', completed: true },
      { status: 'Endorsed to Company', date: 'January 20, 2024 - 9:00 AM', completed: true }
    ]
  },
  {
    id: 2,
    name: 'Jane Smith',
    studentId: '2020-0002',
    course: 'BSCS',
    yearLevel: '3rd Year',
    status: 'Active Intern',
    eligibility: 'Pending',
    company: 'Tech Solutions Inc.',
    position: 'Web Developer Intern',
    applicationDate: 'January 10, 2024',
    documents: [
      { name: 'CV_JaneSmith.pdf', type: 'pdf' },
      { name: 'Portfolio.zip', type: 'zip' },
      { name: 'Grades.pdf', type: 'pdf' }
    ],
    timeline: [
      { status: 'Application Submitted', date: 'January 10, 2024 - 9:15 AM', completed: true },
      { status: 'Initial Screening', date: 'January 11, 2024 - 2:00 PM', completed: true },
      { status: 'Technical Interview', date: 'January 12, 2024 - 4:30 PM', completed: false },
      { status: 'Final Approval Pending', date: 'Pending', completed: false }
    ]
  },
  {
    id: 3,
    name: 'Peter Jones',
    studentId: '2020-0003',
    course: 'BSIT',
    yearLevel: '4th Year',
    status: 'Pending Application',
    eligibility: 'Not Eligible',
    company: 'DataCorp Analytics',
    position: 'Data Analyst Intern',
    applicationDate: 'January 20, 2024',
    documents: [
      { name: 'Resume_Peter.pdf', type: 'pdf' },
      { name: 'CertificationLetter.docx', type: 'docx' }
    ],
    timeline: [
      { status: 'Application Submitted', date: 'January 20, 2024 - 11:45 AM', completed: true },
      { status: 'Document Review', date: 'January 21, 2024 - 3:20 PM', completed: false },
      { status: 'Eligibility Check', date: 'Pending', completed: false },
      { status: 'Company Review', date: 'Pending', completed: false }
    ]
  },
  {
    id: 4,
    name: 'Alice Brown',
    studentId: '2020-0004',
    course: 'BSECE',
    yearLevel: '3rd Year',
    status: 'Registered',
    eligibility: 'Eligible',
    company: 'Innovation Labs',
    position: 'Hardware Engineer Intern',
    applicationDate: 'January 8, 2024',
    documents: [
      { name: 'AliceBrown_Resume.pdf', type: 'pdf' },
      { name: 'ProjectPortfolio.pdf', type: 'pdf' },
      { name: 'Transcript.jpg', type: 'jpg' },
      { name: 'RecommendationLetter.pdf', type: 'pdf' }
    ],
    timeline: [
      { status: 'Application Submitted', date: 'January 8, 2024 - 1:10 PM', completed: true },
      { status: 'Academic Review', date: 'January 9, 2024 - 10:30 AM', completed: true },
      { status: 'Company Screening', date: 'January 10, 2024 - 2:45 PM', completed: true },
      { status: 'Placement Confirmed', date: 'January 11, 2024 - 4:00 PM', completed: true }
    ]
  },
  {
    id: 5,
    name: 'Michael White',
    studentId: '2020-0005',
    course: 'BSIT',
    yearLevel: '4th Year',
    status: 'Active Intern',
    eligibility: 'Eligible',
    company: 'Creative Design Studio',
    position: 'UI/UX Design Intern',
    applicationDate: 'December 28, 2023',
    documents: [
      { name: 'MichaelWhite_CV.pdf', type: 'pdf' },
      { name: 'DesignPortfolio.pdf', type: 'pdf' },
      { name: 'AcademicRecords.pdf', type: 'pdf' }
    ],
    timeline: [
      { status: 'Application Submitted', date: 'December 28, 2023 - 3:25 PM', completed: true },
      { status: 'Portfolio Review', date: 'December 29, 2023 - 11:00 AM', completed: true },
      { status: 'Design Challenge', date: 'January 2, 2024 - 2:15 PM', completed: true },
      { status: 'Internship Started', date: 'January 5, 2024 - 8:00 AM', completed: true }
    ]
  },
  {
    id: 6,
    name: 'Emily Green',
    studentId: '2020-0006',
    course: 'BSCS',
    yearLevel: '4th Year',
    status: 'Completed',
    eligibility: 'Eligible',
    company: 'TechStart Solutions',
    position: 'Full Stack Developer Intern',
    applicationDate: 'November 15, 2023',
    documents: [
      { name: 'EmilyGreen_Resume.pdf', type: 'pdf' },
      { name: 'CodingProjects.zip', type: 'zip' },
      { name: 'FinalTranscript.pdf', type: 'pdf' },
      { name: 'CompletionCertificate.pdf', type: 'pdf' }
    ],
    timeline: [
      { status: 'Application Submitted', date: 'November 15, 2023 - 10:20 AM', completed: true },
      { status: 'Technical Assessment', date: 'November 16, 2023 - 2:30 PM', completed: true },
      { status: 'Final Interview', date: 'November 18, 2023 - 4:00 PM', completed: true },
      { status: 'Internship Completed', date: 'January 15, 2024 - 5:00 PM', completed: true }
    ]
  }
])

function openStudentSidebar(student: any) {
  selectedStudent.value = student
  showStudentSidebar.value = true
}

function closeStudentSidebar() {
  showStudentSidebar.value = false
  selectedStudent.value = null
}

function approveStudent() {
  showApprovalModal.value = true
}

function cancelApproval() {
  showApprovalModal.value = false
  approvalRemarks.value = ''
}

async function submitApproval() {
  if (selectedStudent.value) {
    selectedStudent.value.status = 'Approved'
    // Here you would typically send the approval with remarks to the backend
    console.log('Approved with remarks:', approvalRemarks.value)
    showApprovalModal.value = false
    approvalRemarks.value = ''
    closeStudentSidebar()

    // Show success SweetAlert
    await Swal.fire({
      icon: 'success',
      iconColor: '#10b981',
      title: 'Apply for Internship Now!',
      text: 'Application Approved With Remarks!',
      confirmButtonText: 'Confirm',
      confirmButtonColor: '#3b82f6',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })
  }
}

async function rejectStudent() {
  if (selectedStudent.value) {
    selectedStudent.value.status = 'Rejected'
    closeStudentSidebar()

    // Show rejection SweetAlert
    await Swal.fire({
      icon: 'error',
      iconColor: '#ef4444',
      title: 'Not Eligible',
      text: 'Application Rejected With Remarks!',
      confirmButtonText: 'Confirm',
      confirmButtonColor: '#3b82f6',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })
  }
}

function openCompanySidebar(company: any) {
  selectedCompany.value = company
  showCompanySidebar.value = true
  activeCompanyTab.value = 'general'
}

function closeCompanySidebar() {
  showCompanySidebar.value = false
  selectedCompany.value = null
  activeCompanyTab.value = 'general'
}

function setActiveCompanyTab(tab: string) {
  activeCompanyTab.value = tab
}

// Dashboard stats
const dashboardStats = ref({
  ojtHours: { current: 245, total: 400, remaining: 155 },
  applications: { active: 3, underReview: 1, scheduled: 1, accepted: 1 },
  currentInternship: {
    company: 'Tech Solutions Inc.',
    position: 'Software Development Intern',
    status: 'Active since Jan 1'
  },
  pendingTasks: [
    { task: 'Submit Weekly Journal', type: 'journal' },
    { task: 'Complete Evaluation', type: 'evaluation' }
  ]
})

// Recommended internships
const recommendedInternships = ref([
  {
    id: 1,
    title: 'Marketing Assistant',
    company: 'Digital Marketing Co.',
    location: 'Cavite City',
    match: 92,
    skills: ['Social Media Marketing', 'Content Creation', 'Analytics']
  },
  {
    id: 2,
    title: 'Web Developer',
    company: 'Creative Tech Studio',
    location: 'Bacoor',
    match: 88,
    skills: ['HTML/CSS', 'JavaScript', 'Responsive Design']
  },
  {
    id: 3,
    title: 'Graphic Designer',
    company: 'Design Hub Agency',
    location: 'Imus City',
    match: 85,
    skills: ['Adobe Photoshop', 'Illustrator', 'Branding']
  }
])

// Recent activities
const recentActivities = ref([
  {
    id: 1,
    text: 'Application accepted by Tech Solutions',
    time: '2 hours ago',
    status: 'success'
  },
  {
    id: 2,
    text: 'Weekly journal submitted',
    time: '1 day ago',
    status: 'info'
  },
  {
    id: 3,
    text: 'Time out recorded',
    time: '1 day ago',
    status: 'neutral'
  },
  {
    id: 4,
    text: 'Application submitted to Creative Agency',
    time: '3 days ago',
    status: 'info'
  }
])

// Upcoming deadlines
const upcomingDeadlines = ref([
  {
    id: 1,
    title: 'Weekly Journal Due',
    date: 'Jan 20',
    daysRemaining: 2,
    type: 'warning'
  },
  {
    id: 2,
    title: 'Evaluation Form',
    date: 'Jan 25',
    daysRemaining: 7,
    type: 'info'
  }
])

// School Info Data
const schoolInfoData = ref({
  institutionDetails: {
    schoolName: '',
    logo: null,
    officialAddress: '',
    region: 'NCR',
    province: 'Manila',
    cityMunicipality: '',
    barangay: '',
    zipCode: ''
  },
  ojtCoordinatorInfo: {
    coordinatorName: '',
    position: '',
    phoneNumber: '',
    mobileNumber: '',
    emailAddress: '',
    officeLocation: ''
  },
  schoolContactInfo: {
    mainOfficeNumber: '',
    studentAffairsNumber: '',
    registrarNumber: '',
    faxNumber: '',
    websiteUrl: '',
    officialEmail: ''
  },
  academicCalendar: {
    currentTermSemester: 'First Semester',
    termStartDate: '',
    termEndDate: ''
  },
  socialMediaLinks: {
    facebookPage: '',
    twitterAccount: '',
    linkedInPage: '',
    instagramAccount: ''
  }
})

const academicTerms = ref([
  {
    id: 1,
    termName: 'First Semester AY 2023-2024',
    startDate: 'Aug 15, 2023',
    endDate: 'Dec 15, 2023',
    status: 'Active'
  }
])

// OJT Configuration Data
const ojtConfigData = ref({
  programRequirements: {
    minimumOjtHours: 486,
    allowFlexibleHours: false,
    minimumAttendanceRate: 95,
    requiredDocuments: ['Resume', 'Recommendation Letter', 'Medical Certificate'],
    gradeRequirement: 2.5,
    prerequisiteSubjects: '',
    yearLevelRequirement: '3rd Year'
  },
  applicationSettings: {
    enableApplications: true,
    requireApproval: true,
    autoMatchStudents: false,
    allowMultipleApplications: true,
    maxSimultaneousApplications: 3,
    applicationDeadline: '',
    notifyNewApplications: true,
    notifyStatusChanges: true
  },
  monitoringEvaluation: {
    weeklyJournalRequired: true,
    journalSubmissionDay: 'Friday',
    midtermEvaluationWeek: 6,
    finalEvaluationWeek: 12,
    supervisorEvaluationRequired: true,
    studentSelfEvaluation: true,
    allowLateSubmissions: false
  },
  companyRequirements: {
    moaRequired: true,
    moaValidityPeriod: 2,
    companyVerification: true,
    minimumCompanyRating: 3.5,
    requireCompanyProfile: true,
    backgroundCheckRequired: false
  },
  studentRequirements: {
    completedUnits: 90,
    gpa: 2.5,
    noFailingGrades: true,
    clearanceRequired: true,
    parentConsent: true,
    insuranceRequired: true,
    nbiClearance: false,
    medicalCertificate: true,
    resume: true,
    recommendationLetter: true
  },
  evaluationSettings: {
    enableEvaluations: true,
    studentEvaluatesCompany: true,
    companyEvaluatesStudent: true,
    schoolEvaluatesPlacement: true,
    evaluationFrequency: 'Monthly',
    anonymousEvaluations: false
  }
})

const dataSettings = ref({
  autoBackup: true,
  backupFrequency: 'weekly',
  retentionDays: 365,
  allowAnalytics: true,
})

async function saveDataSettings() {
  await Swal.fire({
    icon: 'success',
    title: 'Saved',
    text: 'Data settings updated successfully.',
    confirmButtonColor: '#2563eb',
  })
}

async function exportSchoolData(type: 'students' | 'internships' | 'contracts') {
  await Swal.fire({
    icon: 'info',
    title: 'Export Started',
    text: `Preparing ${type} export file...`,
    confirmButtonColor: '#2563eb',
  })
}

async function clearSchoolCache() {
  await Swal.fire({
    icon: 'success',
    title: 'Cache Cleared',
    text: 'Temporary cached data has been cleared.',
    confirmButtonColor: '#2563eb',
  })
}

const requiredDocumentsList = ref([
  { id: 1, name: 'Resume/CV', required: true },
  { id: 2, name: 'Recommendation Letter', required: true },
  { id: 3, name: 'Medical Certificate', required: true },
  { id: 4, name: 'NBI Clearance', required: false },
  { id: 5, name: 'Transcript of Records', required: true }
])

// Student Emails (school-generated) - from Firebase
const schoolStudentEmails = ref<SchoolStudentRecord[]>([])
const studentEmailSearchQuery = ref('')
const filteredSchoolStudentEmails = computed(() => {
  const q = studentEmailSearchQuery.value.trim().toLowerCase()
  if (!q) return schoolStudentEmails.value

  return schoolStudentEmails.value.filter((s) => {
    const haystack = [
      s.email,
      s.studentName,
      s.studentNumber,
      s.course,
      s.yearLevel,
      s.status,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return haystack.includes(q)
  })
})
let unsubSchoolStudents: (() => void) | null = null
const newStudentFirstName = ref('')
const newStudentLastName = ref('')
const newStudentEmailDomain = ref('')
const newStudentId = ref('')
const newStudentCourse = ref('')
const newStudentYearLevel = ref('')
const addingStudentEmail = ref(false)

// Initialize the domain field with the school's domain
watch(schoolDomain, (newDomain) => {
  newStudentEmailDomain.value = newDomain
}, { immediate: true })
const importingStudentEmails = ref(false)
const bulkStudentFileInput = ref<HTMLInputElement | null>(null)

function sanitizeNamePart(value: string) {
  return value
    .trim()
    .toLowerCase()
    .replace(/\s+/g, '')
    .replace(/[^a-z0-9]/g, '')
}

const generatedStudentEmail = computed(() => {
  const first = sanitizeNamePart(newStudentFirstName.value)
  const last = sanitizeNamePart(newStudentLastName.value)
  const domain = schoolDomain.value
  
  if (!first || !last || !domain || domain === '@') return ''
  return `${last}.${first}${domain}`
})

const generatedStudentFullName = computed(() => {
  const first = newStudentFirstName.value.trim()
  const last = newStudentLastName.value.trim()
  if (!first || !last) return ''
  return `${first} ${last}`
})

function generateDefaultStudentPassword(studentName?: string) {
  const base = sanitizeNamePart(studentName || '').slice(0, 8) || 'student'
  const suffix = Math.floor(1000 + Math.random() * 9000)
  return `${base}@${suffix}`
}

async function copyToClipboard(text: string, label: string) {
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    await Swal.fire({
      icon: 'success',
      title: 'Copied',
      text: `${label} copied to clipboard.`,
      timer: 1200,
      showConfirmButton: false,
    })
  } catch {
    await Swal.fire({
      icon: 'error',
      title: 'Copy Failed',
      text: `Could not copy ${label.toLowerCase()}.`,
      confirmButtonColor: '#2563eb',
    })
  }
}

function normalizeHeader(value: string) {
  return value.trim().toLowerCase().replace(/[^a-z0-9]/g, '')
}

function parseCsvLine(line: string) {
  const cells: string[] = []
  let current = ''
  let inQuotes = false

  for (let i = 0; i < line.length; i += 1) {
    const char = line[i]
    const next = line[i + 1]

    if (char === '"') {
      if (inQuotes && next === '"') {
        current += '"'
        i += 1
      } else {
        inQuotes = !inQuotes
      }
      continue
    }

    if (char === ',' && !inQuotes) {
      cells.push(current.trim())
      current = ''
      continue
    }

    current += char
  }

  cells.push(current.trim())
  return cells
}

function triggerBulkStudentFilePicker() {
  bulkStudentFileInput.value?.click()
}

async function importStudentsFromFile(event: Event) {
  const uid = authStore.user?.uid
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!uid || !file) return

  const domain = schoolDomain.value
  
  if (!domain || domain === '@') {
    await Swal.fire({
      icon: 'warning',
      title: 'Domain Error',
      text: 'Unable to determine school email domain from your account.',
      confirmButtonColor: '#2563eb',
    })
    input.value = ''
    return
  }

  const lowerName = file.name.toLowerCase()
  const isCsv = lowerName.endsWith('.csv') || file.type.includes('csv')
  const isExcel =
    lowerName.endsWith('.xlsx') ||
    lowerName.endsWith('.xls') ||
    file.type.includes('sheet') ||
    file.type.includes('excel')

  if (!isCsv && !isExcel) {
    await Swal.fire({
      icon: 'info',
      title: 'Unsupported file',
      text: 'Please upload an Excel file (.xlsx/.xls) or CSV file (.csv).',
      confirmButtonColor: '#2563eb',
    })
    input.value = ''
    return
  }

  importingStudentEmails.value = true
  try {
    let headers: string[] = []
    let dataRows: string[][] = []

    if (isCsv) {
      const text = await file.text()
      const lines = text
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line.length > 0)

      if (lines.length < 2) {
        throw new Error('CSV is empty or missing data rows.')
      }

      headers = parseCsvLine(lines[0]!).map(normalizeHeader)
      dataRows = lines.slice(1).map((line) => parseCsvLine(line))
    } else {
      let XLSX: typeof import('xlsx')
      try {
        // Keep this as a normal dynamic import so Vite can bundle/serve the dependency correctly.
        XLSX = await import('xlsx')
      } catch {
        throw new Error('Excel import requires the "xlsx" package. Run: npm.cmd install xlsx')
      }

      const buffer = await file.arrayBuffer()
      const workbook = XLSX.read(buffer, { type: 'array' })
      const sheetName = workbook.SheetNames[0]
      if (!sheetName) {
        throw new Error('Excel file has no worksheets.')
      }

      const sheet = workbook.Sheets[sheetName]
      if (!sheet) {
        throw new Error('Could not read the first worksheet.')
      }

      const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', blankrows: false }) as Array<Array<unknown>>
      const cleaned = rows
        .map((row) => row.map((cell) => (cell == null ? '' : String(cell))).map((cell) => cell.trim()))
        .filter((row) => row.some((cell) => cell.length > 0))

      if (cleaned.length < 2) {
        throw new Error('Excel sheet is empty or missing data rows.')
      }

      headers = cleaned[0]!.map(normalizeHeader)
      dataRows = cleaned.slice(1)
    }

    const firstNameIdx = headers.findIndex((h) => ['firstname', 'first', 'givenname'].includes(h))
    const lastNameIdx = headers.findIndex((h) => ['lastname', 'last', 'surname', 'familyname'].includes(h))
    const fullNameIdx = headers.findIndex((h) => ['name', 'studentname', 'fullname'].includes(h))
    const studentNumberIdx = headers.findIndex((h) =>
      ['studentnumber', 'studentno', 'studentid', 'idnumber', 'studentidnumber'].includes(h)
    )
    const courseIdx = headers.findIndex((h) => ['course', 'program'].includes(h))
    const yearLevelIdx = headers.findIndex((h) => ['yearlevel', 'year', 'level'].includes(h))

    if ((firstNameIdx < 0 || lastNameIdx < 0) && fullNameIdx < 0) {
      throw new Error('CSV must include First Name + Last Name columns, or a Name column.')
    }

    const existingEmails = new Set(schoolStudentEmails.value.map((item) => item.email.trim().toLowerCase()))
    const batchEmails = new Set<string>()
    let imported = 0
    let skipped = 0

    for (const row of dataRows) {
      if (row.length === 0) {
        skipped += 1
        continue
      }

      let firstName = ''
      let lastName = ''

      if (firstNameIdx >= 0 && lastNameIdx >= 0) {
        firstName = (row[firstNameIdx] || '').trim()
        lastName = (row[lastNameIdx] || '').trim()
      } else if (fullNameIdx >= 0) {
        const fullName = (row[fullNameIdx] || '').trim()
        const parts = fullName.split(/\s+/).filter(Boolean)
        if (parts.length >= 2) {
          lastName = parts.pop() || ''
          firstName = parts.join(' ')
        }
      }

      const first = sanitizeNamePart(firstName)
      const last = sanitizeNamePart(lastName)
      if (!first || !last) {
        skipped += 1
        continue
      }

      const email = `${last}.${first}${domain}`
      if (existingEmails.has(email) || batchEmails.has(email)) {
        skipped += 1
        continue
      }

      const studentName = `${firstName} ${lastName}`.trim()
      const defaultPassword = generateDefaultStudentPassword(studentName)

      await addSchoolStudent(uid, email, {
        studentName,
        studentNumber: studentNumberIdx >= 0 ? (row[studentNumberIdx] || '').trim() || undefined : undefined,
        defaultPassword,
        course: courseIdx >= 0 ? (row[courseIdx] || '').trim() || undefined : undefined,
        yearLevel: yearLevelIdx >= 0 ? (row[yearLevelIdx] || '').trim() || undefined : undefined,
      })
      batchEmails.add(email)
      imported += 1
    }

    await Swal.fire({
      icon: 'success',
      title: 'Import Complete',
      text: `Imported ${imported} student email(s). Skipped ${skipped}.`,
      confirmButtonColor: '#2563eb',
    })
  } catch (err) {
    await Swal.fire({
      icon: 'error',
      title: 'Import Failed',
      text: err instanceof Error ? err.message : 'Could not import student file.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    importingStudentEmails.value = false
    input.value = ''
  }
}

const schoolContracts = ref<ContractRecord[]>([])
const companiesForContract = ref<PublicProfile[]>([])
const contractFiles = ref<File[]>([])
const creatingContract = ref(false)
const newContractCourse = ref('')
const selectedContractCourses = ref<string[]>([])
const contractCourseSlots = ref<Record<string, string>>({})
const expandedSentContractId = ref<string | null>(null)
const schoolContractForm = ref({
  companyId: '',
  companyName: '',
  subject: '',
  contractType: 'Memorandum of Agreement (MOA) for OJT Internship',
  moaReferenceNo: '',
  purpose: '',
  startDate: '',
  endDate: '',
  internshipSlots: '',
  studentPrograms: '',
  schoolContactName: '',
  schoolContactEmail: '',
  companyContactName: '',
  companyContactEmail: '',
  schoolResponsibilities: '',
  companyResponsibilities: '',
  terms: '',
  notes: '',
})

// Auto-generate MOA Reference Number
const autoGeneratedMoaRef = computed(() => {
  const year = new Date().getFullYear()
  const timestamp = Date.now().toString().slice(-6)
  return `MOA-${year}-${timestamp}`
})

// Watch for when form is reset or initialized to set auto-generated reference
watch(() => schoolContractForm.value.companyId, (newVal) => {
  if (newVal && !schoolContractForm.value.moaReferenceNo) {
    schoolContractForm.value.moaReferenceNo = autoGeneratedMoaRef.value
  }
})

watch(
  () => [authStore.user?.email, authStore.user?.displayName] as const,
  ([email, displayName]) => {
    if (!email && !displayName) return
    if (!schoolContractForm.value.schoolContactName) {
      schoolContractForm.value.schoolContactName = displayName || schoolName.value
    }
    if (!schoolContractForm.value.schoolContactEmail) {
      schoolContractForm.value.schoolContactEmail = email || ''
    }
  },
  { immediate: true }
)

let unsubSchoolContracts: (() => void) | null = null
let unsubCompaniesPublic: (() => void) | null = null

function addContractCourse() {
  const course = newContractCourse.value.trim().toUpperCase()
  if (!course) return
  if (!selectedContractCourses.value.includes(course)) {
    selectedContractCourses.value.push(course)
  }
  newContractCourse.value = ''
}

function removeContractCourse(course: string) {
  selectedContractCourses.value = selectedContractCourses.value.filter((c) => c !== course)
  delete contractCourseSlots.value[course]
}

function onSchoolContractCompanySelect(e: Event) {
  const target = e.target as HTMLSelectElement
  const id = target.value
  const company = companiesForContract.value.find((c) => c.uid === id)
  if (!company) return
  schoolContractForm.value.companyId = company.uid
  const label = company.orgName || company.displayName || company.email || 'Company'
  schoolContractForm.value.companyName = label
  schoolContractForm.value.companyContactName = label
  schoolContractForm.value.companyContactEmail = company.email || ''
}

function triggerContractFileInput() {
  const fileInput = document.getElementById('school-contract-file-input') as HTMLInputElement
  fileInput?.click()
}

function onSchoolContractFileSelect(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files) {
    contractFiles.value = [...contractFiles.value, ...Array.from(target.files)]
  }
  target.value = ''
}

function removeContractFile(index: number) {
  contractFiles.value.splice(index, 1)
}

function resetSchoolContractForm() {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  const defaultSchoolContactName = authStore.user?.displayName || schoolName.value
  const defaultSchoolContactEmail = authStore.user?.email || (profile?.officialSchoolEmail as string) || ''

  schoolContractForm.value = {
    companyId: '',
    companyName: '',
    subject: '',
    contractType: 'Memorandum of Agreement (MOA) for OJT Internship',
    moaReferenceNo: '',
    purpose: '',
    startDate: '',
    endDate: '',
    internshipSlots: '',
    studentPrograms: '',
    schoolContactName: defaultSchoolContactName,
    schoolContactEmail: defaultSchoolContactEmail,
    companyContactName: '',
    companyContactEmail: '',
    schoolResponsibilities: '',
    companyResponsibilities: '',
    terms: '',
    notes: '',
  }
  contractFiles.value = []
  selectedContractCourses.value = []
  contractCourseSlots.value = {}
}

const selectedCompanyHasPendingContract = computed(() => {
  const companyId = schoolContractForm.value.companyId
  if (!companyId) return false
  return schoolContracts.value.some((c) => c.companyId === companyId && (c.status || 'pending') === 'pending')
})

const schoolCourses = computed(() => {
  const p = authStore.user?.profile as Record<string, unknown> | undefined
  const courses = (p?.courses as string[] | undefined) || []
  return courses.map((c) => c.trim()).filter(Boolean)
})

const selectedCompanyPublic = computed(() => {
  const companyId = schoolContractForm.value.companyId
  if (!companyId) return null
  return companiesForContract.value.find((c) => c.uid === companyId) || null
})

const selectedCompanyCourses = computed(() => {
  const c = selectedCompanyPublic.value as (PublicProfile & { courses?: string[] }) | null
  const courses = (c?.courses as string[] | undefined) || []
  return courses.map((x) => x.trim()).filter(Boolean)
})

const alignedContractCourses = computed(() => {
  const schoolSet = new Set(schoolCourses.value.map((c) => c.toUpperCase()))
  return selectedCompanyCourses.value
    .filter((c) => schoolSet.has(c.toUpperCase()))
    .sort((a, b) => a.localeCompare(b))
})

function displayContractStatus(status?: string) {
  const s = (status || 'pending').toLowerCase()
  if (s === 'active') return 'approved'
  return s
}

function displayContractStatusLabel(status?: string) {
  const s = displayContractStatus(status)
  return s.charAt(0).toUpperCase() + s.slice(1)
}

watch(
  () => [schoolContractForm.value.companyId, alignedContractCourses.value.join('|')] as const,
  () => {
    // When company changes (or alignment changes), preselect aligned courses.
    selectedContractCourses.value = [...alignedContractCourses.value]
    // Clear any stale slot entries for courses not in the aligned list.
    const alignedSet = new Set(alignedContractCourses.value)
    for (const k of Object.keys(contractCourseSlots.value)) {
      if (!alignedSet.has(k)) delete contractCourseSlots.value[k]
    }
  }
)

function toggleAlignedCourse(course: string) {
  if (selectedContractCourses.value.includes(course)) {
    selectedContractCourses.value = selectedContractCourses.value.filter((c) => c !== course)
    delete contractCourseSlots.value[course]
    return
  }
  selectedContractCourses.value.push(course)
}

function toggleSentContractDetails(id: string) {
  expandedSentContractId.value = expandedSentContractId.value === id ? null : id
}

async function submitSchoolContractRequest() {
  const schoolId = authStore.user?.uid
  if (!schoolId) return
  if (!schoolContractForm.value.companyId || !schoolContractForm.value.companyName) {
    await Swal.fire({
      icon: 'warning',
      title: 'Select Company',
      text: 'Please select a company to send this contract request.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  if (selectedCompanyHasPendingContract.value) {
    await Swal.fire({
      icon: 'warning',
      title: 'Pending Request Exists',
      text: 'You already have a pending contract request with this company. Please wait for a response or cancel the pending request first.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  if (!schoolContractForm.value.subject.trim()) {
    await Swal.fire({
      icon: 'warning',
      title: 'Missing Subject',
      text: 'Please enter a subject for the contract (e.g., "OJT Partnership for SY 2026").',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  creatingContract.value = true
  try {
    const courseAllocations = selectedContractCourses.value
      .map((course) => ({
        course,
        slots: Number(contractCourseSlots.value[course] || 0),
      }))
      .filter((item) => Number.isFinite(item.slots) && item.slots > 0)

    const programsFromAllocations = courseAllocations.map((item) => item.course).join(', ')

    if (alignedContractCourses.value.length === 0) {
      await Swal.fire({
        icon: 'warning',
        title: 'No Aligned Courses',
        text: 'There are no aligned courses between your school and this company. Add your school courses in Settings and ask the company to add the courses they accept.',
        confirmButtonColor: '#2563eb',
      })
      return
    }

    if (courseAllocations.length === 0) {
      await Swal.fire({
        icon: 'warning',
        title: 'Missing Course Slots',
        text: 'Please select at least one aligned course and set the number of slots.',
        confirmButtonColor: '#2563eb',
      })
      return
    }

    await createContractRequest({
      companyId: schoolContractForm.value.companyId,
      companyName: schoolContractForm.value.companyName,
      schoolId,
      schoolName: schoolName.value,
      requestedByRole: 'school',
      subject: schoolContractForm.value.subject.trim(),
      contractType: schoolContractForm.value.contractType || undefined,
      moaReferenceNo: schoolContractForm.value.moaReferenceNo.trim() || autoGeneratedMoaRef.value,
      purpose: schoolContractForm.value.purpose.trim() || undefined,
      startDate: schoolContractForm.value.startDate || undefined,
      endDate: schoolContractForm.value.endDate || undefined,
      internshipSlots: schoolContractForm.value.internshipSlots
        ? Number(schoolContractForm.value.internshipSlots)
        : undefined,
      studentPrograms: programsFromAllocations || schoolContractForm.value.studentPrograms.trim() || undefined,
      courseAllocations: courseAllocations.length ? courseAllocations : undefined,
      schoolContactName: schoolContractForm.value.schoolContactName.trim() || undefined,
      schoolContactEmail: schoolContractForm.value.schoolContactEmail.trim() || undefined,
      companyContactName: schoolContractForm.value.companyContactName.trim() || undefined,
      companyContactEmail: schoolContractForm.value.companyContactEmail.trim() || undefined,
      schoolResponsibilities: schoolContractForm.value.schoolResponsibilities.trim() || undefined,
      companyResponsibilities: schoolContractForm.value.companyResponsibilities.trim() || undefined,
      terms: schoolContractForm.value.terms.trim() || undefined,
      notes: schoolContractForm.value.notes.trim() || undefined,
      attachments: contractFiles.value.map((file) => ({
        name: file.name,
        size: file.size,
        type: file.type || 'application/octet-stream',
      })),
    })

    await Swal.fire({
      icon: 'success',
      title: 'Contract Request Sent',
      text: 'The company can now review your contract request.',
      confirmButtonColor: '#2563eb',
    })
    resetSchoolContractForm()
  } catch (e) {
    const firestoreCode = (e as { code?: string })?.code || ''
    const details = [
      firestoreCode ? `Code: ${firestoreCode}` : null,
      `UID: ${authStore.user?.uid || 'none'}`,
      `Role: ${authStore.user?.role || 'none'}`,
      `schoolId payload: ${schoolId}`,
      `companyId payload: ${schoolContractForm.value.companyId || 'none'}`,
    ].filter(Boolean).join(' | ')

    await Swal.fire({
      icon: 'error',
      title: 'Failed',
      text: e instanceof Error ? `${e.message} (${details})` : `Could not send contract request. (${details})`,
      confirmButtonColor: '#2563eb',
    })
  } finally {
    creatingContract.value = false
  }
}

async function handleCancelSchoolContract(contract: ContractRecord) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Cancel Contract?',
    input: 'textarea',
    inputLabel: 'Reason (optional)',
    inputPlaceholder: 'Add a short reason for cancellation...',
    showCancelButton: true,
    confirmButtonText: 'Cancel contract',
    cancelButtonText: 'Keep it',
    confirmButtonColor: '#dc2626',
  })

  if (!result.isConfirmed) return
  const reason = (result.value as string | undefined)?.trim() || undefined

  try {
    await cancelContract(contract.id, 'school', reason)
    await Swal.fire({
      icon: 'success',
      title: 'Cancelled',
      text: 'The contract has been cancelled.',
      confirmButtonColor: '#2563eb',
    })
  } catch (e) {
    await Swal.fire({
      icon: 'error',
      title: 'Failed',
      text: e instanceof Error ? e.message : 'Could not cancel the contract.',
      confirmButtonColor: '#2563eb',
    })
  }
}

watch(
  () => [authStore.user?.uid, authStore.user?.role] as const,
  async ([uid, role]) => {
    if (unsubSchoolStudents) {
      unsubSchoolStudents()
      unsubSchoolStudents = null
    }
    if (unsubSchoolContracts) {
      unsubSchoolContracts()
      unsubSchoolContracts = null
    }
    if (unsubCompaniesPublic) {
      unsubCompaniesPublic()
      unsubCompaniesPublic = null
    }

    if (!uid || role !== 'school') {
      schoolStudentEmails.value = []
      schoolContracts.value = []
      companiesForContract.value = []
      return
    }

    ensurePublicProfile(uid, {
      displayName: schoolName.value,
      role: 'school',
      orgName: schoolName.value,
      email: authStore.user?.email,
      courses: ((authStore.user?.profile as Record<string, unknown> | undefined)?.courses as string[] | undefined) || [],
    }).catch(() => {})

    unsubSchoolStudents = subscribeSchoolStudents(uid, (items) => {
      schoolStudentEmails.value = items
    })
    unsubSchoolContracts = subscribeSchoolContracts(uid, (items) => {
      schoolContracts.value = items
    })

    // Initial load (helps when snapshot takes time or when debugging empty lists)
    try {
      companiesForContract.value = await listPublicProfiles('company')
    } catch {
      companiesForContract.value = []
    }

    unsubCompaniesPublic = subscribePublicProfiles('company', (items) => {
      const byUid = new Map<string, PublicProfile>()
      for (const item of items) byUid.set(item.uid, item)
      companiesForContract.value = Array.from(byUid.values())
    })
  },
  { immediate: true }
)

onUnmounted(() => {
  if (unsubSchoolStudents) unsubSchoolStudents()
  if (unsubSchoolContracts) unsubSchoolContracts()
  if (unsubCompaniesPublic) unsubCompaniesPublic()
})

async function addStudentEmail() {
  const uid = authStore.user?.uid
  const email = generatedStudentEmail.value.trim()
  if (!uid || !email) return
  addingStudentEmail.value = true
  try {
    const defaultPassword = generateDefaultStudentPassword(generatedStudentFullName.value || email)
    await addSchoolStudent(uid, email, {
      studentName: generatedStudentFullName.value || undefined,
      studentNumber: newStudentId.value.trim() || undefined,
      defaultPassword,
      course: newStudentCourse.value.trim() || undefined,
      yearLevel: newStudentYearLevel.value.trim() || undefined,
    })
    await Swal.fire({
      icon: 'success',
      title: 'Student Email Added',
      html: `<div style="text-align:left">` +
        `<p><strong>Email:</strong> ${email}</p>` +
        `<p><strong>Default Password:</strong> ${defaultPassword}</p>` +
        `<p style="margin-top:8px">Share these credentials with the student.</p>` +
      `</div>`,
      confirmButtonColor: '#2563eb',
    })
    newStudentFirstName.value = ''
    newStudentLastName.value = ''
    // Don't clear domain - it's auto-populated
    newStudentId.value = ''
    newStudentCourse.value = ''
    newStudentYearLevel.value = ''
  } catch (err) {
    await Swal.fire({
      icon: 'error',
      title: 'Failed',
      text: err instanceof Error ? err.message : 'Could not add student email.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    addingStudentEmail.value = false
  }
}

async function removeStudentEmail(record: SchoolStudentRecord) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Remove Student Email?',
    text: `${record.email} will no longer be able to register as a student.`,
    showCancelButton: true,
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#6b7280',
  })
  if (!result.isConfirmed) return
  try {
    await removeSchoolStudent(record.id)
    await Swal.fire({ icon: 'success', title: 'Removed', confirmButtonColor: '#2563eb' })
  } catch (err) {
    await Swal.fire({
      icon: 'error',
      title: 'Failed',
      text: err instanceof Error ? err.message : 'Could not remove.',
      confirmButtonColor: '#2563eb',
    })
  }
}

</script>

<template>
  <div class="dashboard">
    <!-- School Sidebar Component -->
    <SchoolSidebar :activeItem="currentView" @menuClick="handleMenuClick" />

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
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
          <!-- Welcome Banner -->
          <div class="welcome-banner">
            <h2>
              Hello, Admin! 
              <HandRaisedIcon class="welcome-icon" />
            </h2>
            <p>You have {{ dashboardStats.applications.active }} pending applications and {{ recommendedInternships.length }} recommended internship.</p>
          </div>

          <!-- Stats Cards -->
          <div class="stats-grid">
            <!-- OJT Hours Card -->
            <div class="stat-card">
              <div class="stat-header">
                <span class="stat-label">OJT HOURS</span>
              </div>
              <div class="stat-main">
                <div class="stat-number-large">{{ dashboardStats.ojtHours.current }} / {{ dashboardStats.ojtHours.total }}</div>
                <div class="progress-bar-container">
                  <div class="progress-bar" :style="{ width: (dashboardStats.ojtHours.current / dashboardStats.ojtHours.total * 100) + '%' }"></div>
                </div>
                <div class="stat-subtitle">{{ dashboardStats.ojtHours.remaining }} hours remaining</div>
              </div>
              <a href="#" class="stat-link">View details →</a>
            </div>

            <!-- Applications Card -->
            <div class="stat-card">
              <div class="stat-header">
                <span class="stat-label">APPLICATIONS</span>
              </div>
              <div class="stat-main">
                <div class="stat-number-large">{{ dashboardStats.applications.active }} Active</div>
                <div class="stat-details">
                  <div>{{ dashboardStats.applications.underReview }} Under Review</div>
                  <div>{{ dashboardStats.applications.scheduled }} Interview Scheduled</div>
                  <div>{{ dashboardStats.applications.accepted }} Accepted</div>
                </div>
              </div>
              <a href="#" class="stat-link">View all →</a>
            </div>

            <!-- Current Internship Card -->
            <div class="stat-card">
              <div class="stat-header">
                <span class="stat-label">CURRENT INTERNSHIP</span>
              </div>
              <div class="stat-main">
                <div class="company-logo-card">
                  <img src="/icons/company-icon.png" alt="Company" />
                </div>
                <div class="company-info-card">
                  <div class="company-name-card">{{ dashboardStats.currentInternship.company }}</div>
                  <div class="position-title-card">{{ dashboardStats.currentInternship.position }}</div>
                  <div class="status-card">{{ dashboardStats.currentInternship.status }}</div>
                </div>
              </div>
              <a href="#" class="stat-link">View details →</a>
            </div>

            <!-- Pending Tasks Card -->
            <div class="stat-card">
              <div class="stat-header">
                <span class="stat-label">PENDING TASKS</span>
              </div>
              <div class="stat-main">
                <div class="stat-number-large">{{ dashboardStats.pendingTasks.length }}</div>
                <div class="task-list-card">
                  <div v-for="task in dashboardStats.pendingTasks" :key="task.task" class="task-item-card">
                    • {{ task.task }}
                  </div>
                </div>
              </div>
              <a href="#" class="stat-link">View tasks →</a>
            </div>
          </div>

          <!-- Recommended Section -->
          <section class="recommended-section">
            <div class="section-header">
              <h3>Recommended for You</h3>
              <p>Based on your profile</p>
            </div>

            <div class="internships-grid">
              <div v-for="internship in recommendedInternships" :key="internship.id" class="internship-card">
                <div class="internship-header">
                  <div class="company-logo-small">
                    <img src="/icons/company-icon.png" alt="Company" />
                  </div>
                  <div class="match-badge">{{ internship.match }}% Match</div>
                </div>
                
                <div class="internship-content">
                  <h4>{{ internship.title }}</h4>
                  <p class="company">{{ internship.company }}</p>
                  <p class="location">
                    <MapPinIcon class="location-icon-inline" />
                    {{ internship.location }}
                  </p>
                  
                  <div class="skills-tags">
                    <span v-for="skill in internship.skills" :key="skill" class="skill-tag">
                      {{ skill }}
                    </span>
                  </div>
                </div>

                <div class="internship-actions">
                  <button class="btn-primary">View Details</button>
                  <button class="btn-secondary">
                    <img src="/icons/heart-icon.png" alt="Like" class="heart-icon" />
                  </button>
                </div>
              </div>
            </div>
          </section>

          <!-- Bottom Grid -->
          <div class="bottom-grid">
            <!-- Recent Activity -->
            <section class="activity-section">
              <h3>Recent Activity</h3>
              <div class="activity-list">
                <div v-for="activity in recentActivities" :key="activity.id" class="activity-item">
                  <div class="activity-dot" :class="activity.status"></div>
                  <div class="activity-content">
                    <p>{{ activity.text }}</p>
                    <span class="activity-time">{{ activity.time }}</span>
                  </div>
                </div>
              </div>
              <a href="#" class="view-all-link">View all activity →</a>
            </section>

            <!-- Upcoming Deadlines -->
            <section class="deadlines-section">
              <h3>Upcoming Deadlines</h3>
              <div class="deadlines-list">
                <div v-for="deadline in upcomingDeadlines" :key="deadline.id" class="deadline-item">
                  <div class="deadline-icon" :class="deadline.type">
                    <ExclamationTriangleIcon v-if="deadline.type === 'warning'" class="deadline-icon-svg" />
                    <InformationCircleIcon v-else class="deadline-icon-svg" />
                  </div>
                  <div class="deadline-content">
                    <p class="deadline-title">{{ deadline.title }}</p>
                    <span class="deadline-date">{{ deadline.date }} • {{ deadline.daysRemaining }} days remaining</span>
                  </div>
                </div>
              </div>
              <a href="#" class="view-all-link">
                <CalendarIcon class="calendar-icon-inline" />
                View calendar
              </a>
            </section>
          </div>
        </main>
      </div>

      <!-- Other Views (Placeholder) -->
      <div v-else-if="currentView === 'internship-post'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-post_new_internship.png" alt="Internship Post" class="header-icon-img" />
            <h1>Pending Approvals</h1>
          </div>
          <div class="header-right">
            <div class="header-actions">
              <div class="status-dropdown">
                <select class="dropdown-select">
                  <option value="all-pending">All pending</option>
                  <option value="new">New</option>
                  <option value="under-review">Under Review</option>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>
              <input type="search" placeholder="Search by company or position..." class="search-input" />
            </div>
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content">
          <div class="approvals-layout">
            <!-- Left Column - Pending Posts -->
            <div class="approvals-main">
              <p class="approvals-subtitle">Review and approve internship posts from partner companies</p>
              
              <!-- Alert Banner -->
              <div class="alert-banner">
                <div class="alert-icon">
                  <ExclamationTriangleIcon class="alert-icon-svg" />
                </div>
                <div class="alert-content">
                  <strong>3 New Internship Post Awaiting Review</strong>
                  <p>Review now to make them available to students</p>
                </div>
              </div>

              <!-- Internship Posts -->
              <div class="internship-posts">
                <!-- TechStart Solutions Post -->
                <div class="post-card">
                  <div class="post-header">
                    <div class="company-avatar">TS</div>
                    <div class="company-info">
                      <div class="company-name">
                        TechStart Solutions
                        <CheckCircleIcon class="verified-badge-icon" />
                      </div>
                      <div class="company-type">Information Technology</div>
                    </div>
                    <div class="status-badge status-new">New</div>
                  </div>

                  <div class="post-content">
                    <h3 class="post-title">Full Stack Development Intern</h3>
                    <div class="post-details">
                      <div class="detail-item">
                        <MapPinIcon class="detail-icon-svg" />
                        <span>Bacoor City</span>
                      </div>
                      <div class="detail-item">
                        <CalendarIcon class="detail-icon-svg" />
                        <span>3 months (June - August 2024)</span>
                      </div>
                      <div class="detail-item">
                        <UsersIcon class="detail-icon-svg" />
                        <span>5 openings</span>
                      </div>
                      <div class="detail-item">
                        <CurrencyDollarIcon class="detail-icon-svg" />
                        <span>₱15,000/month</span>
                      </div>
                      <div class="detail-item">
                        <ClipboardDocumentListIcon class="detail-icon-svg" />
                        <span>Full-time Status</span>
                      </div>
                    </div>

                    <p class="post-description">
                      BS Computer Science 3rd year or higher. Proficient in React and Redux, Node.js communication skills.
                      View full Document requirements
                    </p>

                    <div class="post-tags">
                      <span class="tag tag-skill">JavaScript</span>
                      <span class="tag tag-skill">Business Portal</span>
                      <span class="tag tag-skill">Previous Postings</span>
                      <span class="tag tag-count">+3/5</span>
                    </div>
                  </div>

                  <div class="post-actions">
                    <button class="btn-approve">Review Posting</button>
                    <button class="btn-secondary-action">Quick Approve</button>
                  </div>
                </div>

                <!-- DataCorp Analytics Post -->
                <div class="post-card">
                  <div class="post-header">
                    <div class="company-avatar">TS</div>
                    <div class="company-info">
                      <div class="company-name">
                        DataCorp Analytics
                        <CheckCircleIcon class="verified-badge-icon" />
                      </div>
                      <div class="company-type">Data Science & Analytics</div>
                    </div>
                    <div class="status-badge status-interview">Interview</div>
                  </div>

                  <div class="post-content">
                    <h3 class="post-title">Data Analytics Intern</h3>
                    <div class="post-details">
                      <div class="detail-item">
                        <MapPinIcon class="detail-icon-svg" />
                        <span>BGC, Taguig</span>
                      </div>
                      <div class="detail-item">
                        <CalendarIcon class="detail-icon-svg" />
                        <span>4 months (June - August 2024)</span>
                      </div>
                      <div class="detail-item">
                        <UsersIcon class="detail-icon-svg" />
                        <span>3 openings</span>
                      </div>
                      <div class="detail-item">
                        <CurrencyDollarIcon class="detail-icon-svg" />
                        <span>₱18,000/month</span>
                      </div>
                      <div class="detail-item">
                        <ClipboardDocumentListIcon class="detail-icon-svg" />
                        <span>From May 30, 2024</span>
                      </div>
                    </div>

                    <p class="post-description">
                      BS Statistics, Mathematics or Computer Science. Experience with Python, SQL, and data visualization tools.
                      View full Document requirements
                    </p>

                    <div class="post-tags">
                      <span class="tag tag-skill">Python</span>
                      <span class="tag tag-skill">Business Portal</span>
                      <span class="tag tag-skill">Previous Postings</span>
                      <span class="tag tag-count">+4/6</span>
                    </div>
                  </div>

                  <div class="post-actions">
                    <button class="btn-approve">Review Posting</button>
                    <button class="btn-secondary-action">Request Changes</button>
                  </div>
                </div>

                <!-- Creative Design Studio Post -->
                <div class="post-card">
                  <div class="post-header">
                    <div class="company-avatar">TS</div>
                    <div class="company-info">
                      <div class="company-name">
                        Creative Design Studio
                        <CheckCircleIcon class="verified-badge-icon" />
                      </div>
                      <div class="company-type">Creative & Design</div>
                    </div>
                    <div class="status-badge status-rejected">Rejected</div>
                  </div>

                  <div class="post-content">
                    <h3 class="post-title">UI/UX Design Intern</h3>
                    <div class="post-details">
                      <div class="detail-item">
                        <MapPinIcon class="detail-icon-svg" />
                        <span>Alabang, Muntinlupa</span>
                      </div>
                      <div class="detail-item">
                        <CalendarIcon class="detail-icon-svg" />
                        <span>3 months (June - August 2024)</span>
                      </div>
                      <div class="detail-item">
                        <UsersIcon class="detail-icon-svg" />
                        <span>2 openings</span>
                      </div>
                      <div class="detail-item">
                        <CurrencyDollarIcon class="detail-icon-svg" />
                        <span>₱12,000/month</span>
                      </div>
                      <div class="detail-item">
                        <ClipboardDocumentListIcon class="detail-icon-svg" />
                        <span>From June 15, 2024</span>
                      </div>
                    </div>

                    <p class="post-description">
                      BS Multimedia Arts or related field. Portfolio required. Proficiency in Figma, Adobe XD, and design principles.
                      View full Document requirements
                    </p>

                    <div class="post-tags">
                      <span class="tag tag-skill">Figma</span>
                      <span class="tag tag-skill">Business Portal</span>
                      <span class="tag tag-skill">Previous Postings</span>
                      <span class="tag tag-count">+2/8</span>
                    </div>
                  </div>

                  <div class="post-actions">
                    <button class="btn-approve">Review Posting</button>
                    <button class="btn-secondary-action">Quick Approve</button>
                  </div>
                </div>
              </div>

              <div class="pagination">
                <span>Showing 3 of 5 pending posts</span>
                <a href="#" class="view-archive">View Approved/Reject Archive</a>
              </div>
            </div>

            <!-- Right Sidebar -->
            <div class="approvals-sidebar">
              <!-- Review Statistics -->
              <div class="sidebar-section">
                <h3>Review Statistics</h3>
                <div class="stats-list">
                  <div class="stat-item">
                    <span class="stat-label">This Week Reviewed</span>
                    <span class="stat-value">8</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Approved</span>
                    <span class="stat-value">7</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Rejected</span>
                    <span class="stat-value">1</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Average Review Time</span>
                    <span class="stat-value">4 hours</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Pending Backlog</span>
                    <span class="stat-value">3</span>
                  </div>
                </div>
              </div>

              <!-- Quick Actions -->
              <div class="sidebar-section">
                <h3>Quick Actions</h3>
                <div class="quick-actions">
                  <button class="quick-action-btn">
                    <CheckIcon class="action-icon-svg" />
                    <span>Approve All Verified</span>
                  </button>
                  <button class="quick-action-btn">
                    <ClipboardDocumentListIcon class="action-icon-svg" />
                    <span>Schedule Batch Meeting</span>
                  </button>
                  <button class="quick-action-btn">
                    <EnvelopeIcon class="action-icon-svg" />
                    <span>Send Company Notification</span>
                  </button>
                </div>
              </div>

              <!-- Recent Activity -->
              <div class="sidebar-section">
                <h3>Recent Activity</h3>
                <div class="activity-feed">
                  <div class="activity-item">
                    <span class="activity-text">Approved TechHub's system post</span>
                    <span class="activity-time">2 hours ago</span>
                  </div>
                  <div class="activity-item">
                    <span class="activity-text">Rejected application from DataCorp</span>
                    <span class="activity-time">5 hours ago</span>
                  </div>
                  <div class="activity-item">
                    <span class="activity-text">Company submitted new post</span>
                    <span class="activity-time">1 day ago</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>

      <div v-else-if="currentView === 'applications'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-application.png" alt="Applications" class="header-icon-img" />
            <h1>Internship Applications</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content">
          <!-- Search and Filters -->
          <div class="applications-controls">
            <div class="search-section">
              <div class="search-box">
                <MagnifyingGlassIcon class="search-icon-svg" />
                <input type="search" placeholder="Search Student..." class="search-input-table" />
              </div>
            </div>
            <div class="filter-section">
              <select class="filter-dropdown">
                <option value="all-courses">All Courses</option>
                <option value="bsit">BSIT</option>
                <option value="bscs">BSCS</option>
                <option value="bsece">BSECE</option>
              </select>
              <select class="filter-dropdown">
                <option value="all-years">All Year Level</option>
                <option value="3rd-year">3rd Year</option>
                <option value="4th-year">4th Year</option>
              </select>
              <select class="filter-dropdown">
                <option value="pending">Pending Applications</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="active">Active Intern</option>
                <option value="completed">Completed</option>
              </select>
            </div>
          </div>

          <!-- Applications Table -->
          <div class="applications-table-container">
            <table class="applications-table">
              <thead>
                <tr>
                  <th>Student Name</th>
                  <th>Student ID</th>
                  <th>Course</th>
                  <th>Year Level</th>
                  <th>Status</th>
                  <th>Eligibility</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="student in studentsData" :key="student.id" @click="openStudentSidebar(student)" class="clickable-row">
                  <td class="student-name">{{ student.name }}</td>
                  <td class="student-id">{{ student.studentId }}</td>
                  <td>{{ student.course }}</td>
                  <td>{{ student.yearLevel }}</td>
                  <td>
                    <span class="status-badge" :class="{
                      'status-registered': student.status === 'Registered',
                      'status-active': student.status === 'Active Intern',
                      'status-pending': student.status === 'Pending Application',
                      'status-completed': student.status === 'Completed'
                    }">
                      {{ student.status }}
                    </span>
                  </td>
                  <td>
                    <span class="eligibility-badge" :class="{
                      'eligible': student.eligibility === 'Eligible',
                      'pending': student.eligibility === 'Pending',
                      'not-eligible': student.eligibility === 'Not Eligible'
                    }">
                      {{ student.eligibility }}
                    </span>
                  </td>
                  <td class="actions-cell">
                    <button class="action-menu-btn" @click.stop>
                      <EllipsisVerticalIcon class="action-menu-icon" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </main>

        <!-- Student Details Sidebar -->
      </div>

      <div v-else-if="currentView === 'companies'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/company-icon.png" alt="Companies" class="header-icon-img" />
            <h1>Companies</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content">
          <!-- Page Header -->
          <div class="companies-page-header">
            <h2>Manage Industry Partners</h2>
            <p class="page-subtitle">Oversee company profiles, internship offerings, MOAs, and performance feedback for all partner organizations.</p>
          </div>

          <!-- Search and Filters -->
          <div class="companies-controls">
            <div class="search-section">
              <div class="search-box">
                <MagnifyingGlassIcon class="search-icon-svg" />
                <input type="search" placeholder="Search companies by name or industry..." class="search-input-companies" />
              </div>
            </div>
            <div class="filter-section">
              <select class="filter-dropdown">
                <option value="all-industries">All Industries</option>
                <option value="technology">Technology</option>
                <option value="healthcare">Healthcare</option>
                <option value="finance">Finance</option>
                <option value="education">Education</option>
              </select>
              <select class="filter-dropdown">
                <option value="all-status">All Status</option>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>

          <!-- Companies Grid -->
          <div class="companies-grid">
            <!-- Software Engineer Intern Card -->
            <div class="company-card" @click="openCompanySidebar(companiesData[0])">
              <div class="company-card-header">
                <div class="company-avatar">IC</div>
                <div class="company-details">
                  <h3>Software Engineer Intern</h3>
                  <p class="company-name">Innovate Corp.</p>
                </div>
              </div>

              <div class="company-location">
                <MapPinIcon class="location-icon-svg" />
                <span>New York, NY</span>
              </div>

              <div class="match-info">
                <span class="match-percentage">92% Match</span>
                <div class="recommended-badge">
                  <CheckCircleIcon class="recommended-icon-svg" />
                  Recommended for You
                </div>
              </div>

              <p class="company-description">
                Join Our dynamic team to develop innovative software solutions. 
                You'll work on cutting-edge projects and contribute the projects.
              </p>

              <div class="company-skills">
                <span class="skill-tag">Python</span>
                <span class="skill-tag">AWS</span>
                <span class="skill-tag">Machine Learning</span>
                <span class="skill-tag">Django</span>
              </div>

              <div class="company-rating">
                <div class="stars">
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star">★</span>
                </div>
                <span class="rating-text">4.8/5 95% Placement</span>
                <div class="rating-bar">
                  <div class="rating-fill" style="width: 95%"></div>
                </div>
              </div>
            </div>

            <!-- Data Analyst Intern Card -->
            <div class="company-card" @click="openCompanySidebar(companiesData[0])">
              <div class="company-card-header">
                <div class="company-avatar">IC</div>
                <div class="company-details">
                  <h3>Data Analyst Intern</h3>
                  <p class="company-name">Innovate Corp.</p>
                </div>
              </div>

              <div class="company-location">
                <MapPinIcon class="location-icon-svg" />
                <span>New York, NY</span>
              </div>

              <div class="match-info">
                <span class="match-percentage">89% Match</span>
                <div class="recommended-badge">
                  <CheckCircleIcon class="recommended-icon-svg" />
                  Recommended for You
                </div>
              </div>

              <p class="company-description">
                Join Our dynamic team to develop innovative software solutions. 
                You'll work on cutting-edge projects and contribute the projects.
              </p>

              <div class="company-skills">
                <span class="skill-tag">Python</span>
                <span class="skill-tag">SQL</span>
                <span class="skill-tag">Machine Learning</span>
                <span class="skill-tag">Excel</span>
              </div>

              <div class="company-rating">
                <div class="stars">
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star">★</span>
                </div>
                <span class="rating-text">4.2/5 70% Placement</span>
                <div class="rating-bar">
                  <div class="rating-fill" style="width: 70%"></div>
                </div>
              </div>
            </div>

            <!-- UI/UX Designer Intern Card -->
            <div class="company-card" @click="openCompanySidebar(companiesData[0])">
              <div class="company-card-header">
                <div class="company-avatar">IC</div>
                <div class="company-details">
                  <h3>UI/UX Designer Intern</h3>
                  <p class="company-name">Innovate Corp.</p>
                </div>
              </div>

              <div class="company-location">
                <MapPinIcon class="location-icon-svg" />
                <span>New York, NY</span>
              </div>

              <div class="match-info">
                <span class="match-percentage">85% Match</span>
                <div class="recommended-badge">
                  <CheckCircleIcon class="recommended-icon-svg" />
                  Recommended for You
                </div>
              </div>

              <p class="company-description">
                Join Our dynamic team to develop innovative software solutions. 
                You'll work on cutting-edge projects and contribute the projects.
              </p>

              <div class="company-skills">
                <span class="skill-tag">Figma</span>
                <span class="skill-tag">Prototyping</span>
                <span class="skill-tag">Design System</span>
              </div>

              <div class="company-rating">
                <div class="stars">
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star">★</span>
                  <span class="star">★</span>
                </div>
                <span class="rating-text">3.0/5 65% Placement</span>
                <div class="rating-bar">
                  <div class="rating-fill" style="width: 65%"></div>
                </div>
              </div>
            </div>

            <!-- Business Development Intern Card -->
            <div class="company-card" @click="openCompanySidebar(companiesData[0])">
              <div class="company-card-header">
                <div class="company-avatar">IC</div>
                <div class="company-details">
                  <h3>Business Development Intern</h3>
                  <p class="company-name">Innovate Corp.</p>
                </div>
              </div>

              <div class="company-location">
                <MapPinIcon class="location-icon-svg" />
                <span>New York, NY</span>
              </div>

              <div class="match-info">
                <span class="match-percentage">92% Match</span>
                <div class="recommended-badge">
                  <CheckCircleIcon class="recommended-icon-svg" />
                  Recommended for You
                </div>
              </div>

              <p class="company-description">
                Join Our dynamic team to develop innovative software solutions. 
                You'll work on cutting-edge projects and contribute the projects.
              </p>

              <div class="company-skills">
                <span class="skill-tag">Python</span>
                <span class="skill-tag">AWS</span>
                <span class="skill-tag">Machine Learning</span>
                <span class="skill-tag">Django</span>
              </div>

              <div class="company-rating">
                <div class="stars">
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star">★</span>
                </div>
                <span class="rating-text">4.4/5 75% Placement</span>
                <div class="rating-bar">
                  <div class="rating-fill" style="width: 75%"></div>
                </div>
              </div>
            </div>
          </div>
        </main>

        <!-- Company Details Sidebar -->
        <div v-if="showCompanySidebar" class="company-sidebar-overlay" @click="closeCompanySidebar">
          <div class="company-sidebar" @click.stop>
            <div class="company-sidebar-header">
              <div class="company-header-info">
                <div class="company-avatar-large">{{ selectedCompany?.avatar }}</div>
                <div class="company-header-details">
                  <h2>{{ selectedCompany?.name }}</h2>
                  <span class="partner-status">Partner since 2019</span>
                </div>
              </div>
              <button class="close-btn" @click="closeCompanySidebar">✕</button>
            </div>

            <!-- Tab Navigation -->
            <div class="company-tabs">
              <button 
                class="tab-btn" 
                :class="{ active: activeCompanyTab === 'general' }"
                @click="setActiveCompanyTab('general')"
              >
                Company Info
              </button>
              <button 
                class="tab-btn" 
                :class="{ active: activeCompanyTab === 'contacts' }"
                @click="setActiveCompanyTab('contacts')"
              >
                Key Contacts
              </button>
              <button 
                class="tab-btn" 
                :class="{ active: activeCompanyTab === 'offerings' }"
                @click="setActiveCompanyTab('offerings')"
              >
                Internship Offerings
              </button>
              <button 
                class="tab-btn" 
                :class="{ active: activeCompanyTab === 'moa' }"
                @click="setActiveCompanyTab('moa')"
              >
                MOA History
              </button>
              <button 
                class="tab-btn" 
                :class="{ active: activeCompanyTab === 'feedback' }"
                @click="setActiveCompanyTab('feedback')"
              >
                Intern Feedback
              </button>
            </div>

            <div class="company-sidebar-content">
              <!-- General Information Tab -->
              <div v-if="activeCompanyTab === 'general'" class="tab-content">
                <h3>General Information</h3>
                <div class="company-info-grid">
                  <div class="info-item">
                    <span class="info-label">Address:</span>
                    <span class="info-value">{{ selectedCompany?.generalInfo?.address }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Contact Email:</span>
                    <span class="info-value">{{ selectedCompany?.generalInfo?.contactEmail }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Website:</span>
                    <span class="info-value">{{ selectedCompany?.generalInfo?.website }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Partner Status:</span>
                    <span class="status-active">{{ selectedCompany?.generalInfo?.partnerStatus }}</span>
                  </div>
                </div>

                <h4>Performance Metrics Summary</h4>
                <div class="metrics-grid">
                  <div class="metric-item">
                    <span class="metric-label">Average Rating:</span>
                    <span class="metric-value">4.2/5 ⭐</span>
                  </div>
                  <div class="metric-item">
                    <span class="metric-label">Placement Rate:</span>
                    <span class="metric-value">70%</span>
                  </div>
                </div>
              </div>

              <!-- Key Contacts Tab -->
              <div v-if="activeCompanyTab === 'contacts'" class="tab-content">
                <h3>Key Contacts</h3>
                <div v-for="contact in selectedCompany?.keyContacts" :key="contact.name" class="contact-item">
                  <div class="contact-info">
                    <h4>{{ contact.name }}</h4>
                    <p class="contact-title">{{ contact.title }}</p>
                    <p class="contact-email">📧 {{ contact.email }}</p>
                  </div>
                </div>
              </div>

              <!-- Internship Offerings Tab -->
              <div v-if="activeCompanyTab === 'offerings'" class="tab-content">
                <h3>Internship Offerings</h3>
                <div class="offerings-list">
                  <div v-for="offering in selectedCompany?.internshipOfferings" :key="offering.title" class="offering-item">
                    <div class="offering-header">
                      <h4>{{ offering.title }}</h4>
                      <span class="expand-icon">⌄</span>
                    </div>
                    <p class="offering-requirements">{{ offering.requirements }}</p>
                    <div class="offering-details">
                      <span class="placement-info">Placement: {{ offering.placement }}</span>
                      <span class="status-active">Status: {{ offering.status }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- MOA History Tab -->
              <div v-if="activeCompanyTab === 'moa'" class="tab-content">
                <h3>MOA History</h3>
                <div v-for="moa in selectedCompany?.moaHistory" :key="moa.title" class="moa-item">
                  <h4>{{ moa.title }}</h4>
                  <div class="moa-details">
                    <p>Date: {{ moa.date }}</p>
                    <p>Status: <span class="status-active">{{ moa.status }}</span></p>
                    <p>Expires: {{ moa.expires }}</p>
                  </div>
                </div>
              </div>

              <!-- Intern Feedback Tab -->
              <div v-if="activeCompanyTab === 'feedback'" class="tab-content">
                <h3>Intern Feedback</h3>
                <div v-for="feedback in selectedCompany?.internFeedback" :key="feedback.name" class="feedback-item">
                  <div class="feedback-header">
                    <h4>{{ feedback.name }}</h4>
                    <div class="feedback-rating">
                      <span v-for="i in 5" :key="i" class="star" :class="{ filled: i <= feedback.rating }">★</span>
                    </div>
                  </div>
                  <p class="feedback-text">{{ feedback.feedback }}</p>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="company-sidebar-actions">
              <button class="btn-edit-company">📝 Edit Company Details</button>
              <button class="btn-view-moas">
                <DocumentIcon class="btn-inline-icon" />
                View All MOAs
              </button>
          </div>
          </div>
          </div>
      </div>

      <div v-else-if="currentView === 'contracts'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-docs.png" alt="Contracts" class="header-icon-img" />
            <h1>School Contract Requests</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content">
          <div class="student-emails-section">
            <h2 class="section-title">Create Contract Requests</h2>
            <p class="section-subtitle">Schools initiate partnership contracts with companies. Add complete details and files, then send the request.</p>

            <div class="section-card">
              <div class="add-student-form">
                <div class="form-row">
                  <div class="form-group">
                    <label>Select Company <span class="required">*</span></label>
                    <select v-model="schoolContractForm.companyId" class="form-input" @change="onSchoolContractCompanySelect">
                      <option value="">-- Select Company --</option>
                      <option v-for="c in companiesForContract" :key="c.uid" :value="c.uid">{{ c.orgName || c.displayName || c.email || c.uid }}</option>
                    </select>
                    <p v-if="companiesForContract.length === 0" class="bulk-import-hint" style="margin-top:8px;">
                      No companies available yet. Ask a company to log in and complete their profile (Company Settings) so it appears here.
                    </p>
                  </div>
                  <div class="form-group">
                    <label>Contract Type</label>
                    <input v-model="schoolContractForm.contractType" type="text" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>MOA Reference No.</label>
                    <input :value="schoolContractForm.moaReferenceNo || autoGeneratedMoaRef" type="text" class="form-input" readonly style="background: #f3f4f6; cursor: not-allowed;" />
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Subject <span class="required">*</span></label>
                    <input v-model="schoolContractForm.subject" type="text" class="form-input" placeholder="e.g. OJT Partnership for SY 2026" />
                    <p v-if="selectedCompanyHasPendingContract" class="bulk-import-hint" style="color:#b45309; margin-top:6px;">
                      You already have a pending contract request for this company. Cancel the pending request or wait for the company response.
                    </p>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Purpose</label>
                    <textarea v-model="schoolContractForm.purpose" rows="2" class="form-input" placeholder="Purpose of the partnership..."></textarea>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Start Date</label>
                    <input v-model="schoolContractForm.startDate" type="date" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>End Date</label>
                    <input v-model="schoolContractForm.endDate" type="date" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>Internship Slots</label>
                    <input v-model="schoolContractForm.internshipSlots" type="number" min="1" class="form-input" />
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group full-width">
                    <label>Aligned Courses (select and set slots)</label>
                    <p v-if="!schoolContractForm.companyId" class="bulk-import-hint">Select a company to see aligned courses.</p>
                    <p v-else-if="alignedContractCourses.length === 0" class="bulk-import-hint">
                      No aligned courses found. Add your school courses in Settings, and make sure the company adds the courses they accept.
                    </p>
                    <div v-else class="aligned-courses">
                      <button
                        v-for="course in alignedContractCourses"
                        :key="course"
                        type="button"
                        class="aligned-course-btn"
                        :class="{ active: selectedContractCourses.includes(course) }"
                        @click="toggleAlignedCourse(course)"
                      >
                        {{ course }}
                      </button>
                    </div>
                    <div v-if="selectedContractCourses.length" class="course-allocation-grid">
                      <div v-for="course in selectedContractCourses" :key="course" class="course-allocation-item">
                        <div class="course-allocation-label">
                          <span>{{ course }}</span>
                          <button type="button" class="file-chip-remove" @click="toggleAlignedCourse(course)">x</button>
                        </div>
                        <input
                          v-model="contractCourseSlots[course]"
                          type="number"
                          min="1"
                          class="form-input course-slot-input"
                          :placeholder="`How many ${course} students`"
                        />
                      </div>
                    </div>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>School Contact Name</label>
                    <input v-model="schoolContractForm.schoolContactName" type="text" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>School Contact Email</label>
                    <input v-model="schoolContractForm.schoolContactEmail" type="email" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>Company Contact Name</label>
                    <input v-model="schoolContractForm.companyContactName" type="text" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>Company Contact Email</label>
                    <input v-model="schoolContractForm.companyContactEmail" type="email" class="form-input" />
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>School Responsibilities</label>
                    <textarea v-model="schoolContractForm.schoolResponsibilities" rows="3" class="form-input" placeholder="Responsibilities of school..."></textarea>
                  </div>
                  <div class="form-group">
                    <label>Company Responsibilities</label>
                    <textarea v-model="schoolContractForm.companyResponsibilities" rows="3" class="form-input" placeholder="Responsibilities of company..."></textarea>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Terms and Conditions</label>
                    <textarea v-model="schoolContractForm.terms" rows="3" class="form-input" placeholder="Contract terms..."></textarea>
                  </div>
                  <div class="form-group">
                    <label>Additional Notes</label>
                    <textarea v-model="schoolContractForm.notes" rows="3" class="form-input" placeholder="Notes for company review..."></textarea>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Supporting Files</label>
                    <input id="school-contract-file-input" type="file" multiple class="hidden-file-input" @change="onSchoolContractFileSelect" />
                    <button type="button" class="btn-import-file" @click="triggerContractFileInput">
                      <DocumentIcon class="icon-sm" /> Add Files
                    </button>
                    <p class="bulk-import-hint">Attach draft MOA, guidelines, or supporting files (metadata only).</p>
                    <div v-if="contractFiles.length > 0" class="file-chip-list">
                      <div v-for="(f, idx) in contractFiles" :key="`${f.name}-${idx}`" class="file-chip">
                        <span>{{ f.name }}</span>
                        <button type="button" class="file-chip-remove" @click="removeContractFile(idx)">x</button>
                      </div>
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  class="btn-add-email"
                  :disabled="creatingContract || !schoolContractForm.companyId || selectedCompanyHasPendingContract"
                  @click="submitSchoolContractRequest"
                >
                  <PlusIcon class="icon-sm" />
                  {{ creatingContract ? 'Sending...' : 'Send Contract Request' }}
                </button>
              </div>
            </div>

            <h2 class="section-title" style="margin-top: 28px;">Sent Contract Requests</h2>
            <div v-if="schoolContracts.length === 0" class="empty-state">
              No contract requests sent yet.
            </div>
            <div v-else class="contracts-table-wrap">
              <table class="contracts-table">
                <thead>
                  <tr>
                    <th>Ref</th>
                    <th>Company</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="c in schoolContracts" :key="c.id">
                    <tr>
                      <td class="mono">{{ c.moaReferenceNo || '-' }}</td>
                      <td>{{ c.companyName }}</td>
                      <td>{{ c.subject || '-' }}</td>
                      <td>
                        <span class="status-badge" :class="displayContractStatus(c.status)">{{ displayContractStatusLabel(c.status) }}</span>
                      </td>
                      <td>{{ formatApplicationDate(c.createdAt) }}</td>
                      <td class="actions-cell">
                        <button type="button" class="table-btn" @click="toggleSentContractDetails(c.id)">
                          {{ expandedSentContractId === c.id ? 'Hide' : 'View' }}
                        </button>
                        <button
                          v-if="(c.status || 'pending') === 'pending' || (c.status || 'pending') === 'active'"
                          type="button"
                          class="table-btn danger"
                          @click="handleCancelSchoolContract(c)"
                        >
                          Cancel
                        </button>
                      </td>
                    </tr>
                    <tr v-if="expandedSentContractId === c.id" class="details-row">
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

      <div v-else-if="currentView === 'submit-report'">
        <header class="top-header">
          <div class="header-left">
            <div class="breadcrumb-header">
              <span class="breadcrumb-item">My Reports</span>
              <span class="breadcrumb-separator">></span>
              <span class="breadcrumb-item active">Submit Report</span>
            </div>
          </div>
          <div class="header-right">
            <span class="step-indicator">Step 1 of 3</span>
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content submit-report-main">
          <div class="submit-report-page">
            <div class="submit-report-header-page">
              <h1>Submit New Report</h1>
            </div>

            <div class="submit-report-form-container">
              <form class="report-form-page">
                <div class="form-row">
                  <div class="form-group">
                    <label for="reportType">Report Type <span class="required">*</span></label>
                    <select id="reportType" v-model="reportFormData.reportType" class="form-select" required>
                      <option value="">Select report type</option>
                      <option value="weekly">Weekly Report</option>
                      <option value="monthly">Monthly Report</option>
                      <option value="final">Final Report</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="studentName">Student Name</label>
                    <input 
                      id="studentName" 
                      v-model="reportFormData.studentName" 
                      type="text" 
                      class="form-input" 
                      readonly
                    />
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label for="periodStart">Internship Period Start <span class="required">*</span></label>
                    <input 
                      id="periodStart" 
                      v-model="reportFormData.internshipPeriodStart" 
                      type="date" 
                      class="form-input" 
                      required
                    />
                  </div>
                  <div class="form-group">
                    <label for="periodEnd">Internship Period End <span class="required">*</span></label>
                    <input 
                      id="periodEnd" 
                      v-model="reportFormData.internshipPeriodEnd" 
                      type="date" 
                      class="form-input" 
                      required
                    />
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group full-width">
                    <label for="companyName">Company Name</label>
                    <input 
                      id="companyName" 
                      v-model="reportFormData.companyName" 
                      type="text" 
                      class="form-input" 
                      readonly
                    />
                  </div>
                </div>
              </form>

              <div class="submit-report-actions-page">
                <button class="btn-save-draft" @click="saveAsDraft">
                  💾 Save as Draft
                </button>
                <div class="action-buttons">
                  <button class="btn-cancel" @click="closeSubmitReportModal">Cancel</button>
                  <button class="btn-next" @click="nextStep">Next →</button>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>

      <div v-else-if="currentView === 'community'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-community.png" alt="Community" class="header-icon-img" />
            <h1>Community</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content community-main">
          <div class="community-container-school">
            <!-- Left Sidebar -->
            <aside class="left-sidebar-school">
              <!-- User Profile Card -->
              <div class="profile-card-school">
                <div class="profile-header-school">
                  <div class="profile-avatar-school">AC</div>
                  <h3 class="profile-name-school">Admin User</h3>
                  <p class="profile-title-school">School Administrator</p>
                </div>
                <div class="profile-stats-school">
                  <div class="stat-school">
                    <span class="stat-number-school">150</span>
                    <span class="stat-label-school">Connections</span>
                  </div>
                  <div class="stat-school">
                    <span class="stat-number-school">89</span>
                    <span class="stat-label-school">Profile views</span>
                  </div>
                </div>
                <button class="view-profile-btn-school">View Profile</button>
              </div>

              <!-- Suggestions -->
              <div class="suggestions-card-school">
                <h4 class="suggestions-title-school">Suggestions</h4>
                <div class="suggestions-list-school">
                  <div class="suggestion-item-school">
                    <div class="suggestion-avatar-school">JS</div>
                    <div class="suggestion-info-school">
                      <span class="suggestion-name-school">John Smith</span>
                      <span class="suggestion-title-school">HR Manager</span>
                    </div>
                    <button class="connect-btn-school">+</button>
                  </div>
                  <div class="suggestion-item-school">
                    <div class="suggestion-avatar-school">EM</div>
                    <div class="suggestion-info-school">
                      <span class="suggestion-name-school">Emily Martinez</span>
                      <span class="suggestion-title-school">Company Supervisor</span>
                    </div>
                    <button class="connect-btn-school">+</button>
                  </div>
                  <div class="suggestion-item-school">
                    <div class="suggestion-avatar-school">DK</div>
                    <div class="suggestion-info-school">
                      <span class="suggestion-name-school">David Kim</span>
                      <span class="suggestion-title-school">Student Intern</span>
                    </div>
                    <button class="connect-btn-school">+</button>
                  </div>
                </div>
              </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content-school">
              <!-- Post Creation -->
              <div class="post-creation-school">
                <div class="post-input-area-school">
                  <div class="post-avatar-school">AC</div>
                  <input type="text" placeholder="What's on your mind?" class="post-input-school" />
                </div>
                <div class="post-actions-school">
                  <button class="post-action-btn-school">
                    <PhotoIcon class="post-action-icon" />
                    Photo
                  </button>
                  <button class="post-action-btn-school">
                    <VideoCameraIcon class="post-action-icon" />
                    Video
                  </button>
                  <button class="post-action-btn-school">
                    <DocumentIcon class="post-action-icon" />
                    Document
                  </button>
                  <button class="post-btn-school">Post</button>
                </div>
              </div>

              <!-- Posts Feed -->
              <div class="posts-feed-school">
                <article class="post-school">
                  <div class="post-header-school">
                    <div class="post-author-avatar-school">JS</div>
                    <div class="post-author-info-school">
                      <h4 class="post-author-name-school">John Smith</h4>
                      <p class="post-author-title-school">HR Manager at Tech Solutions Inc.</p>
                      <span class="post-timestamp-school">2 hours ago</span>
                    </div>
                  </div>
                  <div class="post-content-school">
                    <p>Great to see our intern program growing! We're excited to welcome new students this semester. Looking forward to mentoring the next generation of professionals.</p>
                  </div>
                  <div class="post-actions-bar-school">
                    <button class="post-action-school">
                      <HandThumbUpIcon class="post-reaction-icon" />
                      12
                    </button>
                    <button class="post-action-school">
                      <ChatBubbleLeftIcon class="post-reaction-icon" />
                      3 Comment
                    </button>
                    <button class="post-action-school">
                      <ArrowPathRoundedSquareIcon class="post-reaction-icon" />
                      2 Share
                    </button>
                  </div>
                </article>

                <article class="post-school">
                  <div class="post-header-school">
                    <div class="post-author-avatar-school">EM</div>
                    <div class="post-author-info-school">
                      <h4 class="post-author-name-school">Emily Martinez</h4>
                      <p class="post-author-title-school">Student Intern at Creative Design Studio</p>
                      <span class="post-timestamp-school">5 hours ago</span>
                    </div>
                  </div>
                  <div class="post-content-school">
                    <p>Just completed my first week of internship! Learning so much about UI/UX design and working with amazing mentors. Grateful for this opportunity!</p>
                  </div>
                  <div class="post-actions-bar-school">
                    <button class="post-action-school">
                      <HandThumbUpIcon class="post-reaction-icon" />
                      24
                    </button>
                    <button class="post-action-school">
                      <ChatBubbleLeftIcon class="post-reaction-icon" />
                      8 Comment
                    </button>
                    <button class="post-action-school">
                      <ArrowPathRoundedSquareIcon class="post-reaction-icon" />
                      5 Share
                    </button>
                  </div>
                </article>

                <article class="post-school">
                  <div class="post-header-school">
                    <div class="post-author-avatar-school">DK</div>
                    <div class="post-author-info-school">
                      <h4 class="post-author-name-school">David Kim</h4>
                      <p class="post-author-title-school">Software Development Intern</p>
                      <span class="post-timestamp-school">1 day ago</span>
                    </div>
                  </div>
                  <div class="post-content-school">
                    <p>Successfully deployed my first feature to production today! Big thanks to my supervisor and the development team for their guidance and support.</p>
                  </div>
                  <div class="post-actions-bar-school">
                    <button class="post-action-school">
                      <HandThumbUpIcon class="post-reaction-icon" />
                      18
                    </button>
                    <button class="post-action-school">
                      <ChatBubbleLeftIcon class="post-reaction-icon" />
                      6 Comment
                    </button>
                    <button class="post-action-school">
                      <ArrowPathRoundedSquareIcon class="post-reaction-icon" />
                      3 Share
                    </button>
                  </div>
                </article>
              </div>
            </main>

            <!-- Right Sidebar -->
            <aside class="right-sidebar-school">
              <!-- OJT Path Logo -->
              <div class="ojt-logo-card-school">
                <img src="/icons/logo-main.png" alt="OJT Path" class="ojt-logo-school" />
                <span class="ojt-text-school">OJT Intern Path</span>
              </div>

              <!-- Top Jobs -->
              <div class="jobs-card-school">
                <h4 class="jobs-title-school">Top Internship Opportunities</h4>
                <div class="jobs-list-school">
                  <div class="job-item-school">
                    <h5 class="job-title-school">Software Developer Intern</h5>
                    <p class="job-company-school">Tech Solutions Inc.</p>
                    <p class="job-location-school">Bacoor, Cavite</p>
                    <span class="job-type-school">Full-time</span>
                  </div>
                  <div class="job-item-school">
                    <h5 class="job-title-school">Marketing Assistant</h5>
                    <p class="job-company-school">Digital Marketing Co.</p>
                    <p class="job-location-school">Imus, Cavite</p>
                    <span class="job-type-school">Part-time</span>
                  </div>
                  <div class="job-item-school">
                    <h5 class="job-title-school">Data Analyst Intern</h5>
                    <p class="job-company-school">DataCorp Analytics</p>
                    <p class="job-location-school">BGC, Taguig</p>
                    <span class="job-type-school">Full-time</span>
                  </div>
                </div>
              </div>

              <!-- Most Active Users -->
              <div class="most-viewed-card-school">
                <h4 class="most-viewed-title-school">Most Active Users</h4>
                <div class="most-viewed-list-school">
                  <div class="viewed-item-school">
                    <div class="viewed-avatar-school">MS</div>
                    <div class="viewed-info-school">
                      <span class="viewed-name-school">Maria Santos</span>
                      <span class="viewed-title-school">Project Manager</span>
                    </div>
                  </div>
                  <div class="viewed-item-school">
                    <div class="viewed-avatar-school">RT</div>
                    <div class="viewed-info-school">
                      <span class="viewed-name-school">Robert Taylor</span>
                      <span class="viewed-title-school">Senior Developer</span>
                    </div>
                  </div>
                  <div class="viewed-item-school">
                    <div class="viewed-avatar-school">SM</div>
                    <div class="viewed-info-school">
                      <span class="viewed-name-school">Sophia Martinez</span>
                      <span class="viewed-title-school">Design Lead</span>
                    </div>
                  </div>
                </div>
              </div>
            </aside>
          </div>
        </main>
      </div>

      <div v-else-if="currentView === 'documents'">
        <header class="top-header">
          <div class="header-left">
            <div class="breadcrumb-header">
              <span class="breadcrumb-item">Documents</span>
              <span class="breadcrumb-separator">›</span>
              <span class="breadcrumb-item active">My Documents</span>
            </div>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        
        <!-- Documents Toolbar -->
        <div class="documents-toolbar">
          <div class="toolbar-left">
            <label class="checkbox-label-toolbar">
              <input type="checkbox" class="select-all-checkbox-toolbar" />
              <span>Select All</span>
            </label>
            <div class="divider-vertical"></div>
            <div class="sort-container-toolbar">
              <span class="sort-label-toolbar">Sort by:</span>
              <select class="sort-select-toolbar">
                <option>Recent</option>
                <option>Name</option>
                <option>Size</option>
                <option>Status</option>
              </select>
            </div>
          </div>
          <div class="toolbar-right">
            <div class="search-container-toolbar">
              <MagnifyingGlassIcon class="search-icon-toolbar" />
              <input 
                type="text" 
                placeholder="Search documents..." 
                class="search-input-toolbar"
              />
            </div>
            <button class="filter-btn-toolbar">
              <svg class="filter-icon-toolbar" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
              </svg>
            </button>
            <button class="upload-document-btn" @click="showUploadDocumentModal = true">
              <svg class="upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              Upload Document
            </button>
          </div>
        </div>
        
        <main class="main-content">

          <!-- Documents Grid -->
          <div class="documents-grid-school">
            <!-- Document Card 1 -->
            <div class="document-card-school">
              <input type="checkbox" class="doc-checkbox-school" />
              <div class="doc-type-badge-school">PDF</div>
              <div class="doc-icon-wrapper-school">
                <div class="doc-icon-container-school">
                  <img src="/icons/icon-pdf.png" alt="PDF" class="doc-icon-school" />
                </div>
              </div>
              <div class="doc-info-school">
                <h3 class="doc-name-school">Resume_Maria_Santos_2024.pdf</h3>
                <div class="doc-details-school">
                  <span class="doc-size-school">2.4 MB</span>
                  <span class="doc-date-school">Jan 15, 2024</span>
                </div>
                <div class="doc-status-wrapper-school">
                  <span class="status-badge-school approved">Approved</span>
                </div>
              </div>
            </div>

            <!-- Document Card 2 -->
            <div class="document-card-school">
              <input type="checkbox" class="doc-checkbox-school" />
              <div class="doc-type-badge-school">XLSX</div>
              <div class="doc-icon-wrapper-school">
                <div class="doc-icon-container-school">
                  <img src="/icons/icon-docs.png" alt="XLSX" class="doc-icon-school" />
                </div>
              </div>
              <div class="doc-info-school">
                <h3 class="doc-name-school">DTR_January_2024.xlsx</h3>
                <div class="doc-details-school">
                  <span class="doc-size-school">1.2 MB</span>
                  <span class="doc-date-school">Feb 1, 2024</span>
                </div>
                <div class="doc-status-wrapper-school">
                  <span class="status-badge-school pending">Pending Review</span>
                </div>
              </div>
            </div>

            <!-- Document Card 3 -->
            <div class="document-card-school">
              <input type="checkbox" class="doc-checkbox-school" />
              <div class="doc-type-badge-school">PDF</div>
              <div class="doc-icon-wrapper-school">
                <div class="doc-icon-container-school">
                  <img src="/icons/icon-pdf.png" alt="PDF" class="doc-icon-school" />
                </div>
              </div>
              <div class="doc-info-school">
                <h3 class="doc-name-school">Parent_Consent.pdf</h3>
                <div class="doc-details-school">
                  <span class="doc-size-school">856 KB</span>
                  <span class="doc-date-school">Jan 20, 2024</span>
                </div>
                <div class="doc-status-wrapper-school">
                  <span class="status-badge-school rejected">Rejected</span>
                </div>
              </div>
            </div>

            <!-- Document Card 4 -->
            <div class="document-card-school">
              <input type="checkbox" class="doc-checkbox-school" />
              <div class="doc-type-badge-school">DOCX</div>
              <div class="doc-icon-wrapper-school">
                <div class="doc-icon-container-school">
                  <img src="/icons/icon-word.png" alt="DOCX" class="doc-icon-school" />
                </div>
              </div>
              <div class="doc-info-school">
                <h3 class="doc-name-school">Endorsement_Letter.docx</h3>
                <div class="doc-details-school">
                  <span class="doc-size-school">1.8 MB</span>
                  <span class="doc-date-school">Jan 10, 2024</span>
                </div>
                <div class="doc-status-wrapper-school">
                  <span class="status-badge-school approved">Approved</span>
                </div>
              </div>
            </div>

            <!-- Document Card 5 -->
            <div class="document-card-school">
              <input type="checkbox" class="doc-checkbox-school" />
              <div class="doc-type-badge-school">PDF</div>
              <div class="doc-icon-wrapper-school">
                <div class="doc-icon-container-school">
                  <img src="/icons/icon-pdf.png" alt="PDF" class="doc-icon-school" />
                </div>
              </div>
              <div class="doc-info-school">
                <h3 class="doc-name-school">Application_Form_2024.pdf</h3>
                <div class="doc-details-school">
                  <span class="doc-size-school">3.1 MB</span>
                  <span class="doc-date-school">Jan 5, 2024</span>
                </div>
                <div class="doc-status-wrapper-school">
                  <span class="status-badge-school pending">Pending Review</span>
                </div>
              </div>
            </div>

            <!-- Document Card 6 -->
            <div class="document-card-school">
              <input type="checkbox" class="doc-checkbox-school" />
              <div class="doc-type-badge-school">PDF</div>
              <div class="doc-icon-wrapper-school">
                <div class="doc-icon-container-school">
                  <img src="/icons/icon-pdf.png" alt="PDF" class="doc-icon-school" />
                </div>
              </div>
              <div class="doc-info-school">
                <h3 class="doc-name-school">Internship_Completion.pdf</h3>
                <div class="doc-details-school">
                  <span class="doc-size-school">1.5 MB</span>
                  <span class="doc-date-school">Dec 20, 2023</span>
                </div>
                <div class="doc-status-wrapper-school">
                  <span class="status-badge-school approved">Approved</span>
                </div>
              </div>
            </div>
          </div>
        </main>
        
        <!-- Upload Document Modal -->
        <div v-if="showUploadDocumentModal" class="upload-modal-overlay" @click="showUploadDocumentModal = false">
          <div class="upload-modal-content" @click.stop>
            <h2 class="upload-modal-title">Upload Documents</h2>
            <p class="upload-modal-subtitle">Select files to upload or drag and drop them here</p>
            
            <div 
              class="upload-drop-zone"
              @drop="handleSchoolFileDrop"
              @dragover="handleSchoolDragOver"
              @click="triggerSchoolFileInput"
            >
              <svg class="upload-cloud-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              <p class="upload-drop-text">Click to browse or drag files here</p>
              <p class="upload-drop-subtext">Supported formats: PDF, DOCX, XLSX, JPG, PNG (Max 10MB)</p>
              <input 
                type="file" 
                id="school-file-upload-input"
                multiple
                accept=".pdf,.docx,.doc,.xlsx,.xls,.jpg,.jpeg,.png"
                style="display: none"
                @change="handleSchoolFileSelect"
              />
            </div>
            
            <!-- Selected Files List -->
            <div v-if="schoolSelectedFiles.length > 0" class="selected-files-list">
              <div v-for="(file, index) in schoolSelectedFiles" :key="index" class="selected-file-item">
                <svg class="file-icon-small" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <div class="file-info-small">
                  <span class="file-name-small">{{ file.name }}</span>
                  <span class="file-size-small">{{ formatSchoolFileSize(file.size) }}</span>
                </div>
                <button class="remove-file-btn" @click="removeSchoolFile(index)">
                  <XMarkIcon class="remove-icon" />
                </button>
              </div>
            </div>
            
            <div class="upload-modal-actions">
              <button class="cancel-upload-btn" @click="cancelSchoolUpload">Cancel</button>
              <button 
                class="confirm-upload-btn" 
                :disabled="schoolSelectedFiles.length === 0"
                @click="confirmSchoolUpload"
              >
                <svg class="upload-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                Upload Files
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="currentView === 'reports'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-reports.png" alt="Reports" class="header-icon-img" />
            <h1>Reports</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content">
          <!-- Reports Header -->
          <div class="reports-header">
            <div class="reports-title-section">
              <h2>My Reports</h2>
              <span class="reports-count">9 reports</span>
            </div>
            <button class="btn-submit-report" @click="openSubmitReportModal">+ Submit New Report</button>
          </div>

          <!-- Reports Stats Cards -->
          <div class="reports-stats">
            <div class="stat-card-report">
              <div class="stat-header-report">
                <span class="stat-label-report">PENDING REVIEW</span>
                <div class="stat-icon pending-icon">
                  <ClockIcon class="stat-icon-svg" />
                </div>
              </div>
              <div class="stat-number-report">2</div>
            </div>
            <div class="stat-card-report">
              <div class="stat-header-report">
                <span class="stat-label-report">REVISION REQUESTED</span>
                <div class="stat-icon revision-icon">
                  <ExclamationTriangleIcon class="stat-icon-svg" />
                </div>
              </div>
              <div class="stat-number-report">1</div>
            </div>
            <div class="stat-card-report">
              <div class="stat-header-report">
                <span class="stat-label-report">APPROVED REPORTS</span>
                <div class="stat-icon approved-icon">
                  <CheckCircleIcon class="stat-icon-svg" />
                </div>
              </div>
              <div class="stat-number-report">5</div>
            </div>
          </div>

          <!-- Reports Table -->
          <div class="reports-table-container">
            <table class="reports-table">
              <thead>
                <tr>
                  <th>REPORT TYPE</th>
                  <th>PERIOD</th>
                  <th>SUBMITTED</th>
                  <th>COMPANY STATUS</th>
                  <th>SCHOOL STATUS</th>
                  <th>ACTIONS</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="report-type">Weekly Report 10</td>
                  <td>Jul 8-14, 2024</td>
                  <td>Jul 15, 2024</td>
                  <td><span class="status-badge revision-requested">Revision Requested</span></td>
                  <td>—</td>
                  <td><button class="action-btn">
                    <EllipsisVerticalIcon class="action-menu-icon" />
                  </button></td>
                </tr>
                <tr>
                  <td class="report-type">Monthly Report 2</td>
                  <td>Jun 1-30, 2024</td>
                  <td>Jul 1, 2024</td>
                  <td><span class="status-badge under-review">Under Review</span></td>
                  <td>—</td>
                  <td><button class="action-btn">
                    <EllipsisVerticalIcon class="action-menu-icon" />
                  </button></td>
                </tr>
                <tr>
                  <td class="report-type">Weekly Report 9</td>
                  <td>Jul 1-7, 2024</td>
                  <td>Jul 8, 2024</td>
                  <td><span class="status-badge under-review">Under Review</span></td>
                  <td>—</td>
                  <td><button class="action-btn">
                    <EllipsisVerticalIcon class="action-menu-icon" />
                  </button></td>
                </tr>
                <tr>
                  <td class="report-type">Weekly Report 8</td>
                  <td>Jun 24-30, 2024</td>
                  <td>Jul 1, 2024</td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><button class="action-btn">
                    <EllipsisVerticalIcon class="action-menu-icon" />
                  </button></td>
                </tr>
                <tr>
                  <td class="report-type">Weekly Report 7</td>
                  <td>Jun 17-23, 2024</td>
                  <td>Jun 24, 2024</td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><button class="action-btn">
                    <EllipsisVerticalIcon class="action-menu-icon" />
                  </button></td>
                </tr>
                <tr>
                  <td class="report-type">Weekly Report 6</td>
                  <td>Jun 10-16, 2024</td>
                  <td>Jun 17, 2024</td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><button class="action-btn">
                    <EllipsisVerticalIcon class="action-menu-icon" />
                  </button></td>
                </tr>
                <tr>
                  <td class="report-type">Monthly Report 1</td>
                  <td>May 1-31, 2024</td>
                  <td>Jun 1, 2024</td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><button class="action-btn">
                    <EllipsisVerticalIcon class="action-menu-icon" />
                  </button></td>
                </tr>
                <tr>
                  <td class="report-type">Weekly Report 5</td>
                  <td>Jun 3-9, 2024</td>
                  <td>Jun 10, 2024</td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><span class="status-badge under-review">Under Review</span></td>
                  <td><button class="action-btn">
                    <EllipsisVerticalIcon class="action-menu-icon" />
                  </button></td>
                </tr>
                <tr>
                  <td class="report-type">Weekly Report 4</td>
                  <td>May 27 - Jun 2, 2024</td>
                  <td>Jun 3, 2024</td>
                  <td><span class="status-badge submitted">Submitted</span></td>
                  <td>—</td>
                  <td><button class="action-btn">
                    <EllipsisVerticalIcon class="action-menu-icon" />
                  </button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </main>
      </div>

      <!-- Student Emails: School-generated email accounts for student login -->
      <div v-else-if="currentView === 'student-emails'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-student.png" alt="Student Emails" class="header-icon-img" />
            <h1>Student Emails</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content">
          <div class="student-emails-section">
            <div class="section-card">
              <h2 class="section-title">Generate Student School Email</h2>
              <p class="section-subtitle">Enter first name, last name, and school domain. The system will auto-generate the student email using the format lastname.firstname@domain.</p>
              <div class="add-student-form">
                <div class="form-row">
                  <div class="form-group">
                    <label>First Name</label>
                    <input v-model="newStudentFirstName" type="text" placeholder="Airhon King" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>Last Name</label>
                    <input v-model="newStudentLastName" type="text" placeholder="Beron" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>School Email Domain</label>
                    <input :value="schoolDomain" type="text" class="form-input" readonly title="Auto-populated from your school account" />
                    <small class="field-note">Auto-populated from your school account</small>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Generated School Email</label>
                    <input :value="generatedStudentEmail || 'Fill first name, last name, and domain to generate'" type="text" class="form-input" readonly />
                  </div>
                  <div class="form-group">
                    <label>Student Number (optional)</label>
                    <input v-model="newStudentId" type="text" placeholder="e.g. 2026-0001" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>Course (optional)</label>
                    <input v-model="newStudentCourse" type="text" placeholder="BS Computer Science" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>Year Level (optional)</label>
                    <input v-model="newStudentYearLevel" type="text" placeholder="4th Year" class="form-input" />
                  </div>
                </div>
                <button @click="addStudentEmail" class="btn-add-email" :disabled="!generatedStudentEmail || addingStudentEmail">
                  <PlusIcon class="icon-sm" /> {{ addingStudentEmail ? 'Adding...' : 'Add Student Email' }}
                </button>
                <div class="bulk-import-block">
                  <input
                    ref="bulkStudentFileInput"
                    type="file"
                    accept=".csv,.xlsx,.xls"
                    class="hidden-file-input"
                    @change="importStudentsFromFile"
                  />
                  <button
                    type="button"
                    class="btn-import-file"
                    :disabled="importingStudentEmails"
                    @click="triggerBulkStudentFilePicker"
                  >
                    <DocumentIcon class="icon-sm" />
                    {{ importingStudentEmails ? 'Importing...' : 'Import From Excel/CSV' }}
                  </button>
                  <p class="bulk-import-hint">
                    Upload Excel/CSV with headers: First Name, Last Name, Student Number (optional), Course (optional), Year Level (optional).
                  </p>
                </div>
              </div>
            </div>
            <div class="section-card">
              <h2 class="section-title">Student Email List</h2>
              <p class="section-subtitle">Students with these emails can register and log in. Share the email with each student.</p>
              <div v-if="schoolStudentEmails.length === 0" class="empty-state">
                <EnvelopeIcon class="empty-icon" />
                <p>No student emails added yet. Add emails above to get started.</p>
              </div>
              <div v-else class="emails-list">
                <div class="emails-toolbar">
                  <div class="emails-search">
                    <MagnifyingGlassIcon class="emails-search-icon" />
                    <input
                      type="text"
                      placeholder="Search email, name, student number..."
                      class="emails-search-input"
                      v-model="studentEmailSearchQuery"
                    />
                  </div>
                  <div class="emails-count">{{ filteredSchoolStudentEmails.length }} student(s)</div>
                </div>

                <div v-if="filteredSchoolStudentEmails.length === 0" class="empty-state emails-empty">
                  <p>No matching students.</p>
                </div>
                <div v-else class="emails-table-wrap">
                  <table class="emails-table">
                    <thead>
                      <tr>
                        <th>EMAIL</th>
                        <th>NAME</th>
                        <th>STUDENT NO</th>
                        <th>COURSE</th>
                        <th>YEAR LEVEL</th>
                        <th>DEFAULT PASSWORD</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="s in filteredSchoolStudentEmails" :key="s.id">
                        <td>{{ s.email }}</td>
                        <td>{{ s.studentName || '—' }}</td>
                        <td>{{ s.studentNumber || '—' }}</td>
                        <td>{{ s.course || '—' }}</td>
                        <td>{{ s.yearLevel || '—' }}</td>
                        <td class="password-cell">
                          <span class="password-text">{{ s.defaultPassword || '-' }}</span>
                          <button
                            v-if="s.defaultPassword"
                            type="button"
                            class="btn-copy-password"
                            @click="copyToClipboard(s.defaultPassword, 'Default password')"
                          >
                            Copy
                          </button>
                        </td>
                        <td><span class="status-badge" :class="s.status">{{ s.status }}</span></td>
                        <td>
                          <button @click="removeStudentEmail(s)" class="btn-remove" title="Remove">
                            <TrashIcon class="icon-sm" />
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>

      <div v-else-if="currentView === 'student-interns'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-student.png" alt="Student Interns" class="header-icon-img" />
            <h1>Internship Applications</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        
        <!-- Student Interns Toolbar -->
        <div class="student-interns-toolbar">
          <div class="toolbar-left-students">
            <div class="search-container-students">
              <MagnifyingGlassIcon class="search-icon-students" />
              <input 
                type="text" 
                placeholder="Search student..." 
                class="search-input-students"
                v-model="studentSearchQuery"
              />
            </div>
          </div>
          <div class="toolbar-right-students">
            <button class="filter-btn-students">All Courses</button>
            <button class="filter-btn-students">All Year Level</button>
            <button class="filter-btn-students active">Pending Applications</button>
          </div>
        </div>
        
        <main class="main-content">
          <!-- Students Table -->
          <div class="students-table-container">
            <table class="students-table">
              <thead>
                <tr>
                  <th>Student Name</th>
                  <th>Student ID</th>
                  <th>Course</th>
                  <th>Year Level</th>
                  <th>Status</th>
                  <th>Eligibility</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="student in filteredStudents" 
                  :key="student.id"
                  @click="openStudentSidebar(student)"
                  class="clickable-row"
                >
                  <td class="student-name-cell">{{ student.name }}</td>
                  <td>{{ student.studentId }}</td>
                  <td>{{ student.course }}</td>
                  <td>{{ student.yearLevel }}</td>
                  <td>
                    <span 
                      class="status-badge-table"
                      :class="{
                        'status-registered': student.status === 'Registered',
                        'status-active': student.status === 'Active Intern',
                        'status-pending': student.status === 'Pending Application',
                        'status-completed': student.status === 'Completed'
                      }"
                    >
                      {{ student.status }}
                    </span>
                  </td>
                  <td>
                    <span 
                      class="eligibility-badge-table"
                      :class="{
                        'eligibility-eligible': student.eligibility === 'Eligible',
                        'eligibility-pending': student.eligibility === 'Pending',
                        'eligibility-not-eligible': student.eligibility === 'Not Eligible'
                      }"
                    >
                      {{ student.eligibility }}
                    </span>
                  </td>
                  <td>
                    <button class="action-btn-table" @click.stop="openStudentSidebar(student)">
                      <EllipsisVerticalIcon class="action-icon" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </main>
      </div>

      <div v-else-if="currentView === 'subscription'">
        <SubscriptionManager role="school" />
      </div>

      <div v-else-if="currentView === 'settings'">
        <SchoolSettings />
      </div>
    </div>

    <!-- Floating Chat Widget -->
    <FloatingChatWidget userType="school" />
  </div>
</template>

<style scoped src="../../styles/School.css">
</style>

<style scoped>
.contracts-list { display: flex; flex-direction: column; gap: 16px; }
.contract-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px 24px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.contract-doc-header { padding-bottom: 12px; margin-bottom: 12px; border-bottom: 2px solid #e5e7eb; text-align: center; }
.contract-doc-title { display: block; font-size: 0.75rem; font-weight: 700; letter-spacing: 1.5px; color: #374151; }
.contract-doc-parties { display: block; font-size: 0.85rem; color: #6b7280; margin-top: 4px; }
.contract-header-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.contract-info h3 { margin: 0; font-size: 1.125rem; font-weight: 600; color: #111827; }
.contract-type { margin: 4px 0 8px 0; font-size: 0.8rem; font-weight: 600; color: #4b5563; text-transform: uppercase; letter-spacing: 0.5px; }
.contract-purpose { margin: 0 0 12px 0; font-size: 0.95rem; color: #374151; line-height: 1.5; }
.contract-dates { margin: 0 0 12px 0; font-size: 0.875rem; color: #6b7280; }
.contract-section { margin: 0 0 12px 0; }
.contract-section strong { display: block; font-size: 0.8rem; color: #6b7280; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.contract-section p { margin: 0; font-size: 0.9rem; color: #374151; line-height: 1.5; white-space: pre-wrap; }
.contract-info p { margin: 0 0 8px 0; font-size: 0.875rem; color: #6b7280; }
.contract-actions { display: flex; gap: 8px; flex-shrink: 0; }
.contract-actions .btn-approve { padding: 8px 16px; background: #059669; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
.contract-actions .btn-reject { padding: 8px 16px; background: #dc2626; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
.status-badge { font-size: 0.75rem; padding: 4px 8px; border-radius: 6px; text-transform: capitalize; }
.status-badge.pending { background: #fef3c7; color: #92400e; }
.status-badge.active { background: #d1fae5; color: #065f46; }
.status-badge.rejected { background: #fee2e2; color: #991b1b; }
.empty-state { padding: 24px; text-align: center; color: #6b7280; }
.contracts-table-wrap { overflow-x: auto; }
.contracts-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.contracts-table th, .contracts-table td { padding: 10px 12px; border-bottom: 1px solid #eef2f7; text-align: left; font-size: 0.9rem; color: #111827; vertical-align: top; }
.contracts-table th { background: #f8fafc; font-weight: 700; color: #334155; font-size: 0.85rem; }
.contracts-table tr:last-child td { border-bottom: none; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 0.85rem; }
.actions-cell { display: flex; gap: 8px; flex-wrap: wrap; }
.table-btn { padding: 8px 10px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; font-weight: 700; cursor: pointer; font-size: 0.85rem; }
.table-btn:hover { background: #f8fafc; }
.table-btn.danger { border-color: rgba(220, 38, 38, 0.25); background: rgba(220, 38, 38, 0.08); color: #991b1b; }
.details-row td { background: #f8fafc; }
.details-panel { padding: 12px 10px; }
.details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 8px 12px; margin-bottom: 10px; color: #334155; font-size: 0.9rem; }

.student-emails-section { padding: 24px; }
.student-emails-section .section-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.student-emails-section .section-title { font-size: 1.25rem; font-weight: 600; margin: 0 0 8px 0; color: #111827; }
.student-emails-section .section-subtitle { color: #6b7280; margin: 0 0 20px 0; font-size: 0.9rem; }
.add-student-form .form-row { display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.add-student-form .form-group { flex: 1; min-width: 200px; }
.add-student-form .form-group.full-width { flex: 1 1 100%; min-width: 100%; }
.add-student-form .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 6px; color: #374151; }
.add-student-form .form-input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; }
.add-student-form .form-input[readonly] { background-color: #f9fafb; color: #6b7280; }
.add-student-form .field-note { font-size: 0.75rem; color: #6b7280; margin-top: 4px; font-style: italic; }
.course-add-row { display: flex; gap: 10px; margin-bottom: 10px; }
.course-add-row .form-input { flex: 1; }
.aligned-courses { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
.aligned-course-btn {
  padding: 8px 12px;
  border-radius: 999px;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #0f172a;
  font-weight: 600;
  cursor: pointer;
  font-size: 0.82rem;
}
.aligned-course-btn.active {
  border-color: #2563eb;
  background: #eff6ff;
  color: #1d4ed8;
}
.course-allocation-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}
.course-allocation-item {
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f8fafc;
}
.course-allocation-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.86rem;
  color: #1f2937;
  margin-bottom: 8px;
}
.course-slot-input { background: #fff; }
.btn-add-email { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; }
.btn-add-email:hover:not(:disabled) { background: #1d4ed8; }
.btn-add-email:disabled { opacity: 0.6; cursor: not-allowed; }
.hidden-file-input { display: none; }
.bulk-import-block { margin-top: 12px; display: flex; flex-direction: column; gap: 8px; }
.btn-import-file { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #0f766e; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; width: fit-content; }
.btn-import-file:hover:not(:disabled) { background: #0d5f58; }
.btn-import-file:disabled { opacity: 0.6; cursor: not-allowed; }
.bulk-import-hint { margin: 0; color: #6b7280; font-size: 0.82rem; }
.required { color: #dc2626; }
.file-chip-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.file-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
  font-size: 0.8rem;
  color: #334155;
}
.file-chip-remove {
  border: none;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}
.icon-sm { width: 18px; height: 18px; }
.empty-state { text-align: center; padding: 48px 24px; color: #6b7280; }
.empty-state .empty-icon { width: 48px; height: 48px; margin-bottom: 16px; opacity: 0.5; }
.emails-list { display: flex; flex-direction: column; gap: 12px; }
.emails-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.emails-search { position: relative; flex: 1; max-width: 520px; }
.emails-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: #6b7280; }
.emails-search-input {
  width: 100%;
  padding: 10px 12px 10px 40px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  font-size: 0.9rem;
  outline: none;
}
.emails-search-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }
.emails-count { font-size: 0.85rem; color: #6b7280; white-space: nowrap; }
.emails-empty { padding: 18px 0; }
.emails-table-wrap { overflow-x: auto; }
.emails-table { width: 100%; border-collapse: collapse; }
.emails-table th, .emails-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e5e7eb; }
.emails-table th { font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; }
.emails-table .status-badge { padding: 4px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 500; }
.emails-table .status-badge.pending { background: #dbeafe; color: #1e40af; }
.emails-table .status-badge.registered { background: #d1fae5; color: #065f46; }
.password-cell { display: flex; align-items: center; gap: 8px; }
.password-text { font-family: 'Courier New', Courier, monospace; font-size: 0.82rem; }
.btn-copy-password {
  border: 1px solid #d1d5db;
  background: #ffffff;
  color: #111827;
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 0.75rem;
  cursor: pointer;
}
.btn-copy-password:hover { background: #f9fafb; }
.btn-remove { background: none; border: none; padding: 8px; cursor: pointer; color: #ef4444; border-radius: 6px; }
.btn-remove:hover { background: #fef2f2; }
</style>






