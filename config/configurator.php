<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Decor textures whose grain already runs across
    |--------------------------------------------------------------------------
    |
    | The configurator draws the grain of the decor downwards on the door leaf
    | and on the side jambs of the frame, and across on the head piece. It does
    | so by turning the very same square tile by a quarter turn, so it has to
    | know which way the grain runs in the uploaded texture.
    |
    | Every source is a square-ish close-up, therefore neither the pixel size
    | nor the pixels themselves can tell the direction reliably. The direction
    | is stated here instead: a texture runs downwards unless it is listed
    | below. A listed texture keeps the run it was drawn with - its grain is
    | not turned for the leaf and the jambs, and is turned for the head piece.
    |
    | An entry names the texture by the file name of the uploaded image, by the
    | code of its decor, or by both. Both are compared as slugs, so
    | "dub-natural.jpg" and "dub-natural" are the same entry.
    |
    | No source is listed today: every uploaded texture is treated as running
    | downwards. The list stays as the only place to record a source whose
    | grain really is drawn across.
    |
    */

    'horizontal_decor_textures' => [],

];
