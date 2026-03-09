import {
  createUserWithEmailAndPassword,
  deleteUser,
  signInWithEmailAndPassword,
  signOut,
  updatePassword,
  updateProfile,
  onAuthStateChanged,
} from 'firebase/auth'
import type { User } from 'firebase/auth'
import {
  collection,
  doc,
  getDoc,
  getDocs,
  onSnapshot,
  query,
  serverTimestamp,
  setDoc,
  where,
} from 'firebase/firestore'
import { auth, db } from './firebase'
import { ensurePublicProfile } from './profilesPublic'

export type UserRole = 'student' | 'company' | 'school' | 'admin' | 'guest' | null

export interface RegisterPayload {
  fullName: string
  email: string
  password: string
  role?: UserRole
  profile?: Record<string, unknown>
  subscriptionPlan?: string
  billingCycle?: string
  schoolSubscriptionCode?: string
}

export interface UserProfile {
  uid: string
  email: string
  displayName: string
  role: UserRole
  isTemporary?: boolean
  isActive?: boolean
  profileSetupComplete?: boolean
  profile?: Record<string, unknown>
  mustChangePassword?: boolean
  subscription?: {
    plan: string
    billingCycle: string
    status: 'active' | 'pending' | 'inactive'
    subscriptionCode?: string
  }
  createdAt?: unknown
  updatedAt?: unknown
}

function generateSubscriptionCode(name: string) {
  const initials = name
    .split(' ')
    .filter(Boolean)
    .slice(0, 3)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
  const suffix = `${Date.now()}`.slice(-4)
  return `${initials}-${suffix}`
}

export async function validateSchoolSubscription(code: string) {
  const schoolsRef = collection(db, 'users')
  const q = query(
    schoolsRef,
    where('role', '==', 'school'),
    where('subscription.subscriptionCode', '==', code),
    where('subscription.status', '==', 'active'),
  )
  const snapshot = await getDocs(q)
  return snapshot.docs.length > 0 ? snapshot.docs[0]!.id : null
}

export async function registerUser(payload: RegisterPayload) {
  const { email, password, fullName, role = null, profile = {}, subscriptionPlan, billingCycle, schoolSubscriptionCode } = payload
  const normalizedEmail = email.trim().toLowerCase()

  // Validate school subscription code if explicitly provided for student registration.
  if (role === 'student' && schoolSubscriptionCode) {
    const schoolId = await validateSchoolSubscription(schoolSubscriptionCode)
    if (!schoolId) {
      throw new Error('School subscription code is invalid or inactive.')
    }
  }

  // Create Firebase Auth user
  const credential = await createUserWithEmailAndPassword(auth, normalizedEmail, password)
  const preApprovedStudent = await findPreApprovedStudent(normalizedEmail)
  const defaultPassword = preApprovedStudent?.defaultPassword?.trim()
  const hasPreApprovedEntry = !!preApprovedStudent
  const isPreApprovedStudent = !!defaultPassword && defaultPassword === password

  if (hasPreApprovedEntry && !isPreApprovedStudent) {
    await deleteUser(credential.user)
    throw new Error('Use the school-provided default password for your first registration.')
  }

  const resolvedRole: UserRole = isPreApprovedStudent ? 'student' : role
  const resolvedProfile: Record<string, unknown> = { ...profile }
  if (isPreApprovedStudent) {
    resolvedProfile.schoolId = preApprovedStudent?.schoolId || ''
    resolvedProfile.schoolName = preApprovedStudent?.schoolName || ''
    resolvedProfile.course = preApprovedStudent?.course || ''
    resolvedProfile.yearLevel = preApprovedStudent?.yearLevel || ''
  }

  const resolvedDisplayName = (preApprovedStudent?.studentName || fullName).trim() || fullName
  await updateProfile(credential.user, { displayName: resolvedDisplayName })

  // Create Firestore profile - NO ROLE by default (guest)
  const userDoc: UserProfile = {
    uid: credential.user.uid,
    email: normalizedEmail,
    displayName: resolvedDisplayName,
    role: resolvedRole || null, // Guest if no role provided
    isTemporary: false,
    isActive: true,
    profileSetupComplete: resolvedRole === 'student' || resolvedRole === 'school' || resolvedRole === 'company', // Complete when role is set
    profile: resolvedProfile,
    mustChangePassword: isPreApprovedStudent,
    createdAt: serverTimestamp(),
    updatedAt: serverTimestamp(),
  }

  // Only add subscription for school/company accounts
  if (resolvedRole === 'school' || resolvedRole === 'company') {
    userDoc.subscription = {
      plan: subscriptionPlan || 'free',
      billingCycle: billingCycle || 'monthly',
      status: resolvedRole === 'school' ? 'pending' : 'pending',
      subscriptionCode: resolvedRole === 'school' ? generateSubscriptionCode(fullName) : undefined,
    }
    userDoc.profileSetupComplete = true // Has role, so setup is complete
  }

  await setDoc(doc(db, 'users', credential.user.uid), userDoc)

  if (resolvedRole === 'school' || resolvedRole === 'company' || resolvedRole === 'student') {
    const p = resolvedProfile as Record<string, unknown>
    const orgName = (resolvedRole === 'school'
      ? p?.institutionName
      : resolvedRole === 'company'
        ? p?.companyName
        : p?.schoolName) as string | undefined
    await ensurePublicProfile(credential.user.uid, {
      displayName: resolvedDisplayName,
      role: resolvedRole,
      orgName: orgName || resolvedDisplayName,
      email: normalizedEmail,
    }).catch(() => {})
  }

  return userDoc
}

export async function loginUser(email: string, password: string) {
  const normalizedEmail = email.trim().toLowerCase()
  try {
    const credential = await signInWithEmailAndPassword(auth, normalizedEmail, password)
    return credential.user
  } catch (error) {
    const code = (error as { code?: string })?.code || ''
    if (code === 'auth/invalid-credential' || code === 'auth/user-not-found') {
      const provisionedUser = await tryProvisionStudentFromSchoolList(normalizedEmail, password)
      if (provisionedUser) return provisionedUser
    }
    throw error
  }
}

export async function logoutUser() {
  await signOut(auth)
}

export async function fetchUserProfile(uid: string) {
  const snapshot = await getDoc(doc(db, 'users', uid))
  return snapshot.exists() ? (snapshot.data() as UserProfile) : null
}

export async function createMissingUserProfile(firebaseUser: User) {
  const fallbackProfile: UserProfile = {
    uid: firebaseUser.uid,
    email: firebaseUser.email || '',
    displayName: firebaseUser.displayName || (firebaseUser.email?.split('@')[0] || 'User'),
    role: null,
    isTemporary: false,
    isActive: true,
    profileSetupComplete: false,
    profile: {},
    createdAt: serverTimestamp(),
    updatedAt: serverTimestamp(),
  }
  await setDoc(doc(db, 'users', firebaseUser.uid), fallbackProfile, { merge: true })
  return fallbackProfile
}

export async function updateCurrentUserPassword(newPassword: string) {
  if (!auth.currentUser) {
    throw new Error('No authenticated user.')
  }
  await updatePassword(auth.currentUser, newPassword)
  await setDoc(
    doc(db, 'users', auth.currentUser.uid),
    {
      mustChangePassword: false,
      updatedAt: serverTimestamp(),
    },
    { merge: true }
  )
}

async function findPreApprovedStudent(email: string) {
  const q = query(collection(db, 'school_students'), where('email', '==', email))
  const snapshot = await getDocs(q)
  if (snapshot.empty) return null
  const data = snapshot.docs[0]?.data() as {
    schoolId?: string
    studentName?: string
    course?: string
    yearLevel?: string
    defaultPassword?: string
  } | undefined
  if (!data) return null

  let schoolName = ''
  if (data.schoolId) {
    try {
      const schoolSnap = await getDoc(doc(db, 'users', data.schoolId))
      const schoolData = schoolSnap.data() as UserProfile | undefined
      const schoolProfile = schoolData?.profile as Record<string, unknown> | undefined
      schoolName = (schoolProfile?.institutionName as string) || schoolData?.displayName || ''
    } catch {
      schoolName = ''
    }
  }

  return {
    schoolId: data.schoolId || '',
    schoolName,
    studentName: data.studentName || '',
    course: data.course || '',
    yearLevel: data.yearLevel || '',
    defaultPassword: data.defaultPassword || '',
  }
}

async function tryProvisionStudentFromSchoolList(email: string, password: string): Promise<User | null> {
  let credential: { user: User } | null = null

  try {
    credential = await createUserWithEmailAndPassword(auth, email, password)
  } catch (error) {
    const code = (error as { code?: string })?.code || ''
    if (code === 'auth/email-already-in-use') return null
    throw error
  }

  const preApprovedStudent = await findPreApprovedStudent(email)
  const defaultPassword = preApprovedStudent?.defaultPassword?.trim()
  const isValidDefault = !!defaultPassword && defaultPassword === password
  if (!preApprovedStudent || !isValidDefault) {
    await deleteUser(credential.user)
    return null
  }

  const displayName =
    preApprovedStudent.studentName?.trim() ||
    email.split('@')[0]?.replace(/[._-]+/g, ' ') ||
    'Student'

  await updateProfile(credential.user, { displayName })

  const userDoc: UserProfile = {
    uid: credential.user.uid,
    email,
    displayName,
    role: 'student',
    isTemporary: false,
    isActive: true,
    profileSetupComplete: true,
    mustChangePassword: true,
    profile: {
      schoolId: preApprovedStudent.schoolId || '',
      schoolName: preApprovedStudent.schoolName || '',
      course: preApprovedStudent.course || '',
      yearLevel: preApprovedStudent.yearLevel || '',
    },
    createdAt: serverTimestamp(),
    updatedAt: serverTimestamp(),
  }

  await setDoc(doc(db, 'users', credential.user.uid), userDoc)

  await ensurePublicProfile(credential.user.uid, {
    displayName,
    role: 'student',
    orgName: preApprovedStudent.schoolName || undefined,
    email,
  }).catch(() => {})

  return credential.user
}

export function subscribeToUserProfile(uid: string, callback: (profile: UserProfile | null) => void) {
  return onSnapshot(doc(db, 'users', uid), (snapshot) => {
    callback(snapshot.exists() ? (snapshot.data() as UserProfile) : null)
  })
}

export function subscribeToAuthState(callback: (user: User | null) => void) {
  return onAuthStateChanged(auth, callback)
}
