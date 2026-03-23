# Laravel Front Starter

Tento soubor je napsany tak, aby z nej slo postavit zaklad projektu bez dalsich zdrojaku.

Obsahuje:

- instalacni kroky
- presne cesty souboru
- hotove zdrojaky k vlozeni

Cilovy vysledek:

- Laravel 12
- Filament 5
- Shield
- PostgreSQL pres Docker Compose
- `pnpm`
- Vue 3 + TypeScript + Inertia
- frontend pod `resources/js/front`
- hotovy zaklad:
  - `MainLayout`
  - `Navigation`
  - `Footer`
  - `FlexSection`
  - `SeoHead`
- `BasicGdpr`
- `HandleInertiaRequests`
- `Index.vue`
  - `Gdpr.vue`

Pouzivej ASCII nazvy souboru a slozek.

---

## 1. Instalace

### Laravel

```bash
composer create-project laravel/laravel:^12.0 .
```

### PHP balicky

```bash
composer require filament/filament bezhansalleh/filament-shield inertiajs/inertia-laravel -W
php artisan filament:install --panels --no-interaction
php artisan shield:install admin --no-interaction
php artisan shield:generate --all --panel=admin --no-interaction
php artisan inertia:middleware
```

### JS stack

```bash
pnpm install
pnpm add vue @vitejs/plugin-vue @inertiajs/vue3
pnpm add -D typescript typescript-eslint vue-tsc @types/node
pnpm add -D eslint @eslint/js eslint-plugin-vue vue-eslint-parser globals
```

### Docker databaze

```bash
docker compose up -d
php artisan migrate
```

Poznamka:

- pokud chces `Filament 5 + Shield`, zustan na Laravel 12
- aktualne te na Laravel 13 blokuje `filament-shield`

---

## 2. `.env`

Soubor: `.env`

```env
APP_NAME=Laravel
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=stolarstvi_jun
DB_USERNAME=postgres
DB_PASSWORD=postgres

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"
```

Stejne hodnoty dej i do `.env.example`.

---

## 3. `docker-compose.yml`

Soubor: `docker-compose.yml`

```yml
services:
  postgres:
    image: postgres:17-alpine
    container_name: stolarstvi-jun-postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: stolarstvi_jun
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: postgres
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres -d stolarstvi_jun"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  postgres_data:
```

---

## 4. Frontend konfigurace

### `package.json` skripty

Soubor: `package.json`

Pouzij aspon tyto skripty:

```json
{
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "lint": "eslint . --ext .ts,.vue",
    "typecheck": "vue-tsc --noEmit"
  }
}
```

### `vite.config.ts`

Soubor: `vite.config.ts`

```ts
import { defineConfig } from 'vite';
import path from 'node:path';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        host: '127.0.0.1',
        strictPort: true,
        hmr: {
            host: '127.0.0.1',
            port: 5173,
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/front/app.ts'],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
```

Poznamka:

- pro Vue + Vite front nedelej samostatny CSS entrypoint v `@vite(['resources/css/app.css', ...])`
- CSS importuj uvnitr `resources/js/front/app.ts`
- kdyz je CSS samostatny entrypoint, background image z `public/img` muze v devu padat na `404`, protoze se URL vyhodnoti proti Vite originu misto proti Laravel app
- overeny funkcni vzor je: v `@vite(...)` jen JS entrypoint a v nem `import '../../css/app.css';`

### `tsconfig.json`

Soubor: `tsconfig.json`

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "useDefineForClassFields": true,
    "module": "ESNext",
    "moduleResolution": "Bundler",
    "allowJs": false,
    "strict": true,
    "jsx": "preserve",
    "baseUrl": ".",
    "paths": {
      "@/*": ["resources/js/*"]
    },
    "resolveJsonModule": true,
    "isolatedModules": true,
    "esModuleInterop": true,
    "lib": ["ES2022", "DOM", "DOM.Iterable"],
    "types": ["node"]
  },
  "include": [
    "resources/js/**/*.ts",
    "resources/js/**/*.d.ts",
    "resources/js/**/*.vue",
    "vite.config.ts"
  ]
}
```

### `eslint.config.js`

Soubor: `eslint.config.js`

```js
import js from '@eslint/js';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import pluginVue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';

export default [
    {
        ignores: ['bootstrap/cache/**', 'node_modules/**', 'public/**', 'vendor/**'],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['resources/**/*.ts', 'resources/**/*.vue', 'vite.config.ts'],
        languageOptions: {
            parser: vueParser,
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
            },
            parserOptions: {
                parser: tseslint.parser,
                ecmaVersion: 'latest',
                sourceType: 'module',
            },
        },
        rules: {
            'no-undef': 'off',
            'vue/multi-word-component-names': 'off',
        },
    },
];
```

---

## 5. Blade + Inertia

### `resources/views/app.blade.php`

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @vite(['resources/js/front/app.ts'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-white text-dark antialiased">
        @inertia
    </body>
</html>
```

### `bootstrap/app.php`

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'vue' => \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

### `routes/web.php`

```php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware('vue')->name('front.')->group(function () {
    Route::inertia('/', 'Index')->name('index');
    Route::inertia('/ochrana-osobnich-udaju', 'Gdpr')->name('gdpr');
});
```

Poznamka:

- starter uz rovnou pocita s route groupou pro Vue/Inertia frontend
- uvod jde na `Index.vue`
- GDPR jde na `Gdpr.vue`

---

## 6. Inertia middleware pro menu a footer

Soubor: `app/Http/Middleware/HandleInertiaRequests.php`

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @return array<int, array{title: string, link: string, route: string}>
     */
    protected function navLinks(): array
    {
        return [
            [
                'title' => 'Uvod',
                'link' => route('front.index'),
                'route' => 'front.index',
            ],
            [
                'title' => 'GDPR',
                'link' => route('front.gdpr'),
                'route' => 'front.gdpr',
            ],
        ];
    }

    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'navLinks' => fn (): array => $this->navLinks(),
            'openingHours' => fn (): array => [],
            'socialIcon' => fn (): array => [],
            'footerContacts' => fn (): array => [],
        ];
    }
}
```

Poznamka:

- tohle je ted spravny starter pristup
- menu i footer berou data z jednoho backend zdroje
- az pozdeji muzes `openingHours`, `socialIcon` a `footerContacts` napojit na modely

---

## 7. Frontend struktura

Vytvor:

```text
resources/js/
  bootstrap.ts
  front/
    app.ts
    env.d.ts
    utils/
      czechTypography.ts
    layouts/
      MainLayout.vue
    pages/
      Gdpr.vue
      Index.vue
    components/
      Buttons/
        ButtonContent.vue
        ButtonLabel.vue
      Contacts/
        FooterPersonalContact.vue
      Footer/
        Footer.vue
        FooterMenuItem.vue
      Gdpr/
        BasicGdpr.vue
      IconComponents/
        FbIcon.vue
        IgIcon.vue
        LinkedInIcon.vue
      Icons/
        ArrowLeft.vue
      Navigation/
        Navigation.vue
        NavigationItem.vue
      OpeningHours/
        DayItem.vue
      Sections/
        FlexSection.vue
      Seo/
        SeoHead.vue
```

---

## 8. Zakladni frontend soubory

### `resources/js/bootstrap.ts`

```ts
import axios from 'axios';

declare global {
    interface Window {
        axios: typeof axios;
    }
}

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```

### `resources/js/front/env.d.ts`

```ts
/// <reference types="vite/client" />

export {};

declare module '*.vue' {
    const component: any;
    export default component;
}

declare global {
    function route(...args: unknown[]): any;
}
```

### `resources/js/front/app.ts`

```ts
import '../../css/app.css';
import '../bootstrap.ts';
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    resolve: async (name) => {
        const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue');
        const page = pages[`./pages/${name}.vue`];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        return (await page()).default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
```

---

## 9. Utils

### `resources/js/front/utils/czechTypography.ts`

```ts
export const nbspText = (value: string): string => {
    return value.replace(/\s([ksvzouaiKSVZOUAI])\s/g, ' $1\u00A0');
};
```

---

## 10. Sekce a tlacitka

### `resources/js/front/components/Sections/FlexSection.vue`

```vue
<template>
  <section class="flex w-full justify-center px-4 md:px-6">
    <div class="w-full max-w-7xl">
      <slot />
    </div>
  </section>
</template>
```

### `resources/js/front/components/Buttons/ButtonContent.vue`

```vue
<template>
  <span class="inline-flex w-full items-center justify-center rounded-full border border-primary bg-primary px-6 py-3 text-sm font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-lightBlue">
    <slot />
  </span>
</template>
```

### `resources/js/front/components/Buttons/ButtonLabel.vue`

```vue
<template>
  <span class="inline-flex items-center justify-center rounded-full border border-primary px-5 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-primary transition hover:border-darkAccent hover:text-darkAccent dark:border-darkAccent dark:text-darkAccent">
    <slot />
  </span>
</template>
```

---

## 11. Ikony

### `resources/js/front/components/Icons/ArrowLeft.vue`

```vue
<template>
  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
  </svg>
</template>
```

### `resources/js/front/components/IconComponents/FbIcon.vue`

```vue
<template>
  <span class="text-sm font-semibold">Fb</span>
</template>
```

### `resources/js/front/components/IconComponents/IgIcon.vue`

```vue
<template>
  <span class="text-sm font-semibold">Ig</span>
</template>
```

### `resources/js/front/components/IconComponents/LinkedInIcon.vue`

```vue
<template>
  <span class="text-sm font-semibold">In</span>
</template>
```

Poznamka:

- tohle jsou zamerne jednoduche placeholdery
- logo nech smerovat do `public/img/logo/logo.svg`

---

## 12. Footer podpurne komponenty

### `resources/js/front/components/Contacts/FooterPersonalContact.vue`

```vue
<script setup lang="ts">
defineProps<{
    name: string;
    role?: string;
    email?: string;
    phone?: string;
}>();
</script>

<template>
  <div class="space-y-1 py-3 text-sm text-gray-600 dark:text-gray-300">
    <p class="font-semibold text-primary dark:text-white">{{ name }}</p>
    <p v-if="role">{{ role }}</p>
    <p v-if="email">
      <a :href="`mailto:${email}`" class="hover:underline">{{ email }}</a>
    </p>
    <p v-if="phone">
      <a :href="`tel:${phone}`" class="hover:underline">{{ phone }}</a>
    </p>
  </div>
</template>
```

### `resources/js/front/components/OpeningHours/DayItem.vue`

```vue
<script setup lang="ts">
defineProps<{
    day: string;
    hours: string;
    footer?: boolean;
}>();
</script>

<template>
  <div class="flex items-center justify-between gap-4 border-b border-primary/20 py-2 text-sm text-gray-600 dark:border-darkAccent/20 dark:text-gray-300">
    <span>{{ day }}</span>
    <span class="font-medium text-primary dark:text-white">{{ hours }}</span>
  </div>
</template>
```

### `resources/js/front/components/Footer/FooterMenuItem.vue`

```vue
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    routeLink: string;
    link: string;
    title?: string;
}>();

const page = usePage();

const normalizePath = (value: string) => {
    try {
        const url = new URL(value, window.location.origin);
        return url.pathname.replace(/\/+$/, '') || '/';
    } catch {
        return value.replace(/\/+$/, '') || '/';
    }
};

const isActive = computed(() => {
    return normalizePath(page.url) === normalizePath(props.link);
});
</script>

<template>
  <Link :href="props.link" class="inline-flex items-center py-1">
    <p
      class="text-sm"
      :class="isActive ? 'text-primary dark:text-darkAccent underline decoration-primary dark:decoration-darkAccent' : 'text-gray-600 dark:text-gray-400 hover:text-primary hover:underline hover:decoration-primary dark:hover:text-darkAccent'"
    >
      {{ props.title }}
    </p>
  </Link>
</template>
```

---

## 13. Navigation

### `resources/js/front/components/Navigation/NavigationItem.vue`

```vue
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const scrolledFromTop = ref(false);
const handleScroll = () => {
    scrolledFromTop.value = window.scrollY >= 50;
};

onMounted(() => window.addEventListener('scroll', handleScroll));
onUnmounted(() => window.removeEventListener('scroll', handleScroll));

const props = defineProps<{
    link: string;
    title: string;
    routeLink?: string;
}>();

const page = usePage();

const normalizePath = (value: string) => {
    try {
        const url = new URL(value, window.location.origin);
        return url.pathname.replace(/\/+$/, '') || '/';
    } catch {
        return value.replace(/\/+$/, '') || '/';
    }
};

const isActive = computed(() => {
    return normalizePath(page.url) === normalizePath(props.link);
});
</script>

<template>
  <Link :href="props.link" class="inline-flex min-h-[44px] items-center px-5 lg:px-0">
    <div class="group relative">
      <div
        class="absolute -bottom-1 left-0 h-[1px] w-0"
        :class="isActive ? 'w-full bg-gradient-to-r from-primary via-darkAccent/80 to-darkAccent' : 'w-full group-hover:bg-gradient-to-r group-hover:from-dark/0 group-hover:via-lightAccent/80 group-hover:to-lightAccent group-hover:transition-all group-hover:duration-700'"
      />

      <div class="flex w-full items-start justify-end">
        <p
          class="font-normal text-right duration-700 md:text-center"
          :class="scrolledFromTop ? 'text-sm lg:text-sm' : 'text-sm lg:text-sm'"
        >
          <span :class="isActive ? 'text-darkAccent' : 'text-white group-hover:text-darkAccent'">
            {{ props.title }}
          </span>
        </p>
      </div>
    </div>
  </Link>
</template>
```

### `resources/js/front/components/Navigation/Navigation.vue`

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import NavigationItem from './NavigationItem.vue';

type NavLink = {
    title: string;
    link: string;
    route: string;
};

const open = ref(false);
const menuOpen = () => {
    open.value = !open.value;
};

const page = usePage();

const links = computed(() => {
    return (page.props.navLinks ?? []) as NavLink[];
});
</script>

<template>
  <header class="sticky top-0 z-50 w-full bg-primary dark:bg-dark">
    <nav class="flex w-full justify-center px-1 md:px-4 xl:px-0">
      <div class="flex w-full justify-between px-5 2xl:w-9/12 xl:w-12/12">
        <Link href="/" class="my-1 flex cursor-pointer justify-center px-2 md:px-0 md:pr-2">
          <img
            src="/img/logo/logo.svg"
            class="z-50 w-[60px] py-1 transition-all duration-700 ease-out smW:w-[80px]"
            alt="logo"
            width="150"
            height="150"
          />
        </Link>

        <div class="hidden w-full items-center justify-end space-x-4 lg:flex">
          <NavigationItem
            v-for="(link, index) in links"
            :key="index"
            :link="link.link"
            :title="link.title"
            :route-link="link.route"
          />
        </div>

        <div class="flex items-center lg:hidden">
          <button
            type="button"
            class="text-white"
            aria-label="Toggle menu"
            :aria-expanded="open"
            @click="menuOpen"
          >
            Menu
          </button>
        </div>
      </div>
    </nav>

    <div
      v-if="open"
      class="flex min-h-[220px] justify-end bg-dark px-4 lg:hidden"
    >
      <div class="block pt-5">
        <div class="grid grid-cols-1 gap-4">
          <NavigationItem
            v-for="(link, index) in links"
            :key="index"
            :link="link.link"
            :route-link="link.route"
            :title="link.title"
            @click="menuOpen"
          />
        </div>
      </div>
    </div>
  </header>
</template>
```

Poznamka:

- tenhle starter zamerne nepouziva Ziggy
- aktivni stav se resi pres `page.url`
- `navLinks` jdou z `HandleInertiaRequests`

---

## 14. Footer

### `resources/js/front/components/Footer/Footer.vue`

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import FlexSection from '@/front/components/Sections/FlexSection.vue';
import DayItem from '@/front/components/OpeningHours/DayItem.vue';
import FooterMenuItem from '@/front/components/Footer/FooterMenuItem.vue';
import FooterPersonalContact from '@/front/components/Contacts/FooterPersonalContact.vue';

type NavLink = {
    title: string;
    link: string;
    route: string;
};

interface Props {
    build?: number;
    company?: string;
}

const props = withDefaults(defineProps<Props>(), {
    build: 2026,
    company: 'Firma s.r.o.',
});

const { build, company } = props;
const year = computed(() => new Date().getFullYear());

const page = usePage();

const openingHours = computed(() => {
    return page.props.openingHours as any[];
});

const links = computed(() => {
    return (page.props.navLinks ?? []) as NavLink[];
});

const footerContacts = computed(() => {
    return page.props.footerContacts as any[];
});
</script>

<template>
  <div class="block w-full border-t border-primary bg-gray dark:border-darkAccent dark:bg-dark">
    <div class="block w-full pb-10 pt-10">
      <FlexSection>
        <div class="relative z-10 w-full">
          <div class="grid grid-cols-1 gap-10 pb-10 smW:grid-cols-2 lg:grid-cols-3 lg:gap-20 md:pb-0">
            <div class="order-3 block lg:order-1">
              <div v-if="Array.isArray(footerContacts) && footerContacts.length" class="block">
                <p class="text-xl font-thin leading-tight text-primary dark:text-white">
                  Rychly kontakt:
                </p>
                <div class="w-20 border-b border-primary dark:text-white" />
                <FooterPersonalContact
                  v-for="(person, index) in footerContacts"
                  :key="index"
                  v-bind="person"
                />
              </div>

              <div class="hidden lg:block mt-5">
                <p class="text-center font-main text-sm font-normal text-primary dark:text-gray-300 lg:text-left">
                  <span v-if="build === year">&copy; {{ year }} {{ company }}</span>
                  <span v-else>&copy; {{ build }}-{{ year }} {{ company }}</span>
                </p>
                <p class="mt-2 pb-5 text-center font-main text-[16px] font-normal text-primary dark:text-white lg:text-left">
                  <a href="/ochrana-osobnich-udaju">
                    <span class="text-[14px] text-primary underline-offset-2 hover:underline dark:text-white">
                      <br>podminky ochrany osobnich udaju
                    </span>
                  </a>
                </p>
              </div>
            </div>

            <div class="order-3 block h-full lg:order-2">
              <p class="text-xl font-thin leading-tight text-primary dark:text-gray-200">Menu:</p>
              <div class="w-20 border-b text-primary" />
              <div class="mt-5 grid w-full grid-cols-1">
                <FooterMenuItem
                  v-for="(link, index) in links"
                  :key="index"
                  :link="link.link"
                  :title="link.title"
                  :route-link="link.route"
                />
              </div>
            </div>

            <div class="order-1 block h-full lg:order-3">
              <div v-if="Array.isArray(openingHours) && openingHours.length" class="block w-full">
                <p class="text-xl font-thin leading-tight text-primary dark:text-gray-200">
                  Provozni doba:
                </p>
                <div class="w-20 border-b text-primary" />
                <div class="block pt-5">
                  <DayItem
                    v-for="(item, i) in openingHours"
                    :key="i"
                    v-bind="item"
                    footer
                  />
                </div>
              </div>
            </div>

            <div class="order-4 block lg:hidden">
              <p class="text-left font-main text-sm font-normal text-primary dark:text-white lg:text-lg xl:text-xl">
                <span v-if="build === year">&copy; {{ year }} {{ company }}</span>
                <span v-else>&copy; {{ build }}-{{ year }} {{ company }}</span>
              </p>
              <p class="mt-2 pb-5 text-left font-main text-[16px] font-normal text-primary dark:text-white">
                <a href="/ochrana-osobnich-udaju">
                  <span class="text-[14px] underline-offset-2 hover:underline">
                    <br>podminky ochrany osobnich udaju
                  </span>
                </a>
              </p>
            </div>
          </div>
        </div>
      </FlexSection>
    </div>
  </div>
</template>
```

---

## 15. SEO

### `resources/js/front/components/Seo/SeoHead.vue`

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { nbspText } from '@/front/utils/czechTypography';

export interface SeoProps {
    title: string;
    description?: string;
    keywords?: string[] | string;
    canonical?: string;
    ogImage?: string;
    ogSiteName?: string;
    structuredData?: Record<string, unknown> | null;
}

const props = withDefaults(defineProps<SeoProps>(), {
    description: '',
    keywords: () => [],
    canonical: '',
    ogImage: '',
    ogSiteName: '',
    structuredData: null,
});

const title = computed(() => nbspText(props.title));
const description = computed(() => props.description ? nbspText(props.description) : '');
const keywords = computed(() => Array.isArray(props.keywords) ? props.keywords.join(', ') : props.keywords);
const jsonLd = computed(() => props.structuredData ? JSON.stringify(props.structuredData) : null);
</script>

<template>
  <Head>
    <title>{{ title }}</title>
    <meta v-if="description" name="description" :content="description" />
    <meta v-if="keywords" name="keywords" :content="keywords" />
    <link v-if="canonical" rel="canonical" :href="canonical" />
    <meta v-if="ogSiteName" property="og:site_name" :content="ogSiteName" />
    <meta v-if="ogImage" property="og:image" :content="ogImage" />
    <component
      :is="'script'"
      v-if="jsonLd"
      type="application/ld+json"
      :innerHTML="jsonLd"
    />
  </Head>
</template>
```

---

## 16. GDPR

### `resources/js/front/components/Gdpr/BasicGdpr.vue`

```vue
<script setup lang="ts">
interface Props {
    administrator?: string;
    companyId?: string;
    address?: string;
    email?: string;
    tel?: string;
    date?: string;
}

withDefaults(defineProps<Props>(), {
    administrator: 'Firma s.r.o.',
    companyId: '00000000',
    address: 'Ulice 1, Mesto',
    email: 'info@example.test',
    tel: '+420 000 000 000',
    date: '1. 1. 2026',
});
</script>

<template>
  <div class="max-w-none">
    <h1>Ochrana osobnich udaju</h1>
    <p>
      Spravcem osobnich udaju je <strong>{{ administrator }}</strong>,
      IC <strong>{{ companyId }}</strong>, se sidlem <strong>{{ address }}</strong>.
    </p>
    <p>
      Kontakt:
      <a :href="`mailto:${email}`">{{ email }}</a>,
      <a :href="`tel:${tel}`">{{ tel }}</a>
    </p>
    <p>
      Tyto podminky nabyvaji ucinnosti dnem {{ date }}.
    </p>
  </div>
</template>
```

---

## 17. Layout

### `resources/js/front/layouts/MainLayout.vue`

```vue
<script setup lang="ts">
import Navigation from '@/front/components/Navigation/Navigation.vue';
import Footer from '@/front/components/Footer/Footer.vue';
</script>

<template>
  <div class="min-h-screen bg-white text-dark dark:bg-dark dark:text-white">
    <Navigation />

    <main>
      <slot />
    </main>

    <Footer />
  </div>
</template>
```

---

## 18. Index stranka

### `resources/js/front/pages/Index.vue`

```vue
<script setup lang="ts">
import MainLayout from '@/front/layouts/MainLayout.vue';
import FlexSection from '@/front/components/Sections/FlexSection.vue';
import SeoHead, { type SeoProps } from '@/front/components/Seo/SeoHead.vue';
import BasicGdpr from '@/front/components/Gdpr/BasicGdpr.vue';

defineOptions({
    layout: MainLayout,
});

const seoHome: SeoProps = {
    title: 'Uvodni strana',
    description: 'Zakladni front starter s menu, footerem, SEO a GDPR komponentou.',
    canonical: 'http://localhost:8000',
    ogSiteName: 'Starter',
};
</script>

<template>
  <SeoHead v-bind="seoHome" />

  <FlexSection>
    <div class="py-20">
      <h1 class="text-4xl font-semibold">Frontend starter</h1>
      <p class="mt-6 max-w-3xl text-lg leading-8">
        Tohle je zakladni stranka, ktera se rovnou nacita pres Inertia a uz obsahuje menu, footer, SEO i GDPR komponentu.
      </p>
    </div>
  </FlexSection>

  <FlexSection>
    <div class="pb-20">
      <BasicGdpr />
    </div>
  </FlexSection>
</template>
```

---

## 19. CSS

## 19. GDPR stranka

### `resources/js/front/pages/Gdpr.vue`

```vue
<script setup lang="ts">
import BasicGdpr from '@/front/components/Gdpr/BasicGdpr.vue';
import MainLayout from '@/front/layouts/MainLayout.vue';
import SeoHead, { type SeoProps } from '@/front/components/Seo/SeoHead.vue';

defineOptions({
    layout: MainLayout,
});

const seoGdpr: SeoProps = {
    title: 'Podminky ochrany osobnich udaju (GDPR) | MIRA elektromontazni Brno',
    description:
        'Informace o tom, jak MIRA, elektromontazni spolecnost s r.o., zpracovava osobni udaje zakazniku a navstevniku webu v souladu s narizenim GDPR.',
    keywords: ['GDPR MIRA', 'ochrana osobnich udaju', 'zasady zpracovani osobnich udaju'],
    canonical: 'https://mirabrno.cz/gdpr',
    ogSiteName: 'MIRA elektromontazni Brno',
};
</script>

<template>
  <SeoHead v-bind="seoGdpr" />

  <div class="flex w-full justify-center bg-dark">
    <h1 class="py-44 text-center text-light">
      Podminky ochrany <br />
      osobnich udaju
    </h1>
  </div>

  <BasicGdpr
    administrator="MIRA, elektromontazni spolecnost s r.o. | Lubomir Oskrda"
    company-id="27745015"
    address="Cejl 58/72, Brno 602 00"
    email="mira@mirabrno.cz"
    tel="+420 777 758 830"
    date="1.9.2024"
  />
</template>
```

---

## 20. CSS

Soubor: `resources/css/app.css`

```css
@import "tailwindcss";
@custom-variant dark (&:where(.dark, .dark *));

@layer base {
    h1 { @apply font-head text-primary dark:text-white; }
    h2 { @apply font-main font-black text-primary dark:text-white; }
    h3 { @apply font-head font-normal text-left text-xl md:text-2xl text-primary dark:text-white; }
    h4 { @apply font-head font-black text-left text-8xl uppercase text-primary dark:text-darkAccent; }
    h5 { @apply font-main text-lg text-primary dark:text-white; }
    p  { @apply font-main text-sm text-gray-600 dark:text-gray-300; }
    li { @apply font-normal text-xl; }
}

@theme {
    --color-dark: #0c1217;
    --color-primary: #17518c;
    --color-lightBlue: #66a1db;
    --color-darkAccent: #f1e876;
    --color-lightAccent: #e74c3c;
    --color-gray: #f4f4f4;

    --font-head: proxima-nova, sans-serif;
    --font-main: proxima-nova, sans-serif;

    --breakpoint-sm: 375px;
    --breakpoint-smW: 700px;
    --breakpoint-md: 768px;
    --breakpoint-lg: 1024px;
    --breakpoint-xl: 1280px;
    --breakpoint-2xl: 1536px;
    --breakpoint-3xl: 1800px;
    --breakpoint-qhd: 2560px;
    --breakpoint-4k: 3840px;
}
```

Poznamka:

- tohle je aktualni nastaveni z projektu
- pokud si preneses vlastni komponenty, budou pocitat s temito tokeny

---

## 20. Filament uzivatel a User model
## 21. Filament uzivatel a User model

User model musi mit:

- `HasRoles`
- `FilamentUser`
- `canAccessPanel()`

Minimalni podoba:

```php
<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
```

---

## 22. Kontrola

Na konci ma projit:

```bash
pnpm lint
pnpm typecheck
pnpm exec vite build
php artisan migrate
php artisan route:list
docker compose config
```

---

## 23. Poznamky

Tenhle starter je schvalne postaveny tak, aby:

- logo slo jen nahrat do `public/img/logo/logo.svg`
- menu i footer jely z jednoho backend zdroje v `HandleInertiaRequests`
- `Index.vue` sla hned nacist pres Inertia
- `Gdpr.vue` sla hned nacist pres vlastni route
- aktivni stav v menu i footeru fungoval bez Ziggy a podtrzeni se ridi podle aktualni URL
- GDPR a SEO slo hned pouzit
- dalsi komponenty slo pozdeji jen pridavat do `resources/js/front/components`

Tedy ano:

z tohohle jednoho MD souboru jde poskladat zaklad projektu.
