<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import type { Component } from 'vue'
import { useRouter } from 'vue-router'
import SchoolSidebar from '../../components/SchoolSidebar.vue'
import SchoolSettings from '../../components/SchoolSettings.vue'
import OrganizationAccessManager from '../../components/OrganizationAccessManager.vue'
import SubscriptionManager from '../../components/SubscriptionManager.vue'
import FloatingChatWidget from '../../components/FloatingChatWidget.vue'
import { useAuthStore } from '@/stores/auth'
import { subscribeSchoolStudents, addSchoolStudent, updateSchoolStudent, removeSchoolStudent, type SchoolStudentRow } from '@/services/schoolStudents'
import { subscribeSchoolContracts, createContractRequest, acceptContract, rejectContract, cancelContract, type ContractRecord } from '@/services/contracts'
import { ensurePublicProfile, listPublicProfiles, subscribePublicProfiles, type PublicProfile } from '@/services/profilesPublic'
import { saveSchoolPrograms, getSchoolPrograms } from '@/services/schoolPrograms'
import { createInternship, deleteInternship, subscribeSchoolInternships, type InternshipRecord } from '@/services/internships'
import { listDocuments, uploadDocuments, type DocumentRecord } from '@/services/documents'
import { listSchoolReports, saveSchoolReport, type ReportRecord } from '@/services/reports'
import { buildProfileAvatarUrl } from '@/services/profileMedia'
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
  ClockIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
const schoolName = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return (profile?.institutionName as string) || authStore.user?.displayName || 'School'
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

const userAvatarUrl = computed(() => {
  const currentUser = authStore.user
  const profile = currentUser?.profile as Record<string, unknown> | undefined
  if (currentUser?.uid && profile?.avatarPath) {
    return buildProfileAvatarUrl(currentUser.uid, currentUser.updatedAt)
  }
  return ''
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
const contractCompanySearch = ref('')

const sidebarActiveItem = computed(() => {
  // Map sub-views back to their sidebar parent for highlighting
  if (currentView.value === 'contracts-select') return 'contracts'
  return currentView.value
})

const router = useRouter()
const handleMenuClick = (item: string) => {
  if (item === 'profile') {
    router.push('/profile')
    return
  }
  if (item === 'contracts') {
    resetSchoolContractForm()
    contractCompanySearch.value = ''
    currentView.value = 'contracts-select'
    return
  }
  currentView.value = item
}

function goBackToContractCompanies() {
  currentView.value = 'contracts-select'
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
const documentsSearchQuery = ref('')
const documentsSortBy = ref<'recent' | 'name' | 'size' | 'status'>('recent')
const schoolDocuments = ref<DocumentRecord[]>([])
const uploadingDocuments = ref(false)

const filteredSchoolDocuments = computed(() => {
  const query = documentsSearchQuery.value.trim().toLowerCase()
  const filtered = schoolDocuments.value.filter((doc) => {
    if (!query) return true
    return [
      doc.fileName,
      doc.category,
      doc.fileType || '',
      doc.status,
    ].some((value) => value.toLowerCase().includes(query))
  })

  return [...filtered].sort((a, b) => {
    if (documentsSortBy.value === 'name') {
      return a.fileName.localeCompare(b.fileName)
    }
    if (documentsSortBy.value === 'size') {
      return (b.fileSize || 0) - (a.fileSize || 0)
    }
    if (documentsSortBy.value === 'status') {
      return a.status.localeCompare(b.status)
    }
    return new Date(b.createdAt || 0).getTime() - new Date(a.createdAt || 0).getTime()
  })
})

const schoolDocumentStats = computed(() => ({
  total: schoolDocuments.value.length,
  approved: schoolDocuments.value.filter((doc) => doc.status === 'approved').length,
  pending: schoolDocuments.value.filter((doc) => doc.status === 'pending').length,
}))

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

function formatDocumentDate(value?: string) {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? '—'
    : date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

function documentExtension(doc: DocumentRecord) {
  const fromName = doc.fileName.split('.').pop()?.toUpperCase()
  if (fromName) return fromName
  return doc.fileType?.split('/').pop()?.toUpperCase() || 'FILE'
}

function documentStatusClass(status: string) {
  return status.toLowerCase()
}

async function loadSchoolDocuments() {
  if (authStore.user?.role !== 'school' || !authStore.user?.uid) {
    schoolDocuments.value = []
    return
  }

  try {
    schoolDocuments.value = await listDocuments()
  } catch (error) {
    schoolDocuments.value = []
    console.error('Failed to load documents:', error)
  }
}

function cancelSchoolUpload() {
  showUploadDocumentModal.value = false
  schoolSelectedFiles.value = []
}

async function confirmSchoolUpload() {
  if (schoolSelectedFiles.value.length === 0 || uploadingDocuments.value) return

  uploadingDocuments.value = true
  try {
    const uploaded = await uploadDocuments({
      files: schoolSelectedFiles.value,
      category: 'school',
      status: 'pending',
    })

    schoolDocuments.value = [...uploaded, ...schoolDocuments.value]
    showUploadDocumentModal.value = false

    await Swal.fire({
      icon: 'success',
      iconColor: '#16a34a',
      title: 'Documents Uploaded Successfully!',
      text: `${uploaded.length} file(s) have been uploaded and added to your document list.`,
      confirmButtonText: 'Continue',
      confirmButtonColor: '#3b82f6',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      iconColor: '#ef4444',
      title: 'Upload failed',
      text: error instanceof Error ? error.message : 'Could not upload your documents right now.',
      confirmButtonColor: '#3b82f6',
    })
  } finally {
    schoolSelectedFiles.value = []
    uploadingDocuments.value = false
  }
}

const selectedStudent = ref<any>(null)

// Student search
const studentSearchQuery = ref('')

// Approval modal state
const showApprovalModal = ref(false)
const approvalRemarks = ref('')

// Company sidebar state
const showCompanySidebar = ref(false)
const selectedCompany = ref<any>(null)
const activeCompanyTab = ref('general')

type ContractTypeOption = {
  value: string
  title: string
  badge: string
  description: string
  icon: Component
}

const contractTypeOptions: ContractTypeOption[] = [
  {
    value: 'Memorandum of Agreement (MOA) for OJT Internship',
    title: 'OJT Internship Contract',
    badge: 'Standard',
    description: 'Standard on-the-job training contract for student interns.',
    icon: ClipboardDocumentListIcon,
  },
  {
    value: 'Pre-OJT Agreement',
    title: 'Pre-OJT Agreement',
    badge: 'Preparation',
    description: 'Agreement before the start of the OJT program.',
    icon: CalendarIcon,
  },
  {
    value: 'OJT Completion Contract',
    title: 'OJT Completion Contract',
    badge: 'Finalization',
    description: 'Contract for completing OJT requirements.',
    icon: CheckCircleIcon,
  },
  {
    value: 'Extended OJT Contract',
    title: 'Extended OJT Contract',
    badge: 'Extension',
    description: 'Extension for ongoing OJT internship period.',
    icon: ClockIcon,
  },
  {
    value: 'Contract Amendment / Addendum',
    title: 'Contract Amendment',
    badge: 'Amendment',
    description: 'Propose changes or add an addendum to an existing OJT contract.',
    icon: PencilSquareIcon,
  },
  {
    value: 'Partnership Agreement',
    title: 'Partnership Agreement',
    badge: 'Institution',
    description: 'Company–university partnership for the OJT program.',
    icon: HandThumbUpIcon,
  },
]

const showContractTypeModal = ref(false)
const selectedContractType = ref<string>('')

// Submit Report Modal state
const showSubmitReportModal = ref(false)
const schoolReports = ref<ReportRecord[]>([])
const reportFormData = ref({
  reportType: '',
  studentName: '',
  internshipPeriodStart: '',
  internshipPeriodEnd: '',
  companyName: ''
})

const reportStats = computed(() => ({
  pending: schoolReports.value.filter((report) => report.companyStatus === 'pending').length,
  revisionRequested: schoolReports.value.filter((report) => report.companyStatus === 'revision_requested').length,
  approved: schoolReports.value.filter((report) => report.companyStatus === 'approved').length,
}))

function formatReportTypeLabel(value: string) {
  if (value === 'weekly') return 'Weekly Report'
  if (value === 'monthly') return 'Monthly Report'
  if (value === 'final') return 'Final Report'
  return value || 'Report'
}

function formatReportPeriod(report: Pick<ReportRecord, 'internshipPeriodStart' | 'internshipPeriodEnd'>) {
  const start = formatDocumentDate(report.internshipPeriodStart)
  const end = formatDocumentDate(report.internshipPeriodEnd)
  return `${start} - ${end}`
}

async function loadSchoolReports() {
  if (!authStore.user?.uid || authStore.user.role !== 'school') {
    schoolReports.value = []
    return
  }

  schoolReports.value = await listSchoolReports(authStore.user.uid)
}

function openSubmitReportModal() {
  const latestStudent = studentsData.value[0]
  const latestCompany = schoolContracts.value.find((contract) => contract.status === 'active')?.companyName
    || companiesForContract.value[0]?.orgName
    || companiesForContract.value[0]?.displayName
    || ''
  reportFormData.value = {
    reportType: '',
    studentName: latestStudent?.name || '',
    internshipPeriodStart: '',
    internshipPeriodEnd: '',
    companyName: latestCompany,
  }
  currentView.value = 'submit-report'
}

function closeSubmitReportModal() {
  currentView.value = 'reports'
  reportFormData.value = {
    reportType: '',
    studentName: '',
    internshipPeriodStart: '',
    internshipPeriodEnd: '',
    companyName: ''
  }
}

async function persistReport(status: 'draft' | 'pending') {
  const uid = authStore.user?.uid
  if (!uid) return

  if (!reportFormData.value.reportType || !reportFormData.value.studentName || !reportFormData.value.internshipPeriodStart || !reportFormData.value.internshipPeriodEnd || !reportFormData.value.companyName) {
    await Swal.fire({
      icon: 'warning',
      title: 'Complete the report form',
      text: 'Please fill in the report type, student, period, and company before saving.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  const created = await saveSchoolReport({
    schoolId: uid,
    reportType: reportFormData.value.reportType,
    studentName: reportFormData.value.studentName,
    internshipPeriodStart: reportFormData.value.internshipPeriodStart,
    internshipPeriodEnd: reportFormData.value.internshipPeriodEnd,
    companyName: reportFormData.value.companyName,
    status,
  })

  schoolReports.value = [created, ...schoolReports.value]
  await Swal.fire({
    icon: 'success',
    title: status === 'draft' ? 'Draft saved' : 'Report submitted',
    text: status === 'draft' ? 'Your report draft is now saved in the reports table.' : 'Your report has been added and is now pending review.',
    confirmButtonColor: '#2563eb',
  })
  closeSubmitReportModal()
}

async function saveAsDraft() {
  await persistReport('draft')
}

async function nextStep() {
  await persistReport('pending')
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

function openReviewModal(internshipId: string) {
  currentReviewInternship.value = internshipId
  // Load default values from the school's program settings
  const programHours: Record<string, number> = {}
  Object.keys(defaultProgramHours.value).forEach(program => {
    // Use short form for the review modal
    const shortForm = program.match(/\(([^)]+)\)$/)?.[1] || program
    const hours = defaultProgramHours.value[program]
    if (typeof hours === 'number') {
      programHours[shortForm] = hours
    }
  })
  
  reviewFormData.value = {
    programHours,
    eligiblePrograms: Object.keys(programHours).slice(0, 2), // Default to first 2 programs
    minimumGPA: '2.5',
    yearLevels: ['3rd Year', '4th Year'],
    requiredDocuments: ['Resume/CV', 'Transcript of Records'],
    additionalRequirements: '',
    approvalNotes: ''
  }
  showInternshipReviewModal.value = true
}

function closeReviewModal() {
  showInternshipReviewModal.value = false
  currentReviewInternship.value = null
}

async function approveInternshipWithRequirements() {
  // Here you would save the requirements and approve the internship
  console.log('Approving internship with requirements:', reviewFormData.value)
  
  await Swal.fire({
    icon: 'success',
    title: 'Internship Approved',
    text: 'The internship has been approved with your specified requirements.',
    confirmButtonColor: '#2563eb'
  })
  
  closeReviewModal()
}

async function quickApprove(internshipId: string) {
  const result = await Swal.fire({
    icon: 'question',
    title: 'Quick Approve',
    text: 'This will approve the internship with default requirements. Continue?',
    showCancelButton: true,
    confirmButtonText: 'Yes, Approve',
    confirmButtonColor: '#059669'
  })
  
  if (result.isConfirmed) {
    await Swal.fire({
      icon: 'success',
      title: 'Approved',
      text: 'Internship approved with default requirements.',
      confirmButtonColor: '#2563eb'
    })
  }
}

async function saveProgramHours() {
  savingProgramHours.value = true
  
  try {
    const uid = authStore.user?.uid
    
    if (!uid) {
      throw new Error('User not authenticated - no UID available')
    }
    
    if (!schoolName.value) {
      throw new Error('School name is required but not available')
    }
    
    if (!defaultProgramHours.value || Object.keys(defaultProgramHours.value).length === 0) {
      throw new Error('No programs to save')
    }
    
    // Save to the backend
    await saveSchoolPrograms(uid, schoolName.value, defaultProgramHours.value)
    
    await Swal.fire({
      icon: 'success',
      title: 'Program Hours Saved',
      text: 'Default program hours have been updated successfully.',
      confirmButtonColor: '#2563eb'
    })
  } catch (error) {
    console.error('Error saving program hours:', error)
    
    await Swal.fire({
      icon: 'error',
      title: 'Save Failed',
      text: `Could not save program hours. Error: ${error instanceof Error ? error.message : 'Unknown error'}`,
      confirmButtonColor: '#2563eb'
    })
  } finally {
    savingProgramHours.value = false
  }
}

function openAddProgramModal() {
  newProgramName.value = ''
  newProgramHours.value = 400
  showAddProgramModal.value = true
}

function closeAddProgramModal() {
  showAddProgramModal.value = false
  newProgramName.value = ''
  newProgramHours.value = 400
}

async function addNewProgram() {
  const programName = newProgramName.value.trim()
  
  if (!programName) {
    await Swal.fire({
      icon: 'warning',
      title: 'Program Name Required',
      text: 'Please enter a program name.',
      confirmButtonColor: '#2563eb'
    })
    return
  }
  
  if (defaultProgramHours.value[programName]) {
    await Swal.fire({
      icon: 'warning',
      title: 'Program Already Exists',
      text: 'This program already exists in the list.',
      confirmButtonColor: '#2563eb'
    })
    return
  }
  
  if (newProgramHours.value < 1) {
    await Swal.fire({
      icon: 'warning',
      title: 'Invalid Hours',
      text: 'Program hours must be at least 1.',
      confirmButtonColor: '#2563eb'
    })
    return
  }
  
  // Add the new program
  defaultProgramHours.value[programName] = newProgramHours.value
  
  // Automatically save after adding
  try {
    const uid = authStore.user?.uid
    if (uid) {
      await saveSchoolPrograms(uid, schoolName.value, defaultProgramHours.value)
    }
    
    await Swal.fire({
      icon: 'success',
      title: 'Program Added',
      text: `${programName} has been added with ${newProgramHours.value} hours and saved.`,
      confirmButtonColor: '#2563eb'
    })
  } catch (error) {
    console.error('Error saving after adding program:', error)
    await Swal.fire({
      icon: 'warning',
      title: 'Program Added',
      text: `${programName} has been added but not saved. Please click "Save Program Hours" to persist changes.`,
      confirmButtonColor: '#2563eb'
    })
  }
  
  closeAddProgramModal()
}

async function removeProgram(programName: string) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Remove Program?',
    text: `Are you sure you want to remove "${programName}" from the list?`,
    showCancelButton: true,
    confirmButtonText: 'Yes, Remove',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Cancel'
  })
  
  if (result.isConfirmed) {
    delete defaultProgramHours.value[programName]
    
    // Automatically save after removing
    try {
      const uid = authStore.user?.uid
      if (uid) {
        await saveSchoolPrograms(uid, schoolName.value, defaultProgramHours.value)
      }
      
      await Swal.fire({
        icon: 'success',
        title: 'Program Removed',
        text: `${programName} has been removed and changes saved.`,
        confirmButtonColor: '#2563eb'
      })
    } catch (error) {
      console.error('Error saving after removing program:', error)
      await Swal.fire({
        icon: 'warning',
        title: 'Program Removed',
        text: `${programName} has been removed but changes not saved. Please click "Save Program Hours".`,
        confirmButtonColor: '#2563eb'
      })
    }
  }
}

async function loadExistingPrograms() {
  const uid = authStore.user?.uid
  if (!uid) {
    console.log('No user ID available for loading programs')
    return
  }
  
  try {
    console.log('Loading existing programs for user:', uid)
    const schoolPrograms = await getSchoolPrograms(uid)
    if (schoolPrograms && schoolPrograms.programs) {
      console.log('Loaded programs:', schoolPrograms.programs)
      defaultProgramHours.value = { ...schoolPrograms.programs }
    } else {
      console.log('No existing programs found, using defaults')
      // Keep the default programs if no saved programs exist
    }
  } catch (error) {
    console.error('Error loading existing programs:', error)
  }
}

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
const schoolStudentEmails = ref<SchoolStudentRow[]>([])
const studentsData = computed(() =>
  schoolStudentEmails.value.map((student) => ({
    id: student.id,
    name: student.studentName || student.email,
    studentId: student.studentNumber || '—',
    course: student.course || '—',
    yearLevel: student.yearLevel || '—',
    status: student.status || 'Registered',
    eligibility: 'Eligible',
    company: '—',
    position: 'Registered Student',
    applicationDate: '—',
    documents: [],
    timeline: [],
    email: student.email,
  }))
)

function formatRelativeTime(value?: unknown) {
  if (!value) return 'Recently'
  const date = new Date(String(value))
  if (Number.isNaN(date.getTime())) return 'Recently'
  const diffMs = Date.now() - date.getTime()
  const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
  if (diffHours < 1) return 'Just now'
  if (diffHours < 24) return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`
  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 30) return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const schoolCourseList = computed(() => {
  const raw = (authStore.user?.profile as Record<string, unknown> | undefined)?.courses
  return Array.isArray(raw) ? raw.map((item) => String(item)) : []
})

const schoolDashboardStats = computed(() => {
  const totalStudents = schoolStudentEmails.value.length
  const registeredStudents = schoolStudentEmails.value.filter((student) => (student.status || 'Registered') === 'Registered').length
  const activeContracts = schoolContracts.value.filter((contract) => contract.status === 'active').length
  const pendingContracts = schoolContracts.value.filter((contract) => contract.status === 'pending').length
  const rejectedContracts = schoolContracts.value.filter((contract) => contract.status === 'rejected').length
  const currentPartner = schoolContracts.value.find((contract) => contract.status === 'active') || schoolContracts.value[0] || null
  const pendingDocuments = schoolDocuments.value.filter((doc) => doc.status === 'pending').length

  const pendingTasks = [
    ...(pendingContracts > 0 ? [{ task: `${pendingContracts} pending contract request${pendingContracts === 1 ? '' : 's'}`, type: 'contract' }] : []),
    ...(pendingDocuments > 0 ? [{ task: `${pendingDocuments} document${pendingDocuments === 1 ? '' : 's'} waiting for review`, type: 'document' }] : []),
    ...(totalStudents === 0 ? [{ task: 'Add your first registered student', type: 'student' }] : []),
  ]

  return {
    students: {
      total: totalStudents,
      registered: registeredStudents,
      pending: Math.max(totalStudents - registeredStudents, 0),
    },
    contracts: {
      active: activeContracts,
      pending: pendingContracts,
      rejected: rejectedContracts,
    },
    currentPartner: {
      company: currentPartner?.companyName || 'No active company yet',
      position: currentPartner?.subject || currentPartner?.contractType || 'Start a company partnership to see it here',
      status: currentPartner ? `${currentPartner.status} contract` : 'Waiting for first partnership',
    },
    pendingTasks,
  }
})

const recommendedInternships = computed(() => {
  const schoolCourses = schoolCourseList.value.map((course) => course.toLowerCase())
  return companiesForContract.value
    .slice()
    .sort((a, b) => {
      const aCourses = (a.courses || []).map((course) => course.toLowerCase())
      const bCourses = (b.courses || []).map((course) => course.toLowerCase())
      const aMatches = aCourses.filter((course) => schoolCourses.includes(course)).length
      const bMatches = bCourses.filter((course) => schoolCourses.includes(course)).length
      return bMatches - aMatches
    })
    .slice(0, 3)
    .map((company, index) => {
      const courseMatches = (company.courses || []).filter((course) => schoolCourses.includes(course.toLowerCase())).length
      const denominator = Math.max((company.courses || []).length, schoolCourses.length, 1)
      const match = Math.min(99, Math.max(70, Math.round((courseMatches / denominator) * 100) || (90 - index * 5)))
      return {
        id: company.uid,
        title: 'Potential Contract Partner',
        company: company.orgName || company.displayName || company.email || 'Company',
        location: company.email || 'Company profile available',
        match,
        skills: (company.courses || []).slice(0, 4),
      }
    })
})

const recentActivities = computed(() => {
  const contractActivities = schoolContracts.value.slice(0, 3).map((contract) => ({
    id: `contract-${contract.id}`,
    text: `${contract.companyName} contract is ${contract.status}`,
    time: formatRelativeTime(contract.updatedAt || contract.createdAt),
    status: contract.status === 'active' ? 'success' : contract.status === 'rejected' ? 'warning' : 'info',
    sortAt: new Date(String(contract.updatedAt || contract.createdAt || 0)).getTime(),
  }))

  const documentActivities = schoolDocuments.value.slice(0, 3).map((document) => ({
    id: `document-${document.id}`,
    text: `${document.fileName} uploaded to school documents`,
    time: formatRelativeTime(document.createdAt),
    status: document.status === 'approved' ? 'success' : 'info',
    sortAt: new Date(String(document.createdAt || 0)).getTime(),
  }))

  return [...contractActivities, ...documentActivities]
    .sort((a, b) => b.sortAt - a.sortAt)
    .slice(0, 4)
    .map(({ sortAt, ...item }) => item)
})

const upcomingDeadlines = computed(() => {
  const items = []
  if (schoolDashboardStats.value.contracts.pending > 0) {
    items.push({
      id: 'pending-contracts',
      title: 'Review pending contracts',
      date: 'This week',
      daysRemaining: 3,
      type: 'warning',
    })
  }
  if (schoolDocumentStats.value.pending > 0) {
    items.push({
      id: 'pending-documents',
      title: 'Check pending documents',
      date: 'This week',
      daysRemaining: 5,
      type: 'info',
    })
  }
  if (schoolStudentEmails.value.length === 0) {
    items.push({
      id: 'student-roster',
      title: 'Add student roster',
      date: 'As soon as possible',
      daysRemaining: 7,
      type: 'warning',
    })
  }
  return items
})
const studentEmailSearchQuery = ref('')
const filteredStudents = computed(() => {
  const query = studentSearchQuery.value.trim().toLowerCase()
  if (!query) return studentsData.value
  return studentsData.value.filter((student) =>
    student.name.toLowerCase().includes(query) ||
    student.studentId.toLowerCase().includes(query) ||
    student.course.toLowerCase().includes(query) ||
    student.email.toLowerCase().includes(query)
  )
})
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
const editingStudentId = ref<string | null>(null)
const editingStudentForm = ref({
  studentName: '',
  studentNumber: '',
  course: '',
  yearLevel: '',
  status: 'Registered',
})
const savingStudentEdit = ref(false)
const yearLevelOptions = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year']

// Internship review modal state
const showInternshipReviewModal = ref(false)
const currentReviewInternship = ref<string | null>(null)
const reviewFormData = ref({
  programHours: {} as Record<string, number>,
  eligiblePrograms: [] as string[],
  minimumGPA: '',
  yearLevels: [] as string[],
  requiredDocuments: [] as string[],
  additionalRequirements: '',
  approvalNotes: ''
})

// Default program hours management
const defaultProgramHours = ref<Record<string, number>>({
  'BS Information Technology (BSIT)': 400,
  'BS Computer Science (BSCS)': 400,
  'BS Electronics and Communications Engineering (BSECE)': 300,
  'BS Education (BSED)': 200
})

// Add new program state
const showAddProgramModal = ref(false)
const newProgramName = ref('')
const newProgramHours = ref(400)
const savingProgramHours = ref(false)
const schoolHostedInternships = ref<InternshipRecord[]>([])
const creatingSchoolPlacement = ref(false)
const schoolPlacementForm = ref({
  title: '',
  location: 'Cavite',
  type: 'On-site',
  duration: '8 weeks',
  slotsAvailable: 1,
  eligibleCoursesText: '',
  requirementsText: '',
  description: '',
  status: 'active' as 'active' | 'draft',
})
let unsubSchoolInternships: (() => void) | null = null

const schoolPlacementStats = computed(() => ({
  total: schoolHostedInternships.value.length,
  active: schoolHostedInternships.value.filter((item) => item.status === 'active').length,
  draft: schoolHostedInternships.value.filter((item) => item.status === 'draft').length,
}))

function resetSchoolPlacementForm() {
  schoolPlacementForm.value = {
    title: '',
    location: 'Cavite',
    type: 'On-site',
    duration: '8 weeks',
    slotsAvailable: 1,
    eligibleCoursesText: '',
    requirementsText: '',
    description: '',
    status: 'active',
  }
}

async function createSchoolPlacement() {
  const uid = authStore.user?.uid
  if (!uid || authStore.user?.role !== 'school') return
  if (!schoolPlacementForm.value.title.trim()) {
    await Swal.fire({
      icon: 'warning',
      title: 'Missing title',
      text: 'Please add a placement title before saving.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  creatingSchoolPlacement.value = true
  try {
    const eligibleCourses = schoolPlacementForm.value.eligibleCoursesText
      .split(/[,\n]/)
      .map((value) => value.trim())
      .filter(Boolean)
    const requirements = schoolPlacementForm.value.requirementsText
      .split(/[,\n]/)
      .map((value) => value.trim())
      .filter(Boolean)

    await createInternship({
      title: schoolPlacementForm.value.title.trim(),
      description: schoolPlacementForm.value.description.trim() || undefined,
      companyId: '',
      companyName: schoolName.value,
      hostType: 'school',
      hostId: uid,
      hostName: schoolName.value,
      schoolId: uid,
      schoolName: schoolName.value,
      location: schoolPlacementForm.value.location.trim() || 'Cavite',
      type: schoolPlacementForm.value.type.trim() || 'On-site',
      duration: schoolPlacementForm.value.duration.trim() || 'TBD',
      slotsAvailable: Math.max(1, Number(schoolPlacementForm.value.slotsAvailable) || 1),
      eligibleCourses: eligibleCourses.length ? eligibleCourses : undefined,
      requirements: requirements.length ? requirements : undefined,
      status: schoolPlacementForm.value.status,
      allowance: undefined,
      contactInfo: authStore.user?.email || undefined,
    })

    resetSchoolPlacementForm()
    await Swal.fire({
      icon: 'success',
      title: 'Placement posted',
      text: 'Students can now find this school-hosted internship placement.',
      confirmButtonColor: '#2563eb',
    })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Could not save placement',
      text: error instanceof Error ? error.message : 'Please try again.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    creatingSchoolPlacement.value = false
  }
}

async function removeSchoolPlacement(internship: InternshipRecord) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Delete placement?',
    text: `Remove "${internship.title}" from the student internship list?`,
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Keep it',
  })

  if (!result.isConfirmed) return

  try {
    await deleteInternship(internship.id)
    await Swal.fire({
      icon: 'success',
      title: 'Placement removed',
      text: 'The school-hosted placement has been deleted.',
      confirmButtonColor: '#2563eb',
    })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Could not delete placement',
      text: error instanceof Error ? error.message : 'Please try again.',
      confirmButtonColor: '#2563eb',
    })
  }
}

// Watch for authentication changes to load programs
watch(
  () => [authStore.initializing, authStore.user?.uid] as const,
  () => {
    if (!authStore.initializing && authStore.user?.uid) {
      loadExistingPrograms()
    }
  },
  { immediate: true }
)
const availablePrograms = ref([
  'BS Computer Science',
  'BS Information Technology',
  'BS Computer Engineering',
  'BS Electronics Engineering',
  'BS Electrical Engineering',
  'BS Mechanical Engineering',
  'BS Civil Engineering',
  'BS Industrial Engineering',
  'BS Business Administration',
  'BS Accountancy',
  'BS Marketing',
  'BS Psychology',
  'BS Education',
  'BS Nursing',
  'BS Architecture',
  'BS Mathematics',
  'BS Physics',
  'BS Chemistry',
  'BS Biology',
  'Other'
])

function normalizeEmailDomain(value: string): string {
  const trimmed = value.trim().replace(/\s+/g, '')
  if (!trimmed) return ''
  return trimmed.startsWith('@') ? trimmed : `@${trimmed}`
}

// Initialize the domain field with the school's domain (but don't override manual edits)
watch(
  schoolDomain,
  (newDomain) => {
    if (!newStudentEmailDomain.value) newStudentEmailDomain.value = newDomain
  },
  { immediate: true }
)
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
  const domain = normalizeEmailDomain(newStudentEmailDomain.value)
  
  if (!first || !last || !domain || domain === '@') return ''
  return `${last}.${first}${domain}`
})

const generatedStudentFullName = computed(() => {
  const first = newStudentFirstName.value.trim()
  const last = newStudentLastName.value.trim()
  if (!first || !last) return ''
  return `${first} ${last}`
})

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

      await addSchoolStudent(uid, email, {
        studentName,
        studentNumber: studentNumberIdx >= 0 ? (row[studentNumberIdx] || '').trim() || undefined : undefined,
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

function companyDisplayLabel(company: PublicProfile): string {
  return String(company.orgName || company.displayName || company.email || company.uid || 'Company')
}

function companyInitials(company: PublicProfile): string {
  const base = String(company.orgName || company.displayName || company.email || company.uid || '').trim()
  if (!base) return 'CO'
  const parts = base
    .replace(/[^a-zA-Z0-9\s]/g, ' ')
    .split(/\s+/)
    .filter(Boolean)
  const first = parts[0] || ''
  const second = parts[1] || ''
  if (!second) return first.slice(0, 2).toUpperCase() || 'CO'
  return ((first[0] || '') + (second[0] || '')).toUpperCase() || 'CO'
}

function hashToHue(input: string): number {
  let hash = 0
  for (let i = 0; i < input.length; i++) {
    hash = (hash * 31 + input.charCodeAt(i)) | 0
  }
  return Math.abs(hash) % 360
}

function companyLogoStyle(company: PublicProfile): Record<string, string> {
  // Use curated brand-aligned palettes (keeps the look consistent with the system UI).
  const palettes = [
    { a: '#2563eb', b: '#1e40af', c: '#93c5fd' }, // blue
    { a: '#1d4ed8', b: '#1e3a8a', c: '#60a5fa' }, // deep blue
    { a: '#4f46e5', b: '#312e81', c: '#a5b4fc' }, // indigo
    { a: '#0ea5e9', b: '#0369a1', c: '#67e8f9' }, // sky
    { a: '#10b981', b: '#047857', c: '#6ee7b7' }, // emerald
  ]

  const seed = String(company.uid || company.email || company.orgName || company.displayName || 'company')
  const idx = hashToHue(seed) % palettes.length
  const p = palettes[idx]!
  return {
    '--logo-a': p.a,
    '--logo-b': p.b,
    '--logo-c': p.c,
    background: `linear-gradient(135deg, ${p.a}, ${p.b})`,
  }
}

const filteredContractCompanies = computed(() => {
  const query = contractCompanySearch.value.trim().toLowerCase()
  const list = companiesForContract.value.slice()

  const filtered = query
    ? list.filter((c) => {
        const label = companyDisplayLabel(c).toLowerCase()
        const email = (c.email || '').toLowerCase()
        return label.includes(query) || email.includes(query)
      })
    : list

  return filtered.sort((a, b) => companyDisplayLabel(a).localeCompare(companyDisplayLabel(b)))
})

function applySelectedCompanyForContract(company: PublicProfile) {
  schoolContractForm.value.companyId = company.uid
  const label = companyDisplayLabel(company)
  schoolContractForm.value.companyName = label
  schoolContractForm.value.companyContactName = label
  schoolContractForm.value.companyContactEmail = company.email || ''
}

function onSchoolContractCompanySelect(e: Event) {
  const target = e.target as HTMLSelectElement
  const id = target.value
  const company = companiesForContract.value.find((c) => c.uid === id)
  if (!company) return
  applySelectedCompanyForContract(company)
  selectedContractType.value = schoolContractForm.value.contractType || contractTypeOptions[0]!.value
  showContractTypeModal.value = true
}

function selectContractCompany(company: PublicProfile) {
  applySelectedCompanyForContract(company)
  currentView.value = 'contracts'
  selectedContractType.value = schoolContractForm.value.contractType || contractTypeOptions[0]!.value
  showContractTypeModal.value = true
}

function selectContractCompanyById(id: string) {
  const company = companiesForContract.value.find((item) => item.uid === id)
  if (!company) return
  selectContractCompany(company)
}

function openContractTypeModal() {
  selectedContractType.value = schoolContractForm.value.contractType || contractTypeOptions[0]!.value
  showContractTypeModal.value = true
}

function cancelContractTypeModal() {
  showContractTypeModal.value = false
}

function confirmContractTypeModal() {
  const chosen = selectedContractType.value || contractTypeOptions[0]!.value
  schoolContractForm.value.contractType = chosen
  showContractTypeModal.value = false
}

function changeCompanyFromContractTypeModal() {
  resetSchoolContractForm()
  currentView.value = 'contracts-select'
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
  showContractTypeModal.value = false
  selectedContractType.value = ''
}

const selectedCompanyHasPendingContract = computed(() => {
  const companyId = schoolContractForm.value.companyId
  if (!companyId) return false
  return schoolContracts.value.some((c) => c.companyId === companyId && (c.status || 'pending') === 'pending')
})

const isContractAmendment = computed(() => {
  return schoolContractForm.value.contractType === 'Contract Amendment / Addendum'
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

function contractRequestedByLabel(role?: 'school' | 'company') {
  return role === 'company' ? 'Company' : 'School'
}

function canSchoolRespondToContract(contract: ContractRecord) {
  return (contract.status || 'pending') === 'pending' && contract.requestedByRole === 'company'
}

function canSchoolCancelContract(contract: ContractRecord) {
  const status = contract.status || 'pending'
  if (status === 'active') return true
  return status === 'pending' && contract.requestedByRole !== 'company'
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

    if (!isContractAmendment.value && alignedContractCourses.value.length === 0) {
      await Swal.fire({
        icon: 'warning',
        title: 'No Aligned Courses',
        text: 'There are no aligned courses between your school and this company. Add your school courses in Settings and ask the company to add the courses they accept.',
        confirmButtonColor: '#2563eb',
      })
      return
    }

    if (!isContractAmendment.value && courseAllocations.length === 0) {
      await Swal.fire({
        icon: 'warning',
        title: 'Missing Course Slots',
        text: 'Please select at least one aligned course and set the number of slots.',
        confirmButtonColor: '#2563eb',
      })
      return
    }

    const createdContract = await createContractRequest({
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
      files: contractFiles.value,
      attachments: contractFiles.value.map((file) => ({
        name: file.name,
        size: file.size,
        type: file.type || 'application/octet-stream',
      })),
    })

    schoolContracts.value = [createdContract, ...schoolContracts.value.filter((item) => item.id !== createdContract.id)]

    await Swal.fire({
      icon: 'success',
      title: 'Contract Request Sent',
      text: 'The company can now review your contract request.',
      confirmButtonColor: '#2563eb',
    })
    resetSchoolContractForm()
    currentView.value = 'contracts'
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

async function handleAcceptSchoolContract(contract: ContractRecord) {
  const result = await Swal.fire({
    icon: 'question',
    title: 'Accept contract request?',
    text: `Accept contract request from ${contract.companyName}?`,
    showCancelButton: true,
    confirmButtonText: 'Accept',
    confirmButtonColor: '#059669',
    cancelButtonText: 'Keep pending',
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
  } catch (e) {
    await Swal.fire({
      icon: 'error',
      title: 'Failed',
      text: e instanceof Error ? e.message : 'Could not accept the contract.',
      confirmButtonColor: '#2563eb',
    })
  }
}

async function handleRejectSchoolContract(contract: ContractRecord) {
  const result = await Swal.fire({
    title: 'Reject contract request?',
    text: `Reject contract request from ${contract.companyName}?`,
    icon: 'warning',
    input: 'text',
    inputLabel: 'Reason (optional)',
    inputPlaceholder: 'Enter rejection reason',
    showCancelButton: true,
    confirmButtonText: 'Reject',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Keep pending',
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
  } catch (e) {
    await Swal.fire({
      icon: 'error',
      title: 'Failed',
      text: e instanceof Error ? e.message : 'Could not reject the contract.',
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
    if (unsubSchoolInternships) {
      unsubSchoolInternships()
      unsubSchoolInternships = null
    }

    if (!uid || role !== 'school') {
      schoolStudentEmails.value = []
      schoolContracts.value = []
      companiesForContract.value = []
      schoolDocuments.value = []
      schoolReports.value = []
      schoolHostedInternships.value = []
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

    unsubSchoolInternships = subscribeSchoolInternships(uid, (items) => {
      schoolHostedInternships.value = items
    })

    await loadSchoolDocuments()
    await loadSchoolReports()
  },
  { immediate: true }
)

onUnmounted(() => {
  if (unsubSchoolStudents) unsubSchoolStudents()
  if (unsubSchoolContracts) unsubSchoolContracts()
  if (unsubCompaniesPublic) unsubCompaniesPublic()
  if (unsubSchoolInternships) unsubSchoolInternships()
})

async function addStudentEmail() {
  const uid = authStore.user?.uid
  const email = generatedStudentEmail.value.trim()
  if (!uid || !email) return
  addingStudentEmail.value = true
  try {
    const createdStudent = await addSchoolStudent(uid, email, {
      studentName: generatedStudentFullName.value || undefined,
      studentNumber: newStudentId.value.trim() || undefined,
      course: newStudentCourse.value.trim() || undefined,
      yearLevel: newStudentYearLevel.value.trim() || undefined,
    })
    await Swal.fire({
      icon: 'success',
      title: 'Student Account Added',
      html: `<div style="text-align:left">` +
        `<p><strong>Email:</strong> ${email}</p>` +
        (createdStudent.defaultPassword
          ? `<p style="margin-top:8px"><strong>Default Password:</strong> ${createdStudent.defaultPassword}</p>`
          : '') +
        `<p style="margin-top:8px">Share these credentials with the student. They will be required to change the password on first login.</p>` +
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

async function removeStudentEmail(record: SchoolStudentRow) {
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

function schoolStudentStatusClass(status?: string) {
  return String(status || 'Registered')
    .trim()
    .toLowerCase()
    .replace(/\s+/g, '-')
}

function startEditStudent(record: SchoolStudentRow) {
  editingStudentId.value = record.id
  editingStudentForm.value = {
    studentName: record.studentName || '',
    studentNumber: record.studentNumber || '',
    course: record.course || '',
    yearLevel: record.yearLevel || '',
    status: record.status || 'Registered',
  }
}

function cancelEditStudent() {
  editingStudentId.value = null
  editingStudentForm.value = {
    studentName: '',
    studentNumber: '',
    course: '',
    yearLevel: '',
    status: 'Registered',
  }
}

async function saveStudentEdit(record: SchoolStudentRow) {
  savingStudentEdit.value = true
  try {
    await updateSchoolStudent(record.id, {
      studentName: editingStudentForm.value.studentName.trim() || null,
      studentNumber: editingStudentForm.value.studentNumber.trim() || null,
      course: editingStudentForm.value.course.trim() || null,
      yearLevel: editingStudentForm.value.yearLevel.trim() || null,
      status: editingStudentForm.value.status,
    })
    cancelEditStudent()
    await Swal.fire({
      icon: 'success',
      title: 'Student Updated',
      confirmButtonColor: '#2563eb',
      timer: 1500,
      showConfirmButton: false,
    })
  } catch (err) {
    await Swal.fire({
      icon: 'error',
      title: 'Update Failed',
      text: err instanceof Error ? err.message : 'Could not update the student.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    savingStudentEdit.value = false
  }
}

async function toggleStudentAccess(record: SchoolStudentRow) {
  const nextStatus = (record.status || 'Registered') === 'Disabled' ? 'Registered' : 'Disabled'
  const actionLabel = nextStatus === 'Disabled' ? 'disable' : 'reactivate'
  const result = await Swal.fire({
    icon: 'question',
    title: `${nextStatus === 'Disabled' ? 'Disable' : 'Reactivate'} Student?`,
    text: `This will ${actionLabel} ${record.email}.`,
    showCancelButton: true,
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#6b7280',
  })
  if (!result.isConfirmed) return

  try {
    await updateSchoolStudent(record.id, { status: nextStatus })
    await Swal.fire({
      icon: 'success',
      title: nextStatus === 'Disabled' ? 'Student Disabled' : 'Student Reactivated',
      confirmButtonColor: '#2563eb',
      timer: 1500,
      showConfirmButton: false,
    })
  } catch (err) {
    await Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: err instanceof Error ? err.message : 'Could not update student access.',
      confirmButtonColor: '#2563eb',
    })
  }
}

</script>

<template>
  <div class="dashboard">
    <!-- School Sidebar Component -->
    <SchoolSidebar :activeItem="sidebarActiveItem" @menuClick="handleMenuClick" />

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
            <div class="avatar"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-img" /><span v-else>{{ userInitials }}</span></div>
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
            <p>
              You have {{ schoolDashboardStats.contracts.pending }} pending contract request{{ schoolDashboardStats.contracts.pending === 1 ? '' : 's' }}
              and {{ recommendedInternships.length }} recommended compan{{ recommendedInternships.length === 1 ? 'y' : 'ies' }}.
            </p>
          </div>

          <!-- Stats Cards -->
          <div class="stats-grid">
            <!-- Student Roster Card -->
            <div class="stat-card">
              <div class="stat-header">
                <span class="stat-label">STUDENT ROSTER</span>
              </div>
              <div class="stat-main">
                <div class="stat-number-large">{{ schoolDashboardStats.students.registered }} / {{ schoolDashboardStats.students.total }}</div>
                <div class="progress-bar-container">
                  <div
                    class="progress-bar"
                    :style="{ width: ((schoolDashboardStats.students.total ? schoolDashboardStats.students.registered / schoolDashboardStats.students.total : 0) * 100) + '%' }"
                  ></div>
                </div>
                <div class="stat-subtitle">{{ schoolDashboardStats.students.pending }} awaiting registration details</div>
              </div>
              <a href="#" class="stat-link" @click.prevent="currentView = 'student-interns'">View students →</a>
            </div>

            <!-- Contracts Card -->
            <div class="stat-card">
              <div class="stat-header">
                <span class="stat-label">CONTRACTS</span>
              </div>
              <div class="stat-main">
                <div class="stat-number-large">{{ schoolDashboardStats.contracts.active }} Active</div>
                <div class="stat-details">
                  <div>{{ schoolDashboardStats.contracts.pending }} Pending</div>
                  <div>{{ schoolDashboardStats.contracts.rejected }} Rejected</div>
                  <div>{{ companiesForContract.length }} Companies Available</div>
                </div>
              </div>
              <a href="#" class="stat-link" @click.prevent="currentView = 'contracts-select'">View contracts →</a>
            </div>

            <!-- Current Partner Card -->
            <div class="stat-card">
              <div class="stat-header">
                <span class="stat-label">CURRENT PARTNER</span>
              </div>
              <div class="stat-main">
                <div class="company-logo-card">
                  <img src="/icons/company-icon.png" alt="Company" />
                </div>
                <div class="company-info-card">
                  <div class="company-name-card">{{ schoolDashboardStats.currentPartner.company }}</div>
                  <div class="position-title-card">{{ schoolDashboardStats.currentPartner.position }}</div>
                  <div class="status-card">{{ schoolDashboardStats.currentPartner.status }}</div>
                </div>
              </div>
              <a href="#" class="stat-link" @click.prevent="currentView = 'contracts'">View details →</a>
            </div>

            <!-- Pending Tasks Card -->
            <div class="stat-card">
              <div class="stat-header">
                <span class="stat-label">PENDING TASKS</span>
              </div>
              <div class="stat-main">
                <div class="stat-number-large">{{ schoolDashboardStats.pendingTasks.length }}</div>
                <div class="task-list-card">
                  <div v-for="task in schoolDashboardStats.pendingTasks" :key="task.task" class="task-item-card">
                    • {{ task.task }}
                  </div>
                </div>
              </div>
              <a href="#" class="stat-link" @click.prevent="currentView = 'documents'">View tasks →</a>
            </div>
          </div>

          <!-- Recommended Section -->
          <section class="recommended-section">
            <div class="section-header">
              <h3>Recommended Companies</h3>
              <p>Based on your school profile and available partner companies</p>
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
                  <button class="btn-primary" @click="selectContractCompanyById(String(internship.id))">Create Contract</button>
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
              <a href="#" class="view-all-link" @click.prevent="currentView = 'contracts'">View all activity →</a>
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
                <div v-if="upcomingDeadlines.length === 0" class="empty-state" style="padding: 18px 0;">
                  No upcoming admin follow-ups right now.
                </div>
              </div>
              <a href="#" class="view-all-link" @click.prevent="currentView = 'documents'">
                <CalendarIcon class="calendar-icon-inline" />
                Open documents
              </a>
            </section>
          </div>
        </main>
      </div>

      <!-- Internship Requirements Management -->
      <div v-else-if="currentView === 'internship-requirements'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-internship.png" alt="Internship Requirements" class="header-icon-img" />
            <h1>Internship Requirements</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-img" /><span v-else>{{ userInitials }}</span></div>
          </div>
        </header>
        <main class="main-content">
          <div class="requirements-layout">
            <!-- Default Requirements Settings -->
            <div class="requirements-main">
              <div class="section-header">
                <h2>Default Internship Requirements</h2>
                <p class="section-subtitle">Set default hours and program requirements that will be applied to approved internships</p>
              </div>

              <!-- Program Hours Configuration -->
              <div class="requirements-card">
                <div class="card-header">
                  <h3>Required Completion Hours by Program</h3>
                  <p class="card-description">Set the minimum hours students from each program must complete</p>
                </div>
                <div class="card-content">
                  <div class="program-hours-list">
                    <div v-for="(hours, program) in defaultProgramHours" :key="program" class="program-hours-row">
                      <div class="program-name">{{ program }}</div>
                      <div class="hours-input-group">
                        <input 
                          type="number" 
                          min="1" 
                          step="1" 
                          v-model.number="defaultProgramHours[program]" 
                          class="form-input" 
                        />
                        <span class="input-suffix">hours</span>
                        <button 
                          class="btn-remove-program" 
                          @click="removeProgram(program)"
                          title="Remove Program"
                        >
                          <TrashIcon class="remove-icon" />
                        </button>
                      </div>
                    </div>
                  </div>
                  <div class="card-actions">
                    <button 
                      class="btn-primary" 
                      @click="saveProgramHours"
                      :disabled="savingProgramHours"
                    >
                      <span v-if="savingProgramHours">Saving...</span>
                      <span v-else>Save Program Hours</span>
                    </button>
                    <button class="btn-secondary" @click="openAddProgramModal">Add New Program</button>
                    <button class="btn-secondary" @click="loadExistingPrograms">Reload Programs</button>
                  </div>
                </div>
              </div>

              <!-- Program Eligibility Settings -->
              
              <!-- Additional Requirements -->
              <div class="requirements-card">
                <div class="card-header">
                  <h3>Additional Requirements</h3>
                  <p class="card-description">Set additional requirements that apply to all internships</p>
                </div>
                <div class="card-content">

                  <div class="form-group">
                    <label class="form-label">Year Level Requirements</label>
                    <div class="checkbox-group">
                      <label class="checkbox-label">
                        <input type="checkbox" />
                        <span>1st Year</span>
                      </label>
                      <label class="checkbox-label">
                        <input type="checkbox" />
                        <span>2nd Year</span>
                      </label>
                      <label class="checkbox-label">
                        <input type="checkbox" checked />
                        <span>3rd Year</span>
                      </label>
                      <label class="checkbox-label">
                        <input type="checkbox" checked />
                        <span>4th Year</span>
                      </label>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Required Documents</label>
                    <div class="checkbox-group">
                      <label class="checkbox-label">
                        <input type="checkbox" checked />
                        <span>Resume/CV</span>
                      </label>
                      <label class="checkbox-label">
                        <input type="checkbox" checked />
                        <span>Transcript of Records</span>
                      </label>
                      <label class="checkbox-label">
                        <input type="checkbox" />
                        <span>Portfolio (for design/creative roles)</span>
                      </label>
                      <label class="checkbox-label">
                        <input type="checkbox" />
                        <span>Recommendation Letter</span>
                      </label>
                    </div>
                  </div>
                  <div class="card-actions">
                    <button class="btn-primary">Save Additional Requirements</button>
                  </div>
                </div>
              </div>

              <div class="requirements-card">
                <div class="card-header">
                  <h3>School-Hosted Internship Placements</h3>
                  <p class="card-description">Post placements for programs like Psychology where students may intern inside your school or in another partner school setting.</p>
                </div>
                <div class="card-content">
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Placement Title</label>
                      <input v-model="schoolPlacementForm.title" type="text" class="form-input" placeholder="e.g. Guidance Office Internship" />
                    </div>
                    <div class="form-group">
                      <label class="form-label">Location</label>
                      <input v-model="schoolPlacementForm.location" type="text" class="form-input" placeholder="Cavite" />
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Setup</label>
                      <input v-model="schoolPlacementForm.type" type="text" class="form-input" placeholder="On-site" />
                    </div>
                    <div class="form-group">
                      <label class="form-label">Duration</label>
                      <input v-model="schoolPlacementForm.duration" type="text" class="form-input" placeholder="8 weeks" />
                    </div>
                    <div class="form-group">
                      <label class="form-label">Slots</label>
                      <input v-model.number="schoolPlacementForm.slotsAvailable" type="number" min="1" class="form-input" />
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Eligible Courses</label>
                      <textarea v-model="schoolPlacementForm.eligibleCoursesText" rows="2" class="form-input" placeholder="BS Psychology, BS Education"></textarea>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Requirements</label>
                      <textarea v-model="schoolPlacementForm.requirementsText" rows="2" class="form-input" placeholder="Resume, endorsement letter"></textarea>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea v-model="schoolPlacementForm.description" rows="3" class="form-input" placeholder="Describe the school-based internship placement."></textarea>
                  </div>
                  <div class="card-actions">
                    <button class="btn-primary" @click="createSchoolPlacement" :disabled="creatingSchoolPlacement">
                      <span v-if="creatingSchoolPlacement">Saving...</span>
                      <span v-else>Post School Placement</span>
                    </button>
                    <button class="btn-secondary" @click="resetSchoolPlacementForm">Clear</button>
                  </div>

                  <div class="school-placement-summary">
                    <div class="stat-item">
                      <span class="stat-label">Total Posted</span>
                      <span class="stat-value">{{ schoolPlacementStats.total }}</span>
                    </div>
                    <div class="stat-item">
                      <span class="stat-label">Active</span>
                      <span class="stat-value">{{ schoolPlacementStats.active }}</span>
                    </div>
                    <div class="stat-item">
                      <span class="stat-label">Drafts</span>
                      <span class="stat-value">{{ schoolPlacementStats.draft }}</span>
                    </div>
                  </div>

                  <div v-if="schoolHostedInternships.length === 0" class="empty-state" style="padding: 16px 0;">
                    No school-hosted placements yet.
                  </div>
                  <div v-else class="school-placement-list">
                    <div v-for="placement in schoolHostedInternships" :key="placement.id" class="school-placement-card">
                      <div>
                        <div class="program-name">{{ placement.title }}</div>
                        <div class="placement-meta">{{ placement.location || 'Cavite' }} • {{ placement.duration || 'TBD' }} • {{ placement.slotsAvailable }} slot(s)</div>
                        <div class="placement-courses">{{ (placement.eligibleCourses || []).join(', ') || 'Open to aligned school programs' }}</div>
                      </div>
                      <div class="table-actions">
                        <span class="status-badge" :class="placement.status">{{ placement.status }}</span>
                        <button class="table-btn danger" @click="removeSchoolPlacement(placement)">Delete</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Sidebar -->
            <div class="requirements-sidebar">
              <!-- Quick Stats -->
              <div class="sidebar-section">
                <h3>Current Statistics</h3>
                <div class="stats-list">
                  <div class="stat-item">
                    <span class="stat-label">Active Internships</span>
                    <span class="stat-value">24</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Programs Covered</span>
                    <span class="stat-value">{{ Object.keys(defaultProgramHours).length }}</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Average Hours</span>
                    <span class="stat-value">{{ Object.keys(defaultProgramHours).length > 0 ? Math.round(Object.values(defaultProgramHours).reduce((a, b) => a + b, 0) / Object.keys(defaultProgramHours).length) : 0 }}</span>
                  </div>
                </div>
              </div>

              <!-- Recent Changes -->
              <div class="sidebar-section">
                <h3>Recent Changes</h3>
                <div class="activity-feed">
                  <div class="activity-item">
                    <span class="activity-text">Updated BSIT hours to 400</span>
                    <span class="activity-time">2 days ago</span>
                  </div>
                  <div class="activity-item">
                    <span class="activity-text">Added portfolio requirement</span>
                    <span class="activity-time">1 week ago</span>
                  </div>
                </div>
              </div>

              <!-- Help & Guidelines -->
              <div class="sidebar-section">
                <h3>Guidelines</h3>
                <div class="help-content">
                  <p class="help-text">
                    <InformationCircleIcon class="help-icon" />
                    Set realistic hour requirements based on your curriculum and industry standards.
                  </p>
                  <p class="help-text">
                    <InformationCircleIcon class="help-icon" />
                    Program eligibility helps companies understand which students are suitable for their internships.
                  </p>
                </div>
              </div>
            </div>
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
            <div class="avatar"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-img" /><span v-else>{{ userInitials }}</span></div>
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
                    <button class="btn-approve" @click="openReviewModal('techstart-fullstack')">Review Posting</button>
                    <button class="btn-secondary-action" @click="quickApprove('techstart-fullstack')">Quick Approve</button>
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
            <div class="avatar"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-img" /><span v-else>{{ userInitials }}</span></div>
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
            <div class="avatar">
              <img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-img" />
              <span v-else>{{ userInitials }}</span>
            </div>
          </div>
        </header>
        <main class="main-content">
          <div class="companies-page-header">
            <h2>Choose a Company Partner</h2>
            <p class="page-subtitle">Browse available companies and select one to start a contract request.</p>
          </div>

          <div class="companies-controls">
            <div class="search-section">
              <div class="search-box">
                <MagnifyingGlassIcon class="search-icon-svg" />
                <input
                  v-model="contractCompanySearch"
                  type="search"
                  placeholder="Search companies by name or email..."
                  class="search-input-companies"
                />
              </div>
            </div>
          </div>

          <div class="companies-grid">
            <div v-if="filteredContractCompanies.length === 0" class="empty-state">
              No companies found.
            </div>

            <template v-else>
              <button
                v-for="company in filteredContractCompanies"
                :key="company.uid"
                type="button"
                class="company-card company-card-select"
                @click="selectContractCompany(company)"
              >
                <div class="company-card-header">
                  <div class="contracts-company-icon company-card-logo" :style="companyLogoStyle(company)">
                    <span class="contracts-company-initials">{{ companyInitials(company) }}</span>
                  </div>
                  <div class="company-details">
                    <h3>{{ companyDisplayLabel(company) }}</h3>
                    <p class="company-name">{{ company.email || 'Directory profile' }}</p>
                  </div>
                </div>

                <p class="company-description">
                  {{ ((company as PublicProfile & { courses?: string[] }).courses?.length ?? 0) > 0
                    ? `Accepts: ${((company as PublicProfile & { courses?: string[] }).courses ?? []).join(', ')}`
                    : 'Select this company to continue to contract type selection and contract request details.' }}
                </p>

                <div class="company-card-footer">
                  <span class="company-card-meta">
                    {{ schoolContracts.some((c) => c.companyId === company.uid && (c.status || 'pending') === 'pending')
                      ? 'Pending contract exists'
                      : 'Ready for contract request' }}
                  </span>
                  <span class="company-card-action">Select Company</span>
                </div>
              </button>
            </template>
          </div>
        </main>
      </div>
      <div v-else-if="currentView === 'contracts-select'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-docs.png" alt="Contracts" class="header-icon-img" />
            <h1>Select a Company</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-img" /><span v-else>{{ userInitials }}</span></div>
          </div>
        </header>
        <main class="main-content">
          <div class="student-emails-section">
            <h2 class="section-title">Choose a Company</h2>
            <p class="section-subtitle">Pick a company logo to continue to the contract request form.</p>

            <div class="section-card">
              <div class="contracts-select-search">
                <input
                  v-model="contractCompanySearch"
                  type="text"
                  class="form-input"
                  placeholder="Search company name or email..."
                />
              </div>

              <div v-if="filteredContractCompanies.length === 0" class="empty-state" style="margin-top: 16px;">
                No companies found.
              </div>

              <div v-else class="contracts-company-list">
                <button
                  v-for="c in filteredContractCompanies"
                  :key="c.uid"
                  type="button"
                  class="contracts-company-row"
                  @click="selectContractCompany(c)"
                >
                  <div class="contracts-company-icon" :style="companyLogoStyle(c)">
                    <span class="contracts-company-initials">{{ companyInitials(c) }}</span>
                  </div>
                  <div class="contracts-company-meta">
                    <div class="contracts-company-name">{{ companyDisplayLabel(c) }}</div>
                    <div v-if="c.email" class="contracts-company-sub">{{ c.email }}</div>
                  </div>
                  <ChevronRightIcon class="contracts-company-chevron" aria-hidden="true" />
                </button>
              </div>
            </div>
          </div>
        </main>
      </div>

      <div v-else-if="currentView === 'contracts'">
        <header class="top-header">
          <div class="header-left">
            <button
              type="button"
              class="contracts-back-btn"
              aria-label="Back to company selection"
              @click="goBackToContractCompanies"
            >
              <span class="contracts-back-arrow" aria-hidden="true">←</span>
            </button>
            <img src="/icons/icon-docs.png" alt="Contracts" class="header-icon-img" />
            <h1>School Contract Requests</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-img" /><span v-else>{{ userInitials }}</span></div>
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
                      <select
                        v-model="schoolContractForm.companyId"
                        class="form-input"
                        :disabled="!!schoolContractForm.companyId"
                        @change="onSchoolContractCompanySelect"
                      >
                        <option value="">-- Select Company --</option>
                        <option v-for="c in companiesForContract" :key="c.uid" :value="c.uid">{{ c.orgName || c.displayName || c.email || c.uid }}</option>
                      </select>
                      <p v-if="companiesForContract.length === 0" class="bulk-import-hint" style="margin-top:8px;">
                        No companies available yet. Ask a company to log in and complete their profile (Company Settings) so it appears here.
                      </p>
                    </div>
                    <div class="form-group">
                      <label>Contract Type</label>
                      <div class="contract-type-input-row">
                        <input v-model="schoolContractForm.contractType" type="text" class="form-input" readonly />
                        <button type="button" class="contract-type-change-btn" @click="openContractTypeModal">Change</button>
                      </div>
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
                      <p v-if="isContractAmendment" class="bulk-import-hint">
                        Optional for amendments: only set course slots if you are changing allocations in this addendum.
                      </p>
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
                      <p class="bulk-import-hint">Attach draft MOA, guidelines, or supporting files.</p>
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

            <h2 class="section-title" style="margin-top: 28px;">Contract Requests</h2>
            <div v-if="schoolContracts.length === 0" class="empty-state">
              No contract requests yet.
            </div>
            <div v-else class="contracts-table-wrap">
              <table class="contracts-table">
                <thead>
                  <tr>
                    <th>Ref</th>
                    <th>Requested By</th>
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
                      <td>{{ contractRequestedByLabel(c.requestedByRole) }}</td>
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
                          v-if="canSchoolRespondToContract(c)"
                          type="button"
                          class="table-btn ok"
                          @click="handleAcceptSchoolContract(c)"
                        >
                          Accept
                        </button>
                        <button
                          v-if="canSchoolRespondToContract(c)"
                          type="button"
                          class="table-btn danger"
                          @click="handleRejectSchoolContract(c)"
                        >
                          Reject
                        </button>
                        <button
                          v-if="canSchoolCancelContract(c)"
                          type="button"
                          class="table-btn danger"
                          @click="handleCancelSchoolContract(c)"
                        >
                          Cancel
                        </button>
                      </td>
                    </tr>
                    <tr v-if="expandedSentContractId === c.id" class="details-row">
                      <td colspan="7">
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
            <div class="avatar"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-img" /><span v-else>{{ userInitials }}</span></div>
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
                      placeholder="Enter student name"
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
                      placeholder="Enter company name"
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
            <div class="avatar"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-img" /><span v-else>{{ userInitials }}</span></div>
          </div>
        </header>
        
        <!-- Documents Toolbar -->
        <div class="documents-toolbar">
          <div class="toolbar-left">
            <div class="sort-container-toolbar">
              <span class="sort-label-toolbar">Sort by:</span>
              <select v-model="documentsSortBy" class="sort-select-toolbar">
                <option value="recent">Recent</option>
                <option value="name">Name</option>
                <option value="size">Size</option>
                <option value="status">Status</option>
              </select>
            </div>
            <div class="divider-vertical"></div>
            <div class="documents-count-chip">
              {{ schoolDocumentStats.total }} total
            </div>
            <div class="documents-count-chip approved">
              {{ schoolDocumentStats.approved }} approved
            </div>
            <div class="documents-count-chip pending">
              {{ schoolDocumentStats.pending }} pending
            </div>
          </div>
          <div class="toolbar-right">
            <div class="search-container-toolbar">
              <MagnifyingGlassIcon class="search-icon-toolbar" />
              <input 
                type="text" 
                placeholder="Search documents..." 
                class="search-input-toolbar"
                v-model="documentsSearchQuery"
              />
            </div>
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
          <div v-if="filteredSchoolDocuments.length > 0" class="documents-grid-school">
            <div v-for="doc in filteredSchoolDocuments" :key="doc.id" class="document-card-school">
              <div class="doc-type-badge-school">{{ documentExtension(doc) }}</div>
              <div class="doc-icon-wrapper-school">
                <div class="doc-icon-container-school doc-icon-generic-school">
                  <DocumentIcon class="doc-svg-school" />
                </div>
              </div>
              <div class="doc-info-school">
                <h3 class="doc-name-school">{{ doc.fileName }}</h3>
                <div class="doc-details-school">
                  <span class="doc-size-school">{{ formatSchoolFileSize(doc.fileSize || 0) }}</span>
                  <span class="doc-date-school">{{ formatDocumentDate(doc.createdAt) }}</span>
                </div>
                <div class="doc-details-school">
                  <span class="doc-category-school">{{ doc.category }}</span>
                </div>
                <div class="doc-status-wrapper-school">
                  <span class="status-badge-school" :class="documentStatusClass(doc.status)">{{ doc.status }}</span>
                  <a v-if="doc.fileUrl" :href="doc.fileUrl" target="_blank" rel="noopener noreferrer" class="doc-open-link-school">
                    Open
                  </a>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="documents-empty-state">
            <DocumentIcon class="documents-empty-icon" />
            <h3>No documents yet</h3>
            <p>Upload your school files here and they will appear in this list.</p>
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
                :disabled="schoolSelectedFiles.length === 0 || uploadingDocuments"
                @click="confirmSchoolUpload"
              >
                <svg class="upload-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                {{ uploadingDocuments ? 'Uploading...' : 'Upload Files' }}
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
            <div class="avatar"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-img" /><span v-else>{{ userInitials }}</span></div>
          </div>
        </header>
        <main class="main-content">
          <!-- Reports Header -->
          <div class="reports-header">
            <div class="reports-title-section">
              <h2>My Reports</h2>
              <span class="reports-count">{{ schoolReports.length }} reports</span>
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
              <div class="stat-number-report">{{ reportStats.pending }}</div>
            </div>
            <div class="stat-card-report">
              <div class="stat-header-report">
                <span class="stat-label-report">REVISION REQUESTED</span>
                <div class="stat-icon revision-icon">
                  <ExclamationTriangleIcon class="stat-icon-svg" />
                </div>
              </div>
              <div class="stat-number-report">{{ reportStats.revisionRequested }}</div>
            </div>
            <div class="stat-card-report">
              <div class="stat-header-report">
                <span class="stat-label-report">APPROVED REPORTS</span>
                <div class="stat-icon approved-icon">
                  <CheckCircleIcon class="stat-icon-svg" />
                </div>
              </div>
              <div class="stat-number-report">{{ reportStats.approved }}</div>
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
              <tbody v-if="schoolReports.length > 0">
                <tr v-for="report in schoolReports" :key="report.id">
                  <td class="report-type">{{ formatReportTypeLabel(report.reportType) }}</td>
                  <td>{{ formatReportPeriod(report) }}</td>
                  <td>{{ formatDocumentDate(report.submittedAt || report.createdAt) }}</td>
                  <td><span class="status-badge" :class="report.companyStatus">{{ report.companyStatus.replaceAll('_', ' ') }}</span></td>
                  <td>
                    <span v-if="report.schoolStatus" class="status-badge" :class="report.schoolStatus">{{ report.schoolStatus.replaceAll('_', ' ') }}</span>
                    <span v-else>—</span>
                  </td>
                  <td>
                    <button
                      class="action-btn"
                      type="button"
                      @click="reportFormData = {
                        reportType: report.reportType,
                        studentName: report.studentName,
                        internshipPeriodStart: report.internshipPeriodStart,
                        internshipPeriodEnd: report.internshipPeriodEnd,
                        companyName: report.companyName,
                      }; currentView = 'submit-report'"
                    >
                      <EllipsisVerticalIcon class="action-menu-icon" />
                    </button>
                  </td>
                </tr>
              </tbody>
              <tbody v-else-if="schoolReports.length === 0">
                <tr>
                  <td colspan="6" class="empty-state">No reports yet. Submit your first report to see it here.</td>
                </tr>
              </tbody>
              <tbody v-if="false">
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
            <div class="avatar"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-img" /><span v-else>{{ userInitials }}</span></div>
          </div>
        </header>
        <main class="main-content">
          <div class="student-emails-section">
            <div class="section-card">
              <h2 class="section-title">Generate Student School Email</h2>
              <p class="section-subtitle">Enter student details manually. The system will auto-generate the student email using the format lastname.firstname@domain.</p>
              <div class="add-student-form">
                <div class="form-row">
                  <div class="form-group">
                    <label>First Name</label>
                    <input v-model="newStudentFirstName" type="text" placeholder="Enter first name" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>Last Name</label>
                    <input v-model="newStudentLastName" type="text" placeholder="Enter last name" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label>School Email Domain</label>
                    <input v-model="newStudentEmailDomain" type="text" class="form-input" placeholder="@cvsu.edu.ph" />
                    <small class="field-note">Enter your school email domain (with or without @)</small>
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
                    <label>Program (optional)</label>
                    <select v-model="newStudentCourse" class="form-select">
                      <option value="">Select a program</option>
                      <option v-for="program in availablePrograms" :key="program" :value="program">
                        {{ program }}
                      </option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Year Level (optional)</label>
                    <select v-model="newStudentYearLevel" class="form-select">
                      <option value="">Select year level</option>
                      <option v-for="y in yearLevelOptions" :key="y" :value="y">{{ y }}</option>
                    </select>
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
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                      </tr>
                    </thead>
                    <tbody v-if="false">
                      <tr v-for="s in filteredSchoolStudentEmails" :key="s.id">
                        <td>{{ s.email }}</td>
                        <td>{{ s.studentName || '—' }}</td>
                        <td>{{ s.studentNumber || '—' }}</td>
                        <td>{{ s.course || '—' }}</td>
                        <td>{{ s.yearLevel || '—' }}</td>
                        <td><span class="status-badge" :class="s.status">{{ s.status }}</span></td>
                        <td>
                          <button @click="removeStudentEmail(s)" class="btn-remove" title="Remove">
                            <TrashIcon class="icon-sm" />
                          </button>
                        </td>
                      </tr>
                    </tbody>
                    <tbody>
                      <tr v-for="s in filteredSchoolStudentEmails" :key="s.id">
                        <td>{{ s.email }}</td>
                        <td>
                          <template v-if="editingStudentId === s.id">
                            <input v-model="editingStudentForm.studentName" type="text" class="table-edit-input" placeholder="Student name" />
                          </template>
                          <template v-else>{{ s.studentName || '—' }}</template>
                        </td>
                        <td>
                          <template v-if="editingStudentId === s.id">
                            <input v-model="editingStudentForm.studentNumber" type="text" class="table-edit-input" placeholder="Student no." />
                          </template>
                          <template v-else>{{ s.studentNumber || '—' }}</template>
                        </td>
                        <td>
                          <template v-if="editingStudentId === s.id">
                            <input v-model="editingStudentForm.course" type="text" class="table-edit-input" placeholder="Course" />
                          </template>
                          <template v-else>{{ s.course || '—' }}</template>
                        </td>
                        <td>
                          <template v-if="editingStudentId === s.id">
                            <select v-model="editingStudentForm.yearLevel" class="table-edit-input">
                              <option value="">Select year</option>
                              <option v-for="year in yearLevelOptions" :key="year" :value="year">{{ year }}</option>
                            </select>
                          </template>
                          <template v-else>{{ s.yearLevel || '—' }}</template>
                        </td>
                        <td>
                          <template v-if="editingStudentId === s.id">
                            <select v-model="editingStudentForm.status" class="table-edit-input">
                              <option value="Pending Setup">Pending Setup</option>
                              <option value="Registered">Registered</option>
                              <option value="Disabled">Disabled</option>
                            </select>
                          </template>
                          <template v-else>
                            <span class="status-badge" :class="schoolStudentStatusClass(s.status)">{{ s.status }}</span>
                          </template>
                        </td>
                        <td>
                          <div class="table-actions">
                            <template v-if="editingStudentId === s.id">
                              <button @click="saveStudentEdit(s)" class="btn-table-action btn-save" :disabled="savingStudentEdit" title="Save">
                                {{ savingStudentEdit ? 'Saving...' : 'Save' }}
                              </button>
                              <button @click="cancelEditStudent" class="btn-table-action btn-cancel" :disabled="savingStudentEdit" title="Cancel">
                                Cancel
                              </button>
                            </template>
                            <template v-else>
                              <button @click="startEditStudent(s)" class="btn-table-action btn-edit" title="Edit">
                                Edit
                              </button>
                              <button
                                @click="toggleStudentAccess(s)"
                                class="btn-table-action"
                                :class="(s.status || 'Registered') === 'Disabled' ? 'btn-activate' : 'btn-disable'"
                                :title="(s.status || 'Registered') === 'Disabled' ? 'Reactivate' : 'Disable'"
                              >
                                {{ (s.status || 'Registered') === 'Disabled' ? 'Reactivate' : 'Disable' }}
                              </button>
                              <button @click="removeStudentEmail(s)" class="btn-remove" title="Remove">
                                <TrashIcon class="icon-sm" />
                              </button>
                            </template>
                          </div>
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
            <div class="avatar"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-img" /><span v-else>{{ userInitials }}</span></div>
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

      <div v-else-if="currentView === 'team-access'">
        <OrganizationAccessManager organizationType="school" />
      </div>

      <div v-else-if="currentView === 'settings'">
        <SchoolSettings />
      </div>
    </div>

    <!-- Add New Program Modal -->
    <div v-if="showAddProgramModal" class="modal-overlay" @click="closeAddProgramModal">
      <div class="modal-content add-program-modal" @click.stop>
        <div class="modal-header">
          <h2>Add New Program</h2>
          <button class="modal-close" @click="closeAddProgramModal">
            <XMarkIcon class="close-icon" />
          </button>
        </div>
        
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Program Name <span class="required">*</span></label>
            <input 
              type="text" 
              v-model="newProgramName"
              placeholder="e.g. BS Business Administration (BSBA)"
              class="form-input"
              @keyup.enter="addNewProgram"
            />
            <p class="form-hint">Enter the full program name with abbreviation in parentheses</p>
          </div>
          
          <div class="form-group">
            <label class="form-label">Required Hours <span class="required">*</span></label>
            <div class="hours-input-wrapper">
              <input 
                type="number" 
                min="1" 
                step="1" 
                v-model.number="newProgramHours"
                class="form-input hours-input"
              />
              <span class="hours-suffix">hours</span>
            </div>
            <p class="form-hint">Set the minimum completion hours for this program</p>
          </div>
        </div>
        
        <div class="modal-footer">
          <button class="btn-secondary" @click="closeAddProgramModal">Cancel</button>
          <button class="btn-primary" @click="addNewProgram">Add Program</button>
        </div>
      </div>
    </div>

    <!-- Internship Review Modal -->
    <div v-if="showInternshipReviewModal" class="modal-overlay" @click="closeReviewModal">
      <div class="modal-content review-modal" @click.stop>
        <div class="modal-header">
          <h2>Review Internship Posting</h2>
          <button class="modal-close" @click="closeReviewModal">
            <XMarkIcon class="close-icon" />
          </button>
        </div>
        
        <div class="modal-body">
          <div class="review-sections">
            <!-- Program Hours Section -->
            <div class="review-section">
              <h3>Required Completion Hours by Program</h3>
              <p class="section-description">Set the minimum hours students from each program must complete</p>
              <div class="program-hours-grid">
                <div v-for="(hours, program) in reviewFormData.programHours" :key="program" class="program-hours-item">
                  <label class="program-label">{{ program }}</label>
                  <div class="hours-input-wrapper">
                    <input 
                      type="number" 
                      min="1" 
                      step="1" 
                      v-model.number="reviewFormData.programHours[program]"
                      class="form-input hours-input"
                    />
                    <span class="hours-suffix">hours</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Eligible Programs Section -->
            <div class="review-section">
              <h3>Eligible Programs</h3>
              <p class="section-description">Select which programs are eligible for this internship</p>
              <div class="programs-grid">
                <label v-for="program in Object.keys(reviewFormData.programHours)" :key="program" class="program-checkbox">
                  <input 
                    type="checkbox" 
                    :value="program"
                    v-model="reviewFormData.eligiblePrograms"
                  />
                  <span>{{ program }}</span>
                </label>
              </div>
            </div>

            <!-- Additional Requirements Section -->
            <div class="review-section">
              <h3>Additional Requirements</h3>
              <div class="requirements-grid">
                <div class="requirement-group">
                  <label class="form-label">Minimum GPA</label>
                  <select v-model="reviewFormData.minimumGPA" class="form-select">
                    <option value="">No minimum</option>
                    <option value="2.0">2.0 (Satisfactory)</option>
                    <option value="2.5">2.5 (Good)</option>
                    <option value="3.0">3.0 (Very Good)</option>
                    <option value="3.5">3.5 (Excellent)</option>
                  </select>
                </div>
                
                <div class="requirement-group">
                  <label class="form-label">Year Levels</label>
                  <div class="checkbox-group">
                    <label v-for="year in ['2nd Year', '3rd Year', '4th Year']" :key="year" class="checkbox-label">
                      <input 
                        type="checkbox" 
                        :value="year"
                        v-model="reviewFormData.yearLevels"
                      />
                      <span>{{ year }}</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Required Documents Section -->
            <div class="review-section">
              <h3>Required Documents</h3>
              <div class="documents-grid">
                <label v-for="doc in ['Resume/CV', 'Transcript of Records', 'Portfolio', 'Recommendation Letter']" :key="doc" class="document-checkbox">
                  <input 
                    type="checkbox" 
                    :value="doc"
                    v-model="reviewFormData.requiredDocuments"
                  />
                  <span>{{ doc }}</span>
                </label>
              </div>
            </div>

            <!-- Additional Notes Section -->
            <div class="review-section">
              <h3>Additional Requirements & Notes</h3>
              <textarea 
                v-model="reviewFormData.additionalRequirements"
                placeholder="Add any additional requirements or special instructions..."
                class="form-textarea"
                rows="3"
              ></textarea>
            </div>

            <!-- Approval Notes Section -->
            <div class="review-section">
              <h3>Approval Notes (Internal)</h3>
              <textarea 
                v-model="reviewFormData.approvalNotes"
                placeholder="Add internal notes about this approval..."
                class="form-textarea"
                rows="2"
              ></textarea>
            </div>
          </div>
        </div>
        
        <div class="modal-footer">
          <button class="btn-secondary" @click="closeReviewModal">Cancel</button>
          <button class="btn-primary" @click="approveInternshipWithRequirements">Approve with Requirements</button>
        </div>
      </div>
    </div>

    <!-- Contract Type Modal (shown after selecting a company icon) -->
    <div
      v-if="showContractTypeModal"
      class="contract-type-modal-backdrop"
      role="dialog"
      aria-modal="true"
      @click.self="cancelContractTypeModal"
    >
      <div class="contract-type-modal">
        <div class="contract-type-modal-header">
          <button type="button" class="contract-type-modal-close" aria-label="Close" @click="cancelContractTypeModal">
            <XMarkIcon class="contract-type-modal-close-icon" />
          </button>
          <div class="contract-type-modal-title-wrap">
            <div class="contract-type-modal-title">Select Contract Type</div>
            <div class="contract-type-modal-subtitle">Choose the type of OJT contract you want to create.</div>
          </div>
        </div>

        <div v-if="selectedCompanyPublic" class="contract-type-company">
          <div class="contracts-company-icon" :style="companyLogoStyle(selectedCompanyPublic)">
            <span class="contracts-company-initials">{{ companyInitials(selectedCompanyPublic) }}</span>
          </div>
          <div class="contract-type-company-meta">
            <div class="contract-type-company-name">{{ companyDisplayLabel(selectedCompanyPublic) }}</div>
            <div v-if="selectedCompanyPublic.email" class="contract-type-company-sub">{{ selectedCompanyPublic.email }}</div>
          </div>
          <button type="button" class="contract-type-company-change" @click="changeCompanyFromContractTypeModal">
            Change Company
          </button>
        </div>

        <div class="contract-type-grid">
          <button
            v-for="opt in contractTypeOptions"
            :key="opt.value"
            type="button"
            class="contract-type-card"
            :class="{ selected: selectedContractType === opt.value }"
            @click="selectedContractType = opt.value"
          >
            <div class="contract-type-card-top">
              <div class="contract-type-card-icon-wrap">
                <component :is="opt.icon" class="contract-type-card-icon" aria-hidden="true" />
              </div>
              <div v-if="selectedContractType === opt.value" class="contract-type-card-check" aria-hidden="true">
                <CheckIcon class="contract-type-card-check-icon" />
              </div>
            </div>
            <div class="contract-type-card-title">{{ opt.title }}</div>
            <div class="contract-type-card-badge">{{ opt.badge }}</div>
            <div class="contract-type-card-desc">{{ opt.description }}</div>
          </button>
        </div>

        <div class="contract-type-modal-actions">
          <button type="button" class="contract-type-btn secondary" @click="changeCompanyFromContractTypeModal">Back</button>
          <button type="button" class="contract-type-btn primary" @click="confirmContractTypeModal">Continue</button>
        </div>
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
.status-badge.draft { background: #e2e8f0; color: #334155; }
.status-badge.approved { background: #dcfce7; color: #166534; }
.status-badge.revision_requested { background: #fee2e2; color: #991b1b; }
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

.contracts-select-search { margin-top: 8px; }
.contracts-company-list { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }
.contracts-company-row {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #f8fafc;
  cursor: pointer;
  text-align: left;
  transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease, background-color 0.12s ease;
}
.contracts-company-row:hover { transform: translateY(-1px); box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08); border-color: #c7d2fe; background: #fff; }
.contracts-company-row:active { transform: translateY(0px); box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08); }
.contracts-company-row:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.22), 0 10px 25px rgba(15, 23, 42, 0.08); border-color: rgba(37, 99, 235, 0.6); }

.contracts-company-icon {
  width: 46px;
  height: 46px;
  border-radius: 999px;
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(37, 99, 235, 0.18);
  box-shadow: 0 10px 22px rgba(15, 23, 42, 0.10);
  flex: 0 0 auto;
}
.contracts-company-icon::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(70% 70% at 24% 20%, rgba(255, 255, 255, 0.35), transparent 58%),
    radial-gradient(75% 75% at 85% 80%, rgba(15, 23, 42, 0.20), transparent 60%);
  opacity: 1;
}
.contracts-company-icon::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0) 55%);
  mix-blend-mode: overlay;
  opacity: 0.38;
}
.contracts-company-initials {
  position: relative;
  z-index: 1;
  color: #fff;
  font-weight: 900;
  letter-spacing: 0.6px;
  font-size: 0.95rem;
  text-shadow: 0 2px 12px rgba(15, 23, 42, 0.35);
}
.contracts-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #0f172a;
  font-weight: 800;
  cursor: pointer;
}
.contracts-back-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
.contracts-back-arrow { font-size: 1.1rem; line-height: 1; }
.contracts-company-meta { min-width: 0; }
.contracts-company-name { font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.contracts-company-sub { font-size: 0.85rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.contracts-company-chevron {
  width: 20px;
  height: 20px;
  color: #94a3b8;
  flex: 0 0 auto;
  margin-left: auto;
  transition: transform 0.12s ease, color 0.12s ease;
}
.contracts-company-row:hover .contracts-company-chevron { transform: translateX(2px); color: #2563eb; }

.contract-type-input-row { display: flex; gap: 10px; align-items: center; }
.contract-type-input-row .form-input { flex: 1; }
.contract-type-change-btn {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid #cbd5e1;
  background: #fff;
  font-weight: 800;
  cursor: pointer;
  white-space: nowrap;
  color: #0f172a;
}
.contract-type-change-btn:hover { background: #f8fafc; border-color: #94a3b8; }

.contract-type-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 1200;
}
.contract-type-modal {
  width: min(980px, 100%);
  background: #fff;
  border-radius: 18px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  box-shadow: 0 35px 80px rgba(15, 23, 42, 0.28);
  padding: 18px;
}
.contract-type-modal-header { display: flex; gap: 12px; align-items: flex-start; }
.contract-type-modal-close {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #fff;
  cursor: pointer;
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.contract-type-modal-close:hover { background: #f8fafc; border-color: #cbd5e1; }
.contract-type-modal-close-icon { width: 18px; height: 18px; color: #334155; }
.contract-type-modal-title { font-weight: 900; font-size: 1.15rem; color: #0f172a; }
.contract-type-modal-subtitle { margin-top: 2px; color: #64748b; font-size: 0.92rem; }

.contract-type-company {
  margin-top: 12px;
  padding: 12px 12px;
  border-radius: 14px;
  border: 1px solid #e5e7eb;
  background: #f8fafc;
  display: flex;
  align-items: center;
  gap: 12px;
}
.contract-type-company-meta { min-width: 0; }
.contract-type-company-name { font-weight: 900; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.contract-type-company-sub { font-size: 0.85rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.contract-type-company-change {
  margin-left: auto;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  background: #fff;
  cursor: pointer;
  font-weight: 800;
  color: #1d4ed8;
  white-space: nowrap;
}
.contract-type-company-change:hover { background: #eff6ff; border-color: rgba(37, 99, 235, 0.35); }

.contract-type-grid {
  margin-top: 14px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}
.contract-type-card {
  width: 100%;
  text-align: left;
  padding: 14px;
  border-radius: 16px;
  border: 1px solid #e5e7eb;
  background: #fff;
  cursor: pointer;
  transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
  position: relative;
}
.contract-type-card:hover { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(15, 23, 42, 0.10); border-color: #c7d2fe; }
.contract-type-card.selected { border-color: rgba(37, 99, 235, 0.6); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16), 0 12px 28px rgba(15, 23, 42, 0.10); }
.contract-type-card-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
.contract-type-card-icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #eff6ff;
  border: 1px solid rgba(37, 99, 235, 0.18);
  display: flex;
  align-items: center;
  justify-content: center;
}
.contract-type-card-icon { width: 20px; height: 20px; color: #2563eb; }
.contract-type-card-check {
  width: 28px;
  height: 28px;
  border-radius: 999px;
  background: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 10px 22px rgba(15, 23, 42, 0.18);
}
.contract-type-card-check-icon { width: 16px; height: 16px; color: #fff; }
.contract-type-card-title { font-weight: 900; color: #0f172a; }
.contract-type-card-badge {
  display: inline-flex;
  margin-top: 6px;
  font-size: 0.72rem;
  font-weight: 900;
  padding: 3px 8px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #334155;
  border: 1px solid #e2e8f0;
}
.contract-type-card-desc { margin-top: 8px; color: #64748b; font-size: 0.88rem; line-height: 1.35; }

.contract-type-modal-actions {
  margin-top: 14px;
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  align-items: center;
}
.contract-type-btn {
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  background: #fff;
  cursor: pointer;
  font-weight: 900;
}
.contract-type-btn.primary { background: #2563eb; border-color: #2563eb; color: #fff; }
.contract-type-btn.primary:hover { background: #1d4ed8; border-color: #1d4ed8; }
.contract-type-btn.secondary:hover { background: #f8fafc; border-color: #94a3b8; }

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
.add-student-form .form-input[disabled] { background-color: #f1f5f9; color: #0f172a; cursor: not-allowed; }
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
.emails-table .status-badge.pending-setup { background: #fef3c7; color: #92400e; }
.emails-table .status-badge.registered { background: #d1fae5; color: #065f46; }
.emails-table .status-badge.disabled { background: #e5e7eb; color: #374151; }
.table-edit-input {
  width: 100%;
  min-width: 110px;
  padding: 8px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.875rem;
  background: #fff;
}
.table-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.btn-table-action {
  border: none;
  border-radius: 8px;
  padding: 7px 10px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}
.btn-table-action:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-edit { background: #dbeafe; color: #1d4ed8; }
.btn-disable { background: #fee2e2; color: #b91c1c; }
.btn-activate { background: #dcfce7; color: #166534; }
.btn-save { background: #2563eb; color: #fff; }
.btn-cancel { background: #e5e7eb; color: #374151; }
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
.school-placement-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-top: 18px;
}
.school-placement-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 18px;
}
.school-placement-card {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  padding: 16px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #f8fafc;
}
.placement-meta {
  margin-top: 6px;
  color: #475569;
  font-size: 0.9rem;
}
.placement-courses {
  margin-top: 6px;
  color: #64748b;
  font-size: 0.85rem;
}
.documents-count-chip {
  display: inline-flex;
  align-items: center;
  padding: 8px 12px;
  border-radius: 999px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  font-size: 0.82rem;
  font-weight: 700;
}
.documents-count-chip.approved {
  background: #ecfdf5;
  border-color: #a7f3d0;
  color: #166534;
}
.documents-count-chip.pending {
  background: #fffbeb;
  border-color: #fcd34d;
  color: #92400e;
}
.doc-icon-generic-school {
  background: linear-gradient(135deg, #eff6ff, #dbeafe);
}
.doc-svg-school {
  width: 34px;
  height: 34px;
  color: #2563eb;
}
.doc-category-school {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #475569;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: capitalize;
}
.doc-open-link-school {
  color: #2563eb;
  font-weight: 700;
  text-decoration: none;
}
.doc-open-link-school:hover {
  text-decoration: underline;
}
.documents-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-height: 280px;
  background: #fff;
  border: 1px dashed #cbd5e1;
  border-radius: 20px;
  color: #64748b;
  text-align: center;
}
.documents-empty-state h3 {
  margin: 0;
  color: #0f172a;
}
.documents-empty-state p {
  margin: 0;
  max-width: 420px;
}
.documents-empty-icon {
  width: 48px;
  height: 48px;
  color: #94a3b8;
}
</style>







