<script setup lang="ts">
import MagnifyingGlass from '@/front/components/IconComponents/MagnifyingGlass.vue'

const props = withDefaults(
    defineProps<{
        image?: string
        header?: string
        note?: string
        ariaLabel?: string
    }>(),
    {
        image: '',
        header: '',
        note: '',
        ariaLabel: ''
    }
)
</script>

<template>
    <button
        type="button"
        class="group relative flex cursor-pointer items-center justify-center overflow-hidden border border-white bg-cover bg-center bg-no-repeat text-left"
        :style="{ backgroundImage: `url(${props.image})` }"
        :aria-label="props.ariaLabel"
    >
        <span class="absolute inset-0 bg-dark/60 transition duration-500 group-hover:bg-dark/0" />
        <span class="relative z-10 text-white group-hover:text-white/20">
            <MagnifyingGlass />
        </span>
        <span class="absolute bottom-0 w-full group-hover:bg-dark/60">
            <span class="block w-full px-4 pb-2">
                <span class="font-main text-sm uppercase text-white">{{ props.header }}</span>
                <br />
                <span class="font-main text-sm text-white">{{ props.note }}</span>
            </span>
        </span>
    </button>
</template>
