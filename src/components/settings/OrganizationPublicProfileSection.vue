<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { ArrowDown, ArrowUp, ImagePlus, Loader2, Trash2 } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useForm } from 'vee-validate'
import { z } from 'zod'

import AlertDialog from '@/components/ui/alert-dialog/AlertDialog.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import FormControl from '@/components/ui/form/FormControl.vue'
import FormField from '@/components/ui/form/FormField.vue'
import FormItem from '@/components/ui/form/FormItem.vue'
import FormLabel from '@/components/ui/form/FormLabel.vue'
import FormMessage from '@/components/ui/form/FormMessage.vue'
import Input from '@/components/ui/input/Input.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Textarea from '@/components/ui/textarea/Textarea.vue'
import { useToast } from '@/composables/useToast'
import {
  deleteOrganizationMedia,
  listOrganizationMedia,
  ORGANIZATION_PHOTO_ACCEPT,
  updateOrganizationMedia,
  uploadOrganizationMedia,
  validateOrganizationPhotoFile,
  type OrganizationMediaItem,
} from '@/services/organizationMedia'
import {
  fetchOrganizationPublicProfile,
  updateOrganizationPublicProfile,
  type OrganizationPublicProfile,
} from '@/services/settings'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const { success, error } = useToast()

const loading = ref(true)
const loadError = ref('')
const savingProfile = ref(false)
const uploading = ref(false)
const mediaBusyId = ref<string | null>(null)
const deleteTarget = ref<OrganizationMediaItem | null>(null)
const deleteDialogOpen = ref(false)
const photoInputRef = ref<HTMLInputElement | null>(null)

const profile = ref<OrganizationPublicProfile | null>(null)
const media = ref<OrganizationMediaItem[]>([])
const perkDraft = ref('')

const organizationId = computed(() => authStore.user?.activeOrganization?.id ?? profile.value?.id ?? '')
const photoCount = computed(() => profile.value?.photoCount ?? media.value.length)
const photoLimit = computed(() => profile.value?.photoLimit ?? 3)
const atPhotoLimit = computed(() => photoCount.value >= photoLimit.value)
const usageLabel = computed(
  () => profile.value?.photosUsedLabel || `${photoCount.value} of ${photoLimit.value} photos used`,
)

const publicProfileSchema = toTypedSchema(
  z.object({
    tagline: z.string().max(120, 'Tagline must be 120 characters or fewer.'),
    description: z.string().max(2000, 'Description must be 2000 characters or fewer.'),
    address: z.string().max(500, 'Address must be 500 characters or fewer.'),
    city: z.string().max(255, 'City must be 255 characters or fewer.'),
    website: z
      .string()
      .max(255, 'Website must be 255 characters or fewer.')
      .refine(
        (value) => !value || /^https?:\/\/.+/i.test(value),
        'Website must be a valid http or https URL.',
      ),
    industry: z.string().max(255, 'Industry must be 255 characters or fewer.'),
    perksText: z.string().optional(),
  }),
)

const {
  handleSubmit,
  errors,
  setValues,
  setFieldValue,
  values,
} = useForm({
  validationSchema: publicProfileSchema,
  initialValues: {
    tagline: '',
    description: '',
    address: '',
    city: '',
    website: '',
    industry: '',
    perksText: '',
  },
})

const perks = computed(() => {
  try {
    const parsed = JSON.parse(String(values.perksText || '[]'))
    return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string') : []
  } catch {
    return []
  }
})

function applyProfile(next: OrganizationPublicProfile) {
  profile.value = next
  setValues({
    tagline: next.tagline,
    description: next.description,
    address: next.address,
    city: next.city,
    website: next.website,
    industry: next.industry,
    perksText: JSON.stringify(next.perks),
  })
}

async function loadAll() {
  loading.value = true
  loadError.value = ''
  try {
    const nextProfile = await fetchOrganizationPublicProfile()
    applyProfile(nextProfile)
    const orgId = organizationId.value || nextProfile.id
    if (orgId) {
      media.value = await listOrganizationMedia(orgId)
    } else {
      media.value = []
    }
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : 'Unable to load public profile.'
    media.value = []
  } finally {
    loading.value = false
  }
}

function setPerks(next: string[]) {
  setFieldValue('perksText', JSON.stringify(next.slice(0, 10)))
}

function addPerk() {
  const value = perkDraft.value.trim().slice(0, 40)
  if (!value) return
  if (perks.value.length >= 10) {
    error('You can add up to 10 perks.')
    return
  }
  if (perks.value.includes(value)) {
    error('That perk is already listed.')
    return
  }
  setPerks([...perks.value, value])
  perkDraft.value = ''
}

function removePerk(index: number) {
  setPerks(perks.value.filter((_, i) => i !== index))
}

const savePublicProfile = handleSubmit(async (formValues) => {
  savingProfile.value = true
  try {
    const next = await updateOrganizationPublicProfile({
      tagline: formValues.tagline || null,
      description: formValues.description || null,
      address: formValues.address || null,
      city: formValues.city || null,
      website: formValues.website || null,
      industry: formValues.industry || null,
      perks: perks.value,
    })
    applyProfile(next)
    success('Public profile saved.')
  } catch (err) {
    error(err, { fallback: 'Unable to save public profile.' })
  } finally {
    savingProfile.value = false
  }
})

function openPhotoPicker() {
  photoInputRef.value?.click()
}

async function onPhotoSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  const validationError = validateOrganizationPhotoFile(file)
  if (validationError) {
    error(validationError)
    return
  }
  if (atPhotoLimit.value) {
    error('Photo limit reached for your current plan.')
    return
  }

  uploading.value = true
  try {
    const nextSort = media.value.reduce((max, item) => Math.max(max, item.sortOrder), -1) + 1
    const created = await uploadOrganizationMedia(file, { sortOrder: nextSort })
    media.value = [...media.value, created].sort((a, b) => a.sortOrder - b.sortOrder || a.id.localeCompare(b.id))
    if (profile.value) {
      profile.value = {
        ...profile.value,
        photoCount: profile.value.photoCount + 1,
        photosUsedLabel: `${profile.value.photoCount + 1} of ${profile.value.photoLimit} photos used`,
      }
    }
    success('Photo uploaded.')
  } catch (err) {
    error(err, { fallback: 'Unable to upload photo.' })
  } finally {
    uploading.value = false
  }
}

async function saveCaption(item: OrganizationMediaItem, caption: string) {
  const next = caption.trim() || null
  const previous = item.caption?.trim() || null
  if (next === previous) return

  mediaBusyId.value = item.id
  try {
    const updated = await updateOrganizationMedia(item.id, { caption: next })
    media.value = media.value.map((row) => (row.id === item.id ? updated : row))
    success('Caption updated.')
  } catch (err) {
    error(err, { fallback: 'Unable to update caption.' })
  } finally {
    mediaBusyId.value = null
  }
}

async function setAsCover(item: OrganizationMediaItem) {
  if (item.isCover) return
  mediaBusyId.value = item.id
  try {
    const updated = await updateOrganizationMedia(item.id, { isCover: true })
    media.value = media.value.map((row) => ({
      ...row,
      isCover: row.id === updated.id,
    }))
    success('Cover photo updated.')
  } catch (err) {
    error(err, { fallback: 'Unable to set cover photo.' })
  } finally {
    mediaBusyId.value = null
  }
}

async function movePhoto(item: OrganizationMediaItem, direction: -1 | 1) {
  const ordered = [...media.value].sort((a, b) => a.sortOrder - b.sortOrder || a.id.localeCompare(b.id))
  const index = ordered.findIndex((row) => row.id === item.id)
  const swapWith = ordered[index + direction]
  if (!swapWith || index < 0) return

  mediaBusyId.value = item.id
  try {
    const [first, second] = await Promise.all([
      updateOrganizationMedia(item.id, { sortOrder: swapWith.sortOrder }),
      updateOrganizationMedia(swapWith.id, { sortOrder: item.sortOrder }),
    ])
    media.value = media.value
      .map((row) => {
        if (row.id === first.id) return first
        if (row.id === second.id) return second
        return row
      })
      .sort((a, b) => a.sortOrder - b.sortOrder || a.id.localeCompare(b.id))
  } catch (err) {
    error(err, { fallback: 'Unable to reorder photos.' })
  } finally {
    mediaBusyId.value = null
  }
}

function confirmDelete(item: OrganizationMediaItem) {
  deleteTarget.value = item
  deleteDialogOpen.value = true
}

async function deleteConfirmed() {
  const item = deleteTarget.value
  deleteDialogOpen.value = false
  if (!item) return

  mediaBusyId.value = item.id
  try {
    await deleteOrganizationMedia(item.id)
    media.value = media.value.filter((row) => row.id !== item.id)
    if (profile.value) {
      const nextCount = Math.max(0, profile.value.photoCount - 1)
      profile.value = {
        ...profile.value,
        photoCount: nextCount,
        photosUsedLabel: `${nextCount} of ${profile.value.photoLimit} photos used`,
      }
    }
    success('Photo deleted.')
  } catch (err) {
    error(err, { fallback: 'Unable to delete photo.' })
  } finally {
    mediaBusyId.value = null
    deleteTarget.value = null
  }
}

onMounted(() => {
  void loadAll()
})
</script>

<template>
  <section class="space-y-5 rounded-2xl border border-border bg-card p-5">
    <div class="space-y-1">
      <h4 class="text-base font-semibold text-foreground">Public profile</h4>
      <p class="text-sm text-muted-foreground">
        This content appears on internship listing cards and your organization page.
      </p>
    </div>

    <div v-if="loading" class="space-y-3">
      <Skeleton class="h-10 w-full" />
      <Skeleton class="h-24 w-full" />
      <Skeleton class="h-10 w-1/2" />
    </div>

    <div v-else-if="loadError" class="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
      <p>{{ loadError }}</p>
      <Button type="button" variant="outline" class="mt-3" @click="loadAll">Retry</Button>
    </div>

    <template v-else>
      <form class="space-y-4" @submit="savePublicProfile">
        <FormField v-slot="{ componentField, errorMessage: fieldError }" name="tagline">
          <FormItem>
            <FormLabel for="orgPublicTagline">Tagline</FormLabel>
            <FormControl>
              <Input id="orgPublicTagline" v-bind="componentField" maxlength="120" placeholder="Short headline for your organization" />
            </FormControl>
            <FormMessage :message="fieldError || errors.tagline" />
          </FormItem>
        </FormField>

        <FormField v-slot="{ errorMessage: fieldError }" name="description">
          <FormItem>
            <FormLabel for="orgPublicDescription">Description</FormLabel>
            <FormControl>
              <Textarea
                id="orgPublicDescription"
                :model-value="String(values.description ?? '')"
                :rows="5"
                placeholder="Tell students what makes your organization a great place to intern"
                @update:modelValue="setFieldValue('description', $event)"
              />
            </FormControl>
            <FormMessage :message="fieldError || errors.description" />
          </FormItem>
        </FormField>

        <div class="grid gap-4 md:grid-cols-2">
          <FormField v-slot="{ componentField, errorMessage: fieldError }" name="address">
            <FormItem>
              <FormLabel for="orgPublicAddress">Address</FormLabel>
              <FormControl>
                <Input id="orgPublicAddress" v-bind="componentField" />
              </FormControl>
              <FormMessage :message="fieldError || errors.address" />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField, errorMessage: fieldError }" name="city">
            <FormItem>
              <FormLabel for="orgPublicCity">City</FormLabel>
              <FormControl>
                <Input id="orgPublicCity" v-bind="componentField" />
              </FormControl>
              <FormMessage :message="fieldError || errors.city" />
            </FormItem>
          </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <FormField v-slot="{ componentField, errorMessage: fieldError }" name="website">
            <FormItem>
              <FormLabel for="orgPublicWebsite">Website</FormLabel>
              <FormControl>
                <Input id="orgPublicWebsite" v-bind="componentField" placeholder="https://example.com" />
              </FormControl>
              <FormMessage :message="fieldError || errors.website" />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField, errorMessage: fieldError }" name="industry">
            <FormItem>
              <FormLabel for="orgPublicIndustry">Industry</FormLabel>
              <FormControl>
                <Input id="orgPublicIndustry" v-bind="componentField" />
              </FormControl>
              <FormMessage :message="fieldError || errors.industry" />
            </FormItem>
          </FormField>
        </div>

        <div class="space-y-3">
          <FormLabel>Perks</FormLabel>
          <div class="flex flex-wrap gap-2">
            <Badge v-for="(perk, index) in perks" :key="`${perk}-${index}`" variant="secondary" class="gap-2">
              {{ perk }}
              <button type="button" class="text-muted-foreground hover:text-foreground" @click="removePerk(index)">
                ×
              </button>
            </Badge>
            <p v-if="!perks.length" class="text-sm text-muted-foreground">No perks added yet.</p>
          </div>
          <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
            <Input v-model="perkDraft" maxlength="40" placeholder="e.g. Mentorship" @keydown.enter.prevent="addPerk" />
            <Button type="button" variant="outline" :disabled="perks.length >= 10" @click="addPerk">Add perk</Button>
          </div>
          <p class="text-xs text-muted-foreground">Up to 10 perks, 40 characters each.</p>
        </div>

        <Button type="submit" :disabled="savingProfile">
          {{ savingProfile ? 'Saving...' : 'Save public profile' }}
        </Button>
      </form>

      <div class="space-y-4 border-t border-border pt-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="space-y-1">
            <h5 class="text-sm font-semibold text-foreground">Photos</h5>
            <p class="text-sm text-muted-foreground">{{ usageLabel }}</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <input
              ref="photoInputRef"
              type="file"
              class="hidden"
              :accept="ORGANIZATION_PHOTO_ACCEPT"
              @change="onPhotoSelected"
            />
            <Button type="button" variant="outline" :disabled="uploading || atPhotoLimit" @click="openPhotoPicker">
              <Loader2 v-if="uploading" class="mr-2 h-4 w-4 animate-spin" />
              <ImagePlus v-else class="mr-2 h-4 w-4" />
              {{ uploading ? 'Uploading...' : 'Upload photo' }}
            </Button>
          </div>
        </div>

        <div
          v-if="atPhotoLimit"
          class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
        >
          Photo limit reached for your plan.
          <RouterLink to="/billing" class="font-medium text-primary underline-offset-4 hover:underline">
            Upgrade in Billing
          </RouterLink>
          to add more.
        </div>

        <div v-if="!media.length" class="rounded-lg border border-dashed border-border px-4 py-8 text-center">
          <p class="text-sm text-muted-foreground">No photos yet. Upload a cover image so listing cards show your brand.</p>
        </div>

        <ul v-else class="space-y-3">
          <li
            v-for="(item, index) in media"
            :key="item.id"
            class="grid gap-3 rounded-xl border border-border bg-background p-3 md:grid-cols-[120px_minmax(0,1fr)_auto]"
          >
            <div class="overflow-hidden rounded-lg border border-border bg-muted">
              <img :src="item.url" :alt="item.caption || 'Organization photo'" class="h-24 w-full object-cover" />
            </div>

            <div class="space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <Badge v-if="item.isCover" variant="default">Cover</Badge>
                <span class="text-xs text-muted-foreground">Order {{ index + 1 }}</span>
              </div>
              <Input
                :model-value="item.caption ?? ''"
                placeholder="Caption (optional)"
                :disabled="mediaBusyId === item.id"
                @update:modelValue="item.caption = $event"
                @blur="saveCaption(item, item.caption ?? '')"
              />
            </div>

            <div class="flex flex-wrap items-center gap-2 md:flex-col md:items-stretch">
              <Button
                type="button"
                size="sm"
                variant="outline"
                :disabled="item.isCover || mediaBusyId === item.id"
                @click="setAsCover(item)"
              >
                Set as cover
              </Button>
              <div class="flex gap-2">
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  :disabled="index === 0 || mediaBusyId === item.id"
                  @click="movePhoto(item, -1)"
                >
                  <ArrowUp class="h-4 w-4" />
                </Button>
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  :disabled="index === media.length - 1 || mediaBusyId === item.id"
                  @click="movePhoto(item, 1)"
                >
                  <ArrowDown class="h-4 w-4" />
                </Button>
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  class="text-destructive"
                  :disabled="mediaBusyId === item.id"
                  @click="confirmDelete(item)"
                >
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </template>

    <AlertDialog
      :open="deleteDialogOpen"
      title="Delete photo?"
      description="This removes the photo from your public profile. This cannot be undone."
      action-label="Delete"
      action-variant="destructive"
      cancel-label="Cancel"
      @update:open="deleteDialogOpen = $event"
      @action="deleteConfirmed"
    />
  </section>
</template>
