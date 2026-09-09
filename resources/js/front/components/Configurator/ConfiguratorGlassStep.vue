<script setup lang="ts">
import type { GlassOption } from './types'
import { formatPriceWithVat, swatchStyle } from './utils'

defineProps<{
  glasses: GlassOption[]
  selectedGlassId: number | null
}>()

const emit = defineEmits<{
  'update:selectedGlassId': [value: number | null]
}>()
</script>

<template>
  <div class="grid gap-3 sm:grid-cols-2">
    <button
      v-for="glass in glasses"
      :key="glass.id"
      type="button"
      class="flex items-center gap-3 border p-3 text-left transition"
      :class="selectedGlassId === glass.id ? 'border-accent bg-white/10' : 'border-white/10 bg-dark/40 hover:border-accent'"
      @click="emit('update:selectedGlassId', glass.id)"
    >
      <span class="h-12 w-12 shrink-0 border border-white/10 bg-white/5" :style="swatchStyle(glass.preview_image_url ?? glass.texture_image_url, 'glass')" />
      <span class="min-w-0 flex-1">
        <span class="block text-base text-white uppercase">{{ glass.name }}</span>
        <span class="mt-1 block text-xs uppercase text-white/55">{{ glass.price_modifier > 0 ? '+' : '' }}{{ formatPriceWithVat(glass.price_modifier) }} vč. DPH</span>
      </span>
    </button>
  </div>
</template>
