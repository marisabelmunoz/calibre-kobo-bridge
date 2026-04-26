<?php
namespace CalibreOpds;

class Config
{
    private static array $data = [];
    private static string $file = '';

    public static function path(): string
    {
        if (!self::$file) {
            self::$file = dirname(__DIR__) . '/data/config.json';
        }
        return self::$file;
    }

    public static function load(): bool
    {
        $path = self::path();
        if (!file_exists($path)) return false;

        $raw = json_decode((string)file_get_contents($path), true);
        if (!is_array($raw) || empty($raw['API_KEY'])) return false;

        self::$data = $raw;

        if (!empty($raw['nc_pass_enc'])) {
            self::$data['NC_PASS'] = self::decrypt($raw['nc_pass_enc'], $raw['API_KEY']);
        }

        return true;
    }

    public static function save(array $in): bool
    {
        $path = self::path();
        $dir  = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
            file_put_contents($dir . '/.htaccess', "Require all denied\n");
        }

        $store = [
            'NC_USER'       => trim($in['NC_USER']),
            'nc_pass_enc'   => self::encrypt(trim($in['NC_PASS']), trim($in['API_KEY'])),
            'API_KEY'       => trim($in['API_KEY']),
            'BOOKS_API_URL' => rtrim(trim($in['BOOKS_API_URL']), '/'),
            'OPDS_BASE'     => rtrim(trim($in['OPDS_BASE']), '/'),
            'READER_TITLE'  => trim($in['READER_TITLE'] ?: 'My Calibre Bookshelf'),
        ];

        return file_put_contents($path, json_encode($store, JSON_PRETTY_PRINT)) !== false;
    }

    public static function get(string $key, string $default = ''): string
    {
        return (string)($data[$key] ?? self::$data[$key] ?? $default);
    }

    public static function raw(): array
    {
        $out = self::$data;
        unset($out['nc_pass_enc'], $out['NC_PASS']);
        return $out;
    }

    public static function isConfigured(): bool
    {
        return file_exists(self::path());
    }

    private static function encrypt(string $plain, string $key): string
    {
        $iv     = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    private static function decrypt(string $encoded, string $key): string
    {
        $raw    = base64_decode($encoded);
        $iv     = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        return (string)openssl_decrypt($cipher, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
    }
}
