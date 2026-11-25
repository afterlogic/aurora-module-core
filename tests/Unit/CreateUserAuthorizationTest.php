<?php

namespace Aurora\Modules\Core\Tests\Unit;

use Aurora\Modules\Core\Managers\Tenants;
use Aurora\Modules\Core\Models\User;
use Aurora\Modules\Core\Module;
use Aurora\System\Api;
use Aurora\System\Enums\UserRole;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Notifications;
use PHPUnit\Framework\TestCase;

class CreateUserAuthorizationTest extends TestCase
{
    /** @dataProvider roleCases */
    public function testAuthorizationBeforeTenantLookup($actorRole, $targetTenant, $requestedRole, $allowed): void
    {
        $actor = new User();
        $actor->Role = $actorRole;
        $actor->IdTenant = 1;

        $tenants = $this->createMock(Tenants::class);
        $tenants->expects($allowed ? $this->once() : $this->never())
            ->method('getTenantById')->with($targetTenant)->willReturn(null);
        $module = $this->getMockBuilder(Module::class)->disableOriginalConstructor()
            ->onlyMethods(['getTenantsManager', 'getUsersManager'])->getMock();
        $module->method('getTenantsManager')->willReturn($tenants);
        $module->expects($this->never())->method('getUsersManager');

        $authenticatedUser = new \ReflectionProperty(Api::class, 'oAuthenticatedUser');
        $previousUser = $authenticatedUser->getValue();
        $authenticatedUser->setValue(null, $actor);
        try {
            $this->expectException(ApiException::class);
            $this->expectExceptionCode($allowed ? Notifications::InvalidInputParameter : Notifications::AccessDenied);
            $module->CreateUser($targetTenant, 'new-user', $requestedRole);
        } finally {
            $authenticatedUser->setValue(null, $previousUser);
        }
    }

    public static function roleCases(): array
    {
        return [
            'regular user in own tenant' => [UserRole::TenantAdmin, 1, UserRole::NormalUser, true],
            'regular role as string' => [UserRole::TenantAdmin, 1, (string) UserRole::NormalUser, true],
            'regular user in another tenant' => [UserRole::TenantAdmin, 2, UserRole::NormalUser, false],
            'tenant administrator role' => [UserRole::TenantAdmin, 1, UserRole::TenantAdmin, false],
            'global role' => [UserRole::TenantAdmin, 1, UserRole::SuperAdmin, false],
            'global role as string' => [UserRole::TenantAdmin, 1, (string) UserRole::SuperAdmin, false],
            'global administrator assigning global role' => [UserRole::SuperAdmin, 2, UserRole::SuperAdmin, true],
        ];
    }
}
