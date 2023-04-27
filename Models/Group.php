<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Models;

use Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\Tenant;
use Aurora\Modules\Core\Module as CoreModule;

/**
 * The Core Group class.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 * @property int    $Id             Object primary key
 * @property int    $TenantId       Tenant ID
 * @property string $Name           Tenant name
 * @property bool   $IsAll          Defines whether the group represents all the users
 * @property array  $Properties		Custom properties for use by other modules
 * @property \Illuminate\Support\Carbon|null $CreatedAt
 * @property \Illuminate\Support\Carbon|null $UpdatedAt
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Aurora\Modules\Core\Models\User> $Users
 * @property-read int|null $users_count
 * @property-read mixed $entity_id
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Group firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Group newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Group newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Group query()
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Group where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Group whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereIsAll($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Group find(int|string $id, array|string $columns = ['*'])
 * @method static int count(string $columns = '*')
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Group findOrFail(int|string $id, mixed $id, Closure|array|string $columns = ['*'], Closure $callback = null)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Group first(array|string $columns = ['*'])
 * @mixin \Eloquent
 */
class Group extends Model
{
    protected $table = 'core_groups';
    protected $moduleName = 'Core';

    protected $foreignModel = Tenant::class;
    protected $foreignModelIdColumn = 'TenantId'; // Column that refers to an external table

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'TenantId',
        'Name',
        'IsAll',
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

    /**
     * Returns list of users which belong to this group
     *
     * return array
     */
    public function Users()
    {
        return $this->belongsToMany(User::class, 'core_group_user', 'GroupId', 'UserId');
    }

    /**
     * Returns a name of group, or special language constant if the group represents all the users
     *
     * return string
     */
    public function getName()
    {
        return $this->IsAll ? CoreModule::getInstance()->i18N('LABEL_ALL_USERS_GROUP') : $this->Name;
    }
}
