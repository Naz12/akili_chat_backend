<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',         // 'android' or 'ios'
        'latest_version',   // e.g., '2.0.1'
        'force_update',     // true or false
        'update_message',   // optional message shown to the user
    ];
}