<?php

namespace Aurora\Modules\Core\Models;

use \Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\Tenant;
use Aurora\Modules\Core\Module as CoreModule;

class Group extends Model
{
    protected $table = 'core_groups';
    protected $moduleName = 'Core';
    
    protected $foreignModel = Tenant::class;
	protected $foreignModelIdColumn = 'IdTenant'; // Column that refers to an external table

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

    public function Users()
	{
		return $this->belongsToMany(User::class, 'core_group_user', 'GroupId', 'UserId');
	}

    public function getName()
    {
        return $this->IsAll ? CoreModule::getInstance()->i18N('LABEL_ALL_USERS_GROUP') : $this->Name;
    }
}
