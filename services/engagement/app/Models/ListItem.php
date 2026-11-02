<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListItem extends Model
{
    protected $table = 'list_movie';

    protected $primaryKey = null;

    public $incrementing = false;

    protected $fillable = ['list_id', 'movie_id'];
}
