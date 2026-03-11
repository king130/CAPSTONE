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

// Default pricing structure
const defaultPlans: SubscriptionPlan[] = [
  {
    id: 'free',
    name: 'Free',
    schoolPrice: 0,
    companyPrice: 0,
    description: 'Perfect for getting started',
    features: {
      school: [
        '1 School coordinator account',
        'Up to 5 student accounts',
        'Basic student management',
        'Simple reporting dashboard',
        'Email support',
        'Basic internship matching'
      ],
      company: [
        '1 Company account',
        'Post up to 3 internships',
        'Basic applicant management',
        'Simple reporting dashboard',
        'Email support',
        'Basic candidate matching'
      ]
    },
    limits: {
      school: { coordinators: 1, students: 5 },
      company: { accounts: 1, internships: 3 }
    }
  },
  {
    id: 'standard',
    name: 'Standard',
    schoolPrice: 1999,
    companyPrice: 2499,
    description: 'Ideal for growing organizations',
    features: {
      school: [
        '5 School coordinator accounts',
        'Up to 100 student accounts',
        'Advanced student analytics',
        'Bulk student management',
        'Priority email & chat support',
        'Custom reports & exports',
        'Advanced matching algorithms'
      ],
      company: [
        '5 Company accounts',
        'Unlimited internship postings',
        'Advanced applicant tracking',
        'Analytics dashboard',
        'Priority email & chat support',
        'Integration support',
        'Advanced filtering & search'
      ]
    },
    limits: {
      school: { coordinators: 5, students: 100 },
      company: { accounts: 5, internships: 999 }
    }
  },
  {
    id: 'premium',
    name: 'Premium',
    schoolPrice: 3999,
    companyPrice: 4999,
    description: 'Complete enterprise solution',
    features: {
      school: [
        'Unlimited coordinator accounts',
        'Unlimited student accounts',
        'Advanced analytics dashboard',
        'Custom integrations & API access',
        'Dedicated account manager',
        'White-label options',
        'Priority phone support'
      ],
      company: [
        'Unlimited company accounts',
        'Unlimited internship postings',
        'Advanced analytics & reporting',
        'Custom integrations & API access',
        'Dedicated account manager',
        'Custom branding options',
        'Priority phone support'
      ]
    },
    limits: {
      school: { coordinators: 999, students: 999 },
      company: { accounts: 999, internships: 999 }
    }
  }
]

// Local storage key for pricing data
const PRICING_STORAGE_KEY = 'subscription_pricing'

/**
 * Get subscription pricing plans
 * In a real app, this would fetch from an API
 */
export async function getSubscriptionPlans(): Promise<SubscriptionPlan[]> {
  try {
    // Try to get from localStorage first (simulating API cache)
    const stored = localStorage.getItem(PRICING_STORAGE_KEY)
    if (stored) {
      return JSON.parse(stored)
    }
    
    // TODO: Replace with actual API call
    // const response = await fetch('/api/subscription-plans')
    // return await response.json()
    
    // For now, return default plans and store them
    localStorage.setItem(PRICING_STORAGE_KEY, JSON.stringify(defaultPlans))
    return defaultPlans
  } catch (error) {
    console.error('Failed to load subscription plans:', error)
    return defaultPlans
  }
}

/**
 * Update subscription plan pricing
 * In a real app, this would make an API call
 */
export async function updatePlanPricing(
  planId: string, 
  updates: { schoolPrice?: number; companyPrice?: number }
): Promise<void> {
  try {
    // Get current plans
    const plans = await getSubscriptionPlans()
    
    // Find and update the plan
    const planIndex = plans.findIndex(p => p.id === planId)
    if (planIndex === -1) {
      throw new Error(`Plan with id ${planId} not found`)
    }
    
    // Update the plan
    const plan = plans[planIndex]
    if (!plan) {
      throw new Error(`Plan at index ${planIndex} not found`)
    }
    
    if (updates.schoolPrice !== undefined) {
      plan.schoolPrice = updates.schoolPrice
    }
    if (updates.companyPrice !== undefined) {
      plan.companyPrice = updates.companyPrice
    }
    
    // Save to localStorage (simulating API call)
    localStorage.setItem(PRICING_STORAGE_KEY, JSON.stringify(plans))
    
    // TODO: Replace with actual API call
    // await fetch(`/api/subscription-plans/${planId}`, {
    //   method: 'PATCH',
    //   headers: { 'Content-Type': 'application/json' },
    //   body: JSON.stringify(updates)
    // })
    
  } catch (error) {
    console.error('Failed to update plan pricing:', error)
    throw error
  }
}

/**
 * Update subscription plan limits
 * In a real app, this would make an API call
 */
export async function updatePlanLimits(
  planId: string, 
  updates: { 
    schoolCoordinators?: number; 
    schoolStudents?: number;
    companyAccounts?: number;
    companyInternships?: number;
  }
): Promise<void> {
  try {
    // Get current plans
    const plans = await getSubscriptionPlans()
    
    // Find and update the plan
    const planIndex = plans.findIndex(p => p.id === planId)
    if (planIndex === -1) {
      throw new Error(`Plan with id ${planId} not found`)
    }
    
    // Update the plan limits
    const plan = plans[planIndex]
    if (!plan) {
      throw new Error(`Plan at index ${planIndex} not found`)
    }
    
    if (updates.schoolCoordinators !== undefined && plan.limits.school) {
      plan.limits.school.coordinators = updates.schoolCoordinators
    }
    if (updates.schoolStudents !== undefined && plan.limits.school) {
      plan.limits.school.students = updates.schoolStudents
    }
    if (updates.companyAccounts !== undefined && plan.limits.company) {
      plan.limits.company.accounts = updates.companyAccounts
    }
    if (updates.companyInternships !== undefined && plan.limits.company) {
      plan.limits.company.internships = updates.companyInternships
    }
    
    // Save to localStorage (simulating API call)
    localStorage.setItem(PRICING_STORAGE_KEY, JSON.stringify(plans))
    
    // TODO: Replace with actual API call
    // await fetch(`/api/subscription-plans/${planId}/limits`, {
    //   method: 'PATCH',
    //   headers: { 'Content-Type': 'application/json' },
    //   body: JSON.stringify(updates)
    // })
    
  } catch (error) {
    console.error('Failed to update plan limits:', error)
    throw error
  }
}

/**
 * Get pricing for a specific role
 */
export async function getPricingForRole(role: 'school' | 'company'): Promise<{
  Free: { price: string; description: string; features: string[]; limits: any }
  Standard: { price: string; description: string; features: string[]; limits: any }
  Premium: { price: string; description: string; features: string[]; limits: any }
}> {
  const plans = await getSubscriptionPlans()
  
  const result: any = {}
  
  plans.forEach(plan => {
    const price = role === 'school' ? plan.schoolPrice : plan.companyPrice
    result[plan.name] = {
      price: price === 0 ? '₱0' : `₱${price.toLocaleString()}`,
      description: plan.description,
      features: plan.features[role] || [],
      limits: plan.limits[role] || {}
    }
  })
  
  return result
}

/**
 * Format price for display
 */
export function formatPrice(price: number): string {
  return price === 0 ? '₱0' : `₱${price.toLocaleString()}`
}