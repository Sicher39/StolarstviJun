<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Eloquent deletes in bulk straight through the query builder, which means no model event runs for
 * `SeoPage::query()->delete()`, `SeoPage::query()->forceDelete()` or `SeoPage::query()->truncate()`.
 * The guards in App\Models\SeoPage would therefore be skipped.
 *
 * This builder closes those paths for the whole application. A page may only be retired through the
 * soft delete of one model instance, which dispatches the `deleting` event the model guards.
 *
 * Raw SQL and schema level commands (`DB::table('seo_pages')->delete()`, `migrate:fresh`, database
 * tools) do not pass through the model at all, so the table itself has no delete trigger and can
 * not be claimed to protect the rows. Only the promise of the application code is enforced here.
 */
class SeoPageBuilder extends Builder
{
    public function delete(): never
    {
        throw new LogicException(
            'SEO records can not be deleted in bulk. Soft delete a single record instead.'
        );
    }

    public function forceDelete(): never
    {
        throw new LogicException(
            'SEO records are never deleted permanently. Soft delete a single record instead.'
        );
    }

    public function truncate(): never
    {
        throw new LogicException('The seo_pages table can not be truncated by the application.');
    }
}
