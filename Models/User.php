<?php

namespace Aurora\Modules\Core\Models;

use \Illuminate\Database\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    use \Aurora\System\ModelTrait;

    protected $primaryKey = 'Id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        'Name',
        'PublicId',
        'IdTenant',
        'IsDisabled',
        'IdSubscription',
        'Role',
        'DateCreated',
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
}