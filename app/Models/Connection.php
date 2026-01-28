<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Connection extends Model
{
   // use HasEncryptedAttributes;

  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'consodb';
    protected $table = 'connections';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $attributes = ['is_active' => 1, 'global_id' => null, 'inst_id' => 1, 'group_id' => null,
                             'selected_release' => null];
    protected $fillable = ['is_active', 'global_id', 'inst_id', 'group_id', 'selected_release' ];
    protected $casts = ['id'=>'integer', 'is_active'=>'integer', 'inst_id'=>'integer', 'global_id' => 'integer',
                        'group_id' => 'integer'];

  /**
   * The attributes that should be encrypted on save (password is already hashed)
   *
   * @var array
   */

  //NOTE:: needs to account for groupAdmin
    public function canManage()
    {
        // ServerAdmin can manage any institution
        if (auth()->user()->isServerAdmin()) {
            return true;
        }
        return (auth()->user()->hasRole("Admin", $this->inst_id) ||
                auth()->user()->hasRole("Admin", null, $this->group_id));
    }

    public function globalProv()
    {
        return $this->belongsTo('App\Models\GlobalProvider', 'global_id');
    }

    public function institution()
    {
        return $this->belongsTo('App\Models\Institution', 'inst_id');
    }

    public function group()
    {
        return $this->belongsTo('App\Models\InstitutionGroup', 'group_id');
    }

    // Returns related institution IDs as an array = either [$this->inst_id]
    // or  [group->institutions->id]
    public function institutionIds()
    {
        return (is_null($this->group_id)) ? array($this->inst_id) 
                                          : $this->group->institutions->pluck('id')->toArray();
    }

    public function reports()
    {
        $_db = config('database.connections.consodb.database');
        return $this
          ->belongsToMany('App\Models\Report', $_db . '.connection_report')
          ->withTimestamps();
    }

    public function default_release()
    {
        $return_value = "";
        if (!is_null($this->global_id)) {
            $return_value = $this->globalProv->default_release();
        }
        return $return_value;
    }

    public function data()
    {
        if (!is_null($this->global_id)) {
            $globalProv = $this->globalProv()->first()->toArray();
            return array_merge($this->globalProv()->first()->toArray(),$this->toArray());
        } else {
            return $this->toArray();
        }
    }
}
