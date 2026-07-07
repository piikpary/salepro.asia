<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessStampRule extends Model
{
    protected $table = 'business_stamp_rules';

    protected $fillable = [
        'business_id',
        'product_id',
        'stamp_qty',
        'earn_point',
        'claim_product_id',
        'claim_qty',
        'is_active',
        'created_by',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function claimProduct()
    {
        return $this->belongsTo(Product::class, 'claim_product_id');
    }
}