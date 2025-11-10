<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Traits;

use Aurora\Api;
use Aurora\Modules\Core\Enums\ErrorCodes;
use Aurora\Modules\Core\Models\User;
use Aurora\Modules\Core\Models\UserBlock;
use Aurora\System\Enums\LogLevel;
use Aurora\System\Enums\UserRole;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Logger;
use Aurora\System\Notifications;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\BufferedOutput;
use Aurora\System\Managers\Integrator;
use Carbon\Carbon;

/**
 * System module that provides core functionality such as User management, Tenants management.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Modules
 */
trait Common
{
    protected $oIntegratorManager = null;

    /**
     * @return \Aurora\System\Managers\Integrator
     */
    public function getIntegratorManager()
    {
        if ($this->oIntegratorManager === null) {
            $this->oIntegratorManager = new Integrator();
        }

        return $this->oIntegratorManager;
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
        if (@is_dir($sTempPath)) {
            $rDirH = @opendir($sTempPath);
            if ($rDirH) {
                while (($sFile = @readdir($rDirH)) !== false) {
                    if ('.' !== $sFile && '..' !== $sFile) {
                        if (@is_dir($sTempPath . '/' . $sFile)) {
                            $this->removeDirByTime($sTempPath . '/' . $sFile, $iTime2Kill, $iNow);
                        } else {
                            $iFileCount++;
                        }
                    }
                }
                @closedir($rDirH);
            }

            if ($iFileCount > 0) {
                if ($this->removeFilesByTime($sTempPath, $iTime2Kill, $iNow)) {
                    @rmdir($sTempPath);
                }
            } else {
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
        if (@is_dir($sTempPath)) {
            $rDirH = @opendir($sTempPath);
            if ($rDirH) {
                while (($sFile = @readdir($rDirH)) !== false) {
                    if ($sFile !== '.' && $sFile !== '..') {
                        if ($iNow - filemtime($sTempPath . '/' . $sFile) > $iTime2Kill) {
                            @unlink($sTempPath . '/' . $sFile);
                        } else {
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
    private function deleteTree($dir)
    {
        $files = array_diff(scandir($dir), array('.','..'));

        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deleteTree("$dir/$file") : unlink("$dir/$file");
        }

        return rmdir($dir);
    }
    /***** static functions *****/

    /***** public functions *****/
    public function IsModuleExists($Module)
    {
        return Api::GetModuleManager()->ModuleExists($Module);
    }

    /**
     *
     * @return string
     */
    public function GetVersion()
    {
        return Api::Version();
    }

    /**
     * Clears temporary files by cron.
     *
     * @ignore
     * @todo check if it works.
     *
     * @return bool
     */
    protected function ClearTempFiles()
    {
        $sTempPath = Api::DataPath() . '/temp';
        if (@is_dir($sTempPath)) {
            $iNow = time();

            $iTime2Run = $this->oModuleSettings->CronTimeToRunSeconds;
            $iTime2Kill = $this->oModuleSettings->CronTimeToKillSeconds;
            $sDataFile = $this->oModuleSettings->CronTimeFile;

            $iFiletTime = -1;
            if (@file_exists(Api::DataPath() . '/' . $sDataFile)) {
                $iFiletTime = (int) @file_get_contents(Api::DataPath() . '/' . $sDataFile);
            }

            if ($iFiletTime === -1 || $iNow - $iFiletTime > $iTime2Run) {
                $this->removeDirByTime($sTempPath, $iTime2Kill, $iNow);
                @file_put_contents(Api::DataPath() . '/' . $sDataFile, $iNow);
            }
        }

        return true;
    }

    /***** public functions *****/

    /***** public functions might be called with web API *****/
    /**
     * @apiDefine Core Core Module
     * System module that provides core functionality such as User management, Tenants management
     */

    /**
     * @api {post} ?/Api/ DoServerInitializations
     * @apiName DoServerInitializations
     * @apiGroup Core
     * @apiDescription Does some pending actions to be executed when you log in.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=DoServerInitializations} Method Method name.
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'DoServerInitializations'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {bool} Result.Result Indicates if server initializations were made successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
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
        Api::checkUserRoleIsAtLeast(UserRole::Customer);
        $result = true;

        $oCacher = Api::Cacher();

        $bDoGC = false;
        if ($oCacher && $oCacher->IsInited()) {
            $iTime = $oCacher->GetTimer('Cache/ClearFileCache');
            if (0 === $iTime || $iTime + 60 * 60 * 24 < time()) {
                if ($oCacher->SetTimer('Cache/ClearFileCache')) {
                    $bDoGC = true;
                }
            }
        }

        if ($bDoGC) {
            Api::Log('GC: FileCache / Start');
            $oApiFileCache = new \Aurora\System\Managers\Filecache();
            $oApiFileCache->gc();
            $oCacher->gc();
            Api::Log('GC: FileCache / End');
        }

        return $result;
    }

    /**
     * @api {post} ?/Api/ Ping
     * @apiName Ping
     * @apiGroup Core
     * @apiDescription Method is used for checking Internet connection.
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=Ping} Method Method name.
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'Ping'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {string} Result.Result Just a string to indicate that connection to backend is working.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Core',
     *	Method: 'Ping',
     *	Result: 'Pong'
     * }
     */
    /**
     * Method is used for checking Internet connection.
     *
     * @return 'Pong'
     */
    public function Ping()
    {
        Api::checkUserRoleIsAtLeast(UserRole::Anonymous);

        return 'Pong';
    }

    /**
     * @api {post} ?/Api/ GetAppData
     * @apiName GetAppData
     * @apiGroup Core
     * @apiDescription Obtains a list of settings for each module for the current user.
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=GetAppData} Method Method name.
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'GetAppData'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {string} Result.Result List of settings for each module for the current user.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Core',
     *	Method: 'GetAppData',
     *	Result: {
     *				User: {Id: 0, Role: 4, Name: "", PublicId: ""},
     *				Core: { ... },
     *				Contacts: { ... },
     *				 ...
     *				CoreWebclient: { ... },
     *				 ...
     *			}
     * }
     */
    /**
     * Obtains a list of settings for each module for the current user.
     *
     * @return array
     */
    public function GetAppData()
    {
        $oApiIntegrator = $this->getIntegratorManager();
        return $oApiIntegrator->appData();
    }

    /**
     * @api {post} ?/Api/ GetSettings
     * @apiName GetSettings
     * @apiGroup Core
     * @apiDescription Obtains list of module settings for authenticated user.
     *
     * @apiHeader {string} [Authorization] "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=GetSettings} Method Method name.
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'GetSettings'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {mixed} Result.Result List of module settings in case of success, otherwise **false**.
     * @apiSuccess {string} Result.Result.SiteName Site name.
     * @apiSuccess {string} Result.Result.Language Language of interface.
     * @apiSuccess {int} Result.Result.TimeFormat Time format.
     * @apiSuccess {string} Result.Result.DateFormat Date format.
     * @apiSuccess {bool} Result.Result.AutodetectLanguage Indicates if language should be taken from browser.
     * @apiSuccess {object} Result.Result.EUserRole Enumeration with user roles.
     * @apiSuccess {string} [Result.Result.DBHost] Database host is returned only if super administrator is authenticated.
     * @apiSuccess {string} [Result.Result.DBName] Database name is returned only if super administrator is authenticated.
     * @apiSuccess {string} [Result.Result.DBLogin] Database login is returned only if super administrator is authenticated.
     * @apiSuccess {string} [Result.Result.AdminLogin] Super administrator login is returned only if super administrator is authenticated.
     * @apiSuccess {bool} [Result.Result.AdminHasPassword] Indicates if super administrator has set up password. It is returned only if super administrator is authenticated.
     * @apiSuccess {string} [Result.Result.AdminLanguage] Super administrator language is returned only if super administrator is authenticated.
     * @apiSuccess {bool} [Result.Result.IsSystemConfigured] Indicates if 'data' folder exist and writable and encryption key was generated.
     * @apiSuccess {bool} [Result.Result.EncryptionKeyNotEmpty] Indicates if encryption key was generated. It is returned only if super administrator is authenticated.
     * @apiSuccess {bool} [Result.Result.EnableLogging] Indicates if logging is enabled. It is returned only if super administrator is authenticated.
     * @apiSuccess {bool} [Result.Result.EnableEventLogging] Indicates if event logging is enabled. It is returned only if super administrator is authenticated.
     * @apiSuccess {string} [Result.Result.LoggingLevel] Value of logging level. It is returned only if super administrator is authenticated.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Core',
     *	Method: 'GetSettings',
     *	Result: { SiteName: "Aurora Cloud", Language: "English", TimeFormat: 1, DateFormat: "MM/DD/YYYY",
     *		EUserRole: { SuperAdmin: 0, TenantAdmin: 1, NormalUser: 2, Customer: 3, Anonymous: 4 } }
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Core',
     *	Method: 'GetSettings',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */
    /**
     * Obtains list of module settings for authenticated user.
     *
     * @return array
     */
    public function GetSettings()
    {
        Api::checkUserRoleIsAtLeast(UserRole::Anonymous);

        $oUser = Api::getAuthenticatedUser();

        $oApiIntegrator = $this->getIntegratorManager();
        $iLastErrorCode = $oApiIntegrator->getLastErrorCode();
        if (0 < $iLastErrorCode) {
            $oApiIntegrator->clearLastErrorCode();
        }

        $oSettings = &Api::GetSettings();

        $aSettings = array(
            'AutodetectLanguage' => $this->oModuleSettings->AutodetectLanguage,
            'UserSelectsDateFormat' => $this->oModuleSettings->UserSelectsDateFormat,
            'DateFormat' => $this->oModuleSettings->DateFormat,
            'DateFormatList' => $this->oModuleSettings->DateFormatList,
            'EUserRole' => (new UserRole())->getMap(),
            'Language' => Api::GetLanguage(),
            'ShortLanguage' => \Aurora\System\Utils::ConvertLanguageNameToShort(Api::GetLanguage()),
            'LanguageList' => $oApiIntegrator->getLanguageList(),
            'LastErrorCode' => $iLastErrorCode,
            'SiteName' => $this->oModuleSettings->SiteName,
            'SocialName' => '',
            'TenantName' => Api::getTenantName(),
            'EnableMultiTenant' => $oSettings->EnableMultiTenant,
            'TimeFormat' => $this->oModuleSettings->TimeFormat,
            'UserId' => Api::getAuthenticatedUserId(),
            'IsSystemConfigured' => is_writable(Api::DataPath()) &&
                (file_exists(Api::GetEncryptionKeyPath()) && strlen(@file_get_contents(Api::GetEncryptionKeyPath()))),
            'Version' => Api::VersionFull(),
            'ProductName' => $this->oModuleSettings->ProductName,
            'PasswordMinLength' => $oSettings->PasswordMinLength,
            'PasswordMustBeComplex' => $oSettings->PasswordMustBeComplex,
            'CookiePath' => Api::getCookiePath(),
            'CookieSecure' => Api::getCookieSecure(),
            'AuthTokenCookieExpireTime' => $this->oModuleSettings->AuthTokenCookieExpireTime,
            'StoreAuthTokenInDB' => $oSettings->StoreAuthTokenInDB,
            'AvailableClientModules' => $oApiIntegrator->GetClientModuleNames(),
            'AvailableBackendModules' => $oApiIntegrator->GetBackendModules(),
            'AllowGroups' => $this->oModuleSettings->AllowGroups,
        );

        if ($oSettings && ($oUser instanceof User) && $oUser->Role === UserRole::SuperAdmin) {
            $sAdminPassword = $oSettings->AdminPassword;

            $aSettings = array_merge($aSettings, array(
                'DBHost' => $oSettings->DBHost,
                'DBName' => $oSettings->DBName,
                'DBLogin' => $oSettings->DBLogin,
                'AdminLogin' => $oSettings->AdminLogin,
                'AdminHasPassword' => !empty($sAdminPassword),
                'AdminLanguage' => $oSettings->AdminLanguage,
                'CommonLanguage' => $this->oModuleSettings->Language,
                'EncryptionKeyNotEmpty' => file_exists(Api::GetEncryptionKeyPath()) && strlen(@file_get_contents(Api::GetEncryptionKeyPath())),
                'EnableLogging' => $oSettings->EnableLogging,
                'EnableEventLogging' => $oSettings->EnableEventLogging,
                'LoggingLevel' => $oSettings->LoggingLevel,
                'LogFilesData' => $this->GetLogFilesData(),
                'ELogLevel' => (new LogLevel())->getMap()
            ));
        }

        if (($oUser instanceof User) && $oUser->isNormalOrTenant()) {
            if ($oUser->DateFormat !== '') {
                $aSettings['DateFormat'] = $oUser->DateFormat;
            }
            $aSettings['TimeFormat'] = $oUser->TimeFormat;
            $aSettings['Timezone'] = $oUser->DefaultTimeZone;
        }

        return $aSettings;
    }

    /**
     * @api {post} ?/Api/ UpdateSettings
     * @apiName UpdateSettings
     * @apiGroup Core
     * @apiDescription Updates specified settings if super administrator is authenticated.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=UpdateSettings} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **DbLogin** *string* Database login.<br>
     * &emsp; **DbPassword** *string* Database password.<br>
     * &emsp; **DbName** *string* Database name.<br>
     * &emsp; **DbHost** *string* Database host.<br>
     * &emsp; **AdminLogin** *string* Login for super administrator.<br>
     * &emsp; **Password** *string* Current password for super administrator.<br>
     * &emsp; **NewPassword** *string* New password for super administrator.<br>
     * &emsp; **AdminLanguage** *string* Language for super administrator.<br>
     * &emsp; **Language** *string* Language that is used on login and for new users.<br>
     * &emsp; **AutodetectLanguage** *bool* Indicates if browser language should be used on login and for new users.<br>
     * &emsp; **TimeFormat** *int* Time format that is used for new users.<br>
     * &emsp; **EnableLogging** *bool* Indicates if logs are enabled.<br>
     * &emsp; **EnableEventLogging** *bool* Indicates if events logs are enabled.<br>
     * &emsp; **LoggingLevel** *int* Specify logging level.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'UpdateSettings',
     *	Parameters: '{ DbLogin: "login_value", DbPassword: "password_value",
     * DbName: "db_name_value", DbHost: "host_value", AdminLogin: "admin_login_value",
     * Password: "admin_pass_value", NewPassword: "admin_pass_value" }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {bool} Result.Result Indicates if settings were updated successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
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
     * @param string $DbLogin Database login.
     * @param string $DbPassword Database password.
     * @param string $DbName Database name.
     * @param string $DbHost Database host.
     * @param string $AdminLogin Login for super administrator.
     * @param string $Password Current password for super administrator.
     * @param string $NewPassword New password for super administrator.
     * @param string $AdminLanguage Language for super administrator.
     * @param string $SiteName Site name.
     * @param string $Language Language that is used on login and for new users.
     * @param bool $AutodetectLanguage Indicates if browser language should be used on login and for new users.
     * @param int $TimeFormat Time format that is used for new users.
     * @param bool $EnableLogging Indicates if logs are enabled.
     * @param bool $EnableEventLogging Indicates if events logs are enabled.
     * @param int $LoggingLevel Specify logging level.
     * @return bool
     * @throws ApiException
     */
    public function UpdateSettings(
        $DbLogin = null,
        $DbPassword = null,
        $DbName = null,
        $DbHost = null,
        $AdminLogin = null,
        $Password = null,
        $NewPassword = null,
        $AdminLanguage = null,
        $SiteName = null,
        $Language = null,
        $AutodetectLanguage = null,
        $TimeFormat = null,
        $DateFormat = null,
        $EnableLogging = null,
        $EnableEventLogging = null,
        $LoggingLevel = null
    ) {
        Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $oUser = Api::getAuthenticatedUser();

        if ($oUser->Role === UserRole::SuperAdmin) {
            if ($SiteName !== null || $Language !== null || $TimeFormat !== null || $AutodetectLanguage !== null) {
                if ($SiteName !== null) {
                    $this->setConfig('SiteName', $SiteName);
                }
                if ($AutodetectLanguage !== null) {
                    $this->setConfig('AutodetectLanguage', $AutodetectLanguage);
                }
                if ($Language !== null) {
                    $this->setConfig('Language', $Language);
                }
                if ($TimeFormat !== null) {
                    $this->setConfig('TimeFormat', (int) $TimeFormat);
                }
                $this->saveModuleConfig();
            }
            $oSettings = &Api::GetSettings();
            if ($DbLogin !== null) {
                $oSettings->DBLogin = $DbLogin;
            }
            if ($DbPassword !== null) {
                $oSettings->DBPassword = $DbPassword;
            }
            if ($DbName !== null) {
                $oSettings->DBName = $DbName;
            }
            if ($DbHost !== null) {
                $oSettings->DBHost = $DbHost;
            }
            if ($AdminLogin !== null && $AdminLogin !== $oSettings->AdminLogin) {
                $aArgs = array(
                    'Login' => $AdminLogin
                );
                $this->broadcastEvent(
                    'CheckAccountExists',
                    $aArgs
                );

                $oSettings->AdminLogin = $AdminLogin;
            }

            $sAdminPassword = $oSettings->AdminPassword;
            if ((empty($sAdminPassword) && empty($Password) || !empty($Password)) && !empty($NewPassword)) {
                if (empty($sAdminPassword) || password_verify($Password, $sAdminPassword)) {
                    $oSettings->AdminPassword = password_hash(trim($NewPassword), PASSWORD_BCRYPT);
                } else {
                    throw new ApiException(Notifications::AccountOldPasswordNotCorrect);
                }
            }

            if ($AdminLanguage !== null) {
                $oSettings->AdminLanguage = $AdminLanguage;
            }
            if ($EnableLogging !== null) {
                $oSettings->EnableLogging = $EnableLogging;
            }
            if ($EnableEventLogging !== null) {
                $oSettings->EnableEventLogging = $EnableEventLogging;
            }
            if ($LoggingLevel !== null) {
                $oSettings->LoggingLevel = $LoggingLevel;
            }
            return $oSettings->Save();
        }

        if ($oUser->isNormalOrTenant()) {
            if ($Language !== null) {
                $oUser->Language = $Language;
            }
            if ($TimeFormat !== null) {
                $oUser->TimeFormat = $TimeFormat;
            }
            if ($DateFormat !== null) {
                $oUser->DateFormat = $DateFormat;
            }
            return $this->UpdateUserObject($oUser);
        }

        return false;
    }

    public function UpdateLoggingSettings($EnableLogging = null, $EnableEventLogging = null, $LoggingLevel = null)
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        $oSettings = &Api::GetSettings();

        if ($EnableLogging !== null) {
            $oSettings->EnableLogging = $EnableLogging;
        }
        if ($EnableEventLogging !== null) {
            $oSettings->EnableEventLogging = $EnableEventLogging;
        }
        if ($LoggingLevel !== null) {
            $oSettings->LoggingLevel = $LoggingLevel;
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
        Api::checkUserRoleIsAtLeast(UserRole::Anonymous);
        $oIntegrator = $this->getIntegratorManager();
        return $oIntegrator ? $oIntegrator->setMobile($Mobile) : false;
    }

    /**
     * @api {post} ?/Api/ CreateTables
     * @apiName CreateTables
     * @apiGroup Core
     * @apiDescription Creates tables reqired for module work. Creates first channel and tenant if it is necessary.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=CreateTables} Method Method name.
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'CreateTables'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {bool} Result.Result Indicates if tables was created successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
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
     * Creates tables required for module work. Creates first channel and tenant if it is necessary.
     *
     * @return bool
     */
    public function CreateTables()
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        if (!function_exists('mysqli_fetch_all')) {
            throw new ApiException(0, null, 'Please make sure your PHP/MySQL environment meets the minimal system requirements.');
        }

        $bResult = false;

        try {
            $container = Api::GetContainer();

            $oPdo = $container['connection']->getPdo();
            if ($oPdo && strpos($oPdo->getAttribute(\PDO::ATTR_CLIENT_VERSION), 'mysqlnd') === false) {
                throw new ApiException(ErrorCodes::MySqlConfigError, null, 'MySqlConfigError');
            }

            $container['console']->setAutoExit(false);

            $container['console']->find('migrate')
                ->run(new ArrayInput([
                    '--force' => true,
                    '--seed' => true
                ]), new NullOutput());

            $bResult = true;
        } catch (\Exception $oEx) {
            Api::LogException($oEx);
            if ($oEx instanceof ApiException) {
                throw $oEx;
            }
        }

        return $bResult;
    }

    public function GetOrphans()
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        $bResult = false;

        try {
            $container = Api::GetContainer();
            $container['console']->setAutoExit(false);

            $output = new BufferedOutput();
            $container['console']->find('get-orphans')
            ->run(new ArrayInput([]), $output);

            $content = array_filter(explode(PHP_EOL, $output->fetch()));
            $bResult = $content;
        } catch (\Exception $oEx) {
            Api::LogException($oEx);
        }

        return $bResult;
    }

    /**
     * Updates config files.
     * @return boolean
     */
    public function UpdateConfig()
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        return Api::UpdateSettings();
    }

    /**
     * @api {post} ?/Api/ TestDbConnection
     * @apiName TestDbConnection
     * @apiGroup Core
     * @apiDescription Tests connection to database with specified credentials.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=TestDbConnection} Method Method name.
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
     *	Parameters: '{ DbLogin: "db_login_value", DbName: "db_name_value", DbHost: "db_host_value",
     *		DbPassword: "db_pass_value" }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {bool} Result.Result Indicates if test of database connection was successful.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
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
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
        if (!function_exists('mysqli_fetch_all')) {
            throw new ApiException(0, null, 'Please make sure your PHP/MySQL environment meets the minimal system requirements.');
        }

        if (empty($DbName) || empty($DbHost) || empty($DbLogin)) {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        $oPdo = null;
        $oSettings = &Api::GetSettings();
        if ($oSettings) {
            if ($DbPassword === null) {
                $DbPassword = $oSettings->DBPassword;
            }
            $capsule = new \Illuminate\Database\Capsule\Manager();
            $capsule->addConnection(Api::GetDbConfig(
                $oSettings->DBType,
                $DbHost,
                $DbName,
                $oSettings->DBPrefix,
                $DbLogin,
                $DbPassword,
                $oSettings->DBEngine
            ));
            $oPdo = $capsule->getConnection()->getPdo();

            if ($oPdo && strpos($oPdo->getAttribute(\PDO::ATTR_CLIENT_VERSION), 'mysqlnd') === false) {
                throw new ApiException(ErrorCodes::MySqlConfigError, null, 'MySqlConfigError');
            }
        }

        return $oPdo instanceof \PDO;
    }

    /**
     * Obtains authenticated account.
     *
     * @param string $AuthToken
     */
    public function GetAuthenticatedAccount($AuthToken)
    {
        Api::checkUserRoleIsAtLeast(UserRole::Anonymous);

        $aUserInfo = Api::getAuthenticatedUserInfo($AuthToken);
        $oAccount = call_user_func_array([$aUserInfo['accountType'], 'find'], [(int)$aUserInfo['account']]);

        return $oAccount;
    }

    /**
     * Obtains all accounts from all modules for authenticated user.
     *
     * @param string $AuthToken
     * @param string $Type
     * @return array
     */
    public function GetAccounts($AuthToken, $Type = '')
    {
        Api::checkUserRoleIsAtLeast(UserRole::Anonymous);

        $aUserInfo = Api::getAuthenticatedUserInfo($AuthToken);

        $aResult = [];
        if (isset($aUserInfo['userId'])) {
            $aArgs = array(
                'UserId' => $aUserInfo['userId']
            );

            $this->broadcastEvent(
                'GetAccounts',
                $aArgs,
                $aResult
            );
        }

        if (!empty($Type)) {
            $aTempResult = [];
            foreach ($aResult as $aItem) {
                if ($aItem['Type'] === $Type) {
                    $aTempResult[] = $aItem;
                }
            }
            $aResult = $aTempResult;
        }

        return $aResult;
    }

    /**
     * Obtains all accounts from all modules by user.
     *
     * @param int $UserId
     * @param string $Type
     * @return array
     */
    public function GetUserAccounts($UserId, $Type = '')
    {
        Api::checkUserRoleIsAtLeast(UserRole::NormalUser);
        $aResult = [];

        $oAuthenticatedUser = Api::getAuthenticatedUser();

        // reset user id to authenticated user id if authenticated user exist
        // if user is not authenticated then checkUserRoleIsAtLeast will throw exception
        if ($oAuthenticatedUser) {
            $UserId = $oAuthenticatedUser->Id;
        }

        if ($UserId) {
            $aArgs = array(
                'UserId' => $UserId
            );

            $this->broadcastEvent(
                'GetAccounts',
                $aArgs,
                $aResult
            );
            if (!empty($Type)) {
                $aTempResult = [];
                foreach ($aResult as $aItem) {
                    if ($aItem['Type'] === $Type) {
                        $aTempResult[] = $aItem;
                    }
                }
                $aResult = $aTempResult;
            }
        }

        return $aResult;
    }

    /**
     * @param string $sEmail
     * @param string $sIp
     *
     * @throws ApiException
     */
    public function IsBlockedUser($sEmail, $sIp)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $bEnableFailedLoginBlock = $this->oModuleSettings->EnableFailedLoginBlock;
        $iLoginBlockAvailableTriesCount = $this->oModuleSettings->LoginBlockAvailableTriesCount;
        $iLoginBlockDurationMinutes = $this->oModuleSettings->LoginBlockDurationMinutes;

        if ($bEnableFailedLoginBlock) {
            try {
                $oBlockedUser = $this->GetBlockedUser($sEmail, $sIp);
                if ($oBlockedUser) {
                    if ($oBlockedUser->ErrorLoginsCount >= $iLoginBlockAvailableTriesCount) {
                        $iBlockTime = (time() - $oBlockedUser->Time) / 60;
                        if ($iBlockTime > $iLoginBlockDurationMinutes) {
                            $oBlockedUser->delete();
                        } else {
                            $this->BlockUser($sEmail, $sIp);
                            throw new ApiException(
                                1000,
                                null,
                                $this->i18N("BLOCKED_USER_MESSAGE_ERROR", [
                                    "N" => $iLoginBlockAvailableTriesCount,
                                    "M" => ceil($iLoginBlockDurationMinutes - $iBlockTime)
                                ])
                            );
                        }
                    }
                } elseif ($this->CheckIpReputation($sIp)) {
                    $this->BlockUser($sEmail, $sIp, true);

                    throw new ApiException(
                        1000,
                        null,
                        $this->i18N("BLOCKED_USER_IP_REPUTATION_MESSAGE_ERROR")
                    );
                }
            } catch (\Aurora\System\Exceptions\DbException $oEx) {
                Api::LogException($oEx);
            }
        }
    }

    /**
    * @param string $sEmail
    * @param string $sIp
    *
    * @return UserBlock|false
    */
    public function GetBlockedUser($sEmail, $sIp)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $mResult = false;

        if ($this->oModuleSettings->EnableFailedLoginBlock) {
            try {
                $mResult = UserBlock::where('Email', $sEmail)->where('IpAddress', $sIp)->first();
            } catch (\Exception $oEx) {
                $mResult = false;
            }
        }

        return $mResult;
    }

    /**
    * @param string $sIp
    *
    * @return bool
    */
    public function CheckIpReputation($sIp)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $mResult = false;

        if ($this->oModuleSettings->EnableFailedLoginBlock && is_numeric($this->oModuleSettings->LoginBlockIpReputationThreshold) && $this->oModuleSettings->LoginBlockIpReputationThreshold > 0) {
            $iLoginBlockAvailableTriesCount = $this->oModuleSettings->LoginBlockAvailableTriesCount;

            $count = UserBlock::where('IpAddress', $sIp)->where('ErrorLoginsCount', '>=', $iLoginBlockAvailableTriesCount)->count();

            $mResult = $count >= $this->oModuleSettings->LoginBlockIpReputationThreshold;
        }

        return $mResult;
    }

    /**
    * @param string $sIp
    * @param string $sIp
    * @param bool $bMaxErrorLoginsCount
    */
    public function BlockUser($sEmail, $sIp, $bMaxErrorLoginsCount = false)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        if ($this->oModuleSettings->EnableFailedLoginBlock) {

            try {
                $oBlockedUser = $this->GetBlockedUser($sEmail, $sIp);
                if (!$oBlockedUser) {
                    $oBlockedUser = new UserBlock();
                    $oBlockedUser->Email = $sEmail;
                    $oBlockedUser->IpAddress = $sIp;
                }
                $iUserId = Api::getUserIdByPublicId($sEmail);
                if ($iUserId) {
                    $oBlockedUser->UserId = $iUserId;
                    if ($bMaxErrorLoginsCount) {
                        $oBlockedUser->ErrorLoginsCount = $this->oModuleSettings->LoginBlockAvailableTriesCount;
                    } else {
                        $oBlockedUser->ErrorLoginsCount++;
                    }
                    $oBlockedUser->Time = time();

                    $oBlockedUser->save();
                }
            } catch (\Exception $oEx) {
                Api::LogException($oEx);
            }
        }
    }

    /**
    * @param string $Login
    * @param string $Password
    * @param bool $SignMe
    *
    * @return mixed
    */
    public function Authenticate($Login, $Password, $SignMe = false)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $sIp = \Aurora\System\Utils::getClientIp();
        $this->Decorator()->IsBlockedUser($Login, $sIp);

        $mResult = false;
        $aArgs = array(
            'Login' => $Login,
            'Password' => $Password,
            'SignMe' => $SignMe
        );

        try {
            $this->broadcastEvent(
                'Login',
                $aArgs,
                $mResult
            );
        } catch (\Exception $oException) {
            Api::GetModuleManager()->SetLastException($oException);
        }

        if (!$mResult) {
            $this->Decorator()->BlockUser($Login, $sIp);
            $this->Decorator()->IsBlockedUser($Login, $sIp);
        } else {
            $oBlockedUser = $this->Decorator()->GetBlockedUser($Login, $sIp);
            if ($oBlockedUser) {
                $oBlockedUser->delete();
            }
        }

        return $mResult;
    }

    /**
     *
     */
    public function SetAuthDataAndGetAuthToken($aAuthData, $Language = '', $SignMe = false)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $mResult = false;
        if ($aAuthData && is_array($aAuthData)) {
            $mResult = $aAuthData;
            if (isset($aAuthData['token'])) {
                $iTime = $SignMe ? 0 : time();
                $iAuthTokenExpirationLifetimeDays = Api::GetSettings()->AuthTokenExpirationLifetimeDays;
                $iExpire = 0;
                if ($iAuthTokenExpirationLifetimeDays > 0) {
                    $iExpire = time() + ($iAuthTokenExpirationLifetimeDays * 24 * 60 * 60);
                }

                $sAuthToken = Api::UserSession()->Set($aAuthData, $iTime, $iExpire);

                //this will store user data in static variable of Api class for later usage
                $oUser = Api::getAuthenticatedUser($sAuthToken, true);
                if ($oUser) {
                    if ($oUser->Role !== UserRole::SuperAdmin) {
                        // If User is super admin don't try to detect tenant. It will try to connect to DB.
                        // Super admin should be able to log in without connecting to DB.
                        $oTenant = Api::getTenantByWebDomain();
                        if ($oTenant && $oUser->IdTenant !== $oTenant->Id) {
                            throw new ApiException(Notifications::AuthError, null, 'AuthError');
                        }
                    }

                    if ($Language !== '' && $oUser->Language !== $Language) {
                        $oUser->Language = $Language;
                    }

                    $oUser->LastLogin = Carbon::now();
                    $oUser->LoginsCount =  $oUser->LoginsCount + 1;

                    $this->getUsersManager()->updateUser($oUser);
                    Api::LogEvent('login-success: ' . $oUser->PublicId, self::GetName());
                    $mResult = [
                        \Aurora\System\Application::AUTH_TOKEN_KEY => $sAuthToken
                    ];
                } else {
                    throw new ApiException(Notifications::AuthError, null, 'AuthError');
                }
            }
        } else {
            Api::LogEvent('login-failed', self::GetName());
            Api::GetModuleManager()->SetLastException(
                new ApiException(Notifications::AuthError, null, 'AuthError')
            );
        }

        return $mResult;
    }

    /**
     * @api {post} ?/Api/ Login
     * @apiName Login
     * @apiGroup Core
     * @apiDescription Broadcasts event Login to other modules, gets responses from them and returns AuthToken.
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=Login} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **Login** *string* Account login.<br>
     * &emsp; **Password** *string* Account password.<br>
     * &emsp; **Language** *string* New value of language for user.<br>
     * &emsp; **SignMe** *bool* Indicates if it is necessary to remember user between sessions. *optional*<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'Login',
     *	Parameters: '{ Login: "login_value", Password: "password_value", SignMe: true }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {mixed} Result.Result Object in case of success, otherwise **false**.
     * @apiSuccess {string} Result.Result.AuthToken Authentication token.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Core',
     *	Method: 'Login',
     *	Result: { AuthToken: 'token_value' }
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Core',
     *	Method: 'Login',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */
    /**
     * Broadcasts event Login to other modules, gets responses from them and returns AuthToken.
     *
     * @param string $Login Account login.
     * @param string $Password Account password.
     * @param string $Language New value of language for user.
     * @param bool $SignMe Indicates if it is necessary to remember user between sessions.
     * @return array
     * @throws ApiException
     */
    public function Login($Login, $Password, $Language = '', $SignMe = false)
    {
        Api::checkUserRoleIsAtLeast(UserRole::Anonymous);

        $Login = str_replace(" ", "", $Login);
        $aAuthData = $this->Decorator()->Authenticate($Login, $Password, $SignMe);

        return $this->Decorator()->SetAuthDataAndGetAuthToken($aAuthData, $Language, $SignMe);
    }

    public function GetAccountUsedToAuthorize($Login, $Disabled = false)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $mResult = null;

        $aArgs = array(
            'Login' => $Login,
            'Disabled' => $Disabled,
        );

        $this->broadcastEvent(
            'GetAccountUsedToAuthorize',
            $aArgs,
            $mResult
        );

        return $mResult;
    }

    /**
     * @param string $Password Account password.
     * @return bool
     * @throws ApiException
     */
    public function VerifyPassword($Password)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        Api::checkUserRoleIsAtLeast(UserRole::Anonymous);
        $mResult = false;
        $bResult = false;

        $oApiIntegrator = $this->getIntegratorManager();
        $aUserInfo = $oApiIntegrator->getAuthenticatedUserInfo(Api::getAuthToken());
        if (isset($aUserInfo['account']) && isset($aUserInfo['accountType'])) {
            $r = new \ReflectionClass($aUserInfo['accountType']);
            $oQuery = $r->getMethod('query')->invoke(null);

            $oAccount = $oQuery->find($aUserInfo['account']);
            if ($oAccount) {
                $aArgs = array(
                    'Login' => $oAccount->getLogin(),
                    'Password' => $Password,
                    'SignMe' => false
                );
                $this->broadcastEvent(
                    'Login',
                    $aArgs,
                    $mResult
                );

                if (is_array($mResult)
                    && isset($mResult['token'])
                    && $mResult['token'] === 'auth'
                    && isset($mResult['id'])
                ) {
                    $UserId = Api::getAuthenticatedUserId();
                    if ($mResult['id'] === $UserId) {
                        $bResult = true;
                    }
                }
            }
        }

        return $bResult;
    }

    /**
     * @param $email
     * @param $resetOption
     * @return bool
     */
    public function ResetPassword($email, $resetOption)
    {
        $mResult = false;

        $aArgs = array(
            'email' => $email,
            'resetOption' => $resetOption
        );
        $this->broadcastEvent(
            'ResetPassword',
            $aArgs,
            $mResult
        );


        if (!empty($mResult)) {
            Api::LogEvent('resetPassword-success: ' . $email, self::GetName());
        } else {
            Api::LogEvent('resetPassword-failed: ' . $email, self::GetName());
        }

        return $mResult;
    }


    /**
     *
     */
    public function ResetPasswordBySecurityQuestion($securityAnswer, $securityToken)
    {
        $mResult = false;

        $aArgs = array(
            'securityAnswer' => $securityAnswer,
            'securityToken' => $securityToken
        );
        $this->broadcastEvent(
            'ResetPasswordBySecurityQuestion',
            $aArgs,
            $mResult
        );


        if (!empty($mResult)) {
            Api::LogEvent('ResetPasswordBySecurityQuestion-success: ' . $securityAnswer, self::GetName());
            return $mResult;
        }

        Api::LogEvent('ResetPasswordBySecurityQuestion-failed: ' . $securityAnswer, self::GetName());
    }

    /**
     *
     */
    public function UpdatePassword($Password, $ConfirmPassword, $Hash)
    {
        $mResult = false;

        $aArgs = array(
            'Password' => $Password,
            'ConfirmPassword' => $ConfirmPassword,
            'Hash' => $Hash
        );
        $this->broadcastEvent(
            'UpdatePassword',
            $aArgs,
            $mResult
        );

        if (!empty($mResult)) {
            Api::LogEvent('updatePassword-success: ' . $Hash, self::GetName());
            return $mResult;
        }

        Api::LogEvent('updatePassword-failed: ' . $Hash, self::GetName());
    }

    /**
     * @api {post} ?/Api/ Logout
     * @apiName Logout
     * @apiGroup Core
     * @apiDescription Logs out authenticated user. Clears session.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=Logout} Method Method name.
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'Logout'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {bool} Result.Result Indicates if logout was successful.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
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
     * @throws ApiException
     */
    public function Logout()
    {
        Api::checkUserRoleIsAtLeast(UserRole::Anonymous);

        Api::LogEvent('logout', self::GetName());

        Api::UserSession()->Delete(
            Api::getAuthToken()
        );

        return true;
    }

    /**
     *
     */
    public function ClearSeparateLogs()
    {
        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        Api::RemoveSeparateLogs();

        return true;
    }

    /**
     *
     */
    public function GetLogFilesData()
    {
        $aData = [];

        $sFileName = Api::GetLogFileName();
        $sFilePath = Api::GetLogFileDir() . $sFileName;
        $aData['LogFileName'] = $sFileName;
        $aData['LogSizeBytes'] = file_exists($sFilePath) ? filesize($sFilePath) : 0;

        $sEventFileName = Api::GetLogFileName(Logger::$sEventLogPrefix);
        $sEventFilePath = Api::GetLogFileDir() . $sEventFileName;
        $aData['EventLogFileName'] = $sEventFileName;
        $aData['EventLogSizeBytes'] = file_exists($sEventFilePath) ? filesize($sEventFilePath) : 0;

        $sErrorFileName = Api::GetLogFileName(Logger::$sErrorLogPrefix);
        $sErrorFilePath = Api::GetLogFileDir() . $sErrorFileName;
        $aData['ErrorLogFileName'] = $sErrorFileName;
        $aData['ErrorLogSizeBytes'] = file_exists($sErrorFilePath) ? filesize($sErrorFilePath) : 0;

        return $aData;
    }

    public function GetLogFile($FilePrefix = '', $PublicId = '')
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        if ($PublicId !== '') {
            $FilePrefix = $PublicId . '-';
        }
        $sFileName = Api::GetLogFileDir() . Api::GetLogFileName($FilePrefix);

        if (file_exists($sFileName)) {
            $mResult = fopen($sFileName, "r");

            if (false !== $mResult && is_resource($mResult)) {
                $sContentType = \MailSo\Base\Utils::MimeContentType($sFileName);
                \Aurora\System\Managers\Response::OutputHeaders(true, $sContentType, $sFileName);

                if ($sContentType === 'text/plain') {
                    $sLogData = stream_get_contents($mResult);
                    echo(\MailSo\Base\HtmlUtils::ClearTags($sLogData));
                } else {
                    \MailSo\Base\Utils::FpassthruWithTimeLimitReset($mResult, 8192, function ($sData) {
                        return \MailSo\Base\HtmlUtils::ClearTags($sData);
                    });
                }

                @fclose($mResult);
            }
        }
    }

    /**
     *
     */
    public function GetLog($FilePrefix = '', $PartSize = 10240)
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        $sFileName = Api::GetLogFileDir() . Api::GetLogFileName($FilePrefix);

        $logData = '';

        if (file_exists($sFileName)) {
            $iOffset = filesize($sFileName) - $PartSize;
            $iOffset = $iOffset < 0 ? 0 : $iOffset;
            $logData = \MailSo\Base\HtmlUtils::ClearTags(file_get_contents($sFileName, false, null, $iOffset, $PartSize));
        }

        return $logData;
    }

    /**
     *
     * @param string $FilePrefix
     * @return bool
     */
    public function ClearLog($FilePrefix = '')
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        $sFileName = Api::GetLogFileDir() . Api::GetLogFileName($FilePrefix);

        return Api::ClearLog($sFileName);
    }

    /**
     *
     */
    public function GetCompatibilities()
    {
        return [];
    }

    /**
     *
     */
    public function IsModuleDisabledForObject($oObject, $sModuleName)
    {
        return ($oObject instanceof \Aurora\System\Classes\Model) ? $oObject->isModuleDisabled($sModuleName) : false;
    }
    /***** public functions might be called with web API *****/

}
