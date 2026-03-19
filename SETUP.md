# Project Setup

Tento projekt je pripraveny jako cista zakladna pro Laravel aplikaci s adminem ve Filamentu a frontendem ve Vue + TypeScript.

## Backend Stack

- Laravel `12`
- Filament `5`
- Filament Shield `4`
- PostgreSQL pres Docker Compose

## Frontend Stack

- Vue `3`
- TypeScript
- Vite
- Tailwind CSS `4`
- `pnpm` jako package manager
- ESLint

## Co je uz nastavene

- Laravel aplikace je nainstalovana a funkcni.
- Filament panel je vytvoreny a dostupny na `/admin`.
- Shield je nainstalovany a registrovany do Filament panelu.
- Frontend bezi pres `pnpm`.
- Frontend entrypoint je v TypeScriptu.
- Vite alias `@` smeruje na `resources/js`.
- Projekt prochazi:
  - `pnpm lint`
  - `pnpm typecheck`
  - `pnpm exec vite build`

## Databaze

Laravel je nastaveny na PostgreSQL.

Hodnoty v `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=stolarstvi_jun
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

Docker Compose je pripraveny v [docker-compose.yml](/E:/b_git/2026-stolarstvi-jun/docker-compose.yml).

Spusteni databaze:

```bash
docker compose up -d
php artisan migrate
```

## Frontend Struktura

Frontend je pripraveny pod `resources/js/front`.

Pouzivana struktura:

```text
resources/js/
  bootstrap.ts
  front/
    app.ts
    App.vue
    env.d.ts
    components/
    layouts/
    pages/
    utils/
```

Doporuceni:

- `resources/js/front/components`
  - sdilene Vue komponenty
- `resources/js/front/layouts`
  - layout wrappery pro stranky
- `resources/js/front/pages`
  - page-level Vue komponenty
- `resources/js/front/utils`
  - helpery, utility funkce, formattery

## Hlavni Soubory

- Frontend entrypoint: [resources/js/front/app.ts](/E:/b_git/2026-stolarstvi-jun/resources/js/front/app.ts)
- Root Vue komponenta: [resources/js/front/App.vue](/E:/b_git/2026-stolarstvi-jun/resources/js/front/App.vue)
- TS config: [tsconfig.json](/E:/b_git/2026-stolarstvi-jun/tsconfig.json)
- Vite config: [vite.config.ts](/E:/b_git/2026-stolarstvi-jun/vite.config.ts)
- ESLint config: [eslint.config.js](/E:/b_git/2026-stolarstvi-jun/eslint.config.js)
- Filament panel provider: [app/Providers/Filament/AdminPanelProvider.php](/E:/b_git/2026-stolarstvi-jun/app/Providers/Filament/AdminPanelProvider.php)
- User model pro Filament/Shield: [app/Models/User.php](/E:/b_git/2026-stolarstvi-jun/app/Models/User.php)

## Uzitecne Prikazy

Instalace JS balicku:

```bash
pnpm install
```

Vyvojovy frontend:

```bash
pnpm dev
```

Lint:

```bash
pnpm lint
```

Type check:

```bash
pnpm typecheck
```

Build:

```bash
pnpm exec vite build
```

Laravel server:

```bash
php artisan serve
```

Migrace:

```bash
php artisan migrate
```

Vytvoreni Filament uzivatele:

```bash
php artisan make:filament-user
```

Prirazeni super admin role pro Shield:

```bash
php artisan shield:super-admin
```

## Poznamka k Frontendu

Frontend zaklad je zamerne drzeny jednoduse. Do `resources/js/front` muzes primo nahravat vlastni:

- komponenty
- layouty
- stranky
- utility

Projekt je pripraveny tak, aby nebylo potreba pouzivat starsi strukturu typu `resources/js/apps/front`.
