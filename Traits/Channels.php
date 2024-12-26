<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Traits;

use Aurora\Api;
use Aurora\Modules\Core\Models\Channel;
use Aurora\System\Enums\UserRole;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Notifications;

/**
 * System module that provides core functionality such as User management, Tenants management.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Modules
 */
trait Channels
{
    /**
     * @return \Aurora\Modules\Core\Managers\Channels
     */
    public function getChannelsManager()
    {
        if ($this->oChannelsManager === null) {
            $this->oChannelsManager = new \Aurora\Modules\Core\Managers\Channels($this);
        }

        return $this->oChannelsManager;
    }

     /**
     * Creates channel with specified login and description.
     *
     * @param string $Login New channel login.
     * @param string $Description New channel description.
     * @return int New channel identifier.
     * @throws ApiException
     */
    public function CreateChannel($Login, $Description = '')
    {
        $mResult = -1;
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        $mResult = false;

        $Login = \trim($Login);
        if ($Login !== '') {
            $oChannel = new Channel();

            $oChannel->Login = $Login;

            if ($Description !== '') {
                $oChannel->Description = $Description;
            }

            if ($this->getChannelsManager()->createChannel($oChannel)) {
                $mResult = $oChannel->Id;
            }
        } else {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        return $mResult;
    }

    /**
     * Updates channel.
     *
     * @param int $ChannelId Channel identifier.
     * @param string $Login New login for channel.
     * @param string $Description New description for channel.
     * @return bool
     * @throws ApiException
     */
    public function UpdateChannel($ChannelId, $Login = '', $Description = '')
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        if ($ChannelId > 0) {
            $oChannel = $this->getChannelsManager()->getChannelById($ChannelId);

            if ($oChannel) {
                $Login = \trim($Login);
                if (!empty($Login)) {
                    $oChannel->Login = $Login;
                }
                if (!empty($Description)) {
                    $oChannel->Description = $Description;
                }

                return $this->getChannelsManager()->updateChannel($oChannel);
            }
        } else {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        return false;
    }

    /**
     * Deletes channel.
     *
     * @param int $ChannelId Identifier of channel to delete.
     * @return bool
     * @throws ApiException
     */
    public function DeleteChannel($ChannelId)
    {
        Api::checkUserRoleIsAtLeast(UserRole::SuperAdmin);

        if ($ChannelId > 0) {
            $oChannel = $this->getChannelsManager()->getChannelById($ChannelId);

            if ($oChannel) {
                return $this->getChannelsManager()->deleteChannel($oChannel);
            }
        } else {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'InvalidInputParameter');
        }

        return false;
    }
}