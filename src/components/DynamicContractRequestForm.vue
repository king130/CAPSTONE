<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import Swal from 'sweetalert2'
import { useDynamicContractForm } from '@/composables/useDynamicContractForm'
import type { PublicProfile } from '@/services/profilesPublic'

const props = defineProps<{
  requesterRole: 'school' | 'company'
  partners: PublicProfile[]
  initialPartnerId?: string
  selectedPartner?: PublicProfile | null
  lockPartner?: boolean
  initialContractTypeName?: string
  lockContractType?: boolean
}>()

const emit = defineEmits<{
  changePartner: []
  submitted: []
}>()

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
  try {
    await submit()
    await Swal.fire({
      icon: 'success',
      title: 'Contract Request Sent',
      text: 'Your contract request has been submitted successfully.',
      confirmButtonColor: '#2563eb',
    })
    emit('submitted')
  } catch {
    await Swal.fire({
      icon: 'error',
      title: 'Submission Failed',
      text: error.value ?? 'Could not create contract request.',
      confirmButtonColor: '#2563eb',
    })
  }
}
</script>

<template>
  <form class="dynamic-contract-form" @submit.prevent="onSubmit">
    <section class="hero-card">
      <div class="hero-copy">
        <p class="eyebrow">Dynamic Contract Builder</p>
        <h2 class="hero-title">Build the contract around the selected partner</h2>
        <p class="hero-text">
          Pick a {{ partnerLabel.toLowerCase() }}, then the system loads the matching contract types and only the fields needed for that agreement.
        </p>
      </div>

      <div v-if="lockPartner && selectedPartnerProfile" class="partner-card locked">
        <div class="partner-badge">Selected {{ partnerLabel }}</div>
        <div class="partner-name">{{ selectedPartnerProfile.orgName || selectedPartnerProfile.displayName }}</div>
        <div class="partner-meta">{{ selectedPartnerProfile.email || 'Directory profile' }}</div>
        <button type="button" class="ghost-btn" @click="emit('changePartner')">Change {{ partnerLabel }}</button>
      </div>

      <label v-else class="partner-picker">
        <span class="field-title">{{ partnerLabel }}</span>
        <select v-model="partnerUserId" class="input" @change="onPartnerChange">
          <option value="">Select {{ partnerLabel.toLowerCase() }}</option>
          <option v-for="partner in partners" :key="partner.uid" :value="partner.uid">
            {{ partner.orgName || partner.displayName }}
          </option>
        </select>
      </label>
    </section>

    <div class="form-grid">
      <label v-if="!selectedTypeLocked" class="field">
        <span>Contract Type</span>
        <select v-model="selectedTypeId" class="input" :disabled="loadingTypes || !contractTypes.length">
          <option value="">Select contract type</option>
          <option v-for="type in contractTypes" :key="type.id" :value="type.id">
            {{ type.name }}
          </option>
        </select>
      </label>

      <div v-else class="field">
        <span>Contract Type</span>
        <div class="input input-readonly">{{ selectedType?.name || initialContractTypeName }}</div>
      </div>

      <label class="field field-full">
        <span>Subject</span>
        <input v-model="subject" class="input" type="text" placeholder="Contract subject" />
      </label>

      <label class="field field-full">
        <span>Purpose</span>
        <textarea v-model="purpose" class="input textarea" rows="3" placeholder="Explain the contract purpose" />
      </label>

      <label class="field">
        <span>Start Date</span>
        <input v-model="startDate" class="input" type="date" />
      </label>

      <label class="field">
        <span>End Date</span>
        <input v-model="endDate" class="input" type="date" />
      </label>
    </div>

    <div v-if="selectedType" class="schema-block">
      <h3>{{ selectedType.name }}</h3>
      <p class="schema-description">{{ selectedType.description }}</p>

      <div class="form-grid">
        <template v-for="field in selectedType.fieldsSchema" :key="field.key">
          <label class="field" :class="{ 'field-full': field.type === 'textarea' || field.type === 'multiselect' }">
            <span>{{ field.label }}</span>

            <input
              v-if="['text', 'email', 'number', 'date'].includes(field.type)"
              :type="field.type === 'text' ? 'text' : field.type"
              class="input"
              :required="field.required"
              :placeholder="field.placeholder || field.label"
              :value="String(dynamicFields[field.key] ?? '')"
              @input="updateFieldValue(field.key, ($event.target as HTMLInputElement).value)"
            />

            <textarea
              v-else-if="field.type === 'textarea'"
              class="input textarea"
              rows="3"
              :required="field.required"
              :placeholder="field.placeholder || field.label"
              :value="String(dynamicFields[field.key] ?? '')"
              @input="updateFieldValue(field.key, ($event.target as HTMLTextAreaElement).value)"
            />

            <select
              v-else-if="field.type === 'select'"
              class="input"
              :required="field.required"
              :value="String(dynamicFields[field.key] ?? '')"
              @change="updateFieldValue(field.key, ($event.target as HTMLSelectElement).value)"
            >
              <option value="">Select {{ field.label.toLowerCase() }}</option>
              <option v-for="option in field.options || []" :key="option" :value="option">{{ option }}</option>
            </select>

            <select
              v-else-if="field.type === 'multiselect'"
              class="input"
              multiple
              :required="field.required"
              @change="updateFieldValue(field.key, Array.from(($event.target as HTMLSelectElement).selectedOptions).map((option) => option.value))"
            >
              <option
                v-for="option in field.options || []"
                :key="option"
                :value="option"
                :selected="Array.isArray(dynamicFields[field.key]) && (dynamicFields[field.key] as string[]).includes(option)"
              >
                {{ option }}
              </option>
            </select>
          </label>
        </template>
      </div>
    </div>

    <label class="field field-full">
      <span>Notes</span>
      <textarea v-model="notes" class="input textarea" rows="3" placeholder="Optional notes" />
    </label>

    <label class="field field-full">
      <span>Attachments</span>
      <input type="file" class="input" multiple @change="onFileChange" />
    </label>

    <p v-if="error" class="error-copy">{{ error }}</p>

    <div class="actions">
      <button type="submit" class="submit-btn" :disabled="!canSubmit">
        {{ submitting ? 'Submitting...' : 'Send Contract Request' }}
      </button>
    </div>
  </form>
</template>

<style scoped>
.dynamic-contract-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px;
  background: #fff;
  border: 1px solid #dbe3ee;
  border-radius: 24px;
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
}

.hero-card {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(280px, 1fr);
  gap: 16px;
  padding: 18px;
  border-radius: 20px;
  background:
    radial-gradient(circle at top left, rgba(37, 99, 235, 0.14), transparent 30%),
    linear-gradient(135deg, #eff6ff 0%, #f8fafc 55%, #ffffff 100%);
  border: 1px solid #cfe0ff;
}

.eyebrow {
  margin: 0 0 8px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-size: 0.75rem;
  font-weight: 800;
  color: #2563eb;
}

.hero-title {
  margin: 0;
  font-size: 1.65rem;
  line-height: 1.1;
  color: #0f172a;
}

.hero-text {
  margin: 10px 0 0;
  color: #475569;
  max-width: 56ch;
}

.partner-card,
.partner-picker {
  align-self: stretch;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 8px;
  padding: 16px;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(148, 163, 184, 0.22);
}

.partner-card.locked {
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.partner-badge,
.field-title {
  font-size: 0.78rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
}

.partner-name {
  font-size: 1.15rem;
  font-weight: 800;
  color: #0f172a;
}

.partner-meta {
  color: #64748b;
}

.ghost-btn {
  align-self: flex-start;
  margin-top: 4px;
  border: 1px solid #bfdbfe;
  background: #ffffff;
  color: #1d4ed8;
  border-radius: 999px;
  padding: 9px 12px;
  font-weight: 700;
  cursor: pointer;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.field-full {
  grid-column: 1 / -1;
}

.input {
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  padding: 12px 14px;
  font: inherit;
  background: #fff;
}

.input-readonly {
  color: #0f172a;
  background: #f8fafc;
}

.textarea {
  resize: vertical;
}

.schema-block {
  padding: 22px;
  border-radius: 20px;
  background: linear-gradient(180deg, #f8fafc 0%, #fdfefe 100%);
  border: 1px solid #dbe3ee;
}

.schema-block h3 {
  margin: 0 0 4px;
}

.schema-description {
  margin: 0 0 16px;
  color: #64748b;
}

.error-copy {
  color: #b91c1c;
  margin: 0;
}

.actions {
  display: flex;
  justify-content: flex-end;
}

.submit-btn {
  border: none;
  background: #2563eb;
  color: #fff;
  border-radius: 12px;
  padding: 12px 16px;
  font-weight: 800;
  cursor: pointer;
}

.submit-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .dynamic-contract-form {
    padding: 18px;
    border-radius: 18px;
  }

  .hero-card {
    grid-template-columns: 1fr;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
