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
        'Email',
        'IpAddress',
        'ErrorLoginsCount',
        'Time',
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

		// 'Email'	=> array('string', ''),
        // 'IpAddress'	=> array('string', ''),
        // 'ErrorLoginsCount' 	=> array('int', 0),
        // 'Time' 	=> array('int', 0),
}
