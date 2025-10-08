<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core;

use Aurora\Api;
use Aurora\System\Application;
use Aurora\System\Enums\LogLevel;
use Aurora\System\Exceptions\ApiException;

/**
 * System module that provides core functionality such as User management, Tenants management.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Module $module
 *
 * @package Modules
 */
class Subscriptions extends \Aurora\System\Module\AbstractSubscriptions
{
    public function __construct(Module $module)
    {
        parent::__construct($module);

        $this->callbacks = [
            ['CreateAccount', [$this, 'onCreateAccount'], 100],
            ['Core::GetCompatibilities::after', [$this, 'onAfterGetCompatibilities']],
            ['System::RunEntry::before', [$this, 'onBeforeRunEntry'], 100]
        ];
    }

    public static function GetName()
    {
        return Module::GetName();
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
            $oUser = $this->module->getUsersManager()->getUser($Args['UserId']);
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
                $oUser = $this->module->getUsersManager()->getUserByPublicId($sPublicId);
            }
            if (!isset($oUser)) {
                $bPrevState = Api::skipCheckUserRole(true);
                $iUserId = $this->module->Decorator()->CreateUser(isset($Args['TenantId']) ? (int) $Args['TenantId'] : 0, $sPublicId);
                Api::skipCheckUserRole($bPrevState);
                $oUser = $this->module->getUsersManager()->getUser($iUserId);
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

        $mResult[$this->module->GetName()] = $aCompatibilities;
    }

    public function onBeforeRunEntry($aArgs, &$mResult)
    {
        Api::removeOldLogs();

        return $this->redirectToHttps($aArgs['EntryName'], $mResult);
    }

    public function onBeforeLogin($aArgs, &$mResult)
    {
        // Firebase App Check
        $appCheckToken = $this->module->oHttp->GetHeader('X-Firebase-AppCheck');

        if ($appCheckToken) {
            $aFirebaseAppCheck = $this->module->getConfig('FirebaseAppCheck');

            if (is_array($aFirebaseAppCheck) && count($aFirebaseAppCheck) > 0) {
                $sErrorMessage = 'Invalid App Check token';
                try {
                    $jwksJson = @file_get_contents('https://firebaseappcheck.googleapis.com/v1/jwks');
                    if ($jwksJson === false) { // Unable to fetch JWKS
                        Api::Log('Unable to fetch JWKS from Firebase', LogLevel::Error);
                        throw new ApiException(Enums\ErrorCodes::AppCheckError, null, $sErrorMessage);
                    }
                    $jwks = json_decode($jwksJson, true);
                    if ($jwks === null) { // Invalid JSON
                        Api::Log('Invalid JWKS JSON from Firebase', LogLevel::Error);
                        throw new ApiException(Enums\ErrorCodes::AppCheckError, null, $sErrorMessage);
                    }

                    $decoded = \Firebase\JWT\JWT::decode($appCheckToken, \Firebase\JWT\JWK::parseKeySet($jwks), ['RS256']);
                    $payload = (array) $decoded;

                    $parts = explode('.', $appCheckToken);
                    if (count($parts) !== 3) { // Invalid JWT structure
                        Api::Log('Invalid JWT structure for App Check token', LogLevel::Error);
                        throw new ApiException(Enums\ErrorCodes::AppCheckError, null, $sErrorMessage);
                    }

                    $header = (array) \Firebase\JWT\JWT::jsonDecode(\Firebase\JWT\JWT::urlsafeB64Decode($parts[0]));

                    $valid = false;

                    foreach ($aFirebaseAppCheck as $projectConfig) {
                        $projectNumber = $projectConfig['ProjectNumber'] ?? null;
                        $allowedAppIds = array_filter($projectConfig['AppIds'] ?? []);

                        if (!$projectNumber || empty($allowedAppIds)) {
                            continue;
                        }

                        if (
                            ($header['alg'] ?? '') === 'RS256' &&
                            ($header['typ'] ?? '') === 'JWT' &&
                            ($payload['iss'] ?? '') === "https://firebaseappcheck.googleapis.com/{$projectNumber}" &&
                            ($payload['exp'] ?? 0) > time() &&
                            in_array("projects/{$projectNumber}", (array) ($payload['aud'] ?? []), true) &&
                            !empty($payload['sub']) &&
                            in_array($payload['sub'], $allowedAppIds, true)
                        ) {
                            $valid = true;
                            break;
                        }
                    }
                    if (!$valid) {
                        Api::Log('App Check token validation failed', LogLevel::Error);
                        throw new ApiException(Enums\ErrorCodes::AppCheckError, null, $sErrorMessage);
                    }

                } catch (\Exception $e) {
                    if (!($e instanceof ApiException)) {
                        throw new ApiException(Enums\ErrorCodes::AppCheckError, $e, $sErrorMessage);
                    } else {
                        throw $e;
                    }
                }
                Application::$mobileAppChecked = true;
            }
        }
    }

    protected function redirectToHttps($sEntryName, $mResult)
    {
        $oSettings = &Api::GetSettings();
        if ($oSettings) {
            $bRedirectToHttps = $oSettings->RedirectToHttps;

            $bHttps = Api::isHttps();
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
}
