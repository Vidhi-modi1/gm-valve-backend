<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'visitor_id',
        'device_name',
        'ip_address',
        'browser_name',
        'os_name',
        'is_active',
        'last_activity_at'
    ];
}