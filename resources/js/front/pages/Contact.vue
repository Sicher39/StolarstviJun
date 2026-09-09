<script setup lang="ts" >
import MainLayout from "@/front/layouts/MainLayout.vue";
import SecondHeaderSection from "@/front/components/Sections/SecondHeaderSection.vue";

defineOptions({
  layout: MainLayout
})
</script>

<template>

  <SecondHeaderSection
      img="wood-door"
  >
    <template v-slot:header>
      Kontakty
    </template>
  </SecondHeaderSection>
</template>