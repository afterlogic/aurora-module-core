<?php

class CoreModule extends AApiModule
{
	public $oApiTenantsManager = null;
	
	public $oApiChannelsManager = null;
	
	public $oApiUsersManager = null;
	
	protected $aSettingsMap = array(
		'LoggingLevel' => array(ELogLevel::Full, 'spec', 'ELogLevel'),
	);

	/**
	 * Initializes Core Module.
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
	
	
	/***** private functions *****/
	/**
	 * Is called by CreateAccount event. Finds or creates and returns User for new account.
	 * 
	 * @ignore
	 * @param array $aData {
	 *		*int* **UserId** Identificator of existing user.
	 *		*int* **TenantId** Identificator of tenant for creating new user in it.
	 *		*int* **UserName** New user name.
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

			$sUserName = (isset($aData['UserName'])) ? $aData['UserName'] : '';
			if ($sUserName)
			{
				$oUser->Name = $sUserName;
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
	 * @param int $iUserId User identificator.
	 * @return \CUser
	 */
	public function GetUser($iUserId = 0)
	{
		// doesn't call checkUserRoleIsAtLeast because checkUserRoleIsAtLeast functin calls GetUser function
		
		$oUser = $this->oApiUsersManager->getUserById((int) $iUserId);
		
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
		$oUser->Name = 'Administrator';
		
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
	 * Obtaines module settings for authenticated user.
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
	 * 
	 * @return bool
	 * 
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
				$aChannels = $this->oApiChannelsManager->getChannelList(0, 1);
				$iChannelId = count($aChannels) === 1 ? $aChannels[0]->iId : 0;
				return $this->CreateTenant($iChannelId, $Data['Name'], $Data['Description']);
			case 'User':
				$aTenants = $this->oApiTenantsManager->getTenantList(0, 1);
				$iTenantId = count($aTenants) === 1 ? $aTenants[0]->iId : 0;
				return $this->CreateUser($iTenantId, $Data['Name'], $Data['Role']);
		}
		return false;
	}
	
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
				return $this->UpdateTenant($Data['Id'], $Data['Name'], $Data['Description']);
			case 'User':
				return $this->UpdateUser($Data['Id'], $Data['Name'], 0, $Data['Role']);
		}
		return false;
	}
	
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
	 * Creates channel with specified login and description.
	 * 
	 * @param string $Login New channel login.
	 * @param string $Description New channel description.
	 * 
	 * @return int New channel identificator.
	 * 
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
	 * Updates channel.
	 * 
	 * @param int $ChannelId Channel identificator.
	 * @param string $Login New login for channel.
	 * @param string $Description New description for channel.
	 * 
	 * @return bool
	 * 
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
	 * Deletes channel.
	 * 
	 * @param int $iChannelId Identificator of channel to delete.
	 * 
	 * @return bool
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function DeleteChannel($iChannelId)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);

		if ($iChannelId > 0)
		{
			$oChannel = $this->oApiChannelsManager->getChannelById($iChannelId);
			
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
	 * Obtains tenant list if super administrator is authenticated.
	 * 
	 * @return array {
	 *		*int* **Id** Tenant identificator
	 *		*string* **Name** Tenant name
	 * }
	 * 
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
	 * Returns current tenant name.
	 * 
	 * @return string
	 */
	public function GetTenantName()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$sTenant = '';
		$sAuthToken = $this->oHttp->GetPost('AuthToken', '');
		if (!empty($sAuthToken))
		{
			$iUserId = \CApi::getAuthenticatedUserId($sAuthToken);
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
	 * Creates tenant.
	 * 
	 * @param int $ChannelId Identificator of channel new tenant belongs to.
	 * @param string $Name New tenant name.
	 * @param string $Description New tenant description.
	 * 
	 * @return bool
	 * 
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function CreateTenant($ChannelId, $Name, $Description = '')
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
		
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
				$sTenantSpacePath = PSEVEN_APP_ROOT_PATH.'tenants/'.$oTenant->Name;
				
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
	 * Returns user list.
	 * 
	 * @param int $Offset Offset of user list.
	 * @param int $Limit Limit of result user list.
	 * @param string $OrderBy Name of field order by.
	 * @param int $OrderType Order type.
	 * @param string $Search Search string.
	 * @return array {
	 *		*int* **Id** Identificator of user.
	 *		*string* **Name** User name.
	 * }
	 */
	public function GetUserList($Offset = 0, $Limit = 0, $OrderBy = 'Name', $OrderType = \ESortOrder::ASC, $Search = '')
	{
//		\CApi::checkUserRoleIsAtLeast(\EUserRole::TenantAdmin);
		
		$aResults = $this->oApiUsersManager->getUserList($Offset, $Limit, $OrderBy, $OrderType, $Search);
		$aUsers = array();
		foreach($aResults as $oUser)
		{
			$aUsers[] = array(
				'Id' => $oUser->iId,
				'Name' => $oUser->Name
			);
		}
		return $aUsers;
	}

	/**
	 * Creates user.
	 * 
	 * @param int $TenantId Identificator of tenant that will contain new user.
	 * @param string $Name New user name.
	 * @param int $Role New user role.
	 * @return int|false
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function CreateUser($TenantId, $Name, $Role = \EUserRole::NormalUser)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::TenantAdmin);
		
		if (!empty($TenantId) && !empty($Name))
		{
			$oUser = \CUser::createInstance();
			
			$oUser->Name = $Name;
			$oUser->IdTenant = $TenantId;
			$oUser->Role = $Role;

			if ($this->oApiUsersManager->createUser($oUser));
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
	 * Updates user.
	 * 
	 * @param int $UserId Identificator of user to update.
	 * @param string $UserName New user name.
	 * @param int $TenantId Identificator of tenant that will contain the user.
	 * @param int $Role New user role.
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function UpdateUser($UserId, $UserName = '', $TenantId = 0, $Role = -1)
	{
		if (!empty($UserName) && empty($TenantId) && $Role === -1 && $UserId === \CApi::getAuthenticatedUserId())
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
				if (!empty($UserName))
				{
					$oUser->Name = $UserName;
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
	 * Deletes user.
	 * 
	 * @param int $UserId User identificator.
	 * 
	 * @return bool
	 * 
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
