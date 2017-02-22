<?php
/**
 * @copyright Copyright (c) 2016, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
 */

/**
 * CApiTenantsManager class summary
 *
 * @package Tenants
 */
class CApiCoreTenantsManager extends AApiManager
{
	/**
	 * @var array
	 */
	static $aTenantNameCache = array();
	
	/**
	 * @var CApiEavManager
	 */
	public $oEavManager = null;
	
	public $oChannelsManager = null;
	
	/**
	 * @var CTenant
	 */
	static $oDefaultTenant = null;

	/**
	 * Creates a new instance of the object.
	 *
	 * @param CApiGlobalManager &$oManager
	 */
	public function __construct(CApiGlobalManager &$oManager, $sForcedStorage = 'db', AApiModule $oModule = null)
	{
		parent::__construct('tenants', $oManager, $oModule);
		
		$this->oEavManager = \CApi::GetSystemManager('eav', 'db');
		
		$this->oChannelsManager = $this->oModule->GetManager('channels', 'db');
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
	public function getTenantList($iOffset = 0, $iLimit = 0, $sOrderBy = 'Name', $iOrderType = \ESortOrder::ASC, $sSearchDesc = '')
	{
		$aResult = false;
		try
		{
			$aResult = $this->oEavManager->getEntities(
				'CTenant', 
				array(
					'Name', 
					'Description',
					'IdChannel'
				),
				$iOffset,
				$iLimit,
				array(
					'Description' => array(
						'%'.$sSearchDesc.'%',
						'LIKE'
					)
				),
				$sOrderBy,
				$iOrderType
			);
		}
		catch (CApiBaseException $oException)
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
				'CTenant', 
				array(
					'Description' => $sSearchDesc
				)
			);
			
			$iResult = count($aResultTenants);
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $iResult;
	}

	/**
	 * @return CTenant
	 */
	public function getDefaultGlobalTenant()
	{
		if (self::$oDefaultTenant === null)
		{
			try
			{
				$oResult = $this->oEavManager->getEntities(
					'CTenant', 
					array(
						'IsDefault'
					),
					0,
					0,
					array('IsDefault' => true)
				);

				if ($oResult instanceOf \CTenant)
				{
					self::$oDefaultTenant = $oResult;
				}
			}
			catch (CApiBaseException $oException)
			{
				$this->setLastException($oException);
			}
		}
		
		return self::$oDefaultTenant;
	}

	/**
	 * @param mixed $mTenantId
	 *
	 * @return CTenant|null
	 */
	public function getTenantById($mTenantId)
	{
		$oTenant = null;
		try
		{
			$oResult = $this->oEavManager->getEntity($mTenantId);
				
			if ($oResult instanceOf \CTenant)
			{
				$oTenant = $oResult;
			}
		}
		catch (CApiBaseException $oException)
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
//					$oFilterBy['PasswordHash'] = CTenant::hashPassword($sTenantPassword);
					
					//TODO why we shoud filter by these fields?
					$oFilterBy['IsDisabled'] = false;
//					$oFilterBy['IsEnableAdminPanelLogin'] = true;
//				}
				
				$aResultTenants = $this->oEavManager->getEntities(
					'CTenant', 
					array(
						'Name'
					),
					0,
					1,
					$oFilterBy
				);
				
				if (($aResultTenants[0]) && $aResultTenants[0] instanceOf \CTenant)
				{
					$oTenant = $aResultTenants[0];
				}
			}
		}
		catch (CApiBaseException $oException)
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
	 * @param CTenant $oTenant
	 *
	 * @return bool
	 */
	public function isTenantExists(CTenant $oTenant)
	{
		//TODO
//		$bResult = $oTenant->IsDefault;
		
		$bResult = false;

		try
		{
			$aResultTenants = $this->oEavManager->getEntities('CTenant',
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
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param CTenant $oTenant
	 *
	 * @return bool
	 */
	public function createTenant(CTenant &$oTenant)
	{
		$bResult = false;
		try
		{
			if ($oTenant->validate() && !$oTenant->IsDefault)
			{
				if (!$this->isTenantExists($oTenant))
				{
					if (0 < $oTenant->IdChannel &&\CApi::GetConf('tenant', false))
					{
						/* @var $oChannelsApi CApiChannelsManager */
						
						if ($this->oChannelsManager)
						{
							/* @var $oChannel CChannel */
							$oChannel = $this->oChannelsManager->getChannelById($oTenant->IdChannel);
							if (!$oChannel)
							{
								throw new CApiManagerException(Errs::ChannelsManager_ChannelDoesNotExist);
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
						throw new CApiManagerException(Errs::TenantsManager_TenantCreateFailed);
					}
					
					if ($oTenant->EntityId)
					{
						$this->oEavManager->saveEntity($oTenant);
					}
				}
				else
				{
					throw new CApiManagerException(Errs::TenantsManager_TenantAlreadyExists);
				}
			}

			$bResult = true;
		}
		catch (CApiBaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
	}

	/**
	 * @param CTenant $oTenant
	 *
	 * @throws CApiManagerException(Errs::TenantsManager_QuotaLimitExided) 1707
	 * @throws CApiManagerException(Errs::TenantsManager_TenantUpdateFailed) 1703
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function updateTenant(CTenant $oTenant)
	{
		$bResult = false;
		try
		{
			if ($oTenant->validate() && $oTenant->EntityId !== 0)
			{
				$bResult = $this->oEavManager->saveEntity($oTenant);
			}
		}
		catch (CApiBaseException $oException)
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
				'CTenant',
				array('IsDefault', 'IdChannel'),
				0,
				0,
				array('IdChannel' => $iChannelId)
			);
		}
		catch (CApiBaseException $oException)
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
	 * @param CTenant $oTenant
	 *
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function deleteTenant(CTenant $oTenant)
	{
		$bResult = false;
		try
		{
			if ($oTenant)
			{
				$bResult = $this->oEavManager->deleteEntity($oTenant->EntityId);
			}
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $bResult;
	}
}
