<?php

namespace App\Managers;

use App\Enums\StatusEnum;
use App\Enums\System\ContactImportStatusEnum;
use App\Exceptions\ApplicationException;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class ContactManager
{

    /**
     * countEmailsForGroup
     *
     * @param mixed $groupId
     * 
     * @return int
     */
    public function countEmailsForGroup($groupId): int
    {
        return Contact::where('group_id', $groupId)
            ->whereNotNull('email_contact')
            ->count();
    }

    /**
     * getPendingImportLog
     *
     * @param int|string|null $id
     * 
     * @return ContactImport|null
     */
    public function getPendingImportLog(int|string|null $id): ContactImport|null {

        return ContactImport::where("status", ContactImportStatusEnum::PENDING)
                                    ->where("id", $id)
                                    ->with('file')
                                    ->first();
    }
    /**
     * getGroupsByIds
     *
     * @param array $groupIds
     * @param User|null $user
     * 
     * @return Collection
     */
    public function getGroupsByIds(array $groupIds, ?User $user = null): Collection
    {
        return ContactGroup::whereIn('id', $groupIds)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user?->id)
                    ->orWhereNull('user_id');
            })
            ->with(['contacts' => function ($query) {
                $query->select('id', 'group_id', 'first_name', 'sms_contact', 'status')
                    ->where('status', 'active');
            }])
            ->get();
    }
 
    /**
     * createGroup
     *
     * @param array $data
     * 
     * @return ContactGroup
     */
    public function createGroup(array $data, ?User $user = null): ContactGroup
    {
        $conditions = [
            'user_id' => $user?->id,
            'name' => Arr::get($data, 'name'),
        ];

        // Define the data to update or create
        $groupData = array_merge($data, [
            'user_id' => $user?->id,
            'name' => Arr::get($data, 'name'),
        ]);

        // Update or create the contact group
        return ContactGroup::updateOrCreate($conditions, $groupData);
    }
 
    /**
     * insertContacts
     *
     * Inserts contacts with smart duplicate detection.
     * Duplicates are detected by matching ANY contact field (email, sms, whatsapp)
     * within the same group, rather than requiring all fields to match.
     *
     * @param array $contacts
     * @param User|null $user
     *
     * @return bool
     */
    public function insertContacts(array $contacts, ?User $user = null): bool
    {
        // First: Filter out contacts without any contact information
        $uniqueContacts = collect($contacts)->filter(function ($contact) {
            $email    = Arr::get($contact, 'email_contact');
            $sms      = Arr::get($contact, 'sms_contact');
            $whatsapp = Arr::get($contact, 'whatsapp_contact');

            return $email || $sms || $whatsapp;
        });

        if (site_settings('filter_duplicate_contact') == StatusEnum::TRUE->status()) {

            $uniqueContacts = $uniqueContacts
                // Remove duplicates within the batch by contact fields
                ->unique(function ($contact) {
                    $keys = [];
                    if (Arr::get($contact, 'email_contact')) $keys[] = 'email:' . Arr::get($contact, 'email_contact');
                    if (Arr::get($contact, 'sms_contact')) $keys[] = 'sms:' . Arr::get($contact, 'sms_contact');
                    if (Arr::get($contact, 'whatsapp_contact')) $keys[] = 'wa:' . Arr::get($contact, 'whatsapp_contact');
                    return implode('|', $keys) ?: Arr::get($contact, 'uid', uniqid());
                })
                // Filter out contacts that already exist in database
                ->filter(function ($contact) use ($user) {
                    $groupId  = Arr::get($contact, 'group_id');
                    $email    = Arr::get($contact, 'email_contact');
                    $sms      = Arr::get($contact, 'sms_contact');
                    $whatsapp = Arr::get($contact, 'whatsapp_contact');

                    // Check if contact with same phone/email/whatsapp already exists in this group
                    $existsQuery = Contact::where('group_id', $groupId)
                        ->when($user, fn(Builder $q): Builder => $q->where('user_id', $user->id))
                        ->where(function ($q) use ($email, $sms, $whatsapp) {
                            // Match if ANY contact field matches
                            if ($email) $q->orWhere('email_contact', $email);
                            if ($sms) $q->orWhere('sms_contact', $sms);
                            if ($whatsapp) $q->orWhere('whatsapp_contact', $whatsapp);
                        });

                    return !$existsQuery->exists();
                });
        }

        if ($uniqueContacts->isEmpty()) {
            return false;
        }

        // Use insert instead of upsert for better performance and to avoid conflicts
        return Contact::insert($uniqueContacts->values()->all());
    }

    /**
     * Validate the contact group.
     *
     * @param array $data
     * @param User|null $user
     * @return ContactGroup
     * @throws ApplicationException
     */
    public function validateContactGroup(array $data, ?User $user): ContactGroup
    {
        $contactGroup = ContactGroup::when($user, fn(Builder $q): Builder =>
            $q->where("user_id", $user->id), fn(Builder $q): Builder =>
            $q->admin())
            ->where("id", Arr::get($data, "group_id"))
            ->first();

        if (!$contactGroup) {
            throw new ApplicationException("Group is inactive or invalid", Response::HTTP_NOT_FOUND);
        }

        return $contactGroup;
    }

    /**
     * updateOrInsert
     *
     * @param mixed $data
     * 
     * @return void
     */
    public function updateOrCreate($data): void {
        
        $uid = Arr::get($data, "uid");
        $this->checkDuplicateContact($data, auth()->user());
        Contact::updateOrCreate(["uid" => $uid], $data);
    }

    /**
     * checkDuplicateContact
     *
     * @param array $data
     * @param User|null $user
     * 
     * @return void
     */
    private function checkDuplicateContact(array $data, ?User $user = null): void
    {
        if (site_settings('filter_duplicate_contact') == StatusEnum::TRUE->status()) {
            $firstName  = Arr::get($data, 'first_name');
            $lastName   = Arr::get($data, 'last_name');
            $email      = Arr::get($data, 'email_contact');
            $sms        = Arr::get($data, 'sms_contact');
            $whatsapp   = Arr::get($data, 'whatsapp_contact');

            $exists = Contact::when(Arr::get($data,"uid"),
                                    fn(Builder $q): Builder => $q->where("uid", "!=", Arr::get($data, "uid")))
                                ->when($user, 
                                    fn(Builder $q): Builder => $q->where('user_id', $user?->id))
                                ->where('group_id', Arr::get($data, 'group_id'))
                                ->where('first_name', $firstName)
                                ->where('last_name', $lastName)
                                ->where('email_contact', $email)
                                ->where('sms_contact', $sms)
                                ->where('whatsapp_contact', $whatsapp)
                                ->exists();

            if ($exists) {
                throw new ApplicationException("Duplicate contact detected", Response::HTTP_NOT_FOUND);
            }
        }
    }

    /**
     * updateGroupMetaData
     *
     * @param mixed $data
     * 
     * @return void
     */
    public function updateGroupMetaData($data): void
    {
        if (!Arr::exists($data, "meta_data")) return; 
        
        $meta_data = collect($data["meta_data"])
                        ->map(function ($attribute_values) {
                            return collect($attribute_values)
                                ->mapWithKeys(function ($attribute_value, $attribute_key) {
                                    if ($attribute_key === "value") {
                                        return ["status" => true];
                                    }
                                    return [$attribute_key => $attribute_value];
                                })
                                ->except(["value"])
                                ->toArray();
                        })
                        ->toArray();

        $group              = ContactGroup::find($data["group_id"]);
        $currentAttributes  = json_decode($group->meta_data, true);
        $mergedAttributes   = $currentAttributes ? array_merge($currentAttributes, $meta_data) : $meta_data;
        $group->meta_data   = json_encode($mergedAttributes);
        $group->save();
    }

    /**
     * Check for existing pending or processing imports for the group.
     *
     * @param string $groupId
     * @throws ApplicationException
     */
    public function checkExistingImport(string $groupId): void
    {
        $existingImport = ContactImport::where('group_id', $groupId)
            ->whereIn('status', [
                ContactImportStatusEnum::PENDING->value,
                ContactImportStatusEnum::PROCESSING->value,
            ])
            ->first();

        if ($existingImport) {
            throw new ApplicationException(
                "Another file is being processed for this group. Please wait until it completes.",
                Response::HTTP_NOT_FOUND
            );
        }
    }


    /**
     * Create a ContactImport record with the total contacts count.
     *
     * @param int $fileId
     * @param string $groupId
     * @param int $totalContacts
     * @param array $data
     * @return ContactImport
     */
    public function createContactImport(
        int $fileId,
        string $groupId,
        int $totalContacts,
        array $data
    ): ContactImport {

        $mappingData = [
            'location'          => Arr::get($data, 'location.0', ''),
            'value'             => Arr::get($data, 'value.0', ''),
            'add_header_row'    => Arr::get($data, 'new_row', ''),
        ];

        return ContactImport::create([
            'file_id' => $fileId,
            'group_id' => $groupId,
            'status' => ContactImportStatusEnum::PENDING,
            'total_contacts' => $totalContacts,
            'processed_contacts' => 0,
            'total_emails' => 0,
            'processed_emails' => 0,
            'meta_data' => $mappingData,
        ]);
    }
}