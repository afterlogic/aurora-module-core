<?php

namespace Aurora\Modules\Core\Models;

use \Aurora\System\Classes\Model;

class UserBlock extends Model
{
    protected $table = 'core_user_blocks';
    protected $moduleName = 'Core';

    protected $foreignModel = User::class;
	protected $foreignModelIdColumn = 'UserId'; // Column that refers to an external table

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'UserId',
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
}
