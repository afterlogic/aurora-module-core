<?php

namespace Aurora\Modules\Core\Models;

use \Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\Tenant;

class UserGroup extends Model
{
    protected $table = 'core_user_groups_legacy';
    protected $moduleName = 'Core';
    
    protected $foreignModel = Tenant::class;
	protected $foreignModelIdColumn = 'IdTenant'; // Column that refers to an external table

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'UrlIdentifier',
        'IdTenant',
        'Properties'
    ];

    /**
    * The attributes that should be hidden for arrays.
    *
    * @var array
    */
    protected $hidden = [
    ];

    protected $casts = [
        'Properties' => 'array',
    ];

    protected $attributes = [
    ];
}
