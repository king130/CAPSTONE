<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { AlertCircle, Building2, GraduationCap, LoaderCircle } from 'lucide-vue-next'
import { useForm } from 'vee-validate'
import { computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { z } from 'zod'

import Alert from '@/components/ui/alert/Alert.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import FormControl from '@/components/ui/form/FormControl.vue'
import FormField from '@/components/ui/form/FormField.vue'
import FormItem from '@/components/ui/form/FormItem.vue'
import FormLabel from '@/components/ui/form/FormLabel.vue'
import FormMessage from '@/components/ui/form/FormMessage.vue'
import Input from '@/components/ui/input/Input.vue'
import { useToast } from '@/composables/useToast'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'

interface LoginForm {
  email: string
  password: string
}

const router = useRouter()
const authStore = useAuthStore()
const { success, error } = useToast()

const loginSchema = toTypedSchema(
  z.object({
    email: z.string().min(1, 'Email is required.').email('Enter a valid email address.'),
    password: z.string().min(1, 'Password is required.'),
  }),
)

const { handleSubmit, errors, setFieldValue } = useForm<LoginForm>({
  validationSchema: loginSchema,
  initialValues: {
    email: '',
    password: '',
  },
})

const errorMessage = computed(() => authStore.error)

function getRoleDashboard(role: string | null): string {
  switch (role) {
    case 'admin':
      return '/admin/overview'
    case 'company':
      return '/dashboard'
    case 'school':
      return '/school'
    case 'student':
      return '/intern'
    case 'guest':
    case null:
      return '/guest'
    default:
      return '/guest'
  }
}

const onSubmit = handleSubmit(async (values) => {
  try {
    const profile = await authStore.login(values.email, values.password)

    success('Login successful.', {
      description: 'Your workspace is ready.',
    })

    if (!profile?.role || profile.role === 'guest' || profile.role === null) {
      router.push('/guest')
      return
    }

    router.push(getRoleDashboard(profile.role))
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Login failed.',
    })
  }
})
</script>

<template>
  <AuthLayout back-to="/" back-label="Back">
    <template #left-eyebrow>Secure Access</template>
    <template #left-title>Sign in with the account your role already uses.</template>
    <template #left-description>
      Students use school-issued credentials, while schools and companies use their registered organization account.
    </template>
    <template #title>Welcome Back</template>
    <template #description>Sign in to the correct portal for your account and continue where you left off.</template>

    <Card class="border-slate-200/80 bg-white/95 shadow-xl shadow-slate-200/70">
      <CardHeader class="pb-2" />
      <CardContent class="space-y-5 p-5 sm:p-6">
        <Alert v-if="errorMessage" variant="destructive" class="flex items-start gap-3">
          <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
          <div>{{ errorMessage }}</div>
        </Alert>

        <form class="space-y-3" @submit="onSubmit">
          <div class="grid gap-2 sm:grid-cols-2">
            <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-950">
              <div class="flex items-center gap-2 font-semibold">
                <GraduationCap class="h-4 w-4" />
                <span>Student access</span>
              </div>
              <p class="mt-2 text-xs leading-5 text-sky-900/80">
                Students log in using the school-issued email and password provided by their school.
              </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900">
              <div class="flex items-center gap-2 font-semibold">
                <Building2 class="h-4 w-4" />
                <span>School and company access</span>
              </div>
              <p class="mt-2 text-xs leading-5 text-slate-700">
                Registered school coordinators and companies can sign in here with their own account.
              </p>
            </div>
          </div>

          <FormField v-slot="{ componentField, errorMessage: fieldError }" name="email">
            <FormItem>
              <FormLabel for="email">Email</FormLabel>
              <FormControl>
                <Input
                  id="email"
                  type="email"
                  placeholder="name@example.com"
                  autocomplete="email"
                  v-bind="componentField"
                  @update:modelValue="setFieldValue('email', $event)"
                />
              </FormControl>
              <FormMessage :message="fieldError || errors.email" />
            </FormItem>
          </FormField>
          <FormField v-slot="{ componentField, errorMessage: fieldError }" name="password">
            <FormItem>
              <FormLabel for="password">Password</FormLabel>
              <FormControl>
                <Input
                  id="password"
                  type="password"
                  placeholder="Enter your password"
                  autocomplete="current-password"
                  v-bind="componentField"
                  @update:modelValue="setFieldValue('password', $event)"
                />
              </FormControl>
              <FormMessage :message="fieldError || errors.password" />
            </FormItem>
          </FormField>


          
          <Button type="submit" class="w-full" :disabled="authStore.loading">
            <LoaderCircle v-if="authStore.loading" class="h-4 w-4 animate-spin" />
            <span>{{ authStore.loading ? 'Logging in...' : 'Login' }}</span>
          </Button>

          <p class="text-center text-sm text-slate-600">
            Need an organization account?
            <RouterLink to="/register" class="font-semibold text-slate-950 hover:text-slate-700">
              Register here
            </RouterLink>
          </p>
        </form>
      </CardContent>
    </Card>
  </AuthLayout>
</template>
