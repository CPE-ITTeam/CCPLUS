<?php
namespace App\Services;
use App\Models\HarvestLog;

class HarvestService {

    /**
     * Return an array IDs of institutions or providers with successful harvests
     *
     * @param  String  $column :  "prov_id" or "inst_id"
     * @return \Illuminate\Http\Response
     */
    public function hasHarvests($column)
    {
        // Setup the query
        $raw_query = $column . ",count(*) as count";
        $_join = config('database.connections.consodb.database') . '.credentials as Set';

        //Run it
        $ids_with_data = HarvestLog::join($_join, 'harvestlogs.credentials_id', 'Set.id')
                                   ->selectRaw($raw_query)
                                   ->where('harvestlogs.status', 'Success')
                                   ->groupBy($column)
                                   ->pluck($column)->toArray();
        // Return the IDs
        return $ids_with_data;
    }

}
