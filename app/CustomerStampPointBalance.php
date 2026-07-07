<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerStampPointBalance extends Model
{
    protected $table = 'customer_stamp_point_balances';

    protected $fillable = [
        'business_id',
        'contact_id',
        'product_id',
        'claim_product_id',
        'point_balance',
        'pending_qty',
        'total_qty',
        'claimed_qty',
        'claimed_point',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'contact_id' => 'integer',
        'product_id' => 'integer',
        'claim_product_id' => 'integer',
        'point_balance' => 'decimal:4',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function claimProduct()
    {
        return $this->belongsTo(Product::class, 'claim_product_id');
    }
}