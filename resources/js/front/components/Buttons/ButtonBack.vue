<script setup lang="ts">
import ArrowLeft from '@/front/components/Icons/ArrowLeft.vue'
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <div class="flex w-full">
    <Link
      href="/carporty-ploty-balkony"
      class="flex w-full items-center cursor-pointer"
    >
      <span class="flex w-full items-center">
        <ArrowLeft />
        <p class="pl-5 uppercase">zpět do katalogu</p>
      </span>
    </Link>
  </div>
</template>
