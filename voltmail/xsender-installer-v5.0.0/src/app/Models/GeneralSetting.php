<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated Use site_settings() helper or Setting model instead.
 * This model is kept only for backward compatibility with constants.
 * DO NOT use GeneralSetting::first() or any database queries on this model.
 */
class GeneralSetting extends Model
{
    use HasFactory;

    // Constants for attribute types (used in views)
    const DATE    = 1;
    const BOOLEAN = 2;
    const NUMBER  = 3;
    const TEXT    = 4;

    /**
     * The table associated with the model.
     * Points to 'settings' table for backward compatibility with legacy code.
     *
     * @deprecated Use site_settings() helper instead of direct model queries.
     * @var string
     */
    protected $table = 'settings';

    protected $casts = [
    	'frontend_section'   => 'object',
    	'social_login'       => 'json',
    	'recaptcha'          => 'json',
    	'webhook'          => 'json',
    ];
}
