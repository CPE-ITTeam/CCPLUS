<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class DataTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

     // Make sure we're talking to the global database
      $_db = \Config::get('database.connections.globaldb.database');
      $table = $_db . ".datatypes";

     // Make sure table is empty
      if (DB::table($table)->get()->count() == 0) {
          DB::table($table)->insert([
                                ['id' => 1, 'name' => 'Article'],
                                ['id' => 2, 'name' => 'Audiovisual'],
                                ['id' => 3, 'name' => 'Book'],
                                ['id' => 4, 'name' => 'Book_Segment'],
                                ['id' => 5, 'name' => 'Conference'],
                                ['id' => 6, 'name' => 'Conference_Item'],
                                ['id' => 7, 'name' => 'Database_Aggregated'],
                                ['id' => 8, 'name' => 'Database_AI'],
                                ['id' => 9, 'name' => 'Database_Full'],
                                ['id' => 10, 'name' => 'Database_Full_Item'],
                                ['id' => 11, 'name' => 'Dataset'],
                                ['id' => 12, 'name' => 'Image'],
                                ['id' => 13, 'name' => 'Interactive_Resource'],
                                ['id' => 14, 'name' => 'Journal'],
                                ['id' => 15, 'name' => 'Multimedia'],
                                ['id' => 16, 'name' => 'News_Item'],
                                ['id' => 17, 'name' => 'Newspaper_or_Newsletter'],
                                ['id' => 18, 'name' => 'Other'],
                                ['id' => 19, 'name' => 'Patent'],
                                ['id' => 20, 'name' => 'Platform'],
                                ['id' => 21, 'name' => 'Reference_Item'],
                                ['id' => 22, 'name' => 'Reference_Work'],
                                ['id' => 23, 'name' => 'Report'],
                                ['id' => 24, 'name' => 'Software'],
                                ['id' => 25, 'name' => 'Sound'],
                                ['id' => 26, 'name' => 'Standard'],
                                ['id' => 27, 'name' => 'Thesis_or_Dissertation'],
                                ['id' => 28, 'name' => 'Unspecified'],
                                ['id' => 900, 'name' => 'Database'],
                                ['id' => 901, 'name' => 'Unknown'],
                             ]);
      }
    }
}
