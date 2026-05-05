<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    public $table = "orders";
    protected $fillable = [
        'order_no',
        'assembly_no',
        'soa_no',
        'soa_sr_no',
        'assembly_date',
        'unique_code',
        'splitted_code',
        'party_name',
        'customer_po_no',
        'code_no',
        'product',
        'qty',
        'qty_executed',
        'qty_pending',
        'finished_valve',
        'gm_logo',
        'name_plate',
        'special_notes',
        'product_spc1',
        'product_spc2',
        'product_spc3',
        'inspection',
        'painting',
        'remarks',
        'status',
        'created_by',
        'split_id',
        'po_qty',
        'current_stage_id',
        'deliveryDate',
      	'deliveryDT'
    ];
    
    public function splits()
    {
        return $this->hasMany(SplitOrder::class, 'order_id');
    }


}
