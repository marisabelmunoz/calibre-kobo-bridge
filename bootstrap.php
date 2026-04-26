<?php
// Prefer Composer autoloader; fall back to manual requires
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/src/Config.php';
    require_once __DIR__ . '/src/Auth.php';
    require_once __DIR__ . '/src/Navigation.php';
    require_once __DIR__ . '/src/Opds.php';
}
