<?php

namespace Aurora\Modules\Core\Models;

use \Aurora\System\Classes\Model;

class Channel extends Model
{
    protected $table = 'core_channels';
    protected $moduleName = 'Core';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'Login',
        'Password',
        'Description',
        'Properties'
    ];

    public $timestamps = [
        'UpdatedAt',
        'CreatedAt',
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