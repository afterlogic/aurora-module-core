<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core;

use Aurora\Api;

/**
 * System module that provides core functionality such as User management, Tenants management.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Module $module
 *
 * @package Modules
 */
class Subscriptions extends \Aurora\System\Module\AbstractSubscriptions
{
    public function __construct(Module $module)
    {
        parent::__construct($module);

        $this->callbacks = [
            ['CreateAccount', [$this, 'onCreateAccount'], 100],
            ['System::RunEntry::before', [$this, 'onBeforeRunEntry'], 100]
        ];
    }

    public static function GetName()
    {
        return Module::GetName();
    }

    /**
     * Is called by CreateAccount event. Finds or creates and returns User for new account.
     *
     * @ignore
     * @param array $Args {
     *		*int* **UserId** Identifier of existing user.
     *		*int* **TenantId** Identifier of tenant for creating new user in it.
     *		*int* **$PublicId** New user name.
     * }
     * @param Models\User $Result
     */
    public function onCreateAccount(&$Args, &$Result)
    {
        $oUser = null;

        if (isset($Args['UserId']) && (int)$Args['UserId'] > 0) {
            $oUser = $this->module->getUser($Args['UserId']);
        } else {
            $Email = (isset($Args['Email'])) ? $Args['Email'] : '';
            $PublicId = (isset($Args['PublicId'])) ? $Args['PublicId'] : '';
            $sPublicId = null;
            if (!empty($PublicId)) {
                $sPublicId = $PublicId;
            } elseif (!empty($Email)) {
                $sPublicId = $Email;
            }
            if (!empty($sPublicId)) {
                $oUser = $this->module->getUsersManager()->getUserByPublicId($sPublicId);
            }
            if (!isset($oUser)) {
                $bPrevState = Api::skipCheckUserRole(true);
                $iUserId = Module::Decorator()->CreateUser(isset($Args['TenantId']) ? (int) $Args['TenantId'] : 0, $sPublicId);
                Api::skipCheckUserRole($bPrevState);
                $oUser = $this->module->getUsersManager()->getUser($iUserId);
            }

            if (isset($oUser) && isset($oUser->Id)) {
                $Args['UserId'] = $oUser->Id;
            }
        }

        $Result = $oUser;
    }

    public function onBeforeRunEntry($aArgs, &$mResult)
    {
        Api::removeOldLogs();

        return $this->redirectToHttps($aArgs['EntryName'], $mResult);
    }

    protected function redirectToHttps($sEntryName, $mResult)
    {
        $oSettings = &Api::GetSettings();
        if ($oSettings) {
            $bRedirectToHttps = $oSettings->RedirectToHttps;

            $bHttps = Api::isHttps();
            if ($bRedirectToHttps && !$bHttps) {
                if (\strtolower($sEntryName) !== 'api') {
                    \header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                } else {
                    $mResult = [
                        'ErrorCode' => 110
                    ];
                    return true;
                }
            }
        }
    }
}
