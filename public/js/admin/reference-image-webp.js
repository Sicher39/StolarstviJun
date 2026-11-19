/**
 * Browser-side WebP preparation for Filament file upload fields.
 *
 * Filament renders its file uploads with FilePond and hands the picked bytes to Livewire, which is why
 * the conversion has to happen before FilePond receives the selection. This script intercepts the
 * `change` (file picker) and `drop` events in the capture phase for uploads that live inside a field
 * wrapper marked with `data-reference-webp-images`, replaces the selected files with resized WebP
 * versions (long edge max 2560 px, quality 0.82) and re-emits the same event with those files.
 *
 * When the browser cannot produce WebP the upload is cancelled and the admin gets a visible message,
 * so the original bytes never leave the browser. The Filament field validates the same rule again on
 * the server, so nothing that skipped this script can be stored either.
 *
 * The script is registered through `FilamentAsset` in `AppServiceProvider`, so it ships as a plain
 * file in `public/js/admin` and needs no frontend build step.
 */
(() => {
    'use strict'

    const FIELD_SELECTOR = '[data-reference-webp-images]'
    const MAX_LONG_EDGE = 2560
    const QUALITY = 0.82
    const FALLBACK_FILE_NAME = 'reference'

    // Set while a converted selection is re-emitted, so the replayed event is not intercepted again.
    let isReplaying = false

    const isImageFile = (file) => file instanceof File && file.type.startsWith('image/')

    const supportsWebp = () => {
        try {
            const canvas = document.createElement('canvas')
            canvas.width = 1
            canvas.height = 1

            return canvas.toDataURL('image/webp').startsWith('data:image/webp')
        } catch {
            return false
        }
    }

    const webpFileName = (name) => {
        const base = (name || FALLBACK_FILE_NAME).replace(/\.[^./\\]+$/, '')

        return `${base || FALLBACK_FILE_NAME}.webp`
    }

    /**
     * Decodes the image and returns it together with the size to draw and a release callback.
     */
    const loadSource = async (file) => {
        if (typeof createImageBitmap === 'function') {
            try {
                const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })

                return {
                    element: bitmap,
                    width: bitmap.width,
                    height: bitmap.height,
                    release: () => {
                        if (typeof bitmap.close === 'function') {
                            bitmap.close()
                        }
                    }
                }
            } catch {
                // Fall through to the <img> based path below.
            }
        }

        const objectUrl = URL.createObjectURL(file)
        const image = new Image()

        try {
            await new Promise((resolve, reject) => {
                image.onload = () => resolve()
                image.onerror = () => reject(new Error('Obrázek se v prohlížeči nepodařilo načíst.'))
                image.src = objectUrl
            })
        } catch (error) {
            URL.revokeObjectURL(objectUrl)

            throw error
        }

        return {
            element: image,
            width: image.naturalWidth,
            height: image.naturalHeight,
            release: () => URL.revokeObjectURL(objectUrl)
        }
    }

    const canvasToWebpBlob = (canvas) => {
        return new Promise((resolve, reject) => {
            canvas.toBlob(
                (blob) => {
                    if (!blob || blob.type !== 'image/webp') {
                        reject(new Error('Prohlížeč neumí vytvořit WebP.'))

                        return
                    }

                    resolve(blob)
                },
                'image/webp',
                QUALITY
            )
        })
    }

    const convertToWebp = async (file) => {
        const source = await loadSource(file)

        try {
            const scale = Math.min(1, MAX_LONG_EDGE / Math.max(source.width, source.height))
            const width = Math.max(1, Math.round(source.width * scale))
            const height = Math.max(1, Math.round(source.height * scale))

            const canvas = document.createElement('canvas')
            canvas.width = width
            canvas.height = height

            const context = canvas.getContext('2d')

            if (!context) {
                throw new Error('Kreslicí plátno není v tomto prohlížeči dostupné.')
            }

            context.imageSmoothingEnabled = true
            context.imageSmoothingQuality = 'high'
            context.drawImage(source.element, 0, 0, width, height)

            const blob = await canvasToWebpBlob(canvas)

            return new File([blob], webpFileName(file.name), {
                type: 'image/webp',
                lastModified: Date.now()
            })
        } finally {
            source.release()
        }
    }

    /**
     * Non-image files are passed through untouched; the upload field rejects them anyway.
     */
    const convertAll = async (files) => {
        if (!supportsWebp()) {
            throw new Error('Prohlížeč nepodporuje WebP.')
        }

        const converted = []

        for (const file of files) {
            converted.push(isImageFile(file) ? await convertToWebp(file) : file)
        }

        return converted
    }

    const buildFileList = (files) => {
        const transfer = new DataTransfer()

        files.forEach((file) => transfer.items.add(file))

        return transfer
    }

    const reportFailure = (error) => {
        console.error('[reference-image-webp]', error)

        window.alert(
            'Fotografii se nepodařilo v prohlížeči převést na WebP, proto nebyla nahrána. ' +
                'Použijte prosím aktuální prohlížeč (Chrome, Edge, Firefox nebo Safari), ' +
                'který podporuje WebP, a vyberte soubory znovu.'
        )
    }

    const replay = (element, event) => {
        isReplaying = true

        try {
            element.dispatchEvent(event)
        } finally {
            isReplaying = false
        }
    }

    const handleFileInputChange = async (event) => {
        if (isReplaying) {
            return
        }

        const input = event.target

        if (!(input instanceof HTMLInputElement) || input.type !== 'file') {
            return
        }

        if (!input.closest(FIELD_SELECTOR)) {
            return
        }

        const files = Array.from(input.files ?? [])

        if (!files.length) {
            return
        }

        // FilePond listens on this very input, so the original selection must never reach it.
        event.stopPropagation()

        try {
            input.files = buildFileList(await convertAll(files)).files
        } catch (error) {
            input.value = ''
            reportFailure(error)

            return
        }

        replay(input, new Event('change', { bubbles: true }))
    }

    const handleDrop = async (event) => {
        if (isReplaying) {
            return
        }

        const target = event.target

        if (!(target instanceof Element)) {
            return
        }

        const field = target.closest(FIELD_SELECTOR)
        const files = Array.from(event.dataTransfer?.files ?? [])

        if (!field || !files.length) {
            return
        }

        event.preventDefault()
        event.stopPropagation()

        const dropZone = field.querySelector('.filepond--root') ?? field

        try {
            replay(
                dropZone,
                new DragEvent('drop', {
                    bubbles: true,
                    cancelable: true,
                    dataTransfer: buildFileList(await convertAll(files))
                })
            )
        } catch (error) {
            reportFailure(error)
        }
    }

    /**
     * FilePond also accepts pasted images, but a ClipboardEvent cannot be re-created portably with
     * converted files. Pasted images are therefore blocked instead of being uploaded as originals.
     */
    const handlePaste = (event) => {
        if (isReplaying || !document.querySelector(FIELD_SELECTOR)) {
            return
        }

        const files = Array.from(event.clipboardData?.files ?? [])

        if (!files.some(isImageFile)) {
            return
        }

        event.preventDefault()
        event.stopPropagation()

        window.alert(
            'Vkládání obrázků ze schránky není u referencí podporováno, protože by se nenahrály jako WebP. ' +
                'Použijte prosím tlačítko pro výběr souboru nebo soubory přetáhněte myší.'
        )
    }

    document.addEventListener('change', handleFileInputChange, true)
    document.addEventListener('drop', handleDrop, true)
    document.addEventListener('paste', handlePaste, true)
})()
