<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MobileCallPlan extends Model
{
    use SoftDeletes;

    protected $table = 'mobile_call_plans';

    protected $fillable = [
        'business_id',
        'contact_id',
        'assigned_to',
        'location_id',
        'plan_date',
        'priority',
        'status',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $dates = [
        'plan_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function saleUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function logs()
    {
        return $this->hasMany(MobileCallLog::class, 'mobile_call_plan_id');
    }
}