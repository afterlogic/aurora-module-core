<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core;

use Aurora\Api;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Notifications;
use Aurora\System\Enums\UserRole;

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
class Entries extends \Aurora\System\Module\AbstractEntries
{
    public function __construct(Module $module)
    {
        parent::__construct($module);

        $this->entries = [
            'api' => [$this, 'EntryApi'],
            'mobile' => [$this, 'EntryMobile'],
            'file-cache' => [$this, 'EntryFileCache']
        ];
    }

    public static function GetName()
    {
        return Module::GetName();
    }

    /**
     *
     * @return mixed
     */
    private function getUploadData()
    {
        $mResult = false;
        $oFile = null;
        if (count($_FILES) > 0) {
            $oFile = current($_FILES);
        }
        if (isset($oFile, $oFile['name'], $oFile['tmp_name'], $oFile['size'], $oFile['type'])) {
            $iError = (isset($oFile['error'])) ? (int) $oFile['error'] : UPLOAD_ERR_OK;
            $mResult = (UPLOAD_ERR_OK === $iError) ? $oFile : false;
        }

        return $mResult;
    }

    /**
     *
     * @return string
     * @throws ApiException
     */
    public function EntryApi()
    {
        @ob_start();

        if (!is_writable(Api::DataPath())) {
            throw new ApiException(Notifications::SystemNotConfigured, null, 'Check the write permission of the data folder');
        }

        $aResponseItem = null;
        $sModule = $this->module->oHttp->GetPost('Module', null);
        $sMethod = $this->module->oHttp->GetPost('Method', null);
        $sParameters = $this->module->oHttp->GetPost('Parameters', null);
        $sFormat = $this->module->oHttp->GetPost('Format', null);
        $sTenantName = $this->module->oHttp->GetPost('TenantName', null);

        if (isset($sModule, $sMethod)) {
            $oModule = Api::GetModule($sModule);
            if ($oModule instanceof \Aurora\System\Module\AbstractModule) {
                try {
                    Api::Log(" ");
                    Api::Log(" ===== API: " . $sModule . '::' . $sMethod);

                    Api::validateAuthToken();

                    if (!empty($sMethod)) {
                        Api::setTenantName($sTenantName);

                        $aParameters = [];
                        if (isset($sParameters) && \is_string($sParameters) && !empty($sParameters)) {
                            $aParameters = @\json_decode($sParameters, true);

                            if (json_last_error() !== JSON_ERROR_NONE) {
                                throw new ApiException(
                                    Notifications::InvalidInputParameter,
                                    null,
                                    'InvalidInputParameter'
                                );
                            }

                            if (!\is_array($aParameters)) {
                                $aParameters = array($aParameters);
                            }
                        }

                        $mUploadData = $this->getUploadData();
                        if (\is_array($mUploadData)) {
                            $aParameters['UploadData'] = $mUploadData;
                        }

                        $oModule->CallMethod(
                            $sMethod,
                            $aParameters,
                            true
                        );

                        $oLastException = Api::GetModuleManager()->GetLastException();
                        if (isset($oLastException)) {
                            throw $oLastException;
                        }

                        $aResponseItem = $oModule->DefaultResponse(
                            $sMethod,
                            Api::GetModuleManager()->GetResults()
                        );
                    }

                    if (!\is_array($aResponseItem)) {
                        throw new ApiException(
                            Notifications::UnknownError,
                            null,
                            'UnknownError'
                        );
                    }
                } catch (\Exception $oException) {
                    Api::LogException($oException);

                    $aAdditionalParams = null;
                    if ($oException instanceof ApiException) {
                        if (!$oException->GetModule()) {
                            $oException = new ApiException(
                                $oException->getCode(),
                                $oException->getPrevious(),
                                $oException->getMessage(),
                                $oException->GetObjectParams(),
                                $oModule
                            );
                        }
                        $aAdditionalParams = $oException->GetObjectParams();
                    }

                    $aResponseItem = $oModule->ExceptionResponse(
                        $sMethod,
                        $oException,
                        $aAdditionalParams
                    );
                }
            } else {
                $oException = new ApiException(
                    Notifications::ModuleNotFound,
                    null,
                    'Module not found'
                );
                $aResponseItem = $this->module->ExceptionResponse(
                    $sMethod,
                    $oException
                );
            }
        } else {
            $oException = new ApiException(
                Notifications::InvalidInputParameter,
                null,
                'Invalid input parameter'
            );
            $aResponseItem = $this->module->ExceptionResponse(
                $sMethod,
                $oException
            );
        }

        if (isset($aResponseItem['Parameters'])) {
            unset($aResponseItem['Parameters']);
        }

        return \Aurora\System\Managers\Response::GetJsonFromObject($sFormat, $aResponseItem);
    }

    /**
     * @ignore
     */
    public function EntryMobile()
    {
        $oApiIntegrator = $this->module->getIntegratorManager();
        $oApiIntegrator->setMobile(true);

        Api::Location('./');
    }

    public function EntryFileCache()
    {
        Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $sRawKey = \Aurora\System\Router::getItemByIndex(1, '');
        $sAction = \Aurora\System\Router::getItemByIndex(2, '');
        $aValues = Api::DecodeKeyValues($sRawKey);

        $bDownload = true;
        $bThumbnail = false;

        switch ($sAction) {
            case 'view':
                $bDownload = false;
                $bThumbnail = false;
                break;
            case 'thumb':
                $bDownload = false;
                $bThumbnail = true;
                break;
            default:
                $bDownload = true;
                $bThumbnail = false;
                break;
        }

        $iUserId = (isset($aValues['UserId'])) ? $aValues['UserId'] : 0;

        if (isset($aValues['TempFile'], $aValues['TempName'], $aValues['Name'])) {
            $sModule = isset($aValues['Module']) && !empty($aValues['Module']) ? $aValues['Module'] : 'System';
            $sUUID = Api::getUserUUIDById($iUserId);
            $oApiFileCache = new \Aurora\System\Managers\Filecache();
            $mResult = $oApiFileCache->getFile($sUUID, $aValues['TempName'], '', $sModule);

            if (is_resource($mResult)) {
                $sFileName = $aValues['Name'];
                $sContentType = (empty($sFileName)) ? 'text/plain' : \MailSo\Base\Utils::MimeContentType($sFileName);
                $sFileName = \Aurora\System\Utils::clearFileName($sFileName, $sContentType);

                \Aurora\System\Utils::OutputFileResource($sUUID, $sContentType, $sFileName, $mResult, $bThumbnail, $bDownload);
            }
        }
    }
}
