<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Traits;

use Aurora\Api;
use Aurora\Modules\Core\Models\User;
use Aurora\Modules\Core\Models\UserBlock;
use Aurora\System\Enums\UserRole;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Managers\Integrator;
use Aurora\System\Notifications;
use Illuminate\Database\Eloquent\Builder;

/**
 * System module that provides core functionality such as User management, Tenants management.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Modules
 */
trait Users
{
    protected $oUsersManager = null;

    public function initUsersTrait()
    {
        $this->denyMethodsCallByWebApi([
            'UpdateUserObject',
            'GetUserByPublicId',
            'GetAdminUser',
            'UpdateTokensValidFromTimestamp',
        ]);
    }

    /**
     * @return \Aurora\Modules\Core\Managers\Users
     */
    public function getUsersManager()
    {
        if ($this->oUsersManager === null) {
            $this->oUsersManager = new \Aurora\Modules\Core\Managers\Users($this);
        }

        return $this->oUsersManager;
    }

    /**
     * !Not public
     * This method is restricted to be called by web API (see denyMethodsCallByWebApi method).
     *
     * Updates user by object.
     *
     * @param User $oUser
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
     * @param int|string $UserId User identifier or UUID.
     * @return User
     */
    public function GetUserWithoutRoleCheck($UserId = '')
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $oUser = $this->getUsersManager()->getUser($UserId);

        return $oUser ? $oUser : null;
    }

    /**
     * !Not public
     * This method is restricted to be called by web API (see denyMethodsCallByWebApi method).
     *
     * Returns user object.
     *
     * @param string $PublicId User public identifier.
     * @return User
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
     * @return User
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
     * @param User $oUser
     * @return int
     */
    public function UpdateTokensValidFromTimestamp($oUser)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $oUser->TokensValidFromTimestamp = time();
        $this->getUsersManager()->updateUser($oUser);
        return $oUser->TokensValidFromTimestamp;
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

        $Filters = ($Filters instanceof Builder) ? $Filters : User::query();
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
     *
     */
    public function GetTotalUsersCount()
    {
        $count = 0;
        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
        $oUser = Api::getAuthenticatedUser();
        if ($oUser->isAdmin()) {
            $count = $this->getUsersManager()->getTotalUsersCount();
        } else {
            $count = $this->getUsersManager()->getUsersCountForTenant($oUser->IdTenant);
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
     * @return User|null
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
            $oTenant = $this->GetDefaultGlobalTenant();
            $TenantId = $oTenant ? $oTenant->Id : null;
        }

        $oAuthenticatedUser = Api::getAuthenticatedUser();
        if (!($oAuthenticatedUser instanceof User && $oAuthenticatedUser->Role === UserRole::TenantAdmin && $oAuthenticatedUser->IdTenant === $TenantId)) {
            Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
        }

        if (!$oTenant) {
            $oTenant = $this->GetTenant($TenantId);
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
            if ($oUser instanceof User) {
                throw new ApiException(Notifications::UserAlreadyExists, null, 'UserAlreadyExists');
            } else {
                if (class_exists('\Aurora\Modules\Licensing\Module')) {
                    $oLicense = \Aurora\Modules\Licensing\Module::Decorator();
                    if (!$oLicense->ValidateUsersCount($this->GetTotalUsersCount()) || !$oLicense->ValidatePeriod()) {
                        Api::Log("Error: License limit");
                        throw new ApiException(Notifications::LicenseLimit, null, 'LicenseLimit');
                    }
                }
            }

            $oUser = new User();

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
            $oUser = self::Decorator()->GetUserWithoutRoleCheck($UserId);
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

        $oUser = self::Decorator()->GetUserWithoutRoleCheck($UserId);

        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        if ($oUser instanceof User && $oAuthenticatedUser->Role === UserRole::TenantAdmin &&
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
    public function GetUsersWithSeparateLog()
    {
        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        $Filters = User::query();
        $oAuthenticatedUser = Api::getAuthenticatedUser();
        if ($oAuthenticatedUser->Role === UserRole::TenantAdmin) {
            $Filters = $Filters->where('IdTenant', $oAuthenticatedUser->IdTenant);
        }
        return $Filters->select('PublicId')->where('WriteSeparateLog', true)->orderBy('PublicId')->pluck('PublicId');
    }

    /**
     *
     */
    public function TurnOffSeparateLogs()
    {
        Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);

        $Filters = User::query();
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
}
