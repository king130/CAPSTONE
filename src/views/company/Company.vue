<script setup lang="ts">
import { ref, computed } from 'vue'
import Sidebar from '../../components/Sidebar.vue'
import CompanySettings from '../../components/CompanySettings.vue'
import FloatingChatWidget from '../../components/FloatingChatWidget.vue'
import { useAuthStore } from '@/stores/auth'
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
  CloudIcon
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
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

// TEMPORARY DATA: Sample internships list - replace with real data from backend
const internshipsList = ref([
  {
    id: 1,
    title: 'Software Engineer Intern',
    company: 'Innovate Corp.',
    location: 'New York, NY',
    match: 92,
    recommended: true,
    description: 'Join Our dynamic team to develop innovative software solutions. You\'ll work on cutting-edge projects and contribute the projects.',
    skills: ['Python', 'AWS', 'Machine Learning', 'Django'],
    logo: 'IC'
  },
  {
    id: 2,
    title: 'Data Analyst Intern',
    company: 'Innovate Corp.',
    location: 'New York, NY',
    match: 92,
    recommended: true,
    description: 'Join Our dynamic team to develop innovative software solutions. You\'ll work on cutting-edge projects and contribute the projects.',
    skills: ['Python', 'SQL', 'Machine Learning', 'Excel'],
    logo: 'IC'
  },
  {
    id: 3,
    title: 'UI/UX Designer Intern',
    company: 'Innovate Corp.',
    location: 'New York, NY',
    match: 92,
    recommended: true,
    description: 'Join Our dynamic team to develop innovative software solutions. You\'ll work on cutting-edge projects and contribute the projects.',
    skills: ['Figma', 'Prototyping', 'Design System'],
    logo: 'IC'
  },
  {
    id: 4,
    title: 'Business Development Intern',
    company: 'Innovate Corp.',
    location: 'New York, NY',
    match: 92,
    recommended: true,
    description: 'Join Our dynamic team to develop innovative software solutions. You\'ll work on cutting-edge projects and contribute the projects.',
    skills: ['Python', 'AWS', 'Machine Learning', 'Django'],
    logo: 'IC'
  }
])

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

// TEMPORARY DATA: Create internship form state - these are UI state variables
const showCreateForm = ref(false)
const currentFormStep = ref(1)
const formTitle = ref('')
const formDescription = ref('')
const formSkills = ref(['HTML', 'CSS', 'Javascript'])
const newSkillInput = ref('')
const slotsAvailable = ref('1')
const startDateForm = ref('')
const endDateForm = ref('')
const locationType = ref('Remote')

// TEMPORARY DATA: Additional form fields for step 2 - replace with real data from backend
const preferredCourse = ref('')
const minimumGPA = ref('')
const requiredSkills = ref('')
const yearLevel = ref('')
const additionalRequirements = ref('')

function openCreateForm() {
  showCreateForm.value = true
  currentView.value = 'create-internship'
  currentFormStep.value = 1
}

function closeCreateForm() {
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

function addSkill() {
  const skill = newSkillInput.value.trim()
  if (skill && !formSkills.value.includes(skill)) {
    formSkills.value.push(skill)
    newSkillInput.value = ''
  }
}

function removeFormSkill(skill: string) {
  formSkills.value = formSkills.value.filter(s => s !== skill)
}

function handleKeyPress(event: KeyboardEvent) {
  if (event.key === 'Enter') {
    event.preventDefault()
    addSkill()
  }
}

function saveDraft() {
  // Handle save draft logic
  console.log('Saving draft...')
}

function submitForApproval() {
  // Handle submit for approval logic
  console.log('Submitting for approval...')
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

// TEMPORARY DATA: Applications view filter state - these are UI state variables
const selectedInternship = ref('')
const searchStudent = ref('')

// TEMPORARY DATA: Sample applications list - replace with real data from backend
const applications = ref([
  {
    id: 1,
    studentName: 'Jane Doe',
    course: 'BS Computer Science',
    internJob: 'IT Support',
    skillsMatch: 85,
    applicationDate: '2024-09-01',
    status: 'New'
  },
  {
    id: 2,
    studentName: 'John Smith',
    course: 'BS Information Technology',
    internJob: 'Data Analyst',
    skillsMatch: 70,
    applicationDate: '2024-09-02',
    status: 'Reviewed'
  },
  {
    id: 3,
    studentName: 'Alice Johnson',
    course: 'BS Computer Engineering',
    internJob: 'UI/UX Designer',
    skillsMatch: 92,
    applicationDate: '2024-09-03',
    status: 'Accepted'
  },
  {
    id: 4,
    studentName: 'Michael Brown',
    course: 'BS Computer Science',
    internJob: 'Web Developer',
    skillsMatch: 78,
    applicationDate: '2024-09-04',
    status: 'Declined'
  },
  {
    id: 5,
    studentName: 'Sarah Davis',
    course: 'BS Information Technology',
    internJob: 'Cloud Engineer',
    skillsMatch: 88,
    applicationDate: '2024-09-05',
    status: 'Interview'
  },
  {
    id: 6,
    studentName: 'David Wilson',
    course: 'BS Computer Engineering',
    internJob: 'Q/A',
    skillsMatch: 75,
    applicationDate: '2024-09-06',
    status: 'New'
  }
])

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

// TEMPORARY DATA: Sample company profile data - replace with real data from backend
const companyProfile = computed(() => ({
  name: companyName.value,
  industry: 'Information Technology',
  email: authStore.user?.email || 'contact@company.com',
  phone: '+63 (046) 123-4567',
  address: 'Cavite Technology Park, Dasmarinas, Cavite',
  website: 'www.techcorp.com',
  businessType: 'Software Development',
  founded: '2010',
  companySize: '500-1000 employees',
  primaryDepartments: 'Engineering, Marketing',
  preferredBackground: 'IT, Business, Communications',
  internshipDuration: '3-6 months',
  availableSlots: '15 positions',
  description: 'TechCorp Solutions is a leading technology company specializing in innovative software development and digital transformation services. We provide comprehensive internship programs that offer hands-on experience with cutting-edge technologies.',
  totalInternsHosted: 127,
  averageDuration: '6 months',
  completionRate: '94%',
  studentSatisfaction: 4.8,
  profileCompletion: 85
}))

// TEMPORARY DATA: Profile edit mode state - UI state variable
const profileEditMode = ref(false)

function editProfile() {
  profileEditMode.value = true
}

function saveProfile() {
  profileEditMode.value = false
  // TODO: Save profile changes
  console.log('Profile saved')
}

function cancelEdit() {
  profileEditMode.value = false
  // TODO: Reset changes
}

function editDescription() {
  // TODO: Open description editor
  console.log('Edit description')
}

// TEMPORARY DATA: Time tracking date state - UI state variable
const timeTrackingDate = ref('Today - Jan 17, 2026')
const timeTrackingStatus = ref('All')
const timeTrackingInterns = ref('All interns')
const timeTrackingView = ref('daily') // 'daily' or 'weekly'

const timeTrackingRecords = ref([
  {
    id: 1,
    name: 'Sarah Chen',
    internId: 'ID: 2024-001',
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
    date: 'Oct 24, 2024',
    timeIn: '7:50 AM',
    timeOut: '5:20 PM',
    totalHours: '9.50 hrs',
    status: 'PRESENT',
    statusColor: 'present'
  }
])

function viewDailyLog() {
  timeTrackingView.value = 'daily'
}

function viewWeeklySummary() {
  timeTrackingView.value = 'weekly'
}

function viewInternDetails(internId: number) {
  console.log('View details for intern:', internId)
}

// Evaluation view data
const evaluationMonth = ref(new Date(2026, 0, 1)) // January 2026
const evaluationsThisWeek = ref(5)
const pendingMyAction = ref(2)
const totalEvaluationsCompleted = ref(42)
const showNewEvaluationModal = ref(false)

// New evaluation form data
const newEvaluationForm = ref({
  student: '',
  supervisor: '',
  evaluationType: '',
  dueDate: '17/01/2026'
})

const studentOptions = ref([
  'Alice Johnson',
  'Miguel Santos',
  'Anna Reyes',
  'Carlos Torres',
  'Maria Garcia',
  'James Rivera',
  'Lisa Mendoza',
  'Roberto Cruz',
  'Patricia Lim',
  'Daniel Flores'
])

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

const pendingEvaluations = ref([
  {
    id: 1,
    studentName: 'Alice Johnson',
    supervisor: 'Mr. Smith',
    type: 'Midterm',
    dueDate: '17/01/26',
    status: 'PENDING',
    statusColor: 'pending'
  },
  {
    id: 2,
    studentName: 'Alice Johnson',
    supervisor: 'Mr. Smith',
    type: 'Midterm',
    dueDate: '17/01/26',
    status: 'OVERDUE',
    statusColor: 'overdue'
  },
  {
    id: 3,
    studentName: 'Alice Johnson',
    supervisor: 'Mr. Smith',
    type: 'Midterm',
    dueDate: '17/01/26',
    status: 'PENDING',
    statusColor: 'pending'
  },
  {
    id: 4,
    studentName: 'Alice Johnson',
    supervisor: 'Mr. Smith',
    type: 'Midterm',
    dueDate: '17/01/26',
    status: 'COMPLETED',
    statusColor: 'completed'
  }
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
    student: '',
    supervisor: '',
    evaluationType: '',
    dueDate: '17/01/2026'
  }
}

function scheduleEvaluation() {
  // TODO: Handle form submission
  console.log('Schedule evaluation:', newEvaluationForm.value)
  closeNewEvaluationModal()
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
            <!-- Left Column: Filters -->
            <aside class="filters-column">
              <h2 class="filters-title">Filters</h2>
              
              <div class="filter-group">
                <label class="filter-label">Location</label>
                <input
                  type="text"
                  v-model="locationFilter"
                  placeholder="e.g. New York, Remote."
                  class="filter-input"
                />
              </div>

              <div class="filter-group">
                <label class="filter-label">Skills</label>
                <div class="selected-skills" v-if="selectedSkills.length > 0">
                  <span
                    v-for="skill in selectedSkills"
                    :key="skill"
                    class="skill-tag"
                  >
                    {{ skill }}
                    <button @click="removeSkill(skill)" class="skill-remove"><XMarkIcon class="icon-size-sm" /></button>
                  </span>
                </div>
                <p v-else class="no-skills">No skills selected</p>
              </div>

              <div class="filter-group">
                <label class="filter-label">Availability</label>
                <div class="date-inputs">
                  <input
                    type="date"
                    v-model="startDate"
                    class="filter-input date-input"
                  />
                  <input
                    type="date"
                    v-model="endDate"
                    class="filter-input date-input"
                  />
                </div>
              </div>

              <div class="filter-group">
                <label class="filter-label">Company Type</label>
                <div class="checkbox-group">
                  <label v-for="type in ['Startup', 'Enterprise', 'Non-profit', 'Government']" :key="type">
                    <input
                      type="checkbox"
                      :value="type"
                      v-model="companyTypes"
                    />
                    <span>{{ type }}</span>
                  </label>
                </div>
              </div>

              <div class="filter-group">
                <label class="filter-label">Duration</label>
                <input
                  type="text"
                  v-model="duration"
                  placeholder="4-6 Months"
                  class="filter-input"
                />
              </div>

              <div class="filter-buttons">
                <button class="btn-apply-filters">Apply filters</button>
                <button class="btn-clear-all" @click="clearAllFilters">Clear All</button>
              </div>
            </aside>

            <!-- Right Column: Available Internships -->
            <div class="internships-column">
              <div class="internships-header">
                <h2 class="internships-title">Available Internships</h2>
                <div class="search-section">
                  <div class="search-wrapper">
                    <span class="search-icon">
                      <MagnifyingGlassIcon class="icon-size" />
                    </span>
                    <input
                      type="text"
                      v-model="searchQuery"
                      placeholder="Search Internship by keyword..."
                      class="search-input"
                    />
                  </div>
                  <button class="btn-post-internship" @click="openCreateForm">Post New Internship</button>
                </div>
              </div>

              <div class="internships-grid">
                <div
                  v-for="internship in internshipsList"
                  :key="internship.id"
                  class="internship-card"
                >
                  <div class="card-header">
                    <div class="company-logo">{{ internship.logo }}</div>
                    <div class="card-info">
                      <h4>{{ internship.title }}</h4>
                      <p class="company-name">{{ internship.company }}</p>
                      <p class="location">
                        <MapPinIcon class="icon-size-sm" /> {{ internship.location }}
                      </p>
                    </div>
                    <div class="match-badge">{{ internship.match }}% Match</div>
                  </div>
                  <div v-if="internship.recommended" class="recommended-badge">
                    <span class="star-icon">
                      <StarIcon class="icon-size-sm" />
                    </span>
                    Recommended for You
                  </div>
                  <p class="card-description">{{ internship.description }}</p>
                  <div class="skills-tags">
                    <span
                      v-for="skill in internship.skills"
                      :key="skill"
                      class="skill-tag-card"
                    >
                      {{ skill }}
                    </span>
                  </div>
                  <div class="card-actions">
                    <button class="btn-quick-apply">Quick Apply</button>
                    <button class="btn-view-details">View Details</button>
                  </div>
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
          <div class="applications-container">
            <div class="applications-header">
              <h2 class="applications-title">All Applications</h2>
              <div class="applications-filters">
                <div class="filter-group">
                  <label class="filter-label">Filter by Internship:</label>
                  <select v-model="selectedInternship" class="filter-select">
                    <option value="">All Internships</option>
                    <option value="it-support">IT Support</option>
                    <option value="data-analyst">Data Analyst</option>
                    <option value="ui-ux">UI/UX Designer</option>
                    <option value="web-dev">Web Developer</option>
                  </select>
                </div>
                <div class="search-group">
                  <span class="search-icon">
                    <MagnifyingGlassIcon class="icon-size" />
                  </span>
                  <input
                    type="text"
                    v-model="searchStudent"
                    placeholder="Search Student..."
                    class="search-input-applications"
                  />
                </div>
              </div>
            </div>

            <div class="applications-table-container">
              <table class="applications-table">
                <thead>
                  <tr>
                    <th>STUDENT NAME</th>
                    <th>COURSE/PROGRAM</th>
                    <th>INTERN JOBS</th>
                    <th>SKILLS MATCH %</th>
                    <th>RESUME</th>
                    <th>APPLICATION DATE</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="app in applications" :key="app.id">
                    <td>
                      <a href="#" class="student-link">{{ app.studentName }}</a>
                    </td>
                    <td>{{ app.course }}</td>
                    <td>{{ app.internJob }}</td>
                    <td>
                      <div class="match-cell">
                        <span class="match-percentage">{{ app.skillsMatch }}%</span>
                        <div class="match-bar-wrapper">
                          <div
                            class="match-bar"
                            :style="{ width: app.skillsMatch + '%' }"
                          ></div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <button class="download-btn" title="Download Resume">
                        <ArrowDownTrayIcon class="icon-size" />
                      </button>
                    </td>
                    <td>{{ app.applicationDate }}</td>
                    <td>
                      <span
                        class="status-badge-app"
                        :class="app.status.toLowerCase()"
                      >
                        {{ app.status }}
                      </span>
                    </td>
                    <td>
                      <div class="action-icons">
                        <button class="action-icon" title="View Details">
                          <EyeIcon class="icon-size" />
                        </button>
                        <button
                          class="action-icon"
                          :class="{ disabled: app.status === 'Accepted' }"
                          title="Accept"
                        >
                          <CheckIcon class="icon-size" />
                        </button>
                        <button
                          class="action-icon"
                          :class="{ disabled: app.status === 'Accepted' }"
                          title="Decline"
                        >
                          <XMarkIcon class="icon-size" />
                        </button>
                      </div>
                    </td>
                  </tr>
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
            <h1>Post New Internship</h1>
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
              <div class="form-card">
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
                  <select class="form-select">
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
                      <input type="radio" name="internshipType" value="on-site" />
                      <span class="radio-label">On-site</span>
                    </label>
                    <label class="radio-option">
                      <input type="radio" name="internshipType" value="remote" />
                      <span class="radio-label">Remote</span>
                    </label>
                    <label class="radio-option">
                      <input type="radio" name="internshipType" value="hybrid" />
                      <span class="radio-label">Hybrid</span>
                    </label>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Location <span class="required">*</span></label>
                  <input
                    type="text"
                    placeholder="e.g. Bacoor, Cavite, PH (for on-site/hybrid)"
                    class="form-input"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Duration <span class="required">*</span></label>
                  <input
                    type="number"
                    placeholder="12"
                    class="form-input duration-input"
                  />
                  <span class="input-suffix">weeks</span>
                </div>

                <div class="form-group">
                  <label class="form-label">Required Internship Hours / Week <span class="required">*</span></label>
                  <input
                    type="number"
                    placeholder="20"
                    class="form-input"
                  />
                </div>

                <div class="form-actions">
                  <button class="btn-next-section" @click="nextFormStep" v-if="currentFormStep === 1">
                    Next Section
                    <span class="arrow-icon">→</span>
                  </button>
                </div>
              </div>

              <!-- Qualifications & Responsibilities Section (Step 2) -->
              <div class="form-card" v-if="currentFormStep === 2">
                <div class="form-card-header">
                  <img src="/icons/icon-student.png" alt="Qualifications" class="section-icon" />
                  <h2 class="section-title">Qualifications & Responsibilities</h2>
                  <span class="status-badge in-progress">In Progress</span>
                </div>
                <p class="section-description">Define the requirements and responsibilities for this internship position.</p>
                
                <div class="form-row">
                  <div class="form-group half-width">
                    <label class="form-label">Preferred Course/Program <span class="required">*</span></label>
                    <input
                      type="text"
                      v-model="preferredCourse"
                      placeholder="e.g. Computer Science, Information Technology"
                      class="form-input"
                    />
                  </div>

                  <div class="form-group half-width">
                    <label class="form-label">Minimum GPA</label>
                    <input
                      type="number"
                      v-model="minimumGPA"
                      placeholder="e.g. 3.0"
                      step="0.1"
                      min="1.0"
                      max="4.0"
                      class="form-input"
                    />
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Required Skills & Technologies <span class="required">*</span></label>
                  <textarea
                    v-model="requiredSkills"
                    placeholder="List the essential skills, programming languages, tools, or technologies required for this position"
                    class="form-textarea"
                    rows="4"
                  ></textarea>
                </div>

                <div class="form-row">
                  <div class="form-group half-width">
                    <label class="form-label">Year Level <span class="required">*</span></label>
                    <select v-model="yearLevel" class="form-select">
                      <option value="">Select Year Level</option>
                      <option value="3rd-year">3rd Year</option>
                      <option value="4th-year">4th Year</option>
                      <option value="graduate">Graduate</option>
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
                  <img src="/icons/icon-internship.png" alt="Tasks" class="section-icon" />
                  <h2 class="section-title">Tasks & Responsibilities</h2>
                  <span class="status-badge in-progress">In Progress</span>
                </div>
                <p class="section-description">Outline the specific tasks and responsibilities for this internship.</p>
                
                <div class="form-group">
                  <label class="form-label">Primary Responsibilities <span class="required">*</span></label>
                  <textarea
                    placeholder="Describe the main tasks and responsibilities the intern will handle"
                    class="form-textarea"
                    rows="5"
                  ></textarea>
                </div>

                <div class="form-group">
                  <label class="form-label">Learning Objectives</label>
                  <textarea
                    placeholder="What will the intern learn from this experience? What skills will they develop?"
                    class="form-textarea"
                    rows="4"
                  ></textarea>
                </div>

                <div class="form-group">
                  <label class="form-label">Projects & Deliverables</label>
                  <textarea
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
                    placeholder="Special instructions for applicants (e.g., required documents, portfolio requirements)"
                    class="form-textarea"
                    rows="4"
                  ></textarea>
                </div>

                <div class="form-group">
                  <label class="form-label">Contact Information</label>
                  <textarea
                    placeholder="Contact details for questions about this internship"
                    class="form-textarea"
                    rows="3"
                  ></textarea>
                </div>

                <div class="form-actions">
                  <button class="btn-prev-section" @click="prevFormStep">
                    <span class="arrow-icon">←</span>
                    Previous Section
                  </button>
                  <button class="btn-save-draft" @click="saveDraft" disabled title="Coming soon">
                    <img src="/icons/icon-save.png" alt="Save" class="btn-icon" />
                    Save Draft
                  </button>
                  <button class="btn-next-section" @click="submitForApproval" disabled title="Coming soon">
                    Submit for Approval
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
                <h3 class="progress-title">Your Internship Post</h3>
                
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

                <button class="btn-save-draft">
                  <img src="/icons/icon-save.png" alt="Save" class="btn-icon" />
                  Save as Draft
                </button>
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

      <!-- Time Tracking View -->
      <div v-if="currentView === 'time-tracking'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-timetracking.png" alt="Time Tracking" class="header-icon-img" />
            <h1>Time Tracking</h1>
          </div>
          <div class="header-right">
            <div class="notification-wrapper">
              <BellIcon class="notification-icon-bell" />
            </div>
            <div class="avatar" @click="handleNavChange('profile')" title="View Profile">{{ userInitials }}</div>
          </div>
        </header>
        <main class="main-content">
          <!-- Time Tracking Controls -->
          <div class="time-tracking-controls">
            <div class="tracking-filters">
              <div class="filter-item">
                <span class="filter-icon">
                  <CalendarIcon class="icon-size" />
                </span>
                <span class="filter-text">{{ timeTrackingDate }}</span>
              </div>
              <div class="filter-item">
                <span class="filter-label">Status:</span>
                <select v-model="timeTrackingStatus" class="filter-select">
                  <option value="All">All</option>
                  <option value="Present">Present</option>
                  <option value="Late">Late</option>
                  <option value="Ongoing">Ongoing</option>
                  <option value="Incomplete">Incomplete</option>
                </select>
              </div>
              <div class="filter-item">
                <span class="filter-icon">
                  <MagnifyingGlassIcon class="icon-size" />
                </span>
                <select v-model="timeTrackingInterns" class="filter-select">
                  <option value="All interns">All interns</option>
                  <option value="Present">Present Only</option>
                  <option value="Late">Late Only</option>
                </select>
              </div>
            </div>
            <div class="tracking-actions">
              <button 
                class="tracking-btn"
                :class="{ active: timeTrackingView === 'daily' }"
                @click="viewDailyLog"
              >
                <span class="btn-icon">
                  <ChartBarIcon class="icon-size" />
                </span>
                View Daily Log
              </button>
              <button 
                class="tracking-btn"
                :class="{ active: timeTrackingView === 'weekly' }"
                @click="viewWeeklySummary"
              >
                <span class="btn-icon">
                  <ChartBarSquareIcon class="icon-size" />
                </span>
                View Weekly Summary
              </button>
            </div>
          </div>

          <!-- Time Tracking Table -->
          <div class="time-tracking-table-container">
            <table class="time-tracking-table">
              <thead>
                <tr>
                  <th class="th-intern">INTERN NAME / ID</th>
                  <th class="th-date">DATE</th>
                  <th class="th-time">TIME IN</th>
                  <th class="th-time">TIME OUT</th>
                  <th class="th-hours">TOTAL HOURS</th>
                  <th class="th-status">STATUS</th>
                  <th class="th-actions">ACTIONS</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="record in timeTrackingRecords" :key="record.id" class="tracking-row">
                  <td class="td-intern">
                    <div class="intern-info">
                      <div class="intern-name">{{ record.name }}</div>
                      <div class="intern-id">{{ record.internId }}</div>
                    </div>
                  </td>
                  <td class="td-date">{{ record.date }}</td>
                  <td class="td-time">
                    <div class="time-entry" :class="{ 'has-indicator': record.status === 'ONGOING' }">
                      {{ record.timeIn }}
                    </div>
                  </td>
                  <td class="td-time">{{ record.timeOut }}</td>
                  <td class="td-hours">{{ record.totalHours }}</td>
                  <td class="td-status">
                    <span class="status-badge-tracking" :class="record.statusColor">
                      {{ record.status }}
                    </span>
                  </td>
                  <td class="td-actions">
                    <button 
                      class="action-btn-tracking" 
                      @click="viewInternDetails(record.id)"
                      disabled
                      title="View Details"
                    >
                      <EyeIcon class="icon-size" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
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
                  <tr v-for="evaluation in pendingEvaluations" :key="evaluation.id" class="evaluation-row">
                    <td class="td-student">{{ evaluation.studentName }}</td>
                    <td class="td-supervisor">{{ evaluation.supervisor }}</td>
                    <td class="td-type">{{ evaluation.type }}</td>
                    <td class="td-date">{{ evaluation.dueDate }}</td>
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
                  <select v-model="newEvaluationForm.student" class="form-select">
                    <option value="">---Choose a student---</option>
                    <option v-for="student in studentOptions" :key="student" :value="student">
                      {{ student }}
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
                      type="text" 
                      class="form-input date-input"
                      placeholder="DD/MM/YYYY"
                    />
                    <span class="date-icon">
                      <CalendarIcon class="icon-size" />
                    </span>
                  </div>
                </div>
              </div>

              <div class="modal-footer">
                <button class="btn-schedule-evaluation" @click="scheduleEvaluation" disabled title="Coming soon">
                  Schedule Evaluation
                </button>
              </div>
            </div>
          </div>
        </Teleport>
      </div>

      <!-- Settings View -->
      <CompanySettings v-if="currentView === 'settings'" />

    </div> <!-- End Main Wrapper -->

    <!-- Floating Chat Widget -->
    <FloatingChatWidget userType="company" />
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
  grid-template-columns: 320px 1fr;
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

.checkbox-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
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

.internships-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.internship-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.2s;
}

.internship-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  transform: translateY(-2px);
}

.card-header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 12px;
}

.company-logo {
  width: 48px;
  height: 48px;
  background: #2563eb;
  color: #fff;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 16px;
  flex-shrink: 0;
}

.card-info {
  flex: 1;
}

.card-info h4 {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.card-info .company-name {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 4px 0;
}

.card-info .location {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 4px;
}

.match-badge {
  padding: 4px 10px;
  background: #10b981;
  color: #fff;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.recommended-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #d1fae5;
  color: #065f46;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 12px;
}

.star-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #fbbf24;
}

.card-description {
  font-size: 14px;
  color: #374151;
  line-height: 1.5;
  margin: 12px 0;
}

.skills-tags {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin: 16px 0;
}

.skill-tag-card {
  padding: 6px 12px;
  background: #eff6ff;
  color: #2563eb;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}

.card-actions {
  display: flex;
  gap: 12px;
  margin-top: 16px;
}

.btn-quick-apply {
  flex: 1;
  padding: 10px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-quick-apply:hover {
  background: #1d4ed8;
}

.btn-view-details {
  flex: 1;
  padding: 10px;
  background: #fff;
  color: #2563eb;
  border: 1px solid #2563eb;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-view-details:hover {
  background: #eff6ff;
}

@media (max-width: 1200px) {
  .internships-layout {
    grid-template-columns: 1fr;
  }

  .filters-column {
    order: 2;
  }

  .internships-column {
    order: 1;
  }

  .internships-grid {
    grid-template-columns: 1fr;
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

/* Applications View Styles */
.applications-container {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.applications-header {
  margin-bottom: 24px;
}

.applications-title {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 20px 0;
}

.applications-filters {
  display: flex;
  gap: 20px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.filter-label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.filter-select {
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
  color: #111827;
  cursor: pointer;
  min-width: 180px;
  transition: all 0.2s;
}

.filter-select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.search-group {
  position: relative;
  flex: 1;
  min-width: 250px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 18px;
  color: #6b7280;
}

.search-input-applications {
  width: 100%;
  padding: 10px 12px 10px 40px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
  transition: all 0.2s;
  box-sizing: border-box;
}

.search-input-applications:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.applications-table-container {
  overflow-x: auto;
  margin-top: 24px;
}

.applications-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.applications-table thead {
  background: #1e3a8a;
  color: #fff;
}

.applications-table th {
  padding: 14px 12px;
  text-align: left;
  font-weight: 700;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
}

.applications-table tbody tr {
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.2s;
}

.applications-table tbody tr:hover {
  background: #f9fafb;
}

.applications-table td {
  padding: 14px 12px;
  color: #374151;
  vertical-align: middle;
}

.student-link {
  color: #2563eb;
  text-decoration: underline;
  font-weight: 500;
  transition: color 0.2s;
}

.student-link:hover {
  color: #1d4ed8;
}

.match-cell {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 120px;
}

.match-percentage {
  font-weight: 600;
  color: #111827;
  min-width: 40px;
}

.match-bar-wrapper {
  flex: 1;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
  min-width: 60px;
}

.match-bar {
  height: 100%;
  background: #2563eb;
  border-radius: 4px;
  transition: width 0.3s;
}

.download-btn {
  background: none;
  border: none;
  color: #2563eb;
  font-size: 20px;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: background 0.2s;
}

.download-btn:hover {
  background: #eff6ff;
}

.status-badge-app {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}

.status-badge-app.new {
  background: #dbeafe;
  color: #1e40af;
}

.status-badge-app.reviewed {
  background: #fef3c7;
  color: #92400e;
}

.status-badge-app.accepted {
  background: #d1fae5;
  color: #065f46;
}

.status-badge-app.declined {
  background: #fee2e2;
  color: #991b1b;
}

.status-badge-app.interview {
  background: #cffafe;
  color: #155e75;
}

.action-icons {
  display: flex;
  gap: 8px;
  align-items: center;
}

.action-icon {
  background: none;
  border: none;
  font-size: 18px;
  cursor: pointer;
  padding: 6px;
  border-radius: 4px;
  transition: all 0.2s;
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
}

.action-icon:hover:not(.disabled) {
  background: #eff6ff;
  transform: scale(1.1);
}

.action-icon.disabled {
  color: #9ca3af;
  cursor: not-allowed;
  opacity: 0.5;
}

@media (max-width: 1024px) {
  .applications-table {
    font-size: 12px;
  }

  .applications-table th,
  .applications-table td {
    padding: 10px 8px;
  }

  .match-cell {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
  }

  .match-bar-wrapper {
    width: 100%;
  }
}

@media (max-width: 768px) {
  .applications-filters {
    flex-direction: column;
    align-items: stretch;
  }

  .filter-select,
  .search-group {
    width: 100%;
    min-width: unset;
  }

  .applications-table-container {
    overflow-x: scroll;
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

/* Progress Column - Remove sticky behavior */
.progress-column {
  position: relative;
}

.progress-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
</style>


