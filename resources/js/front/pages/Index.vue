<script lang="ts" setup>
import MainLayout from '@/front/layouts/MainLayout.vue'
import SeoHead, { SeoProps } from '@/front/components/Seo/SeoHead.vue'
import FullSection from "@/front/components/Sections/FullSection.vue"
import ButtonMain from "@/front/components/Buttons/ButtonMain.vue";

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



<FullSection>
  <div class="relative min-h-screen  w-full ">

      <div class="absolute top-[150px] left-0 z-10 w-full">
        <h1 class="text-white items-end leading-[150px]  text-5xl lg:text-8xl">
          Každý kus dřeva <br>
          má svůj příběh, <br>
          my mu dáme tvar <br>
          podle vašich představ
        </h1>
        <div class="block w-[250px] group">
          <ButtonMain><span class="uppercase font-main">Konfigurátor dveří</span></ButtonMain>
        </div>

      </div>
    <div class="absolute right-0 top-[410px] w-full">
      <div class="flex w-full justify-center">
        <img src="/img/bg/01/red-door.png" class="w-[1080px] " alt="">
      </div>
    </div>
    

  </div>
</FullSection>


   <FullSection>
     <div class="block w-full py-20 mt-32">
       <h3 class="text-accent text-6xl ">Poctivé stolařství <br>
         bez kompromisů kvality</h3>
       <p class="mt-5">Nejsme sériová výroba. Každé dveře vznikají na zakázku – podle prostoru, stylu i přání zákazníka.</p>
     </div>
   </FullSection>

</template>
