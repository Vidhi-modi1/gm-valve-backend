<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Uploads extends Model
{
    use HasFactory;
    protected $fillable = [
        'file_name',
        'uploaded_by',
        'total_rows',
        'success_rows',
        'failed_rows',
    ];

}

