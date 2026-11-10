<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Check, ChevronLeft, ChevronRight, Plus, Search, X } from 'lucide-vue-next'

import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import {
  createCustomSkill,
  fetchSkillsCatalog,
  type CatalogSkill,
  type SelectedSkill,
  type SkillImportance,
  type SkillLevel,
} from '@/services/skills'

const props = withDefaults(
  defineProps<{
    modelValue: SelectedSkill[]
    mode: 'intern' | 'job'
    open?: boolean
    limit?: number
    /** When set (e.g. job eligible courses), overrides API recommended categories. */
    recommendedCategories?: string[]
  }>(),
  {
    open: false,
    limit: undefined,
    recommendedCategories: undefined,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: SelectedSkill[]]
  'update:open': [value: boolean]
  done: [value: SelectedSkill[]]
}>()

type WizardStep = 'categories' | 'skills' | 'review'

const step = ref<WizardStep>('categories')
const loading = ref(true)
const catalog = ref<CatalogSkill[]>([])
const categories = ref<string[]>([])
const loadedRecommendedCategories = ref<string[]>([])
const showAllCategories = ref(false)
const selectedCategories = ref<string[]>([])
const selected = ref<SelectedSkill[]>([])
const skillSearch = ref('')
const customName = ref('')
const customCategory = ref('Other')
const customBusy = ref(false)
const suggestion = ref<CatalogSkill | null>(null)
const suggestionMessage = ref('')
const errorMessage = ref('')
const limitReachedMessage = ref('')

const maxSkills = computed(() => props.limit ?? (props.mode === 'intern' ? 15 : 10))
const stepIndex = computed(() => (step.value === 'categories' ? 0 : step.value === 'skills' ? 1 : 2))

const effectiveRecommendedCategories = computed(() => {
  if (props.recommendedCategories?.length) {
    return props.recommendedCategories
  }
  return loadedRecommendedCategories.value
})

const hasRecommendedCategories = computed(() => effectiveRecommendedCategories.value.length > 0)

const displayedCategories = computed(() => {
  const all = categories.value
  const recommended = effectiveRecommendedCategories.value.filter((item) => all.includes(item))
  if (!recommended.length) {
    return all
  }
  if (!showAllCategories.value) {
    return recommended
  }
  const recommendedSet = new Set(recommended)
  const rest = all.filter((item) => !recommendedSet.has(item))
  return [...recommended, ...rest]
})

const skillsInSelectedCategories = computed(() => {
  const set = new Set(selectedCategories.value)
  return catalog.value.filter((skill) => set.has(skill.category))
})

const filteredSkills = computed(() => {
  const q = skillSearch.value.trim().toLowerCase()
  const pool = q ? catalog.value : skillsInSelectedCategories.value
  if (!q) return pool
  return pool.filter(
    (skill) =>
      skill.name.toLowerCase().includes(q) || skill.category.toLowerCase().includes(q),
  )
})

const selectedIds = computed(() => new Set(selected.value.map((skill) => skill.id)))

watch(
  () => props.open,
  (open) => {
    if (open) {
      selected.value = props.modelValue.map((skill) => ({ ...skill }))
      selectedCategories.value = Array.from(
        new Set(selected.value.map((skill) => skill.category).filter(Boolean)),
      )
      step.value = 'categories'
      showAllCategories.value = false
      errorMessage.value = ''
      suggestion.value = null
      suggestionMessage.value = ''
      limitReachedMessage.value = ''
      void loadCatalog()
    }
  },
)

onMounted(() => {
  void loadCatalog()
})

async function loadCatalog() {
  loading.value = true
  try {
    const res = await fetchSkillsCatalog()
    catalog.value = res.data || []
    categories.value = res.categories || []
    loadedRecommendedCategories.value = res.recommendedCategories || []
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load skills.'
  } finally {
    loading.value = false
  }
}

function closeWizard() {
  emit('update:open', false)
}

function toggleCategory(category: string) {
  if (selectedCategories.value.includes(category)) {
    selectedCategories.value = selectedCategories.value.filter((item) => item !== category)
    return
  }
  selectedCategories.value = [...selectedCategories.value, category]
}

function isSelected(skillId: string) {
  return selectedIds.value.has(skillId)
}

function toggleSkill(skill: CatalogSkill) {
  if (isSelected(skill.id)) {
    selected.value = selected.value.filter((item) => item.id !== skill.id)
    limitReachedMessage.value = ''
    return
  }
  if (selected.value.length >= maxSkills.value) {
    limitReachedMessage.value = `You reached the limit of ${maxSkills.value} skills.`
    return
  }
  selected.value = [
    ...selected.value,
    {
      ...skill,
      level: 'beginner',
      importance: 'required',
    },
  ]
  limitReachedMessage.value = ''
}

function removeSkill(skillId: string) {
  selected.value = selected.value.filter((item) => item.id !== skillId)
  limitReachedMessage.value = ''
}

function setLevel(skillId: string, level: SkillLevel) {
  selected.value = selected.value.map((skill) =>
    skill.id === skillId ? { ...skill, level } : skill,
  )
}

function setImportance(skillId: string, importance: SkillImportance) {
  selected.value = selected.value.map((skill) =>
    skill.id === skillId ? { ...skill, importance } : skill,
  )
}

function goNext() {
  errorMessage.value = ''
  if (step.value === 'categories') {
    if (!selectedCategories.value.length) {
      errorMessage.value = 'Pick at least one category to continue.'
      return
    }
    step.value = 'skills'
    return
  }
  if (step.value === 'skills') {
    if (!selected.value.length) {
      errorMessage.value = 'Pick at least one skill, or add a custom skill.'
      return
    }
    step.value = 'review'
  }
}

function goBack() {
  errorMessage.value = ''
  if (step.value === 'skills') step.value = 'categories'
  else if (step.value === 'review') step.value = 'skills'
}

function finish() {
  const next = selected.value.map((skill) => ({ ...skill }))
  emit('update:modelValue', next)
  emit('done', next)
  emit('update:open', false)
}

async function submitCustomSkill(force = false) {
  const name = customName.value.trim()
  if (!name) {
    errorMessage.value = 'Type the skill name you want to add.'
    return
  }
  if (selected.value.length >= maxSkills.value) {
    limitReachedMessage.value = `You reached the limit of ${maxSkills.value} skills.`
    return
  }
  customBusy.value = true
  errorMessage.value = ''
  suggestion.value = null
  suggestionMessage.value = ''
  try {
    const res = await createCustomSkill({
      name,
      category: customCategory.value || selectedCategories.value[0] || 'Other',
      force,
    })
    if (res.conflict || res.suggestion) {
      suggestion.value = res.suggestion || res.suggestions?.[0] || null
      suggestionMessage.value = suggestion.value
        ? `Did you mean “${suggestion.value.name}”?`
        : 'A similar skill already exists.'
      return
    }
    const created = res.data
    if (created) {
      if (!selectedCategories.value.includes(created.category)) {
        selectedCategories.value = [...selectedCategories.value, created.category]
      }
      catalog.value = [...catalog.value, created]
      if (!categories.value.includes(created.category)) {
        categories.value = [...categories.value, created.category].sort()
      }
      toggleSkill(created)
      customName.value = ''
      suggestion.value = null
    }
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to add custom skill.'
  } finally {
    customBusy.value = false
  }
}

function acceptSuggestion() {
  if (!suggestion.value) return
  if (!selectedCategories.value.includes(suggestion.value.category)) {
    selectedCategories.value = [...selectedCategories.value, suggestion.value.category]
  }
  if (!catalog.value.some((skill) => skill.id === suggestion.value!.id)) {
    catalog.value = [...catalog.value, suggestion.value]
  }
  toggleSkill(suggestion.value)
  customName.value = ''
  suggestion.value = null
  suggestionMessage.value = ''
}

async function forceCreateCustom() {
  await submitCustomSkill(true)
}
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-stretch justify-center bg-black/40 p-0 sm:items-center sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="skills-wizard-title"
  >
    <div
      class="flex h-[100dvh] w-full max-w-3xl flex-col overflow-hidden bg-background shadow-xl sm:h-auto sm:max-h-[90vh] sm:rounded-2xl sm:border sm:border-border"
    >
      <header class="border-b border-border px-4 py-4 sm:px-6">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
              Skills wizard · Step {{ stepIndex + 1 }} of 3
            </p>
            <h2 id="skills-wizard-title" class="mt-1 text-xl font-semibold text-foreground">
              {{
                step === 'categories'
                  ? 'Choose skill areas'
                  : step === 'skills'
                    ? 'Pick your skills'
                    : 'Review your list'
              }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
              {{
                step === 'categories'
                  ? mode === 'intern'
                    ? 'Start with the kinds of work you know. You can pick more than one area.'
                    : 'Start with the kinds of skills this internship needs. You can pick more than one area.'
                  : step === 'skills'
                    ? mode === 'intern'
                      ? 'Tap the skills you have. You can search, and add a skill if it is missing.'
                      : 'Tap the skills this role needs. You can search, and add a skill if it is missing.'
                    : mode === 'intern'
                      ? 'Set how strong you are in each skill. You can remove anything that does not fit.'
                      : 'Mark each skill as required or nice to have. You can remove anything that does not fit.'
              }}
            </p>
          </div>
          <Button type="button" variant="ghost" size="sm" aria-label="Close skills wizard" @click="closeWizard">
            <X class="h-4 w-4" />
          </Button>
        </div>
        <p class="mt-3 text-sm font-medium text-foreground">
          Selected {{ selected.length }} / {{ maxSkills }}
        </p>
        <p v-if="limitReachedMessage" class="mt-1 text-sm text-destructive">{{ limitReachedMessage }}</p>
      </header>

      <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-6">
        <p v-if="loading" class="text-sm text-muted-foreground">Loading skills…</p>
        <p v-else-if="errorMessage" class="mb-3 text-sm text-destructive">{{ errorMessage }}</p>

        <section v-if="!loading && step === 'categories'" class="space-y-3">
          <div
            v-if="hasRecommendedCategories"
            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border bg-muted/50 px-4 py-3 text-sm"
          >
            <p class="text-muted-foreground">
              {{
                showAllCategories
                  ? 'Showing all skill areas. Suggested areas for this course appear first.'
                  : 'Showing skill areas suggested for your course. You can include other areas too.'
              }}
            </p>
            <Button type="button" variant="outline" size="sm" @click="showAllCategories = !showAllCategories">
              {{ showAllCategories ? 'Show suggested only' : 'Show all skill areas' }}
            </Button>
          </div>
          <button
            v-for="category in displayedCategories"
            :key="category"
            type="button"
            class="flex w-full items-center justify-between rounded-2xl border px-4 py-3 text-left transition"
            :class="
              selectedCategories.includes(category)
                ? 'border-primary/40 bg-accent'
                : 'border-border bg-card hover:border-border'
            "
            :aria-pressed="selectedCategories.includes(category)"
            @click="toggleCategory(category)"
          >
            <span class="font-medium text-foreground">{{ category }}</span>
            <Check v-if="selectedCategories.includes(category)" class="h-4 w-4 text-foreground" />
          </button>
        </section>

        <section v-else-if="!loading && step === 'skills'" class="space-y-4">
          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              v-model="skillSearch"
              class="pl-9"
              placeholder="Search skills"
              aria-label="Search skills"
            />
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              v-for="skill in filteredSkills"
              :key="skill.id"
              type="button"
              class="inline-flex items-center rounded-full border px-3 py-1.5 text-sm transition"
              :class="
                isSelected(skill.id)
                  ? 'border-primary/40 bg-accent font-semibold text-foreground'
                  : 'border-border bg-card text-foreground hover:bg-muted'
              "
              :aria-pressed="isSelected(skill.id)"
              @click="toggleSkill(skill)"
            >
              {{ skill.name }}
            </button>
          </div>
          <p v-if="!filteredSkills.length" class="text-sm text-muted-foreground">
            No skills match this search in your selected areas.
          </p>

          <div class="rounded-2xl border border-dashed border-border bg-muted/40 p-4">
            <p class="text-sm font-semibold text-foreground">Can’t find your skill?</p>
            <p class="mt-1 text-sm text-muted-foreground">
              Type it below. We’ll check for a close match before saving a new one.
            </p>
            <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
              <Input
                v-model="customName"
                placeholder="Example: java script"
                aria-label="Custom skill name"
                @keydown.enter.prevent="submitCustomSkill(false)"
              />
              <Button type="button" class="gap-2" :disabled="customBusy" @click="submitCustomSkill(false)">
                <Plus class="h-4 w-4" />
                Add skill
              </Button>
            </div>
            <div v-if="suggestion" class="mt-3 rounded-xl border border-border bg-background p-3">
              <p class="text-sm font-medium text-foreground">{{ suggestionMessage }}</p>
              <div class="mt-2 flex flex-wrap gap-2">
                <Button type="button" size="sm" @click="acceptSuggestion">Use {{ suggestion.name }}</Button>
                <Button type="button" size="sm" variant="outline" :disabled="customBusy" @click="forceCreateCustom">
                  Keep my wording
                </Button>
              </div>
            </div>
          </div>
        </section>

        <section v-else-if="!loading && step === 'review'" class="space-y-3">
          <div
            v-for="skill in selected"
            :key="skill.id"
            class="rounded-2xl border border-border bg-card p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="font-semibold text-foreground">{{ skill.name }}</p>
                <p class="text-xs text-muted-foreground">{{ skill.category }}</p>
              </div>
              <Button type="button" size="sm" variant="ghost" @click="removeSkill(skill.id)">
                Remove
              </Button>
            </div>

            <div v-if="mode === 'intern'" class="mt-3 grid gap-2 sm:grid-cols-3">
              <button
                v-for="level in (['beginner', 'intermediate', 'advanced'] as SkillLevel[])"
                :key="level"
                type="button"
                class="rounded-xl border px-3 py-2 text-sm capitalize"
                :class="skill.level === level ? 'border-primary/40 bg-accent font-semibold' : 'border-border'"
                @click="setLevel(skill.id, level)"
              >
                {{ level }}
              </button>
            </div>
            <div v-else class="mt-3 grid gap-2 sm:grid-cols-2">
              <button
                type="button"
                class="rounded-xl border px-3 py-2 text-sm"
                :class="skill.importance === 'required' ? 'border-primary/40 bg-accent font-semibold' : 'border-border'"
                @click="setImportance(skill.id, 'required')"
              >
                Required
              </button>
              <button
                type="button"
                class="rounded-xl border px-3 py-2 text-sm"
                :class="skill.importance === 'nice_to_have' ? 'border-primary/40 bg-accent font-semibold' : 'border-border'"
                @click="setImportance(skill.id, 'nice_to_have')"
              >
                Nice to have
              </button>
            </div>
          </div>
        </section>
      </div>

      <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-4 sm:px-6">
        <Button v-if="step !== 'categories'" type="button" variant="outline" class="gap-2" @click="goBack">
          <ChevronLeft class="h-4 w-4" />
          Back
        </Button>
        <div v-else />
        <div class="flex gap-2">
          <Button type="button" variant="outline" @click="closeWizard">Cancel</Button>
          <Button v-if="step !== 'review'" type="button" class="gap-2" @click="goNext">
            Next
            <ChevronRight class="h-4 w-4" />
          </Button>
          <Button v-else type="button" @click="finish">Save skills</Button>
        </div>
      </footer>
    </div>
  </div>
</template>
