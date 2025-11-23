<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenUsageLog extends Model
{
    //

    public function admin()
{
    return $this->belongsTo(User::class, 'admin_id');
}

public function subscription()
{
    return $this->belongsTo(Subscription::class);
}

}