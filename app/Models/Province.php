<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
    ];

    public function regencies(): HasMany
    {
        return $this->hasMany(Regency::class, 'province_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'province_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'province_id');
    }
}
