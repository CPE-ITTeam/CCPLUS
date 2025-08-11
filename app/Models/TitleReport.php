<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// class TitleReport extends Model
class TitleReport extends Report
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'consodb';
    protected $table = 'tr_report_data';
    public $timestamps = false;

    /**
     * Mass assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'title_id', 'prov_id', 'publisher_id', 'plat_id', 'inst_id', 'yearmon', 'datatype_id',
        'sectiontype_id', 'yop', 'accesstype_id', 'accessmethod_id', 'total_item_investigations', 'total_item_requests',
        'unique_item_investigations', 'unique_item_requests', 'unique_title_investigations', 'unique_title_requests',
        'limit_exceeded', 'not_license'
    ];

    public function title()
    {
        return $this->belongsTo('App\Models\Title', 'title_id');
    }

    public function provider()
    {
        return $this->belongsTo('App\Models\GlobalProvider', 'prov_id');
    }

    public function publisher()
    {
        return $this->belongsTo('App\Models\Publisher', 'publisher_id');
    }

    public function platform()
    {
        return $this->belongsTo('App\Models\Platform', 'plat_id');
    }

    public function institution()
    {
        return $this->belongsTo('App\Models\Institution', 'inst_id');
    }

    public function accessMethod()
    {
        return $this->belongsTo('App\Models\AccessMethod', 'accessmethod_id');
    }

    public function accessType()
    {
        return $this->belongsTo('App\Models\AccessType', 'accesstype_id');
    }

    public function dataType()
    {
        return $this->belongsTo('App\Models\DataType', 'datatype_id');
    }

    public function sectionType()
    {
        return $this->belongsTo('App\Models\SectionType', 'sectiontype_id');
    }
}
