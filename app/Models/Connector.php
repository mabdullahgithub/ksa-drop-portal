<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Connector extends Model
{
    protected $fillable = ['key', 'name', 'description', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];

    public function settings()
    {
        return $this->hasMany(ConnectorSetting::class);
    }
}
