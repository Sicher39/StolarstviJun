<script setup lang="ts">
import MainLayout from "@/front/layouts/MainLayout.vue";

import SecondHeaderSection from "@/front/components/Sections/SecondHeaderSection.vue";
import ScrollLinesRevealSection from "@/front/components/Sections/ScrollLinesRevealSection.vue";
import ReferenceGallerySlider from "@/front/components/ReffereceCardGallery/ReferenceGallerySlider.vue";
import FullSection from "@/front/components/Sections/FullSection.vue";

defineOptions({
  layout: MainLayout
})
</script>
<template>


  <SecondHeaderSection
      img="wood-door"
  >
    <template v-slot:header>
      Dveře dělají první dojem <br>
      proto záleží <br> na každém detailu
    </template>
    <template v-slot:title>
      Nevyrábíme sériově a&nbsp;neděláme kompromisy tam, kde by se později vymstily.
    </template>
  </SecondHeaderSection>

<div class="block mt-48">
  <ScrollLinesRevealSection>
    Zakázková výroba ze dřeva vyžaduje víc než jen přesné rozměry. Každý výrobek musí respektovat účel, zatížení, prostředí i&nbsp;návaznost na ostatní prvky v&nbsp; prostoru.
  </ScrollLinesRevealSection>
</div>



  <FullSection>
    <div class="block my-48">
      <h4 class="text-accent text-6xl 2xl:text-8xl reveal">Galerie <br> realizovaných zakázek,</h4>
      <h5 class="mt-5">které vydrží roky, ne sezóny.</h5>
    </div>
    <div class="block w-full">
      <ReferenceGallerySlider/>
    </div>
  </FullSection>
</template>