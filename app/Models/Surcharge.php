<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Surcharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'price_without_vat',
        'active',
    ];

    public function doorModels(): BelongsToMany
    {
        return $this->belongsToMany(DoorModel::class)->withTimestamps();
    }

    public function inquiries(): BelongsToMany
    {
        return $this->belongsToMany(Inquiry::class)
            ->withPivot('price_without_vat')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'price_without_vat' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
