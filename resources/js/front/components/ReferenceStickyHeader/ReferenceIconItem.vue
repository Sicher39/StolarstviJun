<script setup lang="ts">


const props = withDefaults(defineProps<{
    icon?:string
    text?:string
}>(),
        {
            icon:'',
            text:''
        })
</script>
<template>
    <div class="block w-full bg-dark px-2 shadow-2xl py-4 revealHeader">
        <div class="flex w-full justify-center px-2">
            <img :src="`/img/icons/${props.icon}.svg`" class="w-7/12" alt="ikona">
        </div>
        <div class="flex justify-center">
            <div class="border-accent border-b w-32 my-2"/>
        </div>
        <h3 class="text-center text-white text-xl font-main">{{ props.text }}</h3>
    </div>
</template>