<script setup lang="ts">
import type { DoorVariant } from './types'
import { formatSurchargePriceWithVat, variantSpecification } from './utils'

defineProps<{
  variants: DoorVariant[]
  selectedVariantId: number | null
  vatRate: number
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
        <!-- The card is titled by the code of the variant, which is the name the catalogue gives it, so the
             line under the title repeats only the size and the opening and never the identifier again. -->
        <span class="block text-lg text-white">{{ variant.code }}</span>
        <span class="mt-1 block text-xs leading-relaxed text-white/60">{{ variantSpecification(variant) }}</span>
      </span>

      <span class="ml-auto shrink-0 text-right text-xs text-accent">{{ formatSurchargePriceWithVat(variant.price_modifier, vatRate) }}</span>
    </button>
  </div>
</template>
