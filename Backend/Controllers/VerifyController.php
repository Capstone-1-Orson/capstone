<?php
// Backend/Controllers/VerifyController.php

require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Models/User.php';

class VerifyController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('../../verify.html');
        }

        $role      = trim($_POST['role'] ?? '');
        $submitted = trim($_POST['otp']  ?? '');

        if (!in_array($role, ['admin', 'staff'], true) || strlen($submitted) !== 6) {
            $this->redirect("../../verify.html?role={$role}&error=invalid");
        }

        // Resume the correct named session
        $sessionName = ($role === 'admin') ? Session::ADMIN : Session::STAFF;
        Session::start($sessionName);

        // ── Expiry check ─────────────────────────────────────────
        $expires = Session::get('otp_expires', 0);
        if (time() > $expires) {
            $this->clearOtp();
            $this->redirect("../../verify.html?role={$role}&error=expired");
        }

        // ── Hash check ───────────────────────────────────────────
        $stored = Session::get('otp', '');
        if (!$stored || !password_verify($submitted, $stored)) {
            $this->redirect("../../verify.html?role={$role}&error=invalid");
        }

        // ── OTP valid – hydrate session and send to dashboard ────
        $identity = Session::get('otp_identity', '');
        $this->clearOtp();

        if ($role === 'admin') {
            $this->hydrateAdmin($identity);
            $this->redirect('../../Frontend/ADMIN/index2.php');
        } else {
            $this->hydrateStaff($identity);
            $this->redirect('../../Frontend/POS.php');
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Session hydration
    // ─────────────────────────────────────────────────────────────

    private function hydrateAdmin(string $identity): void
    {
        $user = $this->userModel->findAdminByPosition();

        if ($user) {
            Session::set('user',      $user['email']);
            Session::set('position',  'admin');
            Session::set('firstname', $user['firstname'] ?? '');
            Session::set('lastname',  $user['lastname']  ?? '');
            Session::set('image',     $user['image']     ?? '');
        } else {
            Session::set('user',     $identity);
            Session::set('position', 'admin');
        }
    }

    private function hydrateStaff(string $email): void
    {
        $user = $this->userModel->findByEmail($email);

        if ($user) {
            Session::set('user',      $user['email']);
            Session::set('position',  $user['position'] ?? 'staff');
            Session::set('firstname', $user['firstname'] ?? '');
            Session::set('lastname',  $user['lastname']  ?? '');
            Session::set('image',     $user['image']     ?? '');
        } else {
            // Fallback: identity might be a username instead of email
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare('SELECT * FROM user WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                Session::set('user',      $row['email'] ?: $email);
                Session::set('position',  $row['position'] ?? 'staff');
                Session::set('firstname', $row['firstname'] ?? '');
                Session::set('lastname',  $row['lastname']  ?? '');
                Session::set('image',     $row['image']     ?? '');
            } else {
                // Last resort fallback
                Session::set('user',     $email);
                Session::set('position', 'staff');
            }
        }
    }

    // ─────────────────────────────────────────────────────────────

    private function clearOtp(): void
    {
        foreach (['otp', 'otp_expires', 'otp_role', 'otp_identity', 'otp_email'] as $key) {
            Session::remove($key);
        }
    }

    private function redirect(string $url): never
    {
        header("Location: $url");
        exit();
    }
}

(new VerifyController())->handle();