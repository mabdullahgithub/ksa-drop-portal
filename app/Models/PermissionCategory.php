<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class PermissionCategory extends Model
{
    protected $fillable = [
        'name',
        'label',
        'description',
        'order',
    ];

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'category_id');
    }
}
