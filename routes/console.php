<?php

use Illuminate\Support\Facades\Schedule;
use App\Models\Consortium;

/*
 *-------------------------------
 * CC-Plus schedule configuration
 *-------------------------------
 */

/* Harvester runs every 10 minutes to harvest all jobs in the ccplus_global.jobs table (which holds
 * jobs across all defined instances.) Schedule is 10 minutes since jobs can be added manually via
 * the U/I as well overnight. Can be set to everyHour, everyFiveMinutes, etc.
 * (see Laravel Artisan command scheduling docs)
 */
Schedule::command('ccplus:harvester')->runInBackground()->everyTenMinutes()->withoutOverlapping()
                                     ->appendOutputTo('/var/log/ccplus/harvests.log');

/*
 * Loop across all active consortium instances
 */
$consortia = Consortium::where('is_active',1)->get(['id','ccp_key']);
if ($consortia) {
    $dorder = " d"; // used by reportprocessor (set to "" for FIFO)
    foreach ($consortia as $con) {
        /*
         * QueueLoader runs once/day for each active consortium instance to automatically create harvest
         * jobs (in the ccplus_global::jobs table) based on day_of_month settings for all active/complete
         * credentials set for the platform(s) to run that day.
         */
        Schedule::command('ccplus:queueloader ', [$con->id])->daily();
        /*
         * ReportProcessor runs every 10-minutes for each active consortium instance to automatically scan
         * the 'unprocessed' folder (/usr/local/stats_reports/<conso-ID>/0_unprocessed/) for harvested JSON
         * to be processed and stored in consortium XX_report_data tables.
         * (dorder set to " d" uses display order (from Report class) to cause processor to process based
         * on report (PR > DR > TR > IR).
         */
        Schedule::command('ccplus:reportprocessor',[$con->id, $dorder, "RP_".$con->ccp_key])->runInBackground()
                ->everyTenMinutes()->withoutOverlapping()->appendOutputTo('/var/log/ccplus/harvests.log');
    }
}
