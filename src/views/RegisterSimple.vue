<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { AlertCircle, BriefcaseBusiness, CheckCircle2, GraduationCap, LoaderCircle, School } from 'lucide-vue-next'
import { useForm } from 'vee-validate'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
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
import { useToast } from '@/composables/useToast'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { setToken } from '@/services/http'
import { useAuthStore } from '@/stores/auth'

type RegisterRole = 'school' | 'company'

interface CommonRegisterFields {
  fullName: string
  email: string
  password: string
  confirmPassword: string
  address: string
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

type RegisterForm = SchoolRegisterForm | CompanyRegisterForm

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { success, error } = useToast()
const successMessage = ref('')
const registerFormId = 'register-auth-form'
let successRedirectTimer: number | null = null

const selectedPlanQuery = computed(() => {
  const raw = String(route.query.plan || '').trim().toLowerCase()
  if (['free', 'standard', 'premium'].includes(raw)) {
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
  fullName: z.string().min(1, 'Full name is required.'),
  email: z.string().min(1, 'Email is required.').email('Enter a valid email address.'),
  password: z.string().min(8, 'Password must be at least 8 characters.'),
  confirmPassword: z.string().min(1, 'Please confirm your password.'),
  address: z.string().min(1, 'Address is required.'),
}

const schoolSchema = z.object({
  role: z.literal('school'),
  ...commonSchemaFields,
  schoolName: z.string().min(1, 'School name is required.'),
  contactPerson: z.string().min(1, 'Contact person is required.'),
})

const companySchema = z.object({
  role: z.literal('company'),
  ...commonSchemaFields,
  companyName: z.string().min(1, 'Company name is required.'),
  industry: z.string().min(1, 'Industry is required.'),
})

const registerSchema = toTypedSchema(
  z.discriminatedUnion('role', [schoolSchema, companySchema]).superRefine((data, ctx) => {
    if (data.password !== data.confirmPassword) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['confirmPassword'],
        message: 'Passwords do not match.',
      })
    }
  }),
)

const { handleSubmit, values, errors, setFieldValue } = useForm<RegisterForm>({
  validationSchema: registerSchema,
  initialValues: {
    role: 'school',
    fullName: '',
    email: '',
    password: '',
    confirmPassword: '',
    schoolName: '',
    contactPerson: '',
    address: '',
  } as SchoolRegisterForm,
})

const selectedRole = computed(() => values.role as RegisterRole)
const errorMessage = computed(() => authStore.error)
const routeRole = computed<RegisterRole | null>(() => {
  if (route.name === 'register-school') return 'school'
  if (route.name === 'register-company') return 'company'
  return null
})
const roleIntro = computed(() =>
  selectedRole.value === 'school'
    ? {
        eyebrow: 'School Setup',
        title: 'Create a school account built for coordinators and student-managed onboarding.',
        description: 'Schools create student accounts, review endorsement requests, and track placements from one workspace.',
      }
    : {
        eyebrow: 'Company Setup',
        title: 'Create a company account ready for internship posting and school coordination.',
        description: 'Companies register once to post internship roles, review endorsed students, and manage applicant decisions.',
      },
)
const roleHighlights = computed(() =>
  selectedRole.value === 'school'
    ? [
        'Create student accounts and manage intern access',
        'Review endorsement requests before students are placed',
      ]
    : [
        'Publish internship roles and accept endorsed students',
        'Coordinate requirements, contracts, and applicant decisions',
      ],
)

onMounted(() => {
  if (routeRole.value) {
    setFieldValue('role', routeRole.value)
  }

  if (authStore.user) {
    if (authStore.user.mustChangePassword) {
      router.replace('/change-password')
      return
    }

    const role = authStore.user?.role
    if (role && role !== 'guest' && role !== null) {
      const dash =
        role === 'admin'
          ? '/admin/overview'
          : role === 'company'
            ? '/dashboard'
            : role === 'school'
              ? '/school'
              : role === 'student'
                ? '/intern'
                : '/find-internships'
      router.replace(dash)
    } else {
      router.replace('/find-internships')
    }
  }
})

onBeforeUnmount(() => {
  if (successRedirectTimer) {
    window.clearTimeout(successRedirectTimer)
  }
})

const onSubmit = handleSubmit(async (formValues) => {
  successMessage.value = ''

  try {
    const payload: Parameters<typeof authStore.register>[0] =
      formValues.role === 'school'
        ? {
            fullName: formValues.fullName,
            email: formValues.email,
            password: formValues.password,
            role: 'school',
            profile: {
              institutionName: formValues.schoolName,
              schoolAddress: formValues.address,
              contactPersonName: formValues.contactPerson,
              position: formValues.contactPerson,
            },
            subscriptionPlan: selectedPlanQuery.value ?? undefined,
            billingCycle: billingCycleQuery.value,
          }
        : {
            fullName: formValues.fullName,
            email: formValues.email,
            password: formValues.password,
            role: 'company',
            profile: {
              companyName: formValues.companyName,
              industryType: formValues.industry,
              companyAddress: formValues.address,
            },
            subscriptionPlan: selectedPlanQuery.value ?? undefined,
            billingCycle: billingCycleQuery.value,
          }

    await authStore.register(payload)
    authStore.setUserProfile(null)
    setToken(null)

    successMessage.value = 'Account created successfully. Redirecting you to login...'
    success('Account created successfully.', {
      description: 'You can now log in with your new credentials.',
    })
    successRedirectTimer = window.setTimeout(() => {
      router.push('/login')
    }, 1400)
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Registration failed.',
    })
  }
})
</script>

<template>
  <AuthLayout back-to="/" back-label="Back">
    <template #left-eyebrow>{{ routeRole ? roleIntro.eyebrow : 'Organization Setup' }}</template>
    <template #left-title>{{ routeRole ? roleIntro.title : 'Create a school or company account with the right structure from the start.' }}</template>
    <template #left-description>
      {{ routeRole ? roleIntro.description : 'Schools manage student credentials, while companies create their own account to post roles and review applicants.' }}
    </template>
    <template #left-highlights>
      <div class="grid gap-3 sm:grid-cols-2 mb-2">
        <div class="rounded-2xl border border-white/60 bg-white/65 p-4 backdrop-blur">
          <p class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">School Setup</p>
          <p class="mt-2 text-lg font-semibold text-slate-900">Control student access</p>
          <p class="mt-2 text-sm leading-5 text-slate-600">Keep student records aligned by creating and managing their credentials inside the school workflow.</p>
        </div>
        <div class="rounded-2xl border border-white/60 bg-slate-900/90 p-4 text-white shadow-lg shadow-slate-900/10">
          <p class="text-xs font-medium uppercase tracking-[0.18em] text-sky-100">Company Setup</p>
          <p class="mt-2 text-lg font-semibold">Publish and coordinate</p>
          <p class="mt-2 text-sm leading-5 text-sky-100/90">Register once to start posting opportunities and working directly with partner schools.</p>
        </div>
      </div>
      <div v-if="routeRole" class="rounded-2xl border border-white/60 bg-white/70 p-4 text-sm text-slate-700 backdrop-blur">
        <p class="font-semibold text-slate-900">{{ roleIntro.eyebrow }}</p>
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
        <div class="rounded-2xl bg-white/85 p-4">
          <p class="text-sm font-medium text-slate-500">Account Basics</p>
          <p class="mt-2 text-xl font-semibold text-slate-900">Set up the essentials</p>
          <p class="mt-2 text-sm text-slate-600">Choose your organization type and enter the main account details here first.</p>
        </div>

        <FormField v-slot="{ errorMessage: fieldError }" name="role">
          <FormItem>
            <FormLabel class="text-slate-700">Choose account type</FormLabel>
            <FormControl>
              <div class="grid gap-2.5 sm:grid-cols-2">
                <button
                  type="button"
                  class="rounded-2xl border p-3 text-left transition"
                  :class="
                    selectedRole === 'school'
                      ? 'border-sky-500 bg-sky-50 shadow-[0_12px_30px_rgba(14,116,144,0.12)]'
                      : 'border-slate-200 bg-white/80 hover:border-slate-300 hover:bg-white'
                  "
                  @click="setFieldValue('role', 'school')"
                  :disabled="routeRole === 'company'"
                >
                  <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-sky-100 p-2 text-sky-700">
                      <School class="h-5 w-5" />
                    </div>
                    <div>
                      <p class="font-semibold text-slate-950">School</p>
                      <p class="text-[11px] text-slate-600">Manage student access</p>
                    </div>
                  </div>
                </button>

                <button
                  type="button"
                  class="rounded-2xl border p-3 text-left transition"
                  :class="
                    selectedRole === 'company'
                      ? 'border-slate-900 bg-slate-900 text-white shadow-[0_12px_30px_rgba(15,23,42,0.18)]'
                      : 'border-slate-200 bg-white/80 hover:border-slate-300 hover:bg-white'
                  "
                  @click="setFieldValue('role', 'company')"
                  :disabled="routeRole === 'school'"
                >
                  <div class="flex items-center gap-3">
                    <div
                      class="rounded-xl p-2"
                      :class="selectedRole === 'company' ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-700'"
                    >
                      <BriefcaseBusiness class="h-5 w-5" />
                    </div>
                    <div>
                      <p class="font-semibold" :class="selectedRole === 'company' ? 'text-white' : 'text-slate-950'">Company</p>
                      <p class="text-[11px]" :class="selectedRole === 'company' ? 'text-slate-200' : 'text-slate-600'">Post and coordinate</p>
                    </div>
                  </div>
                </button>
              </div>
            </FormControl>
            <FormMessage :message="fieldError || errors.role" />
          </FormItem>
        </FormField>

        <div class="grid gap-3 rounded-2xl border border-white/60 bg-white/70 p-4 backdrop-blur">
          <FormField v-slot="{ componentField, errorMessage: fieldError }" name="fullName">
            <FormItem>
              <FormLabel for="fullName">
                {{ selectedRole === 'school' ? 'Administrator Name' : 'Representative Name' }}
              </FormLabel>
              <FormControl>
                <Input
                  id="fullName"
                  :placeholder="selectedRole === 'school' ? 'Enter the coordinator name' : 'Enter the company representative name'"
                  autocomplete="name"
                  :form="registerFormId"
                  v-bind="componentField"
                  @update:modelValue="setFieldValue('fullName', $event)"
                />
              </FormControl>
              <FormMessage :message="fieldError || errors.fullName" />
            </FormItem>
          </FormField>

          <FormField v-slot="{ componentField, errorMessage: fieldError }" name="email">
            <FormItem>
              <FormLabel for="registerEmail">Email</FormLabel>
              <FormControl>
                <Input
                  id="registerEmail"
                  type="email"
                  placeholder="name@example.com"
                  autocomplete="email"
                  :form="registerFormId"
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
                  <Input
                    id="registerPassword"
                    type="password"
                    placeholder="Create a password"
                    autocomplete="new-password"
                    :form="registerFormId"
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
                  <Input
                    id="confirmPassword"
                    type="password"
                    placeholder="Re-enter your password"
                    autocomplete="new-password"
                    :form="registerFormId"
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
    <template #description>Create an account for a school or company. Student accounts are created and managed by schools.</template>

    <Card class="border-slate-200/80 bg-white/95 shadow-lg shadow-slate-200/70">
      <CardContent class="space-y-4 p-4 sm:p-5">
        <Alert v-if="successMessage" class="flex items-start gap-3 border-emerald-200 bg-emerald-50 text-emerald-700">
          <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" />
          <div>{{ successMessage }}</div>
        </Alert>

        <Alert v-if="errorMessage" variant="destructive" class="flex items-start gap-3">
          <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
          <div>{{ errorMessage }}</div>
        </Alert>

        <Alert
          v-if="cameFromSubscription && selectedPlanQuery"
          class="flex items-start gap-3 border-sky-200 bg-sky-50 text-sky-800"
        >
          <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" />
          <div>
            Continuing with the <strong class="capitalize">{{ selectedPlanQuery }}</strong> plan on a
            <strong class="capitalize">{{ billingCycleQuery }}</strong> billing cycle.
          </div>
        </Alert>

        <form :id="registerFormId" class="space-y-4" @submit="onSubmit">
          <div class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-950">
            <div class="flex items-center gap-2 font-semibold">
              <GraduationCap class="h-4 w-4" />
              <span>Student accounts are not created here</span>
            </div>
            <p class="mt-1.5 text-xs leading-4 text-amber-900/80">
              Schools create student login credentials so intern records stay aligned with the correct institution.
            </p>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5">
            <p class="text-sm font-semibold text-slate-900">
              {{ selectedRole === 'school' ? 'School details' : 'Company details' }}
            </p>
            <p class="mt-1 text-[11px] leading-4 text-slate-600">
              {{ selectedRole === 'school'
                ? 'Add the official school information that will be used to manage student accounts.'
                : 'Add the business details that applicants and partner schools will use for coordination.' }}
            </p>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
              <template v-if="selectedRole === 'school'">
                <FormField v-slot="{ componentField, errorMessage: fieldError }" name="schoolName">
                  <FormItem>
                    <FormLabel for="schoolName">School Name</FormLabel>
                    <FormControl>
                      <Input
                        id="schoolName"
                        placeholder="Enter your institution name"
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
                        placeholder="Enter the primary contact person"
                        v-bind="componentField"
                        @update:modelValue="setFieldValue('contactPerson', $event)"
                      />
                    </FormControl>
                    <FormMessage :message="fieldError || errors.contactPerson" />
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
                        v-bind="componentField"
                        @update:modelValue="setFieldValue('companyName', $event)"
                      />
                    </FormControl>
                    <FormMessage :message="fieldError || errors.companyName" />
                  </FormItem>
                </FormField>

                <FormField v-slot="{ componentField, errorMessage: fieldError }" name="industry">
                  <FormItem>
                    <FormLabel for="industry">Industry</FormLabel>
                    <FormControl>
                      <Input
                        id="industry"
                        placeholder="e.g. Technology, Finance, Healthcare"
                        v-bind="componentField"
                        @update:modelValue="setFieldValue('industry', $event)"
                      />
                    </FormControl>
                    <FormMessage :message="fieldError || errors.industry" />
                  </FormItem>
                </FormField>
              </template>

              <FormField v-slot="{ componentField, errorMessage: fieldError }" name="address">
                <FormItem class="sm:col-span-2">
                  <FormLabel :for="selectedRole === 'school' ? 'schoolAddress' : 'companyAddress'">Address</FormLabel>
                  <FormControl>
                    <Input
                      :id="selectedRole === 'school' ? 'schoolAddress' : 'companyAddress'"
                      :placeholder="selectedRole === 'school' ? 'Enter the school address' : 'Enter the company address'"
                      v-bind="componentField"
                      @update:modelValue="setFieldValue('address', $event)"
                    />
                  </FormControl>
                  <FormMessage :message="fieldError || errors.address" />
                </FormItem>
              </FormField>
            </div>
          </div>

          <Button type="submit" class="w-full" :disabled="authStore.loading || !!successMessage">
            <LoaderCircle v-if="authStore.loading" class="h-4 w-4 animate-spin" />
            <span>{{ authStore.loading ? 'Creating account...' : selectedRole === 'school' ? 'Create School Account' : 'Create Company Account' }}</span>
          </Button>

          <p class="text-center text-sm text-slate-600">
            Already have an account?
            <RouterLink to="/login" class="font-semibold text-slate-950 hover:text-slate-700">
              Login
            </RouterLink>
          </p>
        </form>
      </CardContent>
    </Card>
  </AuthLayout>
</template>
