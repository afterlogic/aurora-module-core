<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Enums;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2022, Afterlogic Corp.
 */
class ErrorCodes
{
	const ChannelDoesNotExist = 1001;
	const TenantAlreadyExists = 1002;
	const GroupAlreadyExists = 1003;
	const MySqlConfigError = 1004;

	/**
	 * @var array
	 */
	protected $aConsts = [
		'ChannelDoesNotExist' => self::ChannelDoesNotExist,
		'TenantAlreadyExists' => self::TenantAlreadyExists,
		'GroupAlreadyExists' => self::GroupAlreadyExists,
	];
}
