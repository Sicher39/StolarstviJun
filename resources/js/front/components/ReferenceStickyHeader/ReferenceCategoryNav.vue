<script setup lang="ts">
import ReferenceIconItem from '@/front/components/ReferenceStickyHeader/ReferenceIconItem.vue'
import type {
    ReferenceCategory,
    ReferenceCategoryKey
} from '@/front/components/ReffereceCardGallery/referenceCategories'

const props = withDefaults(
    defineProps<{
        categories?: ReferenceCategory[]
        activeKey?: ReferenceCategoryKey | null
    }>(),
    {
        categories: () => [],
        activeKey: null
    }
)

const emit = defineEmits<{
    select: [key: ReferenceCategoryKey]
}>()

const isActive = (key: ReferenceCategoryKey): boolean => props.activeKey === key
</script>

<template>
    <nav
        class="flex w-full snap-x snap-mandatory overflow-x-auto scrollbar-hide bg-dark lg:snap-none lg:overflow-visible"
        aria-label="Kategorie referencí"
    >
        <button
            v-for="category in props.categories"
            :key="category.key"
            type="button"
            class="flex min-w-[6.5rem] flex-1 snap-start flex-col border-b-2 transition duration-300 cursor-pointer"
            :class="
                isActive(category.key)
                    ? 'border-accent opacity-100'
                    : 'border-transparent opacity-60 hover:opacity-100'
            "
            :aria-current="isActive(category.key) ? 'true' : undefined"
            :aria-controls="`reference-${category.key}`"
            @click="emit('select', category.key)"
        >
            <ReferenceIconItem :icon="category.icon" :text="category.text" />
        </button>
    </nav>
</template>
