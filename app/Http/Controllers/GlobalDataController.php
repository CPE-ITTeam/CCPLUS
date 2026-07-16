<?php

namespace App\Http\Controllers;

// Controller methods to return data from the global database tables (for the API)
class GlobalDataController extends BaseController
{

    /**
     * Return records from the titles table
     * @return JSON
     */
    public function titles()
    {
        $data = \App\Models\Title::all()->toArray();
        return response()->json($data);
    }

    /**
     * Return records from the platforms table
     * @return JSON
     */
    public function platforms()
    {
        $data = \App\Models\Platform::get(['id', 'name'])->toArray();
        return response()->json($data);
    }

    /**
     * Return records from the publishers table
     * @return JSON
     */
    public function publishers()
    {
        $data = \App\Models\Publisher::get(['id', 'name'])->toArray();
        return response()->json($data);
    }

    /**
     * Return records from the databases table
     * @return JSON
     */
    public function databases()
    {
        $data = \App\Models\DataBase::get(['id', 'name', 'PropID'])->toArray();
        return response()->json($data);
    }

    /**
     * Return records from the items table
     * @return JSON
     */
    public function items()
    {
        $data = \App\Models\Item::all()->toArray();
        return response()->json($data);
    }

    /**
     * Return records from the data_hosts table
     * @return JSON
     */
    public function datahosts()
    {
        $data = \App\Models\DataHost::all()->toArray();
        return response()->json($data);
    }

    /**
     * Return records from the ccplus_errors table
     * @return JSON
     */
    public function errors()
    {
        $data = \App\Models\CcplusError::get(['id', 'message', 'explanation', 'new_status'])->toArray();
        return response()->json($data);
    }

}
