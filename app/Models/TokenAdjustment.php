<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenAdjustment extends Model
{
    //
    protected $fillable = [
        'subscription_id',
        'admin_id',
        'old_tokens',
        'new_tokens',
        'reason',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}