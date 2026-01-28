<?php

namespace App\Models;
use DB;
use App\Models\Report;
use App\Models\ConnectionField;
use App\Models\Consortium;
use App\Models\Connection;
use App\Models\Credential;
use App\Models\HarvestLog;
use App\Models\InstitutionGroup;
use Illuminate\Database\Eloquent\Model;

class GlobalProvider extends Model
{
  /**
   * Class Constructor
   */
    private $global_masters;
    private $all_connectors;
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $this->global_masters = Report::where('parent_id',0)->orderBy('dorder', 'ASC')->get();
        $this->all_connectors = ConnectionField::get();
    }
  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'globaldb';
    protected $table = 'global_providers';

  /**
   * Mass assignable attributes.
   *
   * @var array
   */
    protected $attributes = ['registry_id' => null, 'name' => '', 'abbrev' => null, 'is_active' => 1, 'refreshable' => 1,
                             'refresh_result' => null, 'master_reports' => '{}', 'day_of_month' => 15, 'platform_parm' => null,
                             'selected_release' => null];
    protected $fillable = ['id', 'registry_id', 'name', 'abbrev', 'is_active', 'refreshable', 'refresh_result', 'master_reports',
                           'day_of_month', 'platform_parm', 'selected_release'];
    protected $casts = ['id'=>'integer', 'is_active'=>'integer', 'refreshable'=>'integer', 'master_reports' => 'array',
                        'day_of_month' => 'integer'];

    // Return the ConnectionField detail based on connectors array
    public function connectionFields()
    {
        $connectors = array();
        $registry = $this->default_registry();
        if ($registry) {
            $cnxs = $registry->connectors;
            foreach ($this->all_connectors->toArray() as $cnx) {
                $cnx['required'] = in_array($cnx['id'],$cnxs);
                $connectors[] = $cnx;
            }
        }
        return $connectors;
    }

    public function credentials()
    {
        return $this->hasMany('App\Models\Credential', 'prov_id');
    }

    public function titleReports()
    {
        return $this->hasMany('App\Models\TitleReport');
    }

    public function databaseReports()
    {
        return $this->hasMany('App\Models\DatabaseReport');
    }

    public function platformReports()
    {
        return $this->hasMany('App\Models\PlatformReport');
    }

    public function itemReports()
    {
        return $this->hasMany('App\Models\ItemReport');
    }

    public function connections()
    {
        return $this->hasMany('App\Models\Connection', 'global_id');
    }

    public function registries()
    {
        return $this->hasMany('App\Models\CounterRegistry', 'global_id');
    }

    public function default_registry()
    {
        $rel = (is_null($this->selected_release)) ? $this->registries->max('release') : $this->selected_release;
        $reg = $this->registries->where('release', $rel)->first();
        if ($reg) return $reg;

        // if selected_release registry not found, try for max instead
        $rel = $this->registries->max('release');
        return $this->registries->where('release', $rel)->first();
    }

    public function default_release()
    {
        if (!is_null($this->selected_release)) {
            return $this->selected_release;
        }
        $return_value = "";
        $registry = $this->default_registry();
        if ($registry) {
            $return_value = trim($registry->release);
        }
        return $return_value;
    }

    public function service_url()
    {
        $return_url = null;
        $registry = $this->default_registry();
        if ($registry) {
            $return_url = trim($registry->service_url);
        }
        return $return_url;
    }

    public function notifications_url()
    {
        $return_url = null;
        $registry = $this->default_registry();
        if ($registry) {
            $return_url = trim($registry->notifications_url);
        }
        return $return_url;
    }

    public function connectors()
    {
        $cnx = [];
        $registry = $this->default_registry();
        if ($registry) {
            $cnx = $registry->connectors;        
        }
        return $cnx;
    }

    public function isComplete()
    {
        $required = $this->connectors;
        $connectors = $this->all_connectors->whereIn('id',$required)->pluck('name')->toArray();
        foreach ($connectors as $cnx) {
            if (is_null($this->$cnx) || trim($this->$cnx) == '' || $this->$cnx == '-required-') return false;
        }
        return true;
    }

   /**
    * Return most recent harvest information (for the current instance)
    * @return HarvestLog
    */
    public function lastHarvest()
    {
        return HarvestLog::join('credentials', 'harvestlogs.credentials_id', '=', 'credentials.id')
                         ->where('credentials.prov_id', $this->id)
                         ->orderBy('harvestlogs.created_at', 'DESC')->limit(1)->first();
    }

    // Return an array of connected institution IDs
    public function connectedInstitutions()
    {
        $all_ids = array();
        foreach ($this->connections as $cnx) {
            $cnx_inst_ids = $cnx->institutionIds();
            $all_ids = array_unique(array_merge($all_ids, $cnx_inst_ids));
        }
        return $all_ids;
    }

    // Return an array of connected group IDs
    public function connectedGroups()
    {
        return $this->connections->whereNotNull('group_id')->pluck('group_id')->toArray();
    }

    // Build and return an array of by-report assignments
    //   $reports[
    //            'PR' = ['available' => T/F, 'conso' => T/F,
    //                    'insts' => [id,id,id,id...] || 'insts' = 'ALL' ,
    //                    'groups' => [id,id,id,id,...] || 'groups' = 'ALL' ] ,
    //            ... for 'DR','TR','IR'
    //           ]
    public function enabledReports($inst = null)
    {
        $consoAdmin = auth()->user()->isConsoAdmin();

        // Setup array for each master report
        $reports = array();
        $available = $this->master_reports;
        if (is_null($available)) $available = [];

        $limitInsts  = ($consoAdmin) ? [] : auth()->user()->adminInsts();
        $limitGroups = ($consoAdmin) ? [] : auth()->user()->adminGroups();
        foreach ($this->global_masters as $mr) {
            $rec = array('conso' => false, 'insts' => [], 'groups' => [], 'requested' => false);
            $rec['available'] = in_array($mr->id,$available);
            // If report not available or not connected, update $reports and get next report type
            if ( !$rec['available'] || $this->connections->count() == 0) {
                $reports[$mr->name] = $rec;
                continue;
            }
            // Set conso flag if the report is enabled conso-wide
            $consoWide = $this->connections->where('inst_id',1)->first();
            if ($consoWide) {
                foreach ($consoWide->reports as $rpt) {
                    $rec['conso'] = true;
                }
            }
            // Build role-sensitive, scoped report assignments from all connection that are either
            // not conso-wide or for a specific $inst (used for credentials)
            $connections = (is_null($inst)) ? $this->connections->where('inst_id','<>',1)
                                            : $this->connections->where('inst_id',$inst);
            foreach ($connections as $cnx) {
                $rpt = $cnx->reports->where('id',$mr->id)->first();
                if ($rpt && !$rec['conso']) {
                    if (!is_null($cnx->inst_id) && ($consoAdmin || in_array($cnx->inst_id,$limitInsts))) {
                        if ( !in_array($cnx->inst_id,$rec['insts']) ) {
                            $rec['insts'][] = $cnx->inst_id;
                        }
                    } else if (!is_null($cnx->group_id) && ($consoAdmin || in_array($cnx->group_id,$limitGroups))) {
                        if ( !in_array($cnx->group_id,$rec['groups']) ) {
                            $rec['groups'][] = $cnx->group_id;
                        }
                    }
                }
            }
            $rec['requested'] = (count($rec['insts'])>0);   // set it, was initialized false
            $reports[$mr->name] = $rec;
        }
        return $reports;
    }

    /* Update connection report assignments 
     *  @param  Array   conso_ids : report ID's
     *  @param  String  type : operation to perform
     *                   'detach' : IDs in $conso_ids currently attached to any non-conso connection(s) are detached
     *                   'attach' : IDs in $conso_ids are attached to all non-conso connections
     *  @return Integer deleted : count of connections deleted
     */
    public function updateReports($conso_ids, $type) {
        $deleted = 0;

        // Loop through all (non-consortium) connections connected to the global
        // (will include group-connections since their inst_id should be null)
        $connections = $this->connections()->with('reports')->where('inst_id','<>',1)->get();
        foreach ($connections as $cnx) {

            // Get IDs to add/remove
            $current_ids = $cnx->reports->pluck('id')->toArray();
            $changed_ids = ($type=="attach") ? $conso_ids : array_intersect($current_ids, $conso_ids);

            // Add/Remove the report connection(s)
            foreach ($changed_ids as $r) {
                if ($type == "attach") {
                    $cnx->reports()->attach($r);
                } else {
                    $cnx->reports()->detach($r);
                }
            }
            // If there are no remaining reports attached for this connection, delete it
            if ($type == "detach" && $cnx->reports()->count() == 0) {
                $cnx->delete();
                $deleted++;
            }
        }
        return $deleted;
    }

   /**
    * Apply GlobalProvider settings to all active consortia instances.
    *
    * @return \Illuminate\Http\Response
    */
    public function appyToInstances()
    {
        $global_reports = $this->master_reports;

        // Setup connector lookups
        $registry = $this->default_registry();
        $fields = ($registry) ? $this->all_connectors->whereIn('id',$registry->connectors)->pluck('name')->toArray() : array();
        $unused_fields = ($registry) ? $this->all_connectors->whereNotIn('id',$registry->connectors)->pluck('name')->toArray()
                                     : array();

        // Get active consortium instances and loop on them
        $instances = Consortium::where('is_active',1)->get();
        $keepDB  = config('database.connections.consodb.database');
        foreach ($instances as $instance) {
            // set the database connection
            config(['database.connections.consodb.database' => "ccplus_" . $instance->ccp_key]);
            try {
                DB::reconnect('consodb');
            } catch (\Exception $e) {
                continue;
            }

            // Assign current global is_active value to all connections (keep prior value for late)
            $conns = Connection::where('global_id',$this->id)->get(['id','is_active']);
            $_cnx = $conns->first();
            if (!$_cnx) continue;
            $was_active = $_cnx->is_active;
            $conns->update(['is_active' => $this->is_active]);

            // Detach any reports that are no longer available
            foreach ($conns as $cnx) {
                foreach ($cnx->reports as $rpt) {
                    if (!in_array($rpt->id,$global_reports)) {
                        $cnx->reports()->detach($rpt->id);
                    }
                }
            }

            // Check/update credentials
            // Get all (.not.disabled) credentials for this global from the current conso instances
            $credentials = Credential::with('institution')->where('prov_id',$this->id)
                                     ->where('status','<>','Disabled')->get();

            // Check, and possibly update, status for related credentials (skip if disabled)
            foreach ($credentials as $cred) {
                $cred_updates = array();
                // Clear all unused fields with values, regardless of completeness
                foreach ($unused_fields as $uf) {
                    if (strlen($cred->{$uf}) > 0) {
                        $cred_updates[$uf]= '';
                    }
                }
                if ($cred->isComplete()) {
                    // cred is marked Enabled, but global just went inactive, suspend it
                    if ($cred->status == 'Enabled' && $was_active && !$this->is_active ) {
                        $cred_updates['status'] = 'Suspended';
                    }
                    // cred is marked Suspended, but global is now active with active institution, enable it
                    if ($cred->status == 'Suspended' && !$was_active && $this->is_active &&
                        $cred->institution->is_active) {
                        $cred_updates['status'] = 'Enabled';
                    }
                    // cred status is marked Incomplete
                    if ($cred->status == 'Incomplete') {
                        // if global and institution are active, enable it, otherwise mark suspended
                        $cred_updates['status'] = ($this->is_active && $cred->institution->is_active) ?
                                                        'Enabled' : 'Suspended';
                    }
                // If required connectors are missing value(s), mark them and update cred status to Incomplete
                } else {
                    $cred_updates['status'] = 'Incomplete';
                    foreach ($fields as $fld) {
                        if ($cred->$fld == null || $cred->$fld == '') {
                            $cred_updates[$fld] = "-required-";
                        }
                    }
                }
                if (count($cred_updates) > 0) {
                    $cred->update($cred_updates);
                }
            }
        }

        // Restore the database handle
        config(['database.connections.consodb.database' => $keepDB]);
        return true;
    }

}
