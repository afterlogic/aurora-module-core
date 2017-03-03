<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
 */

/**
 * @property int $IdTenant
 * @property string $UrlIdentifier
 *
 * @package Classes
 * @subpackage UserGroups
 */
class CUserGroup extends \Aurora\System\EAV\Entity
{
	protected $aStaticMap = array(
		'UrlIdentifier'	=> array('string', ''),
		'IdTenant'	=> array('string', '')
	);

	/**
	 * @throws \Aurora\System\Exceptions\ValidationException
	 *
	 * @return bool
	 */
	public function validate()
	{
		switch (true)
		{
			case \Aurora\System\Validate::IsEmpty($this->UrlIdentifier):
				throw new \Aurora\System\Exceptions\ValidationException(Errs::Validation_FieldIsEmpty, null, array(
					'{{ClassName}}' => 'CUserGroup', '{{ClassField}}' => 'UrlIdentifier'));
		}

		return true;
	}
}
