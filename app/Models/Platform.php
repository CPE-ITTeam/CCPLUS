<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'globaldb';
    protected $table = 'platforms';

  /**
   * Mass assignable attributes.
   *
   * @var array
   */
    protected $fillable = ['id', 'name'];

  /**
   * Methods for connections to reports
   */
    public function titleReports()
    {
        return $this->hasMany('App\Models\TitleReport');
    }

    public function databaseReports()
    {
        return $this->hasMany('App\Models\DatabaseReport');
    }

    public function platformReports()
    {
        return $this->hasMany('App\Models\PlatformReport');
    }

    public function itemReports()
    {
        return $this->hasMany('App\Models\ItemReport');
    }
}
