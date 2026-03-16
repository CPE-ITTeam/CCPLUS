<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CounterRegistry extends Model
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
  protected $connection = 'globaldb';
  protected $table = 'counter_registries';

/**
 * Mass assignable attributes.
 *
 * @var array
 */
  protected $attributes = ['global_id' => null, 'release' => null, 'datahost_id' => null, 'master_reports' => '{}',
                           'connectors' => '{}', 'service_url' => null, 'notifications_url' => null];
  protected $fillable = ['id', 'global_id', 'release', 'datahost_id', 'master_reports', 'connectors', 'service_url',
                         'notifications_url'];
  protected $casts = ['id'=>'integer', 'global_id'=>'integer', 'datahost_id'=>'integer', 'connectors'=>'array',
                      'master_reports'=>'array'];

  public function globalProv()
  {
      return $this->belongsTo('App\Models\GlobalProvider', 'global_id');
  }

  public function dataHost()
  {
      return $this->belongsTo('App\Models\DataHost', 'datahost_id');
  }
}
