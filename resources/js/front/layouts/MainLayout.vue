<script setup lang="ts">
import Footer from '@/front/components/Footer/Footer.vue'
import Navigation from '@/front/components/Navigation/Navigation.vue'
</script>

<template>
  <div class="w-full bg-dark ">
    <header class="z-40 fixed w-full">
      <Navigation />
    </header>

    <div class="z-0 w-full">
      <slot />
    </div>

    <div class="">
      <Footer />
    </div>
  </div>
</template>
