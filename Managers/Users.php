<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Managers;

use \Aurora\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use \Aurora\System\Enums\SortOrder;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
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
		$oUser = false;
		try
		{
			$oUser = User::findOrFail($mUserId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oUser = false;
			$this->setLastException($oException);
		}
		return $oUser;
	}

	public function getUserByPublicId($UserPublicId)
	{
		$sUserPublicId = trim((string)$UserPublicId);

		if ($sUserPublicId)
		{
			return User::firstWhere('PublicId', $sUserPublicId);
		}
		return null;
	}

	/**
	 *
	 * @param string $sSearchDesc
	 * @param Builder $oFilters
	 * @return int
	 */
	public function getUsersCount($sSearchDesc = '', Builder $oFilters = null)
	{
		$query = isset($oFilters) ? $oFilters : User::query();

		if ($sSearchDesc !== '') {
			$query = $query->where('PublicId', 'like', '%'.$sSearchDesc.'%');
		}

		return $query->count();
	}

	/**
	 * Obtains list of information about users.
	 * @param int $iOffset
	 * @param int $iLimit
	 * @param string $sOrderBy = 'Email'. Field by which to sort.
	 * @param int $iOrderType = 0
	 * @param string $sSearchDesc = ''. If specified, the search goes on by substring in the name and email of default account.
	 * @param array $aFilters = []
	 * @return array | false
	 */
	public function getUserList($iOffset = 0, $iLimit = 0, $sOrderBy = 'Name', $iOrderType = SortOrder::ASC, $sSearchDesc = '', Builder $oFilters = null)
	{
		$aResult = false;
		try
		{
			$query = isset($oFilters) ? $oFilters : User::query();

			if ($sSearchDesc !== '') {
				$query = $query->where('PublicId', 'like', '%'.$sSearchDesc.'%');
			}
			if ($iOffset > 0) {
				$query = $query->offset($iOffset);
			}
			if ($iLimit > 0) {
				$query = $query->limit($iLimit);
			}

			$aResult = $query->orderBy($sOrderBy, $iOrderType === SortOrder::ASC ? 'asc' : 'desc')->get();
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$aResult = false;
			$this->setLastException($oException);
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
		$mResult = false;
		try
		{
			$mResult = User::where('IdTenent', $iTenantId)->count();
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
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
		try
		{
			$iResult = User::count();
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
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

		$oResult = User::find($oUser->Id);

		if (!empty($oResult) && isset($oResult->IdTenant) && $oResult->IdTenant === $oUser->IdTenant)
		{
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
		try
		{
			if ($oUser->validate())
			{
				if (!$this->isExists($oUser))
				{
					$oUser->DateCreated = date('Y-m-d H:i:s');

					if (!$oUser->save())
					{
						throw new \Aurora\System\Exceptions\ManagerException(Errs::UsersManager_UserCreateFailed);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::UserAlreadyExists);
				}
			}

			$bResult = true;
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
	}

	/**
	 * @param Aurora\Modules\Core\Classes\Channel $oChannel
	 *
	 * @return bool
	 */
	public function updateUser(User &$oUser)
	{
		$bResult = false;
		try
		{
			if ($oUser->validate() && $oUser->Role !== \Aurora\System\Enums\UserRole::SuperAdmin)
			{
				if (!$oUser->update())
				{
					throw new \Aurora\System\Exceptions\ManagerException(Errs::UsersManager_UserCreateFailed);
				}
			}

			$bResult = true;
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
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
		try
		{
//			if ($oUser->validate())
//			{
				if (!$oUser->delete())
				{
					throw new \Aurora\System\Exceptions\ManagerException(Errs::UsersManager_UserDeleteFailed);
				}
//			}

			$bResult = true;
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
	}
}
