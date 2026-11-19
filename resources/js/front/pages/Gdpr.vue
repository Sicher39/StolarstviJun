<script setup lang="ts">
import GdprDocument from '@/front/components/Gdpr/GdprDocument.vue'
import MainLayout from '@/front/layouts/MainLayout.vue'

defineOptions({
  layout: MainLayout
})

// The text itself is a legal document edited in the administration (see the legal documents
// module), so the page only renders what the server publishes.
defineProps<{
  document: {
    title: string
    perex: string | null
    content: string
    version: string
    effective_from: string | null
    updated_at: string | null
  }
  cookie_policy_url: string
}>()

// Title, description and robots directives of this page are managed by the server side SEO panel
// (see config/seo.php), so no metadata is repeated here.
</script>

<template>
  <div class="w-full">
    <div class="flex w-full justify-center bg-dark">
      <h1 class="py-44 text-center text-primary">
        {{ document.title }}
      </h1>
    </div>

    <GdprDocument :document="document" />

    <div class="flex w-full justify-center px-2 pb-16">
      <div class="mx-auto block w-full lg:w-10/12 xl:w-8/12">
        <p class="border-t border-accent/40 pt-8 text-base text-primary">
          Jaké cookies web používá a jak je můžete povolit nebo odmítnout, popisují
          <a
            :href="cookie_policy_url"
            class="text-accent underline underline-offset-4 transition hover:text-primary"
          >zásady používání cookies</a>.
        </p>
      </div>
    </div>
  </div>
</template>
