<script setup lang="ts">
import MainLayout from "@/front/layouts/MainLayout.vue";
import SecondHeaderSection from "@/front/components/Sections/SecondHeaderSection.vue";
import ScrollLinesRevealSection from "@/front/components/Sections/ScrollLinesRevealSection.vue";

defineOptions({
  layout: MainLayout
})
</script>
<template>

  <SecondHeaderSection
      img="wood-door"
  >
    <template v-slot:header>
      Galerie <br>
      realizovaných <br> zakázek
    </template>
    <template v-slot:title>
      Každý detail musí dávat <br>
      smysl jako celek.
    </template>
  </SecondHeaderSection>


</template>