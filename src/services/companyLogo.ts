import { doc, updateDoc } from 'firebase/firestore'
import { ref, uploadBytes, getDownloadURL } from 'firebase/storage'
import { db, storage } from './firebase'

export interface LogoData {
  type: 'predefined' | 'upload' | 'initials'
  logoId?: string
  logoUrl?: string
  logoSetupCompleted: boolean
}

/**
 * Save company logo data to user profile
 */
export async function saveCompanyLogo(userId: string, logoData: LogoData): Promise<void> {
  try {
    const userRef = doc(db, 'users', userId)
    
    // Update user profile with logo data
    await updateDoc(userRef, {
      'profile.logoType': logoData.type,
      'profile.logoId': logoData.logoId || null,
      'profile.logoUrl': logoData.logoUrl || null,
      'profile.logoSetupCompleted': logoData.logoSetupCompleted,
      'profile.updatedAt': new Date()
    })
    
    console.log('Company logo saved successfully')
  } catch (error) {
    console.error('Error saving company logo:', error)
    throw new Error('Failed to save company logo')
  }
}

/**
 * Upload logo file to Firebase Storage
 */
export async function uploadLogoFile(userId: string, file: File): Promise<string> {
  try {
    const fileExtension = file.name.split('.').pop()
    const fileName = `company-logos/${userId}/logo.${fileExtension}`
    const logoRef = ref(storage, fileName)
    
    // Upload file
    const snapshot = await uploadBytes(logoRef, file)
    
    // Get download URL
    const downloadURL = await getDownloadURL(snapshot.ref)
    
    return downloadURL
  } catch (error) {
    console.error('Error uploading logo file:', error)
    throw new Error('Failed to upload logo file')
  }
}

/**
 * Get company logo display data
 */
export function getCompanyLogoDisplay(profile: Record<string, unknown> | undefined, companyName: string) {
  if (!profile) {
    return {
      type: 'initials',
      display: getCompanyInitials(companyName),
      style: 'linear-gradient(135deg, #6b7280, #4b5563)'
    }
  }

  const logoType = profile.logoType as string
  const logoId = profile.logoId as string
  const logoUrl = profile.logoUrl as string

  switch (logoType) {
    case 'predefined':
      return {
        type: 'predefined',
        display: getCompanyInitials(companyName),
        style: getPredefinedLogoStyle(logoId)
      }
    
    case 'upload':
      return {
        type: 'upload',
        display: logoUrl,
        style: null
      }
    
    case 'initials':
    default:
      return {
        type: 'initials',
        display: getCompanyInitials(companyName),
        style: 'linear-gradient(135deg, #6b7280, #4b5563)'
      }
  }
}

/**
 * Get company initials from name
 */
function getCompanyInitials(companyName: string): string {
  return companyName
    .split(' ')
    .filter(Boolean)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

/**
 * Get predefined logo style by ID
 */
function getPredefinedLogoStyle(logoId: string): string {
  const styles: Record<string, string> = {
    'tech-blue': 'linear-gradient(135deg, #2563eb, #1d4ed8)',
    'corporate-gray': 'linear-gradient(135deg, #6b7280, #4b5563)',
    'success-green': 'linear-gradient(135deg, #10b981, #059669)',
    'creative-purple': 'linear-gradient(135deg, #8b5cf6, #7c3aed)',
    'energy-orange': 'linear-gradient(135deg, #f59e0b, #d97706)',
    'professional-navy': 'linear-gradient(135deg, #1e40af, #1e3a8a)'
  }
  
  return styles[logoId] || 'linear-gradient(135deg, #6b7280, #4b5563)'
}