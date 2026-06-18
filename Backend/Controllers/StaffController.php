<?php
// Backend/Controllers/StaffController.php

/**
 * StaffController - handles all staff-management HTTP actions.
 *
 * Replaces: Backend/process.php
 *
 * Actions (POST field):
 *   save_user           – create a new staff member
 *   update_user         – edit an existing staff member
 *   resend_verification – resend email-verification link
 *   delete_user         – remove a staff member
 *
 * All actions require an active ADMIN_SESSION with position === 'admin'
 * and a valid CSRF token.
 *
 * This is the only controller in the set that explicitly checks a CSRF
 * token (Auth::verifyCsrf), presumably because staff records contain the
 * most sensitive data (credentials, contact info) and this form is the
 * most "destructive" one to spoof.
 *
 * NOTE: create(), update(), and resendVerify() below originally declared
 * a `: never` return type while still containing `return $this->fail(...)`
 * statements. PHP does not allow ANY `return` statement inside a
 * `never`-typed function, so the original file was a fatal parse error
 * on PHP 8.1+ and could not run. This version corrects those three
 * signatures to `: void` without changing any behavior.
 */

require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Services/MailerService.php';
require_once __DIR__ . '/../Services/ImageUploadService.php';

class StaffController
{
    private User               $userModel;
    private MailerService      $mailer;
    private ImageUploadService $uploader;
    private string             $redirectBack = '../../Frontend/ADMIN/staff-list.php';

    public function __construct()
    {
        // Order matters: confirm the requester is an authenticated admin
        // *before* checking CSRF, so an unauthenticated request gets
        // redirected to login rather than a generic CSRF failure.
        Auth::requireAdmin('../../login-v2.html');
        Auth::verifyCsrf($this->redirectBack);

        $this->userModel = new User();
        $this->mailer    = new MailerService();
        // 'staff' subfolder/prefix for uploaded profile photos.
        $this->uploader  = new ImageUploadService('staff');
    }

    /** Route the incoming POST to the correct handler. */
    public function handle(): void
    {
        if (isset($_POST['save_user']))            { $this->create();           return; }
        if (isset($_POST['update_user']))          { $this->update();           return; }
        if (isset($_POST['resend_verification']))  { $this->resendVerify();     return; }
        if (isset($_POST['delete_user']))          { $this->delete();           return; }

        $this->redirect();  // Unknown action — just go back
    }

    // -----------------------------------------------------------------
    //  Create
    // -----------------------------------------------------------------

    /**
     * Create a new staff account.
     *
     * Flow: validate fields -> check for duplicate email/contact ->
     * handle optional photo upload -> insert the row -> email the new
     * staff member a verification link. The account exists immediately,
     * but email verification is a separate, trackable step
     * (User::getVerifyToken / resendVerify below).
     *
     * BUG FIX: this method was originally declared `: never`, but its
     * body contains several `return $this->fail(...)` statements. PHP
     * forbids ANY `return` statement inside a `never`-typed function -
     * even one returning the result of another never-returning call -
     * so the original code was a fatal parse error and could not run at
     * all on PHP 8.1+. Changed to `: void` here (fail() itself is still
     * correctly `never`, since it has no `return` statement).
     */
    private function create(): void
    {
        $data = $this->collectFormData();

        // Validation - each rule returns either an error string or null,
        // so we bail on the first failure encountered.
        if ($err = User::validateContact($data['contact'])) {
            $this->fail($err);
        }
        if ($err = User::validateEmail($data['email'])) {
            $this->fail($err);
        }
        if ($err = User::validatePassword($data['password'])) {
            $this->fail($err);
        }
        if ($this->userModel->emailExists($data['email'])) {
            $this->fail('Email already exists!');
        }
        if ($this->userModel->contactExists($data['contact'])) {
            $this->fail('Contact number already exists!');
        }

        // Image upload (optional) - '?? ""' guards against handle()
        // returning null when no file was actually selected.
        try {
            $data['image'] = $this->uploader->handle($_FILES['image'] ?? null) ?? '';
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        // Persist
        $userId = $this->userModel->create($data);
        if (!$userId) {
            $this->fail('Database error: could not create staff member.');
        }

        // The new account starts unverified; grab its verification token
        // so we can email a confirmation link immediately.
        $token = $this->userModel->getVerifyToken($userId);

        if ($this->mailer->sendEmailVerification($data['email'], $data['firstname'], $token)) {
            Session::flashSuccess(
                "Staff member \"{$data['firstname']} {$data['lastname']}\" added! "
                . "A verification email has been sent to <strong>{$data['email']}</strong>."
            );
        } else {
            // The account was still created successfully even if the
            // email failed to send - we just tell the admin so they know
            // to use "Resend Verification" manually afterward.
            Session::flashError(
                "Staff member added, but verification email failed. "
                . "SMTP error: <em>{$this->mailer->lastError}</em>. "
                . "Use <strong>Resend Verification</strong> from the staff list."
            );
        }

        $this->redirect();
    }

    // -----------------------------------------------------------------
    //  Update
    // -----------------------------------------------------------------

    /**
     * Edit an existing staff member's details.
     *
     * Notably: if the email address is changed, the account is treated
     * as needing re-verification again (resetVerification + a fresh
     * email), since the old verified email no longer applies.
     *
     * BUG FIX: changed from `: never` to `: void` - see create() above.
     */
    private function update(): void
    {
        $id   = (int) ($_POST['user_id'] ?? 0);
        $data = $this->collectFormData();

        // Validation
        if ($err = User::validateContact($data['contact'])) {
            $this->fail($err);
        }
        if ($err = User::validateEmail($data['email'])) {
            $this->fail($err);
        }
        // Password is optional on update (blank = "don't change it"), so
        // it's only validated if the admin actually typed a new one.
        if (!empty($data['password'])) {
            if ($err = User::validatePassword($data['password'])) {
                $this->fail($err);
            }
        }
        // Exclude the current user's own id from the duplicate-email
        // check, so saving a record without changing its email doesn't
        // falsely flag itself as "already in use".
        if ($this->userModel->emailExists($data['email'], $id)) {
            $this->fail('Email already in use by another account!');
        }

        // Detect email change before updating - we need the *old* email
        // for comparison, so this lookup must happen before the update
        // call below overwrites it.
        $existing      = $this->userModel->findById($id);
        $emailChanged  = $existing
            && strtolower(trim($existing['email'])) !== strtolower($data['email']);

        // Image upload (optional). Default to keeping whatever image was
        // already on file; only replace it if a new file actually came
        // through the upload handler.
        $data['image'] = $data['existing_image'];
        try {
            $newPath = $this->uploader->handle($_FILES['image'] ?? null);
            if ($newPath) {
                $this->uploader->delete($data['existing_image']);
                $data['image'] = $newPath;
            }
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        if (!$this->userModel->update($id, $data)) {
            $this->fail('Database error: update failed.');
        }

        $fullName = "{$data['firstname']} {$data['lastname']}";

        if ($emailChanged) {
            // Force re-verification: the previous verified-email status
            // no longer means anything once the address itself changed.
            $this->userModel->resetVerification($id);
            $newToken = $this->userModel->getVerifyToken($id);

            if ($this->mailer->sendEmailVerification($data['email'], $data['firstname'], $newToken)) {
                Session::flashSuccess(
                    "\"$fullName\" updated. "
                    . "Email changed — a new verification link has been sent to <strong>{$data['email']}</strong>."
                );
            } else {
                Session::flashSuccess(
                    "\"$fullName\" updated, but the new verification email failed. "
                    . "SMTP error: <em>{$this->mailer->lastError}</em>. "
                    . "Use <strong>Resend Verification</strong> from the staff list."
                );
            }
        } else {
            Session::flashSuccess("\"$fullName\" updated successfully!");
        }

        $this->redirect();
    }

    // -----------------------------------------------------------------
    //  Resend verification
    // -----------------------------------------------------------------

    /**
     * Generate a fresh verification token and re-send the confirmation
     * email - used when the original email was lost, expired, or never
     * arrived.
     *
     * BUG FIX: changed from `: never` to `: void` - see create() above.
     */
    private function resendVerify(): void
    {
        $id  = (int) ($_POST['user_id'] ?? 0);
        $usr = $this->userModel->findById($id);

        if (!$usr) {
            $this->fail('Staff member not found.');
        }
        if ($usr['email_verified']) {
            // Nothing to resend if they're already verified - this also
            // guards against generating unnecessary tokens for already-
            // confirmed accounts.
            $name = htmlspecialchars($usr['firstname'] . ' ' . $usr['lastname']);
            $this->fail("\"$name\" is already verified.");
        }

        // resetVerification() both invalidates any old token and returns
        // a brand-new one to send out.
        $newToken = $this->userModel->resetVerification($id);

        if ($this->mailer->sendEmailVerification($usr['email'], $usr['firstname'], $newToken)) {
            Session::flashSuccess(
                "Verification email resent to {$usr['email']} for "
                . htmlspecialchars($usr['firstname'] . ' ' . $usr['lastname']) . '.'
            );
        } else {
            Session::flashError(
                "Could not send verification email. "
                . "SMTP error: <em>{$this->mailer->lastError}</em>."
            );
        }

        $this->redirect();
    }

    // -----------------------------------------------------------------
    //  Delete
    // -----------------------------------------------------------------

    /**
     * Permanently remove a staff account, including their profile photo
     * (if any) once the database row is confirmed deleted.
     */
    private function delete(): never
    {
        $id  = (int) ($_POST['user_id'] ?? 0);
        $usr = $this->userModel->findById($id);

        $fullName = $usr
            ? htmlspecialchars($usr['firstname'] . ' ' . $usr['lastname'])
            : 'User';

        if ($this->userModel->delete($id)) {
            if ($usr && !empty($usr['image'])) {
                $this->uploader->delete($usr['image']);
            }
            Session::flashSuccess("\"$fullName\" deleted successfully.");
        } else {
            Session::flashError("Error deleting \"$fullName\".");
        }

        $this->redirect();
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    /** Collect and sanitise common form fields. */
    private function collectFormData(): array
    {
        return [
            'firstname'      => trim($_POST['firstname']      ?? ''),
            'lastname'       => trim($_POST['lastname']       ?? ''),
            'email'          => trim($_POST['email']          ?? ''),
            // Intentionally NOT trimmed - a password could legitimately
            // start/end with a space character that the user intends.
            'password'       => $_POST['password']            ?? '',
            'position'       => trim($_POST['position']       ?? ''),
            'contact'        => trim($_POST['contact']        ?? ''),
            'address'        => trim($_POST['address']        ?? ''),
            'existing_image' => trim($_POST['existing_image'] ?? ''),
        ];
    }

    /** Flash an error message and redirect back - used for every validation failure above. */
    private function fail(string $message): never
    {
        Session::flashError($message);
        $this->redirect();
    }

    private function redirect(): never
    {
        header("Location: {$this->redirectBack}");
        exit();
    }
}

// ── Bootstrap ────────────────────────────────────────────────────
(new StaffController())->handle();
