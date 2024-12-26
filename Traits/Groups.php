<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Traits;

use Aurora\Api;
use Aurora\Modules\Contacts\Enums\StorageType;
use Aurora\Modules\Contacts\Module as ContactsModule;
use Aurora\Modules\Core\Enums\ErrorCodes;
use Aurora\Modules\Core\Models\Group;
use Aurora\Modules\Core\Models\User;
use Aurora\System\Enums\UserRole;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Notifications;

/**
 * System module that provides core functionality such as User management, Tenants management.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Modules
 */
trait Groups
{
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
            throw new \Aurora\Modules\Core\Exceptions\Exception(ErrorCodes::GroupAlreadyExists);
        } else {
            $oGroup = new Group();
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
            $oGroup = new Group();
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
}