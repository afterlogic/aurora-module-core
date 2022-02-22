<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Models;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 */

use \Aurora\System\Classes\Model;

class GroupContact extends Model
{
    public $table = 'core_group_user';
	protected $foreignModel = Group::class;
	protected $foreignModelIdColumn = 'GroupId'; // Column that refers to an external table

	protected $fillable = [
		'Id',
		'GroupId',
		'UserId'
	];
}
