<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import {
  BriefcaseBusiness,
  Building2,
  Check,
  ChevronLeft,
  ChevronRight,
  FilePlus2,
  Layers3,
  LoaderCircle,
  Paperclip,
  Search,
  Send,
} from 'lucide-vue-next'

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
import {
  type AgreementWizardStep,
  useDynamicContractForm,
} from '@/composables/useDynamicContractForm'
import { OJT_MOA_TEMPLATE_SLUG, type ContractFieldSchema } from '@/services/dynamicContracts'
import { isAgreementPartnerSelectable, type PublicProfile } from '@/services/profilesPublic'
import { useAuthStore } from '@/stores/auth'
import { normalizeCourseList, sharedCourses } from '@/utils/courseAlignment'

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
  wizardStep,
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
  fieldErrors,
  draftRestored,
  draftSavedAt,
  loadTypes,
  restoreDraft,
  clearFieldError,
  setFieldErrors,
  submit,
} = useDynamicContractForm(props.requesterRole, {
  draftUserId: () => authStore.user?.uid,
})

const partnerSearch = ref('')
const localFieldErrors = ref<Record<string, string>>({})

const WIZARD_STEPS: Array<{ id: AgreementWizardStep; label: string }> = [
  { id: 'partner', label: 'Partner' },
  { id: 'type', label: 'Agreement type' },
  { id: 'details', label: 'Details' },
  { id: 'review', label: 'Review' },
  { id: 'send', label: 'Send' },
]

const partnerLabel = computed(() => (props.requesterRole === 'school' ? 'Company' : 'School'))
const stepIndex = computed(() => WIZARD_STEPS.findIndex((item) => item.id === wizardStep.value))
const selectedPartnerProfile = computed(() => {
  if (props.selectedPartner) return props.selectedPartner
  return props.partners.find((partner) => partner.uid === partnerUserId.value) ?? null
})
const partnerDisplayName = computed(
  () => selectedPartnerProfile.value?.orgName || selectedPartnerProfile.value?.displayName || 'your partner',
)
const selectedTypeLocked = computed(() => Boolean(props.lockContractType && props.initialContractTypeName))
const referencePreview = computed(() => `CTR-${new Date().getFullYear()}-######`)
const selectedFilesLabel = computed(() => {
  if (!files.value.length) return 'No attachments selected'
  if (files.value.length === 1) return files.value[0]?.name ?? '1 file selected'
  return `${files.value.length} files selected`
})

const filteredPartners = computed(() => {
  const query = partnerSearch.value.trim().toLowerCase()
  const list = props.partners.filter((partner) => isAgreementPartnerSelectable(partner))
  if (!query) return list
  return list.filter((partner) =>
    [partner.orgName, partner.displayName, partner.email]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query)),
  )
})

const draftStatusLabel = computed(() => {
  if (!draftSavedAt.value) return ''
  return `Draft saved ${draftSavedAt.value.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`
})

const requesterCourseOptions = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return normalizeCourseList(profile?.courses)
})

const partnerCourseOptions = computed(() => normalizeCourseList(selectedPartnerProfile.value?.courses))

const alignedCourseOptions = computed(() =>
  sharedCourses(requesterCourseOptions.value, partnerCourseOptions.value),
)

const noSharedCoursesForPartner = computed(
  () =>
    Boolean(selectedPartnerProfile.value) &&
    requesterCourseOptions.value.length > 0 &&
    partnerCourseOptions.value.length > 0 &&
    alignedCourseOptions.value.length === 0,
)

function isCourseField(field: ContractFieldSchema) {
  const key = field.key.trim().toLowerCase()
  const label = field.label.trim().toLowerCase()
  return (
    field.type === 'multiselect' &&
    (key.includes('program') || key.includes('course') || label.includes('program') || label.includes('course'))
  )
}

function enrichField(field: ContractFieldSchema): ContractFieldSchema {
  if (!isCourseField(field) || !alignedCourseOptions.value.length) return field
  return { ...field, options: alignedCourseOptions.value }
}

const contractTypeCards = computed(() =>
  contractTypes.value.map((type) => ({
    ...type,
    fieldsSchema: (type.fieldsSchema ?? []).map((field) => enrichField(field)),
  })),
)

const selectedTypeCard = computed(
  () => contractTypeCards.value.find((item) => item.id === selectedTypeId.value) ?? null,
)

function fieldGuidance(field: ContractFieldSchema) {
  const key = field.key.toLowerCase()
  if (key.includes('contact_person') || key.includes('supervisor') || key.includes('coordinator')) {
    return {
      help: 'Name of the person the partner can reach for questions about this agreement.',
      example: 'Maria Santos, OJT Coordinator',
    }
  }
  if (field.type === 'email' || key.includes('email')) {
    return {
      help: 'Use a work email that is checked regularly.',
      example: 'coordinator@school.edu.ph',
    }
  }
  if (isCourseField(field)) {
    return {
      help: 'Choose programs covered by this agreement. Only aligned courses appear here.',
      example: 'BS Information Technology',
    }
  }
  if (field.type === 'number' || key.includes('intern') || key.includes('slot')) {
    return {
      help: 'Approximate number of students you plan to place under this agreement.',
      example: '10',
    }
  }
  if (field.type === 'date') {
    return { help: 'Pick the date using the calendar control.', example: '2026-06-01' }
  }
  if (field.type === 'textarea') {
    return {
      help: `Add clear details for “${field.label}”.`,
      example: field.placeholder || 'Brief summary in plain language',
    }
  }
  return {
    help: field.required ? `Required for this agreement type.` : `Optional — add if it applies to your request.`,
    example: field.placeholder || field.label,
  }
}

const staticGuidance = {
  subject: {
    help: 'Short title shown on the agreement list and in notifications.',
    example: 'OJT Memorandum of Agreement',
  },
  purpose: {
    help: 'Explain why you are sending this agreement and what the partnership should cover.',
    example: 'Formalize OJT placements for computing students for the upcoming term.',
  },
  startDate: {
    help: 'When the partnership or placement period should begin.',
    example: 'First day of the semester or intake batch',
  },
  endDate: {
    help: 'When the agreement period ends. Must be on or after the start date.',
    example: 'Last day of the internship term',
  },
  notes: {
    help: 'Optional message to the partner — scheduling, documents needed, or special arrangements.',
    example: 'We can provide student lists two weeks before start date.',
  },
  attachments: {
    help: 'Upload supporting PDFs or images (MOA templates, endorsement letters, etc.).',
    example: 'Signed endorsement letter, company profile',
  },
} as const

function mergedFieldError(key: string) {
  return localFieldErrors.value[key] || fieldErrors.value[key] || ''
}

function dynamicFieldError(key: string) {
  return localFieldErrors.value[`dynamic:${key}`] || fieldErrors.value[`dynamic:${key}`] || ''
}

function partnerStatusLabel(partner: PublicProfile): string | null {
  if (partner.agreementStatusLabel) return partner.agreementStatusLabel
  if (partner.agreementStatus === 'active') return 'Agreement already active'
  if (partner.agreementStatus === 'pending') return 'Agreement request pending'
  return null
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
  clearLocalDynamicError(key)
}

function updateFieldValue(key: string, value: unknown) {
  dynamicFields.value = { ...dynamicFields.value, [key]: value }
  clearLocalDynamicError(key)
}

function clearLocalDynamicError(key: string) {
  clearFieldError(`dynamic:${key}`)
  if (localFieldErrors.value[`dynamic:${key}`]) {
    const next = { ...localFieldErrors.value }
    delete next[`dynamic:${key}`]
    localFieldErrors.value = next
  }
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  files.value = Array.from(input.files ?? [])
}

async function selectPartner(partner: PublicProfile) {
  if (!isAgreementPartnerSelectable(partner)) return
  partnerUserId.value = partner.uid
  clearFieldError('partnerUserId')
  await loadTypes(props.initialContractTypeName ?? OJT_MOA_TEMPLATE_SLUG)
}

function validatePartnerStep() {
  if (partnerUserId.value) return true
  localFieldErrors.value = { ...localFieldErrors.value, partnerUserId: `Choose a ${partnerLabel.value.toLowerCase()} to continue.` }
  return false
}

function validateTypeStep() {
  if (selectedTypeId.value) return true
  localFieldErrors.value = { ...localFieldErrors.value, selectedTypeId: 'Choose an agreement type to continue.' }
  return false
}

function validateDetailsStep() {
  const next: Record<string, string> = {}
  if (!subject.value.trim()) next.subject = 'Subject is required.'
  if (!purpose.value.trim()) next.purpose = 'Purpose is required.'
  if (startDate.value && endDate.value && endDate.value < startDate.value) {
    next.endDate = 'End date must be on or after the start date.'
  }

  for (const field of selectedTypeCard.value?.fieldsSchema ?? []) {
    if (!field.required) continue
    const value = dynamicFields.value[field.key]
    if (field.type === 'multiselect') {
      if (!selectedMultiValues(field.key).length) {
        next[`dynamic:${field.key}`] = `Choose at least one option for ${field.label}.`
      }
      continue
    }
    if (value === undefined || value === null || String(value).trim() === '') {
      next[`dynamic:${field.key}`] = `${field.label} is required.`
    }
  }

  localFieldErrors.value = { ...localFieldErrors.value, ...next }
  setFieldErrors({ ...fieldErrors.value, ...next })
  return Object.keys(next).length === 0
}

function goToStep(step: AgreementWizardStep) {
  wizardStep.value = step
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function goNext() {
  localFieldErrors.value = {}
  if (wizardStep.value === 'partner') {
    if (!validatePartnerStep()) return
    if (!contractTypes.value.length && partnerUserId.value) {
      await loadTypes(props.initialContractTypeName ?? OJT_MOA_TEMPLATE_SLUG)
    }
    goToStep('type')
    return
  }
  if (wizardStep.value === 'type') {
    if (!validateTypeStep()) return
    goToStep('details')
    return
  }
  if (wizardStep.value === 'details') {
    if (!validateDetailsStep()) return
    goToStep('review')
    return
  }
  if (wizardStep.value === 'review') {
    goToStep('send')
  }
}

function goBack() {
  const index = stepIndex.value
  if (index <= 0) return
  goToStep(WIZARD_STEPS[index - 1]!.id)
}

function formatDateLabel(value: string) {
  if (!value) return 'Not set'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

async function onSendAgreement() {
  if (!validateDetailsStep()) {
    goToStep('details')
    return
  }
  try {
    const response = await submit()
    const referenceNo = response?.data?.moaReferenceNo
    success('Agreement sent.', {
      description: referenceNo
        ? `Reference number: ${referenceNo}. Waiting for ${partnerDisplayName.value} to respond.`
        : `Your partner will be notified. Status: Waiting for ${partnerDisplayName.value} to respond.`,
    })
    emit('submitted', {
      id: response?.data?.id,
      moaReferenceNo: referenceNo,
    })
  } catch (caughtError) {
    if (fieldErrors.value.subject || fieldErrors.value.purpose || Object.keys(fieldErrors.value).some((k) => k.startsWith('dynamic:'))) {
      goToStep('details')
    }
    showError(caughtError, {
      fallback: error.value ?? 'Could not send the agreement.',
    })
  }
}

watch(
  () => props.initialPartnerId,
  async (partnerId) => {
    if (!partnerId) return
    if (partnerUserId.value !== partnerId) {
      partnerUserId.value = partnerId
      await loadTypes(props.initialContractTypeName ?? OJT_MOA_TEMPLATE_SLUG)
    }
  },
  { immediate: true },
)

watch([subject, purpose, startDate, endDate], () => {
  if (subject.value.trim()) clearFieldError('subject')
  if (purpose.value.trim()) clearFieldError('purpose')
  if (localFieldErrors.value.subject && subject.value.trim()) {
    const next = { ...localFieldErrors.value }
    delete next.subject
    localFieldErrors.value = next
  }
  if (localFieldErrors.value.purpose && purpose.value.trim()) {
    const next = { ...localFieldErrors.value }
    delete next.purpose
    localFieldErrors.value = next
  }
})

onMounted(async () => {
  const restored = await restoreDraft()
  if (restored && props.initialPartnerId && partnerUserId.value !== props.initialPartnerId) {
    partnerUserId.value = props.initialPartnerId
    await loadTypes(props.initialContractTypeName ?? OJT_MOA_TEMPLATE_SLUG)
  } else if (!restored && props.initialPartnerId && !contractTypes.value.length) {
    partnerUserId.value = props.initialPartnerId
    await loadTypes(props.initialContractTypeName ?? OJT_MOA_TEMPLATE_SLUG)
    if (props.lockPartner) wizardStep.value = 'type'
  }
})
</script>

<template>
  <div class="space-y-6">
    <Card class="border-border/80 shadow-sm">
      <CardContent class="space-y-4 p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
              Step {{ stepIndex + 1 }} of {{ WIZARD_STEPS.length }} · {{ WIZARD_STEPS[stepIndex]?.label }}
            </p>
            <p v-if="draftRestored" class="mt-1 text-sm text-primary">We restored your saved draft.</p>
            <p v-else-if="draftStatusLabel" class="mt-1 text-xs text-muted-foreground" aria-live="polite">
              {{ draftStatusLabel }}
            </p>
          </div>
          <Button
            v-if="lockPartner && selectedPartnerProfile && wizardStep === 'partner'"
            type="button"
            variant="outline"
            size="sm"
            class="w-full sm:w-auto"
            @click="emit('changePartner')"
          >
            Change {{ partnerLabel }}
          </Button>
        </div>

        <ol class="flex gap-1 overflow-x-auto pb-1 sm:gap-2" aria-label="Agreement wizard progress">
          <li
            v-for="(step, index) in WIZARD_STEPS"
            :key="step.id"
            class="flex min-w-[4.5rem] flex-1 flex-col items-center gap-1 sm:min-w-0"
          >
            <span
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-xs font-semibold"
              :class="
                index < stepIndex
                  ? 'border-primary bg-primary text-primary-foreground'
                  : index === stepIndex
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-border bg-muted text-muted-foreground'
              "
            >
              <Check v-if="index < stepIndex" class="h-4 w-4" />
              <span v-else>{{ index + 1 }}</span>
            </span>
            <span
              class="hidden text-center text-[10px] font-medium leading-tight text-muted-foreground sm:block sm:text-xs"
              :class="index === stepIndex ? 'text-foreground' : ''"
            >
              {{ step.label }}
            </span>
          </li>
        </ol>
      </CardContent>
    </Card>

    <!-- Partner -->
    <Card v-if="wizardStep === 'partner'" class="border-border/80 shadow-sm">
      <CardContent class="space-y-5 p-4 sm:p-6">
        <div class="space-y-2">
          <h3 class="text-xl font-semibold text-foreground">Choose your {{ partnerLabel.toLowerCase() }}</h3>
          <p class="text-sm text-muted-foreground">
            Search approved organizations you can send this agreement to. Partners with an active or pending agreement
            cannot be selected again.
          </p>
        </div>

        <div v-if="lockPartner && selectedPartnerProfile" class="rounded-2xl border border-border bg-muted/40 p-4">
          <div class="flex items-start gap-3">
            <div class="rounded-2xl bg-primary/10 p-3 text-primary">
              <Building2 v-if="requesterRole === 'school'" class="h-5 w-5" />
              <BriefcaseBusiness v-else class="h-5 w-5" />
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">Selected {{ partnerLabel }}</p>
              <p class="text-lg font-semibold text-foreground">
                {{ selectedPartnerProfile.orgName || selectedPartnerProfile.displayName }}
              </p>
              <p class="text-sm text-muted-foreground">{{ selectedPartnerProfile.email || 'Directory profile' }}</p>
            </div>
          </div>
        </div>

        <template v-else>
          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              v-model="partnerSearch"
              class="pl-9"
              :placeholder="partnerLabel === 'Company' ? 'Search companies...' : 'Search schools...'"
            />
          </div>
          <p class="text-xs text-muted-foreground">Example: type part of the company or school name, or an email address.</p>

          <div v-if="filteredPartners.length" class="max-h-[min(420px,50vh)] space-y-2 overflow-y-auto pr-1">
            <button
              v-for="partner in filteredPartners"
              :key="partner.uid"
              type="button"
              class="flex w-full items-start gap-3 rounded-xl border p-4 text-left transition"
              :class="
                partnerUserId === partner.uid
                  ? 'border-primary bg-primary/5'
                  : 'border-border hover:border-primary/50 hover:bg-accent/50'
              "
              @click="selectPartner(partner)"
            >
              <div class="rounded-xl bg-primary/10 p-2 text-primary">
                <Building2 v-if="requesterRole === 'school'" class="h-4 w-4" />
                <BriefcaseBusiness v-else class="h-4 w-4" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-semibold text-foreground">{{ partner.orgName || partner.displayName }}</p>
                  <span
                    v-if="partnerStatusLabel(partner)"
                    class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                  >
                    {{ partnerStatusLabel(partner) }}
                  </span>
                </div>
                <p class="text-sm text-muted-foreground">{{ partner.email || 'Directory profile' }}</p>
              </div>
            </button>
          </div>
          <p v-else class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
            No matching {{ partnerLabel === 'Company' ? 'companies' : 'schools' }} found. Try a different search.
          </p>
        </template>

        <p v-if="mergedFieldError('partnerUserId')" class="text-sm text-destructive">
          {{ mergedFieldError('partnerUserId') }}
        </p>
      </CardContent>
    </Card>

    <!-- Agreement type -->
    <Card v-else-if="wizardStep === 'type'" class="border-border/80 shadow-sm">
      <CardContent class="space-y-5 p-4 sm:p-6">
        <div class="space-y-2">
          <h3 class="text-xl font-semibold text-foreground">Pick an agreement type</h3>
          <p class="text-sm text-muted-foreground">
            We suggest starting with the OJT Memorandum of Agreement template. You can change the type before sending.
          </p>
          <p class="text-xs text-muted-foreground">Example: OJT Memorandum of Agreement for internship placements.</p>
        </div>

        <div v-if="loadingTypes" class="flex items-center gap-2 text-sm text-muted-foreground">
          <LoaderCircle class="h-4 w-4 animate-spin" />
          Loading agreement types...
        </div>

        <div v-else-if="selectedTypeLocked" class="rounded-xl border border-input bg-muted/50 px-4 py-3 text-sm">
          {{ selectedTypeCard?.name || initialContractTypeName }}
        </div>

        <div v-else class="grid gap-3 sm:grid-cols-2">
          <button
            v-for="type in contractTypeCards"
            :key="type.id"
            type="button"
            class="rounded-2xl border p-4 text-left transition"
            :class="
              selectedTypeId === type.id
                ? 'border-primary bg-primary/5 shadow-sm'
                : 'border-border hover:border-primary/40'
            "
            @click="selectedTypeId = type.id"
          >
            <div class="flex items-start gap-3">
              <div class="rounded-xl bg-sky-100 p-2 text-sky-700">
                <Layers3 class="h-5 w-5" />
              </div>
              <div>
                <p class="font-semibold text-foreground">{{ type.name }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                  {{ type.description || 'Complete the fields required for this agreement.' }}
                </p>
                <p v-if="type.slug === OJT_MOA_TEMPLATE_SLUG" class="mt-2 text-xs font-medium text-primary">
                  Recommended template · prefills subject and purpose
                </p>
              </div>
            </div>
          </button>
        </div>

        <p v-if="!contractTypeCards.length && !loadingTypes" class="text-sm text-muted-foreground">
          No agreement types are available for this partner yet.
        </p>
        <p v-if="mergedFieldError('selectedTypeId')" class="text-sm text-destructive">
          {{ mergedFieldError('selectedTypeId') }}
        </p>
      </CardContent>
    </Card>

    <!-- Details -->
    <Card v-else-if="wizardStep === 'details'" class="border-border/80 shadow-sm">
      <CardContent class="space-y-6 p-4 sm:p-6">
        <div class="space-y-2">
          <h3 class="text-xl font-semibold text-foreground">Fill in agreement details</h3>
          <p class="text-sm text-muted-foreground">One section at a time. Required fields are marked with *.</p>
        </div>

        <FormItem>
          <FormLabel>Reference number</FormLabel>
          <FormControl>
            <div class="flex min-h-11 items-center rounded-xl border border-input bg-muted/50 px-4 text-sm">
              {{ referencePreview }}
            </div>
          </FormControl>
          <p class="text-xs text-muted-foreground">Assigned automatically when you send the agreement.</p>
          <p class="text-xs text-muted-foreground">Example: CTR-2026-000142</p>
        </FormItem>

        <FormItem>
          <FormLabel>Subject <span class="text-red-500">*</span></FormLabel>
          <FormControl>
            <Input v-model="subject" type="text" placeholder="OJT Memorandum of Agreement" />
          </FormControl>
          <p class="text-xs text-muted-foreground">{{ staticGuidance.subject.help }}</p>
          <p class="text-xs text-muted-foreground">Example: {{ staticGuidance.subject.example }}</p>
          <p v-if="mergedFieldError('subject')" class="text-sm text-destructive">{{ mergedFieldError('subject') }}</p>
        </FormItem>

        <FormItem>
          <FormLabel>Purpose <span class="text-red-500">*</span></FormLabel>
          <FormControl>
            <Textarea v-model="purpose" :rows="4" placeholder="Describe the purpose of this agreement" />
          </FormControl>
          <p class="text-xs text-muted-foreground">{{ staticGuidance.purpose.help }}</p>
          <p class="text-xs text-muted-foreground">Example: {{ staticGuidance.purpose.example }}</p>
          <p v-if="mergedFieldError('purpose')" class="text-sm text-destructive">{{ mergedFieldError('purpose') }}</p>
        </FormItem>

        <div class="grid gap-4 sm:grid-cols-2">
          <FormItem>
            <FormLabel>Start date</FormLabel>
            <FormControl>
              <Input v-model="startDate" type="date" />
            </FormControl>
            <p class="text-xs text-muted-foreground">{{ staticGuidance.startDate.help }}</p>
            <p class="text-xs text-muted-foreground">Example: {{ staticGuidance.startDate.example }}</p>
          </FormItem>
          <FormItem>
            <FormLabel>End date</FormLabel>
            <FormControl>
              <Input v-model="endDate" type="date" />
            </FormControl>
            <p class="text-xs text-muted-foreground">{{ staticGuidance.endDate.help }}</p>
            <p class="text-xs text-muted-foreground">Example: {{ staticGuidance.endDate.example }}</p>
            <p v-if="mergedFieldError('endDate')" class="text-sm text-destructive">{{ mergedFieldError('endDate') }}</p>
          </FormItem>
        </div>

        <div v-if="selectedTypeCard" class="space-y-4 border-t border-border pt-4">
          <div class="flex items-center gap-3">
            <div class="rounded-xl bg-muted p-2 text-muted-foreground">
              <FilePlus2 class="h-5 w-5" />
            </div>
            <div>
              <h4 class="font-semibold text-foreground">{{ selectedTypeCard.name }}</h4>
              <p class="text-sm text-muted-foreground">{{ selectedTypeCard.description }}</p>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <template v-for="field in selectedTypeCard.fieldsSchema" :key="field.key">
              <FormItem :class="field.type === 'textarea' || field.type === 'multiselect' ? 'sm:col-span-2' : ''">
                <FormLabel>
                  {{ field.label }}
                  <span v-if="field.required" class="ml-1 text-red-500">*</span>
                </FormLabel>
                <FormControl>
                  <Input
                    v-if="['text', 'email', 'number', 'date'].includes(field.type)"
                    :type="field.type === 'text' ? 'text' : field.type"
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
                    v-else-if="field.type === 'multiselect' && isCourseField(field) && noSharedCoursesForPartner"
                    class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-foreground"
                  >
                    <template v-if="requesterRole === 'school'">
                      Your school and this company have no courses in common. Ask the company to add the programs they
                      accept.
                    </template>
                    <template v-else>
                      Your company and this school have no courses in common. Ask the school to add matching programs, or
                      update the courses you accept in Settings.
                    </template>
                  </div>
                  <div
                    v-else-if="field.type === 'multiselect'"
                    class="space-y-3 rounded-xl border border-border bg-muted/40 p-3"
                  >
                    <Select
                      :model-value="''"
                      @update:modelValue="
                        (value) =>
                          value &&
                          !selectedMultiValues(field.key).includes(String(value)) &&
                          toggleMultiSelectValue(field.key, String(value))
                      "
                    >
                      <option value="">Select an option</option>
                      <option
                        v-for="option in (field.options || []).filter((item) => !selectedMultiValues(field.key).includes(item))"
                        :key="option"
                        :value="option"
                      >
                        {{ option }}
                      </option>
                    </Select>
                    <div v-if="selectedMultiValues(field.key).length" class="flex flex-wrap gap-2">
                      <span
                        v-for="option in selectedMultiValues(field.key)"
                        :key="option"
                        class="inline-flex items-center gap-2 rounded-full border border-primary bg-primary px-3 py-1 text-sm text-primary-foreground"
                      >
                        {{ option }}
                        <button type="button" class="text-xs" @click="toggleMultiSelectValue(field.key, option)">×</button>
                      </span>
                    </div>
                  </div>
                </FormControl>
                <p class="text-xs text-muted-foreground">{{ fieldGuidance(field).help }}</p>
                <p class="text-xs text-muted-foreground">Example: {{ fieldGuidance(field).example }}</p>
                <p v-if="dynamicFieldError(field.key)" class="text-sm text-destructive">
                  {{ dynamicFieldError(field.key) }}
                </p>
              </FormItem>
            </template>
          </div>
        </div>

        <FormItem>
          <FormLabel>Notes</FormLabel>
          <FormControl>
            <Textarea v-model="notes" :rows="3" placeholder="Optional message for your partner" />
          </FormControl>
          <p class="text-xs text-muted-foreground">{{ staticGuidance.notes.help }}</p>
          <p class="text-xs text-muted-foreground">Example: {{ staticGuidance.notes.example }}</p>
        </FormItem>

        <FormItem>
          <FormLabel>Attachments</FormLabel>
          <FormControl>
            <label
              class="flex cursor-pointer flex-col gap-2 rounded-xl border border-dashed border-border bg-muted/30 p-4 hover:border-primary/40"
            >
              <div class="flex items-center gap-3 text-sm">
                <Paperclip class="h-4 w-4 text-primary" />
                <span>{{ selectedFilesLabel }}</span>
              </div>
              <input type="file" class="hidden" multiple @change="onFileChange" />
            </label>
          </FormControl>
          <p class="text-xs text-muted-foreground">{{ staticGuidance.attachments.help }}</p>
          <p class="text-xs text-muted-foreground">Example: {{ staticGuidance.attachments.example }}</p>
          <p class="text-xs text-amber-700 dark:text-amber-400">
            Attachments are not saved in your draft if you leave this page — re-upload before sending if needed.
          </p>
        </FormItem>
      </CardContent>
    </Card>

    <!-- Review -->
    <Card v-else-if="wizardStep === 'review'" class="border-border/80 shadow-sm">
      <CardContent class="space-y-5 p-4 sm:p-6">
        <div class="space-y-2">
          <h3 class="text-xl font-semibold text-foreground">Review your agreement</h3>
          <p class="text-sm text-muted-foreground">Check everything below. Use Back to edit any step.</p>
        </div>

        <dl class="divide-y divide-border rounded-xl border border-border text-sm">
          <div class="grid gap-1 px-4 py-3 sm:grid-cols-3">
            <dt class="font-medium text-muted-foreground">{{ partnerLabel }}</dt>
            <dd class="sm:col-span-2">{{ partnerDisplayName }}</dd>
          </div>
          <div class="grid gap-1 px-4 py-3 sm:grid-cols-3">
            <dt class="font-medium text-muted-foreground">Agreement type</dt>
            <dd class="sm:col-span-2">{{ selectedTypeCard?.name || 'Not selected' }}</dd>
          </div>
          <div class="grid gap-1 px-4 py-3 sm:grid-cols-3">
            <dt class="font-medium text-muted-foreground">Subject</dt>
            <dd class="sm:col-span-2">{{ subject || '—' }}</dd>
          </div>
          <div class="grid gap-1 px-4 py-3 sm:grid-cols-3">
            <dt class="font-medium text-muted-foreground">Purpose</dt>
            <dd class="whitespace-pre-wrap sm:col-span-2">{{ purpose || '—' }}</dd>
          </div>
          <div class="grid gap-1 px-4 py-3 sm:grid-cols-3">
            <dt class="font-medium text-muted-foreground">Dates</dt>
            <dd class="sm:col-span-2">{{ formatDateLabel(startDate) }} – {{ formatDateLabel(endDate) }}</dd>
          </div>
          <template v-for="field in selectedTypeCard?.fieldsSchema ?? []" :key="field.key">
            <div
              v-if="dynamicFields[field.key] !== undefined && dynamicFields[field.key] !== ''"
              class="grid gap-1 px-4 py-3 sm:grid-cols-3"
            >
              <dt class="font-medium text-muted-foreground">{{ field.label }}</dt>
              <dd class="sm:col-span-2">
                {{
                  Array.isArray(dynamicFields[field.key])
                    ? (dynamicFields[field.key] as string[]).join(', ')
                    : String(dynamicFields[field.key])
                }}
              </dd>
            </div>
          </template>
          <div v-if="notes" class="grid gap-1 px-4 py-3 sm:grid-cols-3">
            <dt class="font-medium text-muted-foreground">Notes</dt>
            <dd class="whitespace-pre-wrap sm:col-span-2">{{ notes }}</dd>
          </div>
          <div class="grid gap-1 px-4 py-3 sm:grid-cols-3">
            <dt class="font-medium text-muted-foreground">Attachments</dt>
            <dd class="sm:col-span-2">{{ selectedFilesLabel }}</dd>
          </div>
        </dl>
      </CardContent>
    </Card>

    <!-- Send -->
    <Card v-else-if="wizardStep === 'send'" class="border-border/80 shadow-sm">
      <CardContent class="space-y-5 p-4 sm:p-6">
        <div class="space-y-2">
          <h3 class="text-xl font-semibold text-foreground">Send to {{ partnerDisplayName }}</h3>
          <p class="text-sm text-muted-foreground">
            When you send this agreement, {{ partnerDisplayName }} gets an in-app notification and email (if enabled).
            The agreement status becomes
            <strong class="font-medium text-foreground">Waiting for {{ partnerDisplayName }} to respond</strong>
            until they accept or reject it.
          </p>
          <p class="text-xs text-muted-foreground">
            Example: after sending, you can track progress from the Agreements page.
          </p>
        </div>

        <div class="rounded-xl border border-primary/30 bg-primary/5 p-4 text-sm text-foreground">
          <p class="font-medium">Ready to send?</p>
          <p class="mt-2 text-muted-foreground">
            Subject: {{ subject }} · Type: {{ selectedTypeCard?.name || 'Agreement' }}
          </p>
        </div>

        <p v-if="error" class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          {{ error }}
        </p>
      </CardContent>
    </Card>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
      <Button v-if="stepIndex > 0" type="button" variant="outline" class="w-full sm:w-auto" @click="goBack">
        <ChevronLeft class="h-4 w-4" />
        Back
      </Button>
      <div class="flex flex-col gap-2 sm:ml-auto sm:flex-row">
        <Button
          v-if="wizardStep !== 'send'"
          type="button"
          class="w-full sm:w-auto"
          :disabled="loadingTypes"
          @click="goNext"
        >
          Continue
          <ChevronRight class="h-4 w-4" />
        </Button>
        <Button
          v-else
          type="button"
          class="w-full sm:min-w-[200px]"
          :disabled="submitting"
          @click="onSendAgreement"
        >
          <LoaderCircle v-if="submitting" class="h-4 w-4 animate-spin" />
          <Send v-else class="h-4 w-4" />
          {{ submitting ? 'Sending...' : 'Send agreement' }}
        </Button>
      </div>
    </div>
  </div>
</template>
