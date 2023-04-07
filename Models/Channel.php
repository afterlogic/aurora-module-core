<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Models;

use Aurora\System\Classes\Model;

/**
 * The Core Channel class.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 * @property int    $Id            Object primary key
 * @property string $Login	       Channel login
 * @property string $Password      Channel password
 * @property string $Description   Channel description
 * @property array  $Properties    Custom properties for use by other modules
 * @property \Illuminate\Support\Carbon|null $CreatedAt
 * @property \Illuminate\Support\Carbon|null $UpdatedAt
 * @property-read mixed $entity_id
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Channel firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Channel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Channel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Channel query()
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Channel where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Channel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Channel whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Channel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Channel whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder|Channel whereLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Channel wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Channel whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Channel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Channel find(int|string $id, array|string $columns = ['*'])
 * @method static int count(string $columns = '*')
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Channel findOrFail(int|string $id, mixed $id, Closure|array|string $columns = ['*'], Closure $callback = null)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\Channel first(array|string $columns = ['*'])
 * @mixin \Eloquent
 */
class Channel extends Model
{
    protected $table = 'core_channels';
    protected $moduleName = 'Core';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'Login',
        'Password',
        'Description',
        'Properties'
    ];

    public $timestamps = [
        'UpdatedAt',
        'CreatedAt',
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
