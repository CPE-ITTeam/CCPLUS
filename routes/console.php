<?php

use Illuminate\Support\Facades\Schedule;
use App\Models\Consortium;

/*----------------------------------------------------------------------------------
 * CC-Plus schedule configuration
 * A single harvesting process starts every 10 minutes to harvest all jobs in the
 * ccplus_global.jobs table (which holds jobs across all defined instances.)
 * 
 * Two consortium-specific processes are also scheduled.
 *   - QueueLoader runs once/day and loads jobs based on platform day_of_month.
 *   - ReportProcessor runs every 10 minutes and looks for JSON report data files
 *     in the consortium's 0_unprocessed folder (by ID) to be processed and loaded
 *     into the XX_report_data tables.
 *----------------------------------------------------------------------------------
 */

/* Schedule the server-wide harvester */
Schedule::command('ccplus:harvester')->runInBackground()->everyTenMinutes()->withoutOverlapping()
                                     ->appendOutputTo('/var/log/ccplus/harvests.log');

/*
 * Schedule a queue loader and report processor for each active instance on the server
 * (dorder applies to reportprocessor as it walks downloaded JSON files)
 */
$consortia = Consortium::where('is_active',1)->get(['id','ccp_key']);
if ($consortia) {
    /* set dorder to " d" for display order (from Report class), or "" for FIFO */
    $dorder = " d";
    foreach ($consortia as $con) {
        Schedule::command('ccplus:queueloader ', [$con->id])->daily();
        Schedule::command('ccplus:reportprocessor',[$con->id, $dorder, "RP_".$con->ccp_key])->runInBackground()
                ->everyTenMinutes()->withoutOverlapping()->appendOutputTo('/var/log/ccplus/harvests.log');
    }
}
