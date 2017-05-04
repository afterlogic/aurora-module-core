<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
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
