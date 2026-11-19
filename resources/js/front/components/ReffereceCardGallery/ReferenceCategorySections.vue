<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, type ComponentPublicInstance } from 'vue'
import FullSection from '@/front/components/Sections/FullSection.vue'
import ReferenceCategoryNav from '@/front/components/ReferenceStickyHeader/ReferenceCategoryNav.vue'
import ReferenceCard from '@/front/components/ReffereceCardGallery/ReferenceCard.vue'
import ReferenceLightboxEngine from '@/front/components/ReffereceCardGallery/ReferenceLightboxEngine.vue'
import {
    referenceCategories,
    type ReferenceCard as ReferenceCardItem,
    type ReferenceCategoryKey,
    type ReferenceImage
} from '@/front/components/ReffereceCardGallery/referenceCategories'

// The fixed site header (MainLayout) is not part of the document flow, so the sticky bar has to
// know its height and keep itself right below it.
const SITE_HEADER_ID = 'site-header'
const FALLBACK_HEADER_HEIGHT = 120
const ACTIVE_SECTION_TOLERANCE = 16
const BOTTOM_TOLERANCE = 2
const HASH_PREFIX = 'reference-'
const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)'

const categories = referenceCategories
const activeKey = ref<ReferenceCategoryKey>(categories[0]?.key ?? 'dvere')

const stickyNavElement = ref<HTMLElement | null>(null)
const lightbox = ref<InstanceType<typeof ReferenceLightboxEngine> | null>(null)
const lightboxImages = ref<ReferenceImage[]>([])

// Section elements are only read inside event handlers, no need to make them reactive.
const sectionElements: Partial<Record<ReferenceCategoryKey, HTMLElement>> = {}

const headerOffset = ref(FALLBACK_HEADER_HEIGHT)
const navHeight = ref(0)

const stickyOffset = computed<number>(() => headerOffset.value + navHeight.value)

const setSectionElement = (
    key: ReferenceCategoryKey,
    element: Element | ComponentPublicInstance | null
): void => {
    if (element instanceof HTMLElement) {
        sectionElements[key] = element
    }
}

const measureOffsets = (): void => {
    const siteHeader = document.getElementById(SITE_HEADER_ID)
    // The mobile menu is a sibling of the visible bar inside the header, so measure only the bar.
    const headerBar = siteHeader?.querySelector('nav')
    const headerHeight = headerBar?.getBoundingClientRect().height ?? 0

    headerOffset.value = Math.round(
        headerHeight > 0 ? headerHeight : FALLBACK_HEADER_HEIGHT
    )
    navHeight.value = stickyNavElement.value
        ? Math.round(stickyNavElement.value.getBoundingClientRect().height)
        : 0
}

// The section becomes active once its top passes the bottom edge of the sticky icon bar.
const updateActiveCategory = (): void => {
    const threshold = stickyOffset.value + ACTIVE_SECTION_TOLERANCE
    let nextActiveKey: ReferenceCategoryKey | undefined = categories[0]?.key

    categories.forEach((category) => {
        const element = sectionElements[category.key]

        if (element && element.getBoundingClientRect().top <= threshold) {
            nextActiveKey = category.key
        }
    })

    const isPageBottom =
        window.innerHeight + window.scrollY >=
        document.documentElement.scrollHeight - BOTTOM_TOLERANCE

    if (isPageBottom) {
        nextActiveKey = categories[categories.length - 1]?.key ?? nextActiveKey
    }

    if (nextActiveKey && nextActiveKey !== activeKey.value) {
        activeKey.value = nextActiveKey
    }
}

let frameId: number | null = null

const scheduleOffsetUpdate = (): void => {
    if (frameId !== null) {
        return
    }

    frameId = requestAnimationFrame(() => {
        frameId = null
        measureOffsets()
        updateActiveCategory()
    })
}

const scrollToCategory = (key: ReferenceCategoryKey): void => {
    const element = sectionElements[key]

    if (!element) {
        return
    }

    const prefersReducedMotion = window.matchMedia(REDUCED_MOTION_QUERY).matches
    const top = window.scrollY + element.getBoundingClientRect().top - stickyOffset.value

    window.scrollTo({
        top: Math.max(top, 0),
        behavior: prefersReducedMotion ? 'auto' : 'smooth'
    })
    activeKey.value = key
}

const openCardGallery = async (card: ReferenceCardItem): Promise<void> => {
    lightboxImages.value = card.images
    await nextTick()
    await lightbox.value?.open()
}

const getCategoryKeyFromHash = (): ReferenceCategoryKey | null => {
    const hash = window.location.hash.replace('#', '')

    if (!hash.startsWith(HASH_PREFIX)) {
        return null
    }

    const key = hash.slice(HASH_PREFIX.length)
    const isKnownKey = categories.some((category) => category.key === key)

    return isKnownKey ? (key as ReferenceCategoryKey) : null
}

onMounted(async (): Promise<void> => {
    await nextTick()

    measureOffsets()
    updateActiveCategory()

    const hashKey = getCategoryKeyFromHash()

    if (hashKey) {
        scrollToCategory(hashKey)
    }

    window.addEventListener('scroll', scheduleOffsetUpdate, { passive: true })
    window.addEventListener('resize', scheduleOffsetUpdate)
})

onBeforeUnmount((): void => {
    window.removeEventListener('scroll', scheduleOffsetUpdate)
    window.removeEventListener('resize', scheduleOffsetUpdate)

    if (frameId !== null) {
        cancelAnimationFrame(frameId)
        frameId = null
    }
})
</script>

<template>
    <div class="block w-full">
        <div
            ref="stickyNavElement"
            class="sticky z-30 w-full bg-dark shadow-2xl"
            :style="{ top: `${headerOffset}px` }"
        >
            <FullSection>
                <ReferenceCategoryNav
                    :categories="categories"
                    :active-key="activeKey"
                    @select="scrollToCategory"
                />
            </FullSection>
        </div>

        <FullSection>
            <div class="block space-y-24 pt-24 lg:space-y-48 lg:pt-48">
                <section
                    v-for="category in categories"
                    :id="`${HASH_PREFIX}${category.key}`"
                    :key="category.key"
                    :ref="(element) => setSectionElement(category.key, element)"
                    class="block w-full"
                    :style="{ scrollMarginTop: `${stickyOffset}px` }"
                >
                    <h3 class="mb-6 text-3xl uppercase text-accent md:mb-10 md:text-5xl">
                        {{ category.text }}
                    </h3>

                    <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-3 lg:gap-10">
                        <ReferenceCard
                            v-for="card in category.cards"
                            :key="card.id"
                            class="aspect-[3/4] w-full"
                            :image="card.images[0]?.url ?? ''"
                            :header="card.header"
                            :note="card.note"
                            :aria-label="`Otevřít galerii reference ${card.header} ${card.id}`"
                            @click="openCardGallery(card)"
                        />
                    </div>
                </section>
            </div>
        </FullSection>

        <ReferenceLightboxEngine ref="lightbox" :images="lightboxImages" />
    </div>
</template>
