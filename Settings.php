<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core;

use Aurora\System\SettingsProperty;
use Aurora\System\Enums;

/**
 * @property bool $Disabled
 * @property bool $AllowCapa
 * @property bool $AllowPostLogin
 * @property bool $CsrfTokenProtection
 * @property int $CronTimeToRunSeconds
 * @property int $CronTimeToKillSeconds
 * @property string $CronTimeFile
 * @property bool $UserSelectsDateFormat
 * @property Enums\DateFormat $DateFormat
 * @property array $DateFormatList
 * @property array $LanguageList
 * @property string $LanguageListComment
 * @property string $Language
 * @property bool $AutodetectLanguage
 * @property string $PostLoginErrorRedirectUrl
 * @property Enums\TimeFormat $TimeFormat
 * @property string $SiteName
 * @property string $ProductName
 * @property int $AuthTokenCookieExpireTime
 * @property bool $EnableFailedLoginBlock
 * @property bool $AllowPostLogin
 * @property int $LoginBlockAvailableTriesCount
 * @property bool $LoginBlockDurationMinutes
 * @property bool $AllowGroups
 * @property int $LoginBlockIpReputationThreshold
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            'Disabled' => new SettingsProperty(
                false,
                'bool',
                null,
                'Setting to true disables the module'
            ),
            'AllowCapa' => new SettingsProperty(
                false,
                'bool',
                null,
                'If set to true, product features can be enabled/disabled on user or tenant level'
            ),
            'AllowPostLogin' =>  new SettingsProperty(
                false,
                'bool',
                null,
                'If set to true, credentials can be submitted via POST request'
            ),
            'CsrfTokenProtection' => new SettingsProperty(
                true,
                'bool',
                null,
                'If set to true, CSRF protection is enabled'
            ),
            'CronTimeToRunSeconds' => new SettingsProperty(
                10800,
                'int',
                null,
                'Defines intervals in seconds to run a routine of deleting temporary files'
            ),
            'CronTimeToKillSeconds' => new SettingsProperty(
                10800,
                'int',
                null,
                'Defines minimal age in seconds of temporary files to be deleted'
            ),
            'CronTimeFile' => new SettingsProperty(
                '.clear.dat',
                'string',
                null,
                'Defines filename for storing last timestamp when routine of deleting temporary files was run'
            ),
            'UserSelectsDateFormat' => new SettingsProperty(
                false,
                'bool',
                null,
                'If set to true, users can select date format'
            ),
            'DateFormat' => new SettingsProperty(
                Enums\DateFormat::DDMMYYYY,
                'spec',
                Enums\DateFormat::class,
                'Defines default date format used'
            ),
            'DateFormatList' => new SettingsProperty(
                [Enums\DateFormat::DDMMYYYY, Enums\DateFormat::MMDDYYYY, Enums\DateFormat::DD_MONTH_YYYY],
                'array',
                null,
                'Defines default date format used'
            ),
            'LanguageList' => new SettingsProperty(
                [],
                'array',
                null,
                'Empty array means that every language from every module will be available. [\"English\", \"German\"] means that only English and German languages will be used in the system.'
            ),
            'LanguageListComment' => new SettingsProperty(
                '',
                'string',
                null,
                'Empty array means that every language from every module will be available. [\"English\", \"German\"] means that only English and German languages will be used in the system.'
            ),
            'Language' => new SettingsProperty(
                '',
                'string',
                null,
                'Default interface language used'
            ),
            'AutodetectLanguage' => new SettingsProperty(
                true,
                'bool',
                null,
                'Setting to true enables language autodetection'
            ),
            'PostLoginErrorRedirectUrl' => new SettingsProperty(
                './',
                'string',
                null,
                'If login credentials were supplied with POST method, this setting defines redirect URL used when authentication error occurs'
            ),
            'TimeFormat' => new SettingsProperty(
                Enums\TimeFormat::F24,
                'spec',
                Enums\TimeFormat::class,
                'Denotes time format used by default'
            ),
            'SiteName' => new SettingsProperty(
                '',
                'string',
                null,
                'Text used in browser title as a website name'
            ),
            'ProductName' => new SettingsProperty(
                'Unknown',
                'string',
                null,
                'Product name, displayed in About tab of adminpanel'
            ),
            'AuthTokenCookieExpireTime' => new SettingsProperty(
                30,
                'int',
                null,
                'Expiration time for authentication cookie, in days'
            ),
            'EnableFailedLoginBlock' => new SettingsProperty(
                false,
                'bool',
                null,
                'Setting to true enables feature of blocking user after a number of failed login attempts'
            ),
            'LoginBlockAvailableTriesCount' => new SettingsProperty(
                10,
                'int',
                null,
                'Number of failed login attempts which will result in blocking user'
            ),
            'LoginBlockDurationMinutes' => new SettingsProperty(
                3,
                'int',
                null,
                'Number of minutes user will be blocked for upon multiple failed login attempts'
            ),
            'LoginBlockIpReputationThreshold ' => new SettingsProperty(
                0,
                'int',
                null,
                'The setting determines the number of different users that need to be blocked from a specific IP address before that IP is considered to have a bad reputation and blocks any further login attempts from it.'
            ),
            'AllowGroups' => new SettingsProperty(
                false,
                'bool',
                null,
                'Setting to true enables user groups which can be managed in adminpanel'
            ),
        ];
    }
}
