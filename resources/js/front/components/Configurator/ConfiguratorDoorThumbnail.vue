<script setup lang="ts">
import { computed } from 'vue'

import ConfiguratorDoorLayers from './ConfiguratorDoorLayers.vue'
import type { InquiryItemDraft } from './types'
import { doorCanvasSize, glassOpacityFraction } from './utils'

/**
 * The illustration of one request item: the door of that item only, composed from the model, the variant
 * and the decor it was configured with. The room is left out on purpose - the item card keeps the door,
 * while the room preview of the selection lives in the large preview on the left.
 */
const props = defineProps<{
  item: InquiryItemDraft
  // Prefix of every SVG mask of this thumbnail, so several doors on the page never share ids.
  idBase: string
}>()

const canvas = computed((): { width: number; height: number } => doorCanvasSize(props.item.variant))
const aspectRatio = computed((): string => `${canvas.value.width} / ${canvas.value.height}`)
const decorImageUrl = computed((): string | null => props.item.decor?.texture_image_url ?? null)
const glassImageUrl = computed((): string | null => props.item.glass?.texture_image_url ?? null)
const glassOpacity = computed((): number => glassOpacityFraction(props.item.glass))
</script>

<template>
  <!-- The door stands on a plain white surface: the frame is gone, only the white padding breathes around it. -->
  <div class="bg-white px-4 py-1">
    <div class="relative overflow-hidden" :style="{ aspectRatio }">
      <ConfiguratorDoorLayers
        v-if="item.variant"
        class="absolute inset-0 h-full w-full"
        :variant="item.variant"
        :decor-image-url="decorImageUrl"
        :glass-image-url="glassImageUrl"
        :glass-opacity="glassOpacity"
        :mask-id-base="idBase"
        :width="canvas.width"
        :height="canvas.height"
      />
      <p v-else class="absolute inset-0 flex items-center justify-center p-2 text-center text-[10px] uppercase tracking-[0.14em] text-dark/50">
        Bez náhledu
      </p>
    </div>
  </div>
</template>
