<template>
  <main class="min-h-screen bg-[radial-gradient(circle_at_top,rgba(245,158,11,0.18),transparent_35%),linear-gradient(180deg,#0c0a09,#1c1917)]">
    <div class="mx-auto flex min-h-screen max-w-6xl flex-col justify-center gap-8 px-6 py-16">
      <p class="text-sm font-semibold uppercase tracking-[0.35em] text-amber-300">
        Laravel 12 + Vue 3 + Tailwind 4
      </p>

      <div class="grid gap-6 lg:grid-cols-[1.4fr_0.9fr]">
        <section class="space-y-6 rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl shadow-amber-950/20 backdrop-blur">
          <h1 class="max-w-3xl text-5xl font-semibold tracking-tight text-white">
            Front app je pripravena na vlastni layouty a komponenty.
          </h1>

          <p class="max-w-2xl text-lg leading-8 text-stone-300">
            Vsechno frontendove ted sedi pod resources/js/front, takze si sem muzes rovnou nahrat layout vrstvu, pages i sdilene komponenty.
          </p>
        </section>

        <aside class="rounded-3xl border border-white/10 bg-stone-900/80 p-8 shadow-2xl shadow-black/30">
          <h2 class="text-lg font-semibold text-white">
            Doporucena struktura
          </h2>

          <ol class="mt-4 space-y-3 text-sm leading-6 text-stone-300">
            <li>`front/layouts` pro shell a wrappery.</li>
            <li>`front/components` pro sdilene bloky.</li>
            <li>`front/pages` pro konkretni obrazovky.</li>
          </ol>
        </aside>
      </div>
    </div>
  </main>
</template>
