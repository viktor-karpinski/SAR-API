<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
        'lat',
        'lon',
        'description',
        'from',
        'till',
        'status',
        'user_id',
    ];

    public function eventUsers()
    {
        return $this->hasMany(EventUser::class);
    }
}
