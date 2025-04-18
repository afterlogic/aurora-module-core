<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Traits;

use Aurora\Api;
use Aurora\Modules\Core\Models\Tenant;
use Aurora\Modules\Core\Models\Group;
use Aurora\Modules\Core\Models\User;
use Aurora\System\Enums\UserRole;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Notifications;
use Aurora\Modules\Core\Enums\ErrorCodes;

/**
 * System module that provides core functionality such as User management, Tenants management.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Modules
 */
trait Tenants
{
    protected $oTenantsManager = null;

    public function initTenantsTrait()
    {
        $this->aErrors[ErrorCodes::TenantAlreadyExists] = $this->i18N('ERROR_TENANT_ALREADY_EXISTS');
        $this->denyMethodsCallByWebApi([
            'GetTenantName',
            'GetTenantIdByName',
            'GetDefaultGlobalTenant',
        ]);
    }

    /**
     * @return \Aurora\Modules\Core\Managers\Tenants
     */
    public function getTenantsManager()
    {
        if ($this->oTenantsManager === null) {
            $this->oTenantsManager = new \Aurora\Modules\Core\Managers\Tenants($this);
        }

        return $this->oTenantsManager;
    }

    /**
     * !Not public
     * This method is restricted to be called by web API (see denyMethodsCallByWebApi method).
     *
     * Returns tenant object by identifier.
     *
     * @param int $Id Tenant identifier.
     * @return Tenant|null
     */
    public function GetTenantWithoutRoleCheck($Id)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        $oTenant = $this->getTenantsManager()->getTenantById($Id);

        return $oTenant ? $oTenant : null;
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
            $oTenant = self::Decorator()->GetTenantWithoutRoleCheck($oUser->IdTenant);
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
     * @return Tenant
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
     * Updates tenant.
     *
     * @param Tenant $oTenant
     * @return bool
     */
    public function UpdateTenantObject($oTenant)
    {
        /** This method is restricted to be called by web API (see denyMethodsCallByWebApi method). **/

        return $this->getTenantsManager()->updateTenant($oTenant);
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
     * @return Tenant|null
     */
    public function GetTenant($Id)
    {
        $oAuthenticatedUser = Api::getAuthenticatedUser();
        if (($oAuthenticatedUser instanceof User) && $oAuthenticatedUser->IdTenant === $Id) {
            Api::checkUserRoleIsAtLeast(UserRole::TenantAdmin);
        } else {
            Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);
        }

        return $this->GetTenantWithoutRoleCheck($Id);
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
                $oTenant = new Tenant();

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
}
