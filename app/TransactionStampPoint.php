<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionStampPoint extends Model
{
    protected $table = 'transaction_stamp_points';

    protected $fillable = [
        'business_id',
        'transaction_id',
        'contact_id',
        'product_id',
        'claim_product_id',
        'balance_before',
        'earned_point',
        'claimed_point',
        'claim_qty',
        'balance_after',
        'sale_qty',
        'pending_qty_before',
        'pending_qty_after',
        'sale_qty',
        'total_qty_before',
        'total_qty_after',
        'claimed_qty_before',
        'claimed_qty_after',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function claimProduct()
    {
        return $this->belongsTo(Product::class, 'claim_product_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}