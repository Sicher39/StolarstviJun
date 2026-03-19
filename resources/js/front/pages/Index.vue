<script lang="ts" setup>
import MainLayout from '@/front/layouts/MainLayout.vue'
import FlexSection from '@/front/components/Sections/FlexSection.vue'
import SeoHead, { SeoProps } from '@/front/components/Seo/SeoHead.vue'

defineOptions({
  layout: MainLayout
})

const seoHome: SeoProps = {
  title: '',
  description:
    '',
  keywords: [
    '',
  ],
  canonical: '',
  ogImage: '',
  ogSiteName: '',
  structuredData: {
    '@context': 'https://schema.org',
    '@type': '',
    name: '',
    url: '',
    address: {
      '@type': 'PostalAddress',
      streetAddress: '',
      addressLocality: '',
      postalCode: '',
      addressCountry: 'CZ'
    },
    areaServed: ''
  }
}


</script>

<template>
  <SeoHead v-bind="seoHome" />



  <FlexSection>
  <div class="block min-h-screen">
    <div class="block pt-28">
      <h1 class="text-accent">sfdsdfs</h1>
    </div>

  </div>
  </FlexSection>


</template>
