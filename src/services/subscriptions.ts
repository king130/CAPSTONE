import { deleteField, doc, updateDoc } from 'firebase/firestore'
import { db } from '@/services/firebase'

export type SubscriptionStatus = 'active' | 'pending' | 'inactive'

export async function updateUserSubscription(
  uid: string,
  updates: {
    plan?: string
    status?: SubscriptionStatus
    billingCycle?: string
  }
) {
  const userRef = doc(db, 'users', uid)
  const patch: Record<string, unknown> = { updatedAt: new Date() }

  if (updates.plan !== undefined) patch['subscription.plan'] = updates.plan
  if (updates.status !== undefined) patch['subscription.status'] = updates.status
  if (updates.billingCycle !== undefined) patch['subscription.billingCycle'] = updates.billingCycle

  await updateDoc(userRef, patch)
}

export type PaymentMethod = 'gcash' | 'maya' | 'bank' | 'cash' | 'other'

export async function createPendingPlanChange(
  uid: string,
  request: {
    requestId: string
    targetPlan: string
    amount: number
    currency: 'PHP'
  }
) {
  const userRef = doc(db, 'users', uid)
  await updateDoc(userRef, {
    'subscription.pendingChange': { ...request, createdAt: new Date() },
    updatedAt: new Date(),
  })
}

export async function submitPendingPlanPayment(
  uid: string,
  payload: {
    requestId: string
    method: PaymentMethod
    receiptReference: string
    paidAtIso: string
  }
) {
  const userRef = doc(db, 'users', uid)
  await updateDoc(userRef, {
    'subscription.pendingChange.payment': {
      method: payload.method,
      receiptReference: payload.receiptReference,
      paidAtIso: payload.paidAtIso,
      submittedAt: new Date(),
    },
    updatedAt: new Date(),
  })
}

export async function clearPendingPlanChange(uid: string) {
  const userRef = doc(db, 'users', uid)
  await updateDoc(userRef, {
    'subscription.pendingChange': deleteField(),
    updatedAt: new Date(),
  })
}
