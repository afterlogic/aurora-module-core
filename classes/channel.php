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
 * @property int $IdChannel
 * @property string $Login
 * @property string $Password
 * @property string $Description
 *
 * @package Classes
 * @subpackage Channels
 */
class CChannel extends \Aurora\System\EAV\Entity
{
	protected $aStaticMap = array(
		'Login'			=> array('string', ''),
		'Password'		=> array('string', ''),
		'Description'	=> array('string', '')
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
			case !\Aurora\System\Validate::IsValidLogin($this->Login):
				throw new \Aurora\System\Exceptions\ValidationException(Errs::Validation_InvalidTenantName);
			case \Aurora\System\Validate::IsEmpty($this->Login):
				throw new \Aurora\System\Exceptions\ValidationException(Errs::Validation_FieldIsEmpty, null, array(
					'{{ClassName}}' => 'CChannel', '{{ClassField}}' => 'Login'));
		}

		return true;
	}
}
