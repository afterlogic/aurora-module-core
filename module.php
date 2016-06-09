<?php

class CoreModule extends AApiModule
{
	public $oApiTenantsManager = null;
	
	public $oApiChannelsManager = null;
	
	public $oApiUsersManager = null;
	
	public function init() {
		parent::init();
		
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
				'autodiscover' => 'EntryAutodiscover',
				'postlogin' => 'EntryPostlogin'
			)
		);
		
		$this->subscribeEvent('CreateAccount', array($this, 'onAccountCreate'));
	}
	
	/**
	 * @return array
	 */
	public function DoServerInitializations()
	{
		$iUserId = \CApi::getLogginedUserId();

		$bResult = false;

		$oApiIntegrator = \CApi::GetCoreManager('integrator');

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
			$oApiFileCache = \Capi::GetCoreManager('filecache');
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
	 * @return array
	 */
	public function Noop()
	{
		return true;
	}

	/**
	 * @return array
	 */
	public function Ping()
	{
		return 'Pong';
	}	
	
	/**
	 * @return array
	 */
//	public function GetAppData($oUser = null)
//	{
//		$oApiIntegratorManager = \CApi::GetCoreManager('integrator');
//		$sAuthToken = (string) $this->getParamValue('AuthToken', '');
//		return $oApiIntegratorManager ? $oApiIntegratorManager->appData(false, '', '', '', $sAuthToken) : false;
//	}
	
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
	
	public function EntryPing()
	{
		@header('Content-Type: text/plain; charset=utf-8');
		return 'Pong';
	}
	
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
	
	public function EntryMobile()
	{
		if ($this->oApiCapabilityManager->isNotLite())
		{
			$oApiIntegrator = \CApi::GetCoreManager('integrator');
			$oApiIntegrator->setMobile(true);
		}

		\CApi::Location('./');
	}
	
	public function EntrySpeclogon()
	{
		\CApi::SpecifiedUserLogging(true);
		\CApi::Location('./');
	}
	
	public function EntrySpeclogoff()
	{
		\CApi::SpecifiedUserLogging(false);
		\CApi::Location('./');
	}

	public function EntrySso()
	{
		$oApiIntegratorManager = \CApi::GetCoreManager('integrator');

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
	
	public function EntryAutodiscover()
	{
		$sInput = \file_get_contents('php://input');

		\CApi::Log('#autodiscover:');
		\CApi::LogObject($sInput);

		$aMatches = array();
		$aEmailAddress = array();
		\preg_match("/\<AcceptableResponseSchema\>(.*?)\<\/AcceptableResponseSchema\>/i", $sInput, $aMatches);
		\preg_match("/\<EMailAddress\>(.*?)\<\/EMailAddress\>/", $sInput, $aEmailAddress);
		if (!empty($aMatches[1]) && !empty($aEmailAddress[1]))
		{
			$sIncMailServer = trim(\CApi::GetSettingsConf('WebMail/ExternalHostNameOfLocalImap'));
			$sOutMailServer = trim(\CApi::GetSettingsConf('WebMail/ExternalHostNameOfLocalSmtp'));

			if (0 < \strlen($sIncMailServer) && 0 < \strlen($sOutMailServer))
			{
				$iIncMailPort = 143;
				$iOutMailPort = 25;

				$aMatch = array();
				if (\preg_match('/:([\d]+)$/', $sIncMailServer, $aMatch) && !empty($aMatch[1]) && is_numeric($aMatch[1]))
				{
					$sIncMailServer = preg_replace('/:[\d]+$/', $sIncMailServer, '');
					$iIncMailPort = (int) $aMatch[1];
				}

				$aMatch = array();
				if (\preg_match('/:([\d]+)$/', $sOutMailServer, $aMatch) && !empty($aMatch[1]) && is_numeric($aMatch[1]))
				{
					$sOutMailServer = preg_replace('/:[\d]+$/', $sOutMailServer, '');
					$iOutMailPort = (int) $aMatch[1];
				}

				$sResult = \implode("\n", array(
'<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">',
'	<Response xmlns="'.$aMatches[1].'">',
'		<Account>',
'			<AccountType>email</AccountType>',
'			<Action>settings</Action>',
'			<Protocol>',
'				<Type>IMAP</Type>',
'				<Server>'.$sIncMailServer.'</Server>',
'				<LoginName>'.$aEmailAddress[1].'</LoginName>',
'				<Port>'.$iIncMailPort.'</Port>',
'				<SSL>'.(993 === $iIncMailPort ? 'on' : 'off').'</SSL>',
'				<SPA>off</SPA>',
'				<AuthRequired>on</AuthRequired>',
'			</Protocol>',
'			<Protocol>',
'				<Type>SMTP</Type>',
'				<Server>'.$sOutMailServer.'</Server>',
'				<LoginName>'.$aEmailAddress[1].'</LoginName>',
'				<Port>'.$iOutMailPort.'</Port>',
'				<SSL>'.(465 === $iOutMailPort ? 'on' : 'off').'</SSL>',
'				<SPA>off</SPA>',
'				<AuthRequired>on</AuthRequired>',
'			</Protocol>',
'		</Account>',
'	</Response>',
'</Autodiscover>'));
			}
		}

		if (empty($sResult))
		{
			$usec = $sec = 0;
			list($usec, $sec) = \explode(' ', microtime());
			$sResult = \implode("\n", array('<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">',
(empty($aMatches[1]) ?
'	<Response>' :
'	<Response xmlns="'.$aMatches[1].'">'
),
'		<Error Time="'.\gmdate('H:i:s', $sec).\substr($usec, 0, \strlen($usec) - 2).'" Id="2477272013">',
'			<ErrorCode>600</ErrorCode>',
'			<Message>Invalid Request</Message>',
'			<DebugData />',
'		</Error>',
'	</Response>',
'</Autodiscover>'));
		}

		header('Content-Type: text/xml');
		$sResult = '<'.'?xml version="1.0" encoding="utf-8"?'.'>'."\n".$sResult;

		\CApi::Log('');
		\CApi::Log($sResult);		
	}	
	
	public function EntryPostlogin()
	{
		if (\CApi::GetConf('labs.allow-post-login', false))
		{
			$oApiIntegrator = \CApi::GetCoreManager('integrator');
					
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
	 * @return array
	 */
	public function SetMobile($bMobile)
	{
		$oApiIntegratorManager = \CApi::GetCoreManager('integrator');
		return $oApiIntegratorManager ?
			$oApiIntegratorManager->setMobile($bMobile) : false;
	}	
	
	/**
	 * Creates new account.
	 * 
	 * @return CAccount | false
	 */
	public function CreateAccount()
	{
		$mResult = false;
		
		$sEmail = $this->getParamValue('Email');
		$sPassword = $this->getParamValue('Password');
		$sLanguage = $this->getParamValue('Language', '');
		$aExtValues = $this->getParamValue('ExtValues', null);
		$bAllowInternalOnly = $this->getParamValue('AllowInternalOnly', false);
		
		try
		{
			/* @var $oApiDomainsManager CApiDomainsManager */
			$oApiDomainsManager = CApi::GetCoreManager('domains');

			/* @var $oApiUsersManager CApiUsersManager */
			$oApiUsersManager = CApi::GetCoreManager('users');

			$sDomainName = api_Utils::GetDomainFromEmail($sEmail);

			$oDomain = /* @var $oDomain CDomain */ $oApiDomainsManager->getDomainByName($sDomainName);
			if (!$oDomain)
			{
				$oDomain = $oApiDomainsManager->getDefaultDomain();
			}

			$bApiIntegratorLoginToAccountResult = isset($aExtValues['ApiIntegratorLoginToAccountResult']) ? $aExtValues['ApiIntegratorLoginToAccountResult'] : false;
			if ($oDomain && ($bApiIntegratorLoginToAccountResult || $oDomain->AllowNewUsersRegister || ($oDomain->IsInternal && $bAllowInternalOnly) || 'nodb' === CApi::GetManager()->GetStorageByType('webmail')))
			{
				/*if ($oDomain && !$oDomain->AllowWebMail)
				{
					throw new CApiManagerException(Errs::WebMailManager_AccountWebmailDisabled);
				}
				else */if ($oDomain && $oDomain->IsInternal && !$bAllowInternalOnly)
				{
					throw new CApiManagerException(Errs::WebMailManager_NewUserRegistrationDisabled);
				}
				else if ($oDomain && $bAllowInternalOnly && (!$oDomain->IsInternal || $oDomain->IsDefaultDomain))
				{
					throw new CApiManagerException(Errs::WebMailManager_NewUserRegistrationDisabled);
				}
				else if ($oDomain)
				{
					$oAccountToCreate = new CAccount($oDomain);
					$oAccountToCreate->Email = $sEmail;

//					$oAccountToCreate->IncomingMailLogin = (isset($aExtValues['Login'])) ? $aExtValues['Login'] :
//						(($this->oSettings->GetConf('WebMail/UseLoginWithoutDomain'))
//							? api_Utils::GetAccountNameFromEmail($sEmail) : $sEmail);
										
					$oAccountToCreate->IncomingMailLogin = (isset($aExtValues['Login']) ? $aExtValues['Login'] : $sEmail);
					if (\CApi::GetSettingsConf('WebMail/UseLoginWithoutDomain'))
					{
						$oAccountToCreate->IncomingMailLogin = api_Utils::GetAccountNameFromEmail($oAccountToCreate->IncomingMailLogin);
					}

					$oAccountToCreate->IncomingMailPassword = $sPassword;

					if (0 < strlen($sLanguage) && $sLanguage !== $oAccountToCreate->User->DefaultLanguage)
					{
						$oAccountToCreate->User->DefaultLanguage = $sLanguage;
					}

					if ($oDomain->IsDefaultDomain && isset(
						$aExtValues['IncProtocol'], $aExtValues['IncHost'], $aExtValues['IncPort'],
						$aExtValues['OutHost'], $aExtValues['OutPort'], $aExtValues['OutAuth']))
					{
						$oAccountToCreate->IncomingMailProtocol = (int) $aExtValues['IncProtocol'];
						$oAccountToCreate->IncomingMailServer = trim($aExtValues['IncHost']);
						$oAccountToCreate->IncomingMailPort = (int) trim($aExtValues['IncPort']);

						$oAccountToCreate->OutgoingMailServer = trim($aExtValues['OutHost']);
						$oAccountToCreate->OutgoingMailPort = (int) trim($aExtValues['OutPort']);
						$oAccountToCreate->OutgoingMailAuth = ((bool) $aExtValues['OutAuth'])
							? ESMTPAuthType::AuthCurrentUser : ESMTPAuthType::NoAuth;

						// TODO
						$oAccountToCreate->IncomingMailUseSSL = in_array($oAccountToCreate->IncomingMailPort, array(993, 995));
						$oAccountToCreate->OutgoingMailUseSSL = in_array($oAccountToCreate->OutgoingMailPort, array(465));
					}

					CApi::Plugin()->RunHook('api-pre-create-account-process-call', array(&$oAccountToCreate));

					if (isset($aExtValues['FriendlyName']))
					{
						$oAccountToCreate->FriendlyName = $aExtValues['FriendlyName'];
					}

					if (isset($aExtValues['Question1']))
					{
						$oAccountToCreate->User->Question1 = $aExtValues['Question1'];
					}

					if (isset($aExtValues['Question2']))
					{
						$oAccountToCreate->User->Question2 = $aExtValues['Question2'];
					}

					if (isset($aExtValues['Answer1']))
					{
						$oAccountToCreate->User->Answer1 = $aExtValues['Answer1'];
					}

					if (isset($aExtValues['Answer2']))
					{
						$oAccountToCreate->User->Answer2 = $aExtValues['Answer2'];
					}
					
					if ($oApiUsersManager->createAccount($oAccountToCreate,
						!($oAccountToCreate->IsInternal || !$oAccountToCreate->Domain->AllowWebMail || $bApiIntegratorLoginToAccountResult || $oAccountToCreate->Domain->IsDefaultTenantDomain)))
					{
						CApi::Plugin()->RunHook('api-success-post-create-account-process-call', array(&$oAccountToCreate));

						$mResult = $oAccountToCreate;
					}
					else
					{
						$oException = $oApiUsersManager->GetLastException();

						CApi::Plugin()->RunHook('api-error-post-create-account-process-call', array(&$oAccountToCreate, &$oException));

						throw (is_object($oException))
							? $oException
							: new CApiManagerException(Errs::WebMailManager_AccountCreateOnLogin);
					}
				}
				else
				{
					throw new CApiManagerException(Errs::WebMailManager_DomainDoesNotExist);
				}
			}
			else
			{
				throw new CApiManagerException(Errs::WebMailManager_NewUserRegistrationDisabled);
			}
		}
		catch (CApiBaseException $oException)
		{
			$mResult = false;
//			$this->setLastException($oException);
		}

		return $mResult;
	}

	/**
	 * Obtains list of skins.
	 * 
	 * @ignore
	 * @todo not used
	 * 
	 * @return array
	 */
	public function GetSkinList()
	{
		$aList = array();
		$sDir = CApi::WebMailPath().'skins';
		if (@is_dir($sDir))
		{
			$rDirH = @opendir($sDir);
			if ($rDirH)
			{
				while (($sFile = @readdir($rDirH)) !== false)
				{
					if ('.' !== $sFile{0} && @file_exists($sDir.'/'.$sFile.'/styles.css'))
					{
						$aList[] = $sFile;
					}
				}
				@closedir($rDirH);
			}
		}
		return $aList;
	}

	/**
	 * Validates the administrator password.
	 * 
	 * @return bool
	 */
	public function ValidateAdminPassword()
	{
		$sPassword = $this->getParamValue('Password');
		$sSettingsPassword =  \CApi::GetSettingsConf('Common/AdminPassword');
		return $sSettingsPassword === $sPassword || md5($sPassword) === $sSettingsPassword;
	}

	/**
	 * Clears temporary files.
	 * 
	 * @ignore
	 * @todo not used
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
				$this->_removeDirByTime($sTempPath, $iTime2Kill, $iNow);
				@file_put_contents( CApi::DataPath().'/'.$sDataFile, $iNow);
			}
		}

		return true;
	}

	/**
	 * Recursively deletes temporary files and folders on time.
	 * 
	 * @param string $sTempPath Path to the temporary folder.
	 * @param int $iTime2Kill Interval in seconds at which files needs removing.
	 * @param int $iNow Current Unix timestamp.
	 */
	protected function _removeDirByTime($sTempPath, $iTime2Kill, $iNow)
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
							$this->_removeDirByTime($sTempPath.'/'.$sFile, $iTime2Kill, $iNow);
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
				if ($this->_removeFilesByTime($sTempPath, $iTime2Kill, $iNow))
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
	 * @return boolean
	 */
	protected function _removeFilesByTime($sTempPath, $iTime2Kill, $iNow)
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
	
	/**
	 * 
	 * @return boolean
	 */
	public function CreateChannel($sLogin = '', $sDescription = '')
	{
//		$oAccount = $this->getDefaultAccountFromParam();
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		if ($sLogin !== '')
		{
			$oChannel = \CChannel::createInstance();
			
			$oChannel->Login = $sLogin;
			
			if ($sDescription !== '')
			{
				$oChannel->Description = $sDescription;
			}

			$this->oApiChannelsManager->createChannel($oChannel);
			return $oChannel ? array(
				'iObjectId' => $oChannel->iObjectId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function UpdateChannel($iChannelId = 0, $sLogin = '', $sDescription = '')
	{
//		$oAccount = $this->getDefaultAccountFromParam();
		
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		
		if ($iChannelId > 0)
		{
			$oChannel = $this->oApiChannelsManager->getChannelById($iChannelId);
			
			if ($oChannel)
			{
				if ($sLogin)
				{
					$oChannel->Login = $sLogin;
				}
				if ($sDescription)
				{
					$oChannel->Description = $sDescription;
				}
				
				$this->oApiChannelsManager->updateChannel($oChannel);
			}
			
			return $oChannel ? array(
				'iObjectId' => $oChannel->iObjectId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function DeleteChannel($iChannelId = 0)
	{
//		$oAccount = $this->getDefaultAccountFromParam();
		
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))

		if ($iChannelId > 0)
		{
			$oChannel = $this->oApiChannelsManager->getChannelById($iChannelId);
			
			if ($oChannel)
			{
				$this->oApiChannelsManager->deleteChannel($oChannel);
			}
			
			return $oChannel ? array(
				'iObjectId' => $oChannel->iObjectId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function CreateTenant($sName = '', $sDescription = '', $iChannelId = 0)
	{
//		$oAccount = $this->getDefaultAccountFromParam();
	
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		if ($sName !== '' && $sDescription !== '' && $iChannelId > 0)
		{
			$oTenant = \CTenant::createInstance();

			$oTenant->Name = $sName;
			$oTenant->Description = $sDescription;
			$oTenant->IdChannel = $iChannelId;

			$this->oApiTenantsManager->createTenant($oTenant);
			return $oTenant ? array(
				'iObjectId' => $oTenant->iObjectId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function UpdateTenant($iTenantId = 0, $sName = '', $sDescription = '', $iChannelId = 0)
	{
//		$oAccount = $this->getDefaultAccountFromParam();
		
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		
		if ($iTenantId > 0)
		{
			$oTenant = $this->oApiTenantsManager->getTenantById($iTenantId);
			
			if ($oTenant)
			{
				if ($sName)
				{
					$oTenant->Name = $sName;
				}
				if ($sDescription)
				{
					$oTenant->Description = $sDescription;
				}
				if ($iChannelId)
				{
					$oTenant->IdChannel = $iChannelId;
				}
				
				$this->oApiTenantsManager->updateTenant($oTenant);
			}
			
			return $oTenant ? array(
				'iObjectId' => $oTenant->iObjectId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function DeleteTenant($iTenantId = 0)
	{
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))

		if ($iTenantId > 0)
		{
			$oTenant = $this->oApiTenantsManager->getTenantById($iTenantId);
			
			if ($oTenant)
			{
				$sTenantSpacePath = PSEVEN_APP_ROOT_PATH.'tenants/'.$oTenant->Name;
				
				if (@is_dir($sTenantSpacePath))
				{
					$this->deleteTree($sTenantSpacePath);
				}
						
				$this->oApiTenantsManager->deleteTenant($oTenant);
			}
			
			return $oTenant ? array(
				'iObjectId' => $oTenant->iObjectId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public static function deleteTree($dir)
	{
		$files = array_diff(scandir($dir), array('.','..'));
			
		foreach ($files as $file) {
			(is_dir("$dir/$file")) ? self::deleteTree("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	}
  
	//TODO is it used by any code?
	public function onAccountCreate($iTenantId, $iUserId, $sLogin, $sPassword, &$oResult)
	{
		$oUser = null;
		
		if ($iUserId > 0)
		{
			$oUser = $this->oApiUsersManager->getUserById($iUserId);
		}
		else
		{
			$oUser = \CUser::createInstance();
			
			$iTenantId = $iTenantId ? $iTenantId : 0;
			
			if ($iTenantId)
			{
				$oUser->IdTenant = $iTenantId;
			}
				
			if (!$this->oApiUsersManager->createUser($oUser))
			{
				$oUser = null;
			}
		}
		
		$oResult = $oUser;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function CreateUser($iTenantId = 0, $sName = '')
	{
//		$oAccount = $this->getDefaultAccountFromParam();
		
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		if ($iTenantId > 0 && $sName !== '')
		{
			$oUser = \CUser::createInstance();
			
			$oUser->Name = $sName;
			$oUser->IdTenant = $iTenantId;

			$this->oApiUsersManager->createUser($oUser);
			return $oUser ? array(
				'iObjectId' => $oUser->iObjectId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function UpdateUser($iUserId = 0, $sUserName = '', $iTenantId = 0)
	{
//		$oAccount = $this->getDefaultAccountFromParam();
		
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		
		if ($iUserId > 0)
		{
			$oUser = $this->oApiUsersManager->getUserById($iUserId);
			
			if ($oUser)
			{
				$oUser->Name = $sUserName;
				$oUser->IdTenant = $iTenantId;
				$this->oApiUsersManager->updateUser($oUser);
			}
			
			return $oUser ? array(
				'iObjectId' => $oUser->iObjectId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	public function UpdateUserObject($oUser)
	{
		$this->oApiUsersManager->updateUser($oUser);
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function DeleteUser($iUserId = 0)
	{
//		$oAccount = $this->getDefaultAccountFromParam();
		
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))

		if ($iUserId > 0)
		{
			$oUser = $this->oApiUsersManager->getUserById($iUserId);
			
			if ($oUser)
			{
				$this->oApiUsersManager->deleteUser($oUser);
			}
			
			return $oUser ? array(
				'iObjectId' => $oUser->iObjectId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	public function GetUser($iUserId = 0)
	{
		$oUser = $this->oApiUsersManager->getUserById((int) $iUserId);
		
		return $oUser ? $oUser : null;
	}
	
	public function GetUserList($iPage, $iUsersPerPage, $sOrderBy = 'Email', $iOrderType = \ESortOrder::ASC, $sSearchDesc = '')
	{
		$aUsers = $this->oApiUsersManager->getUserList($iPage, $iUsersPerPage, $sOrderBy, $iOrderType, $sSearchDesc);
		return $aUsers ? $aUsers : null;
	}

	public function GetTenantIdByName($sTenantName = '')
	{
		$oTenant = $this->oApiTenantsManager->getTenantIdByName((string) $sTenantName);

		return $oTenant ? $oTenant : null;
	}
	
	public function GetTenantById($iIdTenant)
	{
		$oTenant = $this->oApiTenantsManager->getTenantById($iIdTenant);

		return $oTenant ? $oTenant : null;
	}
	
	
	public function GetDefaultGlobalTenant()
	{
		$oTenant = $this->oApiTenantsManager->getDefaultGlobalTenant();
		
		return $oTenant ? $oTenant : null;
	}
	
	public function GetTenantName()
	{
		$sTenant = '';
		$sAuthToken = $this->oHttp->GetPost('AuthToken', '');
		if (!empty($sAuthToken))
		{
			$iUserId = \CApi::getLogginedUserId($sAuthToken);
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
}
