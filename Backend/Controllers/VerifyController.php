<?php
// Backend/Controllers/VerifyController.php

/**
 * VerifyController - second step of the two-factor login flow.
 *
 * LoginController already confirmed the password and emailed a 6-digit
 * OTP. This controller's only job is: check the OTP the user typed
 * against the hashed one in session, and if it matches, actually
 * "log them in" by writing their identity into $_SESSION and sending
 * them to the right dashboard.
 */

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
        // Only reachable via the verify form's POST; a stray GET just
        // bounces back to the (empty) verify page.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('../../verify.html');
        }

        $role      = trim($_POST['role'] ?? '');
        $submitted = trim($_POST['otp']  ?? '');

        // Basic shape validation before we even touch the session/DB:
        // role must be one of the two known values, and the OTP must be
        // exactly 6 characters (we never accept a partial/garbled code).
        if (!in_array($role, ['admin', 'staff'], true) || strlen($submitted) !== 6) {
            $this->redirect("../../verify.html?role={$role}&error=invalid");
        }

        // Resume the correct named session (ADMIN_SESSION vs STAFF_SESSION)
        // - this is the same session LoginController wrote the OTP into,
        // so we must pick the matching scope or Session::get() below would
        // find nothing.
        $sessionName = ($role === 'admin') ? Session::ADMIN : Session::STAFF;
        Session::start($sessionName);

        // ── Expiry check ─────────────────────────────────────────
        // OTPs are only valid for 5 minutes (set in LoginController).
        // If time has run out, clear it out so it can't be reused and
        // force the user to request a fresh one.
        $expires = Session::get('otp_expires', 0);
        if (time() > $expires) {
            $this->clearOtp();
            $this->redirect("../../verify.html?role={$role}&error=expired");
        }

        // ── Hash check ───────────────────────────────────────────
        // The OTP was stored hashed (password_hash), so we verify it the
        // same way we'd verify a password - never compare plain strings.
        $stored = Session::get('otp', '');
        if (!$stored || !password_verify($submitted, $stored)) {
            $this->redirect("../../verify.html?role={$role}&error=invalid");
        }

        // ── OTP valid - hydrate session and send to dashboard ────
        $identity = Session::get('otp_identity', '');
        // One-time use: remove all OTP data immediately so this same code
        // cannot be replayed for a second login.
        $this->clearOtp();

        if ($role === 'admin') {
            $this->hydrateAdmin($identity);
            $this->redirect('../../Frontend/ADMIN/index2.php');
        } else {
            $this->hydrateStaff($identity);
            $this->redirect('../../Frontend/POS.php');
        }
    }

    // -----------------------------------------------------------------
    //  Session hydration
    // -----------------------------------------------------------------

    /**
     * Populate $_SESSION with the admin's profile fields so the rest of
     * the app (header, nav, "logged in as ...") has what it needs.
     * Falls back to the bare identity string if, for some odd reason,
     * the admin record can't be found at this point.
     */
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
            // Defensive fallback - should be rare, but avoids a fatal
            // error if the admin row somehow vanished between the OTP
            // being sent and now.
            Session::set('user',     $identity);
            Session::set('position', 'admin');
        }
    }

    /**
     * Populate $_SESSION with the staff member's profile fields.
     * Includes an extra fallback path because `$email` passed in here is
     * actually whatever "identity" LoginController stored, which is
     * normally an email but is treated defensively as possibly being a
     * username instead.
     */
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
            // Fallback: identity might be a username instead of email.
            // Goes around the User model directly to the DB because this
            // is a one-off lookup shape (`username` column) the model
            // doesn't otherwise expose.
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
                // Last resort fallback - we know they passed OTP
                // verification, so log them in as `staff` using whatever
                // identity string we have, rather than blocking them out.
                Session::set('user',     $email);
                Session::set('position', 'staff');
            }
        }
    }

    // -----------------------------------------------------------------

    /** Remove every OTP-related key so a used/expired code can't be replayed. */
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

// ── Bootstrap ──────────────────────────────────────────────────────
(new VerifyController())->handle();
