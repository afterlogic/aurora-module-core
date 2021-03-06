<?php

namespace Aurora\Modules\Core\Models;

use \Aurora\System\Classes\Model;

class Tenant extends Model
{
    protected $moduleName = 'Core';

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