<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Inquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'door_model_id',
        'door_variant_id',
        'decor_id',
        'glass_type_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_message',
        'price_without_vat',
        'price_with_vat',
        'configuration',
        'crm_payload',
        'status',
    ];

    public function doorModel(): BelongsTo
    {
        return $this->belongsTo(DoorModel::class);
    }

    public function doorVariant(): BelongsTo
    {
        return $this->belongsTo(DoorVariant::class);
    }

    public function decor(): BelongsTo
    {
        return $this->belongsTo(Decor::class);
    }

    public function glassType(): BelongsTo
    {
        return $this->belongsTo(GlassType::class);
    }

    public function surcharges(): BelongsToMany
    {
        return $this->belongsToMany(Surcharge::class)
            ->withPivot('price_without_vat')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'price_without_vat' => 'decimal:2',
            'price_with_vat' => 'decimal:2',
            'configuration' => 'array',
            'crm_payload' => 'array',
        ];
    }
}
