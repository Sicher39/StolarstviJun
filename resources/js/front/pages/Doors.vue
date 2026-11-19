<script setup lang="ts">
import MainLayout from "@/front/layouts/MainLayout.vue";

import SecondHeaderSection from "@/front/components/Sections/SecondHeaderSection.vue";
import ScrollLinesRevealSection from "@/front/components/Sections/ScrollLinesRevealSection.vue";
import ReferenceGallerySlider from "@/front/components/ReffereceCardGallery/ReferenceGallerySlider.vue";
import FullSection from "@/front/components/Sections/FullSection.vue";
import VerticalScrollLine from "@/front/components/gsap/VerticalScrollLine.vue";
import TwoGridSection from "@/front/components/Sections/TwoGridSection.vue";
import { computed, ref } from "vue";
import type { ReferenceCard as ReferenceCardItem } from "@/front/components/ReffereceCardGallery/referenceCategories";

defineOptions({
    layout: MainLayout
})

// Published references assigned to the Doors page. The page body and gallery above stay untouched.
const props = withDefaults(defineProps<{
    references?: ReferenceCardItem[]
}>(), {
    references: () => []
})

const hasReferences = computed<boolean>(() => props.references.length > 0)


const sections = ref([
    {
        img: 'custom-interior-door',
        title: 'Interiérové dveře na míru vašemu prostoru',
        listTitle: 'Možnosti provedení:',
        texts: [
            'interiérové dveře vyrábíme přesně podle rozměrů, stylu a využití místnosti. Nabízíme klasická i moderní \n',
            'řešení, hladké i kazetové provedení, masiv nebo dýhu. Každé dveře navrhujeme s ohledem na funkčnost, design a dlouhou životnost.'

        ],
        lists: [
            'masivní dřevo / dýha',
            'různé povrchové úpravy',
            'bezfalcové i falcové provedení',
            'posuvné i klasické otevírání'
        ]
    },
    {
        img: 'custom-entrance-door',
        title: 'Bezpečné a reprezentativní vchodové dveře',
        listTitle: 'Důraz klademe na:',
        texts: [

            'Vchodové dveře jsou vizitkou domu. Vyrábíme masivní vchodové dveře, které spojují vysokou odolnost, \n',
            'bezpečnost a estetický vzhled. Každé řešení přizpůsobíme konkrétním podmínkám stavby.'


        ],
        lists: [
            'pevnou konstrukci',
            'tepelnou a zvukovou izolaci',
            'kvalitní kování a zámky',
            'odolné povrchové úpravy'
        ]
    },
    {
        img: 'custom-atypical-door',
        title: 'Když chcete něco výjimečného',
        listTitle: '',
        texts: [
            'Potřebujete atypické rozměry, specifický design nebo nestandardní řešení? \n' +
            ' Zakázková výroba je naše specialita. Navrhneme a vyrobíme dveře přesně podle vašich představ – bez kompromisů.\n'


        ],
        lists: [
        ]
    }
])
</script>
<template>


    <SecondHeaderSection
            img="wood-door"
    >
        <template v-slot:header>
            Dveře dělají první dojem <br>
            proto záleží <br> na každém detailu
        </template>
        <template v-slot:title>
            Nevyrábíme sériově a&nbsp;neděláme kompromisy tam, kde by se později vymstily.
        </template>
    </SecondHeaderSection>

    <FullSection>
        <div class="block mt-[300px] mb-48">
            <div class="relative w-full">
                <div class="absolute left-0 top-0 bottom-0 flex justify-center w-full">
                    <VerticalScrollLine/>
                </div>
                <div class="block space-y-48">
                    <TwoGridSection
                            v-for="(section, i) in sections"
                            :key="i"
                            v-bind="section"
                            :left=" i % 2 === 0 "
                    />
                </div>

            </div>
        </div>
    </FullSection>

    <div class="block mt-48">
        <ScrollLinesRevealSection>
            Zakázková výroba ze dřeva vyžaduje víc než jen přesné rozměry. Každý výrobek musí respektovat účel,
            zatížení, prostředí i&nbsp;návaznost na ostatní prvky v&nbsp; prostoru.
        </ScrollLinesRevealSection>
    </div>


    <FullSection v-if="hasReferences">
        <div class="block my-48">
            <h4 class="text-accent text-6xl 2xl:text-8xl reveal">Galerie <br> realizovaných zakázek,</h4>
            <h5 class="mt-5">které vydrží roky, ne sezóny.</h5>
        </div>
        <div class="block w-full">
            <ReferenceGallerySlider :references="props.references"/>
        </div>
    </FullSection>
</template>