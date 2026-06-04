<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductSaleTarget extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function details()
    {
        return $this->hasMany(\App\ProductSaleTargetDetail::class, 'product_sale_target_id');
    }
}
