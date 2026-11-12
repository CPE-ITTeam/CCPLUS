<?php

namespace App\Http\Controllers;

use App\Models\CcplusError;
use App\Models\DataBase;
use App\Models\DataHost;
use App\Models\Item;
use App\Models\Platform;
use App\Models\Publisher;
use App\Models\Title;
use Illuminate\Http\JsonResponse;

/**
 * Controller methods to return data from the global database tables (for the API).
 */
class GlobalDataController extends BaseController
{
    public function titles(): JsonResponse
    {
        return $this->fetchGlobalTable(Title::class);
    }

    public function platforms(): JsonResponse
    {
        return $this->fetchGlobalTable(Platform::class, ['id', 'name']);
    }

    public function publishers(): JsonResponse
    {
        return $this->fetchGlobalTable(Publisher::class, ['id', 'name']);
    }

    public function databases(): JsonResponse
    {
        return $this->fetchGlobalTable(DataBase::class, ['id', 'name', 'PropID']);
    }

    public function items(): JsonResponse
    {
        return $this->fetchGlobalTable(Item::class);
    }

    public function datahosts(): JsonResponse
    {
        return $this->fetchGlobalTable(DataHost::class);
    }

    public function errors(): JsonResponse
    {
        return $this->fetchGlobalTable(CcplusError::class, ['id', 'message', 'explanation', 'new_status']);
    }

    /**
     * Query a global lookup model with optional column projection.
     */
    private function fetchGlobalTable(string $modelClass, array $columns = ['*']): JsonResponse
    {
        $records = ($columns === ['*'])
            ? $modelClass::all()
            : $modelClass::get($columns);

        return response()->json($records->toArray());
    }
}
