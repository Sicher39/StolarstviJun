<script setup lang="ts">
import ArrowLeft from "@/components/icons/ArrowLeft.vue";
import { onBeforeUnmount, onMounted, ref } from "vue";
import ButtonMain from "@/components/Buttons/ButtonMain.vue";
import { Link } from "@inertiajs/vue3";

type VerticalScrollCard = {
    id: number;
    day: string;
    month: string;
    year: string;
    startDay: string;
    endDay: string;
    startMonthShort: string;
    endMonthShort: string;
    spansMultipleMonths: boolean;
    place: string;
    address: string;
    title: string;
    participants: string[];
    externalLink: string;
    infoLink: string;
    pdfUrl: string;
    registrationAvailable?: boolean;
};

const props = withDefaults(
    defineProps<{
        cards: VerticalScrollCard[];
    }>(),
    {
        cards: () => [],
    },
);

const slider = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);
let cleanup: (() => void) | null = null;

const updateScrollButtons = (): void => {
    const element = slider.value;

    if (!element) {
        canScrollLeft.value = false;
        canScrollRight.value = false;
        return;
    }

    const maxScrollLeft = element.scrollWidth - element.clientWidth;
    const threshold = 4;

    canScrollLeft.value = element.scrollLeft > threshold;
    canScrollRight.value = element.scrollLeft < maxScrollLeft - threshold;
};

const scrollByAmount = (direction: number): void => {
    const element = slider.value;

    if (!element) {
        return;
    }

    element.scrollBy({
        left: direction * element.clientWidth * 0.8,
        behavior: "smooth",
    });
};

onMounted(() => {
    const element = slider.value;

    if (!element) {
        return;
    }

    const onScroll = (): void => {
        updateScrollButtons();
    };

    const onResize = (): void => {
        updateScrollButtons();
    };

    element.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onResize);

    updateScrollButtons();

    cleanup = () => {
        element.removeEventListener("scroll", onScroll);
        window.removeEventListener("resize", onResize);
    };
});

onBeforeUnmount(() => {
    cleanup?.();
    cleanup = null;
});
</script>

<template>
    <section v-if="props.cards.length" class="py-10 pb-32">
        <div class="relative">
            <button
                v-if="canScrollLeft"
                type="button"
                class="absolute left-4 top-1/2 z-10 hidden lg:flex h-12 w-12 -translate-y-1/2 items-center justify-center border border-white/70 bg-lightAccent/40 text-white backdrop-blur-sm transition hover:bg-dark/60"
                @click="scrollByAmount(-1)"
            >
                <ArrowLeft />
            </button>

            <button
                v-if="canScrollRight"
                type="button"
                class="absolute right-4 top-1/2 z-10 hidden lg:flex h-12 w-12 -translate-y-1/2 items-center justify-center border border-white/70 bg-lightAccent/40 text-white backdrop-blur-sm transition hover:bg-dark/60"
                @click="scrollByAmount(1)"
            >
                <ArrowLeft class="rotate-180" />
            </button>

            <div
                ref="slider"
                class="overflow-x-auto overflow-y-hidden scrollbar-hide pb-10 overscroll-x-contain"
            >
                <div
                    class="ml-5 grid w-max auto-cols-[280px] grid-flow-col grid-rows-[auto_1fr_auto_auto_auto_auto] gap-x-5 pr-4 lg:ml-[200px] lg:auto-cols-[450px] lg:gap-x-20"
                >
                    <!-- card -->
                    <article
                        v-for="card in props.cards"
                        :key="card.id"
                        class="row-span-6 grid grid-rows-subgrid bg-dark px-4 py-4 border-l border-lightAccent"
                    >
                        <!-- card date-->
                        <div
                            v-if="!card.spansMultipleMonths"
                            class="block w-fit h-fit bg-lightAccent px-4 -ml-4 mt-5"
                        >
                            <p class="text-dark text-3xl font-bold">
                                {{ card.day }}
                            </p>
                            <div class="border-b border-dark w-[30%]"></div>
                            <p class="text-dark text-xl font-bold">
                                {{ card.month }} {{ card.year }}
                            </p>
                        </div>

                        <div
                            v-else
                            class="block w-fit h-fit bg-lightAccent px-4 -ml-4 mt-5"
                        >
                            <p class="text-dark text-3xl font-bold">
                                {{ card.startDay }} {{ card.startMonthShort }} –
                                {{ card.endDay }} {{ card.endMonthShort }}
                            </p>
                            <div class="border-b border-dark w-[30%]"></div>
                            <p class="text-dark text-xl font-bold">
                                {{ card.year }}
                            </p>
                        </div>

                        <!-- card title/place -->
                        <div class="block 2xl:py-10 pl-2">
                            <h4
                                class="font-main uppercase text-2xl lg:text-2xl xl:text-4xl font-bold mt-5 text-white"
                            >
                                {{ card.title }}
                            </h4>
                        </div>

                        <div
                            class="block w-fit h-fit bg-bg2 px-4 py-1 mt-5 lg:mt-0"
                        >
                            <p
                                class="font-bold text-xl lg:text-2xl text-lightAccent"
                            >
                                {{ card.place }}
                            </p>
                        </div>
                        <div class="block w-fit h-fit bg-bg2 px-4">
                            <p>
                                <template v-if="card.address">
                                    <span
                                        class="text-white text-xl lg:text-xl"
                                        >{{ card.address }}</span
                                    >
                                </template>
                            </p>
                        </div>

                        <div class="block mt-10">
                            <template v-if="card.participants.length">
                                <p>Lektoři semináře</p>
                                <div class="block w-32 border-b border-white" />
                                <p
                                    v-for="participant in card.participants"
                                    :key="participant"
                                    class="text-lg lg:text-2xl font-bold text-white"
                                >
                                    {{ participant }}
                                </p>
                            </template>
                        </div>
                        <div
                            class="flex flex-wrap justify-between gap-3 px-2 pt-6 pb-2"
                        >
                            <a
                                v-if="
                                    card.externalLink &&
                                    card.registrationAvailable
                                "
                                :href="card.externalLink"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ButtonMain>Registrace</ButtonMain>
                            </a>

                            <a v-if="card.infoLink" :href="card.infoLink">
                                <ButtonMain>Informace k akci</ButtonMain>
                            </a>

                            <a
                                v-if="card.pdfUrl"
                                :href="card.pdfUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ButtonMain>PDF</ButtonMain>
                            </a>
                        </div>
                    </article>
                    <article
                        class="row-span-4 bg-dark px-4 py-4 border-l border-white flex items-center"
                    >
                        <div class="">
                            <div class="block w-full">
                                <h4 class="font-main text-3xl mt-5 text-white">
                                    Všechny stáže a&nbsp;akce
                                </h4>
                                <div class="block mt-20">
                                    <Link :href="route('internships')">
                                        <ButtonMain>Zobrazit vše</ButtonMain>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>
