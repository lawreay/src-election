<?php

namespace App\Services;

use App\Core\Session;
use App\Helpers\View;

class AuthService
{
    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function login(string $username, string $password): bool
    {
        $config = require __DIR__ . '/../../config/app.php';
        $expectedUsername = $config['admin']['username'];
        $expectedPassword = $config['admin']['password'];

        if ($username === $expectedUsername && $password === $expectedPassword) {
            $this->session->set('admin_logged_in', true);
            $this->session->set('admin_username', $username);
            return true;
        }

        return false;
    }

    public function logout(): void
    {
        $this->session->forget('admin_logged_in');
        $this->session->forget('admin_username');
    }

    public function requireAdmin(): void
    {
        if (!$this->session->isLoggedIn()) {
            View::redirect('/login');
        }
    }
}
