<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

/**
 * CApiChannelsManager class summary
 *
 * @package Channels
 */

namespace Aurora\Modules\Core\Managers;

class Groups extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * @var \Aurora\System\Managers\Eav
	 */
	public $oEavManager = null;
	
	/**
	 * @param \Aurora\System\Managers\GlobalManager &$oManager
	 */
	public function __construct($sForcedStorage = '', \Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct('usergroups', $oModule);
		
		$this->oEavManager = new \Aurora\System\Managers\Eav();
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
				$aResult[$oUserGroup->EntityId] = array(
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
			$aResults = $this->oEavManager->getObjectsCount(
				'CUserGroups', 
				array(
					'UrlIdentifier' => '%'.$sSearchDesc.'%'
				)
			);
			
			$iResult = count($aResults);
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
		catch (\Aurora\System\Exceptions\BaseException $oException)
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
	public function isExists(\CUserGroup $oGroup)
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
//					if ($oObject->EntityId !== $oGroup->EntityId)
//					{
//						$bResult = true;
//						break;
//					}
//				}
//			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
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
	public function saveUserGroup(\CUserGroup &$oGroup)
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
						throw new \Aurora\System\Exceptions\ManagerException(Errs::UserGroupsManager_UserGroupCreateFailed);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ManagerException(Errs::UserGroupsManager_UserGroupAlreadyExists);
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
	 * @param CUserGroup $oGroup
	 *
	 * @return bool
	 */
	public function updateUserGroup(\CUserGroup $oGroup)
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
						throw new \Aurora\System\Exceptions\ManagerException(Errs::UserGroupsManager_UserGroupCreateFailed);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ManagerException(Errs::UserGroupsManager_UserGroupDoesNotExist);
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
	 * @param CUserGroup $oGroup
	 *
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function deleteUserGroup(\CUserGroup $oGroup)
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

//			$bResult = $this->oEavManager->deleteObject($oGroup->EntityId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $bResult;
	}
}