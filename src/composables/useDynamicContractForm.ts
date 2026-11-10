import { computed, ref, watch, type Ref } from 'vue'
import {
  AgreementSubmitError,
  fetchContractTypes,
  getAgreementSubmitErrorMessage,
  OJT_MOA_DEFAULT_PURPOSE,
  OJT_MOA_DEFAULT_SUBJECT,
  OJT_MOA_TEMPLATE_SLUG,
  submitDynamicContract,
  type DynamicContractType,
} from '@/services/dynamicContracts'

export type AgreementWizardStep = 'partner' | 'type' | 'details' | 'review' | 'send'

export interface AgreementDraftPayload {
  version: 1
  step: AgreementWizardStep
  partnerUserId: string
  selectedTypeId: string
  subject: string
  purpose: string
  startDate: string
  endDate: string
  notes: string
  dynamicFields: Record<string, unknown>
  savedAt: string
}

export function agreementDraftStorageKey(userId: string) {
  return `agreement-draft:${userId}`
}

function readDraft(key: string): AgreementDraftPayload | null {
  if (typeof window === 'undefined') return null
  try {
    const raw = window.localStorage.getItem(key)
    if (!raw) return null
    const parsed = JSON.parse(raw) as AgreementDraftPayload
    if (parsed?.version !== 1) return null
    return parsed
  } catch {
    return null
  }
}

function writeDraft(key: string, payload: AgreementDraftPayload) {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(key, JSON.stringify(payload))
}

export function clearAgreementDraft(userId: string) {
  if (typeof window === 'undefined') return
  window.localStorage.removeItem(agreementDraftStorageKey(userId))
}

export function useDynamicContractForm(
  requestedByRole: 'school' | 'company',
  options?: { draftUserId?: Ref<string | undefined> | (() => string | undefined) },
) {
  const partnerUserId = ref('')
  const wizardStep = ref<AgreementWizardStep>('partner')
  const loadingTypes = ref(false)
  const submitting = ref(false)
  const contractTypes = ref<DynamicContractType[]>([])
  const selectedTypeId = ref('')
  const subject = ref('')
  const purpose = ref('')
  const startDate = ref('')
  const endDate = ref('')
  const notes = ref('')
  const dynamicFields = ref<Record<string, unknown>>({})
  const files = ref<File[]>([])
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string>>({})
  const draftRestored = ref(false)
  const draftSavedAt = ref<Date | null>(null)
  const skipTypeReset = ref(false)
  const templateDefaultsApplied = ref(false)

  const selectedType = computed(() => contractTypes.value.find((item) => item.id === selectedTypeId.value) ?? null)

  function resolveDraftUserId() {
    const source = options?.draftUserId
    if (!source) return ''
    if (typeof source === 'function') return source()?.trim() ?? ''
    return source.value?.trim() ?? ''
  }

  function applyOjtTemplateDefaults(force = false) {
    const type = selectedType.value
    if (!type || type.slug !== OJT_MOA_TEMPLATE_SLUG) return
    if (force || !subject.value.trim()) subject.value = OJT_MOA_DEFAULT_SUBJECT
    if (force || !purpose.value.trim()) purpose.value = OJT_MOA_DEFAULT_PURPOSE
    templateDefaultsApplied.value = true
  }

  watch(selectedType, (type, previous) => {
    if (skipTypeReset.value) {
      skipTypeReset.value = false
      return
    }
    if (!type) return
    dynamicFields.value = { ...(type.defaultValues ?? {}) }
    if (type.slug === OJT_MOA_TEMPLATE_SLUG && (!previous || previous.id !== type.id)) {
      applyOjtTemplateDefaults(!templateDefaultsApplied.value)
    }
  })

  function chooseType(preferredTypeNameOrSlug?: string): boolean {
    const preferred = preferredTypeNameOrSlug?.trim().toLowerCase()
    if (preferred) {
      const matched =
        contractTypes.value.find((item) => item.slug.trim().toLowerCase() === preferred) ??
        contractTypes.value.find((item) => item.name.trim().toLowerCase() === preferred)
      if (matched) {
        selectedTypeId.value = matched.id
        return true
      }
      selectedTypeId.value = ''
      return false
    }

    const ojt = contractTypes.value.find((item) => item.slug === OJT_MOA_TEMPLATE_SLUG)
    selectedTypeId.value = ojt?.id ?? contractTypes.value[0]?.id ?? ''
    return true
  }

  async function loadTypes(preferredTypeNameOrSlug?: string) {
    if (!partnerUserId.value) {
      contractTypes.value = []
      selectedTypeId.value = ''
      return
    }

    loadingTypes.value = true
    error.value = null
    try {
      contractTypes.value = await fetchContractTypes(partnerUserId.value)
      const matched = chooseType(preferredTypeNameOrSlug ?? OJT_MOA_TEMPLATE_SLUG)
      if (!matched && preferredTypeNameOrSlug) {
        error.value = `The agreement type "${preferredTypeNameOrSlug}" is not available for this partner.`
      }
      applyOjtTemplateDefaults()
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Could not load agreement types.'
      contractTypes.value = []
      selectedTypeId.value = ''
    } finally {
      loadingTypes.value = false
    }
  }

  function buildDraftPayload(): AgreementDraftPayload {
    return {
      version: 1,
      step: wizardStep.value,
      partnerUserId: partnerUserId.value,
      selectedTypeId: selectedTypeId.value,
      subject: subject.value,
      purpose: purpose.value,
      startDate: startDate.value,
      endDate: endDate.value,
      notes: notes.value,
      dynamicFields: { ...dynamicFields.value },
      savedAt: new Date().toISOString(),
    }
  }

  function persistDraft() {
    const userId = resolveDraftUserId()
    if (!userId) return
    const hasContent =
      partnerUserId.value ||
      subject.value.trim() ||
      purpose.value.trim() ||
      notes.value.trim() ||
      startDate.value ||
      endDate.value ||
      Object.keys(dynamicFields.value).length
    if (!hasContent) return
    writeDraft(agreementDraftStorageKey(userId), buildDraftPayload())
    draftSavedAt.value = new Date()
  }

  async function restoreDraft(): Promise<boolean> {
    const userId = resolveDraftUserId()
    if (!userId) return false
    const draft = readDraft(agreementDraftStorageKey(userId))
    if (!draft) return false

    skipTypeReset.value = true
    wizardStep.value = draft.step
    partnerUserId.value = draft.partnerUserId
    subject.value = draft.subject
    purpose.value = draft.purpose
    startDate.value = draft.startDate
    endDate.value = draft.endDate
    notes.value = draft.notes
    dynamicFields.value = { ...draft.dynamicFields }

    if (draft.partnerUserId) {
      await loadTypes(OJT_MOA_TEMPLATE_SLUG)
      if (draft.selectedTypeId && contractTypes.value.some((item) => item.id === draft.selectedTypeId)) {
        skipTypeReset.value = true
        selectedTypeId.value = draft.selectedTypeId
        dynamicFields.value = { ...draft.dynamicFields }
      }
    }

    draftRestored.value = true
    draftSavedAt.value = draft.savedAt ? new Date(draft.savedAt) : new Date()
    return true
  }

  function clearFieldError(key: string) {
    if (!fieldErrors.value[key]) return
    const next = { ...fieldErrors.value }
    delete next[key]
    fieldErrors.value = next
  }

  function setFieldErrors(errors: Record<string, string>) {
    fieldErrors.value = { ...errors }
  }

  async function submit() {
    if (!subject.value.trim() || !purpose.value.trim()) {
      const next: Record<string, string> = {}
      if (!subject.value.trim()) next.subject = 'Subject is required.'
      if (!purpose.value.trim()) next.purpose = 'Purpose is required.'
      fieldErrors.value = { ...fieldErrors.value, ...next }
      error.value = 'Please fill in the required fields before sending.'
      throw new AgreementSubmitError(error.value, next)
    }

    submitting.value = true
    error.value = null
    fieldErrors.value = {}
    try {
      const response = await submitDynamicContract({
        requestedByRole,
        subject: subject.value.trim(),
        contractTypeId: selectedTypeId.value || undefined,
        contractTypeLabel: selectedType.value?.name,
        schoolId: requestedByRole === 'company' ? partnerUserId.value : undefined,
        companyId: requestedByRole === 'school' ? partnerUserId.value : undefined,
        purpose: purpose.value.trim(),
        startDate: startDate.value || undefined,
        endDate: endDate.value || undefined,
        notes: notes.value || undefined,
        dynamicFields: dynamicFields.value,
        files: files.value,
      })
      const userId = resolveDraftUserId()
      if (userId) clearAgreementDraft(userId)
      return response
    } catch (err) {
      if (err instanceof AgreementSubmitError) {
        fieldErrors.value = { ...fieldErrors.value, ...err.fieldErrors }
        error.value = err.message
      } else {
        error.value = getAgreementSubmitErrorMessage(err, 'Agreement request failed.')
      }
      throw err
    } finally {
      submitting.value = false
    }
  }

  let draftTimer: ReturnType<typeof setTimeout> | null = null
  watch(
    [wizardStep, partnerUserId, selectedTypeId, subject, purpose, startDate, endDate, notes, dynamicFields],
    () => {
      if (draftTimer) clearTimeout(draftTimer)
      draftTimer = setTimeout(() => persistDraft(), 400)
    },
    { deep: true },
  )

  return {
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
    persistDraft,
    clearFieldError,
    setFieldErrors,
    applyOjtTemplateDefaults,
    submit,
  }
}
