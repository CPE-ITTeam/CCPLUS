<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\ApiResponds;
use App\Http\Controllers\Traits\CleansDatasetInput;
use App\Http\Controllers\Traits\ImportsCsvData;
use App\Http\Controllers\Traits\ManagesDatasetRecords;

class BaseController extends Controller
{
    use ApiResponds;
    use CleansDatasetInput;
    use ImportsCsvData;
    use ManagesDatasetRecords;

    /**
     * Optional Eloquent model class for automated CRUD/bulk actions.
     */
    protected string $modelClass = '';

    /**
     * Optional human-readable label for response messages (e.g., 'Institution').
     */
    protected string $modelLabel = '';
}
