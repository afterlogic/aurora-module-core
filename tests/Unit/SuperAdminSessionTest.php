<?php

namespace Aurora\Modules\Core\Tests\Unit;

use Aurora\Modules\Core\Managers\Users;
use Aurora\Modules\Core\Models\User;
use Aurora\Modules\Core\Module as Core;
use Aurora\Modules\TwoFactorAuth\Module as TwoFactorAuth;
use Aurora\System\Api;
use Aurora\System\Application;
use Aurora\System\Enums\UserRole;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Managers\Integrator;
use Aurora\System\Module\Manager;
use Aurora\System\Notifications;
use Aurora\System\UserSession;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SuperAdminSessionTest extends TestCase
{
    private $sessions;
    private $settings;

    protected function setUp(): void
    {
        $db = new Capsule();
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'test_']);
        $db->setAsGlobal();
        $db->bootEloquent();
        require_once dirname(__DIR__, 4) . '/system/Migrations/2026_11_25_000000_create_core_superadmin_sessions_table.php';
        (new \CreateCoreSuperadminSessionsTable())->up();

        $this->settings = new class () {
            public $DBName = 'test';
            public $StoreAuthTokenInDB = true;
            public $AuthTokenExpirationLifetimeDays = 3;
            public $EnableEventLogging = false;
            public $EnableLogging = false;
            public $SuperAdminAuthTokenExpirationLifetimeDays = 2;

            public function GetValue($key, $default)
            {
                return $this->$key ?? $default;
            }
        };
        (new \ReflectionProperty(Api::class, 'oSettings'))->setValue(null, $this->settings);
        (new \ReflectionProperty(Api::class, 'usersCache'))->setValue(null, [
            -1 => (object) ['IsDisabled' => false], 1 => (object) ['IsDisabled' => false],
        ]);
        (new \ReflectionProperty(Api::class, 'oModuleManager'))->setValue(null, $this->createMock(Manager::class));
        Api::$sEncryptionKey = bin2hex(random_bytes(32));
        $this->sessions = new UserSession();
    }

    public function testIndependentLoginsRefreshAndLogout(): void
    {
        $start = time();
        $data = ['id' => '-1', 'token' => 'admin'];
        $first = $this->sessions->Set($data, $start, $start + 10);
        $second = $this->sessions->Set($data, $start);
        $expiry = $this->sessions->Get($first)['@expire'];
        self::assertGreaterThanOrEqual($start + 2 * 86400, $expiry);
        self::assertLessThanOrEqual(time() + 2 * 86400, $expiry);
        self::assertSame(2, Capsule::table('core_superadmin_sessions')->count());
        $integrator = Integrator::getInstance();
        self::assertTrue($integrator->getAuthenticatedUserInfo($first)['isAdmin']);
        $refreshed = $this->sessions->UpdateTimestamp($first, time());
        self::assertSame($expiry, $this->sessions->Get($refreshed)['@expire']);
        $this->sessions->Delete($refreshed);
        $this->sessions->Delete($refreshed);
        self::assertFalse($this->sessions->Get($first));
        self::assertFalse($this->sessions->Get($refreshed));
        self::assertFalse($integrator->getAuthenticatedUserInfo($first)['isAdmin']);
        self::assertTrue($integrator->getAuthenticatedUserInfo($second)['isAdmin']);
    }

    public function testExpiryAndLegacyTokens(): void
    {
        $this->settings->StoreAuthTokenInDB = false;
        $this->settings->SuperAdminAuthTokenExpirationLifetimeDays = 0;
        $data = ['id' => '-1', 'token' => 'admin'];
        $legacy = Api::EncodeKeyValues($data + ['@ver' => UserSession::TOKEN_VERSION, '@expire' => 0]);
        self::assertFalse($this->sessions->Get($legacy));
        $start = time();
        $token = $this->sessions->Set($data, $start);
        self::assertGreaterThanOrEqual($start + 86400, $this->sessions->Get($token)['@expire']);
        Capsule::table('core_superadmin_sessions')->update(['ExpiresAt' => time()]);
        self::assertFalse($this->sessions->Get($token));
        self::assertFalse($this->sessions->UpdateTimestamp($token));
        $this->sessions->Set($data, time());
        self::assertSame(1, Capsule::table('core_superadmin_sessions')->count());
        $regular = $this->sessions->Set(['id' => 1, 'token' => 'auth'], $start);
        self::assertSame(0, $this->sessions->Get($regular)['@expire']);
        self::assertSame(1, Capsule::table('core_superadmin_sessions')->count());
    }

    public function testInitialSetupKeepsWorkingWhileDatabaseIsConfigured(): void
    {
        $this->settings->DBName = '';
        if (!defined('AU_API_SESSION_WEBMAIL_NAME')) {
            define('AU_API_SESSION_WEBMAIL_NAME', 'auroratest');
        }
        $_SERVER['HTTPS'] = 'on';
        $token = $this->sessions->Set(['id' => '-1', 'token' => 'admin'], time());
        $this->settings->DBName = 'configured';
        self::assertIsArray($this->sessions->Get($token));
        $this->sessions->Delete($token);
        self::assertFalse($this->sessions->Get($token));
        self::assertSame(0, Capsule::table('core_superadmin_sessions')->count());
        \Aurora\System\Session::Destroy();
    }

    public function testMissingTableDoesNotIssueAnUntrackedSession(): void
    {
        (new \CreateCoreSuperadminSessionsTable())->down();
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->sessions->Set(['id' => '-1', 'token' => 'admin'], time());
    }

    public static function userSessionModes(): array
    {
        return [
            'stateless' => [false, false],
            'devices' => [false, true],
            'legacy database' => [true, false],
            'legacy database and devices' => [true, true],
        ];
    }

    /** @dataProvider userSessionModes */
    public function testRegularAndAdminLoginsWithUserSettings(bool $store, bool $devices): void
    {
        $this->settings->StoreAuthTokenInDB = $store;
        Capsule::connection()->statement('CREATE TABLE test_core_auth_tokens (
            Id INTEGER PRIMARY KEY, UserId INTEGER, AccountId INTEGER, AccountType TEXT,
            TokenHash TEXT, LastUsageDateTime INTEGER, CreatedAt TEXT, UpdatedAt TEXT)');
        Capsule::connection()->statement('CREATE TABLE test_security_used_devices (
            Id INTEGER PRIMARY KEY, UserId INTEGER, DeviceId TEXT, DeviceName TEXT, DeviceIP TEXT,
            CreationDateTime INTEGER, LastUsageDateTime INTEGER, TrustTillDateTime INTEGER,
            AuthTokenHash TEXT, CreatedAt TEXT, UpdatedAt TEXT)');

        $user = new User();
        $user->Id = 1;
        $user->Role = UserRole::NormalUser;
        $admin = new User();
        $admin->Id = -1;
        $admin->Role = UserRole::SuperAdmin;
        (new \ReflectionProperty(Api::class, 'usersCache'))->setValue(null, [1 => $user, -1 => $admin]);
        $core = (new \ReflectionClass(Core::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Core::class, 'oUsersManager'))->setValue($core, $this->createMock(Users::class));
        $twoFactor = (new \ReflectionClass(TwoFactorAuth::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(TwoFactorAuth::class, 'oModuleSettings'))->setValue($twoFactor, (object) ['AllowUsedDevices' => $devices]);
        (new \ReflectionProperty(TwoFactorAuth::class, 'oHttp'))->setValue($twoFactor, \MailSo\Base\Http::SingletonInstance());
        if (!defined('AU_API_SESSION_WEBMAIL_NAME')) {
            define('AU_API_SESSION_WEBMAIL_NAME', 'auroratest');
        }
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_CLIENT'] = 'WebClient';
        unset($_SERVER['SERVER_NAME']);
        $regular = ['token' => 'auth', 'id' => 1, 'account' => 5, 'account_type' => 'TestAccount'];
        $login = function (array $data, string $device) use ($core, $twoFactor): string {
            $_SERVER['HTTP_X_DEVICEID'] = $device;
            $response = $core->SetAuthDataAndGetAuthToken($data);
            $twoFactor->onAfterSetAuthDataAndGetAuthToken($data, $response);
            return $response[Application::AUTH_TOKEN_KEY];
        };
        $start = time();
        $first = $login($regular, 'desktop');
        $second = $login($regular, 'phone');
        $adminToken = $login(['id' => '-1', 'token' => 'admin'], '');
        self::assertSame($devices ? 2 : 0, Capsule::table('security_used_devices')->count());
        self::assertSame($store ? 1 : 0, Capsule::table('core_auth_tokens')->count());
        self::assertSame(1, Capsule::table('core_superadmin_sessions')->count());
        $expiry = $this->sessions->Get($second)['@expire'];
        self::assertGreaterThanOrEqual($start + 3 * 86400, $expiry);
        self::assertLessThanOrEqual(time() + 3 * 86400, $expiry);
        self::assertTrue($this->acceptsRequest($twoFactor, $adminToken, '', -1));
        self::assertSame(!$store, $this->acceptsRequest($twoFactor, $first, 'desktop', 1));
        self::assertSame(!$devices, $this->acceptsRequest($twoFactor, $second, 'desktop', 1));
        self::assertTrue($this->acceptsRequest($twoFactor, $second, 'phone', 1));
        $result = null;
        $twoFactor->onBeforeLogout([], $result);
        self::assertTrue($core->Logout());
        self::assertSame(!$store && !$devices, $this->acceptsRequest($twoFactor, $second, 'phone', 1));
        self::assertSame(!$store, $this->acceptsRequest($twoFactor, $first, 'desktop', 1));
        self::assertTrue($this->acceptsRequest($twoFactor, $adminToken, '', -1));
        self::assertTrue($core->Logout());
        self::assertFalse($this->acceptsRequest($twoFactor, $adminToken, '', -1));
        $expired = $this->sessions->Set($regular, $start - 90, $start - 1);
        self::assertFalse($this->acceptsRequest($twoFactor, $expired, 'desktop', 1));
        \Aurora\System\Session::Destroy();
    }

    private function acceptsRequest(TwoFactorAuth $module, string $token, string $device, int $id): bool
    {
        // Each API request starts with fresh authentication and database lookup caches.
        UserSession::$aTokensCache = [];
        (new \ReflectionProperty(Api::class, 'aUserSession'))->setValue(null, []);
        (new \ReflectionProperty(Api::class, 'oAuthenticatedUser'))->setValue(null, null);
        $_COOKIE[Application::AUTH_TOKEN_KEY] = $token;
        $_SERVER['HTTP_X_DEVICEID'] = $device;
        $args = ['EntryName' => 'api', 'Module' => 'Core', 'Method' => 'GetSettings'];
        $result = null;
        try {
            Api::validateAuthToken();
            $user = Api::getAuthenticatedUser($token, true);
            $module->onBeforeRunEntry($args, $result);
            return (int) $user->Id === $id;
        } catch (ApiException $e) {
            self::assertContains($e->getCode(), [Notifications::InvalidToken, Notifications::UntrustedDevice]);
            return false;
        }
    }
}
