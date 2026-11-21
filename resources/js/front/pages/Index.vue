<script lang="ts" setup>
import MainLayout from '@/front/layouts/MainLayout.vue'
import FullSection from "@/front/components/Sections/FullSection.vue"
import ButtonMain from "@/front/components/Buttons/ButtonMain.vue";
import TextGridFour from "@/front/components/TextGrid/TextGridFour.vue";
import VerticalScrollLine from '@/front/components/gsap/VerticalScrollLine.vue'
import {nextTick, onBeforeUnmount, onMounted, ref} from 'vue'
import {gsap} from 'gsap'
import {ScrollTrigger} from 'gsap/ScrollTrigger'
import HeaderSection from "@/front/components/Sections/HeaderSection.vue";
import ScrollLinesRevealSection from '@/front/components/Sections/ScrollLinesRevealSection.vue'
import {Link} from "@inertiajs/vue3";

defineOptions({
    layout: MainLayout
})

let revealGsapContext: gsap.Context | null = null

onMounted(async () => {
    await nextTick()

    gsap.registerPlugin(ScrollTrigger)

    revealGsapContext = gsap.context(() => {
        gsap.set('.reveal', {y: 60, opacity: 0})
        ScrollTrigger.batch('.reveal', {
            start: 'top 80%',
            once: true,
            onEnter: (batch) =>
                    gsap.to(batch, {
                        y: 0,
                        opacity: 1,
                        stagger: 0.15,
                        duration: 1.6,
                        ease: 'power2.out'
                    })
        })
    })
})

onBeforeUnmount((): void => {
    revealGsapContext?.revert()
    revealGsapContext = null
})

const facts = ref([
    {
        sectionHeader: 'Každý výrobek navrhujeme a vyrábíme individuálně podle konkrétního prostoru, účelu a technických možností. Nepracujeme s univerzálními řešeními, ale s funkčním návrhem, který dává dlouhodobě smysl.',
        content: [
            {
                header: 'kvalitní masivní dřevo a ověřené materiály',
                note: 'Používáme materiály, se kterými máme dlouhodobé zkušenosti a jejich vlastnosti známe v praxi. Volbu dřeva i dalších komponent vždy přizpůsobujeme konkrétnímu použití a zatížení výrobku.'
            },
            {
                header: 'precizní zpracování detailů',
                note: 'Důraz klademe na přesnost, čisté spoje a kvalitní povrchovou úpravu. Právě detaily rozhodují o funkčnosti, vzhledu i životnosti celého výrobku.'
            },
            {
                header: 'osobní přístup od návrhu po montáž',
                note: 'Se zákazníkem komunikujeme přímo a po celou dobu realizace. Od prvního návrhu až po montáž řešíme zakázku osobně a s maximální pečlivostí.'
            }
        ]
    }
])

const woodFactory = ref([
    {
        sectionHeader: 'Navrhujeme a vyrábíme dřevěné prvky na míru s důrazem na funkčnost, detail a dlouhou životnost. Každý projekt řešíme individuálně podle prostoru, účelu i nároků na každodenní používání.',
        content: [
            {
                header: 'Schody, zábradlí',
                note: 'Navrhujeme konstrukčně spolehlivá řešení, která odpovídají prostoru i provoznímu zatížení. Každý detail řešíme s ohledem na bezpečnost, komfort i dlouhodobou odolnost.'
            },
            {
                header: 'Obložení interiérů',
                note: 'Vytváříme čisté a přesné realizace, které přirozeně navazují na architekturu prostoru. Důraz klademe na návaznosti, detaily a celkový vizuální dojem.'
            },
            {
                header: 'Kuchyně, skříně a další',
                note: 'Navrhujeme praktická a promyšlená řešení, která respektují každodenní provoz i individuální potřeby. Každý prvek má své místo, funkci i logiku.'
            }
        ]
    }
])


</script>

<template>
    <HeaderSection
            img="red-door"
            button="konfigurátor dveří"
            link="configurator"
    >
        <template v-slot:header>
            Každý kus dřeva <br>
            má svůj příběh, <br>
            my mu dáme tvar <br>
            podle vašich představ
        </template>
    </HeaderSection>


    <FullSection>
        <div class="block w-full section-gap-top lg:pb-10 lg:pt-32">
            <h3 class="text-accent text-fluid-4xl 2xl:text-fluid-5xl reveal">Poctivá výroba dveří<br>
                bez kompromisů kvality</h3>
            <p class="mt-10 reveal text-fluid-base uppercase">Nejsme sériová výroba. Každé dveře vznikají na zakázku –
                podle prostoru,
                <br> stylu i přání
                zákazníka.</p>
            <div class="block pt-10">
                <TextGridFour v-for="(item, i) in facts" :key="i" v-bind="item"/>
            </div>
        </div>
    </FullSection>

    <ScrollLinesRevealSection>
        Výroba dveří u&nbsp;nás stojí na poctivém řemesle, zkušenostech a&nbsp;citu pro detail. Práce se dřevem má v&nbsp;naší
        firmě
        dlouhou tradici, kterou dnes rozvíjí už druhá generace.
    </ScrollLinesRevealSection>


    <div class="block relative w-full py-32">
        <div class="absolute top-0 left-0 flex justify-end w-full px-2 md:px-0">
            <img
                    src="/img/bg/small/Doors01.webp"
                    class="w-full smW:w-[300px] md:w-[450px] lg:w-[600px] xl:w-[700px] 2xl:w-fit"
                    alt=""
            />
        </div>
        <div class="relative top-[400px] lg:top-0 left-0 lg:-top-20">
            <FullSection>
                <div class="block w-full">
                    <h3 class="text-accent text-fluid-4xl 2xl:text-fluid-5xl reveal">Zakázková výroba<br>
                        dveří na míru</h3>
                    <p class="mt-5 reveal text-fluid-base uppercase">Kvalitní řemeslo ze dřeva, které vydrží generace.</p>
                    <div class="grid grid-cols-12 w-full mt-10" data-vertical-scroll-line-container>

                        <div class="col-span-1 md:col-span-1 flex justify-start md:justify-center">
                            <VerticalScrollLine/>
                        </div>
                        <div class="col-span-11 grid grid-cols-12 xl:pt-20 smW:mt-10 md:mt-0">
                            <div class="col-span-12 lg:col-span-11 block mt-10 2xl:mt-1">
                                <p>Vyrábíme interiérové i&nbsp;vchodové dveře na míru. <br class="hidden lg:block"> Každý kus vzniká v&nbsp;naší dílně s&nbsp;důrazem
                                    na detail, <br class="hidden lg:block"> funkčnost a&nbsp;dlouhou životnost. Spojujeme poctivé<br class="hidden lg:block"> stolařské řemeslo s&nbsp;moderními
                                    technologiemi.</p>
                                <div class="block w-full max-w-[250px] group mt-12 lg:mt-20">
                                    <Link :href="route(`front.doors`)">
                                        <ButtonMain><span class="uppercase font-main">Katalog produktů</span></ButtonMain>
                                    </Link>
                                </div>
                                <div class="block mt-10 lg:-mt-20 2xl:-mt-56">
                                    <div class="relative w-full">
                                        <img src="/img/bg/small/Doors02.webp" class="invisible w-full" alt="" aria-hidden="true">
                                        <img src="/img/bg/small/Doors02.webp" class="absolute bottom-0 left-0 w-full max-w-[980px]" alt="">
                                    </div>
                                </div>
                                <div class="col-span-11 grid grid-cols-12 mt-10 2xl:mt-[150px]">

                                    <div class="col-span-12">
                                        <div class="col-span-12">
                                            <h3 class="text-accent text-fluid-4xl 2xl:text-fluid-5xl reveal">Vyberte si, co vám sedí</h3>
                                            <p>Připravili jsme pro vás konfigurátor dveří</p>

                                        </div>

                                        <div class="grid grid-cols-1 lg:grid-cols-2">
                                            <div class="mt-10">
                                                <img src="/img/bg/small/configurator-doors.webp" class="w-full" alt="">
                                            </div>
                                            <div class="block px-4">
                                                <p class="mt-10">V konfigurátoru si snadno zvolíte materiál, design, skleněnou výplň, aby vše dokonale ladilo s&nbsp;vaším
                                                    prostorem. Okamžitě tak získáte přehled o&nbsp;výsledné podobě i&nbsp;orientační ceně dveří, které vám opravdu
                                                    vyhovují.</p>
                                                <Link :href="route('front.configurator')" class="block w-full max-w-[250px] group mt-12 lg:mt-20">
                                                    <ButtonMain><span class="uppercase font-main">spustit konfigurátor</span></ButtonMain>
                                                </Link>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </FullSection>
        </div>
    </div>

    <FullSection>
        <div class="block w-full  my-12 smW:my-16 lg:my-20 lg:pb-10 mt-95 lg:-mt-3">
            <div class="block">
                <h3 class="text-accent text-fluid-4xl 2xl:text-fluid-5xl reveal">Zakázková výroba<br class="hidden lg:block">
                    ze dřeva</h3>
                <p class="mt-10 reveal text-fluid-base uppercase">
                    Schody, obložení, kuchyňské linky,
                    vestavěné skříně <br class="hidden lg:block">i další prvky přesně podle prostoru a&nbsp;přání zákazníka.
                </p>
            </div>
            <div class="block">
                <TextGridFour v-for="(item, i) in woodFactory" :key="i" v-bind="item"/>
            </div>
        </div>

    </FullSection>


</template>
