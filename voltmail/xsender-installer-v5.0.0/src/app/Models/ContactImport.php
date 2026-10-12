<?php

namespace App\Models;

use App\Enums\System\ContactImportStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactImport extends Model
{
    protected $fillable = [
        'file_id',
        'group_id',
        'meta_data',
        'status',
        'total_contacts',
        'processed_contacts',
        'total_emails',
        'processed_emails',
    ];

    protected $casts = [
        'status'        => ContactImportStatusEnum::class,
        'meta_data'        => "array",
    ];

    /**
     * Get the file that this contact import belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Get the contact group associated with this import.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ContactGroup::class, 'group_id');
    }
}