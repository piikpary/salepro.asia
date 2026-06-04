<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $connection = 'mysql';
    protected $table = 'plans';

    protected $fillable = [
        'plan_type',
        'title',
        'plan_date',
        'completed_count',
        'skipped_count',
        'strategy_input',
        'created_by',
    ];

    protected $dates = [
        'plan_date',
        'created_at',
        'updated_at',
    ];

    public function items()
    {
        return $this->hasMany(PlanItem::class, 'plan_id');
    }
}