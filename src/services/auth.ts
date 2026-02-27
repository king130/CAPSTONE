import {
  createUserWithEmailAndPassword,
  signInWithEmailAndPassword,
  signOut,
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

  // Validate school subscription code if provided (for role selection later)
  if (role === 'student' && schoolSubscriptionCode) {
    const schoolId = await validateSchoolSubscription(schoolSubscriptionCode)
    if (!schoolId) {
      throw new Error('School subscription code is invalid or inactive.')
    }
  }

  // Create Firebase Auth user
  const credential = await createUserWithEmailAndPassword(auth, email, password)
  await updateProfile(credential.user, { displayName: fullName })

  // Create Firestore profile - NO ROLE by default (guest)
  const userDoc: UserProfile = {
    uid: credential.user.uid,
    email,
    displayName: fullName,
    role: role || null, // Guest if no role provided
    isTemporary: false,
    isActive: true,
    profileSetupComplete: role === 'student' || role === 'school' || role === 'company', // Complete when role is set
    profile: profile || {},
    createdAt: serverTimestamp(),
    updatedAt: serverTimestamp(),
  }

  // Only add subscription for school/company accounts
  if (role === 'school' || role === 'company') {
    userDoc.subscription = {
      plan: subscriptionPlan || 'free',
      billingCycle: billingCycle || 'monthly',
      status: role === 'school' ? 'pending' : 'pending',
      subscriptionCode: role === 'school' ? generateSubscriptionCode(fullName) : undefined,
    }
    userDoc.profileSetupComplete = true // Has role, so setup is complete
  }

  await setDoc(doc(db, 'users', credential.user.uid), userDoc)

  if (role === 'school' || role === 'company') {
    const p = profile as Record<string, unknown>
    const orgName = (role === 'school' ? p?.institutionName : p?.companyName) as string | undefined
    await ensurePublicProfile(credential.user.uid, {
      displayName: fullName,
      role,
      orgName: orgName || fullName,
      email,
    }).catch(() => {})
  }

  return userDoc
}

export async function loginUser(email: string, password: string) {
  const credential = await signInWithEmailAndPassword(auth, email, password)
  return credential.user
}

export async function logoutUser() {
  await signOut(auth)
}

export async function fetchUserProfile(uid: string) {
  const snapshot = await getDoc(doc(db, 'users', uid))
  return snapshot.exists() ? (snapshot.data() as UserProfile) : null
}

export function subscribeToUserProfile(uid: string, callback: (profile: UserProfile | null) => void) {
  return onSnapshot(doc(db, 'users', uid), (snapshot) => {
    callback(snapshot.exists() ? (snapshot.data() as UserProfile) : null)
  })
}

export function subscribeToAuthState(callback: (user: User | null) => void) {
  return onAuthStateChanged(auth, callback)
}
