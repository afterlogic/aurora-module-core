<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Models;

use Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\Channel;

/**
 * The Core Tenant class.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 * @property int    $Id                 Object primary key
 * @property int    $IdChannel          ID of the channel this tenant belongs to
 * @property bool   $IsDisabled         If set, the tenant is disabled
 * @property bool   $IsDefault          If set, it's a default tenant
 * @property string $Name               Tenant name
 * @property string $Description        Tenant description
 * @property string $WebDomain          Tenant web domain setting
 * @property int    $UserCountLimit     @Deprecated since 9.7.0
 * @property string $Capa               @Deprecated since 9.7.0
 * @property bool   $AllowChangeAdminEmail      @Deprecated since 9.7.0
 * @property bool   $AllowChangeAdminPassword   @Deprecated since 9.7.0
 * @property int    $Expared            @Deprecated since 9.7.0
 * @property string $PayUrl             @Deprecated since 9.7.0
 * @property bool   $IsTrial            @Deprecated since 9.7.0
 * @property string $LogoUrl            @Deprecated since 9.7.0
 * @property string $CalendarNotificationEmailAccount   @Deprecated since 9.7.0
 * @property string $InviteNotificationEmailAccount     @Deprecated since 9.7.0
 * @property array  $Properties         Custom properties for use by other modules
 * @property \Illuminate\Support\Carbon|null $CreatedAt
 * @property \Illuminate\Support\Carbon|null $UpdatedAt
 * @property-read mixed $entity_id
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Tenant firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant query()
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Tenant where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereAllowChangeAdminEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereAllowChangeAdminPassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereCalendarNotificationEmailAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereCapa($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereExpared($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereIdChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Tenant whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereInviteNotificationEmailAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereIsDisabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereIsTrial($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereLogoUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant wherePayUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereUserCountLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Tenant whereWebDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Tenant find(int|string $id, array|string $columns = ['*'])
 * @method static int count(string $columns = '*')
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Tenant findOrFail(int|string $id, mixed $id, Closure|array|string $columns = ['*'], Closure $callback = null)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Tenant first(array|string $columns = ['*'])
 * @mixin \Eloquent
 */
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

    /**
     * Returns tenant ID
     *
     * return int
     */
    public function getEntityIdAttribute()
    {
        return $this->Id;
    }
}
