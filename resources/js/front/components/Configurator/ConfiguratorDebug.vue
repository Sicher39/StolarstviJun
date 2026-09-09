<script setup lang="ts">
defineProps<{
  variantSlug: string | null
  templateCode: string | null
  decorMaskUrl: string | null
  glassMaskUrl: string | null
}>()
</script>

<template>
  <div class="mb-6 border border-white/10 bg-black/20 p-3 text-[10px] uppercase tracking-[0.18em] text-white/50">
    <p>Varianta: {{ variantSlug ?? 'žádná' }}</p>
    <p>Template: {{ templateCode ?? 'žádná' }}</p>
    <p>Decor mask: {{ decorMaskUrl ?? 'není' }}</p>
    <p>Glass mask: {{ glassMaskUrl ?? 'není' }}</p>
  </div>
</template>
