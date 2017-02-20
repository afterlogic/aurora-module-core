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
 * @property int $IdTenant
 * @property string $UrlIdentifier
 *
 * @package Classes
 * @subpackage UserGroups
 */
class CUserGroup extends AEntity
{
	public function __construct($sModule)
	{
		parent::__construct(get_class($this), $sModule);

		$this->setStaticMap(array(
			'UrlIdentifier'	=> array('string', ''),
			'IdTenant'	=> array('string', '')
		));
	}
	
	public static function createInstance($sModule = 'Core')
	{
		return new CUserGroup($sModule);
	}

	/**
	 * @throws CApiValidationException
	 *
	 * @return bool
	 */
	public function validate()
	{
		switch (true)
		{
			case api_Validate::IsEmpty($this->UrlIdentifier):
				throw new CApiValidationException(Errs::Validation_FieldIsEmpty, null, array(
					'{{ClassName}}' => 'CUserGroup', '{{ClassField}}' => 'UrlIdentifier'));
		}

		return true;
	}
}
