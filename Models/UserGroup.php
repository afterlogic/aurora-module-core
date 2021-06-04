<?php

namespace Aurora\Modules\Core\Models;

use \Aurora\System\Classes\Model;

class UserBlock extends Model
{
    protected $moduleName = 'Core';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
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



		// 'UrlIdentifier'	=> array('string', ''),
		// 'IdTenant'	=> array('string', '')
