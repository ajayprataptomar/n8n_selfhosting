<?php

namespace App\Services\Content;

use App\Models\ContentResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ContentResourceService
{
    /**
     * List resources strictly scoped to the tenant
     */
    public function listForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return ContentResource::where('user_id', $userId)
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Retrieve a single resource with strict tenant authorization check
     *
     * @throws ModelNotFoundException|AuthorizationException
     */
    public function getForUser(int $userId, string|int $idOrUid): ContentResource
    {
        $query = ContentResource::query();

        if (is_numeric($idOrUid)) {
            $resource = $query->where('id', (int) $idOrUid)->first();
        } else {
            $resource = $query->where('uid', $idOrUid)->first();
        }

        if (!$resource) {
            throw new ModelNotFoundException("Content resource not found.");
        }

        if ((int) $resource->user_id !== $userId) {
            throw new AuthorizationException("Unauthorized access to content resource.");
        }

        return $resource;
    }

    /**
     * Create a new business content resource for tenant
     */
    public function createForUser(int $userId, array $data): ContentResource
    {
        return DB::transaction(function () use ($userId, $data) {
            $isDefault = !empty($data['is_default']);

            // If marked as default, unset existing defaults for this tenant
            if ($isDefault) {
                ContentResource::where('user_id', $userId)->update(['is_default' => false]);
            } else {
                // If this is the user's first resource, make it default automatically
                $count = ContentResource::where('user_id', $userId)->count();
                if ($count === 0) {
                    $isDefault = true;
                }
            }

            return ContentResource::create([
                'user_id'             => $userId,
                'name'                => $data['name'],
                'company_name'        => $data['company_name'],
                'business_type'       => $data['business_type'] ?? null,
                'company_description' => $data['company_description'] ?? null,
                'website'             => $data['website'] ?? null,
                'industry'            => $data['industry'] ?? null,
                'target_audience'     => $data['target_audience'] ?? null,
                'brand_voice'         => $data['brand_voice'] ?? null,
                'writing_preferences' => $this->normalizeJsonField($data['writing_preferences'] ?? null),
                'products_services'   => $this->normalizeJsonField($data['products_services'] ?? null),
                'faqs'                => $this->normalizeJsonField($data['faqs'] ?? null),
                'templates'           => $this->normalizeJsonField($data['templates'] ?? null),
                'status'              => $data['status'] ?? 'active',
                'is_default'          => $isDefault,
            ]);
        });
    }

    /**
     * Update an existing resource with strict tenant authorization
     *
     * @throws ModelNotFoundException|AuthorizationException
     */
    public function updateForUser(int $userId, string|int $idOrUid, array $data): ContentResource
    {
        $resource = $this->getForUser($userId, $idOrUid);

        return DB::transaction(function () use ($resource, $userId, $data) {
            $isDefault = !empty($data['is_default']);

            if ($isDefault && !$resource->is_default) {
                ContentResource::where('user_id', $userId)->update(['is_default' => false]);
            }

            $updateData = [];
            $fields = [
                'name', 'company_name', 'business_type', 'company_description',
                'website', 'industry', 'target_audience', 'brand_voice', 'status'
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            $jsonFields = ['writing_preferences', 'products_services', 'faqs', 'templates'];
            foreach ($jsonFields as $jf) {
                if (array_key_exists($jf, $data)) {
                    $updateData[$jf] = $this->normalizeJsonField($data[$jf]);
                }
            }

            if (array_key_exists('is_default', $data)) {
                $updateData['is_default'] = $isDefault;
            }

            $resource->update($updateData);

            return $resource->fresh();
        });
    }

    /**
     * Delete resource with tenant authorization
     *
     * @throws ModelNotFoundException|AuthorizationException
     */
    public function deleteForUser(int $userId, string|int $idOrUid): bool
    {
        $resource = $this->getForUser($userId, $idOrUid);
        return (bool) $resource->delete();
    }

    /**
     * Fetch default or oldest active resource for a user
     */
    public function getDefaultOrFirst(int $userId): ?ContentResource
    {
        return ContentResource::where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'asc')
            ->first();
    }

    /**
     * Helper to parse string or array into normalized JSON array
     */
    protected function normalizeJsonField($value): ?array
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
