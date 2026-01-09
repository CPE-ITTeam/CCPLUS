<?php

namespace App\Models;
use DB;
use App\Models\Report;
use App\Models\ConnectionField;
use App\Models\Consortium;
use App\Models\Provider;
use App\Models\Credential;
use App\Models\HarvestLog;
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

    public function consoProviders()
    {
        return $this->hasMany('App\Models\Provider', 'global_id');
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
    // NOTE: caller should include with("consoProviders") before using this on a collection
    public function connectedInstitutions()
    {
        if ($this->consoProviders->count() == 0) return [];
        return $this->consoProviders->pluck('inst_id')->toArray();
    }

    // Build and return an array of by-report assignments
    // NOTE: caller should include with("consoProviders","consoProviders.reports") before using this on a collection
    public function enabledReports()
    {
        // Setup array for each master report
        $reports = array();
        foreach ($this->global_masters as $mr) {
            $reports[$mr->name] = array();
        }
        if ($this->consoProviders->count() == 0) return $reports;

        // get consortium-wide settings
        $consoWide = $this->consoProviders->where('inst_id',1)->first();
        if ($consoWide) {
            foreach ($consoWide->reports as $rpt) {
                $reports[$rpt->name] = "ALL";
            }
        }

        // Get/build inst-specific assignments
        $instSpecific = $this->consoProviders->where('inst_id','<>',1);
        foreach ($instSpecific as $instProv) {
            foreach ($instProv->reports as $rpt) {
                if ($reports[$rpt->name] == "ALL") continue;
                $reports[$rpt->name][] = $instProv->inst_id;
            }
        }
        return $reports;
    }

    /* Update (conso-level Provider) report assignments 
     *  @param  Array   conso_ids  (consortium report ID's to match on)
     *  @param  String  type : operation to perform
     *  @return Integer deleted : count of providers deleted
     */
    public function updateReports($conso_ids, $type) {
        $deleted = 0;

        // Loop through all (non-consortium) providers connected to the global
        $inst_provs = $this->consoProviders()->with('reports')->where('inst_id','<>',1)->get();
        foreach ($inst_provs as $prov) {

            // Get IDs to add/remove
            $current_ids = $prov->reports->pluck('id')->toArray();
            $changed_ids = ($type=="attach") ? array_diff($conso_ids, $current_ids)
                                             : array_intersect($current_ids, $conso_ids);

            // Add/Remove the report connection(s)
            foreach ($changed_ids as $r) {
                if ($type == "attach") {
                    $prov->reports()->attach($r);
                } else {
                    $prov->reports()->detach($r);
                }
            }
            // If there are no remaining reports attached for this provider, delete it
            if ($type == "detach" && $prov->reports()->count() == 0) {
                $prov->delete();
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

        // Get active consortia
        $instances = Consortium::where('is_active',1)->get();
        $keepDB  = config('database.connections.consodb.database');

        // Setup updates array for all related consortial providers
        $prov_updates = array('name' => $this->name, 'is_active' => $this->is_active);
        foreach ($instances as $instance) {
            // switch the database connection
            config(['database.connections.consodb.database' => "ccplus_" . $instance->ccp_key]);
            try {
                DB::reconnect('consodb');
            } catch (\Exception $e) {
                continue;
            }
            // Update the providers table (name and/or is_active)
            $con_prov = Provider::where('global_id',$this->id)->first();
            if (!$con_prov) continue;
            $was_active = $con_prov->is_active;
            $con_prov->update($prov_updates);

            // Detach any reports that are no longer available
            foreach ($con_prov->reports as $rpt) {
                if (!in_array($rpt->id,$global_reports)) {
                    $con_prov->reports()->detach($rpt->id);
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
                    // cred is marked Enabled, but provider just went inactive, suspend it
                    if ($cred->status == 'Enabled' && $was_active && !$con_prov->is_active ) {
                        $cred_updates['status'] = 'Suspended';
                    }
                    // cred is marked Suspended, but provider is now active with active institution, enable it
                    if ($cred->status == 'Suspended' && !$was_active && $con_prov->is_active &&
                        $cred->institution->is_active) {
                        $cred_updates['status'] = 'Enabled';
                    }
                    // cred status is marked Incomplete
                    if ($cred->status == 'Incomplete') {
                        // if provider and institution are active, enable it, otherwise mark suspended
                        $cred_updates['status'] = ($con_prov->is_active && $cred->institution->is_active) ?
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
