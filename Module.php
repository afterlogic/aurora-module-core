<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core;

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
    use Traits\Common;
    use Traits\Groups;
    use Traits\Tenants;
    use Traits\Channels;
    use Traits\Users;

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

        $this->denyMethodsCallByWebApi([
            'Authenticate',
            'UpdateUserObject',
            'GetUserByPublicId',
            'GetAdminUser',
            'GetTenantName',
            'GetTenantIdByName',
            'GetDefaultGlobalTenant',
            'UpdateTenantObject',
            'UpdateTokensValidFromTimestamp',
            'GetAccountUsedToAuthorize',
            'GetDigestHash',
            'VerifyPassword',
            'SetAuthDataAndGetAuthToken',
            'IsModuleDisabledForObject',
            'GetBlockedUser',
            'BlockUser',
            'IsBlockedUser',
            'GetAllGroup',
            'CheckIpReputation'
        ]);
    }
}
