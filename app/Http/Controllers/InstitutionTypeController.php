<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\InstitutionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class InstitutionTypeController extends BaseController
{
    protected string $modelClass = InstitutionType::class;
    protected string $modelLabel = 'Institution type';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $consoAdmin = auth()->user()->isConsoAdmin();

        $data = InstitutionType::with(['institutions:id,name,type_id'])
            ->orderBy('name', 'ASC')
            ->get();

        $types = $data->map(fn ($type) => $this->formatRecord($type, $consoAdmin));

        return response()->json(['records' => $types], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->isConsoAdmin()) {
            return $this->respondError(__('institutions.not_authorized'));
        }

        $this->validate($request, ['name' => 'required']);

        if (InstitutionType::where('name', $request->input('name'))->exists()) {
            return $this->respondError(__('institutions.type_duplicate'));
        }

        $type = InstitutionType::create(['name' => $request->input('name')]);
        $type->setRelation('institutions', collect());

        return $this->respondSuccess(
            __('institutions.type_created'),
            $this->formatRecord($type, true)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (!auth()->user()->isConsoAdmin()) {
            return $this->respondError(__('institutions.not_authorized'));
        }

        $type = InstitutionType::findOrFail($id);

        $this->validate($request, ['name' => 'required']);

        $duplicate = InstitutionType::where('name', $request->input('name'))
            ->where('id', '<>', $type->id)
            ->exists();

        if ($duplicate) {
            return $this->respondError(__('institutions.type_duplicate'));
        }

        $type->update(['name' => $request->input('name')]);
        $type->load('institutions:id,name,type_id');

        return $this->respondSuccess(
            __('institutions.type_updated'),
            $this->formatRecord($type, true)
        );
    }

    /**
     * Restrict deletion to ConsoAdmins and protect the default type (id = 1).
     */
    protected function canDeleteRecord(Model $type): bool|string
    {
        if (!auth()->user()->isConsoAdmin()) {
            return __('institutions.not_authorized');
        }

        if ($type->id == 1) {
            return __('institutions.type_default_protected');
        }

        return true;
    }

    /**
     * Reassign any institutions using this type back to the default type (id = 1).
     */
    protected function beforeDeleteRecord(Model $type): void
    {
        Institution::where('type_id', $type->id)->update(['type_id' => 1]);
    }

    /**
     * Import institution types from a CSV to the database.
     */
    public function import(Request $request)
    {
        if (!auth()->user()->isConsoAdmin()) {
            return $this->respondError(__('institutions.not_authorized'));
        }

        $parsed = $this->parseCsvUpload($request, ['Full Replacement', 'Additions & Updates']);
        if (isset($parsed['error'])) {
            return $this->respondError($parsed['error']);
        }

        $rows = $parsed['rows'];
        $importType = $parsed['type'];

        $num_deleted = 0;
        $num_skipped = 0;
        $num_updated = 0;
        $num_created = 0;

        // Preserve institution type mappings before wiping non-default types on Full Replacement
        if ($importType === 'Full Replacement') {
            $institutions = Institution::get();
            $original_types = [];
            foreach ($institutions as $inst) {
                $original_types[$inst->id] = $inst->type_id;
                $inst->type_id = 1;
                $inst->save();
            }

            $num_deleted = InstitutionType::count() - 1;
            InstitutionType::where('id', '<>', 1)->delete();
        }

        $current_types = InstitutionType::get();

        foreach ($rows as $row) {
            if (!isset($row) || $row === '' || !is_numeric($row) || intval($row) <= 1) {
                continue;
            }

            $_tid = intval($row);
            $_name = isset($row) ? trim($row) : '';

            if ($importType === 'Additions & Updates' && $current_types->where('name', $_name)->first()) {
                $num_skipped++;
                continue;
            }

            $existing_type = $current_types->where('id', $_tid)->first();
            if ($existing_type) {
                if ($_name !== '') {
                    $existing_type->update(['name' => $_name]);
                    $num_updated++;
                }
            } elseif ($_name !== '') {
                InstitutionType::create(['id' => $_tid, 'name' => $_name]);
                $num_created++;
            }
        }

        // Restore institution type assignments where the type ID still exists
        if ($importType === 'Full Replacement') {
            $new_ids = InstitutionType::pluck('id')->toArray();
            foreach ($original_types as $id => $typeId) {
                if (in_array($typeId, $new_ids)) {
                    $inst = $institutions->where('id', $id)->first();
                    if ($inst) {
                        $inst->update(['type_id' => $typeId]);
                    }
                }
            }
        }

        $extra = ($num_skipped > 0) ? __('institutions.type_import_skipped', ['count' => $num_skipped]) : '';
        $msg = $this->formatImportSummary(
            __('institutions.type_import_success'),
            $num_updated,
            $num_created,
            $num_deleted,
            0,
            $extra
        );

        return $this->respondSuccess($msg);
    }

    /**
     * Format an InstitutionType record consistently for the dataset UI.
     */
    private function formatRecord(InstitutionType $type, bool $consoAdmin): array
    {
        return [
            'id'           => $type->id,
            'name'         => $type->name,
            'can_edit'     => ($type->id > 1 && $consoAdmin),
            'can_delete'   => ($type->id > 1 && $consoAdmin && $type->institutions->count() === 0),
            'institutions' => $type->institutions->toArray(),
        ];
    }
}
