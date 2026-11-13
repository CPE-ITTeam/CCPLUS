<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;

trait ImportsCsvData
{
    /**
     * Validates and parses an uploaded CSV file into an array of rows.
     *
     * @param  Request  $request
     * @param  array    $allowedTypes  Optional list of allowed 'type' values (e.g. ['Additions & Updates', 'Full Replacement'])
     * @return array{rows: array, type: ?string}|array{error: string}
     */
    protected function parseCsvUpload(Request $request, array $allowedTypes = []): array
    {
        $rules = ['csvfile' => 'required'];
        if (!empty($allowedTypes)) {
            $rules['type'] = 'required';
        }

        $this->validate($request, $rules);

        if (!$request->hasFile('csvfile')) {
            return ['error' => __('messages.import_file_error')];
        }

        $importType = null;
        if (!empty($allowedTypes)) {
            $importType = $request->input('type');
            if (!in_array($importType, $allowedTypes, true)) {
                return ['error' => __('messages.import_type_invalid')];
            }
        }

        $path = $request->file('csvfile')->getRealPath();
        $csvData = file_get_contents($path);
        if ($csvData === false || trim($csvData) === '') {
            return ['error' => __('messages.import_empty')];
        }

        // Strip UTF-8 BOM if present
        $csvData = preg_replace('/^\x{FEFF}/u', '', $csvData);
        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($csvData)));

        if (count($rows) < 1) {
            return ['error' => __('messages.import_empty')];
        }

        return [
            'rows' => $rows,
            'type' => $importType,
        ];
    }

    /**
     * Formats a consistent localized import summary message.
     */
    protected function formatImportSummary(string $prefix, int $updated = 0, int $created = 0, int $deleted = 0, int $skipped = 0, string $extra = ''): string
    {
        $parts = [];
        if ($deleted > 0) {
            $parts[] = __('messages.import_removed', ['count' => $deleted]);
        }
        if ($updated > 0) {
            $parts[] = __('messages.import_updated', ['count' => $updated]);
        }
        if ($created > 0) {
            $parts[] = __('messages.import_added', ['count' => $created]);
        }
        if ($skipped > 0) {
            $parts[] = __('messages.import_skipped', ['count' => $skipped]);
        }

        $detail = !empty($parts) ? implode(', ', $parts) : __('messages.import_no_changes');
        return "{$prefix} : {$detail}{$extra}";
    }
}
