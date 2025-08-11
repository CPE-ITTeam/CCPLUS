<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Title extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $connection = 'globaldb';
    protected $table = 'titles';

     /**
      * Mass assignable attributes.
      *
      * @var array
      */
    protected $fillable = [
         'Title', 'type', 'ISBN', 'ISSN', 'eISSN', 'DOI', 'PropID', 'URI', 'pub_date', 'article_version'
     ];

    public function titleReports()
    {
        return $this->hasMany('App\Models\TitleReport');
    }

    public function itemReports()
    {
        return $this->hasMany('App\Models\ItemReport');
    }
}
