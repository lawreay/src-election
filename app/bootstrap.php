<?php

require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/Session.php';
require_once __DIR__ . '/Helpers/View.php';
require_once __DIR__ . '/Services/AuthService.php';
require_once __DIR__ . '/Services/ElectionService.php';
require_once __DIR__ . '/Services/StudentImportService.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
