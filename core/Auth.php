<?php
declare(strict_types=1);
namespace App;

final class Auth
{
    private static ?array $user = null;
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { return; }
        ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
        session_name('membership_session');
        session_set_cookie_params(['lifetime' => 0, 'path' => Config::basePath(), 'secure' => strtolower((string)parse_url(Config::baseUrl(), PHP_URL_SCHEME)) === 'https', 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
        $idle = max(5, (int)Config::get('SESSION_IDLE_MINUTES', '30')) * 60;
        $absolute=max(15,min(1440,(int)Config::get('SESSION_ABSOLUTE_MINUTES','480')))*60;
        if(isset($_SESSION['user_id'])&&(!isset($_SESSION['authenticated_at'])||time()-(int)$_SESSION['authenticated_at']>=$absolute)){self::logout('absolute_expired');}
        elseif (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $idle) { self::logout('idle_expired'); }
        if(isset($_SESSION['user_id'])&&time()-(int)($_SESSION['session_renewed_at']??0)>=900){session_regenerate_id(true);$_SESSION['session_renewed_at']=time();}
        $_SESSION['last_activity'] = time();
    }
    public static function user(): ?array
    {
        if (self::$user !== null) { return self::$user; }
        if (!isset($_SESSION['user_id'])) { return null; }
        $user = Database::one('SELECT id,name,email,role,is_active,session_version,last_login_at FROM app_users WHERE id=?', [$_SESSION['user_id']]);
        if (!$user || !$user['is_active'] || (int)$user['session_version'] !== (int)($_SESSION['session_version'] ?? 0) || !\App\Security\StaffIdentity::canSignIn((int)$user['id'])) { self::logout('session_revoked'); return null; }
        return self::$user = $user;
    }
    public static function login(string $email, string $password): bool
    {
        $email = mb_strtolower(trim($email));
        $keys = [hash_hmac('sha256', 'email:' . $email, Config::key(), true), hash_hmac('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'local'), Config::key(), true)];
        foreach ($keys as $key) {
            $blocked = Database::one('SELECT locked_until FROM app_login_limits WHERE attempt_key=?', [$key]);
            if ($blocked && $blocked['locked_until'] && strtotime($blocked['locked_until']) > time()) { \App\Security\SessionSecurity::event('auth.sign_in_blocked');throw new \InvalidArgumentException('Too many sign-in attempts. Please try again in 15 minutes.'); }
        }
        $user = Database::one('SELECT * FROM app_users WHERE email=?', [$email]);
        // A fixed valid hash keeps an unknown account on the password-check path.
        $dummy = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $verified = strlen($password)<=128&&password_verify($password, $user['password_hash'] ?? $dummy);
        if (!$verified || !$user || !$user['is_active'] || !\App\Security\StaffIdentity::canSignIn((int)$user['id'])) {
            foreach ($keys as $key) {
                Database::execute('INSERT INTO app_login_limits(attempt_key,attempt_count,window_started_at) VALUES(?,1,UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE attempt_count=IF(window_started_at<UTC_TIMESTAMP(6)-INTERVAL 15 MINUTE,1,LEAST(attempt_count+1,65535)),window_started_at=IF(window_started_at<UTC_TIMESTAMP(6)-INTERVAL 15 MINUTE,UTC_TIMESTAMP(6),window_started_at),locked_until=IF(attempt_count>=5,UTC_TIMESTAMP(6)+INTERVAL 15 MINUTE,NULL)', [$key]);
            }
            \App\Security\SessionSecurity::event('auth.sign_in_failed');return false;
        }
        Database::execute('DELETE FROM app_login_limits WHERE attempt_key=?', [$keys[0]]);
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) { Database::execute('UPDATE app_users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_ARGON2ID), $user['id']]); }
        session_regenerate_id(true);
        CsvPreviewService::clear();$_SESSION=[];
        $_SESSION['user_id'] = $user['id']; $_SESSION['session_version'] = $user['session_version']; $_SESSION['_token'] = bin2hex(random_bytes(32));
        $_SESSION['authenticated_at']=time();$_SESSION['last_activity']=time();$_SESSION['session_renewed_at']=time();
        Database::execute('UPDATE app_users SET last_login_at=UTC_TIMESTAMP(6) WHERE id=?', [$user['id']]);
        self::audit('auth.signed_in', 'app_user', (int)$user['id'], [], (int)$user['id']);
        self::$user = null;
        return true;
    }
    public static function logout(string $reason='signed_out'): void
    {
        if(isset($_SESSION['user_id'])){\App\Security\SessionSecurity::event('auth.'.$reason,(int)$_SESSION['user_id']);}
        CsvPreviewService::clear();
        $_SESSION = []; self::$user = null;
        if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
    }
    public static function csrf(): string { return $_SESSION['_token'] ??= bin2hex(random_bytes(32)); }
    public static function validateCsrf(): void
    {
        if (!is_string($_POST['_token'] ?? null) || !hash_equals(self::csrf(), $_POST['_token'])) { \App\Security\SessionSecurity::event('security.csrf_rejected',isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null);throw new HttpException(419, 'Your session form expired. Refresh the page and try again.'); }
    }
    public static function requireLogin(): array { return self::user() ?? redirect('login'); }
    public static function requireRole(array $roles): array
    {
        $user = self::requireLogin();
        if (\App\Security\ExecutiveAccess::restricted() && !in_array('viewer', $roles, true)) { throw new HttpException(403, 'Executive access does not permit record management.'); }
        if (!in_array($user['role'], $roles, true)) { throw new HttpException(403, 'You do not have permission to perform this action.'); }
        return $user;
    }
    public static function admin(): bool { return !\App\Security\ExecutiveAccess::restricted() && self::user()['role'] === 'admin'; }
    public static function canManage(): bool { return !\App\Security\ExecutiveAccess::restricted() && in_array(self::user()['role'], ['admin','registrar'], true); }
    public static function forgetCachedUser(): void {self::$user=null;}
    public static function audit(string $action, string $entity, ?int $id = null, array $summary = [], ?int $actor = null): void
    {
        Database::execute('INSERT INTO audit_events(event_uuid,actor_user_id,action_code,entity_type,entity_id,ip_address,change_summary) VALUES(UUID(),?,?,?,?,?,?)', [$actor ?? self::user()['id'] ?? null, $action, $entity, $id, inet_pton($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') ?: null, json_encode($summary, JSON_THROW_ON_ERROR)]);
    }
}

