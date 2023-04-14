<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Models;

use Aurora\System\Classes\Model;

/**
 * The Core GroupUser class.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 * @property int    $Id             Object primary key
 * @property int    $GroupId        Group ID
 * @property int    $UserId         User ID
 * @property-read mixed $entity_id
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\GroupUser firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|GroupUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupUser query()
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\GroupUser where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|GroupUser whereGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\GroupUser whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupUser whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\GroupUser find(int|string $id, array|string $columns = ['*'])
 * @method static int count(string $columns = '*')
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\GroupUser findOrFail(int|string $id, mixed $id, Closure|array|string $columns = ['*'], Closure $callback = null)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\GroupUser first(array|string $columns = ['*'])
 * @mixin \Eloquent
 */
class GroupUser extends Model
{
    public $table = 'core_group_user';
    protected $foreignModel = Group::class;
    protected $foreignModelIdColumn = 'GroupId'; // Column that refers to an external table

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'GroupId',
        'UserId'
    ];
}
