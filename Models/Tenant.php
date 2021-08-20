<?php

namespace Aurora\Modules\Core\Models;

use \Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\Channel;

class Tenant extends Model
{
    protected $table = 'core_tenants';
    protected $moduleName = 'Core';

    protected $foreignModel = Channel::class;
	protected $foreignModelIdColumn = 'IdChannel'; // Column that refers to an external table

    protected $parentType = \Aurora\System\Module\Settings::class;

    protected $parentInheritedAttributes = [
        'Files::UserSpaceLimitMb'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'IdChannel',
        'IsDisabled',
        'IsDefault',
        'Name',
        'Description',
        'WebDomain',
        'UserCountLimit',
        'Capa',
        'AllowChangeAdminEmail',
        'AllowChangeAdminPassword',
        'Expared',
        'PayUrl',
        'IsTrial',
        'LogoUrl',
        'CalendarNotificationEmailAccount',
        'InviteNotificationEmailAccount',
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
        'IsDefault' => 'boolean',
        'AllowChangeAdminEmail' => 'boolean',
        'AllowChangeAdminPassword' => 'boolean',
        'IsTrial' => 'boolean'
    ];

    protected $attributes = [
    ];

    protected $appends = [
        'EntityId'
    ];

    public function getEntityIdAttribute()
    {
        return $this->Id;
    }
}