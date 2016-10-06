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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
 */

class CoreModule extends AApiModule
{
	public $oApiTenantsManager = null;
	
	public $oApiChannelsManager = null;
	
	public $oApiUsersManager = null;
	
	protected $aSettingsMap = array(
		'LoggingLevel' => array(ELogLevel::Full, 'spec', 'ELogLevel'),
	);

	/***** private functions *****/
	/**
	 * Initializes Core Module.
	 * 
	 * @ignore
	 */
	public function init() {
		
		$this->incClass('channel');
		$this->incClass('usergroup');		
		$this->incClass('tenant');
		$this->incClass('socials');
		$this->incClass('user');
		
		$this->oApiTenantsManager = $this->GetManager('tenants');
		$this->oApiChannelsManager = $this->GetManager('channels');
		$this->oApiUsersManager = $this->GetManager('users');
		
		$this->AddEntries(array(
				'ping' => 'EntryPing',
				'pull' => 'EntryPull',
				'plugins' => 'EntryPlugins',
				'mobile' => 'EntryMobile',
				'speclogon' => 'EntrySpeclogon',
				'speclogoff' => 'EntrySpeclogoff',
				'sso' => 'EntrySso',
				'postlogin' => 'EntryPostlogin'
			)
		);
		
		$this->subscribeEvent('CreateAccount', array($this, 'onCreateAccount'));
	}
	
	/**
	 * Is called by CreateAccount event. Finds or creates and returns User for new account.
	 * 
	 * @ignore
	 * @param array $aData {
	 *		*int* **UserId** Identificator of existing user.
	 *		*int* **TenantId** Identificator of tenant for creating new user in it.
	 *		*int* **$PublicId** New user name.
	 * }
	 * @param \CUser $oResult
	 */
	public function onCreateAccount($aData, &$oResult)
	{
		$oUser = null;
		
		if (isset($aData['UserId']) && (int)$aData['UserId'] > 0)
		{
			$oUser = $this->oApiUsersManager->getUserById($aData['UserId']);
		}
		else
		{
			$oUser = \CUser::createInstance();
			
			$iTenantId = (isset($aData['TenantId'])) ? (int)$aData['TenantId'] : 0;
			if ($iTenantId)
			{
				$oUser->IdTenant = $iTenantId;
			}

			$PublicId = (isset($aData['PublicId'])) ? $aData['PublicId'] : '';
			if ($PublicId)
			{
				$oUser->PublicId = $PublicId;
			}
				
			if (!$this->oApiUsersManager->createUser($oUser))
			{
				$oUser = null;
			}
		}
		
		$oResult = $oUser;
	}
	
	/**
	 * Recursively deletes temporary files and folders on time.
	 * 
	 * @param string $sTempPath Path to the temporary folder.
	 * @param int $iTime2Kill Interval in seconds at which files needs removing.
	 * @param int $iNow Current Unix timestamp.
	 */
	protected function removeDirByTime($sTempPath, $iTime2Kill, $iNow)
	{
		$iFileCount = 0;
		if (@is_dir($sTempPath))
		{
			$rDirH = @opendir($sTempPath);
			if ($rDirH)
			{
				while (($sFile = @readdir($rDirH)) !== false)
				{
					if ('.' !== $sFile && '..' !== $sFile)
					{
						if (@is_dir($sTempPath.'/'.$sFile))
						{
							$this->removeDirByTime($sTempPath.'/'.$sFile, $iTime2Kill, $iNow);
						}
						else
						{
							$iFileCount++;
						}
					}
				}
				@closedir($rDirH);
			}

			if ($iFileCount > 0)
			{
				if ($this->removeFilesByTime($sTempPath, $iTime2Kill, $iNow))
				{
					@rmdir($sTempPath);
				}
			}
			else
			{
				@rmdir($sTempPath);
			}
		}
	}

	/**
	 * Recursively deletes temporary files on time.
	 * 
	 * @param string $sTempPath Path to the temporary folder.
	 * @param int $iTime2Kill Interval in seconds at which files needs removing.
	 * @param int $iNow Current Unix timestamp.
	 * 
	 * @return bool
	 */
	protected function removeFilesByTime($sTempPath, $iTime2Kill, $iNow)
	{
		$bResult = true;
		if (@is_dir($sTempPath))
		{
			$rDirH = @opendir($sTempPath);
			if ($rDirH)
			{
				while (($sFile = @readdir($rDirH)) !== false)
				{
					if ($sFile !== '.' && $sFile !== '..')
					{
						if ($iNow - filemtime($sTempPath.'/'.$sFile) > $iTime2Kill)
						{
							@unlink($sTempPath.'/'.$sFile);
						}
						else
						{
							$bResult = false;
						}
					}
				}
				@closedir($rDirH);
			}
		}
		return $bResult;
	}
	/***** private functions *****/
	
	/***** static functions *****/
	/**
	 * @ignore
	 * @return bool
	 */
	public static function deleteTree($dir)
	{
		$files = array_diff(scandir($dir), array('.','..'));
			
		foreach ($files as $file)
		{
			(is_dir("$dir/$file")) ? self::deleteTree("$dir/$file") : unlink("$dir/$file");
		}
		
		return rmdir($dir);
	}
	/***** static functions *****/
	
	/***** public functions *****/
	/**
	 * @ignore
	 */
	public function EntryPull()
	{
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
		{
			pclose(popen("start /B git pull", "r"));
		}
		else 
		{
			exec("git pull > /dev/null 2>&1 &");
		}
	}
	
	/**
	 * @ignore
	 * @return string
	 */
	public function EntryPlugins()
	{
		$sResult = '';
		$aPaths = $this->oHttp->GetPath();
		$sType = !empty($aPaths[1]) ? trim($aPaths[1]) : '';
		if ('js' === $sType)
		{
			@header('Content-Type: application/javascript; charset=utf-8');
			$sResult = \CApi::Plugin()->CompileJs();
		}
		else if ('images' === $sType)
		{
			if (!empty($aPaths[2]) && !empty($aPaths[3]))
			{
				$oPlugin = \CApi::Plugin()->GetPluginByName($aPaths[2]);
				if ($oPlugin)
				{
					echo $oPlugin->GetImage($aPaths[3]);exit;
				}
			}
		}
		else if ('fonts' === $sType)
		{
			if (!empty($aPaths[2]) && !empty($aPaths[3]))
			{
				$oPlugin = \CApi::Plugin()->GetPluginByName($aPaths[2]);
				if ($oPlugin)
				{
					echo $oPlugin->GetFont($aPaths[3]);exit;
				}
			}
		}	
		
		return $sResult;
	}	
	
	/**
	 * @ignore
	 */
	public function EntryMobile()
	{
		if ($this->oApiCapabilityManager->isNotLite())
		{
			$oApiIntegrator = \CApi::GetSystemManager('integrator');
			$oApiIntegrator->setMobile(true);
		}

		\CApi::Location('./');
	}
	
	/**
	 * @ignore
	 * Creates entry point ?Speclogon that turns on user level of logging.
	 */
	public function EntrySpeclogon()
	{
		\CApi::SpecifiedUserLogging(true);
		\CApi::Location('./');
	}
	
	/**
	 * @ignore
	 * Creates entry point ?Speclogoff that turns off user level of logging.
	 */
	public function EntrySpeclogoff()
	{
		\CApi::SpecifiedUserLogging(false);
		\CApi::Location('./');
	}

	/**
	 * @ignore
	 */
	public function EntrySso()
	{
		$oApiIntegratorManager = \CApi::GetSystemManager('integrator');

		try
		{
			$sHash = $this->oHttp->GetRequest('hash');
			if (!empty($sHash))
			{
				$sData = \CApi::Cacher()->get('SSO:'.$sHash, true);
				$aData = \CApi::DecodeKeyValues($sData);

				if (!empty($aData['Email']) && isset($aData['Password'], $aData['Login']))
				{
					$oAccount = $oApiIntegratorManager->loginToAccount($aData['Email'], $aData['Password'], $aData['Login']);
					if ($oAccount)
					{
						$oApiIntegratorManager->setAccountAsLoggedIn($oAccount);
					}
				}
			}
			else
			{
				$oApiIntegratorManager->logoutAccount();
			}
		}
		catch (\Exception $oExc)
		{
			\CApi::LogException($oExc);
		}

		\CApi::Location('./');		
	}	
	
	/**
	 * @ignore
	 */
	public function EntryPostlogin()
	{
		if (\CApi::GetConf('labs.allow-post-login', false))
		{
			$oApiIntegrator = \CApi::GetSystemManager('integrator');
					
			$sEmail = trim((string) $this->oHttp->GetRequest('Email', ''));
			$sLogin = (string) $this->oHttp->GetRequest('Login', '');
			$sPassword = (string) $this->oHttp->GetRequest('Password', '');

			$sAtDomain = trim(\CApi::GetSettingsConf('WebMail/LoginAtDomainValue'));
			if (\ELoginFormType::Login === (int) \CApi::GetSettingsConf('WebMail/LoginFormType') && 0 < strlen($sAtDomain))
			{
				$sEmail = \api_Utils::GetAccountNameFromEmail($sLogin).'@'.$sAtDomain;
				$sLogin = $sEmail;
			}

			if (0 !== strlen($sPassword) && 0 !== strlen($sEmail.$sLogin))
			{
				try
				{
					$oAccount = $oApiIntegrator->loginToAccount($sEmail, $sPassword, $sLogin);
				}
				catch (\Exception $oException)
				{
					$iErrorCode = \System\Notifications::UnknownError;
					if ($oException instanceof \CApiManagerException)
					{
						switch ($oException->getCode())
						{
							case \Errs::WebMailManager_AccountDisabled:
							case \Errs::WebMailManager_AccountWebmailDisabled:
								$iErrorCode = \System\Notifications::AuthError;
								break;
							case \Errs::UserManager_AccountAuthenticationFailed:
							case \Errs::WebMailManager_AccountAuthentication:
							case \Errs::WebMailManager_NewUserRegistrationDisabled:
							case \Errs::WebMailManager_AccountCreateOnLogin:
							case \Errs::Mail_AccountAuthentication:
							case \Errs::Mail_AccountLoginFailed:
								$iErrorCode = \System\Notifications::AuthError;
								break;
							case \Errs::UserManager_AccountConnectToMailServerFailed:
							case \Errs::WebMailManager_AccountConnectToMailServerFailed:
							case \Errs::Mail_AccountConnectToMailServerFailed:
								$iErrorCode = \System\Notifications::MailServerError;
								break;
							case \Errs::UserManager_LicenseKeyInvalid:
							case \Errs::UserManager_AccountCreateUserLimitReached:
							case \Errs::UserManager_LicenseKeyIsOutdated:
							case \Errs::TenantsManager_AccountCreateUserLimitReached:
								$iErrorCode = \System\Notifications::LicenseProblem;
								break;
							case \Errs::Db_ExceptionError:
								$iErrorCode = \System\Notifications::DataBaseError;
								break;
						}
					}
					$sReditectUrl = \CApi::GetConf('labs.post-login-error-redirect-url', './');
					\CApi::Location($sReditectUrl . '?error=' . $iErrorCode);
					exit;
				}

				if ($oAccount instanceof \CAccount)
				{
					$oApiIntegrator->setAccountAsLoggedIn($oAccount);
				}
			}

			\CApi::Location('./');
		}
	}
	
	/**
	 * Clears temporary files by cron.
	 * 
	 * @ignore
	 * @todo check if it works.
	 * 
	 * @return bool
	 */
	public function ClearTempFiles()
	{
		$sTempPath = CApi::DataPath().'/temp';
		if (@is_dir($sTempPath))
		{
			$iNow = time();

			$iTime2Run = CApi::GetConf('temp.cron-time-to-run', 10800);
			$iTime2Kill = CApi::GetConf('temp.cron-time-to-kill', 10800);
			$sDataFile = CApi::GetConf('temp.cron-time-file', '.clear.dat');

			$iFiletTime = -1;
			if (@file_exists(CApi::DataPath().'/'.$sDataFile))
			{
				$iFiletTime = (int) @file_get_contents(CApi::DataPath().'/'.$sDataFile);
			}

			if ($iFiletTime === -1 || $iNow - $iFiletTime > $iTime2Run)
			{
				$this->removeDirByTime($sTempPath, $iTime2Kill, $iNow);
				@file_put_contents( CApi::DataPath().'/'.$sDataFile, $iNow);
			}
		}

		return true;
	}
	
	/**
	 * Updates user by object.
	 * 
	 * @param \CUser $oUser
	 */
	public function UpdateUserObject($oUser)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$this->oApiUsersManager->updateUser($oUser);
	}
	
	/**
	 * Returns user object.
	 * 
	 * @param int $UserId User identificator.
	 * @return \CUser
	 */
	public function GetUser($UserId = 0)
	{
		// doesn't call checkUserRoleIsAtLeast because checkUserRoleIsAtLeast functin calls GetUser function
		
		$oUser = $this->oApiUsersManager->getUserById((int) $UserId);
		
		return $oUser ? $oUser : null;
	}
	
	/**
	 * Creates and returns user with super administrator role.
	 * 
	 * @return \CUser
	 */
	public function GetAdminUser()
	{
		// doesn't call checkUserRoleIsAtLeast because checkUserRoleIsAtLeast function calls GetAdminUser function
		
		$oUser = new \CUser('Core', array());
		$oUser->iId = -1;
		$oUser->Role = \EUserRole::SuperAdmin;
		$oUser->PublicId = 'Administrator';
		
		return $oUser;
	}
	
	/**
	 * Returns tenant object by identificator.
	 * 
	 * @param int $iIdTenant Tenane id.
	 * @return \CTenant
	 */
	public function GetTenantById($iIdTenant)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$oTenant = $this->oApiTenantsManager->getTenantById($iIdTenant);

		return $oTenant ? $oTenant : null;
	}
	
	/**
	 * Returns default global tenant.
	 * 
	 * @return \CTenant
	 */
	public function GetDefaultGlobalTenant()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$oTenant = $this->oApiTenantsManager->getDefaultGlobalTenant();
		
		return $oTenant ? $oTenant : null;
	}
	/***** public functions *****/
	
	/***** public functions might be called with web API *****/
	/**
	 * @api {post} ?/Api/ DoServerInitializations
	 * @apiName DoServerInitializations
	 * @apiGroup Core
	 * @apiDescription Does some pending actions to be executed when you log in.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=DoServerInitializations} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DoServerInitializations',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if server initializations were made successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DoServerInitializations',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DoServerInitializations',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Does some pending actions to be executed when you log in.
	 * 
	 * @return bool
	 */
	public function DoServerInitializations()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Customer);
		
		$iUserId = \CApi::getAuthenticatedUserId();

		$bResult = false;

		$oApiIntegrator = \CApi::GetSystemManager('integrator');

		if ($iUserId && $oApiIntegrator)
		{
			$oApiIntegrator->resetCookies();
		}

		if ($this->oApiCapabilityManager->isGlobalContactsSupported($iUserId, true))
		{
			$bResult = \CApi::ExecuteMethod('Contact::SynchronizeExternalContacts', array('UserId' => $iUserId));
		}

		$oCacher = \CApi::Cacher();

		$bDoGC = false;
		$bDoHepdeskClear = false;
		if ($oCacher && $oCacher->IsInited())
		{
			$iTime = $oCacher->GetTimer('Cache/ClearFileCache');
			if (0 === $iTime || $iTime + 60 * 60 * 24 < time())
			{
				if ($oCacher->SetTimer('Cache/ClearFileCache'))
				{
					$bDoGC = true;
				}
			}

			if (\CApi::GetModuleManager()->ModuleExists('Helpdesk'))
			{
				$iTime = $oCacher->GetTimer('Cache/ClearHelpdeskUsers');
				if (0 === $iTime || $iTime + 60 * 60 * 24 < time())
				{
					if ($oCacher->SetTimer('Cache/ClearHelpdeskUsers'))
					{
						$bDoHepdeskClear = true;
					}
				}
			}
		}

		if ($bDoGC)
		{
			\CApi::Log('GC: FileCache / Start');
			$oApiFileCache = \Capi::GetSystemManager('filecache');
			$oApiFileCache->gc();
			$oCacher->gc();
			\CApi::Log('GC: FileCache / End');
		}

		if ($bDoHepdeskClear && \CApi::GetModuleManager()->ModuleExists('Helpdesk'))
		{
			\CApi::ExecuteMethod('Helpdesk::ClearUnregistredUsers');
			\CApi::ExecuteMethod('Helpdesk::ClearAllOnline');
		}

		return $bResult;
	}
	
	/**
	 * @api {post} ?/Api/ Ping
	 * @apiName Ping
	 * @apiGroup Core
	 * @apiDescription Method is used for checking internet connection.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=Ping} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'Ping',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {string} Result Just a string to indicate that connection to backend is working.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'Ping',
	 *	Result: 'Pong'
	 * }
	 */
	/**
	 * Method is used for checking internet connection.
	 * 
	 * @return 'Pong'
	 */
	public function Ping()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		return 'Pong';
	}	
	
	/**
	 * @api {post} ?/Api/ GetAppData
	 * @apiName GetAppData
	 * @apiGroup Core
	 * @apiDescription Obtaines list of module settings for authenticated user.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=GetAppData} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetAppData',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {mixed} Result List of module settings in case of success, otherwise **false**.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetAppData',
	 *	Result: { SiteName: "Aurora Cloud", DefaultLanguage: "English", DefaultTimeFormat: 1, DefaultDateFormat: "MM/DD/YYYY", AppStyleImage: "", EUserRole: { SuperAdmin: 0, TenantAdmin: 1, NormalUser: 2, Customer: 3, Anonymous: 4 } }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetAppData',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtaines list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetAppData()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$oUser = \CApi::getAuthenticatedUser();
		
		$aSettings = array(
			'SiteName' => \CApi::GetSettingsConf('SiteName'),
			'DefaultLanguage' => \CApi::GetSettingsConf('DefaultLanguage'),
			'DefaultTimeFormat' => \CApi::GetSettingsConf('DefaultTimeFormat'),
			'DefaultDateFormat' => \CApi::GetSettingsConf('DefaultDateFormat'),
			'AppStyleImage' => \CApi::GetSettingsConf('AppStyleImage'),
			'EUserRole' => (new \EUserRole)->getMap(),
		);
		
		if (!empty($oUser) && $oUser->Role === \EUserRole::SuperAdmin)
		{
			$aSettings = array_merge($aSettings, array(
				'LicenseKey' => \CApi::GetSettingsConf('LicenseKey'),
				'DBHost' => \CApi::GetSettingsConf('DBHost'),
				'DBName' => \CApi::GetSettingsConf('DBName'),
				'DBLogin' => \CApi::GetSettingsConf('DBLogin'),
				'AdminLogin' => \CApi::GetSettingsConf('AdminLogin'),
				'AdminHasPassword' => !empty(\CApi::GetSettingsConf('AdminPassword')),
				'EnableLogging' => \CApi::GetSettingsConf('EnableLogging'),
				'EnableEventLogging' => \CApi::GetSettingsConf('EnableEventLogging'),
				'LoggingLevel' => \CApi::GetSettingsConf('LoggingLevel'),
			));
		}
		
		return $aSettings;
	}
	
	/**
	 * @api {post} ?/Api/ UpdateSettings
	 * @apiName UpdateSettings
	 * @apiGroup Core
	 * @apiDescription Updates specified settings if super administrator is authenticated.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=UpdateSettings} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **LicenseKey** *string* Value of license key.<br>
	 * &emsp; **DbLogin** *string* Database login.<br>
	 * &emsp; **DbPassword** *string* Database password.<br>
	 * &emsp; **DbName** *string* Database name.<br>
	 * &emsp; **DbHost** *string* Database host.<br>
	 * &emsp; **AdminLogin** *string* Login for super administrator.<br>
	 * &emsp; **Password** *string* Current password for super administrator.<br>
	 * &emsp; **NewPassword** *string* New password for super administrator.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateSettings',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ LicenseKey: "license_key_value", DbLogin: "login_value", DbPassword: "password_value", DbName: "db_name_value", DbHost: "host_value", AdminLogin: "admin_login_value", Password: "admin_pass_value", NewPassword: "admin_pass_value" }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if settings were updated successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateSettings',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateSettings',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Updates specified settings if super administrator is authenticated.
	 * 
	 * @param string $LicenseKey Value of license key.
	 * @param string $DbLogin Database login.
	 * @param string $DbPassword Database password.
	 * @param string $DbName Database name.
	 * @param string $DbHost Database host.
	 * @param string $AdminLogin Login for super administrator.
	 * @param string $Password Current password for super administrator.
	 * @param string $NewPassword New password for super administrator.
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function UpdateSettings($LicenseKey = null, $DbLogin = null, 
			$DbPassword = null, $DbName = null, $DbHost = null,
			$AdminLogin = null, $Password = null, $NewPassword = null)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		
		$oSettings =& CApi::GetSettings();
		if ($LicenseKey !== null)
		{
			$oSettings->SetConf('LicenseKey', $LicenseKey);
		}
		if ($DbLogin !== null)
		{
			$oSettings->SetConf('DBLogin', $DbLogin);
		}
		if ($DbPassword !== null)
		{
			$oSettings->SetConf('DBPassword', $DbPassword);
		}
		if ($DbName !== null)
		{
			$oSettings->SetConf('DBName', $DbName);
		}
		if ($DbHost !== null)
		{
			$oSettings->SetConf('DBHost', $DbHost);
		}
		if ($AdminLogin !== null && $AdminLogin !== $oSettings->GetConf('AdminLogin'))
		{
			$this->broadcastEvent('CheckAccountExists', array($AdminLogin));
		
			$oSettings->SetConf('AdminLogin', $AdminLogin);
		}
		if ((empty($oSettings->GetConf('AdminPassword')) && empty($Password) || !empty($Password)) && !empty($NewPassword))
		{
			if (empty($oSettings->GetConf('AdminPassword')) || 
					crypt(trim($Password), \CApi::$sSalt) === $oSettings->GetConf('AdminPassword'))
			{
				$oSettings->SetConf('AdminPassword', crypt(trim($NewPassword), \CApi::$sSalt));
			}
			else
			{
				throw new \System\Exceptions\AuroraApiException(Errs::UserManager_AccountOldPasswordNotCorrect);
			}
		}
		return $oSettings->Save();
	}
	
	/**
	 * @ignore
	 * Turns on or turns off mobile version.
	 * @param bool $Mobile Indicates if mobile version should be turned on or turned off.
	 * @return bool
	 */
	public function SetMobile($Mobile)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$oApiIntegratorManager = \CApi::GetSystemManager('integrator');
		return $oApiIntegratorManager ? $oApiIntegratorManager->setMobile($Mobile) : false;
	}	
	
	/**
	 * @api {post} ?/Api/ CreateTables
	 * @apiName CreateTables
	 * @apiGroup Core
	 * @apiDescription Creates tables reqired for module work. Creates first channel and tenant if it is necessary.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=CreateTables} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateTables',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if tables was created successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateTables',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateTables',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Creates tables reqired for module work. Creates first channel and tenant if it is necessary.
	 * 
	 * @return bool
	 */
	public function CreateTables()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		
		$bResult = false;
		$oSettings =& CApi::GetSettings();
		$oApiEavManager = CApi::GetSystemManager('eav', 'db');
		if ($oApiEavManager->createTablesFromFile())
		{
			if ($oSettings->GetConf('EnableMultiChannel') && $oSettings->GetConf('EnableMultiTenant'))
			{
				$bResult = true;
			}
			else
			{
				$iChannelId = 0;
				$aChannels = $this->oApiChannelsManager->getChannelList(0, 1);
				if (is_array($aChannels) && count($aChannels) === 1)
				{
					$iChannelId = $aChannels[0]->iId;
				}
				else
				{
					$iChannelId = $this->CreateChannel('Default', '');
				}
				if ($iChannelId !== 0)
				{
					if ($oSettings->GetConf('EnableMultiTenant'))
					{
						$bResult = true;
					}
					else
					{
						$aTenants = $this->oApiTenantsManager->getTenantsByChannelId($iChannelId);
						if (is_array($aTenants) && count($aTenants) === 1)
						{
							$bResult = true;
						}
						else
						{
							$mTenantId = $this->CreateTenant($iChannelId, 'Default');
							if (is_int($mTenantId))
							{
								$bResult = true;
							}
						}
					}
				}
			}
		}
		
		return $bResult;
	}
	
	/**
	 * @api {post} ?/Api/ TestDbConnection
	 * @apiName TestDbConnection
	 * @apiGroup Core
	 * @apiDescription Tests connection to database with specified credentials.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=TestDbConnection} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **DbLogin** *string* Database login.<br>
	 * &emsp; **DbName** *string* Database name.<br>
	 * &emsp; **DbHost** *string* Database host.<br>
	 * &emsp; **DbPassword** *string* Database password.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'TestDbConnection',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ DbLogin: "db_login_value", DbName: "db_name_value", DbHost: "db_host_value", DbPassword: "db_pass_value" }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if test of database connection was successful.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'TestDbConnection',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'TestDbConnection',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Tests connection to database with specified credentials.
	 * 
	 * @param string $DbLogin Database login.
	 * @param string $DbName Database name.
	 * @param string $DbHost Database host.
	 * @param string $DbPassword Database password.
	 * @return bool
	 */
	public function TestDbConnection($DbLogin, $DbName, $DbHost, $DbPassword = null)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		
		$oSettings =& CApi::GetSettings();
		$oSettings->SetConf('DBLogin', $DbLogin);
		if ($DbPassword !== null)
		{
			$oSettings->SetConf('DBPassword', $DbPassword);
		}
		$oSettings->SetConf('DBName', $DbName);
		$oSettings->SetConf('DBHost', $DbHost);
		
		$oApiEavManager = CApi::GetSystemManager('eav', 'db');
		return $oApiEavManager->testStorageConnection();
	}
	
	/**
	 * @api {post} ?/Api/ Logout
	 * @apiName Logout
	 * @apiGroup Core
	 * @apiDescription Logs out authenticated user. Clears session.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=Logout} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'Logout',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if logout was successful.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'Logout',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'Logout',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Logs out authenticated user. Clears session.
	 * 
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function Logout()
	{	
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$mAuthToken = \CApi::getAuthenticatedUserAuthToken();
		
		if ($mAuthToken !== false)
		{
			\CApi::UserSession()->Delete($mAuthToken);
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\Auth\Notifications::IncorrentAuthToken);
		}

		return true;
	}
	
	/**
	 * @api {post} ?/Api/ GetEntityList
	 * @apiName GetEntityList
	 * @apiGroup Core
	 * @apiDescription Returns entity list.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=GetEntityList} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Entities type.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetEntityList',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "Tenant" }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {mixed} Result Array of objects in case of success, otherwise **false**.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetEntityList',
	 *	Result: [{ Id: 123, UUID: "", PublicId: "PublicId_value123" }, { Id: 124, UUID: "", PublicId: "PublicId_value124" }]
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetEntityList',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Returns entity list.
	 * 
	 * @param string $Type Entities type.
	 * @return array|null
	 */
	public function GetEntityList($Type)
	{
		switch ($Type)
		{
			case 'Tenant':
				return $this->GetTenantList();
			case 'User':
				return $this->GetUserList();
		}
		return null;
	}
	
	/**
	 * @api {post} ?/Api/ GetEntity
	 * @apiName GetEntity
	 * @apiGroup Core
	 * @apiDescription Returns entity.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=GetEntity} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Entity type.<br>
	 * &emsp; **Id** *int* Entity identificator.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetEntity',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "User", Id: 123 }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {mixed} Result Object in case of success, otherwise **false**.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetEntity',
	 *	Result: { PublicId: "PublicId_value", Role: 2 }
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetEntity',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Returns entity.
	 * 
	 * @param string $Type Entity type.
	 * @param int $Id Entity identificator.
	 * @return array
	 */
	public function GetEntity($Type, $Id)
	{
		switch ($Type)
		{
			case 'Tenant':
				return $this->GetTenantById($Id);
			case 'User':
				return $this->GetUser($Id);
		}
		return null;
	}
	
	/**
	 * @api {post} ?/Api/ CreateEntity
	 * @apiName CreateEntity
	 * @apiGroup Core
	 * @apiDescription Creates entity.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=CreateEntity} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Entity type.<br>
	 * &emsp; **Data** *array* Entity data which fields depend on entity type.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateEntity',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "Tenant", Data: { PublicId: "PublicId_value", Description: "description_value" } }'
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateEntity',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "User", Data: { PublicId: "PublicId_value", Role: 2 } }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if entity was created successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateEntity',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateEntity',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Creates entity.
	 * 
	 * @param string $Type Entity type.
	 * @param array $Data Entity data which fields depend on entity type.
	 * @return bool
	 */
	public function CreateEntity($Type, $Data)
	{
		switch ($Type)
		{
			case 'Tenant':
				return $this->CreateTenant(0, $Data['Name'], $Data['Description']);
			case 'User':
				return $this->CreateUser(0, $Data['PublicId'], $Data['Role']);
		}
		return false;
	}
	
	/**
	 * @api {post} ?/Api/ UpdateEntity
	 * @apiName UpdateEntity
	 * @apiGroup Core
	 * @apiDescription Updates entity.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=UpdateEntity} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Entity type.<br>
	 * &emsp; **Data** *array* Entity data.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateEntity',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "Tenant", Data: { Id: 123, PublicId: "PublicId_value", Description: "description_value" } }'
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateEntity',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "User", Data: { Id: 123, PublicId: "PublicId_value", Role: 2 } }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if entity was updated successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateEntity',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateEntity',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Updates entity.
	 * 
	 * @param string $Type Entity type.
	 * @param array $Data Entity data.
	 * @return bool
	 */
	public function UpdateEntity($Type, $Data)
	{
		switch ($Type)
		{
			case 'Tenant':
				return $this->UpdateTenant($Data['Id'], $Data['PublicId'], $Data['Description']);
			case 'User':
				return $this->UpdateUser($Data['Id'], $Data['PublicId'], 0, $Data['Role']);
		}
		return false;
	}
	
	/**
	 * @api {post} ?/Api/ DeleteEntity
	 * @apiName DeleteEntity
	 * @apiGroup Core
	 * @apiDescription Deletes entity.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=DeleteEntity} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Type** *string* Entity type.<br>
	 * &emsp; **Id** *int* Entity identificator.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DeleteEntity',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Type: "Tenant", Id: 123 }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if entity was deleted successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DeleteEntity',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DeleteEntity',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Deletes entity.
	 * 
	 * @param string $Type Entity type
	 * @param int $Id Entity identificator.
	 * @return bool
	 */
	public function DeleteEntity($Type, $Id)
	{
		switch ($Type)
		{
			case 'Tenant':
				return $this->DeleteTenant($Id);
			case 'User':
				return $this->DeleteUser($Id);
		}
		return false;
	}
	
	/**
	 * @api {post} ?/Api/ CreateChannel
	 * @apiName CreateChannel
	 * @apiGroup Core
	 * @apiDescription Creates channel with specified login and description.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=CreateChannel} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Login** *string* New channel login.<br>
	 * &emsp; **Description** *string* New channel description.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateChannel',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Login: "login_value", Description: "description_value" }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {mixed} Result New channel identificator in case of success, otherwise **false**.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateChannel',
	 *	Result: 123
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateChannel',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Creates channel with specified login and description.
	 * 
	 * @param string $Login New channel login.
	 * @param string $Description New channel description.
	 * @return int New channel identificator.
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function CreateChannel($Login, $Description = '')
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		
		if ($Login !== '')
		{
			$oChannel = \CChannel::createInstance();
			
			$oChannel->Login = $Login;
			
			if ($Description !== '')
			{
				$oChannel->Description = $Description;
			}

			if ($this->oApiChannelsManager->createChannel($oChannel))
			{
				return $oChannel->iId;
			}
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::InvalidInputParameter);
		}
	}
	
	/**
	 * @api {post} ?/Api/ UpdateChannel
	 * @apiName UpdateChannel
	 * @apiGroup Core
	 * @apiDescription Updates channel.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=UpdateChannel} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **ChannelId** *int* Channel identificator.<br>
	 * &emsp; **Login** *string* New login for channel.<br>
	 * &emsp; **Description** *string* New description for channel.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateChannel',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ ChannelId: 123, Login: "login_value", Description: "description_value" }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if channel was updated successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateChannel',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateChannel',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Updates channel.
	 * 
	 * @param int $ChannelId Channel identificator.
	 * @param string $Login New login for channel.
	 * @param string $Description New description for channel.
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function UpdateChannel($ChannelId, $Login = '', $Description = '')
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		
		if ($ChannelId > 0)
		{
			$oChannel = $this->oApiChannelsManager->getChannelById($ChannelId);
			
			if ($oChannel)
			{
				if ($Login)
				{
					$oChannel->Login = $Login;
				}
				if ($Description)
				{
					$oChannel->Description = $Description;
				}
				
				return $this->oApiChannelsManager->updateChannel($oChannel);
			}
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::InvalidInputParameter);
		}

		return false;
	}
	
	/**
	 * @api {post} ?/Api/ DeleteChannel
	 * @apiName DeleteChannel
	 * @apiGroup Core
	 * @apiDescription Deletes channel.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=DeleteChannel} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **ChannelId** *int* Identificator of channel to delete.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DeleteChannel',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ ChannelId: 123 }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if channel was deleted successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DeleteChannel',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DeleteChannel',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Deletes channel.
	 * 
	 * @param int $ChannelId Identificator of channel to delete.
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function DeleteChannel($ChannelId)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);

		if ($ChannelId > 0)
		{
			$oChannel = $this->oApiChannelsManager->getChannelById($ChannelId);
			
			if ($oChannel)
			{
				return $this->oApiChannelsManager->deleteChannel($oChannel);
			}
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::InvalidInputParameter);
		}

		return false;
	}
	
	/**
	 * @api {post} ?/Api/ GetTenantList
	 * @apiName GetTenantList
	 * @apiGroup Core
	 * @apiDescription Obtains tenant list if super administrator is authenticated.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=GetTenantList} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetTenantList',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {mixed} Result List of tenants in case of success, otherwise **false**.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetTenantList',
	 *	Result: [{ Id: 123, Name: "name_value" }]
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetTenantList',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Obtains tenant list if super administrator is authenticated.
	 * 
	 * @return array {
	 *		*int* **Id** Tenant identificator
	 *		*string* **Name** Tenant name
	 * }
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function GetTenantList()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		
		$aTenants = $this->oApiTenantsManager->getTenantList();
		$aItems = array();

		foreach ($aTenants as $oTenat)
		{
			$aItems[] = array(
				'Id' => $oTenat->iId,
				'Name' => $oTenat->Name
			);
		}
		
		return $aItems;
	}
	
	/**
	 * @api {post} ?/Api/ GetTenantIdByName
	 * @apiName GetTenantIdByName
	 * @apiGroup Core
	 * @apiDescription Returns tenant identificator by tenant name.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=GetTenantIdByName} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **TenantName** *string* Tenant name.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetTenantIdByName',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ TenantName: "name_value" }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {mixed} Result Tenant identificator in case of success, otherwise **false**.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetTenantIdByName',
	 *	Result: 123
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetTenantIdByName',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Returns tenant identificator by tenant name.
	 * 
	 * @param string $TenantName Tenant name.
	 * @return int|null
	 */
	public function GetTenantIdByName($TenantName = '')
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$iTenantId = $this->oApiTenantsManager->getTenantIdByName((string) $TenantName);

		return $iTenantId ? $iTenantId : null;
	}
	
	/**
	 * @api {post} ?/Api/ GetTenantName
	 * @apiName GetTenantName
	 * @apiGroup Core
	 * @apiDescription Returns current tenant name.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=GetTenantName} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetTenantName',
	 *	AuthToken: 'token_value'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {mixed} Result Tenant name for authenticated user in case of success, otherwise **false**.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetTenantName',
	 *	Result: 'TenantName'
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetTenantName',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Returns current tenant name.
	 * 
	 * @return string
	 */
	public function GetTenantName()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$sTenant = '';
		$sAuthToken = \CApi::getAuthToken();
		if (!empty($sAuthToken))
		{
			$iUserId = \CApi::getAuthenticatedUserId();
			if ($iUserId !== false && $iUserId > 0)
			{
				$oUser = $this->GetUser($iUserId);
				if ($oUser)
				{
					$oTenant = $this->GetTenantById($oUser->IdTenant);
					if ($oTenant)
					{
						$sTenant = $oTenant->Name;
					}
				}
			}
			$sPostTenant = $this->oHttp->GetPost('TenantName', '');
			if (!empty($sPostTenant) && !empty($sTenant) && $sPostTenant !== $sTenant)
			{
				$sTenant = '';
			}
		}
		else
		{
			$sTenant = $this->oHttp->GetRequest('tenant', '');
		}
		\CApi::setTenantName($sTenant);
		return $sTenant;
	}
	
	/**
	 * @api {post} ?/Api/ CreateTenant
	 * @apiName CreateTenant
	 * @apiGroup Core
	 * @apiDescription Creates tenant.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=CreateTenant} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **ChannelId** *int* Identificator of channel new tenant belongs to.<br>
	 * &emsp; **Name** *string* New tenant name.<br>
	 * &emsp; **Description** *string* New tenant description.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateTenant',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ ChannelId: 123, Name: "name_value", Description: "description_value" }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if tenant was created successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateTenant',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateTenant',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Creates tenant.
	 * 
	 * @param int $ChannelId Identificator of channel new tenant belongs to.
	 * @param string $Name New tenant name.
	 * @param string $Description New tenant description.
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function CreateTenant($ChannelId = 0, $Name = '', $Description = '')
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		
		$oSettings =& CApi::GetSettings();
		if (!$oSettings->GetConf('EnableMultiChannel') && $ChannelId === 0)
		{
			$aChannels = $this->oApiChannelsManager->getChannelList(0, 1);
			$ChannelId = count($aChannels) === 1 ? $aChannels[0]->iId : 0;
		}
		
		if ($Name !== '' && $ChannelId > 0)
		{
			$oTenant = \CTenant::createInstance();

			$oTenant->Name = $Name;
			$oTenant->Description = $Description;
			$oTenant->IdChannel = $ChannelId;

			if ($this->oApiTenantsManager->createTenant($oTenant))
			{
				return $oTenant->iId;
			}
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::InvalidInputParameter);
		}

		return false;
	}
	
	/**
	 * @api {post} ?/Api/ UpdateTenant
	 * @apiName UpdateTenant
	 * @apiGroup Core
	 * @apiDescription Updates tenant.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=UpdateTenant} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **TenantId** *int* Identificator of tenant to update.<br>
	 * &emsp; **Name** *string* New tenant name.<br>
	 * &emsp; **Description** *string* New tenant description.<br>
	 * &emsp; **ChannelId** *int* Identificator of the new tenant channel.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateTenant',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ TenantId: 123, Name: "name_value", Description: "description_value", ChannelId: 123 }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if tenant was updated successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateTenant',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateTenant',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Updates tenant.
	 * 
	 * @param int $TenantId Identificator of tenant to update.
	 * @param string $Name New tenant name.
	 * @param string $Description New tenant description.
	 * @param int $ChannelId Identificator of the new tenant channel.
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function UpdateTenant($TenantId, $Name = '', $Description = '', $ChannelId = 0)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		
		if (!empty($TenantId))
		{
			$oTenant = $this->oApiTenantsManager->getTenantById($TenantId);
			
			if ($oTenant)
			{
				if (!empty($Name))
				{
					$oTenant->Name = $Name;
				}
				if (!empty($Description))
				{
					$oTenant->Description = $Description;
				}
				if (!empty($ChannelId))
				{
					$oTenant->IdChannel = $ChannelId;
				}
				
				return $this->oApiTenantsManager->updateTenant($oTenant);
			}
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::InvalidInputParameter);
		}

		return false;
	}
	
	/**
	 * @api {post} ?/Api/ DeleteTenant
	 * @apiName DeleteTenant
	 * @apiGroup Core
	 * @apiDescription Deletes tenant.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=DeleteTenant} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **TenantId** *int* Identificator of tenant to delete.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DeleteTenant',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ TenantId: 123 }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if tenant was deleted successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DeleteTenant',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DeleteTenant',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Deletes tenant.
	 * 
	 * @param int $TenantId Identificator of tenant to delete.
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function DeleteTenant($TenantId)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		
		if (!empty($TenantId))
		{
			$oTenant = $this->oApiTenantsManager->getTenantById($TenantId);
			
			if ($oTenant)
			{
				$sTenantSpacePath = AURORA_APP_ROOT_PATH.'tenants/'.$oTenant->Name;
				
				if (@is_dir($sTenantSpacePath))
				{
					$this->deleteTree($sTenantSpacePath);
				}
						
				return $this->oApiTenantsManager->deleteTenant($oTenant);
			}
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::InvalidInputParameter);
		}

		return false;
	}
	
	/**
	 * @api {post} ?/Api/ GetUserList
	 * @apiName GetUserList
	 * @apiGroup Core
	 * @apiDescription Returns user list.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=GetUserList} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **Offset** *int* Offset of user list.<br>
	 * &emsp; **Limit** *int* Limit of result user list.<br>
	 * &emsp; **OrderBy** *string* Name of field order by.<br>
	 * &emsp; **OrderType** *int* Order type.<br>
	 * &emsp; **Search** *string* Search string.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetUserList',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ Offset: 0, Limit: 0, OrderBy: "", OrderType: 0, Search: 0 }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {mixed} Result List of users in case of success, otherwise **false**.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetUserList',
	 *	Result: [{ Id: 123, PublicId: 'user123_PublicId' }, { Id: 124, PublicId: 'user124_PublicId' }]
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'GetUserList',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Returns user list.
	 * 
	 * @param int $Offset Offset of user list.
	 * @param int $Limit Limit of result user list.
	 * @param string $OrderBy Name of field order by.
	 * @param int $OrderType Order type.
	 * @param string $Search Search string.
	 * @return array {
	 *		*int* **Id** Identificator of user.
	 *		*string* **PublicId** User name.
	 * }
	 */
	public function GetUserList($Offset = 0, $Limit = 0, $OrderBy = 'PublicId', $OrderType = \ESortOrder::ASC, $Search = '')
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::TenantAdmin);
		
		$aResults = $this->oApiUsersManager->getUserList($Offset, $Limit, $OrderBy, $OrderType, $Search);
		$aUsers = array();
		foreach($aResults as $oUser)
		{
			$aUsers[] = array(
				'Id' => $oUser->iId,
				'UUID' => $oUser->sUUID,
				'Name' => $oUser->Name,
				'PublicId' => $oUser->PublicId
			);
		}
		return $aUsers;
	}

	/**
	 * @api {post} ?/Api/ CreateUser
	 * @apiName CreateUser
	 * @apiGroup Core
	 * @apiDescription Creates user.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=CreateUser} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **TenantId** *int* Identificator of tenant that will contain new user.<br>
	 * &emsp; **PublicId** *string* New user name.<br>
	 * &emsp; **Role** *int* New user role.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateUser',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ TenantId: 123, PublicId: "PublicId_value", Role: 2 }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {mixed} Result User identificator in case of success, otherwise **false**.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateUser',
	 *	Result: 123
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'CreateUser',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Creates user.
	 * 
	 * @param int $TenantId Identificator of tenant that will contain new user.
	 * @param string $PublicId New user name.
	 * @param int $Role New user role.
	 * @return int|false
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function CreateUser($TenantId = 0, $PublicId = '', $Role = \EUserRole::NormalUser)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::TenantAdmin);
		
		$oSettings =& CApi::GetSettings();
		if (!$oSettings->GetConf('EnableMultiTenant') && $TenantId === 0)
		{
			$aTenants = $this->oApiTenantsManager->getTenantList(0, 1);
			$TenantId = count($aTenants) === 1 ? $aTenants[0]->iId : 0;
		}
		
		if (!empty($TenantId) && !empty($PublicId))
		{
			$oUser = \CUser::createInstance();
			
			$oUser->PublicId = $PublicId;
			$oUser->IdTenant = $TenantId;
			$oUser->Role = $Role;

			if ($this->oApiUsersManager->createUser($oUser))
			{
				return $oUser->iId;
			}
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::InvalidInputParameter);
		}

		return false;
	}

	/**
	 * @api {post} ?/Api/ UpdateUser
	 * @apiName UpdateUser
	 * @apiGroup Core
	 * @apiDescription Updates user.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=UpdateUser} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **UserId** *int* Identificator of user to update.<br>
	 * &emsp; **UserName** *string* New user name.<br>
	 * &emsp; **TenantId** *int* Identificator of tenant that will contain the user.<br>
	 * &emsp; **Role** *int* New user role.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateUser',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ UserId: 123, UserName: "name_value", TenantId: 123, Role: 2 }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if user was updated successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateUser',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'UpdateUser',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Updates user.
	 * 
	 * @param int $UserId Identificator of user to update.
	 * @param string $PublicId New user name.
	 * @param int $TenantId Identificator of tenant that will contain the user.
	 * @param int $Role New user role.
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function UpdateUser($UserId, $PublicId = '', $TenantId = 0, $Role = -1)
	{
		if (!empty($PublicId) && empty($TenantId) && $Role === -1 && $UserId === \CApi::getAuthenticatedUserId())
		{
			\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		}
		else
		{
			\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		}
		
		if ($UserId > 0)
		{
			$oUser = $this->oApiUsersManager->getUserById($UserId);
			
			if ($oUser)
			{
				if (!empty($PublicId))
				{
					$oUser->PublicId = $PublicId;
				}
				if (!empty($TenantId))
				{
					$oUser->IdTenant = $TenantId;
				}
				if ($Role !== -1)
				{
					$oUser->Role = $Role;
				}
				
				return $this->oApiUsersManager->updateUser($oUser);
			}
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::InvalidInputParameter);
		}

		return false;
	}
	
	/**
	 * @api {post} ?/Api/ DeleteUser
	 * @apiName DeleteUser
	 * @apiGroup Core
	 * @apiDescription Deletes user.
	 * 
	 * @apiParam {string=Core} Module Module name.
	 * @apiParam {string=DeleteUser} Method Method name.
	 * @apiParam {string} AuthToken Auth token.
	 * @apiParam {string} Parameters JSON.stringified object <br>
	 * {<br>
	 * &emsp; **UserId** *int* User identificator.<br>
	 * }
	 * 
	 * @apiParamExample {json} Request-Example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DeleteUser',
	 *	AuthToken: 'token_value',
	 *	Parameters: '{ UserId: 123 }'
	 * }
	 * 
	 * @apiSuccess {string} Module Module name.
	 * @apiSuccess {string} Method Method name.
	 * @apiSuccess {bool} Result Indicates if user was deleted successfully.
	 * @apiSuccess {int} [ErrorCode] Error code.
	 * 
	 * @apiSuccessExample {json} Success response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DeleteUser',
	 *	Result: true
	 * }
	 * 
	 * @apiSuccessExample {json} Error response example:
	 * {
	 *	Module: 'Core',
	 *	Method: 'DeleteUser',
	 *	Result: false,
	 *	ErrorCode: 102
	 * }
	 */
	/**
	 * Deletes user.
	 * 
	 * @param int $UserId User identificator.
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function DeleteUser($UserId = 0)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::TenantAdmin);
		
		$bResult = false;
		
		if (!empty($UserId))
		{
			$oUser = $this->oApiUsersManager->getUserById($UserId);
			
			if ($oUser)
			{
				$bResult = $this->oApiUsersManager->deleteUser($oUser);
				$this->broadcastEvent($this->GetName() . \AApiModule::$Delimiter . 'AfterDeleteUser', array($oUser->iId));
			}
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::InvalidInputParameter);
		}

		return $bResult;
	}
	/***** public functions might be called with web API *****/
}
