<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regency extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'province_id',
        'name',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class, 'regency_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'regency_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'regency_id');
    }
}
