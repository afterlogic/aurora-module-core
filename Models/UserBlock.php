<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Models;

use Aurora\System\Classes\Model;

/**
 * The Core UserBlock class.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 * @property int    $Id                Object primary key
 * @property int    $UserId            User ID of the user blocked
 * @property string $Email             Public ID of the user blocked
 * @property string $IpAddress         IP address recorded for the user block
 * @property int    $ErrorLoginsCount  Number of failed login attempts
 * @property int    $Time              Timestamp when user block added
 * @property array  $Properties        Custom properties for use by other modules
 * @property \Illuminate\Support\Carbon|null $CreatedAt
 * @property \Illuminate\Support\Carbon|null $UpdatedAt
 * @property-read mixed $entity_id
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\UserBlock firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|UserBlock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserBlock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserBlock query()
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\UserBlock where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|UserBlock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBlock whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBlock whereErrorLoginsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBlock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\UserBlock whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBlock whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBlock whereTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBlock whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserBlock whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\UserBlock find(int|string $id, array|string $columns = ['*'])
 * @method static int count(string $columns = '*')
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\UserBlock findOrFail(int|string $id, mixed $id, Closure|array|string $columns = ['*'], Closure $callback = null)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\UserBlock first(array|string $columns = ['*'])
 * @mixin \Eloquent
 */
class UserBlock extends Model
{
    protected $table = 'core_user_blocks';
    protected $moduleName = 'Core';

    protected $foreignModel = User::class;
    protected $foreignModelIdColumn = 'UserId'; // Column that refers to an external table

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'UserId',
        'Email',
        'IpAddress',
        'ErrorLoginsCount',
        'Time',
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
}
