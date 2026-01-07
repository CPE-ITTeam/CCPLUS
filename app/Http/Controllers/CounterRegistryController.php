<?php

namespace App\Http\Controllers;

use App\GlobalProvider;
use App\ConnectionField;
use App\CounterRegistry;
use App\Provider;
use App\Report;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class CounterRegistryController extends Controller
{
    private $masterReports;
    private $allConnectors;
    private $client;
    private $options;

    /**
     * Pull and return a fresh copy of the registry data for a given provider
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function registryRefresh(Request $request)
    {
        global $client, $options, $masterReports, $allConnectors;

        // Set Globals
        $client = new Client();   //GuzzleHttp\Client
        $options = [
            'headers' => ['User-Agent' => "Mozilla/5.0 (CC-Plus custom) Firefox/80.0"]
        ];

        // Validate form inputs
        $this->validate($request, [ 'id' => 'required' ]);
        $input = $request->all();

        $full_refresh = false;
        $no_refresh = array();    // track names of platforms with refresh disabled (skipped)

        // Get all current platforms with a registry_id value
        $all_globals = GlobalProvider::whereNotNull('registry_id')->get();

        if ($input['id'] == "ALL") {
            $global_providers = $all_globals->where('refreshable',1);
            $no_refresh = $all_globals->where('refreshable',0)->pluck('name')->toArray();
            $is_dialog = 0;
            $full_refresh = true;
        } else {
            $is_dialog = json_decode($request->input('dialog'));
            $global_provider_ids = json_decode($request->input('id'));
            if (!is_array($global_provider_ids)) {
                return response()->json(['result' => false, 'msg' => "Refresh Request Failed - Invalid Input!"]);
            }
            $global_providers = $all_globals->where('refreshable',1)->whereIn('id', $global_provider_ids);

            // If nothing requested is refreshable, report the error and exit
            if ($global_providers->count() == 0) {
                return response()->json(['result'=>false, 'msg'=>"The requested platform(s) are marked not refreshable"]);
            }
            $no_refresh = $all_globals->where('refreshable',0)->whereIn('id', $global_provider_ids)->pluck('name')->toArray();
        }
        $gpCount = count($global_providers);
        $ids_to_update = $global_providers->pluck('registry_id')->toArray();

        // Set URL - either just one platform, or all of them
        if ($gpCount == 1) {
            $_gp = $global_providers->first();
            $_url = "https://registry.countermetrics.org/api/v1/platform/" . $_gp->registry_id . "/?format=json";
        } else {
            $_url = "https://registry.countermetrics.org/api/v1/platform/?format=json";
        }
        // Make the request and validate as JSON
        $json = $this->requestURI($_url);
        if ($json == "Request Failed") {
            return response()->json(['result'=>false, 'msg'=>"Unable to retrieve COUNTER registry details: "]);
        }
        if ($json == "JSON Failed") {
            return response()->json(['result'=>false, 'msg'=>"Error decoding JSON returned by registry!"]);
        }

        // Deal with JSON as a single Object for one entry, or as an array for multiple platforms
        $platform_records = null;
        if (is_array($json)) {
            $platform_records = $json;
            if (count($platform_records) == 0) {
                return response()->json(['result'=>false, 'msg'=>"No Platform data returned from Registry Platform request!"]);
            }
        } else if (is_object($json)) {
            $platform_records = [$json];
        } else {
            return response()->json(['result'=>false, 'msg'=>"Error getting registry details - invalid datatype received!"]);
        }

        // Pull master reports and connection fields regardless of JSON flag
        $this->getMasterReports();
        $this->getConnectionFields();

        // Setup a static array to conect what the COUNTER API sends back to conbnection_fields
        $api_connectors = array('customer_id_info'      => array('field' => 'customer_id', 'id' => null),
                                'requestor_id_required' => array('field' => 'requestor_id', 'id' => null),
                                'api_key_required'      => array('field' => 'api_key', 'id' => null)
                               );
        foreach ($api_connectors as $key => $cnx) {
            $fld = $allConnectors->where('name', $cnx['field'])->first();
            if (!$fld) continue;
            $api_connectors[$key]['id'] = $fld->id;
        }

        // Setup a static array for error handling and reporting
        $errorData = array( array('result' => 'success', 'msg' => "Platform successfully refreshed"),
                            array('result' => 'failed', 'msg' => "Registry Error - sushi services improperly defined"),
                            array('result' => 'failed', 'msg' => "Registry Error - COUNTER API connection details invalid"),
                            array('result' => 'failed', 'msg' => "No match for CC+ Registry_ID in Registry")
                          );

        // Loop across the platforms in the JSON and build an array of output to return
        $success_count = 0;
        $return_data = array();
        $updated_ids = array();   // track global_providers actually updated
        $no_registryID = array(); // track names of platforms with no registry_id (skipped)
        $no_url = array();    // track names of platforms with refresh disabled (skipped)
        $new_platforms = array(); // track names of newly created platforms for summary
        foreach ($platform_records as $platform) {
            // Full refresh needs to include NEW platforms. Bulk/single refresh should skip them
            if (is_null($platform->id) || (!$full_refresh && !in_array($platform->id,$ids_to_update))) continue;

            // Look for a matching provider
            $newProvider = false;
            $global_provider = $all_globals->where('registry_id',$platform->id)->first();

            // Skip if platform not matched (and not full refresh) or if it is set not-refreshable
            if ($global_provider && $full_refresh) {
                if ($global_provider->refreshable == 0) continue;
            } else if (!$global_provider && !$full_refresh) {
                continue;
            }
            $orig_name = ($global_provider) ? $global_provider->name : "";
            $orig_isActive = ($global_provider) ? $global_provider->is_active : 0;

            // Scan through the sushi_services to be sure at least one has a url defined
            $hasUrl = false;
            foreach ($platform->sushi_services as $service) {
                $svc = ($is_dialog) ? $service : self::requestURI($service->url);
                if (strlen(trim($svc->url)) > 0) {
                    $hasUrl = true;
                    break;
                }
            }

            // if no URL defined, don't create a GlobalProvider, just flag it
            if (!$hasUrl) {
                if ($global_provider) { // clear service_url(s) for existing provider if sushi_services or url missing
                    CounterRegistry::where('global_id',$global_provider->id)->update(['service_url' => null]);
                } else {
                    if ($gpCount == 1) {
                        return response()->json(['result' => false, 'msg' => $errorData[1]['msg']]);
                    } else {
                        $return_data[] = array('error'=>1, 'id' => null, 'name' => $platform->name);
                    }
                    continue;
                }

            // Platform entry has a URL defined..
            } else {
                // If global provider w/ matching ID not found, look for a match on name, and
                // if none matches, create new entry
                if (!$global_provider) {
                    $global_provider = $all_globals->where('name',$platform->name)->first();
                    if ($global_provider) {
                        if ( $global_provider->refreshable == 0 ) continue;
                    } else {
                        // If doing ALL, add a new GlobalProvider
                        if ($full_refresh) {
                            $global_provider = new GlobalProvider;
                            $global_provider->refresh_result = 'new';
                            $new_platforms[] = $platform->name;
                            $newProvider = true;
                        // If not found, and we're doing more than one, skip this entry and continue
                        // (this is not an error - we pulled everything when count>1)
                        } else if ($gpCount>1) {
                            continue;
                        // this should not happen since the JSON was requested using the global_provider registryID value
                        } else {
                            return response()->json(['result'=>false, 'msg'=>"Error matching platform to registry!"]);
                        }
                    }
                }

                // Setup a basic return rec for this provider and do initial error checks
                $return_rec = array('error'=>0, 'id' => $global_provider->id, 'name' => $platform->name);

                // Pull the Services for the platform from the API
                $releases = array();
                $service_details = array();
                foreach ($platform->sushi_services as $service) {
                    $svc = ($is_dialog) ? $service : self::requestURI($service->url);
                    $cr = $svc->counter_release;
                    $releases[] = $cr;
                    if (is_object($svc)) {
                        $service_details[$cr] = $svc;
                    } else if (!$is_dialog) {
                        // Don't save a new provider that has bad data...
                        if ($global_provider->refresh_result != "new") {
                            $global_provider->refresh_result = "failed";
                            $global_provider->updated_at = now();
                            $global_provider->save();
                        }
                        if ($gpCount == 1) {
                            return response()->json(['result' => false, 'msg' => $errorData[2]['msg']]);
                        } else {
                            $return_rec['error'] = 2;
                            $return_data[] = $return_rec;
                            continue;
                        }
                    }
                }
                if (!$is_dialog && count($service_details) == 0) {
                    // Don't save a new provider that has bad data...
                    if ($global_provider->refresh_result != "new") {
                        $global_provider->refresh_result = "failed";
                        $global_provider->is_active = 0;
                        $global_provider->updated_at = now();
                        $global_provider->save();
                        if ($orig_isActive) {
                            $global_provider->appyToInstances();
                        }
                    }
                    if ($gpCount == 1) {
                        return response()->json(['result' => false, 'msg' => $errorData[1]['msg']]);
                    } else {
                        $return_rec['error'] = 1;
                        $return_data[] = $return_rec;
                        continue;
                    }
                }
                
                // Clean up (CC+) registry records
                $global_provider->load('registries');
                foreach ($global_provider->registries as $reg) {
                    $len = (isset($reg->service_url)) ? strlen(trim($reg->service_url)) : 0;
                    // delete (CC+) registries for releases no longer in the (COUNTER) registry
                    // or that have an empty/missing (skip if there is only one)
                    if ($global_provider->registries->count() > 1 && (!in_array($reg->release,$releases) || $len == 0)) {
                        $reg->delete();
                    }
                }
                $default_url = $global_provider->service_url();
                if (strlen($default_url) > 0) {
                    $hasUrl = true;
                }
            }

            // Update or create CC+ counter_registries records for each release defined in service details
            $connectors_changed = false;
            $reportIds = array();
            if (!$hasUrl) {
                $no_url[] = $global_provider->name;
                $global_provider->is_active = 0;
                $global_provider->refresh_result = 'partial';   // Incomplete
                $global_provider->updated_at = now();
                if (!$is_dialog) {
                    $global_provider->save();   
                }
            } else {
                // Get available platform reports
                $reportIds = $masterReports->whereIn('name',array_column($platform->reports,'report_id'))
                                        ->pluck('id')->toArray();
    
                // Set and save the global_provider
                $global_provider->registry_id = $platform->id;
                $global_provider->name = $platform->name;
                $global_provider->content_provider = $platform->content_provider_name;
                $global_provider->abbrev = $platform->abbrev;
                $global_provider->master_reports = $reportIds;
                $global_provider->refresh_result = ($newProvider) ? "new" : "success";
                $global_provider->updated_at = now();
                if (!$is_dialog) {
                    $global_provider->save();   
                }
                $old_connectors = $global_provider->connectors();
                foreach ( $service_details as $release => $details ) {
                    if (strlen(trim($details->url)) > 0) {
                        $registry = $global_provider->registries->where('release',$release)->first();
                        // create a new entry if it doesn't exist
                        if (!$registry) {
                            $registry = new CounterRegistry;
                            $registry->global_id = $global_provider->id;
                            $registry->release = $release;
                            // Remove release="0" entry, if defined, since we have a "real" one now
                            $zero_reg = $global_provider->registries->where('release',"0")->first();
                            if ($zero_reg) {
                                $zero_reg->delete();
                            }
                        }
                        $registry->service_url = trim($details->url);
                        $registry->notifications_url = trim($details->notifications_url);
    
                        // Get connection fields (for now, assumes customer_id is always required)
                        $connectors = array();
                        foreach ($api_connectors as $key => $cnx) {
                            if ($key == 'customer_id_info' || $details->{$key}) {
                                $connectors[] = $cnx['id'];
                            }
                        }
    
                        // The registry API doesn't know about CC+ extra_args. If set in the original Global, preserve it
                        foreach ($global_provider->connectionFields() as $cf) {
                            if ($cf['name'] == 'extra_args' && $cf['required']) {
                                $connectors[] = $cf['id'];
                                break;
                            }
                        }
                        $registry->connectors = $connectors;
                        $registry->save();
                    }
                }
                $global_provider->load('registries');
    
                // Check for changed connectors (in the default/max release) - if new ones are now required, we need
                // to update SushiSettings.
                $cur_connectors = $global_provider->connectors();
                $connectors_changed = ($cur_connectors != $old_connectors);
                $success_count++;
            }

            // Check/set dialog-specific default release
            if (!$is_dialog) {
                $_release = $global_provider->default_release();
                $global_provider->update(['selected_release' => $_release]);
            }

            // If changes implicate consortia-provider settings, apply to all instances
            if ($global_provider->name != $orig_name || $global_provider->is_active!=$orig_isActive || $connectors_changed) {
                $global_provider->appyToInstances();
            }

            // Setup return data
            $return_rec = $global_provider->toArray();
            $return_rec['registries'] = array();
            foreach ($global_provider->registries->sortBy('release') as $reg) {
                $data = $reg->toArray();
                $data['connector_state'] = $this->connectorState($reg->connectors);
                $return_rec['registries'][] = $data;
            }
            $return_rec['release'] = $global_provider->default_release();
            $return_rec['service_url'] = $global_provider->service_url();
            $return_rec['status'] = ($global_provider->is_active) ? "Active" : "Inactive";
            $return_rec['report_state'] = $this->reportState($reportIds);
            $return_rec['updated'] = date("Y-m-d H:i", strtotime($global_provider->updated_at));
            $updated_ids[] = $global_provider->id;
            $return_rec['error'] = 0;
            $return_data[] = $return_rec;
        }

        if (count($updated_ids) == 0) {
            $_msg = ($gpCount>1) ? "No Records updated" : "Refresh failed";
            return response()->json(['result' => false, 'msg' => $_msg]);
        }

        // Providers that are in CC+ as a GlobalProvider, but missing from updated_ids have been orphaned by COUNTER.
        // Mark these providers' refresh_result as "orphan" (the U/I will label as Deprecated)
        if (!$is_dialog && count($updated_ids) > 0) {
            $orphans = $global_providers->where('is_active',1)->whereNotIn('id',$updated_ids)->all();
            foreach ($orphans as $gp) {
                $gp->refresh_result = 'orphan';
                $gp->is_active = 0;
                if (is_null($gp->registry_id) || $gp->registry_id == '') {
                    $no_registryID[] = $gp->name;
                } else {
                    $return_data[] = array('error' => 3, 'id' => $gp->id, 'name' => $gp->name);
                }
                $gp->save();
            }
        }

        // Build a summary HTML blob if we handled more than one provider ID
        $summary_html = "";
        if ($gpCount > 1) {
            $summary_html = ($success_count>0) ? $success_count . " Platforms successfully refreshed" : "";
            if (count($no_refresh) > 0) {
              $summary_html .= ($summary_html == "") ? "" : "<br /><hr>";
              $summary_html .= "<center>" . count($no_refresh) . " Platforms Skipped (Refresh Disabled)</center>";
            }
            if (count($new_platforms) > 0) {
                $summary_html .= ($summary_html == "") ? "" : "<br /><hr>";
                $summary_html .= "<center><strong><u>New Platforms Added:</u></strong></center><br />";
                foreach ($new_platforms as $name) {
                    $summary_html .= $name . "<br />";
                }
            }
            if (count($no_registryID) > 0) {
              $summary_html .= ($summary_html == "") ? "" : "<br /><hr>";
              $summary_html .= "<center><strong><u>Platforms Skipped (No Registry ID):</u></strong></center><br />";
              foreach ($no_registryID as $name) {
                  $summary_html .= $name . "<br />";
              }
            }
            if (count($no_url) > 0) {
                $summary_html .= ($summary_html == "") ? "" : "<br /><hr>";
                $summary_html .= "<center><strong><u>Platforms with no service URL:</u></strong></center><br />";
                foreach ($no_url as $name) {
                    $summary_html .= $name . "<br />";
                }
            }
            for ($eid=1; $eid<count($errorData); $eid++) {
                $error = $errorData[$eid];
                $matches = array_filter($return_data, function( $rec) use($eid) {
                    return $rec['error'] == $eid;
                });
                if (count($matches) > 0) {
                    $summary_html .= "<br /><hr><center><strong><u>" . $error['msg'] . "</u>:</strong></center><br />";
                    foreach ($matches as $rec) {
                        $summary_html .= $rec['name'] . "<br />";
                    }
                }
            }
        }
        return response()->json(['result' => true, 'providers' => $return_data, 'summary' => $summary_html]);
    }

    private function requestURI($uri) {
      global $client, $options;

      // Get specifc section from the API
      try {
          $result = $client->request('GET', $uri, $options);
      } catch (\Exception $e) {
          return "Request Failed";
      }
      // Get JSON from the response and do basic error checks
      $json = json_decode($result->getBody());
      if (json_last_error() !== JSON_ERROR_NONE) {
          return "JSON Failed";
      }
      return $json;
    }

    /**
     * Pull and re-order master reports and store in private global
     */
    private function getMasterReports() {
        global $masterReports;
        $masterReports = Report::where('revision',5)->where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);
    }

    /**
     * Pull and re-order master reports and store in private global
     */
    private function getConnectionFields() {
        global $allConnectors;
        $allConnectors = ConnectionField::get();
    }

    /**
     * Return an array of booleans for report-state from provider reports columns
     *
     * @param  Array  $reports
     * @return Array  $report-state
     */
    private function reportState($reports) {
        global $masterReports;
        $rpt_state = array();
        foreach ($masterReports as $rpt) {
            $rpt_state[$rpt->name] = (in_array($rpt->id, $reports)) ? true : false;
        }
        return $rpt_state;
    }

    /**
     * Return an array of booleans for connector-state from provider connectors columns
     *
     * @param  Array  $connectors
     * @return Array  $connector-state
     */
    private function connectorState($connectors) {
        global $allConnectors;
        $cnx_state = array();
        foreach ($allConnectors as $fld) {
            $cnx_state[$fld->name] = (in_array($fld->id, $connectors)) ? true : false;
        }
        return $cnx_state;
    }

  }
