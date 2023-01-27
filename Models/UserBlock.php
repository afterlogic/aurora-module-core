<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Models;

use Aurora\System\Classes\Model;

/**
 * The Core UserBlock class.
	* 
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 * 
 * @property int    $Id                Object primary key
 * @property int    $UserId            User ID of the user blocked
 * @property string $Email             Public ID of the user blocked
 * @property string $IpAddress         IP address recorded for the user block
 * @property int    $ErrorLoginsCount  Number of failed login attempts
 * @property int    $Time              Timestamp when user block added
 * @property array  $Properties        Custom properties for use by other modules
 */
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
