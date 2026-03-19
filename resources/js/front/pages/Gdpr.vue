<script setup lang="ts">
import BasicGdpr from '@/front/components/Gdpr/BasicGdpr.vue'
import MainLayout from '@/front/layouts/MainLayout.vue'
import SeoHead, { type SeoProps } from '@/front/components/Seo/SeoHead.vue'

defineOptions({
  layout: MainLayout
})

const seoGdpr: SeoProps = {
  title: 'Podminky ochrany osobnich udaju (GDPR) | MIRA elektromontazni Brno',
  description:
    'Informace o tom, jak MIRA, elektromontazni spolecnost s r.o., zpracovava osobni udaje zakazniku a navstevniku webu v souladu s narizenim GDPR.',
  keywords: ['GDPR MIRA', 'ochrana osobnich udaju', 'zasady zpracovani osobnich udaju'],
  canonical: 'https://mirabrno.cz/gdpr',
  ogSiteName: 'MIRA elektromontazni Brno'
}
</script>

<template>
  <SeoHead v-bind="seoGdpr" />

  <div class="flex w-full justify-center bg-dark">
    <h1 class="py-44 text-center text-light">
      Podminky ochrany <br />
      osobnich udaju
    </h1>
  </div>

  <BasicGdpr
    administrator="MIRA, elektromontazni spolecnost s r.o. | Lubomir Oskrda"
    company-id="27745015"
    address="Cejl 58/72, Brno 602 00"
    email="mira@mirabrno.cz"
    tel="+420 777 758 830"
    date="1.9.2024"
  />
</template>
