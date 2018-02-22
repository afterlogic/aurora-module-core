<?php
/*
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 * 
 */

namespace Aurora\Modules\Core\Managers;

/**
 * Aurora\Modules\Core\Managers\Integrator class summary
 *
 * @package Integrator
 */
class Integrator extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * @type string
	 */
	const MOBILE_KEY = 'aurora-mobile';

	/**
	 * @type string
	 */
	const AUTH_HD_KEY = 'aurora-hd-auth';

	/**
	 * @type string
	 */
	const TOKEN_KEY = 'aurora-token';

	/**
	 * @type string
	 */
	const TOKEN_LAST_CODE = 'aurora-last-code';

	/**
	 * @type string
	 */
	const TOKEN_HD_THREAD_ID = 'aurora-hd-thread';

	/**
	 * @type string
	 */
	const TOKEN_HD_ACTIVATED = 'aurora-hd-activated';

	/**
	 * @type string
	 */
	const TOKEN_SKIP_MOBILE_CHECK = 'aurora-skip-mobile';

	/**
	 * @var $bCache bool
	 */
	private $bCache;

	private static $_instance = null;
	
	public static function createInstance()
	{
		return new self();
	}
	
	public static function getInstance()
	{
		if(is_null(self::$_instance))
		{
			self::$_instance = new self();		
		}
		
		return self::$_instance;
	}	

	/**
	 * Creates a new instance of the object.
	 *
	 * @param &$oManager
	 */
	public function __construct()
	{
		$this->bCache = false;
		parent::__construct(\Aurora\System\Api::GetModule('Core'));
	}
	
	/**
	 * @param string $sDir
	 * @param string $sType
	 *
	 * @return array
	 */
	private function folderFiles($sDir, $sType)
	{
		$aResult = array();
		if (is_dir($sDir))
		{
			$aFiles = \Aurora\System\Utils::GlobRecursive($sDir.'/*'.$sType);
			foreach ($aFiles as $sFile)
			{
				if ((empty($sType) || $sType === substr($sFile, -strlen($sType))) && is_file($sFile))
				{
					$aResult[] = $sFile;
				}
			}
		}

		return $aResult;
	}

	/**
	 * @TODO use tenants modules if exist
	 * 
	 * @return string
	 */
	public function compileTemplates()
	{
		$sHash = \Aurora\System\Api::GetModuleManager()->GetModulesHash();
		
		$sCacheFileName = '';
		$oSettings =& \Aurora\System\Api::GetSettings();
		if ($oSettings->GetConf('CacheTemplates', $this->bCache))
		{
			$sCacheFileName = 'templates-'.md5(\Aurora\System\Api::Version().$sHash).'.cache';
			$sCacheFullFileName = \Aurora\System\Api::DataPath().'/cache/'.$sCacheFileName;
			if (file_exists($sCacheFullFileName))
			{
				return file_get_contents($sCacheFullFileName);
			}
		}

		$sResult = '';
		$sPath =\Aurora\System\Api::WebMailPath().'modules';
		
		$aModuleNames = \Aurora\System\Api::GetModuleManager()->GetAllowedModulesName();

		foreach ($aModuleNames as $sModuleName)
		{
			$sDirName = $sPath . '/' . $sModuleName . '/templates';
			$iDirNameLen = strlen($sDirName);
			if (is_dir($sDirName))
			{
				$aList = $this->folderFiles($sDirName, '.html');
				foreach ($aList as $sFileName)
				{
					$sName = '';
					$iPos = strpos($sFileName, $sDirName);
					if ($iPos === 0)
					{
						$sName = substr($sFileName, $iDirNameLen + 1);
					}
					else
					{
						$sName = '@errorName'.md5(rand(10000, 20000));
					}

					$sTemplateID = $sModuleName.'_'.preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(array('/', '\\'), '_', substr($sName, 0, -5)));
					$sTemplateHtml = file_get_contents($sFileName);

					$sTemplateHtml = \Aurora\System\Api::GetModuleManager()->ParseTemplate($sTemplateID, $sTemplateHtml);
					$sTemplateHtml = preg_replace('/\{%INCLUDE-START\/[a-zA-Z\-_]+\/INCLUDE-END%\}/', '', $sTemplateHtml);
					$sTemplateHtml = str_replace('%ModuleName%', $sModuleName, $sTemplateHtml);
					$sTemplateHtml = str_replace('%MODULENAME%', strtoupper($sModuleName), $sTemplateHtml);

					$sTemplateHtml = preg_replace('/<script([^>]*)>/', '&lt;script$1&gt;', $sTemplateHtml);
					$sTemplateHtml = preg_replace('/<\/script>/', '&lt;/script&gt;', $sTemplateHtml);

					$sResult .= '<script id="'.$sTemplateID.'" type="text/html">'.
						preg_replace('/[\r\n\t]+/', ' ', $sTemplateHtml).'</script>';
				}
			}
		}

		$sResult = trim($sResult);
		$oSettings =& \Aurora\System\Api::GetSettings();
		if ($oSettings->GetConf('CacheTemplates', $this->bCache))
		{
			if (!is_dir(dirname($sCacheFullFileName)))
			{
				mkdir(dirname($sCacheFullFileName), 0777, true);
			}
			
			$sResult = '<!-- '.$sCacheFileName.' -->'.$sResult;
			file_put_contents($sCacheFullFileName, $sResult);
		}

		return $sResult;
	}

	/**
	 * @param string $sTheme
	 *
	 * @return string
	 */
	private function validatedThemeValue($sTheme)
	{
		if ('' === $sTheme || !in_array($sTheme, $this->getThemeList()))
		{
			$sTheme = 'Default';
		}

		return $sTheme;
	}

	/**
	 * @param string $sLanguage
	 *
	 * @return string
	 */
	private function validatedLanguageValue($sLanguage)
	{
		if ('' === $sLanguage || !in_array($sLanguage, $this->getLanguageList()))
		{
			$sLanguage = 'English';
		}

		return $sLanguage;
	}

	/**
	 * @TODO use tenants modules if exist
	 * @param string $sLanguage
	 *
	 * @return string
	 */
	public function compileLanguage($sLanguage)
	{
		$sLanguage = $this->validatedLanguageValue($sLanguage);
		$sResult = "";
		
		$sHash = \Aurora\System\Api::GetModuleManager()->GetModulesHash();

		$sCacheFileName = '';
		$oSettings =& \Aurora\System\Api::GetSettings();
		if ($oSettings->GetConf('CacheLangs', $this->bCache))
		{
			$sCacheFileName = 'langs-' . $sLanguage . '-' . md5(\Aurora\System\Api::Version().$sHash) . '.cache';
			$sCacheFullFileName = \Aurora\System\Api::DataPath() . '/cache/' . $sCacheFileName;
			if (file_exists($sCacheFullFileName))
			{
				$sResult = file_get_contents($sCacheFullFileName);
			}
		}
		
		if ($sResult === "")
		{
			$aResult = array();
			$sPath =\Aurora\System\Api::WebMailPath().'modules';

			$aModuleNames = \Aurora\System\Api::GetModuleManager()->GetAllowedModulesName();

			foreach ($aModuleNames as $sModuleName)
			{
				$aLangContent = '';

				$sFileName = $sPath . '/' . $sModuleName . '/i18n/'.$sLanguage.'.ini';

				if (file_exists($sFileName))
				{
					$aLangContent = parse_ini_file($sFileName);
				} 
				else if (file_exists($sPath . '/' . $sModuleName . '/i18n/English.ini'))
				{
					$aLangContent = parse_ini_file($sPath . '/' . $sModuleName . '/i18n/English.ini');
				}
				else
				{
					continue;
				}

				if ($aLangContent)
				{
					foreach ($aLangContent as $sLangKey => $sLangValue)
					{
						$aResult[strtoupper($sModuleName)."/".$sLangKey] = $sLangValue;
					}
				}
			}
			
			$sResult .= json_encode($aResult);
			
			$oSettings =& \Aurora\System\Api::GetSettings();
			if ($oSettings->GetConf('CacheLangs', $this->bCache))
			{
				if (!is_dir(dirname($sCacheFullFileName)))
				{
					mkdir(dirname($sCacheFullFileName), 0777, true);
				}

				$sResult = '/* '.$sCacheFileName.' */'.$sResult;
				file_put_contents($sCacheFullFileName, $sResult);
			}
		}

		return '<script>window.auroraI18n='.($sResult ? $sResult : '{}').';</script>';
	}

	/**
	 * @return string|null
	 */
	private function getCookiePath()
	{
		static $sPath = false;
		
		if (false === $sPath)
		{
			$oSettings =& \Aurora\System\Api::GetSettings();
			$sPath = $oSettings->GetConf('AppCookiePath', '/');
		}

		return $sPath;
	}

	/**
	 * @param string $sAuthToken Default value is empty string.
	 *
	 * @return \Aurora\Modules\Core\Classes\User
	 */
	public function getAuthenticatedUserHelper($sAuthToken = '')
	{
		$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
		$aUserInfo = $this->getAuthenticatedUserInfo($sAuthToken);
		$iUserId = $aUserInfo['userId'];
		$oUser = null;
		if (0 < $iUserId)
		{
			$oUser = $oCoreDecorator->GetUser($iUserId);
		}
		elseif ($aUserInfo['isAdmin'])
		{
			$oUser = $oCoreDecorator->GetAdminUser();
		}
		return $oUser;
	}
	
	/**
	 * @param int $iUserId Default value is empty string.
	 *
	 * @return \Aurora\Modules\Core\Classes\User
	 */
	public function getAuthenticatedUserByIdHelper($iUserId)
	{
		$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
		$oUser = null;
		if (0 < $iUserId)
		{
			$oUser = $oCoreDecorator->GetUser($iUserId);
		}
		elseif ($iUserId === -1)
		{
			$oUser = $oCoreDecorator->GetAdminUser();
		}
		return $oUser;
	}

	
	public function validateAuthToken($sAuthToken)
	{
		return (\Aurora\System\Api::UserSession()->Get($sAuthToken) !== false);
	}
	
	/**
	 * @param string $sAuthToken Default value is empty string.
	 *
	 * @return int
	 */
	public function getAuthenticatedUserInfo($sAuthToken = '')
	{
		$aInfo = array(
			'isAdmin' => false,
			'userId' => 0
		);
		$aAccountHashTable = \Aurora\System\Api::UserSession()->Get($sAuthToken);
		if (is_array($aAccountHashTable) && isset($aAccountHashTable['token']) &&
			'auth' === $aAccountHashTable['token'] && 0 < strlen($aAccountHashTable['id'])) {
			
			$oCoreModule = \Aurora\System\Api::GetModule('Core');
			if ($oCoreModule)
			{
				$oUser = $oCoreModule->oApiUsersManager->getUser((int) $aAccountHashTable['id']);				
				if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
				{
					$aInfo = array(
						'isAdmin' => false,
						'userId' => (int) $aAccountHashTable['id'],
						'account' => isset($aAccountHashTable['account']) ? $aAccountHashTable['account'] : 0,
						'accountType' => isset($aAccountHashTable['account_type']) ? $aAccountHashTable['account_type'] : 0,
					);
				}
			}
		}
		elseif (is_array($aAccountHashTable) && isset($aAccountHashTable['token']) &&
			'admin' === $aAccountHashTable['token'])
		{
			$aInfo = array(
				'isAdmin' => true,
				'userId' => -1
			);
		}
		return $aInfo;
	}

	/**
	 * @return int
	 */
	public function getAuthenticatedHelpdeskUserId()
	{
		$iHdUserId = 0;
		$sKey = empty($_COOKIE[self::AUTH_HD_KEY]) ? '' : $_COOKIE[self::AUTH_HD_KEY];
		if (!empty($sKey) && is_string($sKey))
		{
			$aUserHashTable =\Aurora\System\Api::DecodeKeyValues($sKey);
			if (is_array($aUserHashTable) && isset($aUserHashTable['token']) &&
				'hd_auth' === $aUserHashTable['token'] && 0 < strlen($aUserHashTable['id']) && is_int($aUserHashTable['id']))
			{
				$iHdUserId = (int) $aUserHashTable['id'];
			}
		}

		return $iHdUserId;
	}

	/**
	 * @return \Aurora\Modules\StandardAuth\Classes\Account|null
	 */
	public function getAuthenticatedDefaultAccount($sAuthToken = '')
	{
		$oResult = null;
		$iUserId = \Aurora\System\Api::getAuthenticatedUserId($sAuthToken);
		if (0 < $iUserId)
		{
//			$oApiUsers =\Aurora\System\Api::GetSystemManager('users');
			if ($oApiUsers)
			{
				$iAccountId = $oApiUsers->getDefaultAccountId($iUserId);
				if (0 < $iAccountId)
				{
					$oAccount = $oApiUsers->getAccountById($iAccountId);
					$oResult = $oAccount instanceof \Aurora\Modules\StandardAuth\Classes\Account ? $oAccount : null;
				}
			}
		}
		else 
		{
			$this->logoutAccount();
		}

		return $oResult;
	}

	/**
	 * @param int $iCode
	 */
	public function setLastErrorCode($iCode)
	{
		@setcookie(self::TOKEN_LAST_CODE, $iCode, 0, $this->getCookiePath(), null, null, true);
	}

	/**
	 * @return int
	 */
	public function getLastErrorCode()
	{
		return isset($_COOKIE[self::TOKEN_LAST_CODE]) ? (int) $_COOKIE[self::TOKEN_LAST_CODE] : 0;
	}

	public function clearLastErrorCode()
	{
		if (isset($_COOKIE[self::TOKEN_LAST_CODE]))
		{
			unset($_COOKIE[self::TOKEN_LAST_CODE]);
		}
		
		@setcookie(self::TOKEN_LAST_CODE, '', time() - 60 * 60 * 24 * 30, $this->getCookiePath());
	}

	/**
	 * @param string $sAuthToken Default value is empty string.
	 * 
 	 * @return bool
	 */
	public function logoutAccount($sAuthToken = '')
	{
		if (strlen($sAuthToken) !== 0)
		{
			$sKey = \Aurora\System\Api::UserSession()->Delete($sAuthToken);
		}
		
		@setcookie(\Aurora\System\Application::AUTH_TOKEN_KEY, '', time() - 60 * 60 * 24 * 30, $this->getCookiePath());
		return true;
	}

	/**
	 * @param int $iThreadID
	 * @param string $sThreadAction Default value is empty string.
	 */
	public function setThreadIdFromRequest($iThreadID, $sThreadAction = '')
	{
		$aHashTable = array(
			'token' => 'thread_id',
			'id' => (int) $iThreadID,
			'action' => (string) $sThreadAction
		);

		\Aurora\System\Api::LogObject($aHashTable);

		$_COOKIE[self::TOKEN_HD_THREAD_ID] =\Aurora\System\Api::EncodeKeyValues($aHashTable);
		@setcookie(self::TOKEN_HD_THREAD_ID,\Aurora\System\Api::EncodeKeyValues($aHashTable), 0, $this->getCookiePath(), null, null, true);
	}

	/**
	 * @return array
	 */
	public function getThreadIdFromRequestAndClear()
	{
		$aHdThreadId = array();
		$sKey = empty($_COOKIE[self::TOKEN_HD_THREAD_ID]) ? '' : $_COOKIE[self::TOKEN_HD_THREAD_ID];
		if (!empty($sKey) && is_string($sKey))
		{
			$aUserHashTable =\Aurora\System\Api::DecodeKeyValues($sKey);
			if (is_array($aUserHashTable) && isset($aUserHashTable['token'], $aUserHashTable['id']) &&
				'thread_id' === $aUserHashTable['token'] && 0 < strlen($aUserHashTable['id']) && is_int($aUserHashTable['id']))
			{
				$aHdThreadId['id'] = (int) $aUserHashTable['id'];
				$aHdThreadId['action'] = isset($aUserHashTable['action']) ? (string) $aUserHashTable['action'] : '';
			}
		}

		if (0 < strlen($sKey))
		{
			if (isset($_COOKIE[self::TOKEN_HD_THREAD_ID]))
			{
				unset($_COOKIE[self::TOKEN_HD_THREAD_ID]);
			}

			@setcookie(self::TOKEN_HD_THREAD_ID, '', time() - 60 * 60 * 24 * 30, $this->getCookiePath());
		}

		return $aHdThreadId;
	}

	public function removeUserAsActivated()
	{
		if (isset($_COOKIE[self::TOKEN_HD_ACTIVATED]))
		{
			$_COOKIE[self::TOKEN_HD_ACTIVATED] = '';
			unset($_COOKIE[self::TOKEN_HD_ACTIVATED]);
			@setcookie(self::TOKEN_HD_ACTIVATED, '', time() - 60 * 60 * 24 * 30, $this->getCookiePath());
		}
	}

	/**
	 * @param CHelpdeskUser $oHelpdeskUser
	 * @param bool $bForgot Default value is **false**.
	 *
	 * @return void
	 */
	public function setUserAsActivated($oHelpdeskUser, $bForgot = false)
	{
		$aHashTable = array(
			'token' => 'hd_activated_email',
			'forgot' => $bForgot,
			'email' => $oHelpdeskUser->Email
		);

		$_COOKIE[self::TOKEN_HD_ACTIVATED] =\Aurora\System\Api::EncodeKeyValues($aHashTable);
		@setcookie(self::TOKEN_HD_ACTIVATED,\Aurora\System\Api::EncodeKeyValues($aHashTable), 0, $this->getCookiePath(), null, null, true);
	}

	/**
	 * @return int
	 */
	public function getActivatedUserEmailAndClear()
	{
		$sEmail = '';
		$sKey = empty($_COOKIE[self::TOKEN_HD_ACTIVATED]) ? '' : $_COOKIE[self::TOKEN_HD_ACTIVATED];
		if (!empty($sKey) && is_string($sKey))
		{
			$aUserHashTable =\Aurora\System\Api::DecodeKeyValues($sKey);
			if (is_array($aUserHashTable) && isset($aUserHashTable['token']) &&
				'hd_activated_email' === $aUserHashTable['token'] && 0 < strlen($aUserHashTable['email']))
			{
				$sEmail = $aUserHashTable['email'];
			}
		}

		if (0 < strlen($sKey))
		{
			if (isset($_COOKIE[self::TOKEN_HD_ACTIVATED]))
			{
				unset($_COOKIE[self::TOKEN_HD_ACTIVATED]);
			}

			@setcookie(self::TOKEN_HD_THREAD_ID, '', time() - 60 * 60 * 24 * 30, $this->getCookiePath());
		}

		return $sEmail;
	}

	/**
	 * @param \Aurora\Modules\StandardAuth\Classes\Account $oAccount
	 * @param bool $bSignMe Default value is **false**.
	 *
	 * @return string
	 */
	public function setAccountAsLoggedIn(\Aurora\Modules\StandardAuth\Classes\Account $oAccount, $bSignMe = false)
	{
		$aAccountHashTable = array(
			'token' => 'auth',
			'sign-me' => $bSignMe,
			'id' => $oAccount->IdUser,
			'email' => $oAccount->Email
		);
		
		$iTime = $bSignMe ? time() + 60 * 60 * 24 * 30 : 0;
		$sAccountHashTable = \Aurora\System\Api::EncodeKeyValues($aAccountHashTable);
		
		$sAuthToken = \md5($oAccount->IdUser.$oAccount->IncomingLogin.\microtime(true).\rand(10000, 99999));
		
		return \Aurora\System\Api::Cacher()->Set('AUTHTOKEN:'.$sAuthToken, $sAccountHashTable) ? $sAuthToken : '';
	}

	/**
	 * @return bool
	 */
	public function logoutHelpdeskUser()
	{
		@setcookie(self::AUTH_HD_KEY, '', time() - 60 * 60 * 24 * 30, $this->getCookiePath());
		return true;
	}

	public function skipMobileCheck()
	{
		@setcookie(self::TOKEN_SKIP_MOBILE_CHECK, '1', 0, $this->getCookiePath(), null, null, true);
	}

	/**
	 * @return int
	 */
	public function isMobile()
	{
		if (isset($_COOKIE[self::TOKEN_SKIP_MOBILE_CHECK]) && '1' === (string) $_COOKIE[self::TOKEN_SKIP_MOBILE_CHECK])
		{
			@setcookie(self::TOKEN_SKIP_MOBILE_CHECK, '', time() - 60 * 60 * 24 * 30, $this->getCookiePath());
			return 0;
		}

		return isset($_COOKIE[self::MOBILE_KEY]) ? ('1' === (string) $_COOKIE[self::MOBILE_KEY] ? 1 : 0) : -1;
	}

	/**
	 * @param bool $bMobile
	 *
	 * @return bool
	 */
	public function setMobile($bMobile)
	{
		@setcookie(self::MOBILE_KEY, $bMobile ? '1' : '0', time() + 60 * 60 * 24 * 200, $this->getCookiePath());
		return true;
	}

	public function resetCookies()
	{
		$sHelpdeskHash = !empty($_COOKIE[self::AUTH_HD_KEY]) ? $_COOKIE[self::AUTH_HD_KEY] : '';
		if (0 < strlen($sHelpdeskHash))
		{
			$aHelpdeskHashTable =\Aurora\System\Api::DecodeKeyValues($sHelpdeskHash);
			if (isset($aHelpdeskHashTable['sign-me']) && $aHelpdeskHashTable['sign-me'])
			{
				@setcookie(self::AUTH_HD_KEY,\Aurora\System\Api::EncodeKeyValues($aHelpdeskHashTable),
					time() + 60 * 60 * 24 * 30, $this->getCookiePath(), null, null, true);
			}
		}
	}

	/**
	 * @param int $iIdTenant
	 * @param string $sEmail
	 * @param string $sPassword
	 *
	 * @throws \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_AccountSystemAuthentication) 6008
	 * @throws \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_UnactivatedUser) 6010
	 * @throws \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_AccountAuthentication) 6004
	 *
	 * @return CHelpdeskUser|null|bool
	 */
	public function loginToHelpdeskAccount($iIdTenant, $sEmail, $sPassword)
	{
		$oResult = null;

//		$oApiHelpdeskManager = /* @var $oApiHelpdeskManager CApiHelpdeskManager */\Aurora\System\Api::Manager('helpdesk');
//		$oApiUsersManager = /* @var $oApiUsersManager CApiUsersManager */\Aurora\System\Api::GetSystemManager('users');
		if (!$oApiHelpdeskManager || !$oApiUsersManager || !$oApiCapabilityManager)
		{
			return false;
		}

		$oAccount = $oApiUsersManager->getAccountByEmail($sEmail);
		if ($oAccount && $oAccount->IdTenant === $iIdTenant &&
			$oAccount->IncomingPassword === $sPassword)
		{
			$this->setAccountAsLoggedIn($oAccount);
			$this->setThreadIdFromRequest(0);
			throw new \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_AccountSystemAuthentication);
		}

		$oUser = /* @var $oUser CHelpdeskUser */ $oApiHelpdeskManager->getUserByEmail($iIdTenant, $sEmail);
		if ($oUser instanceof CHelpdeskUser && $oUser->validatePassword($sPassword) && $iIdTenant === $oUser->IdTenant)
		{
			if (!$oUser->Activated)
			{
				throw new \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_UnactivatedUser);
			}

			$oResult = $oUser;
		}
		else
		{
			throw new \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_AccountAuthentication);
		}

		return $oResult;
	}

	/**
	 * @param int $iIdTenant
	 * @param string $sEmail
	 * @param string $sName
	 * @param string $sPassword
	 * @param bool $bCreateFromFetcher Default value is **false**.
	 *
	 * @throws \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_UserAlreadyExists) 6001
	 * @throws \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_UserCreateFailed) 6002
	 *
	 * @return CHelpdeskUser|bool
	 */
	public function registerHelpdeskAccount($iIdTenant, $sEmail, $sName, $sPassword, $bCreateFromFetcher = false)
	{
		$mResult = false;

		$oApiHelpdeskManager = /* @var $oApiHelpdeskManager CApiHelpdeskManager */\Aurora\System\Api::Manager('helpdesk');
//		$oApiUsersManager = /* @var $oApiUsersManager CApiUsersManager */\Aurora\System\Api::GetSystemManager('users');
		if (!$oApiHelpdeskManager || !$oApiUsersManager || !$oApiCapabilityManager)
		{
			return $mResult;
		}

		$oUser = /* @var $oUser CHelpdeskUser */ $oApiHelpdeskManager->getUserByEmail($iIdTenant, $sEmail);
		if (!$oUser)
		{
			$oAccount = $oApiUsersManager->getAccountByEmail($sEmail);
			if ($oAccount && $oAccount->IdTenant === $iIdTenant)
			{
				throw new \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_UserAlreadyExists);
			}

			$oUser = new CHelpdeskUser();
			$oUser->Activated = false;
			$oUser->Email = $sEmail;
			$oUser->Name = $sName;
			$oUser->IdTenant = $iIdTenant;
			$oUser->IsAgent = false;

			$oUser->setPassword($sPassword, $bCreateFromFetcher);

			$oApiHelpdeskManager->createUser($oUser, $bCreateFromFetcher);
			if (!$oUser || 0 === $oUser->IdHelpdeskUser)
			{
				throw new \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_UserCreateFailed);
			}
			else
			{
				$mResult = $oUser;
			}
		}
		else
		{
			throw new \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_UserAlreadyExists);
		}

		return $mResult;
	}

	/**
	 * @param int $iIdTenant
	 * @param string $sTenantName
	 * @param string $sNotificationEmail
	 * @param string $sSocialId
	 * @param string $sSocialType
	 * @param string $sSocialName
	 *
	 * @throws \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_UserAlreadyExists) 6001
	 * @throws \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_UserCreateFailed) 6002
	 *
	 * @return bool
	 */
	public function registerSocialAccount($iIdTenant, $sTenantName, $sNotificationEmail, $sSocialId, $sSocialType, $sSocialName)
	{
		$bResult = false;

		$oApiHelpdeskManager = /* @var $oApiHelpdeskManager CApiHelpdeskManager */\Aurora\System\Api::Manager('helpdesk');
//		$oApiUsersManager = /* @var $oApiUsersManager CApiUsersManager */\Aurora\System\Api::GetSystemManager('users');
		if (!$oApiHelpdeskManager || !$oApiUsersManager || !$oApiCapabilityManager)
		{
			return $bResult;
		}

		$oUser = /* @var $oUser CHelpdeskUser */ $oApiHelpdeskManager->getUserBySocialId($iIdTenant, $sSocialId);
		if (!$oUser)
		{
			$oAccount = $this->getAhdSocialUser($sTenantName, $sSocialId);
			if ($oAccount && $oAccount->IdTenant === $iIdTenant)
			{
				throw new \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_UserAlreadyExists);
			}

			$oUser = new CHelpdeskUser();
			$oUser->Activated = true;
			$oUser->Name = $sSocialName;
			$oUser->NotificationEmail = $sNotificationEmail;
			$oUser->SocialId = $sSocialId;
			$oUser->SocialType = $sSocialType;
			$oUser->IdTenant = $iIdTenant;
			$oUser->IsAgent = false;
			$oApiHelpdeskManager->createUser($oUser);
			if (!$oUser || 0 === $oUser->IdHelpdeskUser)
			{
				throw new \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_UserCreateFailed);
			}
			else
			{
				$bResult = true;
			}
		}
		else
		{
			throw new \Aurora\System\Exceptions\ManagerException(Errs::HelpdeskManager_UserAlreadyExists);
		}

		return $bResult;
	}

	/**
	 * @return array
	 */
	public function getLanguageList()
	{
		static $aList = null;
		
		if (null === $aList)
		{
			$aList = array();
			$sPath =\Aurora\System\Api::WebMailPath().'modules';

			$aModuleNames = \Aurora\System\Api::GetModuleManager()->GetAllowedModulesName();

			foreach ($aModuleNames as $sModuleName)
			{
				$sModuleLangsDir = $sPath . '/' . $sModuleName . '/i18n';

				if (@is_dir($sModuleLangsDir))
				{
					$rDirH = @opendir($sModuleLangsDir);
					if ($rDirH)
					{
						while (($sFile = @readdir($rDirH)) !== false)
						{
							$sLanguage = substr($sFile, 0, -4);
							if ('.' !== $sFile{0} && is_file($sModuleLangsDir.'/'.$sFile) && '.ini' === substr($sFile, -4))
							{
								if (0 < strlen($sLanguage) && !in_array($sLanguage, $aList))
								{
									if ('english' === strtolower($sLanguage))
									{
										array_unshift($aList, $sLanguage);
									}
									else
									{
										$aList[] = $sLanguage;
									}
								}
							}
						}
						@closedir($rDirH);
					}
				} 
			}
		}
		
		return $aList;
	}

	/**
	 * @return array
	 */
	public function getThemeList()
	{
		static $sList = null;
		if (null === $sList)
		{
			$sList = array();

			$oModuleManager = \Aurora\System\Api::GetModuleManager();
			$aThemes = $oModuleManager->getModuleConfigValue('CoreWebclient', 'ThemeList');
			$sDir =\Aurora\System\Api::WebMailPath().'static/styles/themes/';

			if (is_array($aThemes))
			{
				foreach ($aThemes as $sTheme)
				{
					if (file_exists($sDir.'/'.$sTheme.'/styles.css'))
					{
						$sList[] = $sTheme;
					}
				}
			}
		}

		return $sList;
	}

	/**
	 * @return array
	 */
	public function appData()
	{
		$aAppData = array(
			'User' => array(
				'Id' => 0,
				'Role' => \Aurora\System\Enums\UserRole::Anonymous,
				'Name' => '',
				'PublicId' => '',
			)
		);
		
		// AuthToken reads from coockie for HTML
		$sAuthToken = isset($_COOKIE[\Aurora\System\Application::AUTH_TOKEN_KEY]) ? $_COOKIE[\Aurora\System\Application::AUTH_TOKEN_KEY] : '';
		
		$oUser = null;
		try
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser($sAuthToken);
		}
		catch (\Exception $oEx)	{}

		$aModules = \Aurora\System\Api::GetModules();

		foreach ($aModules as $sModuleName => $oModule)
		{
			try
			{
				$oDecorator = \Aurora\System\Api::GetModuleDecorator($sModuleName);
				$aModuleAppData = $oDecorator->GetSettings();
				$aModuleErrors = $oDecorator->GetErrors();
			}
			catch (\Aurora\System\Exceptions\ApiException $oEx)
			{
				$aModuleAppData = null;
			}
			
			if (is_array($aModuleAppData))
			{
				$aAppData[$oModule->GetName()] = $aModuleAppData;
			}
			$aAppData['module_errors'][$oModule->GetName()] = $aModuleErrors;
		}
		
		if ($oUser)
		{
			$aAppData['User'] = array(
				'Id' => $oUser->EntityId,
				'Role' => $oUser->Role,
				'Name' => $oUser->Name,
				'PublicId' => $oUser->PublicId,
			);
		}
		else
		{
			\Aurora\System\Api::UserSession()->Delete($sAuthToken);
		}

		return $aAppData;
	}

	/**
	 * @depricated
	 * @param string $sHelpdeskTenantHash Default value is empty string.
	 * @param string $sUserId Default value is empty string.
	 *
	 * @throws \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter) 103
	 *
	 * @return User|bool
	 */
	public function getAhdSocialUser($sHelpdeskTenantHash = '', $sUserId = '')
	{
//		$sTenantHash = $sHelpdeskTenantHash;
//		$oApiTenant = \Aurora\System\Api::GetCoreManager('tenants');
//		$iIdTenant = $oApiTenant->getTenantIdByName($sTenantHash);
//		if (!is_int($iIdTenant))
//		{
//			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::InvalidInputParameter);
//		}
////		$oApiHelpdeskManager =\Aurora\System\Api::Manager('helpdesk'); // TODO:
//		$oUser = $oApiHelpdeskManager->getUserBySocialId($iIdTenant, $sUserId);
//
//		return $oUser;
	}

	/**
	 * @param string $sHelpdeskHash Default value is empty string.
	 * @param string $sCalendarPubHash Default value is empty string.
	 * @param string $sFileStoragePubHash Default value is empty string.
	 *
	 * @return string
	 */
	public function compileAppData()
	{
		return '<script>window.auroraAppData='.@json_encode($this->appData()).';</script>';
	}

	/**
	 * @return array
	 */
	public function getThemeAndLanguage()
	{
		static $sLanguage = false;
		static $sTheme = false;
		static $sSiteName = false;

		if (false === $sLanguage && false === $sTheme && false === $sSiteName)
		{
			$oSettings =&\Aurora\System\Api::GetSettings();
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			$oModuleManager = \Aurora\System\Api::GetModuleManager();
			
			$sSiteName = $oSettings->GetConf('SiteName');
			$sLanguage = \Aurora\System\Api::GetLanguage();
			$sTheme = $oUser ? $oUser->{'CoreWebclient::Theme'} : $oModuleManager->getModuleConfigValue('CoreWebclient', 'Theme');
			
			if ($oUser)
			{
				$sSiteName = '';
			}
			else
			{
			}

			$sLanguage = $this->validatedLanguageValue($sLanguage);
			$sTheme = $this->validatedThemeValue($sTheme);
		}
		
		/*** temporary fix to the problems in mobile version in rtl mode ***/
		
		if (in_array($sLanguage, array('Arabic', 'Hebrew', 'Persian')) /* && $oApiCapability->isNotLite()*/ && 1 === $this->isMobile()) // todo
		{
			$sLanguage = 'English';
		}
		
		/*** end of temporary fix to the problems in mobile version in rtl mode ***/

		return array($sLanguage, $sTheme, $sSiteName);
	}

	/**
	 * Indicates if rtl interface should be turned on.
	 * 
	 * @return bool
	 */
	public function IsRtl()
	{
		list($sLanguage, $sTheme, $sSiteName) = $this->getThemeAndLanguage();
		return \in_array($sLanguage, array('Arabic', 'Hebrew', 'Persian'));
	}
	
	/**
	 * Returns css links for building in html.
	 * 
	 * @return string
	 */
	public function buildHeadersLink()
	{
		list($sLanguage, $sTheme, $sSiteName) = $this->getThemeAndLanguage();
		$sMobileSuffix = \Aurora\System\Api::IsMobileApplication() ? '-mobile' : '';
		$sTenantName = \Aurora\System\Api::getTenantName();
		$oSettings =&\Aurora\System\Api::GetSettings();
		
		if ($oSettings->GetConf('EnableMultiTenant') && $sTenantName)
		{
			$sS =
'<link type="text/css" rel="stylesheet" href="./static/styles/libs/libs.css'.'?'.\Aurora\System\Api::VersionJs().'" />'.
'<link type="text/css" rel="stylesheet" href="./tenants/'.$sTenantName.'/static/styles/themes/'.$sTheme.'/styles'.$sMobileSuffix.'.css'.'?'.\Aurora\System\Api::VersionJs().'" />';
		}
		else
		{
			$sS =
'<link type="text/css" rel="stylesheet" href="./static/styles/libs/libs.css'.'?'.\Aurora\System\Api::VersionJs().'" />'.
'<link type="text/css" rel="stylesheet" href="./static/styles/themes/'.$sTheme.'/styles'.$sMobileSuffix.'.css'.'?'.\Aurora\System\Api::VersionJs().'" />';
		}
		
		return $sS;
	}
	
	public function GetClientModuleNames()
	{
		$aClientModuleNames = [];
		$aModuleNames = \Aurora\System\Api::GetModuleManager()->GetAllowedModulesName();
		$sModulesPath = \Aurora\System\Api::GetModuleManager()->GetModulesPath();
		$bIsMobileApplication = \Aurora\System\Api::IsMobileApplication();
		foreach ($aModuleNames as $sModuleName)
		{
			$this->populateClientModuleNames($sModulesPath, $sModuleName, $bIsMobileApplication, $aClientModuleNames, false);
		}		
		
		return $aClientModuleNames;
	}
	
	public function GetBackendModules()
	{
		$aBackendModuleNames = [];
		$aModuleNames = \Aurora\System\Api::GetModuleManager()->GetAllowedModulesName();
		$sModulesPath = \Aurora\System\Api::GetModuleManager()->GetModulesPath();
		foreach ($aModuleNames as $sModuleName)
		{
			if (!\file_exists($sModulesPath . $sModuleName . '/js/manager.js'))
			{
				$aBackendModuleNames[] = $sModuleName;
			}
		}
		return $aBackendModuleNames;
	}
	
	/**
	 * Returns JS links for building in HTML.
	 * @param array $aConfig
	 * @return string
	 */
	public function compileJS($aConfig = array())
	{
		$oSettings =& \Aurora\System\Api::GetSettings();
		$sPostfix = '';
		
		if ($oSettings->GetConf('UseAppMinJs', false))
		{
			$sPostfix .= '.min';
		}
		
		$sTenantName = \Aurora\System\Api::getTenantName();
		
		$sJsScriptPath = $oSettings->GetConf('EnableMultiTenant') && $sTenantName ? "./tenants/".$sTenantName."/" : "./";
		
		$aClientModuleNames = [];
		if (isset($aConfig['modules_list']))
		{
			$aClientModuleNames = $aConfig['modules_list'];
		}
		else
		{
			$aClientModuleNames = $this->GetClientModuleNames();
		}
		
		$bIsPublic = isset($aConfig['public_app']) ? (bool)$aConfig['public_app'] : false;
		$bIsNewTab = isset($aConfig['new_tab']) ? (bool)$aConfig['new_tab'] : false;
		
		return '<script>window.isPublic = '.($bIsPublic ? 'true' : 'false').
				'; window.isNewTab = '.($bIsNewTab ? 'true' : 'false').
				'; window.aAvailableModules = ["'.implode('","', $aClientModuleNames).'"]'.
				'; window.aAvailableBackendModules = ["'.implode('","', $this->GetBackendModules()).'"];</script>
		<script src="'.$sJsScriptPath."static/js/app".$sPostfix.".js?".\Aurora\System\Api::VersionJs().'"></script>';
	}
	
	/**
	 * Populates array with names of modules that should be loaded on client side.
	 * @param string $sModulesPath Path to folder with modules.
	 * @param string $sModuleName Name of module to add.
	 * @param boolean $bIsMobileApplication Indicates if there is mobile version of application.
	 * @param array $aClientModuleNames Array with names of modules that should be loaded on client side. Array is passed by reference and populated with this method.
	 * @param boolean $bAddAnyway Indicates if module should be added anyway.
	 */
	protected function populateClientModuleNames($sModulesPath, $sModuleName, $bIsMobileApplication, &$aClientModuleNames, $bAddAnyway)
	{
		if (!in_array($sModuleName, $aClientModuleNames))
		{
			$bAddModuleName = $bAddAnyway;
			
			$oModuleManager = \Aurora\System\Api::GetModuleManager();
			if (!$bAddModuleName && $bIsMobileApplication)
			{
				$bAddModuleName = $oModuleManager->getModuleConfigValue($sModuleName, 'IncludeInMobile', false);
			}
			elseif (!$bAddModuleName)
			{
				$bAddModuleName = $oModuleManager->getModuleConfigValue($sModuleName, 'IncludeInDesktop', true);
			}
			
			if ($bAddModuleName && \file_exists($sModulesPath . $sModuleName . '/js/manager.js'))
			{
				$aClientModuleNames[] = $sModuleName;
				if ($bIsMobileApplication)
				{
					$aRequire = $oModuleManager->getModuleConfigValue($sModuleName, 'RequireInMobile', true);
					if (is_array($aRequire))
					{
						foreach ($aRequire as $sRequireModuleName)
						{
							$this->populateClientModuleNames($sModulesPath, $sRequireModuleName, $bIsMobileApplication, $aClientModuleNames, true);
						}
					}
				}
			}
		}
	}
	
	/**
	 * Returns application html.
	 * 
	 * @param array $aConfig
	 * @return string
	 */
	public function buildBody($aConfig = array())
	{
		list($sLanguage, $sTheme, $sSiteName) = $this->getThemeAndLanguage();
		return
			$this->compileTemplates()."\r\n".
			$this->compileLanguage($sLanguage)."\r\n".
			$this->compileAppData()."\r\n".
			$this->compileJS($aConfig).
			"\r\n".'<!-- '.\Aurora\System\Api::Version().' -->'
		;
	}
	
	public function GetModulesForEntry($sEntryModule)
	{
		$aResModuleList = [$sEntryModule];
		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		$aAvailableOn = $oModuleManager->getModuleConfigValue($sEntryModule, 'AvailableOn');
		if (is_array($aAvailableOn) && count($aAvailableOn) > 0)
		{
			$aResModuleList = array_merge($aResModuleList, $aAvailableOn);
		}
		else
		{
			$aAllowedModuleNames = $oModuleManager->GetAllowedModulesName();
			foreach ($aAllowedModuleNames as $sAllowedModule)
			{
				$aAvailableFor = $oModuleManager->getModuleConfigValue($sAllowedModule, 'AvailableFor');
				if (is_array($aAvailableFor) && count($aAvailableFor) > 0 && in_array($sEntryModule, $aAvailableFor))
				{
					$aResModuleList = array_merge($aResModuleList, [$sAllowedModule]);
				}
			}
		}
		
		return $aResModuleList;
	}
}
