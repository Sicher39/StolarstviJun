<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const scrolledFromTop = ref(false)
const handleScroll = () => {
  scrolledFromTop.value = window.scrollY >= 50
}

onMounted(() => window.addEventListener('scroll', handleScroll))
onUnmounted(() => window.removeEventListener('scroll', handleScroll))

const props = defineProps<{
  link: string
  title: string
  routeLink?: string
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
  <Link :href="props.link" class="inline-flex items-center min-h-[44px] px-5 lg:px-0">
    <div class="relative group">
      <div
        class="absolute -bottom-1 left-0 w-0 h-[1px]"
        :class="[
          isActive
            ? 'w-full bg-gradient-to-r from-primary dark:from-dark via-darkAccent/80 to-darkAccent'
            : 'text w-full group-hover:bg-gradient-to-r group-hover:from-dark/0 group-hover:via-accent/80 group-hover:to-accent group-hover:transition-all group-hover:ease-out group-hover:duration-700'
        ]"
      ></div>

      <div class="flex justify-end items-start text w-full">
        <p
          class="duration-700 font-normal text-right md:text-center "
          :class="[
            scrolledFromTop
              ? 'text-sm lg:text-sm'
              : 'text-sm lg:text-sm'
          ]"
        >
          <span :class="[isActive ? 'text-darkAccent' : 'text-white group-hover:text-darkAccent']">
            {{ props.title }}
          </span>
        </p>
      </div>
    </div>
  </Link>
</template>
