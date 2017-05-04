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

class CApiCoreChannelsManager extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * @var \Aurora\System\Managers\Eav\Manager
	 */
	public $oEavManager = null;
	
	/**
	 * @param \Aurora\System\Managers\GlobalManager &$oManager
	 */
	public function __construct(\Aurora\System\Managers\GlobalManager &$oManager, $sForcedStorage = '', \Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct('channels', $oManager, $oModule);
		
		$this->oEavManager = \Aurora\System\Api::GetSystemManager('eav', 'db');
	}

	/**
	 * @param int $iOffset
	 * @param int $iLimit
	 * @param string $sOrderBy Default value is **Login**
	 * @param bool $iOrderType Default value is **\ESortOrder::ASC**
	 * @param string $sSearchDesc Default value is empty string
	 *
	 * @return array|false [Id => [Login, Description]]
	 */
	public function getChannelList($iOffset = 0, $iLimit = 0, $sOrderBy = 'Login', $iOrderType = \ESortOrder::ASC, $sSearchDesc = '')
	{
		$aResult = false;
		$aSearch = empty($sSearchDesc) ? array() : array(
			'Login' => '%'.$sSearchDesc.'%',
			'Description' => '%'.$sSearchDesc.'%'
		);
		try
		{
			$aResult = $this->oEavManager->getEntities(
				'CChannel', 
				array('Login', 'Description', 'Password'),
				$iOffset,
				$iLimit,
				$aSearch,
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
	 * @param string $sSearchDesc Default value is empty string
	 *
	 * @return int
	 */
	public function getChannelCount($sSearchDesc = '')
	{
		$iResult = 0;
		try
		{
			$aResults = $this->oEavManager->getEntitiesCount('CChannel', 
				array(
					'Login' => '%'.$sSearchDesc.'%',
					'Description' => '%'.$sSearchDesc.'%'
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
	 * @param int $iChannelId
	 *
	 * @return CChannel
	 */
	public function getChannelById($iChannelId)
	{
		$oChannel = null;
		try
		{
			$oResult = $this->oEavManager->getEntity($iChannelId);
			
			if ($oResult instanceOf \CChannel)
			{
				$oChannel = $oResult;
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $oChannel;
	}

	/**
	 * @param string $sChannelLogin
	 *
	 * @return int
	 */
	public function getChannelIdByLogin($sChannelLogin)
	{
		$iChannelId = 0;
		try
		{
			$aResultChannels = $this->oEavManager->getEntities('CChannel', 
				array(
					'Login'
				),
				0,
				1,
				array('Login' => $sChannelLogin)
			);
			
			if (isset($aResultChannels[0]) && $aResultChannels[0] instanceOf \CChannel)
			{
				$iChannelId = $aResultChannels[0]->EntityId;
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $iChannelId;
	}

	/**
	 * @param CChannel $oChannel
	 *
	 * @return bool
	 */
	public function isExists(CChannel $oChannel)
	{
		$bResult = false;
		try
		{
			$aResultChannels = $this->oEavManager->getEntities('CChannel',
				array('Login'),
				0,
				0,
				array('Login' => $oChannel->Login)
			);

			if ($aResultChannels)
			{
				foreach($aResultChannels as $oObject)
				{
					if ($oObject->EntityId !== $oChannel->EntityId)
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
	 * @param CChannel $oChannel
	 *
	 * @return bool
	 */
	public function createChannel(CChannel &$oChannel)
	{
		$bResult = false;
		try
		{
			if ($oChannel->validate())
			{
				if (!$this->isExists($oChannel))
				{
					$oChannel->Password = md5($oChannel->Login.mt_rand(1000, 9000).microtime(true));
					
					if (!$this->oEavManager->saveEntity($oChannel))
					{
						throw new \Aurora\System\Exceptions\ManagerException(Errs::ChannelsManager_ChannelCreateFailed);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ManagerException(Errs::ChannelsManager_ChannelAlreadyExists);
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
	 * @param CChannel $oChannel
	 *
	 * @return bool
	 */
	public function updateChannel(CChannel $oChannel)
	{
		$bResult = false;
		try
		{
			if ($oChannel->validate())
			{
				if (!$this->isExists($oChannel))
				{
					if (!$this->oEavManager->saveEntity($oChannel))
					{
						throw new \Aurora\System\Exceptions\ManagerException(Errs::ChannelsManager_ChannelUpdateFailed);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ManagerException(Errs::ChannelsManager_ChannelDoesNotExist);
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
	 * @param CChannel $oChannel
	 *
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function deleteChannel(CChannel $oChannel)
	{
		$bResult = false;
		try
		{
			/* @var $oTenantsApi CApiTenantsManager */
//			$oTenantsApi =\Aurora\System\Api::GetCoreManager('tenants');
			$oTenantsApi = $this->oModule->GetManager('tenants');
			
			if ($oTenantsApi && !$oTenantsApi->deleteTenantsByChannelId($oChannel->EntityId, true))
			{
				$oException = $oTenantsApi->GetLastException();
				if ($oException)
				{
					throw $oException;
				}
			}

			$bResult = $this->oEavManager->deleteEntity($oChannel->EntityId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $bResult;
	}
}