<?php

namespace App\Helpers;

class View
{
    public static function render(string $view, array $params = []): void
    {
        extract($params, EXTR_SKIP);
        $viewPath = __DIR__ . '/../../resources/views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        include __DIR__ . '/../../resources/views/layout.php';
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
