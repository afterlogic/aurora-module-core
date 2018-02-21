<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Managers;
 
use Aurora\System\Exceptions\Errs;
use Aurora\System\Exceptions\ErrorCodes;

/**
 * CApiTenantsManager class summary
 *
 * @package Tenants
 */
class Tenants extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * @var array
	 */
	static $aTenantNameCache = array();
	
	/**
	 * @var \Aurora\System\Managers\Eav
	 */
	public $oEavManager = null;
	
	public $oChannelsManager = null;
	
	/**
	 * @var Aurora\Modules\Core\Classes\Tenant
	 */
	static $oDefaultTenant = null;

	/**
	 * Creates a new instance of the object.
	 *
	 */
	public function __construct(\Aurora\System\Module\AbstractModule $oModule)
	{
		parent::__construct($oModule);
		
		$this->oEavManager = new \Aurora\System\Managers\Eav();
		
		$this->oChannelsManager = new Channels($oModule);
	}

	/**
	 * @param int $iOffset Default value is **0**.
	 * @param int $iLimit Default value is **0**.
	 * @param string $sOrderBy Default value is **'Name'**.
	 * @param int $iOrderType Default value is **0**.
	 * @param string $sSearchDesc Default value is empty string.
	 *
	 * @return array|false [Id => [Name, Description]]
	 */
	public function getTenantList($iOffset = 0, $iLimit = 0, $sOrderBy = 'Name', $iOrderType = \Aurora\System\Enums\SortOrder::ASC, $sSearchDesc = '')
	{
		$aResult = false;
		try
		{
			$aResult = $this->oEavManager->getEntities(
				$this->getModule()->getNamespace() . '\Classes\Tenant',
				array(
					'Name', 
					'Description',
					'IdChannel'
				),
				$iOffset,
				$iLimit,
				array(
					'Name' => array(
						'%'.$sSearchDesc.'%',
						'LIKE'
					)
				),
				$sOrderBy,
				$iOrderType
			);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $aResult;
	}

	/**
	 * @param string $sSearchDesc Default value is empty string.
	 *
	 * @return int|false
	 */
	public function getTenantCount($sSearchDesc = '')
	{
		$iResult = false;
		try
		{
			$aResultTenants = $this->oEavManager->getEntitiesCount(
				$this->getModule()->getNamespace() . '\Classes\Tenant',
				array(
					'Description' => $sSearchDesc
				)
			);
			
			$iResult = count($aResultTenants);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $iResult;
	}

	/**
	 * @return Aurora\Modules\Core\Classes\Tenant
	 */
	public function getDefaultGlobalTenant()
	{
		if (self::$oDefaultTenant === null)
		{
			try
			{
				$oResult = $this->oEavManager->getEntities(
					$this->getModule()->getNamespace() . '\Classes\Tenant',
					array(
						'IsDefault'
					),
					0,
					0,
					array('IsDefault' => true)
				);

				if ($oResult instanceOf \Aurora\Modules\Core\Classes\Tenant)
				{
					self::$oDefaultTenant = $oResult;
				}
			}
			catch (\Aurora\System\Exceptions\BaseException $oException)
			{
				$this->setLastException($oException);
			}
		}
		
		return self::$oDefaultTenant;
	}

	/**
	 * @param mixed $mTenantId
	 *
	 * @return Aurora\Modules\Core\Classes\Tenant|null
	 */
	public function getTenantById($mTenantId)
	{
		$oTenant = null;
		try
		{
			$oResult = $this->oEavManager->getEntity($mTenantId, $this->getModule()->getNamespace() . '\Classes\Tenant');
				
			if (!empty($oResult))
			{
				$oTenant = $oResult;
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $oTenant;
	}

	/**
	 * @param string $sTenantName
	 * @param string $sTenantPassword Default value is **null**.
	 *
	 * @return 
	 */
	public function getTenantByName($sTenantName
)	{
		$oTenant = null;
		try
		{
			if (!empty($sTenantName))
			{
				$oFilterBy = array('Name' => $sTenantName);
//				if (null !== $sTenantPassword)
//				{
//					$oFilterBy['PasswordHash'] = Aurora\Modules\Core\Classes\Tenant::hashPassword($sTenantPassword);
					
					//TODO why we shoud filter by these fields?
					$oFilterBy['IsDisabled'] = false;
//					$oFilterBy['IsEnableAdminPanelLogin'] = true;
//				}
				
				$aResultTenants = $this->oEavManager->getEntities(
					$this->getModule()->getNamespace() . '\Classes\Tenant',
					array(
						'Name'
					),
					0,
					1,
					$oFilterBy
				);
				
				if (($aResultTenants[0]) && $aResultTenants[0] instanceOf \Aurora\Modules\Core\Classes\Tenant)
				{
					$oTenant = $aResultTenants[0];
				}
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
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
		//TODO
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
				$iResult = $oTenant->EntityId;
			}
		}

		return 0 < $iResult ? $iResult : false;
	}

	/**
	 * @param Aurora\Modules\Core\Classes\Tenant $oTenant
	 *
	 * @return bool
	 */
	public function isTenantExists(\Aurora\Modules\Core\Classes\Tenant $oTenant)
	{
		//TODO
//		$bResult = $oTenant->IsDefault;
		
		$bResult = false;

		try
		{
			$aResultTenants = $this->oEavManager->getEntities($this->getModule()->getNamespace() . '\Classes\Tenant',
				array('Name'),
				0,
				0,
				array('Name' => $oTenant->Name)
			);

			if ($aResultTenants)
			{
				foreach($aResultTenants as $oObject)
				{
					if ($oObject->EntityId !== $oTenant->EntityId)
					{
						$bResult = true;
						break;
					}
				}
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param Aurora\Modules\Core\Classes\Tenant $oTenant
	 *
	 * @return bool
	 */
	public function createTenant(\Aurora\Modules\Core\Classes\Tenant &$oTenant)
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
						
						if ($this->oChannelsManager)
						{
							/* @var $oChannel Aurora\Modules\Core\Classes\Channel */
							$oChannel = $this->oChannelsManager->getChannelById($oTenant->IdChannel);
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
					
					if (!$this->oEavManager->saveEntity($oTenant))
					{
						throw new \Aurora\System\Exceptions\ManagerException(Errs::TenantsManager_TenantCreateFailed);
					}
					
					if ($oTenant->EntityId)
					{
						$this->oEavManager->saveEntity($oTenant);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ManagerException(Errs::TenantsManager_TenantAlreadyExists);
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
	 * @param Aurora\Modules\Core\Classes\Tenant $oTenant
	 *
	 * @throws \Aurora\System\Exceptions\ManagerException(Errs::TenantsManager_QuotaLimitExided) 1707
	 * @throws \Aurora\System\Exceptions\ManagerException(Errs::TenantsManager_TenantUpdateFailed) 1703
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function updateTenant(\Aurora\Modules\Core\Classes\Tenant $oTenant)
	{
		$bResult = false;
		try
		{
			if ($oTenant->validate() && $oTenant->EntityId !== 0)
			{
				$bResult = $this->oEavManager->saveEntity($oTenant);
			}
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
			$aResult = $this->oEavManager->getEntities(
				$this->getModule()->getNamespace() . '\Classes\Tenant',
				array('IsDefault', 'IdChannel'),
				0,
				0,
				array('IdChannel' => $iChannelId)
			);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $aResult;
	}

	/**
	 * @param int $iChannelId
	 *
	 * @return bool
	 */
	public function deleteTenantsByChannelId($iChannelId)
	{
		$iResult = 1;
		$aTenants = $this->getTenantsByChannelId($iChannelId);

		if (is_array($aTenants))
		{
			foreach ($aTenants as $oTenant)
			{
				if (!$oTenant->IsDefault && 0 < $oTenant->EntityId)
				{
					$iResult &= $this->deleteTenant($oTenant);
				}
			}
		}

		return (bool) $iResult;
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
	public function deleteTenant(\Aurora\Modules\Core\Classes\Tenant $oTenant)
	{
		$bResult = false;
		try
		{
			if ($oTenant)
			{
				$bResult = $this->oEavManager->deleteEntity($oTenant->EntityId);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $bResult;
	}
}
