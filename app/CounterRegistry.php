<?php

namespace App;
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
  protected $attributes = ['global_id' => null, 'release' => null, 'connectors' => '{}', 'service_url' => null,
                           'notifications_url' => null];
  protected $fillable = ['id', 'global_id', 'connectors', 'service_url', 'notifications_url'];
  protected $casts = ['id'=>'integer', 'global_id'=>'integer', 'connectors' => 'array'];

  public function globalProv()
  {
      return $this->belongsTo('App\GlobalProvider', 'global_id');
  }
}
