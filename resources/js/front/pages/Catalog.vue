<script setup lang="ts">
import { computed } from 'vue'

import CatalogCategoryNav from '@/front/components/Catalog/CatalogCategoryNav.vue'
import CatalogOptionCard from '@/front/components/Catalog/CatalogOptionCard.vue'
import CatalogVariantCard from '@/front/components/Catalog/CatalogVariantCard.vue'
import {
  CATALOG_HASH_PREFIX,
  buildCatalogCategories,
  type CatalogCategory,
  type CatalogCategoryKey
} from '@/front/components/Catalog/catalogCategories'
import {
  emptyCatalogListing,
  type CatalogListing,
  type CatalogOption
} from '@/front/components/Catalog/types'
import { formatVariantCount } from '@/front/components/Configurator/utils'
import FullSection from '@/front/components/Sections/FullSection.vue'
import SecondHeaderSection from '@/front/components/Sections/SecondHeaderSection.vue'
import { useStickySectionNav } from '@/front/composables/useStickySectionNav'
import MainLayout from '@/front/layouts/MainLayout.vue'

defineOptions({
  layout: MainLayout
})

// The page is a listing of the published offer: the models with their variants and the decors, the
// glass, the frames and the hardware those models carry. Nothing here configures a door, so the page
// only reads the payload; the configurator stays the one place a door is put together.
// Title, description and robots directives of this page are managed by the server side SEO panel
// (see config/seo.php), so no metadata is repeated here.
const props = withDefaults(
  defineProps<{
    listing?: CatalogListing
  }>(),
  {
    listing: emptyCatalogListing
  }
)

const categories = computed<CatalogCategory[]>(() => buildCatalogCategories(props.listing))
// A section without a published item is left out entirely: no item in the sticky bar and no heading in
// the page, exactly like the References page hides its empty categories.
const visibleCategories = computed<CatalogCategory[]>(() =>
  categories.value.filter((category) => category.count > 0)
)
const hasCatalog = computed<boolean>(() => visibleCategories.value.length > 0)

// The fixed site header of the layout is not part of the document flow, so the sticky bar and every
// scroll target are offset by its measured height.
const {
  headerOffset,
  stickyOffset,
  activeKey,
  stickyNavElement,
  setSectionElement,
  scrollToSection
} = useStickySectionNav<CatalogCategoryKey>({
  keys: () => visibleCategories.value.map((category) => category.key),
  hashPrefix: CATALOG_HASH_PREFIX
})

// Every section after the doors lists options, so they share one renderer and only the source list
// differs. The doors are rendered from the models instead, because a model is a heading, not a card.
const optionsByCategory = computed<Partial<Record<CatalogCategoryKey, CatalogOption[]>>>(() => ({
  dekory: props.listing.decors,
  skla: props.listing.glasses,
  zarubne: props.listing.frames,
  kovani: props.listing.hardware
}))

const optionsFor = (key: CatalogCategoryKey): CatalogOption[] => optionsByCategory.value[key] ?? []
</script>

<template>
  <div class="block w-full">
    <SecondHeaderSection img="wood-door" reserve-media-space>
      <template v-slot:header>
        Katalog
      </template>
      <template v-slot:title>
        Přehled modelů a provedení, ze kterých si můžete vybrat.
      </template>
    </SecondHeaderSection>

    <!--
      The bar stays sticky below the fixed site header, so the visitor can jump between the sections of
      the catalog while a long one scrolls underneath it. Without a single published item there is
      nothing to navigate to, so the bar is left out and only the empty state below is rendered.

      On `lg` the hero photo is offset below the heading and bleeds past the content, so on pages that
      rely on the original absolute placement of the photo it would run under this bar. `reserve-media-space`
      keeps the photo in the flow of the header instead, so the hero always ends at the rendered bottom of
      the photo and the bar starts below it, whatever height the photo has at a given viewport.
    -->
    <div
      v-if="hasCatalog"
      ref="stickyNavElement"
      class="sticky z-30 w-full  bg-dark"
      :style="{ top: `${headerOffset}px` }"
    >
      <FullSection>
        <CatalogCategoryNav
          :categories="visibleCategories"
          :active-key="activeKey"
          @select="scrollToSection"
        />
      </FullSection>
    </div>

    <FullSection>
      <p v-if="!hasCatalog" class="block mt-16 text-fluid-base text-white lg:mt-48">
        Zatím zde nejsou žádné publikované modely ani provedení.
      </p>

      <div v-else class="block space-y-16 pt-16 smW:space-y-24 smW:pt-24 lg:space-y-32 lg:pt-32">
        <section
          v-for="category in visibleCategories"
          :key="category.key"
          :ref="(element) => setSectionElement(category.key, element)"
          :id="category.anchor"
          class="block w-full"
          :style="{ scrollMarginTop: `${stickyOffset}px` }"
        >
          <h2 class="text-fluid-2xl uppercase text-accent smW:text-fluid-3xl">
            {{ category.title }}
          </h2>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-white/60">{{ category.note }}</p>

          <!-- The doors: every model is a heading and its variants are the cards under it. -->
          <template v-if="category.key === 'dvere'">
            <article
              v-for="model in props.listing.models"
              :key="model.id"
              class="mt-10 block w-full smW:mt-14"
            >
              <div
                class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-white/10 pb-3"
              >
                <h3 class="text-fluid-xl uppercase text-white smW:text-fluid-2xl">
                  {{ model.name }}
                </h3>
                <p
                  v-if="model.type_label || model.category"
                  class="text-xs uppercase tracking-[0.16em] text-white/55"
                >
                  <span v-if="model.type_label" class="text-accent">{{ model.type_label }}</span>
                  <span v-if="model.type_label && model.category"> · </span>
                  <span v-if="model.category">{{ model.category }}</span>
                </p>
              </div>

              <p v-if="model.description" class="mt-3 max-w-2xl text-sm leading-6 text-white/60">
                {{ model.description }}
              </p>

              <p
                v-if="model.variants.length"
                class="mt-3 text-xs uppercase tracking-[0.16em] text-white/45"
              >
                {{ formatVariantCount(model.variants.length) }}
              </p>

              <div
                v-if="model.variants.length"
                class="mt-4 grid grid-cols-2 gap-3 smW:grid-cols-3 smW:gap-5 lg:grid-cols-5 lg:gap-6"
              >
                <CatalogVariantCard
                  v-for="variant in model.variants"
                  :key="variant.id"
                  :variant="variant"
                />
              </div>

              <!-- A model that publishes no variant is still a model of the catalog, so it keeps its
                   heading and explains why there is nothing under it. -->
              <p
                v-else
                class="mt-4 max-w-2xl text-sm leading-6 text-white/50"
              >
                K tomuto modelu zatím nejsou publikované žádné varianty.
              </p>
            </article>
          </template>

          <!-- The options: decors, glass, frames and hardware, every card naming the models it belongs to. -->
          <div
            v-else
            class="mt-8 grid grid-cols-2 gap-3 smW:grid-cols-3 smW:gap-5 lg:grid-cols-5 lg:gap-6"
          >
            <CatalogOptionCard
              v-for="option in optionsFor(category.key)"
              :key="option.id"
              :option="option"
            />
          </div>
        </section>
      </div>
    </FullSection>

    <div class="mb-16 smW:mb-24 lg:mb-32" />
  </div>
</template>
