<script setup lang="ts">
import type { DoorVariant } from './types'
import { formatPriceWithVat, variantLabel } from './utils'

defineProps<{
  variants: DoorVariant[]
  selectedVariantId: number | null
}>()

const emit = defineEmits<{
  'update:selectedVariantId': [value: number | null]
}>()
</script>

<template>
  <div class="grid gap-3">
    <button
      v-for="variant in variants"
      :key="variant.id"
      type="button"
      class="flex items-center justify-between gap-4 border p-4 text-left transition"
      :class="selectedVariantId === variant.id ? 'border-accent bg-white/10' : 'border-white/10 bg-dark/40 hover:border-accent'"
      @click="emit('update:selectedVariantId', variant.id)"
    >
      <div>
        <p class="text-lg text-white uppercase">{{ variant.code }}</p>
        <p class="mt-1 text-xs uppercase leading-relaxed text-white/60">{{ variantLabel(variant) }}</p>
      </div>
      <p class="shrink-0 text-xs uppercase text-accent">{{ variant.price_modifier > 0 ? '+' : '' }}{{ formatPriceWithVat(variant.price_modifier) }} vč. DPH</p>
    </button>
  </div>
</template>
