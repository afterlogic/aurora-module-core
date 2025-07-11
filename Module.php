<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core;

use Aurora\Api;
use Aurora\Modules\Contacts\Enums\StorageType;
use Aurora\Modules\Contacts\Module as ContactsModule;
use Aurora\Modules\Core\Enums\ErrorCodes;
use Aurora\Modules\Core\Models\Group;
use Aurora\Modules\Core\Models\User;
use Aurora\Modules\Core\Models\UserBlock;
use Aurora\System\Enums\UserRole;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Notifications;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\BufferedOutput;
use Aurora\System\Logger;
use Aurora\System\Managers\Integrator;

/**
 * System module that provides core functionality such as User management, Tenants management.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    protected $oTenantsManager = null;

    protected $oChannelsManager = null;

    protected $oUsersManager = null;

    protected $oIntegratorManager = null;

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /**
     * @return Managers\Tenants
     */
    public function getTenantsManager()
    {
        if ($this->oTenantsManager === null) {
            $this->oTenantsManager = new Managers\Tenants($this);
        }

        return $this->oTenantsManager;
    }

    /**
     * @return Managers\Channels
     */
    public function getChannelsManager()
    {
        if ($this->oChannelsManager === null) {
            $this->oChannelsManager = new Managers\Channels($this);
        }

        return $this->oChannelsManager;
    }

    /**
     * @return Managers\Users
     */
    public function getUsersManager()
    {
        if ($this->oUsersManager === null) {
            $this->oUsersManager = new Managers\Users($this);
        }

        return $this->oUsersManager;
    }

    /**
     * @return \Aurora\System\Managers\Integrator
     */
    public function getIntegratorManager()
    {
        if ($this->oIntegratorManager === null) {
            $this->oIntegratorManager = new \Aurora\System\Managers\Integrator();
        }

        return $this->oIntegratorManager;
    }

    /***** private functions *****/
    /**
     * Initializes Core Module.
     *
     * @ignore
     */
    public function init()
    {
        $this->aErrors = [
            Enums\ErrorCodes::ChannelDoesNotExist => $this->i18N('ERROR_CHANNEL_NOT_EXISTS'),
            Enums\ErrorCodes::TenantAlreadyExists => $this->i18N('ERROR_TENANT_ALREADY_EXISTS'),
            Enums\ErrorCodes::GroupAlreadyExists => $this->i18N('ERROR_GROUP_ALREADY_EXISTS'),
            Enums\ErrorCodes::MySqlConfigError => 'Please make sure your PHP/MySQL environment meets the minimal system requirements.',
        ];

        \Aurora\System\Router::getInstance()->registerArray(
            self::GetName(),
            [
                'api' => [$this, 'EntryApi'],
                'ping' => [$this, 'EntryPing'],
                'pull' => [$this, 'EntryPull'],
                'mobile' => [$this, 'EntryMobile'],
                'file-cache' => [$this, 'EntryFileCache']
            ]
        );

        \Aurora\System\EventEmitter::getInstance()->onAny(
            [
                ['CreateAccount', [$this, 'onCreateAccount'], 100],
                ['Core::GetCompatibilities::after', [$this, 'onAfterGetCompatibilities']],
                ['System::RunEntry::before', [$this, 'onBeforeRunEntry'], 100]
            ]
        );

        $this->denyMethodsCallByWebApi([
            'Authenticate',
            'UpdateUserObject',
            'GetUserByPublicId',
            'GetAdminUser',
            'GetTenantName',
            'GetTenantIdByName',
            'GetDefaultGlobalTenant',
            'UpdateTokensValidFromTimestamp',
            'GetAccountUsedToAuthorize',
            'GetDigestHash',
            'VerifyPassword',
            'SetAuthDataAndGetAuthToken',
            'GetBlockedUser',
            'BlockUser',
            'IsBlockedUser',
            'GetAllGroup',
            'CheckIpReputation'
        ]);
    }

    /**
     *
     * @return mixed
     */
    private function getUploadData()
    {
        $mResult = false;
        $oFile = null;
        if (count($_FILES) > 0) {
            $oFile = current($_FILES);
        }
        if (isset($oFile, $oFile['name'], $oFile['tmp_name'], $oFile['size'], $oFile['type'])) {
            $iError = (isset($oFile['error'])) ? (int) $oFile['error'] : UPLOAD_ERR_OK;
            $mResult = (UPLOAD_ERR_OK === $iError) ? $oFile : false;
        }

        return $mResult;
    }

    /**
     * Is called by CreateAccount event. Finds or creates and returns User for new account.
     *
     * @ignore
     * @param array $Args {
     *		*int* **UserId** Identifier of existing user.
     *		*int* **TenantId** Identifier of tenant for creating new user in it.
     *		*int* **$PublicId** New user name.
     * }
     * @param Models\User $Result
     */
    public function onCreateAccount(&$Args, &$Result)
    {
        $oUser = null;

        if (isset($Args['UserId']) && (int)$Args['UserId'] > 0) {
            $oUser = $this->getUsersManager()->getUser($Args['UserId']);
        } else {
            $Email = (isset($Args['Email'])) ? $Args['Email'] : '';
            $PublicId = (isset($Args['PublicId'])) ? $Args['PublicId'] : '';
            $sPublicId = null;
            if (!empty($PublicId)) {
                $sPublicId = $PublicId;
            } elseif (!empty($Email)) {
                $sPublicId = $Email;
            }
            if (!empty($sPublicId)) {
                $oUser = $this->getUsersManager()->getUserByPublicId($sPublicId);
            }
            if (!isset($oUser)) {
                $bPrevState = Api::skipCheckUserRole(true);
                $iUserId = self::Decorator()->CreateUser(isset($Args['TenantId']) ? (int) $Args['TenantId'] : 0, $sPublicId);
                Api::skipCheckUserRole($bPrevState);
                $oUser = $this->getUsersManager()->getUser($iUserId);
            }

            if (isset($oUser) && isset($oUser->Id)) {
                $Args['UserId'] = $oUser->Id;
            }
        }

        $Result = $oUser;
    }

    /**
     * @ignore
     * @param array $aArgs
     * @param array $mResult
     */
    public function onAfterGetCompatibilities($aArgs, &$mResult)
    {
        $aCompatibility['php.version'] = phpversion();
        $aCompatibility['php.version.valid'] = (int) (version_compare($aCompatibility['php.version'], '7.2.5') > -1);

        $aCompatibility['safe-mode'] = @ini_get('safe_mode');
        $aCompatibility['safe-mode.valid'] = is_numeric($aCompatibility['safe-mode'])
            ? !((bool) $aCompatibility['safe-mode'])
            : ('off' === strtolower($aCompatibility['safe-mode']) || empty($aCompatibility['safe-mode']));

        $aCompatibility['mysql.valid'] = (int) extension_loaded('mysql');
        $aCompatibility['pdo.valid'] = (int)
            ((bool) extension_loaded('pdo') && (bool) extension_loaded('pdo_mysql'));

        $aCompatibility['mysqlnd.valid'] = (int) (
            function_exists('mysqli_fetch_all') &&
            strpos(mysqli_get_client_info(), "mysqlnd") !== false
        );

        $aCompatibility['socket.valid'] = (int) function_exists('fsockopen');
        $aCompatibility['iconv.valid'] = (int) function_exists('iconv');
        $aCompatibility['curl.valid'] = (int) function_exists('curl_init');
        $aCompatibility['mbstring.valid'] = (int) function_exists('mb_detect_encoding');
        $aCompatibility['openssl.valid'] = (int) extension_loaded('openssl');
        $aCompatibility['xml.valid'] = (int) (class_exists('DOMDocument') && function_exists('xml_parser_create'));
        $aCompatibility['json.valid'] = (int) function_exists('json_decode');
        $aCompatibility['gd.valid'] = (int) extension_loaded('gd');

        $aCompatibility['ini-get.valid'] = (int) function_exists('ini_get');
        $aCompatibility['ini-set.valid'] = (int) function_exists('ini_set');
        $aCompatibility['set-time-limit.valid'] = (int) function_exists('set_time_limit');

        $aCompatibility['session.valid'] = (int) (function_exists('session_start') && isset($_SESSION['checksessionindex']));

        $dataPath = Api::DataPath();

        $aCompatibility['data.dir'] = $dataPath;
        $aCompatibility['data.dir.valid'] = (int) (@is_dir($aCompatibility['data.dir']) && @is_writable($aCompatibility['data.dir']));

        $sTempPathName = '_must_be_deleted_' . md5(time());

        $aCompatibility['data.dir.create'] =
            (int) @mkdir($aCompatibility['data.dir'] . '/' . $sTempPathName);
        $aCompatibility['data.file.create'] =
            (int) (bool) @fopen($aCompatibility['data.dir'] . '/' . $sTempPathName . '/' . $sTempPathName . '.test', 'w+');
        $aCompatibility['data.file.delete'] =
            (int) (bool) @unlink($aCompatibility['data.dir'] . '/' . $sTempPathName . '/' . $sTempPathName . '.test');
        $aCompatibility['data.dir.delete'] =
            (int) @rmdir($aCompatibility['data.dir'] . '/' . $sTempPathName);


        $oSettings = &Api::GetSettings();

        $aCompatibility['settings.file'] = $oSettings ? $oSettings->GetPath() : '';

        $aCompatibility['settings.file.exist'] = (int) @file_exists($aCompatibility['settings.file']);
        $aCompatibility['settings.file.read'] = (int) @is_readable($aCompatibility['settings.file']);
        $aCompatibility['settings.file.write'] = (int) @is_writable($aCompatibility['settings.file']);

        $aCompatibilities = [
            [
                'Name' => 'PHP version',
                'Result' => $aCompatibility['php.version.valid'],
                'Value' => $aCompatibility['php.version.valid']
                ? 'OK'
                : [$aCompatibility['php.version'] . ' detected, 7.2.5 or above required.',
'You need to upgrade PHP engine installed on your server.
If it\'s a dedicated or your local server, you can download the latest version of PHP from its
<a href="http://php.net/downloads.php" target="_blank">official site</a> and install it yourself.
In case of a shared hosting, you need to ask your hosting provider to perform the upgrade.']
            ],
            [
                'Name' => 'Safe Mode is off',
                'Result' => $aCompatibility['safe-mode.valid'],
                'Value' => ($aCompatibility['safe-mode.valid'])
                ? 'OK'
                : ['Error, safe_mode is enabled.',
'You need to <a href="http://php.net/manual/en/ini.sect.safe-mode.php" target="_blank">disable it in your php.ini</a>
or contact your hosting provider and ask to do this.']
            ],
            [
                'Name' => 'PDO MySQL Extension',
                'Result' => $aCompatibility['pdo.valid'],
                'Value' => ($aCompatibility['pdo.valid'])
                ? 'OK'
                : ['Error, PHP PDO MySQL extension not detected.',
'You need to install this PHP extension or enable it in php.ini file.']
            ],
            [
                'Name' => 'MySQL Native Driver (mysqlnd)',
                'Result' => $aCompatibility['mysqlnd.valid'],
                'Value' => ($aCompatibility['mysqlnd.valid'])
                ? 'OK'
                : ['Error, MySQL Native Driver not found.',
'You need to install this PHP extension or enable it in php.ini file.']
            ],
            [
                'Name' => 'Iconv Extension',
                'Result' => $aCompatibility['iconv.valid'],
                'Value' => ($aCompatibility['iconv.valid'])
                ? 'OK'
                : ['Error, iconv extension not detected.',
'You need to install this PHP extension or enable it in php.ini file.']
            ],
            [
                'Name' => 'Multibyte String Extension',
                'Result' => $aCompatibility['mbstring.valid'],
                'Value' => ($aCompatibility['mbstring.valid'])
                ? 'OK'
                : ['Error, mb_string extension not detected.',
'You need to install this PHP extension or enable it in php.ini file.']
            ],
            [
                'Name' => 'CURL Extension',
                'Result' => $aCompatibility['curl.valid'],
                'Value' => ($aCompatibility['curl.valid'])
                ? 'OK'
                : ['Error, curl extension not detected.',
'You need to install this PHP extension or enable it in php.ini file.']
            ],
            [
                'Name' => 'JSON Extension',
                'Result' => $aCompatibility['json.valid'],
                'Value' => ($aCompatibility['json.valid'])
                ? 'OK'
                : ['Error, JSON extension not detected.',
'You need to install this PHP extension or enable it in php.ini file.']
            ],
            [
                'Name' => 'XML/DOM Extension',
                'Result' => $aCompatibility['xml.valid'],
                'Value' => ($aCompatibility['xml.valid'])
                ? 'OK'
                : ['Error, xml (DOM) extension not detected.',
'You need to install this PHP extension or enable it in php.ini file.']
            ],
            [
                'Name' => 'GD Extension',
                'Result' => $aCompatibility['gd.valid'],
                'Value' => ($aCompatibility['gd.valid'])
                ? 'OK'
                : ['Error, GD extension not detected.',
'You need to install this PHP extension or enable it in php.ini file.']
            ],
            [
                'Name' => 'Sockets',
                'Result' => $aCompatibility['socket.valid'],
                'Value' => ($aCompatibility['socket.valid'])
                ? 'OK'
                : ['Error, creating network sockets must be enabled.', '
To enable sockets, you should remove fsockopen function from the list of prohibited functions in disable_functions directive of your php.ini file.
In case of a shared hosting, you need to ask your hosting provider to do this.']
            ],
            [
                'Name' => 'SSL (OpenSSL extension)',
                'Result' => $aCompatibility['openssl.valid'],
                'Value' => ($aCompatibility['openssl.valid'])
                ? 'OK'
                : ['SSL connections (like Gmail) will not be available. ', '
You need to enable OpenSSL support in your PHP configuration and make sure OpenSSL library is installed on your server.
For instructions, please refer to the official PHP documentation. In case of a shared hosting,
you need to ask your hosting provider to enable OpenSSL support.
You may ignore this if you\'re not going to connect to SSL-only mail servers (like Gmail).']
            ],
            [
                'Name' => 'Setting memory limits',
                'Result' => $aCompatibility['ini-get.valid'],
                'Value' => ($aCompatibility['ini-get.valid'] && $aCompatibility['ini-set.valid'])
                ? 'OK'
                : ['Opening large e-mails may fail.', '
You need to enable setting memory limits in your PHP configuration, i.e. remove ini_get and ini_set functions
from the list of prohibited functions in disable_functions directive of your php.ini file.
In case of a shared hosting, you need to ask your hosting provider to do this.']
            ],
            [
                'Name' => 'Setting script timeout',
                'Result' => $aCompatibility['set-time-limit.valid'],
                'Value' => ($aCompatibility['set-time-limit.valid'])
                ? 'OK'
                : ['Downloading large mailboxes may fail.', '
To enable setting script timeout, you should remove set_time_limit function from the list
of prohibited functions in disable_functions directive of your php.ini file.
In case of a shared hosting, you need to ask your hosting provider to do this.']
            ],
            [
                'Name' => 'WebMail data directory',
                'Result' => $aCompatibility['data.dir.valid'],
                'Value' => ($aCompatibility['data.dir.valid'])
                ? 'Found'
                : ['Error, data directory path discovery failure.']
            ],
            [
                'Name' => 'Creating/deleting directories',
                'Result' => $aCompatibility['data.dir.create'] && $aCompatibility['data.dir.delete'],
                'Value' => ($aCompatibility['data.dir.create'] && $aCompatibility['data.dir.delete'])
                ? 'OK'
                : ['Error, can\'t create/delete sub-directories in the data directory.', '
You need to grant read/write permission over data directory and all its contents to your web server user.
For instructions, please refer to this section of documentation and our
<a href="https://afterlogic.com/docs/webmail-pro-8/troubleshooting/troubleshooting-issues-with-data-directory" target="_blank">FAQ</a>.']
            ],
            [
                'Name' => 'Creating/deleting files',
                'Result' => $aCompatibility['data.file.create'] && $aCompatibility['data.file.delete'],
                'Value' => ($aCompatibility['data.file.create'] && $aCompatibility['data.file.delete'])
                ? 'OK'
                : ['Error, can\'t create/delete files in the data directory.', '
You need to grant read/write permission over data directory and all its contents to your web server user.
For instructions, please refer to this section of documentation and our
<a href="https://afterlogic.com/docs/webmail-pro-8/troubleshooting/troubleshooting-issues-with-data-directory" target="_blank">FAQ</a>.']
            ],
            [
                'Name' => 'WebMail Settings File',
                'Result' => $aCompatibility['settings.file.exist'],
                'Value' => ($aCompatibility['settings.file.exist'])
                ? 'Found'
                : ['Not Found, can\'t find "' . $aCompatibility['settings.file'] . '" file.', '
Make sure you completely copied the data directory with all its contents from installation package.
By default, the data directory is webmail subdirectory, and if it\'s not the case make sure its location matches one specified in inc_settings_path.php file.']
            ],
            [
                'Name' => 'Read/write settings file',
                'Result' => $aCompatibility['settings.file.read'] && $aCompatibility['settings.file.write'],
                'Value' => ($aCompatibility['settings.file.read'] && $aCompatibility['settings.file.write'])
                ? 'OK / OK'
                : ['Not Found, can\'t find "' . $aCompatibility['settings.file'] . '" file.', '
You should grant read/write permission over settings file to your web server user.
For instructions, please refer to this section of documentation and our
<a href="https://afterlogic.com/docs/webmail-pro-8/troubleshooting/troubleshooting-issues-with-data-directory" target="_blank">FAQ</a>.']
            ],
        ];

        $mResult[self::GetName()] = $aCompatibilities;
    }

    public function onBeforeRunEntry($aArgs, &$mResult)
    {
        \Aurora\Api::removeOldLogs();

        return $this->redirectToHttps($aArgs['EntryName'], $mResult);
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

    protected function redirectToHttps($sEntryName, $mResult)
    {
        $oSettings = &\Aurora\Api::GetSettings();
        if ($oSettings) {
            $bRedirectToHttps = $oSettings->RedirectToHttps;

            $bHttps = \Aurora\Api::isHttps();
            if ($bRedirectToHttps && !$bHttps) {
                if (\strtolower($sEntryName) !== 'api') {
                    \header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                } else {
                    $mResult = [
                        'ErrorCode' => 110
                    ];
                    return true;
                }
            }
        }
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
    /**
     *
     * @return string
     * @throws ApiException
     */
    public function EntryApi()
    {
        @ob_start();

        if (!is_writable(Api::DataPath())) {
            throw new ApiException(Notifications::SystemNotConfigured, null, 'Check the write permission of the data folder');
        }

        $aResponseItem = null;
        $sModule = $this->oHttp->GetPost('Module', null);
        $sMethod = $this->oHttp->GetPost('Method', null);
        $sParameters = $this->oHttp->GetPost('Parameters', null);
        $sFormat = $this->oHttp->GetPost('Format', null);
        $sTenantName = $this->oHttp->GetPost('TenantName', null);

        if (isset($sModule, $sMethod)) {
            $oModule = Api::GetModule($sModule);
            if ($oModule instanceof \Aurora\System\Module\AbstractModule) {
                try {
                    Api::Log(" ");
                    Api::Log(" ===== API: " . $sModule . '::' . $sMethod);

                    Api::validateAuthToken();

                    if (!empty($sMethod)) {
                        Api::setTenantName($sTenantName);

                        $aParameters = [];
                        if (isset($sParameters) && \is_string($sParameters) && !empty($sParameters)) {
                            $aParameters = @\json_decode($sParameters, true);

                            if (json_last_error() !== JSON_ERROR_NONE) {
                                throw new ApiException(
                                    Notifications::InvalidInputParameter,
                                    null,
                                    'InvalidInputParameter'
                                );
                            }

                            if (!\is_array($aParameters)) {
                                $aParameters = array($aParameters);
                            }
                        }

                        $mUploadData = $this->getUploadData();
                        if (\is_array($mUploadData)) {
                            $aParameters['UploadData'] = $mUploadData;
                        }

                        $oModule->CallMethod(
                            $sMethod,
                            $aParameters,
                            true
                        );

                        $oLastException = Api::GetModuleManager()->GetLastException();
                        if (isset($oLastException)) {
                            throw $oLastException;
                        }

                        $aResponseItem = $oModule->DefaultResponse(
                            $sMethod,
                            Api::GetModuleManager()->GetResults()
                        );
                    }

                    if (!\is_array($aResponseItem)) {
                        throw new ApiException(
                            Notifications::UnknownError,
                            null,
                            'UnknownError'
                        );
                    }
                } catch (\Exception $oException) {
                    Api::LogException($oException);

                    $aAdditionalParams = null;
                    if ($oException instanceof ApiException) {
                        if (!$oException->GetModule()) {
                            $oException = new ApiException(
                                $oException->getCode(),
                                $oException->getPrevious(),
                                $oException->getMessage(),
                                $oException->GetObjectParams(),
                                $oModule
                            );
                        }
                        $aAdditionalParams = $oException->GetObjectParams();
                    }

                    $aResponseItem = $oModule->ExceptionResponse(
                        $sMethod,
                        $oException,
                        $aAdditionalParams
                    );
                }
            } else {
                $oException = new ApiException(
                    Notifications::ModuleNotFound,
                    null,
                    'Module not found'
                );
                $aResponseItem = $this->ExceptionResponse(
                    $sMethod,
                    $oException
                );
            }
        } else {
            $oException = new ApiException(
                Notifications::InvalidInputParameter,
                null,
                'Invalid input parameter'
            );
            $aResponseItem = $this->ExceptionResponse(
                $sMethod,
                $oException
            );
        }

        if (isset($aResponseItem['Parameters'])) {
            unset($aResponseItem['Parameters']);
        }

        return \Aurora\System\Managers\Response::GetJsonFromObject($sFormat, $aResponseItem);
    }

    /**
     * @ignore
     */
    public function EntryMobile()
    {
        $oApiIntegrator = $this->getIntegratorManager();
        $oApiIntegrator->setMobile(true);

        Api::Location('./');
    }

    public function EntryFileCache()
    {
        Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $sRawKey = \Aurora\System\Router::getItemByIndex(1, '');
        $sAction = \Aurora\System\Router::getItemByIndex(2, '');
        $aValues = Api::DecodeKeyValues($sRawKey);

        $bDownload = true;
        $bThumbnail = false;

        switch ($sAction) {
            case 'view':
                $bDownload = false;
                $bThumbnail = false;
                break;
            case 'thumb':
                $bDownload = false;
                $bThumbnail = true;
                break;
            default:
                $bDownload = true;
                $bThumbnail = false;
                break;
        }

        $iUserId = (isset($aValues['UserId'])) ? $aValues['UserId'] : 0;

        if (isset($aValues['TempFile'], $aValues['TempName'], $aValues['Name'])) {
            $sModule = isset($aValues['Module']) && !empty($aValues['Module']) ? $aValues['Module'] : 'System';
            $sUUID = Api::getUserUUIDById($iUserId);
            $oApiFileCache = new \Aurora\System\Managers\Filecache();
            $mResult = $oApiFileCache->getFile($sUUID, $aValues['TempName'], '', $sModule);

            if (is_resource($mResult)) {
                $sFileName = $aValues['Name'];
                $sContentType = (empty($sFileName)) ? 'text/plain' : \MailSo\Base\Utils::MimeContentType($sFileName);
                $sFileName = \Aurora\System\Utils::clearFileName($sFileName, $sContentType);

                \Aurora\System\Utils::OutputFileResource($sUUID, $sContentType, $sFileName, $mResult, $bThumbnail, $bDownload);
            }
        }
    }

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

    /**
     * !Not public
     * This method is restricted to be called by web API (see denyMethodsCallByWebApi method).
     *
     * Updates user by object.
     *
     * @param Models\User $oUser
     * @return bool
     */
    public function UpdateUserObject($oUser)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        return $this->getUsersManager()->updateUser($oUser);
    }

    /**
     * !Not public
     * This method is restricted to be called by web API (see denyMethodsCallByWebApi method).
     *
     * Returns user object.
     *
     * @param string $PublicId User public identifier.
     * @return Models\User
     */
    public function GetUserByPublicId($PublicId)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $oUser = $this->getUsersManager()->getUserByPublicId($PublicId);

        return $oUser ? $oUser : null;
    }

    /**
     * !Not public
     * This method is restricted to be called by web API (see denyMethodsCallByWebApi method).
     *
     * Creates and returns user with super administrator role.
     *
     * @deprecated sinse version 9.7.8
     *
     * @return Models\User
     */
    public function GetAdminUser()
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        return Integrator::GetAdminUser();
    }

    /**
     * !Not public
     * This method is restricted to be called by web API (see denyMethodsCallByWebApi method).
     *
     * Returns tenant identifier by tenant name.
     *
     * @param string $TenantName Tenant name.
     * @return int|null
     */
    public function GetTenantIdByName($TenantName = '')
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $iTenantId = $this->getTenantsManager()->getTenantIdByName((string) $TenantName);

        return $iTenantId ? $iTenantId : null;
    }

    /**
     * !Not public
     * This method is restricted to be called by web API (see denyMethodsCallByWebApi method).
     *
     * Returns current tenant name.
     *
     * @return string
     */
    public function GetTenantName()
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $sTenant = '';

        $oUser = Api::getAuthenticatedUser();
        if ($oUser) {
            $oTenant = \Aurora\Api::getTenantById($oUser->IdTenant);
            if ($oTenant) {
                $sTenant = $oTenant->Name;

                $sPostTenant = $this->oHttp->GetPost('TenantName', '');
                if (!empty($sPostTenant) && !empty($sTenant) && $sPostTenant !== $sTenant) {
                    $sTenant = '';
                }
            }
        } else {
            $sTenant = $this->oHttp->GetRequest('tenant', '');
        }
        Api::setTenantName($sTenant);
        return $sTenant;
    }

    /**
     * !Not public
     * This method is restricted to be called by web API (see denyMethodsCallByWebApi method).
     *
     * Returns default global tenant.
     *
     * @return Models\Tenant
     */
    public function GetDefaultGlobalTenant()
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $oTenant = $this->getTenantsManager()->getDefaultGlobalTenant();

        return $oTenant ? $oTenant : null;
    }

    /**
     * !Not public
     * This method is restricted to be called by web API (see denyMethodsCallByWebApi method).
     *
     * @param Models\User $oUser
     * @return int
     */
    public function UpdateTokensValidFromTimestamp($oUser)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $oUser->TokensValidFromTimestamp = time();
        $this->getUsersManager()->updateUser($oUser);
        return $oUser->TokensValidFromTimestamp;
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
    public function DoServerInitializations($Timezone = '')
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
                'ELogLevel' => (new \Aurora\System\Enums\LogLevel())->getMap()
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
            $container = \Aurora\Api::GetContainer();

            $oPdo = $container['connection']->getPdo();
            if ($oPdo && strpos($oPdo->getAttribute(\PDO::ATTR_CLIENT_VERSION), 'mysqlnd') === false) {
                throw new ApiException(Enums\ErrorCodes::MySqlConfigError, null, 'MySqlConfigError');
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
            $container = \Aurora\Api::GetContainer();
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
                $DbPassword
            ));
            $oPdo = $capsule->getConnection()->getPdo();

            if ($oPdo && strpos($oPdo->getAttribute(\PDO::ATTR_CLIENT_VERSION), 'mysqlnd') === false) {
                throw new ApiException(Enums\ErrorCodes::MySqlConfigError, null, 'MySqlConfigError');
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
    * @return Models\UserBlock|false
    */
    public function GetBlockedUser($sEmail, $sIp)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $mResult = false;

        if ($this->oModuleSettings->EnableFailedLoginBlock) {
            try {
                $mResult = Models\UserBlock::where('Email', $sEmail)->where('IpAddress', $sIp)->first();
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

            $count = Models\UserBlock::where('IpAddress', $sIp)->where('ErrorLoginsCount', '>=', $iLoginBlockAvailableTriesCount)->count();

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
                    $oBlockedUser = new Models\UserBlock();
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
                $iAuthTokenExpirationLifetimeDays = \Aurora\Api::GetSettings()->AuthTokenExpirationLifetimeDays;
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

                    $oUser->LastLogin = date('Y-m-d H:i:s');
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

    public function GetAccountUsedToAuthorize($Login)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $mResult = null;

        $aArgs = array(
            'Login' => $Login
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
     * Creates channel with specified login and description.
     *
     * @param string $Login New channel login.
     * @param string $Description New channel description.
     * @return int New channel identifier.
     * @throws ApiException
     */
    public function CreateChannel($Login, $Description = '')
    {
        $mResult = -1;
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        $mResult = false;

        $Login = \trim($Login);
        if ($Login !== '') {
            $oChannel = new Models\Channel();

            $oChannel->Login = $Login;

            if ($Description !== '') {
                $oChannel->Description = $Description;
            }

            if ($this->getChannelsManager()->createChannel($oChannel)) {
                $mResult = $oChannel->Id;
            }
        } else {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        return $mResult;
    }

    /**
     * Updates channel.
     *
     * @param int $ChannelId Channel identifier.
     * @param string $Login New login for channel.
     * @param string $Description New description for channel.
     * @return bool
     * @throws ApiException
     */
    public function UpdateChannel($ChannelId, $Login = '', $Description = '')
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        if ($ChannelId > 0) {
            $oChannel = $this->getChannelsManager()->getChannelById($ChannelId);

            if ($oChannel) {
                $Login = \trim($Login);
                if (!empty($Login)) {
                    $oChannel->Login = $Login;
                }
                if (!empty($Description)) {
                    $oChannel->Description = $Description;
                }

                return $this->getChannelsManager()->updateChannel($oChannel);
            }
        } else {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        return false;
    }

    /**
     * Deletes channel.
     *
     * @param int $ChannelId Identifier of channel to delete.
     * @return bool
     * @throws ApiException
     */
    public function DeleteChannel($ChannelId)
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        if ($ChannelId > 0) {
            $oChannel = $this->getChannelsManager()->getChannelById($ChannelId);

            if ($oChannel) {
                return $this->getChannelsManager()->deleteChannel($oChannel);
            }
        } else {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        return false;
    }

    /**
     * @api {post} ?/Api/ GetTenants
     * @apiName GetTenants
     * @apiGroup Core
     * @apiDescription Obtains tenant list if super administrator is authenticated.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=GetTenants} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **Offset** *int* Offset of tenant list.<br>
     * &emsp; **Limit** *int* Limit of result tenant list.<br>
     * &emsp; **Search** *string* Search string.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'GetTenants'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {mixed} Result.Result Object with array of tenants and their count in case of success, otherwise **false**.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Core',
     *	Method: 'GetTenants',
     *	Result: {
     *				Items: [
     *					{ Id: 123, Name: 'Default', SiteName: '' }
     *				],
     *				Count: 1
     *			}
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Core',
     *	Method: 'GetTenants',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */
    /**
     * Obtains tenant list if super administrator is authenticated.
     * @param int $Offset Offset of the list.
     * @param int $Limit Limit of the list.
     * @param string $Search Search string.
     * @return array {
     *		*array* **Items** Tenant list
     *		*int* **Count** Tenant count
     * }
     * @throws ApiException
     */
    public function GetTenants($Offset = 0, $Limit = 0, $Search = '')
    {
        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        $oAuthenticatedUser = Api::getAuthenticatedUser();
        $bSuperadmin = $oAuthenticatedUser->Role === UserRole::SuperAdmin;

        $aTenantsFromDb = $this->getTenantsManager()->getTenantList($Offset, $Limit, $Search);
        $oSettings = $this->oModuleSettings;
        $aTenants = [];

        foreach ($aTenantsFromDb as $oTenant) {
            if ($bSuperadmin || $oTenant->Id === $oAuthenticatedUser->IdTenant) {
                $aTenants[] = [
                    'Id' => $oTenant->Id,
                    'Name' => $oTenant->Name,
                    'SiteName' => $oSettings->GetTenantValue($oTenant->Name, 'SiteName', '')
                ];
            }
        }

        $iTenantsCount = $Limit > 0 ? $this->getTenantsManager()->getTenantsCount($Search) : count($aTenants);
        return array(
            'Items' => $aTenants,
            'Count' => $iTenantsCount,
        );
    }

    /**
     * @api {post} ?/Api/ GetTenant
     * @apiName GetTenant
     * @apiGroup Core
     * @apiDescription Returns tenant.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=GetTenant} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **Id** *int* Tenant identifier.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'GetTenant',
     *	Parameters: '{ Id: 123 }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {mixed} Result.Result Object in case of success, otherwise **false**.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Core',
     *	Method: 'GetTenant',
     *	Result: { Description: '', Name: 'Default', SiteName: '', WebDomain: '' }
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Core',
     *	Method: 'GetTenant',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */
    /**
     * Returns tenant object by identifier.
     *
     * @param int $Id Tenant identifier.
     * @return Models\Tenant|null
     */
    public function GetTenant($Id)
    {
        $oAuthenticatedUser = Api::getAuthenticatedUser();
        if (($oAuthenticatedUser instanceof User) && $oAuthenticatedUser->IdTenant === $Id) {
            Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
        } else {
            Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
        }

        return \Aurora\Api::getTenantById($Id);
    }

    /**
     * @api {post} ?/Api/ CreateTenant
     * @apiName CreateTenant
     * @apiGroup Core
     * @apiDescription Creates tenant.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=CreateTenant} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **ChannelId** *int* Identifier of channel new tenant belongs to.<br>
     * &emsp; **Name** *string* New tenant name.<br>
     * &emsp; **Description** *string* New tenant description.<br>
     * &emsp; **WebDomain** *string* New tenant web domain.<br>
     * &emsp; **SiteName** *string* New tenant site name.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'CreateTenant',
     *	Parameters: '{ ChannelId: 123, Name: "name_value", Description: "description_value" }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {bool} Result.Result Indicates if tenant was created successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
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
     * @param int $ChannelId Identifier of channel new tenant belongs to.
     * @param string $Name New tenant name.
     * @param string $Description New tenant description.
     * @param string $WebDomain New tenant web domain.
     * @param string $SiteName Tenant site name.
     * @return bool
     * @throws ApiException
     */
    public function CreateTenant($ChannelId = 0, $Name = '', $Description = '', $WebDomain = '', $SiteName = null)
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        $oSettings = &Api::GetSettings();
        if (/*!$oSettings->EnableMultiChannel && */$ChannelId === 0) { // TODO: temporary ignore 'EnableMultiChannel' config
            $aChannels = $this->getChannelsManager()->getChannelList(0, 1);
            $ChannelId = count($aChannels) === 1 ? $aChannels[0]->Id : 0;
        }
        $Name = \trim(\Aurora\System\Utils::getSanitizedFilename($Name));

        if ($Name !== '' && $ChannelId > 0) {
            $iTenantsCount = $this->getTenantsManager()->getTenantsByChannelIdCount($ChannelId);
            if ($oSettings->EnableMultiTenant || $iTenantsCount === 0) {
                $oTenant = new Models\Tenant();

                $oTenant->Name = $Name;
                $oTenant->Description = $Description;
                $oTenant->WebDomain = $WebDomain;
                $oTenant->IdChannel = $ChannelId;

                if ($this->getTenantsManager()->createTenant($oTenant)) {
                    if ($SiteName !== null) {
                        $oSettings = $this->oModuleSettings;
                        $oSettings->SaveTenantSettings($oTenant->Name, [
                            'SiteName' => $SiteName
                        ]);
                    }
                    return $oTenant->Id;
                }
            }
        } else {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        return false;
    }

    /**
     * @api {post} ?/Api/ UpdateTenant
     * @apiName UpdateTenant
     * @apiGroup Core
     * @apiDescription Updates tenant.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=UpdateTenant} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **TenantId** *int* Identifier of tenant to update.<br>
     * &emsp; **Description** *string* Tenant description.<br>
     * &emsp; **WebDomain** *string* Tenant web domain.<br>
     * &emsp; **SiteName** *string* Tenant site name.<br>
     * &emsp; **ChannelId** *int* Identifier of the new tenant channel.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'UpdateTenant',
     *	Parameters: '{ TenantId: 123, Description: "description_value", ChannelId: 123 }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {bool} Result.Result Indicates if tenant was updated successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
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
     * @param int $TenantId Identifier of tenant to update.
     * @param string $Description Tenant description.
     * @param string $WebDomain Tenant web domain.
     * @param string $SiteName Tenant site name.
     * @param int $ChannelId Identifier of the tenant channel.
     * @return bool
     * @throws ApiException
     */
    public function UpdateTenant($TenantId, $Description = null, $WebDomain = null, $SiteName = null, $ChannelId = 0)
    {
        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
        $oAuthenticatedUser = Api::getAuthenticatedUser();
        if ($oAuthenticatedUser->Role === UserRole::TenantAdmin && $oAuthenticatedUser->IdTenant !== $TenantId) {
            throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
        } else {
            Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
        }

        if (!empty($TenantId)) {
            $oTenant = $this->getTenantsManager()->getTenantById($TenantId);
            if ($oTenant) {
                if ($SiteName !== null) {
                    $oSettings = $this->oModuleSettings;
                    $oSettings->SaveTenantSettings($oTenant->Name, [
                        'SiteName' => $SiteName
                    ]);
                }
                if ($Description !== null) {
                    $oTenant->Description = $Description;
                }
                if ($WebDomain !== null && $oAuthenticatedUser->Role === UserRole::SuperAdmin) {
                    $oTenant->WebDomain = $WebDomain;
                }
                if (!empty($ChannelId) && $oAuthenticatedUser->Role === UserRole::SuperAdmin) {
                    $oTenant->IdChannel = $ChannelId;
                }

                return $this->getTenantsManager()->updateTenant($oTenant);
            }
        } else {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        return false;
    }

    /**
     * @api {post} ?/Api/ DeleteTenants
     * @apiName DeleteTenants
     * @apiGroup Core
     * @apiDescription Deletes tenants specified by a list of identifiers.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=DeleteTenants} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **IdList** *array* List of tenants identifiers.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'DeleteTenants',
     *	Parameters: '{ IdList: [123, 456] }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {bool} Result.Result Indicates if tenants were deleted successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Core',
     *	Method: 'DeleteTenants',
     *	Result: true
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Core',
     *	Method: 'DeleteTenants',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */
    /**
     * Deletes tenants specified by a list of identifiers.
     * @param array $IdList List of tenants identifiers.
     * @return bool
     */
    public function DeleteTenants($IdList)
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        $bResult = true;

        foreach ($IdList as $sId) {
            $bResult = $bResult && self::Decorator()->DeleteTenant($sId);
        }

        return $bResult;
    }

    /**
     * @api {post} ?/Api/ DeleteTenant
     * @apiName DeleteTenant
     * @apiGroup Core
     * @apiDescription Deletes tenant.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=DeleteTenant} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **TenantId** *int* Identifier of tenant to delete.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'DeleteTenant',
     *	Parameters: '{ TenantId: 123 }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {bool} Result.Result Indicates if tenant was deleted successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
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
     * @param int $TenantId Identifier of tenant to delete.
     * @return bool
     * @throws ApiException
     */
    public function DeleteTenant($TenantId)
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        if (!empty($TenantId)) {
            $oTenant = $this->getTenantsManager()->getTenantById($TenantId);

            if ($oTenant) {
                // Delete tenant config files.
                $sTenantSpacePath = Api::GetModuleManager()->GetModulesSettingsPath() . 'tenants/' . $oTenant->Name;
                if (@is_dir($sTenantSpacePath)) {
                    $this->deleteTree($sTenantSpacePath);
                }

                // Delete group
                Group::where('TenantId', $oTenant->Id)->delete();

                // Delete users
                $userIds = User::where('IdTenant', $oTenant->Id)->select('Id')->pluck('Id')->toArray();
                self::Decorator()->DeleteUsers($userIds);

                // Delete tenant itself.
                return $this->getTenantsManager()->deleteTenant($oTenant);
            }
        } else {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        return false;
    }

    /**
     * @api {post} ?/Api/ GetUsers
     * @apiName GetUsers
     * @apiGroup Core
     * @apiDescription Returns user list.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=GetUsers} Method Method name.
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
     *	Method: 'GetUsers',
     *	Parameters: '{ Offset: 0, Limit: 0, OrderBy: "", OrderType: 0, Search: 0 }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {mixed} Result.Result List of users in case of success, otherwise **false**.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Core',
     *	Method: 'GetUsers',
     *	Result: {
     *				Items: [
     *					{ Id: 123, PublicId: 'user123_PublicId' },
     *					{ Id: 124, PublicId: 'user124_PublicId' }
     *				],
     *				Count: 2
     *			}
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Core',
     *	Method: 'GetUsers',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */
    /**
     * Returns user list.
     *
     * @param int $TenantId Tenant identifier.
     * @param int $Offset Offset of user list.
     * @param int $Limit Limit of result user list.
     * @param string $OrderBy Name of field order by.
     * @param int $OrderType Order type.
     * @param string $Search Search string.
     * @param array $Filters Filters.
     * @return array {
     *		*array* **Items** User list.
     *		*int* **Count** Users count.
     * }
     */
    public function GetUsers($TenantId = 0, $Offset = 0, $Limit = 0, $OrderBy = 'PublicId', $OrderType = \Aurora\System\Enums\SortOrder::ASC, $Search = '', $Filters = null, $GroupId = -1)
    {
        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        $oAuthenticatedUser = Api::getAuthenticatedUser();
        if ($oAuthenticatedUser->Role === UserRole::TenantAdmin) {
            if ($oAuthenticatedUser->IdTenant !== $TenantId) {
                throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
            }
        } else {
            Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
        }

        $aResult = [
            'Items' => [],
            'Count' => 0,
        ];

        $Filters = ($Filters instanceof Builder) ? $Filters : Models\User::query();
        if ($TenantId !== 0) {
            $Filters = $Filters->where('IdTenant', $TenantId);
        }

        $aResult['Count'] = $this->getUsersManager()->getUsersCount($Search, $Filters, $GroupId);
        $aUsers = $this->getUsersManager()->getUserList($Offset, $Limit, $OrderBy, $OrderType, $Search, $Filters, $GroupId);
        foreach ($aUsers as $oUser) {
            $aGroups = [];
            if ($this->oModuleSettings->AllowGroups) {
                foreach ($oUser->Groups as $oGroup) {
                    if (!$oGroup->IsAll) {
                        $aGroups[] = [
                            'Id' => $oGroup->Id,
                            'TenantId' => $oGroup->TenantId,
                            'Name' => $oGroup->Name
                        ];
                    }
                }
            }
            $aResult['Items'][] = [
                'Id' => $oUser->Id,
                'UUID' => $oUser->UUID,
                'Name' => $oUser->Name,
                'PublicId' => $oUser->PublicId,
                'Role' => $oUser->Role,
                'IsDisabled' => $oUser->IsDisabled,
                'Groups' => $aGroups,
            ];
        }

        return $aResult;
    }

    /**
     * Getting the total number of users
     */
    public function GetTotalUsersCount($TenantId = 0)
    {
        $count = 0;
        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
        $oUser = Api::getAuthenticatedUser();
        if ($oUser) {
            if ($oUser->isAdmin()) {
                $count = $this->getUsersManager()->getTotalUsersCount();
            } else {
                $count = $this->getUsersManager()->getUsersCountForTenant($oUser->IdTenant);
            }
        } elseif ($TenantId > 0) {
            $count = $this->getUsersManager()->getUsersCountForTenant($TenantId);
        }
        return $count;
    }

    /**
     * @api {post} ?/Api/ GetUser
     * @apiName GetUser
     * @apiGroup Core
     * @apiDescription Returns user data.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=GetUser} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **UserId** *string* User identifier.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'GetUser',
     *	Parameters: '{ "Id": 17 }'
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
     *	Method: 'GetUser',
     *	Result: {
     *		'Name': '',
     *		'PublicId': 'mail@domain.com',
     *		'Role': 2,
     *		'WriteSeparateLog': false
     *	}
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Core',
     *	Method: 'GetUser',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */
    /**
     * Returns user object.
     *
     * @param int|string $Id User identifier or UUID.
     * @return Models\User
     */
    public function GetUser($Id = '')
    {
        $oUser = $this->getUsersManager()->getUser($Id);
        $oAuthenticatedUser = Api::getAuthenticatedUser();

        if ($oUser) { // User may be needed for anonymous on reset password or register screens. It can be obtained after using skipCheckUserRole method.
            if (($oAuthenticatedUser instanceof User) && $oAuthenticatedUser->Role === UserRole::NormalUser && $oAuthenticatedUser->Id === $oUser->Id) {
                Api::checkUserRoleIsAtLeast(UserRole::NormalUser);
            } elseif (($oAuthenticatedUser instanceof User) && $oAuthenticatedUser->Role === UserRole::TenantAdmin && $oAuthenticatedUser->IdTenant === $oUser->IdTenant) {
                Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
            } else {
                Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
            }

            return $oUser;
        }

        return null;
    }

    /**
     *
     */
    public function TurnOffSeparateLogs()
    {
        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        $Filters = Models\User::query();
        $oAuthenticatedUser = Api::getAuthenticatedUser();
        if ($oAuthenticatedUser->Role === UserRole::TenantAdmin) {
            $Filters = $Filters->where('IdTenant', $oAuthenticatedUser->IdTenant);
        }

        $aResults = $this->getUsersManager()->getUserList(0, 0, 'PublicId', \Aurora\System\Enums\SortOrder::ASC, '', $Filters->where('WriteSeparateLog', true));
        foreach ($aResults as $aUser) {
            $oUser = self::Decorator()->GetUser($aUser['EntityId']);
            if ($oUser) {
                $oUser->WriteSeparateLog = false;
                $this->UpdateUserObject($oUser);
            }
        }

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
    public function GetUsersWithSeparateLog()
    {
        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        $Filters = Models\User::query();
        $oAuthenticatedUser = Api::getAuthenticatedUser();
        if ($oAuthenticatedUser->Role === UserRole::TenantAdmin) {
            $Filters = $Filters->where('IdTenant', $oAuthenticatedUser->IdTenant);
        }

        $aResults = $this->getUsersManager()->getUserList(0, 0, 'PublicId', \Aurora\System\Enums\SortOrder::ASC, '', $Filters->where('WriteSeparateLog', true));
        $aUsers = [];
        foreach ($aResults as $aUser) {
            $aUsers[] = $aUser['PublicId'];
        }
        return $aUsers;
    }

    /**
     * @api {post} ?/Api/ CreateUser
     * @apiName CreateUser
     * @apiGroup Core
     * @apiDescription Creates user.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=CreateUser} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **TenantId** *int* Identifier of tenant that will contain new user.<br>
     * &emsp; **PublicId** *string* New user name.<br>
     * &emsp; **Role** *int* New user role.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'CreateUser',
     *	Parameters: '{ TenantId: 123, PublicId: "PublicId_value", Role: 2 }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {mixed} Result.Result User identifier in case of success, otherwise **false**.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
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
     * @param int $TenantId Identifier of tenant that will contain new user.
     * @param string $PublicId New user name.
     * @param int $Role New user role.
     * @param bool $WriteSeparateLog Indicates if log file should be written separate for this user.
     * @return int|false
     * @throws ApiException
     */
    public function CreateUser($TenantId = 0, $PublicId = '', $Role = UserRole::NormalUser, $WriteSeparateLog = false, $IsDisabled = false, $Note = null)
    {
        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        if (!UserRole::validateValue($Role)) {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        $oTenant = null;

        // if $TenantId === 0  we need to get default tenant
        if ($TenantId === 0) {
            Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
            $oTenant = $this->getTenantsManager()->getDefaultGlobalTenant();
            $TenantId = $oTenant ? $oTenant->Id : null;
        }

        $oAuthenticatedUser = Api::getAuthenticatedUser();
        if (!($oAuthenticatedUser instanceof User && $oAuthenticatedUser->Role === UserRole::TenantAdmin && $oAuthenticatedUser->IdTenant === $TenantId)) {
            Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
        }

        if (!$oTenant) {
            $oTenant = $this->getTenantsManager()->getTenantById($TenantId);
            if (!$oTenant) {
                throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
            }
        }

        $PublicId = \trim($PublicId);
        if (substr_count($PublicId, '@') > 1) {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        if (!empty($TenantId) && !empty($PublicId)) {
            $oUser = $this->getUsersManager()->getUserByPublicId($PublicId);
            if ($oUser instanceof Models\User) {
                throw new ApiException(Notifications::UserAlreadyExists, null, 'UserAlreadyExists');
            } else {
                if (class_exists('\Aurora\Modules\Licensing\Module')) {
                    $oLicense = \Aurora\Modules\Licensing\Module::Decorator();
                    if (!$oLicense->ValidateUsersCount($this->GetTotalUsersCount($TenantId)) || !$oLicense->ValidatePeriod()) {
                        Api::Log("Error: License limit");
                        throw new ApiException(Notifications::LicenseLimit, null, 'LicenseLimit');
                    }
                }
            }

            $oUser = new Models\User();

            $oUser->PublicId = $PublicId;
            $oUser->IdTenant = $TenantId;
            $oUser->Role = $Role;
            $oUser->WriteSeparateLog = $WriteSeparateLog;

            $oUser->Language = Api::GetLanguage(true);
            $oUser->TimeFormat = $this->oModuleSettings->TimeFormat;
            $oUser->DateFormat = $this->oModuleSettings->DateFormat;
            $oUser->DefaultTimeZone = '';


            $oUser->IsDisabled = $IsDisabled;
            $oUser->Note = $Note;

            if ($this->getUsersManager()->createUser($oUser)) {
                return $oUser->Id;
            }
        } else {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        return false;
    }

    /**
     * @api {post} ?/Api/ UpdateUser
     * @apiName UpdateUser
     * @apiGroup Core
     * @apiDescription Updates user.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=UpdateUser} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **UserId** *int* Identifier of user to update.<br>
     * &emsp; **UserName** *string* New user name.<br>
     * &emsp; **TenantId** *int* Identifier of tenant that will contain the user.<br>
     * &emsp; **Role** *int* New user role.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'UpdateUser',
     *	Parameters: '{ UserId: 123, UserName: "name_value", TenantId: 123, Role: 2 }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {bool} Result.Result Indicates if user was updated successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
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
     * @param int $UserId Identifier of user to update.
     * @param string $PublicId New user name.
     * @param int $TenantId Identifier of tenant that will contain the user.
     * @param int $Role New user role.
     * @param bool|null $IsDisabled Disbles the user
     * @param bool $WriteSeparateLog New value of indicator if user's logs should be in a separate file.
     * @param array $GroupIds List of system group ids user belogs to.
     * @param string $Note User text note.
     * @return bool
     * @throws ApiException
     */
    public function UpdateUser($UserId, $PublicId = '', $TenantId = 0, $Role = -1, $IsDisabled = null, $WriteSeparateLog = null, $GroupIds = null, $Note = null)
    {
        $PublicId = \trim($PublicId);

        $oUser = null;
        if ($UserId > 0) {
            $oUser = \Aurora\Api::getUserById($UserId);
        }
        if ($oUser) {
            if ((!empty($TenantId) && $oUser->IdTenant != $TenantId) || (!empty($PublicId) && $oUser->PublicId != $PublicId)) {
                // Only super administrator can edit users TenantId and PublicId
                Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
            } elseif ($Role !== -1 || $IsDisabled !== null || $WriteSeparateLog !== null || $GroupIds !== null || $Note !== null) {
                Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
            } elseif ($UserId === Api::getAuthenticatedUserId()) {
                Api::checkUserRoleIsAtLeast(UserRole::NormalUser);
            } else {
                Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
            }

            Api::checkUserAccess($oUser);

            if (!empty($PublicId)) {
                $oUser->PublicId = $PublicId;
            }
            if (!empty($TenantId)) {
                $oUser->IdTenant = $TenantId;
            }
            if (UserRole::validateValue($Role)) {
                $oUser->Role = $Role;
            }
            if ($IsDisabled !== null) {
                $oUser->IsDisabled = (bool) $IsDisabled;
            }
            if ($WriteSeparateLog !== null) {
                $oUser->WriteSeparateLog = $WriteSeparateLog;
            }
            if ($Note !== null) {
                $oUser->Note = (string) $Note;
            }

            $mResult = $this->getUsersManager()->updateUser($oUser);
            if ($mResult && $this->oModuleSettings->AllowGroups && $GroupIds !== null) {
                self::Decorator()->UpdateUserGroups($UserId, $GroupIds);
            }

            return $mResult;
        } else {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }
    }

    /**
     * @api {post} ?/Api/ DeleteUsers
     * @apiName DeleteUsers
     * @apiGroup Core
     * @apiDescription Deletes users specified by a list of identifiers.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=DeleteUsers} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **IdList** *int* List of users identifiers.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'DeleteUsers',
     *	Parameters: '{ IdList: [125, 457] }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {bool} Result.Result Indicates if users were deleted successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Core',
     *	Method: 'DeleteUsers',
     *	Result: true
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Core',
     *	Method: 'DeleteUsers',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */
    /**
     * Deletes users specified by a list of identifiers.
     * @param array $IdList List of users identifiers.
     * @return bool
     */
    public function DeleteUsers($IdList)
    {
        $bResult = true;

        foreach ($IdList as $sId) {
            $bResult = $bResult && self::Decorator()->DeleteUser($sId);
        }

        return $bResult;
    }

    /**
     * @api {post} ?/Api/ DeleteUser
     * @apiName DeleteUser
     * @apiGroup Core
     * @apiDescription Deletes user.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Core} Module Module name.
     * @apiParam {string=DeleteUser} Method Method name.
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **UserId** *int* User identifier.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Core',
     *	Method: 'DeleteUser',
     *	Parameters: '{ UserId: 123 }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {bool} Result.Result Indicates if user was deleted successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code.
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
     * @param int $UserId User identifier.
     * @return bool
     * @throws ApiException
     */
    public function DeleteUser($UserId = 0)
    {
        $oAuthenticatedUser = Api::getAuthenticatedUser();

        $oUser = \Aurora\Api::getUserById($UserId);

        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        if ($oUser instanceof Models\User && $oAuthenticatedUser->Role === UserRole::TenantAdmin &&
            $oUser->IdTenant !== $oAuthenticatedUser->IdTenant) {
            throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
        } else {
            if ($oUser->IdTenant === $oAuthenticatedUser->IdTenant) {
                Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
            } else {
                Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
            }
        }

        $bResult = false;

        if (!empty($UserId) && is_int($UserId)) {
            $bResult = $this->getUsersManager()->deleteUserById($UserId);
            if ($bResult) {
                UserBlock::where('UserId', $UserId)->delete();
            }
        } else {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        return $bResult;
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
     * Updates user Timezone.
     *
     * @param string $Timezone New Timezone.
     *
     */
    public function UpdateUserTimezone($Timezone)
    {
        Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $oUser = Api::getAuthenticatedUser();

        if ($oUser && $Timezone) {
            if ($oUser && $oUser->DefaultTimeZone !== $Timezone) {
                $oUser->DefaultTimeZone = $Timezone;
                $this->UpdateUserObject($oUser);
            }
        } else {
            return false;
        }
        return true;
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
    public function GetUserSessions()
    {
        $aResult = [];
        if (\Aurora\Api::GetSettings()->StoreAuthTokenInDB) {
            $oUser = Api::getAuthenticatedUser();
            if ($oUser) {
                $aUserSessions = Api::UserSession()->GetUserSessionsFromDB($oUser->Id);
                foreach ($aUserSessions as $oUserSession) {
                    $aTokenInfo = Api::DecodeKeyValues($oUserSession->Token);

                    if ($aTokenInfo !== false && isset($aTokenInfo['id'])) {
                        $aResult[] = [
                            'LastUsageDateTime' => $oUserSession->LastUsageDateTime,
                            'ExpireDateTime' => (int) isset($aTokenInfo['@expire']) ? $aTokenInfo['@expire'] : 0,
                        ];
                    }
                }
            }
        }
        return $aResult;
    }

    /**
     *
     */
    public function CreateGroup($TenantId, $Name)
    {
        if (!$this->oModuleSettings->AllowGroups) {
            throw new ApiException(Notifications::MethodAccessDenied, null, 'MethodAccessDenied');
        }

        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        $oUser = Api::getAuthenticatedUser();
        if ($oUser->Role === UserRole::TenantAdmin && $oUser->IdTenant !== $TenantId) {
            throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
        }

        $oGroup = Group::firstWhere([
            'TenantId' => $TenantId,
            'Name' => $Name
        ]);

        if ($oGroup) {
            throw new \Aurora\Modules\Core\Exceptions\Exception(Enums\ErrorCodes::GroupAlreadyExists);
        } else {
            $oGroup = new Models\Group();
            $oGroup->Name = $Name;
            $oGroup->TenantId = $TenantId;
            if ($oGroup->save()) {
                return $oGroup->Id;
            } else {
                return false;
            }
        }
    }

    /**
     * Returns a user group
     * @param int $GroupId
     *
     * @return Group|false
     */
    public function GetGroup($GroupId)
    {
        if (!$this->oModuleSettings->AllowGroups) {
            return false;
        }

        $mResult = false;

        Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $oUser = Api::getAuthenticatedUser();
        $oGroup = Group::firstWhere([ 'Id' => $GroupId ]);
        if ($oUser && $oGroup && ($oUser->Role === UserRole::TenantAdmin || $oUser->Role === UserRole::NormalUser) && $oUser->IdTenant !== $oGroup->TenantId) {
            throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
        }

        $mResult = $oGroup;

        return $mResult;
    }

    /**
     *
     */
    public function GetAllGroup($TenantId)
    {
        if (!$this->oModuleSettings->AllowGroups) {
            return false;
        }

        $mResult = false;

        Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $oUser = Api::getAuthenticatedUser();
        if ($oUser && ($oUser->Role === UserRole::TenantAdmin || $oUser->Role === UserRole::NormalUser)  && $oUser->IdTenant !== $TenantId) {
            throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
        }

        $oGroup = Group::firstWhere([
            'TenantId' => $TenantId,
            'IsAll' => true
        ]);

        if (!$oGroup) {
            $oGroup = new Models\Group();
            $oGroup->Name = 'All';
            $oGroup->TenantId = $TenantId;
            $oGroup->IsAll = true;

            if ($oGroup->save()) {
                $mResult = $oGroup;
            } else {
                $mResult = false;
            }
        } else {
            $mResult = $oGroup;
        }

        return $mResult;
    }

    /**
     *
     */
    public function GetGroups($TenantId, $Search = '')
    {
        if (!$this->oModuleSettings->AllowGroups) {
            return [
                'Items' => [],
                'Count' => 0
            ];
        }

        Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $oUser = Api::getAuthenticatedUser();
        if ($oUser && ($oUser->Role === UserRole::TenantAdmin || $oUser->Role === UserRole::NormalUser)  && $oUser->IdTenant !== $TenantId) {
            throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
        }

        $query = Group::where('TenantId', $TenantId);
        if (!empty($Search)) {
            $query = $query->where(function ($q) use ($Search) {
                $q->where('Name', 'LIKE', '%' . $Search . '%');
                $q->orWhere('IsAll', true);
            });
        }

        $aGroups = $query->get()->map(function ($oGroup) use ($oUser) {

            $aArgs = [
                'User' => $oUser,
                'Group' => $oGroup
            ];
            $mResult = false;

            try {
                $this->broadcastEvent('GetGroupContactsEmails', $aArgs, $mResult);
            } catch (\Exception $oException) {
            }

            $aEmails = [];
            if (is_array($mResult)) {
                $aEmails = $mResult;
            }

            return [
                'Id' => $oGroup->Id,
                'Name' => $oGroup->getName(),
                'Emails' => implode(', ', $aEmails),
                'IsAll' => !!$oGroup->IsAll
            ];
        })->toArray();

        if (!empty($Search)) {
            $aGroups = array_filter($aGroups, function ($aGroup) use ($Search) {
                return (stripos($aGroup['Name'], $Search) !== false);
            });
        }

        return [
            'Items' => $aGroups,
            'Count' => count($aGroups)
        ];
    }

    /**
     *
    */
    public function UpdateGroup($GroupId, $Name)
    {
        if (!$this->oModuleSettings->AllowGroups) {
            throw new ApiException(Notifications::MethodAccessDenied, null, 'MethodAccessDenied');
        }

        $mResult = false;

        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        $oGroup = Group::find($GroupId);
        if ($oGroup && !$oGroup->IsAll) {
            $oUser = Api::getAuthenticatedUser();
            if ($oUser && $oUser->Role === UserRole::TenantAdmin && $oGroup->TenantId !== $oUser->IdTenant) {
                throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
            }

            if ($oGroup->Name !== $Name && Group::where(['TenantId' => $oGroup->TenantId, 'Name' => $Name])->count() > 0) {
                throw new ApiException(ErrorCodes::GroupAlreadyExists, null, 'GroupAlreadyExists');
            } else {
                $oGroup->Name = $Name;
                $mResult = !!$oGroup->save();
            }
        }

        return $mResult;
    }

    /**
     * Deletes groups specified by a list of identifiers.
     * @param array $IdList List of groups identifiers.
     * @return bool
     */
    public function DeleteGroups($IdList)
    {
        if (!$this->oModuleSettings->AllowGroups) {
            throw new ApiException(Notifications::MethodAccessDenied, null, 'MethodAccessDenied');
        }

        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        $bResult = true;

        foreach ($IdList as $iId) {
            $bResult = $bResult && self::Decorator()->DeleteGroup($iId);
        }

        return $bResult;
    }

    /**
     *
     */
    public function DeleteGroup($GroupId)
    {
        if (!$this->oModuleSettings->AllowGroups) {
            throw new ApiException(Notifications::MethodAccessDenied, null, 'MethodAccessDenied');
        }

        $mResult = false;

        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        $oGroup = Group::find($GroupId);
        if ($oGroup && !$oGroup->IsAll) {
            $oUser = Api::getAuthenticatedUser();
            if ($oUser && $oUser->Role === UserRole::TenantAdmin && $oGroup->TenantId !== $oUser->IdTenant) {
                throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
            }

            $mResult = $oGroup->delete();
        }

        return $mResult;
    }

    /**
     *
     */
    public function GetGroupUsers($TenantId, $GroupId)
    {
        if (!$this->oModuleSettings->AllowGroups) {
            throw new ApiException(Notifications::MethodAccessDenied, null, 'MethodAccessDenied');
        }

        $mResult = [];

        Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $oGroup = Group::where('TenantId', $TenantId)->where('Id', $GroupId)->first();
        if ($oGroup) {
            $oUser = Api::getAuthenticatedUser();
            if ($oUser && ($oUser->Role === UserRole::NormalUser || $oUser->Role === UserRole::TenantAdmin) && $oGroup->TenantId !== $oUser->IdTenant) {
                throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
            }

            if ($oGroup->IsAll) {
                $teamContacts = ContactsModule::Decorator()->GetContacts($oUser->Id, StorageType::Team, 0, 0);
                if (isset($teamContacts['List'])) {
                    $mResult = array_map(function ($item) {
                        return [
                            'UserId' => $item['UserId'],
                            'Name' => $item['FullName'],
                            'PublicId' => $item['ViewEmail']
                        ];
                    }, $teamContacts['List']);
                }
            } else {
                $mResult = $oGroup->Users()->get()->map(function ($oUser) {
                    return [
                        'UserId' => $oUser->Id,
                        'Name' => $oUser->Name,
                        'PublicId' => $oUser->PublicId
                    ];
                })->toArray();
            }
        }

        return $mResult;
    }

    /**
     *
     */
    public function AddUsersToGroup($GroupId, $UserIds)
    {
        if (!$this->oModuleSettings->AllowGroups) {
            throw new ApiException(Notifications::MethodAccessDenied, null, 'MethodAccessDenied');
        }

        $mResult = false;

        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        $oGroup = Group::find($GroupId);
        if ($oGroup && !$oGroup->IsAll) {
            $oUser = Api::getAuthenticatedUser();
            if ($oUser && $oUser->Role === UserRole::TenantAdmin && $oGroup->TenantId !== $oUser->IdTenant) {
                throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
            }

            $oGroup->Users()->syncWithoutDetaching($UserIds);
            $mResult = true;
        }

        return $mResult;
    }

    /**
     *
     */
    public function RemoveUsersFromGroup($GroupId, $UserIds)
    {
        if (!$this->oModuleSettings->AllowGroups) {
            throw new ApiException(Notifications::MethodAccessDenied, null, 'MethodAccessDenied');
        }

        $mResult = false;

        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        $oGroup = Group::find($GroupId);
        if ($oGroup) {
            $oUser = Api::getAuthenticatedUser();
            if ($oUser && $oUser->Role === UserRole::TenantAdmin && $oGroup->TenantId !== $oUser->IdTenant) {
                throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
            }

            $oGroup->Users()->detach($UserIds);
            $mResult = true;
        }

        return $mResult;
    }

    /**
     *
     */
    public function UpdateUserGroups($UserId, $GroupIds)
    {
        if (!$this->oModuleSettings->AllowGroups) {
            throw new ApiException(Notifications::MethodAccessDenied, null, 'MethodAccessDenied');
        }

        $mResult = false;

        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
        $oAuthUser = Api::getAuthenticatedUser();
        $oUser = User::find($UserId);

        if ($oAuthUser && $oAuthUser->Role === UserRole::TenantAdmin && $oAuthUser->IdTenant !== $oUser->IdTenant) {
            throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
        }
        if ($oUser) {
            $aGroupIds = Group::where('IsAll', false)->whereIn('Id', $GroupIds)->get(['Id'])->map(function ($oGroup) {
                return $oGroup->Id;
            });
            $oUser->Groups()->sync($aGroupIds);
            $mResult = true;
        }

        return $mResult;
    }
    /***** public functions might be called with web API *****/
}
