<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;

trait CleansDatasetInput
{
    /**
     * Converts 'Active'/'Inactive' status strings to is_active (1/0).
     */
    protected function normalizeStatus(array &$input, ?int $default = null): void
    {
        if (isset($input['status'])) {
            $input['is_active'] = ($input['status'] === 'Active') ? 1 : 0;
        } elseif (!isset($input['is_active']) && $default !== null) {
            $input['is_active'] = $default;
        }
    }

    /**
     * Syncs a BelongsToMany relationship from either flat IDs or object arrays ([['id' => 1]]).
     */
    protected function syncRelationIds(BelongsToMany $relation, $items): void
    {
        if (!is_array($items)) {
            return;
        }

        if (empty($items)) {
            $relation->sync([]);
            return;
        }

        $first = reset($items);
        $ids = is_array($first) ? array_column($items, 'id') : $items;
        $relation->sync($ids);
    }

    /**
     * Strips standard frontend UI keys and virtual attributes before create/update.
     */
    protected function cleanPayload(array $input, array $extraExcept = []): array
    {
        $baseExcept = ['id', 'status', 'can_edit', 'can_delete', 'role', 'updated'];
        return Arr::except($input, array_merge($baseExcept, $extraExcept));
    }
}
