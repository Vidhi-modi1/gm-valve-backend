<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SplitOrder extends Model
{
    use HasFactory;

    public $table = 'order_splits';
    
    protected $fillable = [
        'order_id',
        'from_stage_id',
        'to_stage_id',
        'qty',
        'assigned_qty',
        'remaining_qty',
        'action_by',
        'remarks',
        'currentStage',
        'isComplete',
        'status',
        'split_code',
        'ocl_no',
        'is_packaging'
       
    ];
}

