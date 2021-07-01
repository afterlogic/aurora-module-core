<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Managers;

use Aurora\Modules\Core\Models\UserGroup;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Groups extends \Aurora\System\Managers\AbstractManager
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
	 * @param int $iPage
	 * @param int $iItemsPerPage
	 * @param string $sOrderBy Default value is **Login**
	 * @param bool $iOrderType Default value is **\Aurora\System\Enums\SortOrder::ASC**
	 * @param string $sSearchDesc Default value is empty string
	 *
	 * @return array|false [Id => [Login, Description]]
	 */
	public function getUserGroupsList($iPage, $iItemsPerPage, $sOrderBy = 'Login', $iOrderType = \Aurora\System\Enums\SortOrder::ASC, $sSearchDesc = '')
	{
		$aResult = false;
		try
		{
			$aResultGroups = UserGroup::where('UrlIdentifier', '%'.$sSearchDesc.'%')
				->orderBy($sOrderBy, $iOrderType === \Aurora\System\Enums\SortOrder::ASC ? 'asc' : 'desc')
				->offset($iPage)
				->limit($iItemsPerPage)->get()
			;

			foreach($aResultGroups as $oUserGroup)
			{
				$aResult[$oUserGroup->Id] = array(
					$oUserGroup->UrlIdentifier,
					$oUserGroup->IdTenant
				);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $aResult;
	}

	/**
	 * @param string $sSearchDesc Default value is empty string
	 *
	 * @return int|false
	 */
	public function getUserGroupsCount($sSearchDesc = '')
	{
		$iResult = false;
		try
		{
			$iResult = UserGroup::where('UrlIdentifier', '%'.$sSearchDesc.'%')->count();
		;
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $iResult;
	}

	/**
	 * @param int $iGroupId
	 *
	 * @return Aurora\Modules\Core\Classes\Channel
	 */
	public function getUserGroupById($iGroupId)
	{
		$oGroup = null;
		try
		{
			$oResult = UserGroup::find($iGroupId);

			if ($oResult instanceOf UserGroup)
			{
				$oGroup = $oResult;
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $oGroup;
	}


	/**
	 * @param Aurora\Modules\Core\Classes\UserGroup $oGroup
	 *
	 * @return bool
	 */
	public function isExists(UserGroup $oGroup)
	{
		return false;
	}

	/**
	 * @param Aurora\Modules\Core\Classes\UserGroup $oGroup
	 *
	 * @return bool
	 */
	public function saveUserGroup(UserGroup &$oGroup)
	{
		$bResult = false;
		try
		{
			if ($oGroup->validate())
			{
				if (!$this->isExists($oGroup))
				{
					if (!$oGroup->save())
					{
						throw new \Aurora\System\Exceptions\ManagerException(0);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ManagerException(0);
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
	 * @param Aurora\Modules\Core\Classes\UserGroup $oGroup
	 *
	 * @return bool
	 */
	public function updateUserGroup(UserGroup $oGroup)
	{
		$bResult = false;
		try
		{
			if ($oGroup->validate())
			{
				if (!$this->isExists($oGroup))
				{
					if (!$oGroup->save())
					{
						throw new \Aurora\System\Exceptions\ManagerException(0);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ManagerException(0);
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
	 * @todo
	 * @param Aurora\Modules\Core\Classes\UserGroup $oGroup
	 *
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function deleteUserGroup(UserGroup $oGroup)
	{
		$bResult = false;
		try
		{
//			$oTenantsManager = new \Aurora\Modules\Core\Managers\Tenants\Manager();
//
//			if ($oTenantsManager && !$oTenantsManager->deleteTenantsByChannelId($oGroup->EntityId, true))
//			{
//				$oException = $oTenantsManager->GetLastException();
//				if ($oException)
//				{
//					throw $oException;
//				}
//			}

			$bResult = $oGroup->delete();
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $bResult;
	}
}