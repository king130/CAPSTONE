<script setup lang="ts">
import L from 'leaflet'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import {
  CAVITE_DEFAULT_ZOOM,
  CAVITE_MAP_BOUNDS,
  CAVITE_MAP_CENTER,
  CAVITE_MAX_ZOOM,
  CAVITE_MIN_ZOOM,
} from '@/config/caviteMapLocations'
import { cn } from '@/lib/utils'
import type { MapMarker } from '@/types/map'

import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'

const props = withDefaults(
  defineProps<{
    markers?: MapMarker[]
    selectedId?: string | number | null
    class?: string
    heightClass?: string
    fitToMarkers?: boolean
  }>(),
  {
    markers: () => [],
    selectedId: null,
    class: '',
    heightClass: 'h-[320px] min-h-[240px] md:h-[420px]',
    fitToMarkers: false,
  },
)

const emit = defineEmits<{
  select: [marker: MapMarker]
}>()

const rootClass = computed(() =>
  cn(
    'overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm',
    props.class,
  ),
)

const mapEl = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let markerLayer: L.LayerGroup | null = null
const leafletMarkers = new Map<string, L.Marker>()

const defaultIcon = L.icon({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
})

const selectedIcon = L.divIcon({
  className: 'cavite-map-selected-marker',
  html: '<span class="cavite-map-selected-pin" aria-hidden="true"></span>',
  iconSize: [28, 28],
  iconAnchor: [14, 28],
  popupAnchor: [0, -24],
})

function markerKey(id: string | number): string {
  return String(id)
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

function buildPopupHtml(marker: MapMarker): string {
  if (marker.popupHtml) return marker.popupHtml

  const title = escapeHtml(marker.title)
  const subtitle = marker.subtitle ? `<div class="cavite-map-popup-sub">${escapeHtml(marker.subtitle)}</div>` : ''
  return `<div class="cavite-map-popup"><strong>${title}</strong>${subtitle}</div>`
}

function syncMarkers() {
  if (!map || !markerLayer) return

  markerLayer.clearLayers()
  leafletMarkers.clear()

  const boundsPoints: L.LatLngExpression[] = []

  for (const marker of props.markers) {
    if (!Number.isFinite(marker.lat) || !Number.isFinite(marker.lng)) continue

    const isSelected = props.selectedId != null && markerKey(marker.id) === markerKey(props.selectedId)
    const leafletMarker = L.marker([marker.lat, marker.lng], {
      icon: isSelected ? selectedIcon : defaultIcon,
      title: marker.title,
      riseOnHover: true,
    })

    leafletMarker.bindPopup(buildPopupHtml(marker), { maxWidth: 260 })
    leafletMarker.on('click', () => {
      emit('select', marker)
    })

    leafletMarker.addTo(markerLayer)
    leafletMarkers.set(markerKey(marker.id), leafletMarker)
    boundsPoints.push([marker.lat, marker.lng])
  }

  if (props.fitToMarkers && boundsPoints.length > 0) {
    map.fitBounds(L.latLngBounds(boundsPoints), {
      padding: [36, 36],
      maxZoom: 13,
    })
  }
}

function initMap() {
  if (!mapEl.value || map) return

  const bounds = L.latLngBounds(
    [CAVITE_MAP_BOUNDS.southWest.lat, CAVITE_MAP_BOUNDS.southWest.lng],
    [CAVITE_MAP_BOUNDS.northEast.lat, CAVITE_MAP_BOUNDS.northEast.lng],
  )

  map = L.map(mapEl.value, {
    center: [CAVITE_MAP_CENTER.lat, CAVITE_MAP_CENTER.lng],
    zoom: CAVITE_DEFAULT_ZOOM,
    minZoom: CAVITE_MIN_ZOOM,
    maxZoom: CAVITE_MAX_ZOOM,
    maxBounds: bounds.pad(0.08),
    maxBoundsViscosity: 0.85,
    scrollWheelZoom: true,
    attributionControl: true,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: CAVITE_MAX_ZOOM,
  }).addTo(map)

  markerLayer = L.layerGroup().addTo(map)
  syncMarkers()

  // Leaflet needs a tick after mount when container size was 0 during init.
  requestAnimationFrame(() => {
    map?.invalidateSize()
  })
}

function destroyMap() {
  if (map) {
    map.remove()
    map = null
  }
  markerLayer = null
  leafletMarkers.clear()
}

onMounted(() => {
  initMap()
})

onBeforeUnmount(() => {
  destroyMap()
})

watch(
  () => [props.markers, props.selectedId, props.fitToMarkers] as const,
  () => {
    syncMarkers()
  },
  { deep: true },
)

watch(
  () => props.selectedId,
  (id) => {
    if (id == null || !map) return
    const leafletMarker = leafletMarkers.get(markerKey(id))
    if (!leafletMarker) return
    const latLng = leafletMarker.getLatLng()
    map.panTo(latLng, { animate: true })
    leafletMarker.openPopup()
  },
)
</script>

<template>
  <div :class="rootClass" data-testid="cavite-map">
    <div
      ref="mapEl"
      :class="cn('cavite-map-surface w-full', heightClass)"
      role="application"
      aria-label="Cavite OJT map"
    />
  </div>
</template>

<style scoped>
.cavite-map-surface {
  z-index: 0;
}

.cavite-map-surface :deep(.leaflet-container) {
  font-family: inherit;
  background: hsl(var(--muted) / 0.35);
}

.cavite-map-surface :deep(.leaflet-control-attribution) {
  background: hsl(var(--card) / 0.92);
  color: hsl(var(--muted-foreground));
  font-size: 10px;
}

.cavite-map-surface :deep(.leaflet-control-attribution a) {
  color: hsl(var(--foreground));
}

.cavite-map-surface :deep(.leaflet-popup-content-wrapper) {
  border-radius: 0.75rem;
  border: 1px solid hsl(var(--border));
  background: hsl(var(--card));
  color: hsl(var(--card-foreground));
  box-shadow: 0 8px 24px rgb(0 0 0 / 0.12);
}

.cavite-map-surface :deep(.leaflet-popup-tip) {
  background: hsl(var(--card));
}

.cavite-map-surface :deep(.cavite-map-popup) {
  font-size: 0.875rem;
  line-height: 1.35;
}

.cavite-map-surface :deep(.cavite-map-popup-sub) {
  margin-top: 0.25rem;
  color: hsl(var(--muted-foreground));
  font-size: 0.8125rem;
}

.cavite-map-surface :deep(.cavite-map-selected-marker) {
  background: transparent;
  border: none;
}

.cavite-map-surface :deep(.cavite-map-selected-pin) {
  display: block;
  width: 18px;
  height: 18px;
  margin: 0 auto;
  border-radius: 9999px;
  border: 3px solid hsl(var(--background));
  background: hsl(var(--primary));
  box-shadow: 0 0 0 2px hsl(var(--primary) / 0.35);
}
</style>
