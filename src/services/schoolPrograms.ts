import {
  collection,
  doc,
  getDoc,
  getDocs,
  onSnapshot,
  query,
  setDoc,
  updateDoc,
  where,
  serverTimestamp,
} from 'firebase/firestore'
import { db } from './firebase'

export interface SchoolProgramRecord {
  id: string
  schoolId: string
  schoolName: string
  programs: Record<string, number> // program name -> required hours
  createdAt?: unknown
  updatedAt?: unknown
}

export interface ProgramOption {
  name: string
  shortName: string
  hours: number
  schoolName: string
}

// Subscribe to all school programs to get available courses
export function subscribeAllSchoolPrograms(callback: (programs: ProgramOption[]) => void) {
  const schoolProgramsRef = collection(db, 'schoolPrograms')
  
  return onSnapshot(
    schoolProgramsRef,
    (snapshot) => {
      const allPrograms: ProgramOption[] = []
      
      snapshot.docs.forEach((docSnap) => {
        const data = docSnap.data() as Omit<SchoolProgramRecord, 'id'>
        const schoolPrograms = data.programs || {}
        
        Object.entries(schoolPrograms).forEach(([programName, hours]) => {
          // Extract short name from full program name (e.g., "BS Information Technology (BSIT)" -> "BSIT")
          const shortNameMatch = programName.match(/\(([^)]+)\)$/)
          const shortName = shortNameMatch?.[1] || programName
          
          allPrograms.push({
            name: programName,
            shortName,
            hours,
            schoolName: data.schoolName
          })
        })
      })
      
      // Remove duplicates based on short name and keep the most common hours
      const uniquePrograms = new Map<string, ProgramOption>()
      allPrograms.forEach(program => {
        const existing = uniquePrograms.get(program.shortName)
        if (!existing || existing.hours < program.hours) {
          uniquePrograms.set(program.shortName, program)
        }
      })
      
      callback(Array.from(uniquePrograms.values()))
    },
    (err) => {
      console.warn('School programs subscription error:', err?.message || err)
      callback([])
    }
  )
}

// Get programs for a specific school
export async function getSchoolPrograms(schoolId: string): Promise<SchoolProgramRecord | null> {
  try {
    console.log('Loading school programs for:', schoolId)
    const docRef = doc(db, 'schoolPrograms', schoolId)
    const snapshot = await getDoc(docRef)
    
    if (snapshot.exists()) {
      const data = {
        id: snapshot.id,
        ...(snapshot.data() as Omit<SchoolProgramRecord, 'id'>)
      }
      console.log('School programs loaded:', data)
      return data
    } else {
      console.log('No school programs found for:', schoolId)
      return null
    }
  } catch (error) {
    console.error('Error getting school programs:', error)
    return null
  }
}

// Save school programs
export async function saveSchoolPrograms(
  schoolId: string, 
  schoolName: string, 
  programs: Record<string, number>
): Promise<void> {
  try {
    if (!schoolId) {
      throw new Error('School ID is required')
    }
    
    if (!schoolName) {
      throw new Error('School name is required')
    }
    
    if (!programs || typeof programs !== 'object') {
      throw new Error('Programs must be a valid object')
    }
    
    const docRef = doc(db, 'schoolPrograms', schoolId)
    const data = {
      schoolId,
      schoolName,
      programs,
      createdAt: serverTimestamp(),
      updatedAt: serverTimestamp()
    }
    
    await setDoc(docRef, data, { merge: true })
  } catch (error) {
    console.error('Error saving school programs:', error)
    throw error
  }
}

// Get unique program short names across all schools
export async function getAllAvailablePrograms(): Promise<string[]> {
  try {
    const schoolProgramsRef = collection(db, 'schoolPrograms')
    const snapshot = await getDocs(schoolProgramsRef)
    
    const allShortNames = new Set<string>()
    
    snapshot.docs.forEach((docSnap) => {
      const data = docSnap.data() as Omit<SchoolProgramRecord, 'id'>
      const programs = data.programs || {}
      
      Object.keys(programs).forEach(programName => {
        // Extract short name
        const shortNameMatch = programName.match(/\(([^)]+)\)$/)
        const shortName = shortNameMatch?.[1] || programName
        allShortNames.add(shortName)
      })
    })
    
    return Array.from(allShortNames).sort()
  } catch (error) {
    console.error('Error getting available programs:', error)
    return []
  }
}