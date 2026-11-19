<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A realised job (reference) rendered on the public References page and, optionally, in the bottom
 * gallery slider of the Doors or Custom Manufacturing page.
 *
 * Category and placement are independent: every reference belongs to exactly one category, while the
 * placement only decides whether it is also offered in one of the two bottom sliders (or nowhere).
 */
class Reference extends Model
{
    use HasFactory;

    public const CATEGORY_DOORS = 'dvere';

    public const CATEGORY_FURNITURE = 'nabytok';

    public const CATEGORY_KITCHEN = 'kuchyne';

    public const CATEGORY_OTHER = 'ostatni';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const PLACEMENT_DOORS = 'doors';

    public const PLACEMENT_CUSTOM_PRODUCTION = 'custom_production';

    public const IMAGES_DISK = 'public';

    public const IMAGES_DIRECTORY = 'references';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'heading',
        'description',
        'category',
        'status',
        'placement',
        'images',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_DOORS => 'Dveře',
            self::CATEGORY_FURNITURE => 'Nábytek',
            self::CATEGORY_KITCHEN => 'Kuchyně',
            self::CATEGORY_OTHER => 'Ostatní',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Koncept',
            self::STATUS_PUBLISHED => 'Publikováno',
        ];
    }

    /**
     * Placements are pages, not categories - a reference may appear in the bottom slider of one page,
     * the other one, or nowhere at all.
     *
     * @return array<string, string>
     */
    public static function placements(): array
    {
        return [
            self::PLACEMENT_DOORS => 'Spodní galerie na stránce Dveře',
            self::PLACEMENT_CUSTOM_PRODUCTION => 'Spodní galerie na stránce Zakázková výroba',
        ];
    }

    protected static function booted(): void
    {
        // The file upload field keeps its state keyed by upload key, so the column is normalised to a
        // plain list of paths before it is written to the database.
        static::saving(function (self $reference): void {
            if (! $reference->isDirty('images')) {
                return;
            }

            $reference->images = $reference->imagePaths();
        });

        // Cleanup runs after a successful save, so a failed save never removes files that are still
        // referenced. `getOriginal()` still holds the previous paths while the `saved` event runs.
        static::saved(function (self $reference): void {
            if (! $reference->wasChanged('images')) {
                return;
            }

            $previous = $reference->getOriginal('images');
            $removed = array_diff(is_array($previous) ? $previous : [], $reference->imagePaths());

            $reference->deleteImageFiles($removed);
        });

        static::deleted(function (self $reference): void {
            $reference->deleteImageFiles($reference->imagePaths());
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeWithPlacement(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement);
    }

    /**
     * Public lists are always newest to oldest; the image order itself is managed in the admin.
     */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * @return list<string>
     */
    public function imagePaths(): array
    {
        $images = $this->images;

        if (! is_array($images)) {
            return [];
        }

        return array_values(array_filter(
            $images,
            fn (mixed $path): bool => is_string($path) && $path !== '',
        ));
    }

    public function coverImagePath(): ?string
    {
        return $this->imagePaths()[0] ?? null;
    }

    /**
     * Image URLs keep the order in which the admin sorted them.
     *
     * @return list<array{url: string, alt: string}>
     */
    public function imageUrls(): array
    {
        $paths = $this->imagePaths();
        $alt = filled($this->heading) ? $this->heading : 'Reference';

        return array_values(array_map(
            fn (string $path, int $index): array => [
                'url' => Storage::disk(self::IMAGES_DISK)->url($path),
                'alt' => $index === 0 ? $alt : $alt.' – fotografie '.($index + 1),
            ],
            $paths,
            array_keys($paths),
        ));
    }

    /**
     * Shape consumed by the public Vue components (ReferenceCard header/note/images contract).
     *
     * @return array{id: int, header: string, note: string, images: list<array{url: string, alt: string}>}
     */
    public function toCardArray(): array
    {
        return [
            'id' => (int) $this->getKey(),
            'header' => (string) $this->heading,
            'note' => (string) ($this->description ?? ''),
            'images' => $this->imageUrls(),
        ];
    }

    /**
     * Published references grouped by category key, every card mapped to its public shape.
     *
     * @return array<string, list<array{id: int, header: string, note: string, images: list<array{url: string, alt: string}>}>>
     */
    public static function publishedCardsByCategory(): array
    {
        $grouped = static::query()
            ->published()
            ->newestFirst()
            ->get()
            ->groupBy('category');

        $cards = [];

        foreach (array_keys(self::categories()) as $category) {
            $cards[$category] = self::mapToCards($grouped->get($category));
        }

        return $cards;
    }

    /**
     * Published references assigned to one of the bottom gallery pages, newest first.
     *
     * @return list<array{id: int, header: string, note: string, images: list<array{url: string, alt: string}>}>
     */
    public static function publishedCardsForPlacement(string $placement): array
    {
        return self::mapToCards(
            static::query()
                ->published()
                ->withPlacement($placement)
                ->newestFirst()
                ->get(),
        );
    }

    protected function casts(): array
    {
        return [
            'images' => 'array',
        ];
    }

    /**
     * @param  Collection<int, self>|null  $references
     * @return list<array{id: int, header: string, note: string, images: list<array{url: string, alt: string}>}>
     */
    protected static function mapToCards(?Collection $references): array
    {
        if ($references === null) {
            return [];
        }

        return $references
            ->map(fn (self $reference): array => $reference->toCardArray())
            ->values()
            ->all();
    }

    /**
     * Only paths inside the reference image directory are ever deleted, so a malformed value can never
     * remove files owned by another part of the application.
     *
     * @param  array<int, mixed>  $paths
     */
    protected function deleteImageFiles(array $paths): void
    {
        $directory = self::IMAGES_DIRECTORY.'/';

        $owned = array_values(array_filter(
            $paths,
            fn (mixed $path): bool => is_string($path) && str_starts_with($path, $directory),
        ));

        if ($owned === []) {
            return;
        }

        Storage::disk(self::IMAGES_DISK)->delete($owned);
    }
}
