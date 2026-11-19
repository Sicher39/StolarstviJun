<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of one published version of a legal document. Revisions are written by
 * LegalDocument::recordRevision() and are never edited: they are the audit trail of what the site
 * published, when and by whom.
 */
class LegalDocumentRevision extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'version',
        'title',
        'perex',
        'content',
        'effective_from',
        'published_at',
        'created_by',
    ];

    /**
     * @return BelongsTo<LegalDocument, $this>
     */
    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }

    /**
     * True when the snapshot already contains exactly the values of the given document, so a
     * second identical revision would only duplicate the history.
     */
    public function matches(LegalDocument $document): bool
    {
        return $this->version === $document->version
            && $this->title === $document->title
            && $this->perex === $document->perex
            && $this->content === $document->content
            && $this->effective_from?->toDateString() === $document->effective_from?->toDateString();
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
}
