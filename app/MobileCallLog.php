<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MobileCallLog extends Model
{
    protected $table = 'mobile_call_logs';

    protected $fillable = [
        'business_id',
        'mobile_call_plan_id',
        'contact_id',
        'created_by',
        'local_id',
        'source',
        'phone_number',
        'call_started_at',
        'call_ended_at',
        'duration_seconds',
        'outcome',
        'note',
        'next_callback_at',
        'synced_at',
    ];

    protected $dates = [
        'call_started_at',
        'call_ended_at',
        'next_callback_at',
        'synced_at',
        'created_at',
        'updated_at',
    ];

    public function callPlan()
    {
        return $this->belongsTo(MobileCallPlan::class, 'mobile_call_plan_id');
    }

    public function customer()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}