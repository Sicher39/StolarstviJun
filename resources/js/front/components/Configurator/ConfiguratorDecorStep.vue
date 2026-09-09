<script setup lang="ts">
import type { DecorOption } from './types'
import { formatPriceWithVat, swatchStyle } from './utils'

defineProps<{
  decors: DecorOption[]
  selectedDecorId: number | null
}>()

const emit = defineEmits<{
  'update:selectedDecorId': [value: number | null]
}>()
</script>

<template>
  <div class="grid gap-3 sm:grid-cols-2">
    <button
      v-for="decor in decors"
      :key="decor.id"
      type="button"
      class="flex items-center gap-3 border p-3 text-left transition"
      :class="selectedDecorId === decor.id ? 'border-accent bg-white/10' : 'border-white/10 bg-dark/40 hover:border-accent'"
      @click="emit('update:selectedDecorId', decor.id)"
    >
      <span class="h-12 w-12 shrink-0 border border-white/10 bg-white/5" :style="swatchStyle(decor.texture_image_url ?? decor.preview_image_url, 'decor')" />
      <span class="min-w-0 flex-1">
        <span class="block text-base text-white uppercase">{{ decor.name }}</span>
        <span class="mt-1 block text-xs uppercase text-white/55">{{ decor.price_modifier > 0 ? '+' : '' }}{{ formatPriceWithVat(decor.price_modifier) }} vč. DPH</span>
      </span>
    </button>
  </div>
</template>
