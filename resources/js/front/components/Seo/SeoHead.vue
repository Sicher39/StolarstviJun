<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { nbspText } from '@/front/utils/czechTypography'

const DEFAULT_SITE_SUFFIX = ' | Stolařství Jun a synové'
const DEFAULT_OG_IMAGE = 'https://'
const DEFAULT_SITE_NAME = ''

export interface SeoProps {
  title: string
  description?: string
  keywords?: string[] | string
  canonical?: string
  robots?: string
  ogTitle?: string
  ogDescription?: string
  ogType?: string
  ogSiteName?: string
  ogLocale?: string
  ogUrl?: string
  ogImage?: string
  twitterCard?: string
  structuredData?: Record<string, unknown> | Record<string, unknown>[] | null
}

const props = withDefaults(defineProps<SeoProps>(), {
  robots: 'index,follow',
  ogType: 'website',
  ogLocale: 'cs_CZ',
  ogSiteName: DEFAULT_SITE_NAME,
  twitterCard: 'summary_large_image',
  structuredData: null,
})

const fullTitle = computed(() => {
  const title = nbspText(props.title)
  return title.includes('Stolařství Jun')
    ? title
    : title + DEFAULT_SITE_SUFFIX
})

const descriptionContent = computed(() => {
  return props.description ? nbspText(props.description) : null
})

const keywordsContent = computed(() => {
  if (!props.keywords) return null

  return Array.isArray(props.keywords)
    ? props.keywords.map((keyword) => nbspText(keyword)).join(', ')
    : nbspText(props.keywords)
})

const ogTitleContent = computed(() => {
  return props.ogTitle ? nbspText(props.ogTitle) : fullTitle.value
})

const ogDescriptionContent = computed(() => {
  const value = props.ogDescription ?? props.description
  return value ? nbspText(value) : null
})

const ogSiteNameContent = computed(() => {
  return props.ogSiteName ? nbspText(props.ogSiteName) : null
})

const ogImageContent = computed(() => {
  return props.ogImage ?? DEFAULT_OG_IMAGE
})

const ogUrlContent = computed(() => {
  return props.ogUrl ?? props.canonical ?? null
})

const jsonLd = computed(() => {
  if (!props.structuredData) return null
  return JSON.stringify(props.structuredData)
})
</script>

<template>
  <Head>
    <title>{{ fullTitle }}</title>
    <meta v-if="descriptionContent" head-key="description" name="description" :content="descriptionContent" />
    <meta v-if="keywordsContent" head-key="keywords" name="keywords" :content="keywordsContent" />
    <meta head-key="robots" name="robots" :content="robots" />
    <link v-if="canonical" head-key="canonical" rel="canonical" :href="canonical" />

    <meta head-key="og:title" property="og:title" :content="ogTitleContent" />
    <meta
      v-if="ogDescriptionContent"
      head-key="og:description"
      property="og:description"
      :content="ogDescriptionContent"
    />
    <meta head-key="og:type" property="og:type" :content="ogType" />
    <meta v-if="ogUrlContent" head-key="og:url" property="og:url" :content="ogUrlContent" />
    <meta head-key="og:image" property="og:image" :content="ogImageContent" />
    <meta v-if="ogSiteNameContent" head-key="og:site_name" property="og:site_name" :content="ogSiteNameContent" />
    <meta v-if="ogLocale" head-key="og:locale" property="og:locale" :content="ogLocale" />

    <meta v-if="twitterCard" head-key="twitter:card" name="twitter:card" :content="twitterCard" />
    <meta head-key="twitter:title" name="twitter:title" :content="ogTitleContent" />
    <meta
      v-if="ogDescriptionContent"
      head-key="twitter:description"
      name="twitter:description"
      :content="ogDescriptionContent"
    />
    <meta head-key="twitter:image" name="twitter:image" :content="ogImageContent" />

    <component
      :is="'script'"
      v-if="jsonLd"
      head-key="structured-data"
      type="application/ld+json"
      :innerHTML="jsonLd"
    />
  </Head>
</template>
