<script setup lang="ts">
import MainLayout from "@/front/layouts/MainLayout.vue";
import SecondHeaderSection from "@/front/components/Sections/SecondHeaderSection.vue";

defineOptions({
  layout: MainLayout
})

// The page uses the same header as the other subpages. The hero image is shared with the Doors page
// until the catalog gets its own picture (`public/img/bg/headers/<img>.webp`).
// Title, description and robots directives of this page are managed by the server side SEO panel
// (see config/seo.php), so no metadata is repeated here.
</script>

<template>

    <SecondHeaderSection
        img="wood-door"
    >
        <template v-slot:header>
            Katalog
        </template>
        <template v-slot:title>
            Přehled modelů a provedení, ze kterých si můžete vybrat.
        </template>
    </SecondHeaderSection>

</template>
