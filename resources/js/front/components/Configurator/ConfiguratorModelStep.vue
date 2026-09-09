<script setup lang="ts">
import type { DoorModel } from './types'
import { formatPriceWithVat } from './utils'

defineProps<{
  doorModels: DoorModel[]
  selectedModelId: number | null
}>()

const emit = defineEmits<{
  'update:selectedModelId': [value: number | null]
}>()
</script>

<template>
  <div class="grid gap-3 sm:grid-cols-2">
    <button
      v-for="model in doorModels"
      :key="model.id"
      type="button"
      class="border p-4 text-left transition"
      :class="selectedModelId === model.id ? 'border-accent bg-white/10' : 'border-white/10 bg-dark/40 hover:border-accent'"
      @click="emit('update:selectedModelId', model.id)"
    >
      <div class="flex items-start justify-between gap-3">
        <p class="text-xl text-white uppercase">{{ model.name }}</p>
        <span v-if="model.category" class="text-[10px] uppercase tracking-[0.16em] text-accent">{{ model.category }}</span>
      </div>
      <div class="mt-5 flex items-center justify-between gap-3 text-xs uppercase tracking-[0.16em] text-white/55">
        <span>{{ model.variants.length }} variant</span>
        <span>{{ formatPriceWithVat(model.base_price_without_vat) }} vč. DPH</span>
      </div>
    </button>
  </div>
</template>
