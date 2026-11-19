<script setup lang="ts">
import MainLayout from "@/front/layouts/MainLayout.vue";
import SecondHeaderSection from "@/front/components/Sections/SecondHeaderSection.vue";
import ReferenceCategorySections from "@/front/components/ReffereceCardGallery/ReferenceCategorySections.vue";

defineOptions({
  layout: MainLayout
})
</script>
<template>

  <SecondHeaderSection
      img="references"
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

  <!-- The icon bar rendered inside stays sticky below the fixed site header and scrolls to its category strip. -->
  <ReferenceCategorySections/>

</template>
