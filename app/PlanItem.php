<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PlanItem extends Model
{
    protected $connection = 'mysql';
    protected $table = 'plan_items';

    protected $fillable = [
        'plan_id',
        'contact_id',
        'salesperson_id',
        'priority_level',
        'last_order_date',
        'last_call_date',
        'last_visit_date',
        'ai_note',
        'item_status',
        'result',
        'notes',
        'followup_date',
    ];

    protected $dates = [
        'last_order_date',
        'last_call_date',
        'last_visit_date',
        'followup_date',
        'created_at',
        'updated_at',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }
}