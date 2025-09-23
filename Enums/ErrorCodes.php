<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Enums;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 */
class ErrorCodes
{
    public const ChannelDoesNotExist = 1001;
    public const TenantAlreadyExists = 1002;
    public const GroupAlreadyExists = 1003;
    public const MySqlConfigError = 1004;
    public const UserCreateFailed = 1005;
    public const UserDeleteFailed = 1006;
    public const UserAlreadyExists = 1007;
    public const ChannelAlreadyExists = 1008;
    public const ChannelCreateFailed = 1009;
    public const ChannelUpdateFailed = 1010;
    public const UserUpdateFailed = 1011;
    public const AppCheckError = 1012;

    /**
     * @var array
     */
    protected $aConsts = [
        'ChannelDoesNotExist' => self::ChannelDoesNotExist,
        'ChannelAlreadyExists' => self::ChannelAlreadyExists,
        'ChannelCreateFailed' => self::ChannelCreateFailed,
        'ChannelUpdateFailed' => self::ChannelUpdateFailed,
        'TenantAlreadyExists' => self::TenantAlreadyExists,
        'GroupAlreadyExists' => self::GroupAlreadyExists,
        'UserCreateFailed' => self::UserCreateFailed,
        'UserDeleteFailed' => self::UserDeleteFailed,
        'UserUpdateFailed' => self::UserUpdateFailed,
        'AppCheckError' => self::AppCheckError,
        'UserAlreadyExists' => self::UserAlreadyExists,
        'MySqlConfigError' => self::MySqlConfigError,
    ];
}
