<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import {
  AlertCircle,
  BriefcaseBusiness,
  CheckCircle2,
  GraduationCap,
  Landmark,
  LoaderCircle,
  School,
} from 'lucide-vue-next'
import { useForm } from 'vee-validate'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { z } from 'zod'

import Alert from '@/components/ui/alert/Alert.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import FormControl from '@/components/ui/form/FormControl.vue'
import FormField from '@/components/ui/form/FormField.vue'
import FormItem from '@/components/ui/form/FormItem.vue'
import FormLabel from '@/components/ui/form/FormLabel.vue'
import FormMessage from '@/components/ui/form/FormMessage.vue'
import Input from '@/components/ui/input/Input.vue'
import PasswordInput from '@/components/ui/password-input/PasswordInput.vue'
import Select from '@/components/ui/select/Select.vue'
import { caviteBarangaysByLocation, caviteLocationGroups } from '@/config/courseCatalog'
import { useToast } from '@/composables/useToast'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { setToken } from '@/services/http'
import { useAuthStore } from '@/stores/auth'
import { resolveAuthenticatedRegisterRedirect } from '@/utils/registerEntryGuard'

type RegisterRole = 'school' | 'company' | 'municipal_hall'

const lguTypeOptions = [
  { value: 'municipality', label: 'Municipality' },
  { value: 'city', label: 'City' },
  { value: 'other_office', label: 'Other office' },
] as const

const industryOptions = [
  'Technology',
  'Manufacturing',
  'Healthcare',
  'Education',
  'Finance & Banking',
  'Retail & E-commerce',
  'Hospitality & Tourism',
  'Government',
  'Agriculture',
  'Construction',
  'Business Process Outsourcing',
  'Other',
] as const

interface CommonRegisterFields {
  fullName: string
  email: string
  password: string
  confirmPassword: string
  contactPhone: string
  locationArea: string
  barangay: string
  addressLine: string
}

interface SchoolRegisterForm extends CommonRegisterFields {
  role: 'school'
  schoolName: string
  contactPerson: string
}

interface CompanyRegisterForm extends CommonRegisterFields {
  role: 'company'
  companyName: string
  industry: string
}

interface MunicipalHallRegisterForm extends CommonRegisterFields {
  role: 'municipal_hall'
  officialName: string
  lguType: string
  representativeName: string
  representativePosition: string
}

type RegisterForm = SchoolRegisterForm | CompanyRegisterForm | MunicipalHallRegisterForm

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { success, error } = useToast()
const successMessage = ref('')
const registerFormId = 'register-auth-form'
let successRedirectTimer: number | null = null

const selectedPlanQuery = computed(() => {
  const raw = String(route.query.plan || '').trim().toLowerCase()
  // Free is legacy and not selectable for new organizations (Phase H).
  if (['standard', 'premium'].includes(raw)) {
    return raw
  }
  return null
})

const billingCycleQuery = computed(() => {
  const raw = String(route.query.billingCycle || '').trim().toLowerCase()
  return raw || 'monthly'
})

const cameFromSubscription = computed(() => route.query.source === 'subscription')

const commonSchemaFields = {
  fullName: z.string().trim().min(2, 'Enter the full name of the account owner.'),
  email: z.string().trim().min(1, 'Email is required.').email('Enter a valid email address.'),
  password: z
    .string()
    .min(8, 'Password must be at least 8 characters.')
    .regex(/[A-Za-z]/, 'Password must include at least one letter.')
    .regex(/[0-9]/, 'Password must include at least one number.'),
  confirmPassword: z.string().min(1, 'Please confirm your password.'),
  contactPhone: z
    .string()
    .trim()
    .min(1, 'Contact number is required.')
    .regex(/^(\+63|0)?9\d{9}$/, 'Enter a valid PH mobile number (e.g. 09171234567).'),
  locationArea: z.string().min(1, 'Select a city or municipality in Cavite.'),
  barangay: z.string().min(1, 'Select a barangay.'),
  addressLine: z.string().trim().max(120, 'Keep the street/building details under 120 characters.'),
}

const schoolSchema = z.object({
  role: z.literal('school'),
  ...commonSchemaFields,
  schoolName: z.string().trim().min(2, 'School name is required.'),
  contactPerson: z.string().trim().min(2, 'Contact person is required.'),
})

const companySchema = z.object({
  role: z.literal('company'),
  ...commonSchemaFields,
  companyName: z.string().trim().min(2, 'Company name is required.'),
  industry: z.string().min(1, 'Select an industry.'),
})

const municipalHallSchema = z.object({
  role: z.literal('municipal_hall'),
  ...commonSchemaFields,
  officialName: z.string().trim().min(2, 'Official municipal / LGU office name is required.'),
  lguType: z.enum(['municipality', 'city', 'other_office'], {
    required_error: 'Select the LGU type.',
  }),
  representativeName: z.string().trim().min(2, 'Authorized representative name is required.'),
  representativePosition: z.string().trim().min(2, 'Representative position is required.'),
})

const registerSchema = toTypedSchema(
  z.discriminatedUnion('role', [schoolSchema, companySchema, municipalHallSchema]).superRefine((data, ctx) => {
    if (data.password !== data.confirmPassword) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['confirmPassword'],
        message: 'Passwords do not match.',
      })
    }
  }),
)

const { handleSubmit, values, errors, setFieldValue, isSubmitting } = useForm<RegisterForm>({
  validationSchema: registerSchema,
  initialValues: {
    role: 'school',
    fullName: '',
    email: '',
    password: '',
    confirmPassword: '',
    contactPhone: '',
    locationArea: '',
    barangay: '',
    addressLine: '',
    schoolName: '',
    contactPerson: '',
  } as SchoolRegisterForm,
})

const selectedRole = computed(() => values.role as RegisterRole)
const errorMessage = computed(() => authStore.error)
const submitting = computed(() => authStore.loading || isSubmitting.value)
const barangayOptions = computed(() => caviteBarangaysByLocation[values.locationArea || ''] || [])

const routeRole = computed<RegisterRole | null>(() => {
  if (route.name === 'register-school') return 'school'
  if (route.name === 'register-company') return 'company'
  if (route.name === 'register-municipal-hall') return 'municipal_hall'
  return null
})

const roleIntro = computed(() => {
  if (selectedRole.value === 'school') {
    return {
      eyebrow: 'School Setup',
      title: 'Create a school account built for coordinators and student-managed onboarding.',
      description:
        'Schools create student accounts, review endorsement requests, and track placements from one workspace.',
    }
  }
  if (selectedRole.value === 'municipal_hall') {
    return {
      eyebrow: 'Municipal Hall Setup',
      title: 'Register a Cavite LGU office that hosts OJT students.',
      description:
        'Municipal halls post opportunities, review endorsed students, and approve DTRs like company hosts — with LGU verification documents.',
    }
  }
  return {
    eyebrow: 'Company Setup',
    title: 'Create a company account ready for internship posting and school coordination.',
    description:
      'Companies register once to post internship roles, review endorsed students, and manage applicant decisions.',
  }
})

const roleHighlights = computed(() => {
  if (selectedRole.value === 'school') {
    return [
      'Create student accounts and manage intern access',
      'Review endorsement requests before students are placed',
    ]
  }
  if (selectedRole.value === 'municipal_hall') {
    return [
      'Publish OJT openings for your LGU office',
      'Approve DTRs and track assigned students after verification',
    ]
  }
  return [
    'Publish internship roles and accept endorsed students',
    'Coordinate requirements, agreements, and applicant decisions',
  ]
})

watch(
  () => values.locationArea,
  (next, previous) => {
    if (next !== previous) {
      setFieldValue('barangay', '')
    }
  },
)

watch(
  () => values.role,
  (role) => {
    if (role === 'company') {
      if (!(values as CompanyRegisterForm).companyName) setFieldValue('companyName', '')
      if (!(values as CompanyRegisterForm).industry) setFieldValue('industry', '')
    } else if (role === 'municipal_hall') {
      if (!(values as MunicipalHallRegisterForm).officialName) setFieldValue('officialName', '')
      if (!(values as MunicipalHallRegisterForm).lguType) setFieldValue('lguType', '')
      if (!(values as MunicipalHallRegisterForm).representativeName) setFieldValue('representativeName', '')
      if (!(values as MunicipalHallRegisterForm).representativePosition) setFieldValue('representativePosition', '')
    } else {
      if (!(values as SchoolRegisterForm).schoolName) setFieldValue('schoolName', '')
      if (!(values as SchoolRegisterForm).contactPerson) setFieldValue('contactPerson', '')
    }
  },
)

function composeCaviteAddress(locationArea: string, barangay: string, addressLine?: string) {
  const parts = [addressLine?.trim(), barangay, locationArea, 'Cavite'].filter(Boolean)
  return parts.join(', ')
}

function normalizePhone(value: string) {
  const digits = value.replace(/\s+/g, '')
  if (digits.startsWith('+63')) return `0${digits.slice(3)}`
  return digits
}

onMounted(() => {
  if (routeRole.value) {
    setFieldValue('role', routeRole.value)
  }

  // Guests may complete organization registration. Other authenticated roles are redirected.
  const redirectTo = resolveAuthenticatedRegisterRedirect(authStore.user)
  if (redirectTo) {
    void router.replace(redirectTo)
  }
})

onBeforeUnmount(() => {
  if (successRedirectTimer) {
    window.clearTimeout(successRedirectTimer)
  }
})

const onSubmit = handleSubmit(async (formValues) => {
  successMessage.value = ''

  const address = composeCaviteAddress(formValues.locationArea, formValues.barangay, formValues.addressLine)
  const phone = normalizePhone(formValues.contactPhone)

  try {
    let payload: Parameters<typeof authStore.register>[0]
    if (formValues.role === 'school') {
      payload = {
        fullName: formValues.fullName,
        email: formValues.email,
        password: formValues.password,
        role: 'school',
        profile: {
          institutionName: formValues.schoolName,
          schoolAddress: address,
          contactPersonName: formValues.contactPerson,
          position: formValues.contactPerson,
          contactNumber: phone,
          locationArea: formValues.locationArea,
          barangay: formValues.barangay,
        },
        subscriptionPlan: selectedPlanQuery.value ?? undefined,
        billingCycle: billingCycleQuery.value,
      }
    } else if (formValues.role === 'municipal_hall') {
      payload = {
        fullName: formValues.fullName,
        email: formValues.email,
        password: formValues.password,
        role: 'municipal_hall',
        profile: {
          officialName: formValues.officialName,
          companyName: formValues.officialName,
          lguType: formValues.lguType,
          representativeName: formValues.representativeName,
          representativePosition: formValues.representativePosition,
          contactPersonName: formValues.representativeName,
          contactPersonTitle: formValues.representativePosition,
          companyAddress: address,
          officialAddress: address,
          contactNumber: phone,
          officialEmail: formValues.email,
          locationArea: formValues.locationArea,
          cityMunicipality: formValues.locationArea,
          barangay: formValues.barangay,
        },
        subscriptionPlan: selectedPlanQuery.value ?? undefined,
        billingCycle: billingCycleQuery.value,
      }
    } else {
      payload = {
        fullName: formValues.fullName,
        email: formValues.email,
        password: formValues.password,
        role: 'company',
        profile: {
          companyName: formValues.companyName,
          industryType: formValues.industry,
          companyAddress: address,
          contactNumber: phone,
          locationArea: formValues.locationArea,
          barangay: formValues.barangay,
        },
        subscriptionPlan: selectedPlanQuery.value ?? undefined,
        billingCycle: billingCycleQuery.value,
      }
    }

    await authStore.register(payload)
    authStore.setUserProfile(null)
    setToken(null)

    successMessage.value = 'Account created successfully. Redirecting you to login…'
    success('Account created successfully.', {
      description: 'You can now log in with your new credentials.',
    })
    successRedirectTimer = window.setTimeout(() => {
      void router.push({ name: 'login', query: { registered: '1' } })
    }, 1400)
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Registration failed. Check your details and try again.',
    })
  }
})
</script>

<template>
  <AuthLayout back-to="/" back-label="Back">
    <template #left-eyebrow>{{ routeRole ? roleIntro.eyebrow : 'Organization Setup' }}</template>
    <template #left-title>
      {{
        routeRole
          ? roleIntro.title
          : 'Create a school, company, or municipal hall account with the right structure from the start.'
      }}
    </template>
    <template #left-description>
      {{
        routeRole
          ? roleIntro.description
          : 'Schools manage student credentials. Companies and municipal halls register to host OJT students, post openings, and review applicants.'
      }}
    </template>
    <template #left-highlights>
      <div class="mb-2 grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-border bg-card/65 p-4 backdrop-blur">
          <p class="text-xs font-medium uppercase tracking-[0.18em] text-muted-foreground">School Setup</p>
          <p class="mt-2 text-lg font-semibold text-foreground">Control student access</p>
          <p class="mt-2 text-sm leading-5 text-muted-foreground">
            Keep student records aligned by creating and managing their credentials inside the school workflow.
          </p>
        </div>
        <div class="rounded-2xl border border-border bg-slate-900/90 p-4 text-white shadow-lg shadow-slate-900/10">
          <p class="text-xs font-medium uppercase tracking-[0.18em] text-sky-100">Company Setup</p>
          <p class="mt-2 text-lg font-semibold">Publish and coordinate</p>
          <p class="mt-2 text-sm leading-5 text-sky-100/90">
            Register once to start posting opportunities and working directly with partner schools.
          </p>
        </div>
      </div>
      <div v-if="routeRole" class="rounded-2xl border border-border bg-card/70 p-4 text-sm text-muted-foreground backdrop-blur">
        <p class="font-semibold text-foreground">{{ roleIntro.eyebrow }}</p>
        <ul class="mt-3 space-y-2">
          <li v-for="item in roleHighlights" :key="item" class="flex items-start gap-2">
            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-sky-700" />
            <span>{{ item }}</span>
          </li>
        </ul>
      </div>
    </template>
    <template #left-form>
      <div class="space-y-4">
        <div class="rounded-2xl bg-card/85 p-4">
          <p class="text-sm font-medium text-muted-foreground">Account Basics</p>
          <p class="mt-2 text-xl font-semibold text-foreground">Set up the essentials</p>
          <p class="mt-2 text-sm text-muted-foreground">
            Choose your organization type and enter the main account details here first.
          </p>
        </div>

        <FormField v-slot="{ errorMessage: fieldError }" name="role">
          <FormItem>
            <FormLabel class="text-muted-foreground">Choose account type</FormLabel>
            <FormControl>
              <div class="grid gap-2.5 sm:grid-cols-3" role="group" aria-label="Account type">
                <button
                  type="button"
                  class="rounded-2xl border p-3 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                  :class="
                    selectedRole === 'school'
                      ? 'border-sky-500 bg-accent shadow-[0_12px_30px_rgba(14,116,144,0.12)]'
                      : 'border-border bg-card/80 hover:border-border hover:bg-accent'
                  "
                  :aria-pressed="selectedRole === 'school'"
                  :disabled="routeRole === 'company' || routeRole === 'municipal_hall' || submitting || !!successMessage"
                  @click="setFieldValue('role', 'school')"
                >
                  <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-sky-100 p-2 text-sky-700 dark:bg-sky-950 dark:text-sky-200">
                      <School class="h-5 w-5" />
                    </div>
                    <div>
                      <p class="font-semibold text-foreground">School</p>
                      <p class="text-[11px] text-muted-foreground">Manage student access</p>
                    </div>
                  </div>
                </button>

                <button
                  type="button"
                  class="rounded-2xl border p-3 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900"
                  :class="
                    selectedRole === 'company'
                      ? 'border-slate-900 bg-slate-900 text-white shadow-[0_12px_30px_rgba(15,23,42,0.18)]'
                      : 'border-border bg-card/80 hover:border-border hover:bg-accent'
                  "
                  :aria-pressed="selectedRole === 'company'"
                  :disabled="routeRole === 'school' || routeRole === 'municipal_hall' || submitting || !!successMessage"
                  @click="setFieldValue('role', 'company')"
                >
                  <div class="flex items-center gap-3">
                    <div
                      class="rounded-xl p-2"
                      :class="selectedRole === 'company' ? 'bg-white/15 text-white' : 'bg-muted text-muted-foreground'"
                    >
                      <BriefcaseBusiness class="h-5 w-5" />
                    </div>
                    <div>
                      <p class="font-semibold" :class="selectedRole === 'company' ? 'text-white' : 'text-foreground'">
                        Company
                      </p>
                      <p class="text-[11px]" :class="selectedRole === 'company' ? 'text-slate-200' : 'text-muted-foreground'">
                        Post and coordinate
                      </p>
                    </div>
                  </div>
                </button>

                <button
                  type="button"
                  class="rounded-2xl border p-3 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 sm:col-span-1"
                  :class="
                    selectedRole === 'municipal_hall'
                      ? 'border-emerald-700 bg-emerald-800 text-white shadow-[0_12px_30px_rgba(6,95,70,0.2)]'
                      : 'border-border bg-card/80 hover:border-border hover:bg-accent'
                  "
                  :aria-pressed="selectedRole === 'municipal_hall'"
                  :disabled="routeRole === 'school' || routeRole === 'company' || submitting || !!successMessage"
                  @click="setFieldValue('role', 'municipal_hall')"
                >
                  <div class="flex items-center gap-3">
                    <div
                      class="rounded-xl p-2"
                      :class="
                        selectedRole === 'municipal_hall'
                          ? 'bg-white/15 text-white'
                          : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                      "
                    >
                      <Landmark class="h-5 w-5" />
                    </div>
                    <div>
                      <p
                        class="font-semibold"
                        :class="selectedRole === 'municipal_hall' ? 'text-white' : 'text-foreground'"
                      >
                        Municipal Hall
                      </p>
                      <p
                        class="text-[11px]"
                        :class="selectedRole === 'municipal_hall' ? 'text-emerald-100' : 'text-muted-foreground'"
                      >
                        Cavite LGU host
                      </p>
                    </div>
                  </div>
                </button>
              </div>
            </FormControl>
            <FormMessage :message="fieldError || errors.role" />
          </FormItem>
        </FormField>

        <div class="grid gap-3 rounded-2xl border border-border bg-card/70 p-4 backdrop-blur">
          <FormField v-slot="{ componentField, errorMessage: fieldError }" name="fullName">
            <FormItem>
              <FormLabel for="fullName">
                {{ selectedRole === 'school' ? 'Administrator Name' : 'Representative Name' }}
              </FormLabel>
              <FormControl>
                <Input
                  id="fullName"
                  :placeholder="
                    selectedRole === 'school'
                      ? 'Enter the coordinator name'
                      : 'Enter the company representative name'
                  "
                  autocomplete="name"
                  :form="registerFormId"
                  :disabled="submitting || !!successMessage"
                  v-bind="componentField"
                  @update:modelValue="setFieldValue('fullName', $event)"
                />
              </FormControl>
              <FormMessage :message="fieldError || errors.fullName" />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField, errorMessage: fieldError }" name="email">
            <FormItem>
              <FormLabel for="registerEmail">Work Email</FormLabel>
              <FormControl>
                <Input
                  id="registerEmail"
                  type="email"
                  placeholder="name@example.com"
                  autocomplete="email"
                  :form="registerFormId"
                  :disabled="submitting || !!successMessage"
                  v-bind="componentField"
                  @update:modelValue="setFieldValue('email', $event)"
                />
              </FormControl>
              <FormMessage :message="fieldError || errors.email" />
            </FormItem>
          </FormField>

          <div class="grid gap-3 sm:grid-cols-2">
            <FormField v-slot="{ componentField, errorMessage: fieldError }" name="password">
              <FormItem>
                <FormLabel for="registerPassword">Password</FormLabel>
                <FormControl>
                  <PasswordInput
                    id="registerPassword"
                    placeholder="8+ chars, letter + number"
                    class="placeholder:text-muted-foreground/45"
                    autocomplete="new-password"
                    :form="registerFormId"
                    :disabled="submitting || !!successMessage"
                    v-bind="componentField"
                    @update:modelValue="setFieldValue('password', $event)"
                  />
                </FormControl>
                <FormMessage :message="fieldError || errors.password" />
              </FormItem>
            </FormField>

            <FormField v-slot="{ componentField, errorMessage: fieldError }" name="confirmPassword">
              <FormItem>
                <FormLabel for="confirmPassword">Confirm Password</FormLabel>
                <FormControl>
                  <PasswordInput
                    id="confirmPassword"
                    placeholder="Re-enter your password"
                    autocomplete="new-password"
                    :form="registerFormId"
                    :disabled="submitting || !!successMessage"
                    v-bind="componentField"
                    @update:modelValue="setFieldValue('confirmPassword', $event)"
                  />
                </FormControl>
                <FormMessage :message="fieldError || errors.confirmPassword" />
              </FormItem>
            </FormField>
          </div>
        </div>
      </div>
    </template>
    <template #title>Register Your Organization</template>
    <template #description>
      Create an account for a school, company, or municipal hall in Cavite. Student accounts are created and managed by
      schools.
    </template>

    <Card class="border-border bg-card/95 shadow-lg shadow-sm">
      <CardContent class="space-y-4 p-4 sm:p-5">
        <!-- AuthLayout hides the left chooser below xl; keep role selection reachable on mobile. -->
        <div v-if="!routeRole" class="space-y-2 xl:hidden" role="group" aria-label="Account type">
          <p class="text-sm font-medium text-foreground">Choose account type</p>
          <div class="grid gap-2">
            <button
              type="button"
              class="rounded-xl border p-3 text-left transition"
              :class="
                selectedRole === 'school'
                  ? 'border-sky-500 bg-accent'
                  : 'border-border bg-background hover:bg-accent'
              "
              :aria-pressed="selectedRole === 'school'"
              :disabled="submitting || !!successMessage"
              @click="setFieldValue('role', 'school')"
            >
              <p class="font-semibold text-foreground">School</p>
              <p class="text-xs text-muted-foreground">Manage student access</p>
            </button>
            <button
              type="button"
              class="rounded-xl border p-3 text-left transition"
              :class="
                selectedRole === 'company'
                  ? 'border-slate-900 bg-slate-900 text-white'
                  : 'border-border bg-background hover:bg-accent'
              "
              :aria-pressed="selectedRole === 'company'"
              :disabled="submitting || !!successMessage"
              @click="setFieldValue('role', 'company')"
            >
              <p class="font-semibold" :class="selectedRole === 'company' ? 'text-white' : 'text-foreground'">Company</p>
              <p class="text-xs" :class="selectedRole === 'company' ? 'text-slate-200' : 'text-muted-foreground'">
                Post and coordinate
              </p>
            </button>
            <button
              type="button"
              class="rounded-xl border p-3 text-left transition"
              :class="
                selectedRole === 'municipal_hall'
                  ? 'border-emerald-700 bg-emerald-800 text-white'
                  : 'border-border bg-background hover:bg-accent'
              "
              :aria-pressed="selectedRole === 'municipal_hall'"
              :disabled="submitting || !!successMessage"
              @click="setFieldValue('role', 'municipal_hall')"
            >
              <p class="font-semibold" :class="selectedRole === 'municipal_hall' ? 'text-white' : 'text-foreground'">
                Municipal Hall
              </p>
              <p
                class="text-xs"
                :class="selectedRole === 'municipal_hall' ? 'text-emerald-100' : 'text-muted-foreground'"
              >
                Cavite LGU host
              </p>
            </button>
          </div>
        </div>

        <Alert v-if="successMessage" class="flex items-start gap-3 border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
          <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" />
          <div>{{ successMessage }}</div>
        </Alert>

        <Alert v-if="errorMessage" variant="destructive" class="flex items-start gap-3" role="alert">
          <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
          <div>{{ errorMessage }}</div>
        </Alert>

        <Alert
          v-if="cameFromSubscription && selectedPlanQuery"
          class="flex items-start gap-3 border-sky-200 bg-accent text-sky-800"
        >
          <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" />
          <div>
            Continuing with the <strong class="capitalize">{{ selectedPlanQuery }}</strong> plan on a
            <strong class="capitalize">{{ billingCycleQuery }}</strong> billing cycle.
          </div>
        </Alert>

        <form :id="registerFormId" class="space-y-4" novalidate @submit="onSubmit">
          <div class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-950 dark:border-amber-500/50 dark:bg-amber-950/50 dark:text-amber-50">
            <div class="flex items-center gap-2 font-semibold text-amber-950 dark:text-amber-50">
              <GraduationCap class="h-4 w-4 shrink-0" />
              <span>Student accounts are not created here</span>
            </div>
            <p class="mt-1.5 text-xs leading-4 text-amber-900/90 dark:text-amber-100/90">
              Schools invite students so intern records stay linked to the correct institution.
            </p>
          </div>

          <div class="rounded-2xl border border-border bg-muted/80 p-3.5">
            <p class="text-sm font-semibold text-foreground">
              {{
                selectedRole === 'school'
                  ? 'School details'
                  : selectedRole === 'municipal_hall'
                    ? 'Municipal Hall details'
                    : 'Company details'
              }}
            </p>
            <p class="mt-1 text-[11px] leading-4 text-muted-foreground">
              {{
                selectedRole === 'school'
                  ? 'Official school information used for student management and endorsements.'
                  : selectedRole === 'municipal_hall'
                    ? 'Official LGU information. After registration, upload authorization, ID, and office proof for verification.'
                    : 'Business details that applicants and partner schools will use for coordination.'
              }}
            </p>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
              <template v-if="selectedRole === 'school'">
                <FormField v-slot="{ componentField, errorMessage: fieldError }" name="schoolName">
                  <FormItem>
                    <FormLabel for="schoolName">School Name</FormLabel>
                    <FormControl>
                      <Input
                        id="schoolName"
                        placeholder="Institution name"
                        class="placeholder:text-muted-foreground/45"
                        :disabled="submitting || !!successMessage"
                        v-bind="componentField"
                        @update:modelValue="setFieldValue('schoolName', $event)"
                      />
                    </FormControl>
                    <FormMessage :message="fieldError || errors.schoolName" />
                  </FormItem>
                </FormField>

                <FormField v-slot="{ componentField, errorMessage: fieldError }" name="contactPerson">
                  <FormItem>
                    <FormLabel for="contactPerson">Contact Person</FormLabel>
                    <FormControl>
                      <Input
                        id="contactPerson"
                        placeholder="OJT coordinator or registrar"
                        :disabled="submitting || !!successMessage"
                        v-bind="componentField"
                        @update:modelValue="setFieldValue('contactPerson', $event)"
                      />
                    </FormControl>
                    <FormMessage :message="fieldError || errors.contactPerson" />
                  </FormItem>
                </FormField>
              </template>

              <template v-else-if="selectedRole === 'municipal_hall'">
                <FormField v-slot="{ componentField, errorMessage: fieldError }" name="officialName">
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="officialName">Official name of the municipality / LGU office</FormLabel>
                    <FormControl>
                      <Input
                        id="officialName"
                        placeholder="e.g. Municipality of Naic — Municipal Hall"
                        :disabled="submitting || !!successMessage"
                        v-bind="componentField"
                        @update:modelValue="setFieldValue('officialName', $event)"
                      />
                    </FormControl>
                    <FormMessage :message="fieldError || errors.officialName" />
                  </FormItem>
                </FormField>

                <FormField v-slot="{ errorMessage: fieldError }" name="lguType">
                  <FormItem>
                    <FormLabel for="lguType">LGU type</FormLabel>
                    <FormControl>
                      <Select
                        id="lguType"
                        :model-value="values.role === 'municipal_hall' ? values.lguType : ''"
                        :disabled="submitting || !!successMessage"
                        @update:modelValue="setFieldValue('lguType', String($event))"
                      >
                        <option value="" disabled>Select LGU type</option>
                        <option v-for="option in lguTypeOptions" :key="option.value" :value="option.value">
                          {{ option.label }}
                        </option>
                      </Select>
                    </FormControl>
                    <FormMessage :message="fieldError || errors.lguType" />
                  </FormItem>
                </FormField>

                <FormField v-slot="{ componentField, errorMessage: fieldError }" name="representativeName">
                  <FormItem>
                    <FormLabel for="representativeName">Authorized representative</FormLabel>
                    <FormControl>
                      <Input
                        id="representativeName"
                        placeholder="Full name"
                        :disabled="submitting || !!successMessage"
                        v-bind="componentField"
                        @update:modelValue="setFieldValue('representativeName', $event)"
                      />
                    </FormControl>
                    <FormMessage :message="fieldError || errors.representativeName" />
                  </FormItem>
                </FormField>

                <FormField v-slot="{ componentField, errorMessage: fieldError }" name="representativePosition">
                  <FormItem class="sm:col-span-2">
                    <FormLabel for="representativePosition">Representative position</FormLabel>
                    <FormControl>
                      <Input
                        id="representativePosition"
                        placeholder="e.g. Municipal Administrator, HR Officer"
                        :disabled="submitting || !!successMessage"
                        v-bind="componentField"
                        @update:modelValue="setFieldValue('representativePosition', $event)"
                      />
                    </FormControl>
                    <FormMessage :message="fieldError || errors.representativePosition" />
                  </FormItem>
                </FormField>
              </template>

              <template v-else>
                <FormField v-slot="{ componentField, errorMessage: fieldError }" name="companyName">
                  <FormItem>
                    <FormLabel for="companyName">Company Name</FormLabel>
                    <FormControl>
                      <Input
                        id="companyName"
                        placeholder="Enter your company name"
                        :disabled="submitting || !!successMessage"
                        v-bind="componentField"
                        @update:modelValue="setFieldValue('companyName', $event)"
                      />
                    </FormControl>
                    <FormMessage :message="fieldError || errors.companyName" />
                  </FormItem>
                </FormField>

                <FormField v-slot="{ errorMessage: fieldError }" name="industry">
                  <FormItem>
                    <FormLabel for="industry">Industry</FormLabel>
                    <FormControl>
                      <Select
                        id="industry"
                        :model-value="values.role === 'company' ? values.industry : ''"
                        :disabled="submitting || !!successMessage"
                        @update:model-value="setFieldValue('industry', $event)"
                      >
                        <option value="" disabled>Select industry</option>
                        <option v-for="option in industryOptions" :key="option" :value="option">
                          {{ option }}
                        </option>
                      </Select>
                    </FormControl>
                    <FormMessage :message="fieldError || errors.industry" />
                  </FormItem>
                </FormField>
              </template>

              <FormField v-slot="{ componentField, errorMessage: fieldError }" name="contactPhone">
                <FormItem class="sm:col-span-2">
                  <FormLabel for="contactPhone">Contact Number</FormLabel>
                  <FormControl>
                    <Input
                      id="contactPhone"
                      type="tel"
                      inputmode="tel"
                      placeholder="e.g. 09XXXXXXXXX"
                      class="placeholder:text-muted-foreground/45"
                      autocomplete="tel"
                      :disabled="submitting || !!successMessage"
                      v-bind="componentField"
                      @update:modelValue="setFieldValue('contactPhone', $event)"
                    />
                  </FormControl>
                  <FormMessage :message="fieldError || errors.contactPhone" />
                </FormItem>
              </FormField>

              <FormField v-slot="{ errorMessage: fieldError }" name="locationArea">
                <FormItem>
                  <FormLabel for="locationArea">City / Municipality (Cavite)</FormLabel>
                  <FormControl>
                    <Select
                      id="locationArea"
                      :model-value="values.locationArea || ''"
                      :disabled="submitting || !!successMessage"
                      @update:model-value="setFieldValue('locationArea', $event)"
                    >
                      <option value="" disabled>Select city / municipality</option>
                      <optgroup
                        v-for="group in caviteLocationGroups"
                        :key="group.label"
                        :label="group.label"
                      >
                        <option v-for="option in group.options" :key="option" :value="option">
                          {{ option }}
                        </option>
                      </optgroup>
                    </Select>
                  </FormControl>
                  <FormMessage :message="fieldError || errors.locationArea" />
                </FormItem>
              </FormField>

              <FormField v-slot="{ errorMessage: fieldError }" name="barangay">
                <FormItem>
                  <FormLabel for="barangay">Barangay</FormLabel>
                  <FormControl>
                    <Select
                      id="barangay"
                      :model-value="values.barangay || ''"
                      :disabled="!values.locationArea || submitting || !!successMessage"
                      @update:model-value="setFieldValue('barangay', $event)"
                    >
                      <option value="" disabled>
                        {{ values.locationArea ? 'Select barangay' : 'Select a city first' }}
                      </option>
                      <option v-for="option in barangayOptions" :key="option" :value="option">
                        {{ option }}
                      </option>
                    </Select>
                  </FormControl>
                  <FormMessage :message="fieldError || errors.barangay" />
                </FormItem>
              </FormField>

              <FormField v-slot="{ componentField, errorMessage: fieldError }" name="addressLine">
                <FormItem class="sm:col-span-2">
                  <FormLabel for="addressLine">Street / Building (optional)</FormLabel>
                  <FormControl>
                    <Input
                      id="addressLine"
                      placeholder="e.g. Rm 204, Main Admin Building"
                      :disabled="submitting || !!successMessage"
                      v-bind="componentField"
                      @update:modelValue="setFieldValue('addressLine', $event)"
                    />
                  </FormControl>
                  <FormMessage :message="fieldError || errors.addressLine" />
                </FormItem>
              </FormField>
            </div>
          </div>

          <Button type="submit" class="w-full" :disabled="submitting || !!successMessage">
            <LoaderCircle v-if="submitting" class="h-4 w-4 animate-spin" aria-hidden="true" />
            <span>
              {{
                submitting
                  ? 'Creating account…'
                  : selectedRole === 'school'
                    ? 'Create School Account'
                    : selectedRole === 'municipal_hall'
                      ? 'Create Municipal Hall Account'
                      : 'Create Company Account'
              }}
            </span>
          </Button>

          <p class="text-center text-sm text-muted-foreground">
            Already have an account?
            <RouterLink to="/login" class="font-semibold text-foreground hover:text-muted-foreground"> Login </RouterLink>
          </p>
        </form>
      </CardContent>
    </Card>
  </AuthLayout>
</template>
