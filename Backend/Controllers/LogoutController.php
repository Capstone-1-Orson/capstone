<?php
// Backend/Controllers/LogoutController.php

require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Core/Auth.php';

class LogoutController
{
    private Session $session;

    public function __construct()
    {
        $this->session = new Session();
    }

    public function handle(): void
    {
        // Destroy the session completely
        $this->session->destroy();

        // Redirect to login page
        header('Location: ../../login-v2.html');
        exit;
    }
}

// ── Dispatch ────────────────────────────────────────────────────
$controller = new LogoutController();
$controller->handle();
