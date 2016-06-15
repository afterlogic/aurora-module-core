<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @property int $IdTenant
 * @property string $UrlIdentifier
 *
 * @package UserGroups
 * @subpackage Classes
 */
class CUserGroup extends APropertyBag
{
	public function __construct($sModule)
	{
		parent::__construct(get_class($this), $sModule);

		$this->__USE_TRIM_IN_STRINGS__ = true;
		
		$this->aStaticMap = array(
			'UrlIdentifier'	=> array('string', ''),
			'IdTenant'	=> array('string', '')
		);
		
		$this->SetDefaults();
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
