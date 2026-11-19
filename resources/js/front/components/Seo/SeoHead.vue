<script lang="ts">
// Module scope on purpose: the flag has to survive component instances, because the server rendered
// tags exist only in the very first document.
let serverRenderedTagsRemoved = false
</script>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

export interface SeoProfileLink {
  platform: string
  url: string
}

export interface SeoFaqItem {
  question: string
  answer: string
}

export interface SeoPayload {
  key: string | null
  route_name: string | null
  page_name: string | null
  is_global: boolean
  path: string
  title: string
  description: string | null
  keywords: string | null
  canonical: string
  canonical_url: string
  robots: string
  og_title: string
  og_description: string | null
  og_type: string
  og_site_name: string
  og_locale: string
  og_url: string
  og_image: string | null
  twitter_card: string
  twitter_title: string
  twitter_description: string | null
  twitter_image: string | null
  json_ld: string | null
  faqs: SeoFaqItem[]
  schema_type: string | null
  aeo_summary: string | null
  aeo_search_intent: string | null
  aeo_entities: string[]
  business_name: string | null
  street_address: string | null
  address_locality: string | null
  postal_code: string | null
  address_country: string | null
  available_languages: string[]
  latitude: number | null
  longitude: number | null
  offers_online: boolean
  social_profiles: SeoProfileLink[]
  social_facebook_url: string | null
  social_instagram_url: string | null
  social_linkedin_url: string | null
  social_youtube_url: string | null
  locality: string | null
  area_served: string | null
  availability: string | null
}

// The payload comes from the shared Inertia prop, so this component never composes values on its
// own: title, description, Open Graph and JSON-LD are exactly what the server rendered.
const props = defineProps<{ seo: SeoPayload }>()

// The Blade root view already wrote these exact tags into the first response. They are removed once
// before <Head> renders, so the document head never ends up with two copies of the same tag.
if (!serverRenderedTagsRemoved && typeof document !== 'undefined') {
  serverRenderedTagsRemoved = true

  document.head.querySelectorAll('[inertia]').forEach((element) => element.remove())
}
</script>

<template>
  <Head>
    <title>{{ props.seo.title }}</title>
    <meta v-if="props.seo.description" head-key="description" name="description" :content="props.seo.description" />
    <meta v-if="props.seo.keywords" head-key="keywords" name="keywords" :content="props.seo.keywords" />
    <meta head-key="robots" name="robots" :content="props.seo.robots" />
    <link head-key="canonical" rel="canonical" :href="props.seo.canonical" />

    <meta head-key="og:title" property="og:title" :content="props.seo.og_title" />
    <meta
      v-if="props.seo.og_description"
      head-key="og:description"
      property="og:description"
      :content="props.seo.og_description"
    />
    <meta head-key="og:type" property="og:type" :content="props.seo.og_type" />
    <meta head-key="og:url" property="og:url" :content="props.seo.og_url" />
    <meta v-if="props.seo.og_image" head-key="og:image" property="og:image" :content="props.seo.og_image" />
    <meta
      v-if="props.seo.og_site_name"
      head-key="og:site_name"
      property="og:site_name"
      :content="props.seo.og_site_name"
    />
    <meta v-if="props.seo.og_locale" head-key="og:locale" property="og:locale" :content="props.seo.og_locale" />

    <meta v-if="props.seo.twitter_card" head-key="twitter:card" name="twitter:card" :content="props.seo.twitter_card" />
    <meta head-key="twitter:title" name="twitter:title" :content="props.seo.twitter_title ?? props.seo.og_title" />
    <meta
      v-if="props.seo.twitter_description ?? props.seo.og_description"
      head-key="twitter:description"
      name="twitter:description"
      :content="props.seo.twitter_description ?? props.seo.og_description"
    />
    <meta
      v-if="props.seo.twitter_image || props.seo.og_image"
      head-key="twitter:image"
      name="twitter:image"
      :content="props.seo.twitter_image ?? props.seo.og_image"
    />

    <component
      :is="'script'"
      v-if="props.seo.json_ld"
      head-key="structured-data"
      type="application/ld+json"
      :innerHTML="props.seo.json_ld"
    />
  </Head>
</template>
