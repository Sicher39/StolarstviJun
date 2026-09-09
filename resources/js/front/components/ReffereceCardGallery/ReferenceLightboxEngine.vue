<script setup lang="ts">
import { onBeforeUnmount } from 'vue'
import PhotoSwipe from 'photoswipe'
import 'photoswipe/style.css'

type GalleryImage = {
    url: string
    alt?: string
}

const props = defineProps<{
    images: GalleryImage[]
}>()

let photoSwipeInstance: PhotoSwipe | null = null

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

const open = async (): Promise<void> => {
    if (!props.images.length) {
        return
    }

    const dataSource = await Promise.all(
        props.images.map(async (image) => {
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

defineExpose({
    open
})

onBeforeUnmount(() => {
    photoSwipeInstance?.destroy()
    photoSwipeInstance = null
})
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>
