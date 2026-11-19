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
      class="flex flex-wrap items-center gap-4 border p-4 text-left transition"
      :class="selectedVariantId === variant.id ? 'border-accent bg-white/10' : 'border-white/10 bg-dark/40 hover:border-accent'"
      @click="emit('update:selectedVariantId', variant.id)"
    >
      <span class="block h-16 w-12 shrink-0 overflow-hidden border border-white/10 bg-white/5">
        <img
          v-if="variant.source_reference_url"
          :src="variant.source_reference_url"
          :alt="variant.code"
          class="block h-full w-full object-contain"
          loading="lazy"
        />
      </span>

      <span class="flex-1">
        <span class="block text-lg text-white uppercase">{{ variant.code }}</span>
        <span class="mt-1 block text-xs uppercase leading-relaxed text-white/60">{{ variantLabel(variant) }}</span>
      </span>

      <span class="ml-auto shrink-0 text-right text-xs uppercase text-accent">{{ variant.price_modifier > 0 ? '+' : '' }}{{ formatPriceWithVat(variant.price_modifier) }} vč. DPH</span>
    </button>
  </div>
</template>
