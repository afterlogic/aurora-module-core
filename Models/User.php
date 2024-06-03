<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Core\Models;

use Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\Tenant;

/**
 * The Core User class.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 * @property int    $Id             Object primary key
 * @property string $UUID           Unique identifier of the object
 * @property string $Name           User name
 * @property string $PublicId       User public ID, usually equals user's email address
 * @property int    $IdTenant       ID of the tetant a user relates to
 * @property bool   $IsDisabled     Reserved for future use
 * @property int    $IdSubscription @Deprecated since 9.7.0
 * @property int    $Role           User role from \Aurora\System\Enums\UserRole
 * @property \DateTime $LastLogin   Date at time of last login
 * @property string $LastLoginNow   @Deprecated since 9.7.0
 * @property int    $LoginsCount    Stores the number of times user has logged in
 * @property string $Language       Interface language set for this user
 * @property int    $TimeFormat     Time format set for this user
 * @property string $DateFormat     Date format set for this user
 * @property string $Question1      @Deprecated since 9.7.0
 * @property string $Question2      @Deprecated since 9.7.0
 * @property string $Answer1        @Deprecated since 9.7.0
 * @property string $Answer2        @Deprecated since 9.7.0
 * @property bool   $SipEnable      @Deprecated since 9.7.0
 * @property string $SipImpi        @Deprecated since 9.7.0
 * @property string $SipPassword    @Deprecated since 9.7.0
 * @property bool   $DesktopNotifications   @Deprecated since 9.7.0
 * @property string $Capa           @Deprecated since 9.7.0
 * @property string $CustomFields   @Deprecated since 9.7.0
 * @property bool   $FilesEnable    @Deprecated since 9.7.0
 * @property string $EmailNotification  @Deprecated since 9.7.0
 * @property string $PasswordResetHash  @Deprecated since 9.7.0
 * @property bool   $WriteSeparateLog   If set to true, a separate log file is recorded for the user
 * @property string $DefaultTimeZone    Default time zone set for this user
 * @property int    $TokensValidFromTimestamp   Timestamp the token is valid since
 * @property array  $Properties     Custom properties for use by other modules
 * @property string $Note           User text note
 * @property \Illuminate\Support\Carbon|null $CreatedAt
 * @property \Illuminate\Support\Carbon|null $UpdatedAt
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Aurora\Modules\Core\Models\Group> $Groups
 * @property-read int|null $groups_count
 * @property-read mixed $entity_id
 * @property-read Tenant|null $parent
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\User firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\User where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|User whereAnswer1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereAnswer2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCapa($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCustomFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDateFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDefaultTimeZone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDesktopNotifications($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmailNotification($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereFilesEnable($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereIdSubscription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereIdTenant($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\User whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereIsDisabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLastLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLastLoginNow($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLoginsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePasswordResetHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePublicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereQuestion1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereQuestion2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereSipEnable($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereSipImpi($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereSipPassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereTimeFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereTokensValidFromTimestamp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUUID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereWriteSeparateLog($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\User find(int|string $id, array|string $columns = ['*'])
 * @method static int count(string $columns = '*')
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\User findOrFail(int|string $id, mixed $id, Closure|array|string $columns = ['*'], Closure $callback = null)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Core\Models\User first(array|string $columns = ['*'])
 * @mixin \Eloquent
 */
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
        'Properties',
        'Note',
    ];

    /**
     * @var array
     * */
    protected $validationRules = [
        'TimeFormat' => 'in:0,1',
    ];

    /**
     * @var array
     */
    protected $validationMessages = [
        'TimeFormat.in' => 'User model validation error, rule: TimeFormat'
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
        'WriteSeparateLog' => 'boolean',
        'LastLogin' => 'datetime'
    ];

    protected $attributes = [
    ];

    /**
     * Checks if a user is a superadmin.
     *
     * return bool
     */
    public function isAdmin()
    {
        return $this->Id === -1;
    }

    /**
     * Checks if a user can act as a regular user.
     *
     * return bool
     */
    public function isNormalOrTenant()
    {
        return $this->Role === \Aurora\System\Enums\UserRole::NormalUser || $this->Role === \Aurora\System\Enums\UserRole::TenantAdmin;
    }

    /**
     * Returns array of groups the user is related to.
     *
     * return array
     */
    public function Groups()
    {
        return $this->belongsToMany(Group::class, 'core_group_user', 'UserId', 'GroupId');
    }
}
