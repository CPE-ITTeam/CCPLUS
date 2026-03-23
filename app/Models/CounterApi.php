<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CounterApi extends Model
{
   /**
    * Build and return a COUNTER API request URI based on a cred and report
    *
    * @param String $begin  (YYYY-MM-DD)
    * @param String $end    (YYYY-MM-DD)
    * @param Credential $cred
    * @param Report $report
    * @param String $method
    * @param String $release
    * @return string $request_uri
    */
    public static function buildUri($begin, $end, $cred, $report, $method = "reports", $release="")
    {
       // Set URL based on release from the provider registr(ies); default to max if not found or release not set
        $registry = $cred->provider->registries->where('release',$release)->first();
        $service_url = ($registry) ? $registry->service_url : $cred->provider->service_url();
       // Begin setting up the URI by cleaning/standardizing the service_url string in the cred
        $_url = rtrim($service_url);                          // remove trailing whitespace
        $_url = preg_replace('/\/reports\/?$/i', '', $_url);  // take off any methods with any leading slashes
        $_url = preg_replace('/\/status\/?$/i', '', $_url);   //   "   "   "     "      "   "     "        "
        $_url = preg_replace('/\/members\/?$/i', '', $_url);  //   "   "   "     "      "   "     "        "
        $_uri = rtrim($_url, '/');                            // remove any remaining trailing slashes
        $request_uri = $_uri . '/' . $method;

       // Construct and execute the Request
        $uri_auth = "";
        $connectors = $cred->provider->connectionFields();
        foreach ($connectors as $cnx) {
            if ($cnx['required']) {
                $name = $cnx['name'];
                $argv = ($uri_auth == "") ? "?" : "&";
                if ($name == 'extra_args') {
                    // Tack on extra_args intact from the cred
                    $argv .= $cred->extra_args;
                } else {
                    $argv .= $name . "=" . urlencode( $cred->{$name} );
                }
                $uri_auth .= $argv;
            }
        }

        // If a platform value is set, add it
        if (!is_null($cred->provider->platform_parm)) {
            $uri_auth .= "&platform=" . $cred->provider->platform_parm;
        }

        // Return the URI if we're not building a report request
        if (!$report || $method != "reports") {
            return $request_uri . $uri_auth;
        }

       // Setup date range and attributes for the request
        $uri_dates = "&begin_date=" . $begin . "&end_date=" . $end;
        if ($report->name == "TR") {
            $uri_atts  = "&attributes_to_show=Access_Method%7CAccess_Type%7CYOP";
            $uri_atts .= ($release == "5") ? "%7CData_Type%7CSection_Type" : "";
        } elseif ($report->name == "DR") {
            $uri_atts  = "&attributes_to_show=Access_Method";
            $uri_atts .= ($release == "5") ? "%7CData_Type" : "";
        } elseif ($report->name == "PR") {
            $uri_atts  = "&attributes_to_show=Access_Method";
            $uri_atts .= ($release == "5") ? "%7CData_Type" : "";
        } elseif ($report->name == "IR") {
            $uri_atts  = "&Include_Parent_Details=True&attributes_to_show=Access_Method%7CAccess_Type%7CYOP";
            $uri_atts .= "%7CAuthors%7CPublication_Date%7CArticle_Version";
            $uri_atts .= ($release == "5") ? "%7CData_Type" : "";
        }

       // Construct URI for the request
        $request_uri .= '/' . strtolower($report->name) . $uri_auth . $uri_dates . $uri_atts;
        return $request_uri;
    }
}
