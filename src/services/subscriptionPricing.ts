import { apiFetch } from './http'

export interface SubscriptionPlan {
  id: string
  name: string
  schoolPrice: number
  companyPrice: number
  description: string
  features: {
    school: string[]
    company: string[]
  }
  limits: {
    school: { coordinators: number; students: number }
    company: { accounts: number; internships: number }
  }
}

type SubscriptionPlansResponse = {
  data: SubscriptionPlan[]
}

/** Bundled catalog when GET /subscription-plans fails (network, CORS, wrong API URL). Mirrors server fallback. */
export const DEFAULT_SUBSCRIPTION_PLANS: SubscriptionPlan[] = [
  {
    id: 'free',
    name: 'Free',
    description: 'Perfect for getting started',
    schoolPrice: 0,
    companyPrice: 0,
    features: {
      school: [
        '1 school coordinator account',
        'Up to 5 student accounts',
        'Basic student management',
        'Simple reporting dashboard',
        'Email support',
        'Basic internship matching',
      ],
      company: [
        '1 company account',
        'Post up to 3 internships',
        'Basic applicant management',
        'Simple reporting dashboard',
        'Email support',
        'Basic candidate matching',
      ],
    },
    limits: {
      school: { coordinators: 1, students: 5 },
      company: { accounts: 1, internships: 3 },
    },
  },
  {
    id: 'standard',
    name: 'Standard',
    description: 'Ideal for growing organizations',
    schoolPrice: 1999,
    companyPrice: 2499,
    features: {
      school: [
        '5 school coordinator accounts',
        'Up to 100 student accounts',
        'Advanced student analytics',
        'Bulk student management',
        'Priority email and chat support',
        'Custom reports and exports',
        'Advanced matching algorithms',
      ],
      company: [
        '5 company accounts',
        'Unlimited internship postings',
        'Advanced applicant tracking',
        'Analytics dashboard',
        'Priority email and chat support',
        'Integration support',
        'Advanced filtering and search',
      ],
    },
    limits: {
      school: { coordinators: 5, students: 100 },
      company: { accounts: 5, internships: 999 },
    },
  },
  {
    id: 'premium',
    name: 'Premium',
    description: 'Complete enterprise solution',
    schoolPrice: 3999,
    companyPrice: 4999,
    features: {
      school: [
        'Unlimited coordinator accounts',
        'Unlimited student accounts',
        'Advanced analytics dashboard',
        'Custom integrations and API access',
        'Dedicated account manager',
        'White-label options',
        'Priority phone support',
      ],
      company: [
        'Unlimited company accounts',
        'Unlimited internship postings',
        'Advanced analytics and reporting',
        'Custom integrations and API access',
        'Dedicated account manager',
        'Custom branding options',
        'Priority phone support',
      ],
    },
    limits: {
      school: { coordinators: 999, students: 999 },
      company: { accounts: 999, internships: 999 },
    },
  },
]

function normalizePlansResponse(raw: unknown): SubscriptionPlan[] | null {
  if (Array.isArray(raw) && raw.length) {
    return raw as SubscriptionPlan[]
  }
  if (raw && typeof raw === 'object' && 'data' in raw) {
    const d = (raw as SubscriptionPlansResponse).data
    if (Array.isArray(d) && d.length) {
      return d
    }
  }
  return null
}

export type GetSubscriptionPlansOptions = {
  /** When false, empty or failed API responses throw (use for admin saves). Default true for read-only UIs. */
  fallbackOnError?: boolean
}

export async function getSubscriptionPlans(options: GetSubscriptionPlansOptions = {}): Promise<SubscriptionPlan[]> {
  const fallbackOnError = options.fallbackOnError !== false

  try {
    const raw = await apiFetch<unknown>('/subscription-plans')
    const list = normalizePlansResponse(raw)
    if (list?.length) {
      return list
    }
    if (!fallbackOnError) {
      throw new Error('The API returned no subscription plans.')
    }
    console.warn('[subscription-plans] empty response; using bundled defaults')
    return cloneDefaultPlans()
  } catch (error) {
    if (!fallbackOnError) {
      throw error
    }
    console.warn('[subscription-plans] request failed; using bundled defaults', error)
    return cloneDefaultPlans()
  }
}

function cloneDefaultPlans(): SubscriptionPlan[] {
  return DEFAULT_SUBSCRIPTION_PLANS.map((p) => ({
    ...p,
    features: { school: [...p.features.school], company: [...p.features.company] },
    limits: {
      school: { ...p.limits.school },
      company: { ...p.limits.company },
    },
  }))
}

export async function updatePlanPricing(
  planId: string,
  updates: { schoolPrice?: number; companyPrice?: number },
): Promise<void> {
  const plans = await getSubscriptionPlans({ fallbackOnError: false })
  const plan = plans.find((candidate) => candidate.id === planId)

  if (!plan) {
    throw new Error(`Plan with id ${planId} not found`)
  }

  await updateSubscriptionPlan(planId, {
    description: plan.description,
    schoolPrice: updates.schoolPrice ?? plan.schoolPrice,
    companyPrice: updates.companyPrice ?? plan.companyPrice,
    schoolFeatures: plan.features.school,
    companyFeatures: plan.features.company,
    limits: plan.limits,
  })
}

export async function updatePlanLimits(
  planId: string,
  updates: {
    schoolCoordinators?: number
    schoolStudents?: number
    companyAccounts?: number
    companyInternships?: number
  },
): Promise<void> {
  const plans = await getSubscriptionPlans({ fallbackOnError: false })
  const plan = plans.find((candidate) => candidate.id === planId)

  if (!plan) {
    throw new Error(`Plan with id ${planId} not found`)
  }

  await updateSubscriptionPlan(planId, {
    description: plan.description,
    schoolPrice: plan.schoolPrice,
    companyPrice: plan.companyPrice,
    schoolFeatures: plan.features.school,
    companyFeatures: plan.features.company,
    limits: {
      school: {
        coordinators: updates.schoolCoordinators ?? plan.limits.school.coordinators,
        students: updates.schoolStudents ?? plan.limits.school.students,
      },
      company: {
        accounts: updates.companyAccounts ?? plan.limits.company.accounts,
        internships: updates.companyInternships ?? plan.limits.company.internships,
      },
    },
  })
}

export async function updateSubscriptionPlan(
  planId: string,
  updates: Partial<{
    description: string
    schoolPrice: number
    companyPrice: number
    schoolFeatures: string[]
    companyFeatures: string[]
    limits: {
      school: { coordinators: number; students: number }
      company: { accounts: number; internships: number }
    }
  }>,
): Promise<void> {
  const plans = await getSubscriptionPlans({ fallbackOnError: false })
  const current = plans.find((candidate) => candidate.id === planId)

  if (!current) {
    throw new Error(`Plan with id ${planId} not found`)
  }

  await apiFetch(`/admin/subscription-plans/${planId}`, {
    method: 'PATCH',
    body: JSON.stringify({
      description: updates.description ?? current.description,
      schoolPrice: updates.schoolPrice ?? current.schoolPrice,
      companyPrice: updates.companyPrice ?? current.companyPrice,
      schoolFeatures: updates.schoolFeatures ?? current.features.school,
      companyFeatures: updates.companyFeatures ?? current.features.company,
      limits: updates.limits ?? current.limits,
    }),
  })
}

export async function getPricingForRole(role: 'school' | 'company'): Promise<{
  Free: { price: string; description: string; features: string[]; limits: Record<string, number> }
  Standard: { price: string; description: string; features: string[]; limits: Record<string, number> }
  Premium: { price: string; description: string; features: string[]; limits: Record<string, number> }
}> {
  const plans = await getSubscriptionPlans()

  const result = {} as {
    Free: { price: string; description: string; features: string[]; limits: Record<string, number> }
    Standard: { price: string; description: string; features: string[]; limits: Record<string, number> }
    Premium: { price: string; description: string; features: string[]; limits: Record<string, number> }
  }

  plans.forEach((plan) => {
    const price = role === 'school' ? plan.schoolPrice : plan.companyPrice
    result[plan.name as keyof typeof result] = {
      price: formatPrice(price),
      description: plan.description,
      features: plan.features[role] || [],
      limits: plan.limits[role] || {},
    }
  })

  return result
}

export function formatPrice(price: number): string {
  return price === 0 ? 'PHP 0' : `PHP ${price.toLocaleString()}`
}
