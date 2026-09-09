<script setup lang="ts">
import type { PreviewSceneMaterial } from './types'
import { swatchStyle } from './utils'

defineProps<{
  materials: PreviewSceneMaterial[]
  selectedMaterialId: number | null
  title: string
}>()

const emit = defineEmits<{
  'update:selectedMaterialId': [value: number | null]
}>()
</script>

<template>
  <div class="space-y-3">
    <p class="text-xs uppercase tracking-[0.18em] text-white/55">{{ title }}</p>

    <div class="grid gap-3 sm:grid-cols-2">
      <button
        v-for="material in materials"
        :key="material.id"
        type="button"
        class="flex items-center gap-3 border p-3 text-left transition"
        :class="selectedMaterialId === material.id ? 'border-accent bg-white/10' : 'border-white/10 bg-dark/40 hover:border-accent'"
        @click="emit('update:selectedMaterialId', material.id)"
      >
        <span
          class="h-12 w-12 shrink-0 border border-white/10 bg-white/5"
          :style="swatchStyle(material.preview_image_url ?? material.texture_image_url, 'decor')"
        />
        <span class="min-w-0 flex-1">
          <span class="block text-base text-white uppercase">{{ material.name }}</span>
          <span class="mt-1 block text-xs uppercase text-white/55">{{ material.type === 'wall' ? 'Stěna' : 'Podlaha' }}</span>
        </span>
      </button>
    </div>
  </div>
</template>
