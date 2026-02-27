import {
  collection,
  doc,
  getDoc,
  getDocs,
  onSnapshot,
  query,
  setDoc,
  where,
} from 'firebase/firestore'
import { db } from './firebase'

export interface PublicProfile {
  uid: string
  displayName: string
  role: 'school' | 'company' | 'student'
  orgName?: string
  email?: string
}

/** Ensure current user has a public profile (for discoverability in chat/contracts). Call when school/company dashboard loads. */
export async function ensurePublicProfile(
  uid: string,
  data: { displayName: string; role: string; orgName?: string; email?: string }
) {
  const role = data.role as 'school' | 'company' | 'student'
  if (!role || !['school', 'company', 'student'].includes(role)) return

  const ref = doc(db, 'profiles_public', uid)
  await setDoc(ref, {
    uid,
    displayName: data.displayName || 'User',
    role,
    orgName: data.orgName || null,
    email: data.email || null,
  }, { merge: true })
}

/** Get a single public profile by uid (for chat display names). */
export async function getPublicProfile(uid: string): Promise<PublicProfile | null> {
  const snap = await getDoc(doc(db, 'profiles_public', uid))
  if (!snap.exists()) return null
  const data = snap.data() as PublicProfile
  return { ...data, uid: data.uid || snap.id }
}

/** List public profiles by role (for chat partner selection, contract school selection). Deduplicates by uid and orgName. */
export async function listPublicProfiles(
  role: 'school' | 'company'
): Promise<PublicProfile[]> {
  const ref = collection(db, 'profiles_public')
  const q = query(ref, where('role', '==', role))
  const snapshot = await getDocs(q)
  const profiles = snapshot.docs.map((d) => {
    const data = d.data() as PublicProfile
    return { ...data, uid: data.uid || d.id }
  })
  const seenUids = new Set<string>()
  const seenOrgNames = new Set<string>()
  return profiles.filter((p) => {
    if (seenUids.has(p.uid)) return false
    seenUids.add(p.uid)
    const key = (p.orgName || p.displayName || p.uid).toLowerCase().trim()
    if (seenOrgNames.has(key)) return false
    seenOrgNames.add(key)
    return true
  })
}

/** Subscribe to public profiles by role. */
export function subscribePublicProfiles(
  role: 'school' | 'company',
  callback: (profiles: PublicProfile[]) => void
) {
  const ref = collection(db, 'profiles_public')
  const q = query(ref, where('role', '==', role))
  return onSnapshot(q, (snapshot) => {
    const profiles = snapshot.docs.map((d) => {
      const data = d.data() as PublicProfile
      return { ...data, uid: data.uid || d.id }
    })
    callback(profiles)
  })
}
