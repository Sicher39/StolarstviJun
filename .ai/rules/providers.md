---
paths:
  - 'app/Providers/**'
---

# Providers

## Produkční asset URL na Roští
Při nefunkčním převodu JPG/PNG na WebP v produkci nejprve ověřit ASSET_URL a vygenerovanou adresu public/js/admin/reference-image-webp.js. V tomto projektu lokální upload fungoval, produkční odmítal JPG/PNG při uložení a hotový WebP prošel; uživatel problém vyřešil přidáním ASSET_URL do serverového .env. Nepovažovat to automaticky za chybu podpory WebP nebo PHP workerů.
