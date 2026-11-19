<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import CookieConsent from '@/front/components/Cookies/CookieConsent.vue'
import Footer from '@/front/components/Footer/Footer.vue'
import Navigation from '@/front/components/Navigation/Navigation.vue'
import SeoHead, { type SeoPayload } from '@/front/components/Seo/SeoHead.vue'

// Every frontend page uses this layout, so the document head is managed in exactly one place.
// The payload is the shared Inertia prop the server also used for the first response.
const page = usePage()
const seo = computed<SeoPayload | null>(() => (page.props.seo as SeoPayload | undefined) ?? null)
</script>

<template>
  <div class="w-full bg-dark ">
    <SeoHead v-if="seo" :seo="seo" />

    <header id="site-header" class="z-40 fixed w-full">
      <Navigation />
    </header>

    <div class="z-0 w-full">
      <slot />
    </div>

    <div class="">
      <Footer />
    </div>

    <!-- The cookie dialog belongs to the layout: it is the same on every page and renders nothing
         while the site has no optional cookie to ask for. -->
    <CookieConsent />
  </div>
</template>
