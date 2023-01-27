<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Models;

use Aurora\System\Classes\Model;

/**
 * The Core GroupUser class.
 * 
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 * 
 * @property int    $Id             Object primary key
 * @property int    $GroupId        Group ID
 * @property int    $UserId         User ID
 */
class GroupUser extends Model
{
    public $table = 'core_group_user';
	protected $foreignModel = Group::class;
	protected $foreignModelIdColumn = 'GroupId'; // Column that refers to an external table

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */	
	protected $fillable = [
		'Id',
		'GroupId',
		'UserId'
	];
}
