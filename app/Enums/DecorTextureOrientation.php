<?php

namespace App\Enums;

/**
 * The direction the grain runs in an uploaded decor texture.
 *
 * The renderer has to know it before it can draw the leaf and the side jambs of the frame downwards and the
 * head piece across: one quarter turn of the same square tile swaps the two axes, so the source decides which
 * run is drawn straight and which one is turned.
 */
enum DecorTextureOrientation: string
{
    case Vertical = 'vertical';
    case Horizontal = 'horizontal';
}
