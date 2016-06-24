<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * CApiChannelsManager class summary
 *
 * @package Channels
 */
//class CApiChannelsManager extends AApiManagerWithStorage
class CApiCoreUserGroupsManager extends AApiManager
{
	/**
	 * @var CApiEavManager
	 */
	public $oEavManager = null;
	
	/**
	 * @param CApiGlobalManager &$oManager
	 */
	public function __construct(CApiGlobalManager &$oManager, $sForcedStorage = '', AApiModule $oModule = null)
	{
		parent::__construct('usergroups', $oManager, $oModule);
		
		$this->oEavManager = \CApi::GetSystemManager('eav', 'db');

		$this->incClass('usergroup');
	}

	/**
	 * @return CUserGroup
	 */
	public function createUserGroup()
	{
		return CUserGroup::createInstance();
	}

	/**
	 * @param int $iPage
	 * @param int $iItemsPerPage
	 * @param string $sOrderBy Default value is **Login**
	 * @param bool $iOrderType Default value is **\ESortOrder::ASC**
	 * @param string $sSearchDesc Default value is empty string
	 *
	 * @return array|false [Id => [Login, Description]]
	 */
	public function getUserGroupsList($iPage, $iItemsPerPage, $sOrderBy = 'Login', $iOrderType = \ESortOrder::ASC, $sSearchDesc = '')
	{
		$aResult = false;
		try
		{
			$aResultGroups = $this->oEavManager->getObjects(
				'CUserGroup', 
				array('UrlIdentifier', 'IdTenant'),
				$iPage,
				$iItemsPerPage,
				array(
					'UrlIdentifier' => '%'.$sSearchDesc.'%'
				),
				$sOrderBy,
				$iOrderType
			);
			
			foreach($aResultGroups as $oUserGroup)
			{
				$aResult[$oUserGroup->iObjectId] = array(
					$oUserGroup->UrlIdentifier,
					$oUserGroup->IdTenant
				);
			}
		}
		catch (CApiBaseException $oException)
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
			$aResults = $this->oEavManager->getObjectsCount(
				'CUserGroups', 
				array(
					'UrlIdentifier' => '%'.$sSearchDesc.'%'
				)
			);
			
			$iResult = count($aResults);
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $iResult;
	}

	/**
	 * @param int $iGroupId
	 *
	 * @return CChannel
	 */
	public function getUserGroupById($iGroupId)
	{
		$oGroup = null;
		try
		{
			$oResult = $this->oEavManager->getObjectById($iGroupId);
			
			if ($oResult instanceOf \CChannel)
			{
				$oGroup = $oResult;
			}
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $oGroup;
	}


	/**
	 * @param CUserGroup $oGroup
	 *
	 * @return bool
	 */
	public function isExists(CUserGroup $oGroup)
	{
		$bResult = false;
		try
		{
//			$aResultChannels = $this->oEavManager->getObjects(
//				'CUserGroup',
//				array('Login'),
//				0,
//				0,
//				array('Login' => $oGroup->Login)
//			);
//
//			if ($aResultChannels)
//			{
//				foreach($aResultChannels as $oObject)
//				{
//					if ($oObject->iObjectId !== $oGroup->iObjectId)
//					{
//						$bResult = true;
//						break;
//					}
//				}
//			}
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param CUserGroup $oGroup
	 *
	 * @return bool
	 */
	public function saveUserGroup(CUserGroup &$oGroup)
	{
		$bResult = false;
		try
		{
			if ($oGroup->validate())
			{
				if (!$this->isExists($oGroup))
				{
					if (!$this->oEavManager->saveObject($oGroup))
					{
						throw new CApiManagerException(Errs::UserGroupsManager_UserGroupCreateFailed);
					}
				}
				else
				{
					throw new CApiManagerException(Errs::UserGroupsManager_UserGroupAlreadyExists);
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
	 * @param CUserGroup $oGroup
	 *
	 * @return bool
	 */
	public function updateUserGroup(CUserGroup $oGroup)
	{
		$bResult = false;
		try
		{
			if ($oGroup->validate())
			{
				if (!$this->isExists($oGroup))
				{
					if (!$this->oEavManager->saveObject($oGroup))
					{
						throw new CApiManagerException(Errs::UserGroupsManager_UserGroupCreateFailed);
					}
				}
				else
				{
					throw new CApiManagerException(Errs::UserGroupsManager_UserGroupDoesNotExist);
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
	 * @todo
	 * @param CUserGroup $oGroup
	 *
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function deleteUserGroup(CUserGroup $oGroup)
	{
		$bResult = false;
		try
		{
//			$oTenantsApi = $this->oModule->GetManager('tenants');
//			
//			if ($oTenantsApi && !$oTenantsApi->deleteTenantsByChannelId($oGroup->iObjectId, true))
//			{
//				$oException = $oTenantsApi->GetLastException();
//				if ($oException)
//				{
//					throw $oException;
//				}
//			}

//			$bResult = $this->oEavManager->deleteObject($oGroup->iObjectId);
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $bResult;
	}
}