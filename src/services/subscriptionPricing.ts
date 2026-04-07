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

export async function getSubscriptionPlans(): Promise<SubscriptionPlan[]> {
  const response = await apiFetch<SubscriptionPlansResponse>('/subscription-plans')
  return response.data ?? []
}

export async function updatePlanPricing(
  planId: string,
  updates: { schoolPrice?: number; companyPrice?: number },
): Promise<void> {
  const plans = await getSubscriptionPlans()
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
  const plans = await getSubscriptionPlans()
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
  const plans = await getSubscriptionPlans()
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
