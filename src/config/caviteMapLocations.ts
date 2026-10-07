import { allCaviteLocations } from '@/config/courseCatalog'
import type { MapLatLng } from '@/types/map'

/**
 * Approximate city/municipality center points for Cavite OJT Map MVP.
 * Display/demo lookup only — not geocoded street addresses.
 */
export const CAVITE_MAP_CENTER: MapLatLng = { lat: 14.28, lng: 120.9 }

/** Reasonable pan bounds around Cavite province. */
export const CAVITE_MAP_BOUNDS = {
  southWest: { lat: 13.98, lng: 120.52 } as MapLatLng,
  northEast: { lat: 14.58, lng: 121.18 } as MapLatLng,
}

export const CAVITE_DEFAULT_ZOOM = 10
export const CAVITE_MIN_ZOOM = 9
export const CAVITE_MAX_ZOOM = 15

/** Canonical catalog names → approximate centers. */
const CAVITE_CITY_COORDINATES: Record<string, MapLatLng> = {
  'Bacoor City': { lat: 14.459, lng: 120.9326 },
  'Cavite City': { lat: 14.4791, lng: 120.8969 },
  'Dasmarinas City': { lat: 14.3294, lng: 120.9367 },
  'General Trias City': { lat: 14.3869, lng: 120.8816 },
  'Imus City': { lat: 14.4297, lng: 120.9367 },
  'Tagaytay City': { lat: 14.1153, lng: 120.9621 },
  'Trece Martires City': { lat: 14.2814, lng: 120.8669 },
  Alfonso: { lat: 14.1408, lng: 120.8556 },
  Amadeo: { lat: 14.1692, lng: 120.9236 },
  Carmona: { lat: 14.3108, lng: 121.0575 },
  'General Emilio Aguinaldo': { lat: 14.1847, lng: 120.7958 },
  'General Mariano Alvarez': { lat: 14.2986, lng: 121.0131 },
  Indang: { lat: 14.1953, lng: 120.8769 },
  Kawit: { lat: 14.4443, lng: 120.9015 },
  Magallanes: { lat: 14.1564, lng: 120.7489 },
  Maragondon: { lat: 14.2733, lng: 120.7347 },
  Mendez: { lat: 14.1289, lng: 120.9058 },
  Naic: { lat: 14.3181, lng: 120.7681 },
  Noveleta: { lat: 14.4331, lng: 120.88 },
  Rosario: { lat: 14.4139, lng: 120.8578 },
  Silang: { lat: 14.2306, lng: 120.9714 },
  Tanza: { lat: 14.3947, lng: 120.85 },
  Ternate: { lat: 14.2894, lng: 120.7167 },
}

function stripDiacritics(value: string): string {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
}

/**
 * Normalize free-text location labels (city filters, internship.location, org.city)
 * into a lookup key.
 */
export function normalizeCaviteLocationKey(raw: string): string {
  return stripDiacritics(raw)
    .toLowerCase()
    .replace(/[.,]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

/** Human alias labels → canonical catalog name (keys are normalized). */
const LOCATION_ALIAS_ENTRIES: Array<[string, string]> = [
  ['Bacoor', 'Bacoor City'],
  ['Bacoor City', 'Bacoor City'],
  ['Cavite City', 'Cavite City'],
  ['Dasmarinas', 'Dasmarinas City'],
  ['Dasmarinas City', 'Dasmarinas City'],
  ['Dasmariñas', 'Dasmarinas City'],
  ['Dasmariñas City', 'Dasmarinas City'],
  ['General Trias', 'General Trias City'],
  ['General Trias City', 'General Trias City'],
  ['Gen. Trias', 'General Trias City'],
  ['Gen Trias', 'General Trias City'],
  ['Imus', 'Imus City'],
  ['Imus City', 'Imus City'],
  ['Tagaytay', 'Tagaytay City'],
  ['Tagaytay City', 'Tagaytay City'],
  ['Trece Martires', 'Trece Martires City'],
  ['Trece Martires City', 'Trece Martires City'],
  ['Alfonso', 'Alfonso'],
  ['Amadeo', 'Amadeo'],
  ['Carmona', 'Carmona'],
  ['General Emilio Aguinaldo', 'General Emilio Aguinaldo'],
  ['Gen. Emilio Aguinaldo', 'General Emilio Aguinaldo'],
  ['Bailen', 'General Emilio Aguinaldo'],
  ['General Mariano Alvarez', 'General Mariano Alvarez'],
  ['GMA', 'General Mariano Alvarez'],
  ['Indang', 'Indang'],
  ['Kawit', 'Kawit'],
  ['Magallanes', 'Magallanes'],
  ['Maragondon', 'Maragondon'],
  ['Mendez', 'Mendez'],
  ['Mendez-Nunez', 'Mendez'],
  ['Mendez Nuñez', 'Mendez'],
  ['Naic', 'Naic'],
  ['Noveleta', 'Noveleta'],
  ['Rosario', 'Rosario'],
  ['Silang', 'Silang'],
  ['Tanza', 'Tanza'],
  ['Ternate', 'Ternate'],
]

const LOCATION_ALIASES: Record<string, string> = Object.fromEntries(
  LOCATION_ALIAS_ENTRIES.map(([alias, canonical]) => [normalizeCaviteLocationKey(alias), canonical]),
)

/**
 * Resolve a free-text Cavite place name to approximate lat/lng.
 * Returns null when the place cannot be mapped to a known municipality.
 */
export function resolveCaviteCoordinates(raw: string | null | undefined): MapLatLng | null {
  if (!raw?.trim()) return null

  const normalized = normalizeCaviteLocationKey(raw)

  // Exact alias hit
  const viaAlias = LOCATION_ALIASES[normalized]
  if (viaAlias && CAVITE_CITY_COORDINATES[viaAlias]) {
    return CAVITE_CITY_COORDINATES[viaAlias]
  }

  // Exact canonical name (case-insensitive)
  for (const [name, coords] of Object.entries(CAVITE_CITY_COORDINATES)) {
    if (normalizeCaviteLocationKey(name) === normalized) {
      return coords
    }
  }

  // Partial: "Imus City - Anabu..." or "Dasmarinas City, Cavite"
  // Prefer longer keys first to avoid short false positives.
  const byKeyLength = Object.entries(CAVITE_CITY_COORDINATES).sort(
    (a, b) => normalizeCaviteLocationKey(b[0]).length - normalizeCaviteLocationKey(a[0]).length,
  )
  for (const [name, coords] of byKeyLength) {
    const key = normalizeCaviteLocationKey(name)
    if (normalized.startsWith(key) || normalized.includes(` ${key}`) || normalized.includes(`${key} `)) {
      return coords
    }
  }

  // Alias prefix (e.g. "Dasmariñas - Barangay ...")
  const aliasesByLength = Object.entries(LOCATION_ALIASES).sort((a, b) => b[0].length - a[0].length)
  for (const [alias, canonical] of aliasesByLength) {
    if (normalized === alias || normalized.startsWith(`${alias} `) || normalized.startsWith(`${alias}-`)) {
      return CAVITE_CITY_COORDINATES[canonical] ?? null
    }
  }

  return null
}

/** Canonical names covered by the coordinate table (should match catalog). */
export function getCaviteMapLocationNames(): string[] {
  return Object.keys(CAVITE_CITY_COORDINATES)
}

/** True when every catalog city/municipality has a coordinate entry. */
export function caviteMapLocationsCoverCatalog(): boolean {
  return allCaviteLocations.every((name) => Boolean(CAVITE_CITY_COORDINATES[name]))
}
