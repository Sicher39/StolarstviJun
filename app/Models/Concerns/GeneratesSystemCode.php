<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait GeneratesSystemCode
{
    public static function bootGeneratesSystemCode(): void
    {
        static::creating(function (Model $model): void {
            if (filled($model->getAttribute('code'))) {
                return;
            }

            $model->setAttribute('code', sprintf('%s-%s', $model->systemCodePrefix(), Str::upper((string) Str::ulid())));
        });
    }

    abstract protected function systemCodePrefix(): string;
}
