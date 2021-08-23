<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Managers;

use \Aurora\Modules\Core\Module as CoreModule;
use \Aurora\System\Exceptions\Errs;
use Aurora\System\Exceptions\ErrorCodes;
use \Aurora\Modules\Core\Models\Tenant;
use \Aurora\Modules\Core\Models\Сhannel;
use \Aurora\System\Enums\SortOrder;
/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Tenants
 */
class Tenants extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * @var array
	 */
	static $aTenantNameCache = [];

	/**
	 * @var Tenant
	 */
	static $oDefaultTenant = null;

	/**
	 * @param int $iOffset Offset of list.
	 * @param int $iLimit Limit of the list.
	 * @param string $sSearch Search string.
	 * @return array|false
	 */
	public function getTenantList($iOffset = 0, $iLimit = 0, $sSearch = '', $sOrderBy = 'Name', $iOrderType = SortOrder::ASC)
	{
		$oResult = collect();
		if (!empty($sSearch))
		{
			$query = Tenant::where('Name', 'like', '%'.$sSearch.'%');
		}
		else
		{
			$query = Tenant::query();
		}
		if ($iOffset > 0) {
			$query = $query->offset($iOffset);
		}
		if ($iLimit > 0) {
			$query = $query->limit($iLimit);
		}
		try {
			$oResult = $query->orderBy($sOrderBy, $iOrderType === SortOrder::ASC ? 'asc' : 'desc')->get();
		} catch(\Illuminate\Database\QueryException $oException) {
			\Aurora\Api::LogException($oException);
		}

		return $oResult;
	}

	/**
	 * @param string $sSearch Search string.
	 * @return int|false
	 */
	public function getTenantsCount($sSearch = '')
	{ 
		$iResult = 0;
		try {
			$iResult = Tenant::where('Name', 'like', '%'.$sSearch.'%')->count();
		} catch(\Illuminate\Database\QueryException $oException) {
			$iResult = 0;
			\Aurora\Api::LogException($oException);
		}

		return $iResult;
	}

	/**
	 * @return Aurora\Modules\Core\Models\Tenant
	 */
	public function getDefaultGlobalTenant()
	{
		if (self::$oDefaultTenant === null)
		{
			try
			{
				self::$oDefaultTenant = Tenant::firstWhere('IsDefault', true);
				if (!self::$oDefaultTenant) {
					self::$oDefaultTenant = Tenant::first();
				}
			}
			catch(\Illuminate\Database\QueryException $oException) {
				\Aurora\Api::LogException($oException);
			}
		}

		return self::$oDefaultTenant;
	}

	/**
	 * @param mixed $mTenantId
	 *
	 * @return \Aurora\Modules\Core\Classes\Tenant|null
	 */
	public function getTenantById($mTenantId)
	{
		$oTenant = null;
		try
		{
			$oResult = Tenant::find($mTenantId);

			if ($oResult)
			{
				$oTenant = $oResult;
			}
		}
		catch(\Illuminate\Database\QueryException $oException) 
		{
			\Aurora\Api::LogException($oException);
		}

		return $oTenant;
	}

	/**
	 * @param string $sTenantName
	 * @param string $sTenantPassword Default value is **null**.
	 *
	 * @return
	 */
	public function getTenantByName($sTenantName)	{
		$oTenant = null;
		try
		{
			if (!empty($sTenantName))
			{
				$oTenant = Tenant::where('Name', $sTenantName)->where('IsDisabled', false)->first();
			}
		}
		catch(\Illuminate\Database\QueryException $oException) 
		{
			\Aurora\Api::LogException($oException);
		}
		return $oTenant;
	}

	/**
	 * @param string $sTenantName
	 *
	 * @return int|bool
	 */
	public function getTenantIdByName($sTenantName)
	{
		$iResult = 0;

		if (0 === strlen($sTenantName))
		{
			return 0;
		}
		else if (0 < strlen($sTenantName))
		{
			$oTenant = $this->getTenantByName($sTenantName);
			if ($oTenant)
			{
				$iResult = $oTenant->Id;
			}
		}

		return 0 < $iResult ? $iResult : false;
	}

	/**
	 * @param Tenant $oTenant
	 *
	 * @return bool
	 */
	public function isTenantExists(Tenant $oTenant)
	{
		$bResult = false;

		try
		{
			$bResult = Tenant::where('Name', $oTenant->Name)->where('Id', '!=', $oTenant->Id)->exists();
		}
		catch(\Illuminate\Database\QueryException $oException) 
		{
			\Aurora\Api::LogException($oException);
		}
		return $bResult;
	}

	/**
	 * @param Tenant $oTenant
	 *
	 * @return bool
	 */
	public function createTenant(Tenant &$oTenant)
	{
		$bResult = false;
		try
		{
			if ($oTenant->validate() && !$oTenant->IsDefault)
			{
				if (!$this->isTenantExists($oTenant))
				{
					if (0 < $oTenant->IdChannel)
					{
						/* @var $oChannelsApi CApiChannelsManager */

						$oChannelsManager = CoreModule::getInstance()->getChannelsManager();
						if ($oChannelsManager)
						{
							/* @var $oChannel Сhannel */
							$oChannel = $oChannelsManager->getChannelById($oTenant->IdChannel);
							if (!$oChannel)
							{
								throw new \Aurora\System\Exceptions\ManagerException(Errs::ChannelsManager_ChannelDoesNotExist);
							}
						}
						else
						{
							$oTenant->IdChannel = 0;
						}
					}
					else
					{
						$oTenant->IdChannel = 0;
					}

					if (!$oTenant->save())
					{
						throw new \Aurora\System\Exceptions\ManagerException(Errs::TenantsManager_TenantCreateFailed);
					}

					// if ($oTenant->EntityId)
					// {
					// 	$this->oEavManager->saveEntity($oTenant);
					// }
				}
				else
				{
					throw new \Aurora\System\Exceptions\ManagerException(Errs::TenantsManager_TenantAlreadyExists);
				}
			}

			$bResult = true;
		}
		catch(\Illuminate\Database\QueryException $oException) 
		{
			\Aurora\Api::LogException($oException);
		}

		return $bResult;
	}

	/**
	 * @param Aurora\Modules\Core\Classes\Tenant $oTenant
	 *
	 * @throws \Aurora\System\Exceptions\ManagerException(Errs::TenantsManager_QuotaLimitExided) 1707
	 * @throws \Aurora\System\Exceptions\ManagerException(Errs::TenantsManager_TenantUpdateFailed) 1703
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function updateTenant(Tenant $oTenant)
	{
		$bResult = false;
		try
		{
			if ($oTenant->validate() && $oTenant->Id !== 0)
			{
				$bResult = $oTenant->save();
			}
		}
		catch(\Illuminate\Database\QueryException $oException) 
		{
			\Aurora\Api::LogException($oException);
		}
		return $bResult;
	}

	/**
	 * @todo
	 * @param int $iTenantID
	 *
	 * @return false
	 */
	public function updateTenantMainCapa($iTenantID)
	{
		return false;
	}

	/**
	 * @param int $iChannelId
	 *
	 * @return array
	 */
	public function getTenantsByChannelId($iChannelId)
	{
		$aResult = false;
		try
		{
			$aResult = Tenant::where('IdChannel', $iChannelId)->get();
		}
		catch(\Illuminate\Database\QueryException $oException) 
		{
			\Aurora\Api::LogException($oException);
		}
		return $aResult;
	}

	/**
	 * @param int $iChannelId
	 *
	 * @return array
	 */
	public function getTenantsByChannelIdCount($iChannelId)
	{
		$iCount = 0;
		try 
		{
			$iCount = Tenant::where('IdChannel', $iChannelId)->count();
		}
		catch(\Illuminate\Database\QueryException $oException) 
		{
			\Aurora\Api::LogException($oException);
		}

		return $iCount;
	}

	/**
	 * @param int $iChannelId
	 *
	 * @return bool
	 */
	public function deleteTenantsByChannelId($iChannelId)
	{
		return Tenant::where('IdChannel', $iChannelId)->delete();
	}

	/**
	 * @TODO rewrite other menagers usage
	 *
	 * @param Aurora\Modules\Core\Classes\Tenant $oTenant
	 *
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function deleteTenant(Tenant $oTenant)
	{
		$bResult = false;
		try
		{
			if ($oTenant)
			{
				$bResult = $oTenant->delete();
			}
		}
		catch(\Illuminate\Database\QueryException $oException) 
		{
			\Aurora\Api::LogException($oException);
		}

		return $bResult;
	}
}
