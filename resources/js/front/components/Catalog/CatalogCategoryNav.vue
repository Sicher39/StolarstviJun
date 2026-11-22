<script setup lang="ts">
import type { CatalogCategory, CatalogCategoryKey } from './catalogCategories'

const props = withDefaults(
  defineProps<{
    categories?: CatalogCategory[]
    activeKey?: CatalogCategoryKey | null
  }>(),
  {
    categories: () => [],
    activeKey: null
  }
)

const emit = defineEmits<{
  select: [key: CatalogCategoryKey]
}>()

const isActive = (key: CatalogCategoryKey): boolean => props.activeKey === key
</script>

<template>
  <!-- The bar is text only: the catalog has no category pictures, so it needs none of the icon cards
       of the References page. On phones it stays a full width snap scroller, on a desktop the items
       share the row equally. -->
  <nav
    class="mx-auto flex w-full snap-x snap-mandatory overflow-x-auto scrollbar-hide bg-dark lg:snap-none lg:justify-center lg:overflow-visible"
    aria-label="Kategorie katalogu"
  >
    <button
      v-for="category in props.categories"
      :key="category.key"
      type="button"
      class="min-w-[7rem] flex-1 snap-start border-b-2 px-3 py-4 text-center transition duration-300 cursor-pointer lg:max-w-[20%]"
      :class="
        isActive(category.key)
          ? 'border-accent text-white'
          : 'border-transparent text-white/60 hover:text-white'
      "
      :aria-current="isActive(category.key) ? 'true' : undefined"
      :aria-controls="category.anchor"
      @click="emit('select', category.key)"
    >
      <span class="block text-xs uppercase tracking-[0.18em]">{{ category.text }}</span>
    </button>
  </nav>
</template>
