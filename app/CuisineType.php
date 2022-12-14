<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CuisineType extends Model
{
    protected $fillable = ['name'];

    public function user(){
    	return $this->belongsTo('App/User', 'name');
    }
}
