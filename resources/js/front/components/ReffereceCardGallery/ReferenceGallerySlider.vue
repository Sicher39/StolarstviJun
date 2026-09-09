<script setup lang="ts">
import MagnifyingGlass from '@/front/components/IconComponents/MagnifyingGlass.vue'
import ArrowLeft from '@/front/components/Icons/ArrowLeft.vue'
import { Link, router } from '@inertiajs/vue3'
import PhotoSwipe from 'photoswipe'
import 'photoswipe/style.css'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

type GalleryImage = {
    url: string
    alt?: string
}

type ReferenceItem = {
    id: number
    header: string
    note: string
    images: GalleryImage[]
}

const maxCards = 10
const dragThreshold = 8

const references: ReferenceItem[] = [
    {
        id: 1,
        header: 'dveře jako ',
        note: 'Babice nad Svitavou',
        images: [
            { url: '/img/bg/small/Doors01.webp', alt: 'Reference 1 - 1' },
            { url: '/img/bg/small/Doors02.webp', alt: 'Reference 1 - 2' },
            { url: '/img/bg/small/Doors03.webp', alt: 'Reference 1 - 3' }
        ]
    },
    {
        id: 2,
        header: 'dveře jako ',
        note: 'Babice nad Svitavou',
        images: [
            { url: '/img/bg/headers/red-door.webp', alt: 'Reference 2 - 1' },
            { url: '/img/bg/headers/wood-door.webp', alt: 'Reference 2 - 2' }
        ]
    },
    {
        id: 3,
        header: 'dveře jako ',
        note: 'Babice nad Svitavou',
        images: [
            { url: '/img/bg/small/Doors02.webp', alt: 'Reference 3 - 1' },
            { url: '/img/bg/small/Doors03.webp', alt: 'Reference 3 - 2' }
        ]
    },
    {
        id: 4,
        header: 'dveře jako ',
        note: 'Babice nad Svitavou',
        images: [
            { url: '/img/bg/headers/wood-door.webp', alt: 'Reference 4 - 1' },
            { url: '/img/bg/headers/red-door.webp', alt: 'Reference 4 - 2' }
        ]
    },
    {
        id: 5,
        header: 'dveře jako ',
        note: 'Babice nad Svitavou',
        images: [
            { url: '/img/bg/small/Doors03.webp', alt: 'Reference 5 - 1' },
            { url: '/img/bg/small/Doors01.webp', alt: 'Reference 5 - 2' }
        ]
    },
    {
        id: 6,
        header: 'dveře jako ',
        note: 'Babice nad Svitavou',
        images: [
            { url: '/img/bg/small/Doors01.webp', alt: 'Reference 6 - 1' },
            { url: '/img/bg/small/Doors02.webp', alt: 'Reference 6 - 2' }
        ]
    },
    {
        id: 7,
        header: 'dveře jako ',
        note: 'Babice nad Svitavou',
        images: [
            { url: '/img/bg/headers/red-door.webp', alt: 'Reference 7 - 1' },
            { url: '/img/bg/small/Doors03.webp', alt: 'Reference 7 - 2' }
        ]
    },
    {
        id: 8,
        header: 'dveře jako ',
        note: 'Babice nad Svitavou',
        images: [
            { url: '/img/bg/headers/wood-door.webp', alt: 'Reference 8 - 1' },
            { url: '/img/bg/small/Doors01.webp', alt: 'Reference 8 - 2' }
        ]
    },
    {
        id: 9,
        header: 'dveře jako ',
        note: 'Babice nad Svitavou',
        images: [
            { url: '/img/bg/small/Doors02.webp', alt: 'Reference 9 - 1' },
            { url: '/img/bg/headers/red-door.webp', alt: 'Reference 9 - 2' }
        ]
    },
    {
        id: 10,
        header: 'dveře jako ',
        note: 'Babice nad Svitavou',
        images: [
            { url: '/img/bg/small/Doors03.webp', alt: 'Reference 10 - 1' },
            { url: '/img/bg/headers/wood-door.webp', alt: 'Reference 10 - 2' }
        ]
    },
    {
        id: 11,
        header: 'dveře jako ',
        note: 'Babice nad Svitavou',
        images: [
            { url: '/img/bg/small/Doors01.webp', alt: 'Reference 11 - 1' },
            { url: '/img/bg/small/Doors02.webp', alt: 'Reference 11 - 2' }
        ]
    }
]

const slider = ref<HTMLElement | null>(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)
const isDragging = ref(false)
const previousScrollPosition = ref(0)
const currentScrollPosition = ref(0)
const targetScrollPosition = ref(0)
const suppressClick = ref(false)

let animationFrameId: number | null = null
let cleanup: (() => void) | null = null
let photoSwipeInstance: PhotoSwipe | null = null

const hasMoreReferences = computed(() => {
    return references.length > maxCards
})

const visibleReferences = computed(() => {
    if (hasMoreReferences.value) {
        return references.slice(0, maxCards - 1)
    }

    return references.slice(0, maxCards)
})

const sliderCursorClass = computed(() => {
    return isDragging.value ? 'cursor-grabbing' : 'cursor-grab'
})

const updateScrollButtons = (): void => {
    const element = slider.value

    if (!element) {
        canScrollLeft.value = false
        canScrollRight.value = false
        return
    }

    const maxScrollLeft = element.scrollWidth - element.clientWidth
    const threshold = 4

    canScrollLeft.value = element.scrollLeft > threshold
    canScrollRight.value = element.scrollLeft < maxScrollLeft - threshold
}

const stopAnimation = (): void => {
    if (animationFrameId !== null) {
        cancelAnimationFrame(animationFrameId)
        animationFrameId = null
    }
}

const animateScroll = (): void => {
    const element = slider.value

    if (!element) {
        stopAnimation()
        return
    }

    currentScrollPosition.value += (targetScrollPosition.value - currentScrollPosition.value) * 0.14

    if (Math.abs(targetScrollPosition.value - currentScrollPosition.value) < 0.5) {
        currentScrollPosition.value = targetScrollPosition.value
    }

    element.scrollLeft = currentScrollPosition.value
    updateScrollButtons()

    if (Math.abs(targetScrollPosition.value - currentScrollPosition.value) >= 0.5) {
        animationFrameId = requestAnimationFrame(animateScroll)
    } else {
        stopAnimation()
    }
}

const startAnimation = (): void => {
    if (animationFrameId === null) {
        animationFrameId = requestAnimationFrame(animateScroll)
    }
}

const scrollByAmount = (direction: number): void => {
    const element = slider.value

    if (!element) {
        return
    }

    const amount = element.clientWidth * 0.8
    const maxScrollLeft = element.scrollWidth - element.clientWidth

    targetScrollPosition.value = Math.min(
        maxScrollLeft,
        Math.max(0, element.scrollLeft + direction * amount)
    )
    currentScrollPosition.value = element.scrollLeft

    startAnimation()
}

const getImageSize = (src: string): Promise<{ width: number; height: number }> => {
    return new Promise((resolve, reject) => {
        const image = new Image()

        image.onload = () => {
            resolve({ width: image.naturalWidth, height: image.naturalHeight })
        }

        image.onerror = reject
        image.src = src
    })
}

const openReferenceGallery = async (images: GalleryImage[]): Promise<void> => {
    if (!images.length) {
        return
    }

    const dataSource = await Promise.all(
        images.map(async (image) => {
            try {
                const { width, height } = await getImageSize(image.url)

                return {
                    src: image.url,
                    alt: image.alt ?? '',
                    width,
                    height
                }
            } catch (error) {
                console.error(`Chyba při načítání obrázku: ${image.url}`, error)

                return {
                    src: image.url,
                    alt: image.alt ?? '',
                    width: 1600,
                    height: 1200
                }
            }
        })
    )

    photoSwipeInstance?.destroy()

    photoSwipeInstance = new PhotoSwipe({
        dataSource,
        index: 0,
        showHideAnimationType: 'zoom',
        bgOpacity: 0.9
    })

    photoSwipeInstance.init()
}

const handleReferenceClick = async (reference: ReferenceItem, event: MouseEvent): Promise<void> => {
    if (suppressClick.value) {
        event.preventDefault()
        event.stopPropagation()
        return
    }

    await openReferenceGallery(reference.images)
}

const handleReferenceNavigation = (event: MouseEvent): void => {
    if (suppressClick.value) {
        event.preventDefault()
        event.stopPropagation()
        return
    }

    router.visit(route('front.references'))
}

onMounted(() => {
    const element = slider.value

    if (!element) {
        return
    }

    let pointerId: number | null = null
    let startX = 0
    let startScrollLeft = 0
    let dragDistance = 0

    const clampScroll = (value: number): number => {
        const maxScrollLeft = element.scrollWidth - element.clientWidth

        return Math.min(maxScrollLeft, Math.max(0, value))
    }

    const onPointerDown = (event: PointerEvent): void => {
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return
        }

        isDragging.value = true
        suppressClick.value = false
        pointerId = event.pointerId
        startX = event.clientX
        startScrollLeft = element.scrollLeft
        dragDistance = 0
        previousScrollPosition.value = element.scrollLeft
        currentScrollPosition.value = element.scrollLeft
        targetScrollPosition.value = element.scrollLeft

        stopAnimation()
    }

    const onPointerMove = (event: PointerEvent): void => {
        if (!isDragging.value || pointerId !== event.pointerId) {
            return
        }

        const deltaX = event.clientX - startX
        dragDistance = Math.max(dragDistance, Math.abs(deltaX))

        if (dragDistance > dragThreshold) {
            if (!element.hasPointerCapture(event.pointerId)) {
                element.setPointerCapture(event.pointerId)
            }

            event.preventDefault()
            suppressClick.value = true
        }

        const nextScrollLeft = clampScroll(startScrollLeft - deltaX)
        const velocity = nextScrollLeft - previousScrollPosition.value

        previousScrollPosition.value = nextScrollLeft
        currentScrollPosition.value = nextScrollLeft
        targetScrollPosition.value = clampScroll(nextScrollLeft + velocity * 2.2)
        element.scrollLeft = nextScrollLeft

        updateScrollButtons()
    }

    const endDrag = (event: PointerEvent): void => {
        if (
            pointerId !== null &&
            event.pointerId === pointerId &&
            element.hasPointerCapture(event.pointerId)
        ) {
            element.releasePointerCapture(event.pointerId)
        }

        if (!isDragging.value) {
            return
        }

        isDragging.value = false
        pointerId = null
        currentScrollPosition.value = element.scrollLeft

        window.setTimeout(() => {
            suppressClick.value = false
        }, 80)

        if (Math.abs(targetScrollPosition.value - currentScrollPosition.value) > 1) {
            startAnimation()
        } else {
            targetScrollPosition.value = currentScrollPosition.value
            updateScrollButtons()
        }
    }

    const onClickCapture = (event: MouseEvent): void => {
        if (!suppressClick.value) {
            return
        }

        event.preventDefault()
        event.stopPropagation()
    }

    const onScroll = (): void => {
        if (isDragging.value || animationFrameId !== null) {
            return
        }

        currentScrollPosition.value = element.scrollLeft
        targetScrollPosition.value = element.scrollLeft
        updateScrollButtons()
    }

    const onResize = (): void => {
        targetScrollPosition.value = clampScroll(targetScrollPosition.value)
        currentScrollPosition.value = clampScroll(element.scrollLeft)
        element.scrollLeft = currentScrollPosition.value
        updateScrollButtons()
    }

    element.addEventListener('pointerdown', onPointerDown)
    element.addEventListener('pointermove', onPointerMove)
    element.addEventListener('pointerup', endDrag)
    element.addEventListener('pointercancel', endDrag)
    element.addEventListener('click', onClickCapture, true)
    element.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onResize)

    updateScrollButtons()

    cleanup = () => {
        stopAnimation()
        photoSwipeInstance?.destroy()
        photoSwipeInstance = null
        element.removeEventListener('pointerdown', onPointerDown)
        element.removeEventListener('pointermove', onPointerMove)
        element.removeEventListener('pointerup', endDrag)
        element.removeEventListener('pointercancel', endDrag)
        element.removeEventListener('click', onClickCapture, true)
        element.removeEventListener('scroll', onScroll)
        window.removeEventListener('resize', onResize)
    }
})

onBeforeUnmount(() => {
    if (cleanup) {
        cleanup()
    }
})
</script>

<template>
    <section class="py-10 mb-32">
        <div class="relative">
            <button
                v-if="canScrollLeft"
                type="button"
                class="absolute left-4 top-1/2 z-30 flex h-12 w-12 -translate-y-1/2 items-center justify-center border border-white/70 bg-black/40 text-white backdrop-blur-sm transition hover:bg-black/60 cursor-pointer"
                @click="scrollByAmount(-1)"
            >
                <ArrowLeft />
            </button>

            <button
                v-if="canScrollRight"
                type="button"
                class="absolute right-4 top-1/2 z-30 flex h-12 w-12 -translate-y-1/2 items-center justify-center border border-white/70 bg-black/40 text-white backdrop-blur-sm transition hover:bg-black/60 cursor-pointer"
                @click="scrollByAmount(1)"
            >
                <ArrowLeft class="rotate-180" />
            </button>

            <div
                ref="slider"
                :class="[
                    'overflow-x-auto overflow-y-hidden scrollbar-hide select-none touch-pan-x',
                    sliderCursorClass
                ]"
            >
                <div class="flex w-max gap-5 pl-5 pr-4 lg:gap-20 lg:pl-96">
                    <button
                        v-for="reference in visibleReferences"
                        :key="reference.id"
                        type="button"
                        class="group relative z-10 w-[150px] flex justify-center items-center text-left shrink-0 overflow-hidden border border-white bg-cover bg-center bg-no-repeat px-10 py-48 lg:w-[350px] cursor-pointer"
                        :style="{ backgroundImage: `url(${reference.images[0]?.url ?? ''})` }"
                        :aria-label="`Otevřít galerii reference ${reference.id}`"
                        @click="handleReferenceClick(reference, $event)"
                    >
                        <span
                            class="absolute inset-0  bg-dark/60 transition duration-500 group-hover:bg-dark/0"
                        />
                        <span class="relative z-10 text-white group-hover:text-white/20">
                            <MagnifyingGlass />
                        </span>
                      <span class="absolute w-full bottom-0 group-hover:bg-dark/60">
                        <template class="block px-4 pb-2 w-full">

                        <span class="font-main uppercase text-sm text-white">{{ reference.header }}</span> <br>
                        <span class="font-main text-sm text-white">{{ reference.note }}</span>
                        </template>
                      </span>
                    </button>

                    <Link
                        v-if="hasMoreReferences"
                        :href="route('front.references')"
                        class="group relative z-10 flex w-[150px] shrink-0 items-center justify-center overflow-hidden border border-white bg-primary/10 px-6 py-48 text-center lg:w-[350px]"
                        @click.prevent="handleReferenceNavigation($event)"
                    >
                        <div
                            class="absolute inset-0 bg-dark/10 transition duration-500 group-hover:bg-red"
                        />
                        <span
                            class="p relative z-10 text-lg font-semibold uppercase text-white lg:text-2xl"
                        >
                            Všechny reference
                        </span>
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
