<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Managers;

use \Aurora\Modules\Core\Models\Channel;
use \Aurora\System\Enums\SortOrder;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Channels
 */
class Channels extends \Aurora\System\Managers\AbstractManager
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
	 * @param int $iOffset
	 * @param int $iLimit
	 * @param string $sOrderBy Default value is **Login**
	 * @param bool $iOrderType Default value is **\Aurora\System\Enums\SortOrder::ASC**
	 * @param string $sSearchDesc Default value is empty string
	 *
	 * @return array|false [Id => [Login, Description]]
	 */
	public function getChannelList($iOffset = 0, $iLimit = 0, $sOrderBy = 'Login', $iOrderType = SortOrder::ASC, $sSearchDesc = '')
	{
		if (!empty($sSearchDesc))
		{
			$query = Channel::where('Login', 'like', '%'.$sSearchDesc.'%');
		}
		else
		{
			$query = Channel::query();
		}
		if ($iOffset > 0) {
			$query = $query->offset($iOffset);
		}
		if ($iLimit > 0) {
			$query = $query->limit($iLimit);
		}
		try
		{
			$aResult = $query->orderBy($sOrderBy, $iOrderType === SortOrder::ASC ? 'asc' : 'desc')->get();
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
			$iResult = Channel::where('Login', 'like', '%'.$sSearchDesc.'%')->where('Description', 'like', '%'.$sSearchDesc.'%')->count();
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
	 * @return \Aurora\Modules\Core\Classes\Channel
	 */
	public function getChannelById($iChannelId)
	{
		$oChannel = null;
		try
		{
			$oChannel = Channel::find($iChannelId);
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
			$oChannel = Channel::firstWhere('Login', $sChannelLogin);


			if ($oChannel instanceOf Channel)
			{
				$iChannelId = $oChannel->Id;
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $iChannelId;
	}

	/**
	 * @param Aurora\Modules\Core\Models\Channel $oChannel
	 *
	 * @return bool
	 */
	public function isExists(Channel $oChannel)
	{
		$bResult = false;
		$oChannels = Channel::where('Login', $oChannel->Login)->get();

		foreach($oChannels as $oObject)
		{
			if ($oObject->Id !== $oChannel->Id)
			{
				$bResult = true;
				break;
			}
		}
		return $bResult;
	}

	/**
	 * @param Aurora\Modules\Core\Models\Channel $oChannel
	 *
	 * @return bool
	 */
	public function createChannel(Channel &$oChannel)
	{
		$bResult = false;
		try
		{
			if ($oChannel->validate())
			{
				if (!$this->isExists($oChannel))
				{
					$oChannel->Password = md5($oChannel->Login.mt_rand(1000, 9000).microtime(true));

					if (!$oChannel->save())
					{
						throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::ChannelsManager_ChannelCreateFailed);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::ChannelsManager_ChannelAlreadyExists);
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
	 * @param Aurora\Modules\Core\Models\Channel $oChannel
	 *
	 * @return bool
	 */
	public function updateChannel(Channel $oChannel)
	{
		$bResult = false;
		try
		{
			if ($oChannel->validate())
			{
				if (!$this->isExists($oChannel))
				{
					if (!$oChannel->save())
					{
						throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::ChannelsManager_ChannelUpdateFailed);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ManagerException(\Aurora\System\Exceptions\Errs::ChannelsManager_ChannelDoesNotExist);
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
	 * @param Aurora\Modules\Core\Models\Channel $oChannel
	 *
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function deleteChannel(\Aurora\Modules\Core\Models\Channel $oChannel)
	{
		$bResult = false;
		try
		{
			/* @var $oTenantsManager CApiTenantsManager */
			$oTenantsManager = new \Aurora\Modules\Core\Managers\Tenants($this->oModule);

			if ($oTenantsManager && !$oTenantsManager->deleteTenantsByChannelId($oChannel->Id, true))
			{
				$oException = $oTenantsManager->GetLastException();
				if ($oException)
				{
					throw $oException;
				}
			}
			$oChannel->delete();
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $bResult;
	}
}
