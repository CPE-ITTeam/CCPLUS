<?php

namespace App;
use App\Report;
use App\ConnectionField;
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

    public function sushiSettings()
    {
        return $this->hasMany('App\SushiSetting', 'prov_id');
    }

    public function titleReports()
    {
        return $this->hasMany('App\TitleReport');
    }

    public function databaseReports()
    {
        return $this->hasMany('App\DatabaseReport');
    }

    public function platformReports()
    {
        return $this->hasMany('App\PlatformReport');
    }

    public function itemReports()
    {
        return $this->hasMany('App\ItemReport');
    }

    public function consoProviders()
    {
        return $this->hasMany('App\Provider', 'global_id');
    }

    public function registries()
    {
        return $this->hasMany('App\CounterRegistry', 'global_id');
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
}
