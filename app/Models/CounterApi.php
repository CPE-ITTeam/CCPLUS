<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\Models\CcplusError;
use \ubfr\c5tools\JsonR5Report;
use \ubfr\c5tools\CheckResult;
use \ubfr\c5tools\ParseException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

class CounterApi extends Model
{
    private static $begin;
    private static $end;
    public $json;
    public $message;
    public $detail;
    public $error_code;
    public $severity;
    public $help_url;
    public $step;
    public $raw_datafile;

   /**
    * Class Constructor and setting methods
    */
    public function __construct($_begin, $_end)
    {
        self::$begin = $_begin;
        self::$end = $_end;
        $this->raw_datafile = "";
    }

   /**
    * Request the report
    *
    * @param string $uri
    * @return string $status   // Success , Fail,  Pending
    */
    public function request($uri)
    {
        $this->json = "";
        $this->message = "";
        $this->detail = "";
        $this->step = "";
        $this->error_code = 0;
        $this->severity = "";
        $this->help_url = "";
        $client = new Client();   //GuzzleHttp\Client

        // ASME (there may be others) checks the Agent and returns 403 if it doesn't like what it sees
        // Disable http_errors and tell Guzzle to return JSON (instead of embedding exceptions in a text "message")
        $options = [
            'http_errors' => false,
            'headers' => [ 'Accept' => 'application/json', 'User-Agent' => "Mozilla/5.0 (CC-Plus custom) Firefox/80.0" ]
        ];

       // Make the request and convert into JSON
        try {
            $result = $client->request('GET', $uri, $options);
        } catch (\Exception $e) {
            $this->step = "HTTP";
            $this->error_code = (property_exists($e, 'Code')) ? $e->getCode() : 9200;
            $this->severity = (property_exists($e, 'Severity')) ? strtoupper($e->getSeverity()) : "ERROR";
            $this->message = (property_exists($e, 'Message')) ? $e->getMessage() : "COUNTER API HTTP request failed, verify URL";
            $this->raw_datafile = "";
            return "Fail";
        }

       // Issue a warning if it looks like we'll run out of memory
        $mem_avail = intval(ini_get('memory_limit'));
        $body_len = strlen($result->getBody());
        $mem_needed = ($body_len * 8) + memory_get_usage(true);
        if ($mem_avail>0 && $mem_needed > ($mem_avail * 1024 * 1024)) {
            $mb_need = intval($mem_needed / (1024 * 1024));
            echo "Warning! Projected memory required: " . $mb_need . "Mb but only " . $mem_avail . "Mb available\n";
            echo "-------> Decoding this report may exhaust system memory (JSON len = $body_len)\n";
        }

       // Save raw data
        if ($this->raw_datafile != "") {
            if (File::put($this->raw_datafile, Crypt::encrypt(bzcompress($result->getBody(), 9), false)) === false) {
                echo "Failed to save raw data in: " . $this->raw_datafile;
                $this->raw_datafile = "";
                // ... OR ...
                // throw new \Exception("Failed to save raw data in: ".$this->raw_datafile);
            }
        }
//  This may be simpler ... IF... we outsource validation to the c5tools as (?)
//    $this->json = jsonReportFromBuffer($result->getBody());
        // Decode result body into $json, throw and log error if it fails
        $body = json_decode($result->getBody());

        // If result is an array, treat the first element in the array as JSON
        // (allows for an array of JSON objects, but only handles the 1st one)
        $this->json = (is_array($body)) ? $body[0] : $body;

        // Make sure $json is a proper object
        if (!is_object($this->json)) {
            $this->step = "API";
            $this->message = "Reported Dataset Formatting Invalid - JSON Expected, something else returned";
            $this->error_code = 9300;
            $this->detail = " returned data is not an Object that JSON can decode";

            // Not valid JSON, toss the raw datafile and clear the string
            try { unlink($this->raw_datafile); } catch (\Exception $e2) { }
            $this->raw_datafile = "";
            return "Fail";
        }
        unset($result);

       // Check JSON for exceptions
        $this->step = "API";
        if ($this->jsonHasExceptions($this->json)) {
           // Check for "queued" state response
            if ($this->error_code == 1011 || $this->error_code == 1020) {
                return "Pending";
            }

            // Override JSON severity with value from CC+ Error table if the code is found there.
            $known_error = CcplusError::with('severity')->where('id',$this->error_code)->first();
            // Set the return message and severity string based on the error table
            if ($known_error) {
                $this->message = $known_error->message;
                $this->severity = strtoupper($known_error->severity->name);
                // For severity_id= (0 or 10) (INFO or DEBUG) , return CcplusError->new_status
                if ($known_error->severity_id == 0 || $known_error->severity_id == 10) {
                    return $known_error->new_status;
                }
                return "Fail";
            // If code unrecognized, return 9400 (unknown error) and Fail.
            } else {
                $this->message = "Unknown exception code in response : ".$this->error_code;
                $this->error_code = 9400;
                return "Fail";
            }
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->detail = json_last_error_msg();
            $this->error_code = 9400;
            $this->message = "Error decoding request response : ";
            return "Fail";
        }
        return "Success";
    }

   /**
    * Build and return a COUNTER API request URI based on a cred and report
    *
    * @param Credential $cred
    * @param String $method
    * @param Report $report
    * @param String $release
    * @return string $request_uri
    */
    public function buildUri($cred, $method = "reports", $report, $release="")
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
        $uri_dates = "&begin_date=" . self::$begin . "&end_date=" . self::$end;
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

   /**
    * Validate the JSON from a COUNTER API against the COUNTER standard for Release-5
    *
    * @return boolean $result
    */
    public function validateJson()
    {
       // Confirm Report_Header is present and a valid object, store in $header
        if (! property_exists($this->json, 'Report_Header')) {
            throw new \Exception('Report_Header is missing');
        }
        $header = $this->json->Report_Header;
        if (! is_object($header)) {
            throw new \Exception('Report_Header must be an object, found ' .
                                 (is_array($header) ? 'an array' : 'a scalar'));
        }

       // Get release value; we're only handling Release 5
        if (! property_exists($header, 'Release')) {
            throw new \Exception("Could not determine COUNTER Release");
        }
        if (! is_scalar($header->Release)) {
            throw new \Exception('Report_Header.Release must be a scalar, found an ' .
                                 (is_array($header->Release) ? 'array' : 'object'));
        }
        $release = trim($header->Release);
        if ($release != "5" && $release != "5.1") {
            throw new \Exception("COUNTER Release '{$release}' invalid/unsupported");
        }

       // Make sure there are Report_Items to process
        if (!isset($this->json->Report_Items)) {
            throw new \Exception("COUNTER error: no Report_Items included in JSON response.");
        } else {
            if (sizeof($this->json->Report_Items) <= 0) {
                throw new \Exception("COUNTER error: Report_Items present but empty.", 9030);
            }
        }

       // Make sure there are Report_Items to process
        try {
            $report = new JsonR5Report($this->json);
            $checkResult = $report->getCheckResult();
        } catch (\Exception $e) {
            throw new \Exception("COUNTER error: c5tools CheckResult threw a validation error.");
            //NOTE:: this needs work... c5tools expects something different. For now, just throw simple exception
            // $checkResult = new CheckResult();
            // try {
            //     $checkResult->fatalError($e->getMessage());
            // } catch (ParseException $e) {
            //     // ignore
            // }
            // $message = $checkResult->asText();
            // throw new \Exception($message());
        }
       // If we modify Counter5Processor functions to handle the validated JSON
       // (to make it more O-O), we'll need to return $report instead of a boolean.
       // For now, we're just scanning for errors and not modifying the original data.
       // return $report;
        unset($report);
        return true;
    }

    /**
     * Scan the JSON from a COUNTER API request for exceptions and set returned details in
     * public class variables (sometimes exceptions are expressed differently!)
     *   * JSON Property named "Exception" takes precedence over "Exceptions".
     *   * If an array of exceptions is returned, only the first is reported.
     *     The raw JSON, however, will still hold all the returned data.
     *
     * @return boolean $has_exception
     */
    public function jsonHasExceptions($json)
    {
        $jException = null;
        // Standardize the JSON keys before looking for exceptions
        $ucwJson = json_decode(
                       json_encode(
                           array_combine(array_map('ucwords', array_keys( (array) $json)), (array) $json)
                       )
                   );

        // Code+Message at the root of returned JSON treated-as-Exception
        if (property_exists($ucwJson, 'Code') && property_exists($ucwJson, 'Message')) {
            $jException = $ucwJson;
        // Test for Exception(s) at the root of the JSON
        } elseif (property_exists($ucwJson, 'Exception') || property_exists($ucwJson, 'Exceptions')) {
            $ex_prop = (property_exists($ucwJson, 'Exception')) ? "Exception" : "Exceptions";
            if (is_array($ucwJson->$ex_prop)) {
                $jException = (count($ucwJson->$ex_prop)>0) ? $ucwJson->$ex_prop[0] : null;
            } else {
                $jException = $ucwJson->$ex_prop;
            }
        // Test for Exception(s) returned in the JSON header
        } elseif (property_exists($ucwJson, 'Report_Header')) {
            $header = $ucwJson->Report_Header;
            if (is_object($header)) {
                if (property_exists($header, 'Exception') || property_exists($header, 'Exceptions')) {
                    $ex_prop = (property_exists($header, 'Exception')) ? "Exception" : "Exceptions";
                    if (is_array($header->$ex_prop)) {
                        $jException = (count($header->$ex_prop)>0) ? $header->$ex_prop[0] : null;
                    } else {
                        $jException = $header->$ex_prop;
                    }
                }
            }
        }
        // Set class globals if found, and return true/false
        if (!is_null($jException)) {
            $this->saveExceptionData($jException);
            return true;
        }
        return false;
    }

    /**
     * Update class data with exception details
     * 
     * @param Exception $e
     */
    public function saveExceptionData($e)
    {
      $this->severity = "ERROR";
      $this->error_code = $e->Code;
      if (property_exists($e, 'Severity')) {
          $this->severity = strtoupper($e->Severity);
      }
      $this->message = $e->Message;
      $this->detail = (property_exists($e, 'Data')) ? $e->Data : "";
      $this->help_url = (property_exists($e, 'Help_URL')) ? $e->Help_URL : "";
    }
}
