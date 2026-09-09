# Glass assets with preview images

Každé sklo má vlastní složku:

```txt
cire-sklo/
  preview-with-plant.webp
  preview-with-plant.png
  glass.png
  glass.webp
  meta.json
```

- `preview-with-plant` = čtvercový náhled s kytkou, aby bylo vidět zkreslení/průhlednost.
- `glass.png` / `glass.webp` = samotné sklo bez kytky, pro renderer do dveří přes `glass-mask`.
