<script setup lang="ts">
import { ref, computed } from 'vue'
import { nbspText } from '@/front/utils/czechTypography'

const props = withDefaults(
  defineProps<{
    sectionHeader?: string
    content: {
      header?: string
      note?: string
    }[]
  }>(),
  {
    sectionHeader: '',
    content: () => []
  }
)

const sectionHeader = computed(() => nbspText(props.sectionHeader))
const sections = computed(() =>
  props.content.map((section) => ({
    ...section,
    header: nbspText(section.header ?? ''),
    note: nbspText(section.note ?? '')
  }))
)
</script>

<template>
  <div
    class="grid grid-cols-1 smW:grid-cols-2 lg:grid-cols-4 lg:grid-rows-[auto_auto] w-full py-32 reveal"
  >
    <div class="px-3 py-5 lg:row-span-2">
      <p>{{ sectionHeader }}</p>
    </div>

    <div
      v-for="(section, i) in sections"
      :key="i"
      class="grid px-3 py-5 border-l border-primary dark:border-white lg:row-span-2 lg:grid-rows-subgrid my-6 lg:my-0"
    >
      <div class="block h-[200px] smW:h-[150px] xl:h-[300px]">
        <h4 class="font-head text-4xl smW:text-3xl lg:text-xl 3xl:text-4xl font-black uppercase">
          {{ section.header }}
        </h4>
      </div>

      <p class="leading-relaxed">
        {{ section.note }}
      </p>
    </div>
  </div>
</template>
