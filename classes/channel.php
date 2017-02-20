<?php
/**
 * @copyright Copyright (c) 2016, Afterlogic Corp.
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
 * @property int $IdChannel
 * @property string $Login
 * @property string $Password
 * @property string $Description
 *
 * @package Classes
 * @subpackage Channels
 */
class CChannel extends CEntity
{
	protected $aStaticMap = array(
		'Login'			=> array('string', ''),
		'Password'		=> array('string', ''),
		'Description'	=> array('string', '')
	);	

	/**
	 * @throws CApiValidationException
	 *
	 * @return bool
	 */
	public function validate()
	{
		switch (true)
		{
			case !api_Validate::IsValidLogin($this->Login):
				throw new CApiValidationException(Errs::Validation_InvalidTenantName);
			case api_Validate::IsEmpty($this->Login):
				throw new CApiValidationException(Errs::Validation_FieldIsEmpty, null, array(
					'{{ClassName}}' => 'CChannel', '{{ClassField}}' => 'Login'));
		}

		return true;
	}
}
