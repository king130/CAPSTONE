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

export type UserRole = 'student' | 'company' | 'school' | 'admin'

export interface RegisterPayload {
  fullName: string
  email: string
  password: string
  role: UserRole
  profile: Record<string, unknown>
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
  profile: Record<string, unknown>
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
  return snapshot.docs.length > 0 ? snapshot.docs[0].id : null
}

export async function registerUser(payload: RegisterPayload) {
  const { email, password, fullName, role, profile, subscriptionPlan, billingCycle, schoolSubscriptionCode } = payload

  if (role === 'student' && schoolSubscriptionCode) {
    const schoolId = await validateSchoolSubscription(schoolSubscriptionCode)
    if (!schoolId) {
      throw new Error('School subscription code is invalid or inactive.')
    }
  }

  const credential = await createUserWithEmailAndPassword(auth, email, password)
  await updateProfile(credential.user, { displayName: fullName })

  const userDoc: UserProfile = {
    uid: credential.user.uid,
    email,
    displayName: fullName,
    role,
    isTemporary: false,
    isActive: true,
    profile,
    createdAt: serverTimestamp(),
    updatedAt: serverTimestamp(),
  }

  if (role === 'school' || role === 'company') {
    userDoc.subscription = {
      plan: subscriptionPlan || 'free',
      billingCycle: billingCycle || 'monthly',
      status: role === 'school' ? 'pending' : 'pending',
      subscriptionCode: role === 'school' ? generateSubscriptionCode(fullName) : undefined,
    }
  }

  await setDoc(doc(db, 'users', credential.user.uid), userDoc)
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
