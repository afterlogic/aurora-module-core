<?php

namespace Aurora\Modules\Core\Models;

use \Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\Tenant;

class User extends Model
{
    protected $table = 'core_users';

    protected $moduleName = 'Core';

    protected $parentType = Tenant::class;

    protected $parentKey = 'IdTenant';

    protected $parentInheritedAttributes = [
    ];

    protected $foreignModel = Tenant::class;
	protected $foreignModelIdColumn = 'IdTenant'; // Column that refers to an external table

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'UUID',
        'Name',
        'PublicId',
        'IdTenant',
        'IsDisabled',
        'IdSubscription',
        'Role',
        'DateCreated',
        'LastLogin',
        'LastLoginNow',
        'LoginsCount',
        'Language',
        'TimeFormat',
        'DateFormat',
        'Question1',
        'Question2',
        'Answer1',
        'Answer2',
        'SipEnable',
        'SipImpi',
        'SipPassword',
        'DesktopNotifications',
        'Capa',
        'CustomFields',
        'FilesEnable',
        'EmailNotification',
        'PasswordResetHash',
        'WriteSeparateLog',
        'DefaultTimeZone',
        'TokensValidFromTimestamp',
        'Properties'
    ];

    /**
    * The attributes that should be hidden for arrays.
    *
    * @var array
    */
    protected $hidden = [
    ];

    protected $casts = [
        'Properties' => 'array',
        'IsDisabled' => 'boolean',
        'SipEnable' => 'boolean',
        'DesktopNotifications' => 'boolean',
        'FilesEnable' => 'boolean',
        'WriteSeparateLog' => 'boolean'
    ];

    protected $attributes = [
    ];

    public function isAdmin()
	{
		return $this->Id === -1;
	}

	public function isNormalOrTenant()
	{
		return $this->Role === \Aurora\System\Enums\UserRole::NormalUser || $this->Role === \Aurora\System\Enums\UserRole::TenantAdmin;
	}
}