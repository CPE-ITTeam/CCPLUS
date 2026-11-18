<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;

trait ImportsCsvData
{
    /**
     * Validates and parses an uploaded CSV file into an array of raw rows.
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
     * Parses an uploaded CSV into associative rows keyed by canonical field names.
     * Automatically resolves translated headers across all supported locales,
     * handles any column order, and ignores trailing blank columns.
     *
     * @param  Request  $request
     * @param  array    $fieldAliases  Map of canonical_key => [alias1, alias2, ...] (and optional 'lang_keys' => [...])
     * @param  array    $allowedTypes  Optional allowed import modes
     * @return array{rows: array<int, array<string, string>>, type: ?string, headers: array}|array{error: string}
     */
    protected function parseMappedCsvUpload(Request $request, array $fieldAliases, array $allowedTypes = []): array
    {
        $parsed = $this->parseCsvUpload($request, $allowedTypes);
        if (isset($parsed['error'])) {
            return $parsed;
        }

        $rawRows = $parsed['rows'];
        $rawHeaders = array_shift($rawRows) ?? [];

        // Trim trailing blank headers caused by extra empty columns in Excel/Calc
        while (!empty($rawHeaders) && trim((string) end($rawHeaders)) === '') {
            array_pop($rawHeaders);
        }

        if (empty($rawHeaders)) {
            return ['error' => __('messages.import_empty')];
        }

        // Build case-insensitive lookup map: normalized_header => canonical_key
        $aliasLookup = $this->buildHeaderAliasLookup($fieldAliases);

        $columnMap = [];
        foreach ($rawHeaders as $idx => $header) {
            $norm = $this->normalizeHeaderLabel((string) $header);
            if ($norm !== '' && isset($aliasLookup[$norm])) {
                $columnMap[$idx] = $aliasLookup[$norm];
            }
        }

        $mappedRows = [];
        foreach ($rawRows as $row) {
            // Skip completely blank lines
            $nonEmpty = array_filter($row, fn ($cell) => trim((string) $cell) !== '');
            if (empty($nonEmpty)) {
                continue;
            }

            $assoc = [];
            foreach ($columnMap as $idx => $canonicalKey) {
                $assoc[$canonicalKey] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
            }
            $mappedRows[] = $assoc;
        }

        return [
            'rows'    => $mappedRows,
            'type'    => $parsed['type'],
            'headers' => $columnMap,
        ];
    }

    /**
     * Builds a normalized lookup table mapping raw/translated header labels to canonical keys.
     */
    protected function buildHeaderAliasLookup(array $fieldAliases): array
    {
        $lookup = [];
        $locales = ['en', 'de', 'es', 'fi', 'fr', 'hu'];

        foreach ($fieldAliases as $canonicalKey => $aliases) {
            $lookup[$this->normalizeHeaderLabel($canonicalKey)] = $canonicalKey;

            foreach ((array) $aliases as $alias) {
                // If alias looks like a translation key (e.g. 'fields.institution'), resolve across all locales
                if (str_contains($alias, '.')) {
                    foreach ($locales as $loc) {
                        $translated = __($alias, [], $loc);
                        if ($translated !== $alias) {
                            $lookup[$this->normalizeHeaderLabel($translated)] = $canonicalKey;
                        }
                    }
                } else {
                    $lookup[$this->normalizeHeaderLabel($alias)] = $canonicalKey;
                }
            }
        }

        return $lookup;
    }

    /**
     * Normalizes a CSV column header for resilient matching.
     */
    protected function normalizeHeaderLabel(string $label): string
    {
        $clean = preg_replace('/\s+/', ' ', trim($label));
        return mb_strtolower($clean);
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
