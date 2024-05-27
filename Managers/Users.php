<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Managers;

use Aurora\Modules\Core\Enums\ErrorCodes;
use Aurora\Modules\Core\Models\Group;
use Aurora\Modules\Core\Models\User;
use Aurora\Modules\Core\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;
use Aurora\System\Enums\SortOrder;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Users
 */
class Users extends \Aurora\System\Managers\AbstractManager
{
    /**
     *
     * @param \Aurora\System\Module\AbstractModule $oModule
     */
    public function __construct(\Aurora\System\Module\AbstractModule $oModule)
    {
        parent::__construct($oModule);
    }

    /**
     * Retrieves information on particular WebMail Pro user.
     *
     * @param int|string $mUserId User identifier or UUID.
     *
     * @return User | false
     */
    public function getUser($mUserId)
    {
        return \Aurora\Api::getUserById($mUserId);
    }

    public function getUserByPublicId($UserPublicId)
    {
        return \Aurora\Api::getUserByPublicId($UserPublicId);
    }

    /**
     *
     * @param string $sSearchDesc
     * @param Builder $oFilters
     * @return int
     */
    public function getUsersCount($sSearchDesc = '', Builder $oFilters = null, $iGroupId = 0)
    {
        $iResult = 0;
        $query = isset($oFilters) ? $oFilters : User::query();
        if ($iGroupId > 0) {
            $query = $query->whereHas('Groups', function ($q) use ($iGroupId) {
                $q->where('GroupId', $iGroupId);
            });
        } elseif ($iGroupId === 0) {
            $query = $query->doesnthave('Groups');
        }

        if ($sSearchDesc !== '') {
            $query = $query->where('PublicId', 'like', '%' . $sSearchDesc . '%');
        }

        try {
            $iResult = $query->count();
        } catch (\Illuminate\Database\QueryException $oEx) {
            \Aurora\Api::LogException($oEx);
        }

        return $iResult;
    }

    /**
     * Obtains list of information about users.
     * @param int $iOffset
     * @param int $iLimit
     * @param string $sOrderBy = 'Email'. Field by which to sort.
     * @param int $iOrderType = 0
     * @param string $sSearchDesc = ''. If specified, the search goes on by substring in the name and email of default account.
     * @param Builder $oFilters = []
     * @return array | false
     */
    public function getUserList($iOffset = 0, $iLimit = 0, $sOrderBy = 'Name', $iOrderType = SortOrder::ASC, $sSearchDesc = '', Builder $oFilters = null, $iGroupId = 0)
    {
        $aResult = [];
        try {
            $query = isset($oFilters) ? $oFilters : User::query();

            if ($sSearchDesc !== '') {
                $query = $query->where('PublicId', 'like', '%' . $sSearchDesc . '%');
            }
            if ($iOffset > 0) {
                $query = $query->offset($iOffset);
            }
            if ($iLimit > 0) {
                $query = $query->limit($iLimit);
            }

            if ($iGroupId > 0) {
                $oGroup = Group::find($iGroupId);
                if ($oGroup->IsAll) {
                    $query = $query->orWhere('IdTenant', $oGroup->TenantId);
                } else {
                    $query = $query->whereHas('Groups', function ($q) use ($iGroupId) {
                        $q->where('GroupId', $iGroupId);
                    });
                }
            } elseif ($iGroupId === 0) {
                $query = $query->doesnthave('Groups');
            }

            $aResult = $query->orderBy($sOrderBy, $iOrderType === SortOrder::ASC ? 'asc' : 'desc')->get();
        } catch (\Illuminate\Database\QueryException $oEx) {
            $aResult = [];
            \Aurora\Api::LogException($oEx);
        }
        return $aResult;
    }

    /**
     * Determines how many users are in particular tenant. Tenant identifier is used for look up.
     *
     * @param int $iTenantId Tenant identifier.
     *
     * @return int | false
     */
    public function getUsersCountForTenant($iTenantId)
    {
        $mResult = 0;
        try {
            $mResult = User::where('IdTenent', $iTenantId)->count();
        } catch (\Illuminate\Database\QueryException $oEx) {
            $mResult = 0;
            \Aurora\Api::LogException($oEx);
        }
        return $mResult;
    }

    /**
     * Calculates total number of users registered in WebMail Pro.
     *
     * @return int
     */
    public function getTotalUsersCount()
    {
        $iResult = 0;
        try {
            $iResult = User::count();
        } catch (\Illuminate\Database\QueryException $oEx) {
            $iResult = 0;
            \Aurora\Api::LogException($oEx);
        }
        return $iResult;
    }

    /**
     * @param User $oUser
     *
     * @return bool
     */
    public function isExists(User $oUser)
    {
        $bResult = false;
        $oResult = null;

        try {
            $oResult = User::find($oUser->Id);
        } catch (\Illuminate\Database\QueryException $oEx) {
            \Aurora\Api::LogException($oEx);
        }
        if (!empty($oResult) && isset($oResult->IdTenant) && $oResult->IdTenant === $oUser->IdTenant) {
            $bResult = true;
        }

        return $bResult;
    }

    /**
     * @param User $oUser
     *
     * @return bool
     */
    public function createUser(User &$oUser)
    {
        $bResult = false;
        try {
            if ($oUser->validate() && $oUser->Role !== \Aurora\System\Enums\UserRole::SuperAdmin) {
                if (!$this->isExists($oUser)) {
                    $oUser->UUID = $oUser->generateUUID();

                    if (!$oUser->save()) {
                        throw new \Aurora\System\Exceptions\ManagerException(ErrorCodes::UserCreateFailed);
                    }
                } else {
                    throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::UserAlreadyExists);
                }
            }

            $bResult = true;
        } catch (\Aurora\System\Exceptions\BaseException $oException) {
            $bResult = false;
            $this->setLastException($oException);
        }

        return $bResult;
    }

    /**
     * @param \Aurora\Modules\Core\Models\User $oUser
     *
     * @return bool
     */
    public function updateUser(User &$oUser)
    {
        $bResult = false;
        try {
            if ($oUser->Role !== \Aurora\System\Enums\UserRole::SuperAdmin) {
                if (!$oUser->update()) {
                    throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\ErrorCodes::UsersManager_UserCreateFailed);
                }
                \Aurora\Api::removeUserFromCache($oUser->Id);
            }

            $bResult = true;
        } catch (\Aurora\System\Exceptions\BaseException $oException) {
            $bResult = false;
            $this->setLastException($oException);
        }

        return $bResult;
    }

    /**
     * @param User $oUser
     *
     * @return bool
     */
    public function deleteUser(User &$oUser)
    {
        $bResult = false;
        try {
            if (!$oUser->delete()) {
                throw new \Aurora\System\Exceptions\ManagerException(ErrorCodes::UserDeleteFailed);
            }
            UserBlock::where('UserId', $oUser->Id)->delete();
            \Aurora\Api::removeUserFromCache($oUser->Id);

            $bResult = true;
        } catch (\Aurora\System\Exceptions\BaseException $oException) {
            $bResult = false;
            $this->setLastException($oException);
        }

        return $bResult;
    }

    public function deleteUserById($id)
    {
        $result = false;
        if (User::find($id)->delete()) {
            \Aurora\Api::removeUserFromCache($id);

            $result = true;
        }

        return $result;
    }
}
