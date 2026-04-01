<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Triangle from "@/front/components/Icons/Triangle.vue";

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
  <Link :href="props.link" class="inline-flex items-center min-h-[44px] ">
    <div class="block group">
      <div class="flex justify-start items-start text w-full px-2">
        <p
            :class="['duration-700 font-light text-right md:text-center',
            scrolledFromTop
              ? 'text-lg'
              : 'text-lg'
          ]"
        >
          <span :class="[isActive ? 'text-accent' : 'text-white group-hover:text-accent']">
            {{ props.title }}
          </span>
        </p>
      </div>
      <div class="flex justify-center w-full">
        <Triangle :class="['-rotate-90 -mb-[4px] group-hover:transition-all group-hover:ease-out group-hover:duration-700',
        isActive ? 'text-accent' : 'text-red group-hover:text-accent'
        ]"
        />
        <div
            :class="[ 'block hidden',
          isActive
            ? 'w-[2px] h-[10px] bg-accent'
            : 'w-[2px] h-[10px] bg-red group-hover:bg-accent group-hover:transition-all group-hover:ease-out group-hover:duration-700'
        ]"
        />
      </div>
      <div :class="['flex justify-center w-full h-[2px]  group-hover:transition-all group-hover:ease-out group-hover:duration-700 ',
          isActive
          ? 'bg-accent'
          : 'bg-red group-hover:bg-accent'
          ]"
      />
    </div>
  </Link>
</template>
