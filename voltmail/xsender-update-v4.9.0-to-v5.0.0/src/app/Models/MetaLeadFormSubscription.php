<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MetaLeadFormSubscription extends Model
{
    protected $fillable = [
        'uid',
        'user_id',
        'name',
        'page_id',
        'page_name',
        'page_access_token',
        'form_id',
        'form_name',
        'contact_group_id',
        'workflow_id',
        'is_active',
        'field_mapping',
        'meta_data',
        'leads_imported',
        'last_lead_at',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'field_mapping'  => 'array',
        'meta_data'      => 'array',
        'leads_imported' => 'integer',
        'last_lead_at'   => 'datetime',
    ];

    protected $hidden = ['page_access_token'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = (string) Str::uuid();
            }
        });
    }

    /**
     * Encrypt-at-rest for page_access_token.
     */
    public function setPageAccessTokenAttribute($value): void
    {
        $this->attributes['page_access_token'] = $value === null ? null : encrypt($value);
    }

    public function getPageAccessTokenAttribute($value): ?string
    {
        if ($value === null) return null;
        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
