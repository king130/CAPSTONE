<script setup lang="ts">
import { computed } from 'vue'
import { useTimeAgo } from '@vueuse/core'
import {
  BellRing,
  BriefcaseBusiness,
  CheckCheck,
  Clock3,
  GraduationCap,
  ShieldAlert,
  UserPlus,
} from 'lucide-vue-next'

import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import type { Notification } from '@/services/notifications'

interface NotificationItemProps {
  notification: Notification
  compact?: boolean
}

const props = withDefaults(defineProps<NotificationItemProps>(), {
  compact: false,
})

const emit = defineEmits<{
  open: [notification: Notification]
  'mark-read': [notification: Notification]
}>()

const timeAgo = useTimeAgo(computed(() => new Date(props.notification.createdAt)))

const iconConfig = computed(() => {
  switch (props.notification.type) {
    case 'application':
      return {
        icon: BriefcaseBusiness,
        class: 'bg-sky-100 text-sky-700',
      }
    case 'endorsement':
      return {
        icon: GraduationCap,
        class: 'bg-emerald-100 text-emerald-700',
      }
    case 'hours':
      return {
        icon: Clock3,
        class: 'bg-amber-100 text-amber-700',
      }
    case 'registration':
      return {
        icon: UserPlus,
        class: 'bg-violet-100 text-violet-700',
      }
    default:
      return {
        icon: ShieldAlert,
        class: 'bg-rose-100 text-rose-700',
      }
  }
})

function handleOpen() {
  emit('open', props.notification)
}

function handleMarkRead() {
  emit('mark-read', props.notification)
}
</script>

<template>
  <Card
    class="cursor-pointer border-border/80 transition hover:-translate-y-0.5 hover:border-sky-200 hover:shadow-md"
    :class="compact ? 'rounded-lg' : ''"
    @click="handleOpen"
  >
    <div class="flex items-start gap-4 p-4" :class="compact ? 'p-3' : 'p-4'">
      <div
        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl"
        :class="[iconConfig.class, compact ? 'h-10 w-10 rounded-xl' : '']"
      >
        <component :is="iconConfig.icon" class="h-5 w-5" />
      </div>

      <div class="min-w-0 flex-1">
        <p
          class="leading-6 text-slate-700"
          :class="props.notification.isRead ? 'font-medium' : 'font-semibold text-slate-950'"
        >
          {{ props.notification.message }}
        </p>
        <div class="mt-2 flex items-center gap-2 text-sm text-slate-500">
          <BellRing class="h-3.5 w-3.5" />
          <span>{{ timeAgo }}</span>
        </div>
      </div>

      <div class="flex shrink-0 items-center gap-2" @click.stop>
        <span v-if="!props.notification.isRead" class="h-2.5 w-2.5 rounded-full bg-sky-500" />
        <Button
          v-if="!props.notification.isRead"
          variant="ghost"
          size="sm"
          class="text-sky-700 hover:text-sky-800"
          @click="handleMarkRead"
        >
          <CheckCheck class="h-4 w-4" />
          <span v-if="!compact">Mark as read</span>
        </Button>
      </div>
    </div>
  </Card>
</template>
