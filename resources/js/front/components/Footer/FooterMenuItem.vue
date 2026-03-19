<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
  routeLink: string
  link: string
  title?: string
}>()

const page = usePage()

const normalizePath = (value: string) => {
  try {
    const url = new URL(value, window.location.origin)
    return url.pathname.replace(/\/+$/, '') || '/'
  } catch {
    return value.replace(/\/+$/, '') || '/'
  }
}

const isActive = computed(() => {
  return normalizePath(page.url) === normalizePath(props.link)
})
</script>

<template>
  <Link :href="props.link" class="inline-flex items-center py-1">
    <p class="text-sm" :class="isActive ? 'text-primary dark:text-darkAccent decoration-primary dark:decoration-darkAccent underline' : 'text-gray-600 dark:text-gray-400 hover:text-primary hover:underline hover:decoration-primary dark:hover:text-darkAccent'">
      {{ props.title }}
    </p>
  </Link>
</template>
