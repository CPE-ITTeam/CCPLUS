<?php

namespace App\Http\Controllers;

use DB;
use App\Models\Consortium;
use App\Models\SavedReport;
use App\Models\Report;
use App\Models\ReportField;
use App\Models\ReportFilter;
use App\Models\Connection;
use App\Models\Platform;
use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\HarvestLog;
use App\Models\GlobalProvider;
use App\Models\Alert;
use App\Models\SystemAlert;
use Illuminate\Http\Request;

class SavedReportController extends Controller
{
    /**
     * Return a listing of the resource.
     *
     * @return JSON
     */
    public function index()
    {
        // Return formatted array of saved user reports for the current user
        $report_data = SavedReport::formattedReports();

        return response()->json(['records' => $report_data], 200);
    }

    /**
     * Save a report configuration
     * --> IF $request includes a non-zero 'save_id', the request is treated as an update
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON array
     */
    public function store(Request $request)
    {
        $this->validate($request, ['date_range'  => 'required', 'report_id' => 'required', 'fields' => 'required']);

        // Need somewhere to save it...
        if (!isset($request->title) && !isset($request->save_id)) {
            return response()->json(['result' => false, 'msg' => 'A name or ID of a saved report is required.']);
        }
        $input = $request->all();
        $input_fields = $input['fields'];
        $exZeros = (isset($input['zeros'])) ? $input['zeros'] : 0;
        $rptonly = (isset($input['rpt_only'])) ? $input['rpt_only'] : 0;

        // Pull the model for report_id (points to presets in global table), and get all fields
        $_report = Report::findorFail($input['report_id']);
        $all_fields = ReportField::get();

        // Get the saved report config
        $type = ($input['save_id'] != 0) ? 'update' : 'create';
        if ($type == 'update') {
            $saved_report = SavedReport::where('user_id', auth()->id())->where('id', $input['save_id'])->first();
            if (!$saved_report) {
                return response()->json(['result' => false, 'msg' => 'Cannot access saved report data']);
            }

        // -or- create a new config
        } else {
            $saved_report = new SavedReport();
            $saved_report->title = $input['title'];
            $saved_report->user_id = auth()->id();
            $saved_report->report_id = $input['report_id'];
        }

        // Build output fields and filters strings based on active columns/filters
        $filters = '';
        $save_fields = '';
        foreach ($input_fields as $key => $inputField) {
            if ($inputField['active']) {
                $field = $all_fields->where('id',$inputField['id'])->first();
                if ($field) {
                    $save_fields .= ($save_fields == '') ? '' : ',';
                    $save_fields .= $field->id;
                    // Filters are saved as ID:VALUE or ID:[VALUE,VALUE,...], separated by "+"
                    if ($field->reportFilter) {
                        // Check for an array (i.e. Providers, institutions, and platforms)
                        if (is_array($inputField['limit'])) {
                            if (sizeof($inputField['limit']) > 0) {
                                $_filt = '';
                                foreach ($inputField['limit'] as $val) {
                                    $_filt .= ($_filt == '') ? $val : ',' . $val;
                                }
                                $filters .= ($filters == '') ? '' : '+';
                                $filters .= $field->reportFilter->id . ":";
                                $filters .= "[" . $_filt . "]";
                            }
                        } else {
                            if ($inputField['limit'] > 0) {
                                $filters .= ($filters == '') ? '' : '+';
                                $filters .= $field->reportFilter->id;
                                $filters .= ":" . $inputField['limit'];
                            }
                        }
                    }
                }
            }
        }

       // Save record with fields, filters and dates
        $saved_report->fields = $save_fields;
        $saved_report->filters = $filters;
        $saved_report->date_range = $input['date_range'];
        $saved_report->ym_from = $input['from'];
        $saved_report->ym_to = $input['to'];
        $saved_report->format = $input['format'];
        $saved_report->rpt_only = (is_null($rptonly)) ? 0 : $rptonly;
        $saved_report->exclude_zeros = (is_null($exZeros)) ? 0 : $exZeros;
        $saved_report->save();

        $return_data = SavedReport::formattedReports($saved_report->id);

        return response()->json(['result'=>true, 'record'=>$return_data, 'msg'=>'Configuration saved successfully']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\SavedReport  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $report = SavedReport::findOrFail($id);
        if (!$report->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $report->delete();
        return response()->json(['result' => true, 'msg' => 'Saved report successfully deleted']);
    }

}
