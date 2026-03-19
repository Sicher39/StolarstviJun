<script setup lang="ts">
import Footer from '@/front/components/Footer/Footer.vue'
import Navigation from '@/front/components/Navigation/Navigation.vue'
</script>

<template>
  <div class="block w-full bg-dark">
    <header class="z-20 fixed w-full">
      <Navigation />
    </header>

    <div class="z-0">
      <slot />
    </div>

    <div>
      <Footer />
    </div>
  </div>
</template>
