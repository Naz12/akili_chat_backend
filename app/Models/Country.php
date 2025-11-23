<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',        // e.g., "ET", "US"
        'currency',    // e.g., "ETB", "USD"
        'region',      // "local" or "intl"
    ];

    /**
     * Get the users associated with this country.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the plans available to this region.
     */
    public function plans()
    {
        return Plan::where('region', $this->region)->get();
    }
}