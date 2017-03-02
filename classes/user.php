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
 */

/**
 * @property int $IdUser
 * @property int $IdSubscription
 * @property int $ContactsPerPage
 * @property int $CreatedTime
 * @property int $LastLogin
 * @property int $LastLoginNow
 * @property int $LoginsCount
 * @property string $Language
 * @property int $DefaultTimeZone
 * @property int $TimeFormat
 * @property string $DateFormat
 * @property string $Question1
 * @property string $Question2
 * @property string $Answer1
 * @property string $Answer2
 * @property string $Capa
 * @property bool $DesktopNotifications
 * @property bool $EnableOpenPgp
 * @property bool $AutosignOutgoingEmails
 * @property mixed $CustomFields
 * @property bool $SipEnable
 * @property string $SipImpi
 * @property string $SipPassword
 * 
 * @property bool $FilesEnable
 * @property string $EmailNotification
 * @property string $PasswordResetHash
 *
 * @package Classes
 * @subpackage Users
 */
class CUser extends \Aurora\System\EAV\Entity
{
	/**
	 * @var CSubscription
	 */
	private $oSubCache;

	/**
	 * Creates a new instance of the object.
	 * 
	 * @return void
	 */
	public function __construct($sModule)
	{
		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		
		$this->aStaticMap = array(
			'Name'								=> array('string', ''),
			'PublicId'							=> array('string', ''),
			'IdTenant'							=> array('int', 0),
			'IsDisabled'						=> array('bool', false),
			'IdSubscription'					=> array('int', 0), //'id_subscription'),
			'Role'								=> array('int', \EUserRole::NormalUser),

			'ContactsPerPage'					=> array('int', 0), //'contacts_per_page'),

			'CreatedTime'						=> array('string', ''), //'created_time'), //must be datetime
			'LastLogin'							=> array('string', ''), //'last_login', true, false), //must be datetime
			'LastLoginNow'						=> array('string', ''), //'last_login_now', true, false), //must be datetime
			'LoginsCount'						=> array('int', 0), //'logins_count', true, false),

			'Language'							=> array('string', $oModuleManager->getModuleConfigValue('Core', 'Language')),

			'DefaultTimeZone'					=> array('int', 0), //'def_timezone'),
			'TimeFormat'						=> array('int', $oModuleManager->getModuleConfigValue('Core', 'TimeFormat')),
			'DateFormat'						=> array('string', $oModuleManager->getModuleConfigValue('Core', 'DateFormat')),

			'Question1'							=> array('string', ''), //'question_1'),
			'Question2'							=> array('string', ''), //'question_2'),
			'Answer1'							=> array('string', ''), //'answer_1'),
			'Answer2'							=> array('string', ''), //'answer_2'),

			'SipEnable'							=> array('bool', true), //'sip_enable'),
			'SipImpi'							=> array('string', ''), //'sip_impi'),
			'SipPassword'						=> array('string', ''), //'sip_password'), //must be password
			
			'DesktopNotifications'				=> array('bool', false), //'desktop_notifications'),

			'EnableOpenPgp'						=> array('bool', true), //'enable_open_pgp'),
			'AutosignOutgoingEmails'			=> array('bool', true), //'autosign_outgoing_emails'),

			'Capa'								=> array('string', ''), //'capa'),
			'CustomFields'						=> array('string', ''), //'custom_fields'), //must be serialize type

			'FilesEnable'						=> array('bool', true), //'files_enable'),
			
			'EmailNotification'					=> array('string', ''), //'email_notification'),
			
			'PasswordResetHash'					=> array('string', ''), //'password_reset_hash')
		);

		$this->oSubCache = null;

		parent::__construct($sModule);

//		$this->SetUpper(array('Capa'));
	}
	
	/**
	 * @ignore
	 * @todo not used
	 * 
	 * @param string $sCapaName
	 *
	 * @return bool
	 */
	public function getCapa($sCapaName)
	{
		return true;
		// TODO

		if (!\Aurora\System\Api::GetConf('capa', false) || '' === $this->Capa ||
			0 === $this->IdSubscription)
		{
			return true;
		}

		$sCapaName = preg_replace('/[^A-Z0-9_=]/', '', strtoupper($sCapaName));

		$aCapa = explode(' ', $this->Capa);

		return in_array($sCapaName, $aCapa);
	}

	/**
	 * @ignore
	 * @todo not used
	 * 
	 * @return void
	 */
	public function allowAllCapas()
	{
		$this->Capa = '';
	}

	/**
	 * @ignore
	 * @todo not used
	 * 
	 * @return void
	 */
	public function removeAllCapas()
	{
		$this->Capa = ECapa::NO;
	}

	/**
	 * @ignore
	 * @todo not used
	 * 
	 * @param CTenant $oTenant
	 * @param string $sCapaName
	 * @param bool $bValue
	 *
	 * @return bool
	 */
	public function setCapa($oTenant, $sCapaName, $bValue)
	{
		if (!\Aurora\System\Api::GetConf('capa', false) || !$oTenant)
		{
			return true;
		}

		return true;
	}

	/**
	 * Checks if the user has only valid data.
	 * 
	 * @return bool
	 */
	public function validate()
	{
		switch (true)
		{
			case false:
				throw new \CApiValidationException(Errs::Validation_FieldIsEmpty, null, array(
					'{{ClassName}}' => 'CUser', '{{ClassField}}' => 'Error'));
		}

		return true;
	}
	
	public function toResponseArray()
	{
		return array(
			'Name' => $this->Name,
			'PublicId' => $this->PublicId,
			'Role' => $this->Role
		);
	}
}
