<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import Swal from 'sweetalert2'
import { BriefcaseBusiness, Building2, CheckCircle2, FilePlus2, Layers3, LoaderCircle, Paperclip, RefreshCcw } from 'lucide-vue-next'

import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import FormControl from '@/components/ui/form/FormControl.vue'
import FormItem from '@/components/ui/form/FormItem.vue'
import FormLabel from '@/components/ui/form/FormLabel.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Textarea from '@/components/ui/textarea/Textarea.vue'
import { useToast } from '@/composables/useToast'
import { useDynamicContractForm } from '@/composables/useDynamicContractForm'
import type { PublicProfile } from '@/services/profilesPublic'
import type { ContractFieldSchema } from '@/services/dynamicContracts'
import { useAuthStore } from '@/stores/auth'

interface DynamicContractRequestFormProps {
  requesterRole: 'school' | 'company'
  partners: PublicProfile[]
  initialPartnerId?: string
  selectedPartner?: PublicProfile | null
  lockPartner?: boolean
  initialContractTypeName?: string
  lockContractType?: boolean
}

const props = defineProps<DynamicContractRequestFormProps>()
const authStore = useAuthStore()

const emit = defineEmits<{
  changePartner: []
  submitted: [payload?: { id?: string; moaReferenceNo?: string }]
}>()

const { success, error: showError } = useToast()

const {
  partnerUserId,
  loadingTypes,
  submitting,
  contractTypes,
  selectedTypeId,
  selectedType,
  subject,
  purpose,
  startDate,
  endDate,
  notes,
  dynamicFields,
  files,
  error,
  loadTypes,
  submit,
} = useDynamicContractForm(props.requesterRole)

const partnerLabel = computed(() => (props.requesterRole === 'school' ? 'Company' : 'School'))
const selectedPartnerProfile = computed(() => {
  if (props.selectedPartner) return props.selectedPartner
  return props.partners.find((partner) => partner.uid === partnerUserId.value) ?? null
})
const selectedTypeLocked = computed(() => Boolean(props.lockContractType && props.initialContractTypeName))
const canSubmit = computed(() => !loadingTypes.value && !submitting.value && Boolean(selectedTypeId.value))
const referencePreview = computed(() => `CTR-${new Date().getFullYear()}-######`)
const selectedFilesLabel = computed(() => {
  if (!files.value.length) return 'No attachments selected'
  if (files.value.length === 1) return files.value[0]?.name ?? '1 file selected'
  return `${files.value.length} files selected`
})
function normalizeCourseList(courses: unknown): string[] {
  if (!Array.isArray(courses)) return []

  const unique = new Map<string, string>()
  for (const course of courses) {
    if (typeof course !== 'string') continue
    const normalized = course.trim()
    if (!normalized) continue
    unique.set(normalized.toLowerCase(), normalized)
  }

  return [...unique.values()]
}

const requesterCourseOptions = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return normalizeCourseList(profile?.courses)
})

const partnerCourseOptions = computed(() => normalizeCourseList(selectedPartnerProfile.value?.courses))

const alignedCourseOptions = computed(() => {
  const requesterCourses = requesterCourseOptions.value
  const partnerCourses = partnerCourseOptions.value

  if (!requesterCourses.length || !partnerCourses.length) return []

  const partnerCourseMap = new Map(partnerCourses.map((course) => [course.toLowerCase(), course]))
  return requesterCourses.filter((course) => partnerCourseMap.has(course.toLowerCase()))
})
const contractTypeCards = computed(() =>
  contractTypes.value.map((type) => {
    const fieldsSchema = (type.fieldsSchema ?? []).map((field) => enrichField(field))
    return {
      ...type,
      fieldsSchema,
    }
  }),
)
const selectedTypeCard = computed(() => contractTypeCards.value.find((item) => item.id === selectedTypeId.value) ?? null)

function isCourseField(field: ContractFieldSchema) {
  const key = field.key.trim().toLowerCase()
  const label = field.label.trim().toLowerCase()
  return (
    field.type === 'multiselect' &&
    (key.includes('program') ||
      key.includes('course') ||
      label.includes('program') ||
      label.includes('course'))
  )
}

function enrichField(field: ContractFieldSchema): ContractFieldSchema {
  if (!isCourseField(field) || !alignedCourseOptions.value.length) {
    return field
  }

  return {
    ...field,
    options: alignedCourseOptions.value,
  }
}

function selectedMultiValues(key: string): string[] {
  const value = dynamicFields.value[key]
  return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : []
}

function toggleMultiSelectValue(key: string, option: string) {
  const currentValues = selectedMultiValues(key)
  const nextValues = currentValues.includes(option)
    ? currentValues.filter((item) => item !== option)
    : [...currentValues, option]

  updateFieldValue(key, nextValues)
}

watch(
  () => props.initialPartnerId,
  async (partnerId) => {
    if (!partnerId) return
    if (partnerUserId.value !== partnerId) {
      partnerUserId.value = partnerId
      await loadTypes(props.initialContractTypeName)
    }
  },
  { immediate: true },
)

onMounted(async () => {
  if (props.initialPartnerId && !contractTypes.value.length) {
    partnerUserId.value = props.initialPartnerId
    await loadTypes(props.initialContractTypeName)
  }
})

async function onPartnerChange() {
  await loadTypes()
}

function updateFieldValue(key: string, value: unknown) {
  dynamicFields.value = {
    ...dynamicFields.value,
    [key]: value,
  }
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  files.value = Array.from(input.files ?? [])
}

async function onSubmit() {
  const confirmation = await Swal.fire({
    icon: 'question',
    title: 'Send this contract request?',
    html: `
      <div style="text-align:left;display:grid;gap:8px;">
        <div><strong>${partnerLabel.value}:</strong> ${selectedPartnerProfile.value?.orgName || selectedPartnerProfile.value?.displayName || 'Not selected'}</div>
        <div><strong>Contract Type:</strong> ${selectedTypeCard.value?.name || selectedType.value?.name || 'Not selected'}</div>
        <div><strong>Subject:</strong> ${subject.value || 'No subject entered yet'}</div>
        <div><strong>Attachments:</strong> ${files.value.length}</div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Send Request',
    cancelButtonText: 'Review Again',
    confirmButtonColor: '#2563eb',
  })

  if (!confirmation.isConfirmed) return

  try {
    const response = await submit()
    const referenceNo = response?.data?.moaReferenceNo
    success('Contract request sent.', {
      description: referenceNo
        ? `Reference number: ${referenceNo}`
        : 'Your agreement request has been submitted successfully.',
    })
    emit('submitted', {
      id: response?.data?.id,
      moaReferenceNo: referenceNo,
    })
  } catch (caughtError) {
    showError(caughtError, {
      fallback: error.value ?? 'Could not create contract request.',
    })
  }
}
</script>

<template>
  <form class="space-y-6" @submit.prevent="onSubmit">
    <Card class="overflow-hidden border-primary/20 bg-gradient-to-br from-sky-50 via-white to-slate-50 shadow-none">
      <CardContent class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(280px,1fr)]">
        <div class="space-y-3">
          <div class="inline-flex w-fit items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.25em] text-primary">
            Dynamic Contract Builder
          </div>
          <div class="space-y-2">
            <h3 class="text-2xl font-semibold tracking-tight text-slate-950">Build a polished contract request</h3>
            <p class="max-w-2xl text-sm leading-6 text-slate-600">
              Pick a {{ partnerLabel.toLowerCase() }}, load the matching contract template, and complete only the fields required for that agreement.
            </p>
          </div>
        </div>

        <Card class="border-border/70 bg-white/90 shadow-none">
          <CardContent class="space-y-4 p-4">
            <div v-if="lockPartner && selectedPartnerProfile" class="space-y-3">
              <div class="flex items-start gap-3">
                <div class="rounded-2xl bg-primary/10 p-3 text-primary">
                  <Building2 v-if="requesterRole === 'school'" class="h-5 w-5" />
                  <BriefcaseBusiness v-else class="h-5 w-5" />
                </div>
                <div class="min-w-0 space-y-1">
                  <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Selected {{ partnerLabel }}</p>
                  <p class="text-base font-semibold text-slate-950">{{ selectedPartnerProfile.orgName || selectedPartnerProfile.displayName }}</p>
                  <p class="text-sm text-slate-600">{{ selectedPartnerProfile.email || 'Directory profile' }}</p>
                </div>
              </div>
              <Button type="button" variant="outline" class="w-full sm:w-auto" @click="emit('changePartner')">
                <RefreshCcw class="h-4 w-4" />
                Change {{ partnerLabel }}
              </Button>
            </div>

            <FormItem v-else>
              <FormLabel>{{ partnerLabel }}</FormLabel>
              <FormControl>
                <Select v-model="partnerUserId" @update:modelValue="onPartnerChange">
                  <option value="">Select {{ partnerLabel.toLowerCase() }}</option>
                  <option v-for="partner in partners" :key="partner.uid" :value="partner.uid">
                    {{ partner.orgName || partner.displayName }}
                  </option>
                </Select>
              </FormControl>
            </FormItem>
          </CardContent>
        </Card>
      </CardContent>
    </Card>

    <div class="grid gap-4 md:grid-cols-2">
      <FormItem>
        <FormLabel>Reference Number</FormLabel>
        <FormControl>
          <div class="flex min-h-11 items-center rounded-xl border border-input bg-muted/50 px-4 py-3 text-sm text-foreground">
            {{ referencePreview }}
          </div>
        </FormControl>
        <p class="text-xs text-slate-500">
          Assigned automatically when the contract is submitted.
        </p>
      </FormItem>

      <FormItem class="md:col-span-2">
        <FormLabel>Contract Type</FormLabel>
        <FormControl>
          <div v-if="selectedTypeLocked" class="flex min-h-11 items-center rounded-xl border border-input bg-muted/50 px-4 py-3 text-sm text-foreground">
            {{ selectedTypeCard?.name || selectedType?.name || initialContractTypeName }}
          </div>
          <div
            v-else
            class="grid gap-3 rounded-2xl border border-border/80 bg-slate-50/70 p-4 md:grid-cols-2 xl:grid-cols-3"
          >
            <button
              v-for="type in contractTypeCards"
              :key="type.id"
              type="button"
              class="rounded-2xl border p-4 text-left transition-all"
              :class="
                selectedTypeId === type.id
                  ? 'border-sky-400 bg-white shadow-[0_18px_45px_rgba(14,116,144,0.12)]'
                  : 'border-slate-200 bg-white/90 hover:border-slate-300 hover:bg-white'
              "
              @click="selectedTypeId = type.id"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="rounded-2xl bg-sky-100 p-2.5 text-sky-700">
                  <Layers3 class="h-5 w-5" />
                </div>
                <span
                  class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                  :class="type.scope === 'organization' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'"
                >
                  <CheckCircle2 v-if="selectedTypeId === type.id" class="h-3.5 w-3.5" />
                  {{ type.scope === 'organization' ? 'Custom' : 'Standard' }}
                </span>
              </div>
              <p class="mt-4 text-base font-semibold text-slate-950">{{ type.name }}</p>
              <p class="mt-2 text-sm leading-6 text-slate-600">{{ type.description || 'Complete the required fields for this agreement type.' }}</p>
              <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
                <span>{{ type.fieldsSchema.length }} field{{ type.fieldsSchema.length === 1 ? '' : 's' }}</span>
                <span v-if="type.fieldsSchema.some((field) => isCourseField(field)) && alignedCourseOptions.length">Uses aligned courses</span>
              </div>
            </button>
            <div
              v-if="!contractTypeCards.length && !loadingTypes"
              class="rounded-2xl border border-dashed border-slate-200 bg-white/80 px-4 py-8 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3"
            >
              No contract types are available for this partner yet.
            </div>
          </div>
        </FormControl>
      </FormItem>

      <FormItem>
        <FormLabel>Subject</FormLabel>
        <FormControl>
          <Input v-model="subject" type="text" placeholder="Memorandum of Agreement for Internship Placement" />
        </FormControl>
      </FormItem>

      <FormItem class="md:col-span-2">
        <FormLabel>Purpose</FormLabel>
        <FormControl>
          <Textarea v-model="purpose" :rows="4" placeholder="Explain the purpose of this contract request" />
        </FormControl>
      </FormItem>

      <FormItem>
        <FormLabel>Start Date</FormLabel>
        <FormControl>
          <Input v-model="startDate" type="date" />
        </FormControl>
      </FormItem>

      <FormItem>
        <FormLabel>End Date</FormLabel>
        <FormControl>
          <Input v-model="endDate" type="date" />
        </FormControl>
      </FormItem>
    </div>

    <Card v-if="selectedTypeCard" class="border-border/80 shadow-none">
      <CardContent class="space-y-5 p-5">
        <div class="space-y-2">
          <div class="flex items-center gap-3">
            <div class="rounded-2xl bg-slate-100 p-3 text-slate-700">
              <FilePlus2 class="h-5 w-5" />
            </div>
            <div>
              <h4 class="text-lg font-semibold text-slate-950">{{ selectedTypeCard.name }}</h4>
              <p class="text-sm text-slate-600">{{ selectedTypeCard.description }}</p>
            </div>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <template v-for="field in selectedTypeCard.fieldsSchema" :key="field.key">
            <FormItem :class="field.type === 'textarea' || field.type === 'multiselect' ? 'md:col-span-2' : ''">
              <FormLabel>
                {{ field.label }}
                <span v-if="field.required" class="ml-1 text-red-500">*</span>
              </FormLabel>
              <FormControl>
                <Input
                  v-if="['text', 'email', 'number', 'date'].includes(field.type)"
                  :type="field.type === 'text' ? 'text' : field.type"
                  :required="field.required"
                  :placeholder="field.placeholder || field.label"
                  :model-value="String(dynamicFields[field.key] ?? '')"
                  @update:modelValue="updateFieldValue(field.key, $event)"
                />

                <Textarea
                  v-else-if="field.type === 'textarea'"
                  :rows="4"
                  :placeholder="field.placeholder || field.label"
                  :model-value="String(dynamicFields[field.key] ?? '')"
                  @update:modelValue="updateFieldValue(field.key, $event)"
                />

                <Select
                  v-else-if="field.type === 'select'"
                  :model-value="String(dynamicFields[field.key] ?? '')"
                  @update:modelValue="updateFieldValue(field.key, $event)"
                >
                  <option value="">Select {{ field.label.toLowerCase() }}</option>
                  <option v-for="option in field.options || []" :key="option" :value="option">{{ option }}</option>
                </Select>

                <div
                  v-else-if="field.type === 'multiselect'"
                  class="space-y-3 rounded-2xl border border-border/80 bg-slate-50/80 p-4"
                >
                  <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">
                    Select one or more options
                  </p>
                  <p v-if="isCourseField(field)" class="text-xs text-slate-500">
                    Showing only courses shared by both organizations.
                  </p>
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-for="option in field.options || []"
                      :key="option"
                      type="button"
                      class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium transition-all"
                      :class="
                        selectedMultiValues(field.key).includes(option)
                          ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                          : 'border-slate-200 bg-white text-slate-700 hover:border-primary/40 hover:bg-primary/5'
                      "
                      @click="toggleMultiSelectValue(field.key, option)"
                    >
                      <span
                        class="flex h-4 w-4 items-center justify-center rounded-full border text-[10px]"
                        :class="
                          selectedMultiValues(field.key).includes(option)
                            ? 'border-primary-foreground/50 bg-primary-foreground/15 text-primary-foreground'
                            : 'border-slate-300 text-transparent'
                        "
                      >
                        ✓
                      </span>
                      {{ option }}
                    </button>
                  </div>
                  <p v-if="field.required && !selectedMultiValues(field.key).length" class="text-xs text-slate-500">
                    Choose at least one option before submitting.
                  </p>
                </div>
              </FormControl>
            </FormItem>
          </template>
        </div>
      </CardContent>
    </Card>

    <div class="grid gap-4">
      <FormItem>
        <FormLabel>Notes</FormLabel>
        <FormControl>
          <Textarea v-model="notes" :rows="4" placeholder="Add optional notes or context for the receiving party" />
        </FormControl>
      </FormItem>

      <FormItem>
        <FormLabel>Attachments</FormLabel>
        <FormControl>
          <label class="flex cursor-pointer flex-col gap-3 rounded-xl border border-dashed border-border bg-muted/30 p-4 transition hover:border-primary/40 hover:bg-primary/5">
            <div class="flex items-center gap-3 text-sm text-slate-700">
              <div class="rounded-xl bg-primary/10 p-2 text-primary">
                <Paperclip class="h-4 w-4" />
              </div>
              <div>
                <p class="font-medium">Upload supporting documents</p>
                <p class="text-slate-500">{{ selectedFilesLabel }}</p>
              </div>
            </div>
            <input type="file" class="hidden" multiple @change="onFileChange" />
          </label>
        </FormControl>
      </FormItem>
    </div>

    <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ error }}
    </div>

    <div class="flex justify-end">
      <Button type="submit" class="min-w-[220px]" :disabled="!canSubmit">
        <LoaderCircle v-if="submitting || loadingTypes" class="h-4 w-4 animate-spin" />
        <span>{{ submitting ? 'Submitting...' : loadingTypes ? 'Loading Types...' : 'Send Contract Request' }}</span>
      </Button>
    </div>
  </form>
</template>
