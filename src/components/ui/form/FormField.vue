<script setup lang="ts" generic="TValue = unknown">
import { Field } from 'vee-validate'

defineProps<{
  name: string
}>()
</script>

<template>
  <Field :name="name" v-slot="slotProps">
    <slot v-bind="slotProps" />
  </Field>
</template>
