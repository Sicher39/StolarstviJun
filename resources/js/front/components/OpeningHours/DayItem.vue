<script setup lang="ts">
const props = defineProps<{
  day?: string
  hours?: string
  footer?: boolean
}>()
</script>

<template>
  <div class="grid grid-cols-12 px-2">
    <div class="col-span-5 md:col-span-3 lg:col-span-4">
      <p :class="footer ? 'text-sm text-primary dark:text-gray-200' : 'text-xl text-gray-600 dark:text-gray-200'">{{ props.day }}:</p>
    </div>
    <div class="col-span-7 md:col-span-8 lg:col-span-8">
      <p class=" text-left" :class="footer ? 'text-sm text-primary dark:text-gray-200' : 'text-xl text-gray-600 dark:text-gray-200'">{{ props.hours }}</p>
    </div>
  </div>
</template>
