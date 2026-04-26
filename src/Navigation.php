<?php
namespace CalibreOpds;

class Navigation
{
    public static function selfUrl(array $params = []): string
    {
        return 'index.php' . ($params ? '?' . http_build_query($params) : '');
    }

    public static function feedUrl(string $href): string
    {
        return self::selfUrl(['feed' => $href]);
    }

    public static function enc(string $href): string
    {
        return rtrim(strtr(base64_encode($href), '+/', '-_'), '=');
    }

    public static function dec(string $val): string
    {
        return (string)base64_decode(strtr($val, '-_', '+/'));
    }

    public static function breadcrumb(array $catalog): array
    {
        $crumbs = [];
        $nav    = $catalog['nav'] ?? [];

        if (isset($nav['start'])) {
            $crumbs[] = ['label' => 'Home', 'href' => self::feedUrl($nav['start']['href']), 'icon' => '⌂'];
        }
        if (isset($nav['up'])) {
            $crumbs[] = ['label' => 'Back', 'href' => self::feedUrl($nav['up']['href']), 'icon' => '←'];
        }

        $crumbs[] = ['label' => $catalog['title'], 'href' => null];

        return $crumbs;
    }

    public static function imgUrl(string $href): string
    {
        return self::selfUrl(['action' => 'img', 'href' => self::enc($href)]);
    }
}
