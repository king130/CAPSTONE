<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { FilePlus2, Layers3, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next'

import AlertDialog from '@/components/ui/alert-dialog/AlertDialog.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue'
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue'
import FormControl from '@/components/ui/form/FormControl.vue'
import FormItem from '@/components/ui/form/FormItem.vue'
import FormLabel from '@/components/ui/form/FormLabel.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Textarea from '@/components/ui/textarea/Textarea.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import {
  createContractType,
  deleteContractType,
  fetchManagedContractTypes,
  updateContractType,
  type ContractFieldSchema,
  type DynamicContractType,
} from '@/services/dynamicContracts'
import { useAuthStore } from '@/stores/auth'

type ContractRole = 'school' | 'company'
type EditableField = ContractFieldSchema & { optionsText: string }

interface FormState {
  id: string | null
  name: string
  description: string
  baseContractTypeId: string
  isActive: boolean
  fields: EditableField[]
}

const authStore = useAuthStore()
const router = useRouter()
const { success, error } = useToast()

const currentRole = computed<ContractRole>(() => (authStore.user?.role === 'company' ? 'company' : 'school'))
const loading = ref(true)
const saving = ref(false)
const search = ref('')
const customTypes = ref<DynamicContractType[]>([])
const globalTypes = ref<DynamicContractType[]>([])
const editorOpen = ref(false)
const deleteTarget = ref<DynamicContractType | null>(null)

const form = ref<FormState>(defaultForm())

const filteredCustomTypes = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return customTypes.value
  return customTypes.value.filter((type) =>
    [type.name, type.description, type.slug].some((value) => String(value || '').toLowerCase().includes(query)),
  )
})

const stats = computed(() => [
  { label: 'Custom Types', value: customTypes.value.length, copy: 'Organization-specific templates you can edit and reuse.' },
  { label: 'Standard Types', value: globalTypes.value.length, copy: 'Built-in templates available across partnerships.' },
  { label: 'Active Custom Types', value: customTypes.value.filter((item) => item.isActive !== false).length, copy: 'Currently selectable in contract requests.' },
])

function defaultField(): EditableField {
  return {
    key: '',
    label: '',
    type: 'text',
    required: false,
    options: [],
    optionsText: '',
    placeholder: '',
  }
}

function defaultForm(): FormState {
  return {
    id: null,
    name: '',
    description: '',
    baseContractTypeId: '',
    isActive: true,
    fields: [defaultField()],
  }
}

function hydrateForm(type?: DynamicContractType | null) {
  if (!type) {
    form.value = defaultForm()
    return
  }

  form.value = {
    id: type.id,
    name: type.name,
    description: type.description || '',
    baseContractTypeId: type.baseContractTypeId || '',
    isActive: type.isActive !== false,
    fields: (type.fieldsSchema || []).map((field) => ({
      ...field,
      optionsText: (field.options || []).join(', '),
    })),
  }
}

async function loadTypes() {
  loading.value = true
  try {
    const data = await fetchManagedContractTypes()
    customTypes.value = data.customTypes
    globalTypes.value = data.globalTypes
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to load contract types.',
    })
  } finally {
    loading.value = false
  }
}

function openCreateDialog() {
  hydrateForm()
  editorOpen.value = true
}

function openEditDialog(type: DynamicContractType) {
  hydrateForm(type)
  editorOpen.value = true
}

function addField() {
  form.value.fields.push(defaultField())
}

function removeField(index: number) {
  if (form.value.fields.length === 1) {
    form.value.fields = [defaultField()]
    return
  }
  form.value.fields.splice(index, 1)
}

function normalizeFields() {
  return form.value.fields.map((field) => ({
    key: field.key.trim(),
    label: field.label.trim(),
    type: field.type,
    required: field.required,
    placeholder: field.placeholder?.trim() || undefined,
    options: ['select', 'multiselect'].includes(field.type)
      ? field.optionsText.split(',').map((item) => item.trim()).filter(Boolean)
      : undefined,
  }))
}

async function saveType() {
  saving.value = true
  try {
    const payload = {
      name: form.value.name.trim(),
      description: form.value.description.trim() || undefined,
      baseContractTypeId: form.value.baseContractTypeId || null,
      isActive: form.value.isActive,
      fieldsSchema: normalizeFields(),
      defaultValues: {},
    }

    if (form.value.id) {
      await updateContractType(form.value.id, payload)
      success('Contract type updated.')
    } else {
      await createContractType(payload)
      success('Custom contract type created.')
    }

    editorOpen.value = false
    hydrateForm()
    await loadTypes()
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to save contract type.',
    })
  } finally {
    saving.value = false
  }
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  try {
    await deleteContractType(deleteTarget.value.id)
    success('Contract type deleted.')
    deleteTarget.value = null
    await loadTypes()
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to delete contract type.',
    })
  }
}

onMounted(() => {
  void loadTypes()
})
</script>

<template>
  <MainLayout :role="currentRole" title="Manage Contract Types" active-item="contracts">
    <div class="space-y-6">
      <Card class="border-border/80 shadow-sm">
        <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="space-y-2">
            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-sky-700">Contracts</p>
            <div>
              <h2 class="text-3xl font-semibold tracking-tight text-foreground">Manage contract types</h2>
              <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                Build custom agreement templates for your organization while keeping the standard library available for everyday requests.
              </p>
            </div>
          </div>
          <div class="flex flex-wrap gap-3">
            <Button variant="outline" @click="router.push({ name: 'contracts' })">Back to Contracts</Button>
            <Button @click="openCreateDialog">
              <Plus class="h-4 w-4" />
              New Custom Type
            </Button>
          </div>
        </CardHeader>
      </Card>

      <div class="grid gap-4 md:grid-cols-3">
        <Card v-for="stat in stats" :key="stat.label" class="border-border/80 shadow-sm">
          <CardContent class="p-5">
            <p class="text-sm font-medium text-slate-500">{{ stat.label }}</p>
            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ stat.value }}</p>
            <p class="mt-3 text-sm text-slate-600">{{ stat.copy }}</p>
          </CardContent>
        </Card>
      </div>

      <Card class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h3 class="text-2xl font-semibold text-foreground">Custom Types</h3>
            <p class="text-sm text-muted-foreground">These templates belong to your organization and can be tailored for special partner requirements.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <Input v-model="search" placeholder="Search custom types..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent>
          <div v-if="loading" class="grid gap-4 md:grid-cols-2">
            <Skeleton v-for="index in 4" :key="index" class="h-48 rounded-2xl" />
          </div>
          <div v-else-if="filteredCustomTypes.length" class="grid gap-4 md:grid-cols-2">
            <Card v-for="type in filteredCustomTypes" :key="type.id" class="border-border/70 shadow-none">
              <CardContent class="space-y-4 p-5">
                <div class="flex items-start justify-between gap-4">
                  <div class="space-y-2">
                    <div class="flex items-center gap-2">
                      <div class="rounded-2xl bg-sky-100 p-2.5 text-sky-700">
                        <Layers3 class="h-5 w-5" />
                      </div>
                      <p class="text-lg font-semibold text-slate-950">{{ type.name }}</p>
                    </div>
                    <p class="text-sm leading-6 text-slate-600">{{ type.description || 'No description added yet.' }}</p>
                  </div>
                  <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]" :class="type.isActive === false ? 'bg-slate-100 text-slate-600' : 'bg-emerald-100 text-emerald-700'">
                    {{ type.isActive === false ? 'Inactive' : 'Active' }}
                  </span>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                  <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Fields</p>
                    <p class="mt-2 font-medium text-slate-900">{{ type.fieldsSchema.length }}</p>
                  </div>
                  <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Base Template</p>
                    <p class="mt-2 font-medium text-slate-900">{{ type.baseContractTypeId ? 'Derived from standard type' : 'Built from scratch' }}</p>
                  </div>
                </div>

                <div class="flex flex-wrap gap-2">
                  <Button variant="outline" size="sm" @click="openEditDialog(type)">
                    <Pencil class="h-4 w-4" />
                    Edit
                  </Button>
                  <Button variant="ghost" size="sm" class="text-red-600 hover:text-red-600" @click="deleteTarget = type">
                    <Trash2 class="h-4 w-4" />
                    Delete
                  </Button>
                </div>
              </CardContent>
            </Card>
          </div>
          <Card v-else class="border-dashed shadow-none">
            <CardContent class="py-10 text-center text-muted-foreground">
              No custom contract types yet. Create one to support partner-specific agreements.
            </CardContent>
          </Card>
        </CardContent>
      </Card>

      <Card class="border-border/80 shadow-sm">
        <CardHeader>
          <h3 class="text-2xl font-semibold text-foreground">Standard Library</h3>
          <p class="text-sm text-muted-foreground">These built-in types stay available as a starting point for everyday OJT agreements.</p>
        </CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <Card v-for="type in globalTypes" :key="type.id" class="border-border/70 shadow-none">
            <CardContent class="space-y-4 p-5">
              <div class="flex items-center gap-3">
                <div class="rounded-2xl bg-slate-100 p-2.5 text-slate-700">
                  <FilePlus2 class="h-5 w-5" />
                </div>
                <div>
                  <p class="text-lg font-semibold text-slate-950">{{ type.name }}</p>
                  <p class="text-sm text-slate-500">{{ type.fieldsSchema.length }} fields</p>
                </div>
              </div>
              <p class="text-sm leading-6 text-slate-600">{{ type.description || 'Standard agreement template.' }}</p>
            </CardContent>
          </Card>
        </CardContent>
      </Card>
    </div>

    <Dialog v-model:open="editorOpen">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>{{ form.id ? 'Edit Custom Contract Type' : 'Create Custom Contract Type' }}</DialogTitle>
          <p class="text-sm text-slate-600">Define the fields your organization needs when standard contract templates are not enough.</p>
        </DialogHeader>

        <div class="mt-6 space-y-6">
          <div class="grid gap-4 sm:grid-cols-2">
            <FormItem class="sm:col-span-2">
              <FormLabel>Name</FormLabel>
              <FormControl>
                <Input v-model="form.name" placeholder="Partner School Practicum Agreement" />
              </FormControl>
            </FormItem>
            <FormItem class="sm:col-span-2">
              <FormLabel>Description</FormLabel>
              <FormControl>
                <Textarea v-model="form.description" :rows="3" placeholder="Describe when this custom type should be used." />
              </FormControl>
            </FormItem>
            <FormItem>
              <FormLabel>Base Template</FormLabel>
              <FormControl>
                <Select v-model="form.baseContractTypeId">
                  <option value="">None</option>
                  <option v-for="type in globalTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                </Select>
              </FormControl>
            </FormItem>
            <FormItem>
              <FormLabel>Status</FormLabel>
              <FormControl>
                <div class="grid grid-cols-2 gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-2">
                  <button
                    type="button"
                    class="rounded-xl px-3 py-2 text-sm font-medium transition"
                    :class="form.isActive ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'"
                    @click="form.isActive = true"
                  >
                    Active
                  </button>
                  <button
                    type="button"
                    class="rounded-xl px-3 py-2 text-sm font-medium transition"
                    :class="!form.isActive ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'"
                    @click="form.isActive = false"
                  >
                    Inactive
                  </button>
                </div>
              </FormControl>
            </FormItem>
          </div>

          <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="text-lg font-semibold text-slate-950">Field Builder</h3>
                <p class="text-sm text-slate-600">Add the fields that should appear when this contract type is selected.</p>
              </div>
              <Button type="button" variant="outline" @click="addField">
                <Plus class="h-4 w-4" />
                Add Field
              </Button>
            </div>

            <div class="space-y-4">
              <Card v-for="(field, index) in form.fields" :key="index" class="border-border/70 shadow-none">
                <CardContent class="grid gap-4 p-5 md:grid-cols-2">
                  <FormItem>
                    <FormLabel>Field Key</FormLabel>
                    <FormControl>
                      <Input v-model="field.key" placeholder="placement_type" />
                    </FormControl>
                  </FormItem>
                  <FormItem>
                    <FormLabel>Label</FormLabel>
                    <FormControl>
                      <Input v-model="field.label" placeholder="Placement Type" />
                    </FormControl>
                  </FormItem>
                  <FormItem>
                    <FormLabel>Input Type</FormLabel>
                    <FormControl>
                      <Select v-model="field.type">
                        <option value="text">Text</option>
                        <option value="textarea">Textarea</option>
                        <option value="date">Date</option>
                        <option value="number">Number</option>
                        <option value="select">Select</option>
                        <option value="multiselect">Multi Select</option>
                        <option value="email">Email</option>
                        <option value="checkbox">Checkbox</option>
                      </Select>
                    </FormControl>
                  </FormItem>
                  <FormItem>
                    <FormLabel>Required</FormLabel>
                    <FormControl>
                      <div class="grid grid-cols-2 gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-2">
                        <button
                          type="button"
                          class="rounded-xl px-3 py-2 text-sm font-medium transition"
                          :class="field.required ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'"
                          @click="field.required = true"
                        >
                          Required
                        </button>
                        <button
                          type="button"
                          class="rounded-xl px-3 py-2 text-sm font-medium transition"
                          :class="!field.required ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'"
                          @click="field.required = false"
                        >
                          Optional
                        </button>
                      </div>
                    </FormControl>
                  </FormItem>
                  <FormItem class="md:col-span-2">
                    <FormLabel>Placeholder</FormLabel>
                    <FormControl>
                      <Input v-model="field.placeholder" placeholder="Optional helper text for this field" />
                    </FormControl>
                  </FormItem>
                  <FormItem v-if="['select', 'multiselect'].includes(field.type)" class="md:col-span-2">
                    <FormLabel>Options</FormLabel>
                    <FormControl>
                      <Textarea v-model="field.optionsText" :rows="3" placeholder="Enter options separated by commas" />
                    </FormControl>
                  </FormItem>
                  <div class="md:col-span-2 flex justify-end">
                    <Button type="button" variant="ghost" class="text-red-600 hover:text-red-600" @click="removeField(index)">
                      <Trash2 class="h-4 w-4" />
                      Remove Field
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <Button variant="outline" @click="close">Cancel</Button>
          <Button :disabled="saving || !form.name.trim()" @click="saveType">
            <Plus v-if="!saving && !form.id" class="h-4 w-4" />
            <Pencil v-else-if="!saving" class="h-4 w-4" />
            <span>{{ saving ? 'Saving...' : form.id ? 'Save Changes' : 'Create Type' }}</span>
          </Button>
        </div>
      </template>
    </Dialog>

    <AlertDialog
      :open="!!deleteTarget"
      title="Delete custom contract type?"
      :description="deleteTarget ? `Remove ${deleteTarget.name}? This cannot be undone if it has not been used yet.` : ''"
      action-label="Delete"
      @update:open="(open) => { if (!open) deleteTarget = null }"
      @action="confirmDelete"
    />
  </MainLayout>
</template>
