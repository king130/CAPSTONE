export interface SchoolProgramRecord {
  id: string
  schoolId: string
  schoolName: string
  programs: Record<string, number>
  createdAt?: unknown
  updatedAt?: unknown
}

export interface ProgramOption {
  name: string
  shortName: string
  hours: number
  schoolName: string
}

/** Replace with GET /api/school-programs when implemented in Laravel. */
export function subscribeAllSchoolPrograms(callback: (programs: ProgramOption[]) => void): () => void {
  callback([])
  return () => {}
}

export async function getSchoolPrograms(_schoolId: string): Promise<SchoolProgramRecord | null> {
  return null
}

export async function saveSchoolPrograms(
  _schoolId: string,
  _schoolName: string,
  _programs: Record<string, number>
): Promise<void> {
  throw new Error('School programs API not implemented yet.')
}

export async function getAllAvailablePrograms(): Promise<string[]> {
  return []
}
