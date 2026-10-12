<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GoogleAdsLeadSubscription extends Model
{
    protected $fillable = [
        'uid', 'user_id', 'name', 'customer_id', 'form_id', 'shared_key',
        'contact_group_id', 'workflow_id', 'is_active',
        'leads_imported', 'last_lead_at', 'field_mapping',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'field_mapping'  => 'array',
        'leads_imported' => 'integer',
        'last_lead_at'   => 'datetime',
    ];

    protected $hidden = ['shared_key'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uid)) $model->uid = (string) Str::uuid();
        });
    }

    public function setSharedKeyAttribute($value): void
    {
        $this->attributes['shared_key'] = $value === null ? null : encrypt($value);
    }

    public function getSharedKeyAttribute($value): ?string
    {
        if ($value === null) return null;
        try { return decrypt($value); } catch (\Throwable $e) { return null; }
    }
}
