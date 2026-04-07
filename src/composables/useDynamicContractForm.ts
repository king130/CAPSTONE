import { computed, ref, watch } from 'vue'
import { fetchContractTypes, submitDynamicContract, type DynamicContractType } from '@/services/dynamicContracts'

export function useDynamicContractForm(requestedByRole: 'school' | 'company') {
  const partnerUserId = ref('')
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

  const selectedType = computed(() => contractTypes.value.find((item) => item.id === selectedTypeId.value) ?? null)

  function defaultSubjectForType(type: { name: string } | null): string {
    if (!type) return ''
    const year = new Date().getFullYear()
    return `${type.name} – ${year}`
  }

  watch(selectedType, (type) => {
    dynamicFields.value = { ...(type?.defaultValues ?? {}) }
    if (type && !subject.value.trim()) {
      subject.value = defaultSubjectForType(type)
    }
  })

  function pickDefaultContractType(): (typeof contractTypes.value)[0] | undefined {
    const list = contractTypes.value
    return (
      list.find((t) => t.slug === 'ojt-moa')
      ?? list.find((t) => /moa|memorandum of agreement/i.test(t.name))
      ?? list[0]
    )
  }

  function chooseType(preferredTypeName?: string): boolean {
    const list = contractTypes.value
    const fallback = pickDefaultContractType()

    const preferred = preferredTypeName?.trim().toLowerCase()
    if (preferred) {
      const matched =
        list.find((item) => item.name.trim().toLowerCase() === preferred)
        ?? list.find((item) => item.slug === 'ojt-moa' && (preferred.includes('moa') || preferred.includes('ojt')))
        ?? list.find((item) => item.name.toLowerCase().includes('memorandum'))
        ?? list.find((item) => item.name.toLowerCase().includes(preferred))

      if (matched) {
        selectedTypeId.value = matched.id
        return true
      }

      if (fallback) {
        selectedTypeId.value = fallback.id
        return true
      }

      selectedTypeId.value = ''
      return false
    }

    selectedTypeId.value = fallback?.id ?? ''
    return Boolean(fallback)
  }

  async function loadTypes(preferredTypeName?: string) {
    if (!partnerUserId.value) {
      contractTypes.value = []
      selectedTypeId.value = ''
      return
    }

    loadingTypes.value = true
    error.value = null
    try {
      contractTypes.value = await fetchContractTypes(partnerUserId.value)
      const picked = chooseType(preferredTypeName)
      if (!picked) {
        error.value = 'No contract templates are available for this partner.'
      }
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Could not load contract types.'
      contractTypes.value = []
      selectedTypeId.value = ''
    } finally {
      loadingTypes.value = false
    }
  }

  async function submit() {
    submitting.value = true
    error.value = null
    try {
      return await submitDynamicContract({
        requestedByRole,
        subject: subject.value.trim() || defaultSubjectForType(selectedType.value),
        contractTypeId: selectedTypeId.value || undefined,
        contractTypeLabel: selectedType.value?.name,
        schoolId: requestedByRole === 'company' ? partnerUserId.value : undefined,
        companyId: requestedByRole === 'school' ? partnerUserId.value : undefined,
        purpose: purpose.value || undefined,
        startDate: startDate.value || undefined,
        endDate: endDate.value || undefined,
        notes: notes.value || undefined,
        dynamicFields: dynamicFields.value,
        files: files.value,
      })
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Contract request failed.'
      throw err
    } finally {
      submitting.value = false
    }
  }

  return {
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
  }
}
