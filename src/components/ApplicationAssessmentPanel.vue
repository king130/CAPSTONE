<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { LoaderCircle } from 'lucide-vue-next'

import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import { useToast } from '@/composables/useToast'
import { createAssessment, listAssessments, type AssessmentRecord } from '@/services/assessments'
import { hasPermission } from '@/services/permissions'

const props = defineProps<{
  applicationId: string
}>()

const { success, error } = useToast()
const assessments = ref<AssessmentRecord[]>([])
const loading = ref(false)
const submitting = ref(false)
const form = ref({
  stage: 'pre_screen',
  overallScore: '',
  technical: '',
  communication: '',
  attitude: '',
  comments: '',
})

const canAssess = () => hasPermission('org.manage_assessments') || hasPermission('org.approve_applications')

async function load() {
  if (!props.applicationId) return
  loading.value = true
  try {
    assessments.value = await listAssessments(props.applicationId)
  } catch (caught) {
    error(caught, { fallback: 'Unable to load assessments.' })
  } finally {
    loading.value = false
  }
}

async function submit() {
  const score = Number(form.value.overallScore)
  if (Number.isNaN(score)) {
    error('Enter an overall score.')
    return
  }
  submitting.value = true
  try {
    await createAssessment({
      application_id: props.applicationId,
      stage: form.value.stage,
      overall_score: score,
      comments: form.value.comments || undefined,
      rubric_scores: {
        technical: Number(form.value.technical) || 0,
        communication: Number(form.value.communication) || 0,
        attitude: Number(form.value.attitude) || 0,
      },
    })
    success('Assessment saved.')
    form.value = { stage: 'pre_screen', overallScore: '', technical: '', communication: '', attitude: '', comments: '' }
    await load()
  } catch (caught) {
    error(caught, { fallback: 'Unable to save assessment.' })
  } finally {
    submitting.value = false
  }
}

onMounted(load)
watch(() => props.applicationId, load)
</script>

<template>
  <div class="space-y-4 rounded-2xl border border-border/70 p-4">
    <div class="flex items-center justify-between gap-3">
      <div>
        <h4 class="text-sm font-semibold text-foreground">Application assessment</h4>
        <p class="text-xs text-muted-foreground">Score applicants at pre-screen, interview, or final stage.</p>
      </div>
      <Button size="sm" variant="outline" :disabled="loading" @click="load">
        <LoaderCircle v-if="loading" class="h-3.5 w-3.5 animate-spin" />
        Refresh
      </Button>
    </div>

    <div v-if="assessments.length" class="space-y-2">
      <div v-for="item in assessments" :key="item.id" class="rounded-xl bg-muted p-3">
        <div class="flex flex-wrap items-center gap-2">
          <Badge variant="outline">{{ item.stage }}</Badge>
          <span class="text-sm font-medium text-foreground">
            Score: {{ item.overallScore ?? '—' }}
          </span>
          <span v-if="item.assessedByName" class="text-xs text-muted-foreground">by {{ item.assessedByName }}</span>
        </div>
        <p v-if="item.comments" class="mt-1 text-sm text-muted-foreground">{{ item.comments }}</p>
      </div>
    </div>
    <p v-else class="text-sm text-muted-foreground">No assessments recorded yet.</p>

    <div v-if="canAssess()" class="grid gap-3 border-t border-border/60 pt-4 sm:grid-cols-2">
      <label class="space-y-1 text-sm">
        <span class="font-medium text-foreground">Stage</span>
        <select v-model="form.stage" class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
          <option value="pre_screen">Pre-screen</option>
          <option value="interview">Interview</option>
          <option value="final">Final</option>
        </select>
      </label>
      <label class="space-y-1 text-sm">
        <span class="font-medium text-foreground">Overall score</span>
        <Input v-model="form.overallScore" type="number" min="0" max="100" step="0.1" />
      </label>
      <label class="space-y-1 text-sm">
        <span class="font-medium text-foreground">Technical</span>
        <Input v-model="form.technical" type="number" min="0" max="100" />
      </label>
      <label class="space-y-1 text-sm">
        <span class="font-medium text-foreground">Communication</span>
        <Input v-model="form.communication" type="number" min="0" max="100" />
      </label>
      <label class="space-y-1 text-sm">
        <span class="font-medium text-foreground">Attitude</span>
        <Input v-model="form.attitude" type="number" min="0" max="100" />
      </label>
      <label class="space-y-1 text-sm">
        <span class="font-medium text-foreground">Comments</span>
        <Input v-model="form.comments" placeholder="Optional comments" />
      </label>
      <div class="sm:col-span-2">
        <Button :disabled="submitting" @click="submit">
          <LoaderCircle v-if="submitting" class="h-4 w-4 animate-spin" />
          Save assessment
        </Button>
      </div>
    </div>
  </div>
</template>
