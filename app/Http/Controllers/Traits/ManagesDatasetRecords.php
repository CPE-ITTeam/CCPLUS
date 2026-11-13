<?php

namespace App\Http\Controllers\Traits;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait ManagesDatasetRecords
{
    /**
     * Default destroy endpoint for controllers that define $modelClass.
     */
    public function destroy($id)
    {
        if (!property_exists($this, 'modelClass') || empty($this->modelClass)) {
            return $this->respondError(__('messages.model_not_configured'), 500);
        }

        $query = $this->modelClass::query();

        if (!empty($this->deleteWith)) {
            $query->with($this->deleteWith);
        }

        $record = $query->find($id);
        $label = $this->modelLabel ?? class_basename($this->modelClass);

        if (!$record) {
            return $this->respondError(__('messages.not_found', ['item' => $label]));
        }

        $authCheck = $this->canDeleteRecord($record);
        if ($authCheck !== true) {
            $msg = is_string($authCheck) ? $authCheck : __('messages.delete_forbidden');
            return $this->respondError($msg);
        }

        $this->beforeDeleteRecord($record);

        return $this->deleteModelRecord($record, $label);
    }

    /**
     * Override in child controllers to enforce authorization before deletion.
     * Return true if allowed, or false / custom error string if forbidden.
     */
    protected function canDeleteRecord(Model $record): bool|string
    {
        return true;
    }

    /**
     * Override in child controllers to run cleanup logic before deletion.
     */
    protected function beforeDeleteRecord(Model $record): void
    {
        // Default: nothing
    }

    /**
     * Safely deletes an Eloquent model record with permission and exception guards.
     */
    protected function deleteModelRecord(?Model $record, string $label = 'Record', bool $canDelete = true)
    {
        if (!$record) {
            return $this->respondError(__('messages.not_found', ['item' => $label]));
        }

        if (!$canDelete) {
            return $this->respondError(__('messages.delete_dependent', ['item' => $label]));
        }

        try {
            $record->delete();
            return $this->respondSuccess(__('messages.deleted', ['item' => $label]));
        } catch (Exception $e) {
            return $this->respondError(__('messages.delete_failed', [
                'item'  => $label,
                'error' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Default bulk action handler for standard dataset controllers.
     */
    public function bulk(Request $request)
    {
        $user = auth()->user();
        if (!$this->canBulkManage($user)) {
            return $this->respondError(__('messages.forbidden_request'));
        }

        $this->validate($request, ['ids' => 'required', 'action' => 'required']);
        $ids = (array) $request->input('ids', []);
        $action = $request->input('action');

        if (!property_exists($this, 'modelClass') || empty($this->modelClass)) {
            return $this->respondError(__('messages.model_not_configured'), 500);
        }

        // Scope query to records this user is allowed to modify
        $query = $this->modelClass::whereIn('id', $ids);
        $this->scopeBulkQuery($query, $user);
        $records = $query->get();
        $allowedIds = $records->pluck('id')->toArray();

        // 1. Allow child controller to handle custom bulk actions first
        $customResponse = $this->handleCustomBulkAction($action, $records, $request);
        if ($customResponse !== null) {
            return $customResponse;
        }

        // 2. Standard 'Set Active' / 'Set Inactive'
        if (in_array($action, ['Set Active', 'Set Inactive'], true)) {
            $newIsActive = ($action === 'Set Active') ? 1 : 0;
            $affectedIds = $records->where('is_active', '<>', $newIsActive)->pluck('id')->toArray();

            if (!empty($affectedIds)) {
                $this->modelClass::whereIn('id', $affectedIds)->update(['is_active' => $newIsActive]);
            }

            return response()->json(['result' => true, 'msg' => '', 'affectedIds' => $affectedIds], 200);
        }

        // 3. Standard 'Delete'
        if ($action === 'Delete') {
            if (!empty($allowedIds)) {
                $this->modelClass::whereIn('id', $allowedIds)->delete();
            }

            return response()->json(['result' => true, 'msg' => '', 'affectedIds' => $allowedIds], 200);
        }

        return $this->respondError(__('messages.bulk_unrecognized'));
    }

    /**
     * Override in child controllers to customize bulk authorization.
     */
    protected function canBulkManage($user): bool
    {
        return $user && $user->isAdmin();
    }

    /**
     * Override in child controllers to restrict which records can be bulk-modified.
     */
    protected function scopeBulkQuery($query, $user): void
    {
        // Default: no extra filtering
    }

    /**
     * Override in child controllers to handle domain-specific bulk actions.
     */
    protected function handleCustomBulkAction(string $action, $records, Request $request)
    {
        return null;
    }

    /**
     * Handles standard 'Set Active' / 'Set Inactive' bulk updates across models with an is_active column.
     */
    protected function handleBulkStatus(string $modelClass, array $ids, string $action, string $pluralLabel = 'Records')
    {
        if (!in_array($action, ['Set Active', 'Set Inactive'], true)) {
            return null;
        }

        if (empty($ids)) {
            return $this->respondError(__('messages.bulk_none_selected', ['items' => $pluralLabel]));
        }

        $isActive = ($action === 'Set Active') ? 1 : 0;
        $modelClass::whereIn('id', $ids)->update(['is_active' => $isActive]);

        $key = $isActive ? 'messages.bulk_activated' : 'messages.bulk_deactivated';
        return $this->respondSuccess(__($key, ['items' => $pluralLabel]));
    }
}
