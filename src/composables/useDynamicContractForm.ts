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

  watch(selectedType, (type) => {
    dynamicFields.value = { ...(type?.defaultValues ?? {}) }
  })

  function chooseType(preferredTypeName?: string): boolean {
    const preferredName = preferredTypeName?.trim().toLowerCase()
    if (preferredName) {
      const matched = contractTypes.value.find((item) => item.name.trim().toLowerCase() === preferredName)
      if (matched) {
        selectedTypeId.value = matched.id
        return true
      }

      selectedTypeId.value = ''
      return false
    }

    selectedTypeId.value = contractTypes.value[0]?.id ?? ''
    return true
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
      const matched = chooseType(preferredTypeName)
      if (!matched && preferredTypeName) {
        error.value = `The selected contract type "${preferredTypeName}" is not available for this partner.`
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
        subject: subject.value,
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
