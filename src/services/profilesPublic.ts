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
  courses?: string[]
}

/** Ensure current user has a public profile (for discoverability in chat/contracts). Call when school/company dashboard loads. */
export async function ensurePublicProfile(
  uid: string,
  data: { displayName: string; role: string; orgName?: string; email?: string; courses?: string[] }
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
    courses: Array.isArray(data.courses) ? data.courses : null,
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
  const publicRef = collection(db, 'profiles_public')
  const publicQ = query(publicRef, where('role', '==', role))
  const publicSnapshot = await getDocs(publicQ)
  let publicProfiles = publicSnapshot.docs.map((d) => {
    const data = d.data() as PublicProfile
    return { ...data, uid: data.uid || d.id }
  })

  // Extra fallback: older docs may have inconsistent role casing; if the filtered query is empty, fetch all and filter locally.
  if (publicProfiles.length === 0) {
    try {
      const allSnapshot = await getDocs(publicRef)
      publicProfiles = allSnapshot.docs
        .map((d) => {
          const data = d.data() as PublicProfile
          return { ...data, uid: data.uid || d.id }
        })
        .filter((p) => String((p as { role?: unknown }).role || '').toLowerCase() === role)
    } catch {
      publicProfiles = []
    }
  }

  // Fallback: include accounts from users collection in case profiles_public is not yet backfilled.
  let userProfiles: PublicProfile[] = []
  try {
    const usersRef = collection(db, 'users')
    const usersQ = query(usersRef, where('role', '==', role))
    const usersSnapshot = await getDocs(usersQ)
    userProfiles = usersSnapshot.docs.map((d) => {
      const data = d.data() as {
        uid?: string
        email?: string
        displayName?: string
        profile?: Record<string, unknown>
      }
      const profile = data.profile || {}
      const courses = (profile.courses as string[] | undefined) || []
      const orgName = (
        role === 'school'
          ? (profile.institutionName as string | undefined)
          : (profile.companyName as string | undefined)
      ) || data.displayName

      return {
        uid: data.uid || d.id,
        role,
        displayName: data.displayName || orgName || data.email?.split('@')[0] || 'User',
        orgName: orgName || undefined,
        email: data.email || undefined,
        courses: Array.isArray(courses) ? courses : undefined,
      } as PublicProfile
    })
  } catch {
    // Most non-admin users cannot read other user docs due rules; ignore fallback if denied.
    userProfiles = []
  }

  const profiles = [...publicProfiles, ...userProfiles]
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
  return onSnapshot(
    q,
    (snapshot) => {
      const profiles = snapshot.docs.map((d) => {
        const data = d.data() as PublicProfile
        return { ...data, uid: data.uid || d.id }
      })
      callback(profiles)
    },
    (error) => {
      console.warn('subscribePublicProfiles error:', error)
      callback([])
    }
  )
}
