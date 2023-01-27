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
 * 
 * @property int    $Id             Object primary key
 * @property int    $TenantId       Tenant ID
 * @property string $Name           Tenant name
 * @property bool   $IsAll          Defines whether the group represents all the users
 * @property array  $Properties		Custom properties for use by other modules   		
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
