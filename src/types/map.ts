/**
 * Generic map marker contract for Cavite OJT Map.
 * Intentionally decoupled from Internship / School / Company / Student models.
 */
export interface MapMarker {
  id: string | number
  lat: number
  lng: number
  title: string
  subtitle?: string
  type?: string
  popupHtml?: string
  metadata?: Record<string, unknown>
}

export interface MapLatLng {
  lat: number
  lng: number
}
