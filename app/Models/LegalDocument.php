<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * One legal page of the site (today the privacy policy) that is edited in the administration.
 *
 * The record is identified by `type` and `locale`, both unique together: the text is edited in
 * place and never multiplied. `published_at` decides what the public page shows:
 *
 * - a published record is rendered as it is stored,
 * - an unpublished record is a draft and the public page falls back to the baseline text defined
 *   in `config/legal.php`, so the site never publishes an unfinished version and never returns 404.
 *
 * Every change of a published record is snapshotted into `legal_document_revisions`, which keeps
 * what was actually published, by whom and when. The text itself uses `{{ variable }}` for values
 * read from the contact record and `[DOPLNIT: ...]` for legally material facts that the owner has
 * to confirm (see App\Services\Legal\LegalContentRenderer).
 */
class LegalDocument extends Model
{
    public const TYPE_PRIVACY_POLICY = 'privacy';

    public const TYPE_COOKIE_POLICY = 'cookie_policy';

    public const DEFAULT_LOCALE = 'cs';

    public const DEFAULT_VERSION = '1.0';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'locale',
        'title',
        'perex',
        'content',
        'version',
        'effective_from',
        'published_at',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => self::DEFAULT_LOCALE,
        'version' => self::DEFAULT_VERSION,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $document): void {
            $document->type = trim((string) $document->type);
            $document->locale = trim((string) $document->locale) ?: self::DEFAULT_LOCALE;
            $document->title = trim((string) $document->title);
            $document->version = trim((string) $document->version) ?: self::DEFAULT_VERSION;
            $document->perex = self::normalizeText($document->perex);
            $document->notes = self::normalizeText($document->notes);

            if (! array_key_exists($document->type, self::typeOptions())) {
                throw ValidationException::withMessages([
                    'type' => 'Zvolený typ dokumentu není v konfiguraci legal.php definován.',
                ]);
            }

            // The unique index is the second line of defence; this message reaches the editor.
            $duplicateExists = self::query()
                ->where('type', $document->type)
                ->where('locale', $document->locale)
                ->when($document->exists, fn (Builder $query): Builder => $query->whereKeyNot($document->getKey()))
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'type' => 'Dokument tohoto typu a jazyka už existuje. Upravte stávající záznam.',
                ]);
            }
        });

        // The snapshot is written after the record is stored, so it always holds saved values.
        static::saved(function (self $document): void {
            $document->recordRevision();
        });
    }

    /**
     * Document types offered in the administration, taken from the configuration so a new legal
     * page is added in one place.
     *
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        $options = [];

        foreach ((array) config('legal.documents', []) as $type => $definition) {
            $label = is_array($definition) ? (string) ($definition['label'] ?? $type) : (string) $type;
            $options[(string) $type] = $label;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function localeOptions(): array
    {
        $options = [];

        foreach ((array) config('legal.locales', []) as $locale => $label) {
            $options[(string) $locale] = (string) $label;
        }

        return $options ?: [self::DEFAULT_LOCALE => self::DEFAULT_LOCALE];
    }

    /**
     * The configuration definition of one document type.
     *
     * @return array<string, mixed>
     */
    public static function definition(string $type): array
    {
        $documents = (array) config('legal.documents', []);
        $definition = (array) ($documents[$type] ?? []);

        if ($definition === []) {
            throw new \InvalidArgumentException('Neznámý typ právního dokumentu: '.$type);
        }

        return $definition;
    }

    /**
     * The published record of a document, or null when the document exists only as a draft.
     */
    public static function publishedFor(string $type, ?string $locale = null): ?self
    {
        return self::query()
            ->where('type', $type)
            ->where('locale', $locale ?? self::DEFAULT_LOCALE)
            ->whereNotNull('published_at')
            ->first();
    }

    /**
     * The text the public page renders: the published record when there is one, otherwise the
     * baseline from the configuration. An existing but unpublished draft is never published.
     */
    public static function bodyFor(string $type, ?string $locale = null): self
    {
        return self::publishedFor($type, $locale) ?? self::baseline($type, $locale);
    }

    /**
     * The configured text as an unsaved model, used before the first seeding and whenever a
     * document is edited but not published yet.
     */
    public static function baseline(string $type, ?string $locale = null): self
    {
        $definition = self::definition($type);

        return new self([
            'type' => $type,
            'locale' => $locale ?? self::DEFAULT_LOCALE,
            'title' => (string) ($definition['title'] ?? $definition['label'] ?? $type),
            'perex' => $definition['perex'] ?? null,
            'content' => (string) ($definition['content'] ?? ''),
            'version' => (string) ($definition['version'] ?? self::DEFAULT_VERSION),
            'effective_from' => null,
            'published_at' => null,
        ]);
    }

    /**
     * Attribute values the seeder creates a record with.
     *
     * A definition is published by the seeder only when the configuration allows it
     * (`publish_on_seed => false` keeps the record a draft). The cookie policy uses the flag on
     * purpose: its text is an operator-reviewable template with placeholders, so the seeded row must
     * not pretend the workshop already published it. The public page renders the configured baseline
     * in both cases, exactly as it does for a draft edited in the administration.
     *
     * @return array<string, mixed>
     */
    public static function attributesFromDefinition(string $type, ?string $locale = null): array
    {
        $definition = self::definition($type);

        return [
            'type' => $type,
            'locale' => $locale ?? self::DEFAULT_LOCALE,
            'title' => (string) ($definition['title'] ?? $definition['label'] ?? $type),
            'perex' => $definition['perex'] ?? null,
            'content' => (string) ($definition['content'] ?? ''),
            'version' => (string) ($definition['version'] ?? self::DEFAULT_VERSION),
            'published_at' => ($definition['publish_on_seed'] ?? true) === false ? null : now(),
        ];
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * True for the model built from the configuration, which has no database row.
     */
    public function isBaseline(): bool
    {
        return ! $this->exists;
    }

    /**
     * @param  Builder<LegalDocument>  $query
     * @return Builder<LegalDocument>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * @return HasMany<LegalDocumentRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(LegalDocumentRevision::class);
    }

    /**
     * Store what is published now, unless the latest snapshot already describes exactly this
     * version. Drafts are never snapshotted, because they are not published.
     */
    public function recordRevision(): ?LegalDocumentRevision
    {
        if (! $this->isPublished()) {
            return null;
        }

        $latest = $this->revisions()->latest('id')->first();

        if ($latest instanceof LegalDocumentRevision && $latest->matches($this)) {
            return null;
        }

        return $this->revisions()->create([
            'version' => $this->version,
            'title' => $this->title,
            'perex' => $this->perex,
            'content' => $this->content,
            'effective_from' => $this->effective_from,
            'published_at' => $this->published_at,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'published_at' => 'datetime',
        ];
    }

    protected static function normalizeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
