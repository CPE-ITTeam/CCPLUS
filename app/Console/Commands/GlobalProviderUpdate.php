<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use DB;
use App\Models\Report;
use App\Models\GlobalProvider;
use App\Models\ConnectionField;
use App\Models\CounterRegistry;

class GlobalProviderUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ccplus:global-provider-update';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Global Platform definitions using settings from the Project COUNTER API';
    private $client;
    private $options;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        global $client, $options;

        parent::__construct();
        $client = new Client();   //GuzzleHttp\Client
        $options = [
            'headers' => ['User-Agent' => "Mozilla/5.0 (CC-Plus custom) Firefox/80.0"]
        ];

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        global $client, $options;

        // Get Platform section from the API root
        $json = self::requestURI("https://registry.projectcounter.org/api/v1/platform/?format=json");
        if ( !is_array($json)) {
            $this->error("Error - Array expected from Registry Platform request... something else came back!");
            return false;
        }
        if (count($json) == 0) {
            $this->error("No Platform data returned from Registry Platform request!");
            return false;
        }

        // Pull master reports
        $master_reports = Report::where('parent_id', '=', 0)->get(['id','name']);
        // Pull connection fields and map a static array to what the API sends back
        $fields = ConnectionField::get();
        $api_connectors = array('customer_id_info'      => array('field' => 'customer_id', 'id' => null),
                                'requestor_id_required' => array('field' => 'requestor_id', 'id' => null),
                                'api_key_required'      => array('field' => 'api_key', 'id' => null)
                               );
        foreach ($api_connectors as $key => $cnx) {
            $fld = $fields->where('name', $cnx['field'])->first();
            if (!$fld) continue;
            $api_connectors[$key]['id'] = $fld->id;
        }

        // We're going to keep track of the provider records updated
        $updated_ids = array();

        // Walk the array by-platform
        foreach ($json as $platform) {

            if (is_null($platform->id)) continue;

            // Get reports available
            $reportIds = $master_reports->whereIn('name',array_column($platform->reports,'report_id'))->pluck('id')->toArray();

            // Pull the Services for the platform from the API
            $service_details = array();
            foreach ($platform->sushi_services as $svc) {
                $cr = $svc->counter_release;
                if (strlen($cr) > 0 && strlen($svc->url) > 0) {
                    $details = self::requestURI($svc->url);
                    if (is_object($details)) {
                        if (isset($details->url)) {
                            if (strlen(trim($details->url)) > 0) {
                                $service_details[$cr] = $details;
                            }
                        }
                    }
                }
            }
            if (count($service_details) == 0) {
                $this->error("Cannot find sushi service details and URL for: " . $platform->name . " .. skipping ..");
                continue;
            }

            // Create or get GlobalProvider with a matching registry_id
            $global_provider = GlobalProvider::where('registry_id',$platform->id)->orWhere('name',$platform->name)->first();
            if ($global_provider) {
                if (!$global_provider->refreshable) {
                    $this->error("Registry refresh is disallowed for " . $global_provider->name . " .. skipping ..");
                    continue;
                }
                $global_provider->refresh_result = 'success';
            } else {
                $global_provider = new GlobalProvider;
                $global_provider->refresh_result = 'new';
            }
            $global_provider->registry_id = $platform->id;
            $global_provider->name = $platform->name;
            $global_provider->content_provider = $platform->content_provider_name;
            $global_provider->abbrev = $platform->abbrev;
            $global_provider->master_reports = $reportIds;
            $global_provider->save();
            $global_provider->load('registries');

            // Update or create CC+ counter_registries records for each release defined in service details
            foreach ( $service_details as $release => $details ) {
                $registry = $global_provider->registries->where('release',$release)->first();
                // create a new entry if it doesn't exist
                if (!$registry) {
                    $registry = new CounterRegistry;
                    $registry->global_id = $global_provider->id;
                    $registry->release = $release;
                }
                $registry->service_url = $details->url;
                $registry->notifications_url = $details->notifications_url;

                // Get connection fields (for now, assumes customer_id is always required)
                $connectors = array();
                foreach ($api_connectors as $key => $cnx) {
                    if ($key == 'customer_id_info' || $details->{$key}) {
                        $connectors[] = $cnx['id'];
                    }
                }
                $registry->connectors = $connectors;
                $registry->save();
            }

            $updated_ids[] = $global_provider->id;
        }

        // Update any existing providers that are marked "refreshable", but are missing from the updated set
        // These need to be set to refresh_result='failed' since they are missing from the current COUNTER set
        // (Admins can reset the provider as "no refresh", and this won't happen again).
        GlobalProvider::where('refreshable',1)->whereNotIn('id',$updated_ids)->update(['refresh_result' => 'failed']);
    }

    private function requestURI($uri)
    {
      global $client, $options;

      // Get Platform section from the API root
      try {
          $result = $client->request('GET', $uri, $options);
      } catch (\Exception $e) {
          $this->error("API request Failed for: " . $uri);
          $this->error("Error was: " . $e->getMessage());
          return 0;
      }
      // Get JSON from the response and do basic error checks
      $json = json_decode($result->getBody());
      if (json_last_error() !== JSON_ERROR_NONE) {
          $this->error("Error decoding JSON returned from : " . $uri);
          return false;
      }
      return $json;
    }
}
