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
            return ['error' => 'Error accessing CSV import file'];
        }

        $importType = null;
        if (!empty($allowedTypes)) {
            $importType = $request->input('type');
            if (!in_array($importType, $allowedTypes, true)) {
                return ['error' => 'Error - unrecognized import type.'];
            }
        }

        $path = $request->file('csvfile')->getRealPath();
        $csvData = file_get_contents($path);
        if ($csvData === false || trim($csvData) === '') {
            return ['error' => 'Import file is empty, no changes applied.'];
        }

        // Strip UTF-8 BOM if present
        $csvData = preg_replace('/^\x{FEFF}/u', '', $csvData);
        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($csvData)));

        if (count($rows) < 1) {
            return ['error' => 'Import file is empty, no changes applied.'];
        }

        return [
            'rows' => $rows,
            'type' => $importType,
        ];
    }

    /**
     * Formats a consistent import summary message.
     */
    protected function formatImportSummary(string $prefix, int $updated = 0, int $created = 0, int $deleted = 0, int $skipped = 0, string $extra = ''): string
    {
        $parts = [];
        if ($deleted > 0) {
            $parts[] = "{$deleted} removed";
        }
        if ($updated > 0) {
            $parts[] = "{$updated} updated";
        }
        if ($created > 0) {
            $parts[] = "{$created} added";
        }
        if ($skipped > 0) {
            $parts[] = "{$skipped} skipped";
        }

        $detail = !empty($parts) ? implode(', ', $parts) : '0 changes';
        return "{$prefix} : {$detail}{$extra}";
    }
}
