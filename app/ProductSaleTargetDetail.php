<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductSaleTargetDetail extends Model
{
    protected $guarded = ['id'];

    public function productSaleTarget()
    {
        return $this->belongsTo(\App\ProductSaleTarget::class, 'product_sale_target_id');
    }

    public function product()
    {
        return $this->belongsTo(\App\Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(\App\Variation::class);
    }
}
