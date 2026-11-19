<?php

namespace App\Services\Inquiry;

use Illuminate\Http\UploadedFile;

/**
 * Checks the preview PNG that the configurator composes in the browser right before an inquiry is sent.
 *
 * The image is only illustrative: the selected model, variant, decor, glass and the prices are always
 * validated from the database, so nothing in the file can change the quote. It is still a file uploaded
 * by an anonymous visitor, therefore it is accepted only when it really is a PNG of a plausible size:
 *
 *  - the file must be a successful upload,
 *  - the bytes must start with the PNG signature and end with the IEND chunk, so a renamed SVG, JPEG or
 *    an arbitrary payload is refused before any image parser touches it,
 *  - the header must parse as a PNG,
 *  - the dimensions must stay inside the limits that the browser side generator produces.
 *
 * No image library is used on purpose (getimagesizefromstring is part of the PHP core), the file is never
 * re-encoded and it is never served as-is to an unauthenticated visitor.
 */
class DoorPreviewImageInspector
{
    /**
     * Laravel's `max` rule of the upload field mirrors this value, so both messages stay consistent.
     */
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MIN_EDGE = 64;

    /**
     * The browser generator caps the long edge at 1200 px, this leaves room for a future larger render.
     */
    public const MAX_EDGE = 2000;

    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /**
     * IEND is the last chunk of a valid PNG: length 0, type IEND and its fixed CRC.
     */
    private const PNG_TRAILER = "IEND\xAE\x42\x60\x82";

    /**
     * Returns the Czech reason why the upload is unusable, or null when it is an acceptable PNG.
     */
    public function violation(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return 'Náhled se nepodařilo nahrát, odešlete ho prosím znovu.';
        }

        $path = $file->getRealPath();
        $bytes = $path === false ? false : file_get_contents($path);

        if ($bytes === false || $bytes === '') {
            return 'Náhled se nepodařilo načíst, odešlete ho prosím znovu.';
        }

        if (strlen($bytes) > self::MAX_BYTES) {
            return 'Náhled je příliš velký, maximum je 5 MB.';
        }

        if (! str_starts_with($bytes, self::PNG_SIGNATURE) || ! str_ends_with($bytes, self::PNG_TRAILER)) {
            return 'Náhled musí být obrázek ve formátu PNG.';
        }

        $size = @getimagesizefromstring($bytes);

        if ($size === false || ($size[2] ?? null) !== IMAGETYPE_PNG) {
            return 'Náhled musí být obrázek ve formátu PNG.';
        }

        [$width, $height] = $size;

        if ($width < self::MIN_EDGE || $height < self::MIN_EDGE) {
            return 'Náhled je příliš malý, minimum je '.self::MIN_EDGE.' × '.self::MIN_EDGE.' px.';
        }

        if ($width > self::MAX_EDGE || $height > self::MAX_EDGE) {
            return 'Náhled je příliš velký, maximum je '.self::MAX_EDGE.' × '.self::MAX_EDGE.' px.';
        }

        return null;
    }
}
