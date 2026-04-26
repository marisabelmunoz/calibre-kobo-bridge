<?php
namespace CalibreOpds;

class Auth
{
    private const KEY = 'calibre_opds_ok';

    public static function check(): bool
    {
        return !empty($_SESSION[self::KEY]);
    }

     public static function checkApiKey(string $key): bool
    {
        $configKey = Config::get('API_KEY');
        if (empty($configKey)) {
            return false;
        }
        return hash_equals($configKey, $key);
    }
    
    
    public static function attempt(string $key): bool
    {
        if (hash_equals(Config::get('API_KEY'), trim($key))) {
            session_regenerate_id(true);
            $_SESSION[self::KEY] = true;
            return true;
        }
        return false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(): bool
    {
        $token = $_POST['_csrf'] ?? $_GET['_csrf'] ?? '';
        return !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }
}
