<?php
// Backend/Controllers/StaffController.php

/**
 * StaffController – handles all staff-management HTTP actions.
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
        Auth::requireAdmin('../../login-v2.html');
        Auth::verifyCsrf($this->redirectBack);

        $this->userModel = new User();
        $this->mailer    = new MailerService();
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

    // ─────────────────────────────────────────────────────────────
    //  Create
    // ─────────────────────────────────────────────────────────────

    private function create(): never
    {
        $data = $this->collectFormData();

        // Validation
        if ($err = User::validateContact($data['contact'])) {
            return $this->fail($err);
        }
        if ($err = User::validateEmail($data['email'])) {
            return $this->fail($err);
        }
        if ($err = User::validatePassword($data['password'])) {
            return $this->fail($err);
        }
        if ($this->userModel->emailExists($data['email'])) {
            return $this->fail('Email already exists!');
        }
        if ($this->userModel->contactExists($data['contact'])) {
            return $this->fail('Contact number already exists!');
        }

        // Image upload (optional)
        try {
            $data['image'] = $this->uploader->handle($_FILES['image'] ?? null) ?? '';
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage());
        }

        // Persist
        $userId = $this->userModel->create($data);
        if (!$userId) {
            return $this->fail('Database error: could not create staff member.');
        }

        $token = $this->userModel->getVerifyToken($userId);

        if ($this->mailer->sendEmailVerification($data['email'], $data['firstname'], $token)) {
            Session::flashSuccess(
                "Staff member \"{$data['firstname']} {$data['lastname']}\" added! "
                . "A verification email has been sent to <strong>{$data['email']}</strong>."
            );
        } else {
            Session::flashError(
                "Staff member added, but verification email failed. "
                . "SMTP error: <em>{$this->mailer->lastError}</em>. "
                . "Use <strong>Resend Verification</strong> from the staff list."
            );
        }

        $this->redirect();
    }

    // ─────────────────────────────────────────────────────────────
    //  Update
    // ─────────────────────────────────────────────────────────────

    private function update(): never
    {
        $id   = (int) ($_POST['user_id'] ?? 0);
        $data = $this->collectFormData();

        // Validation
        if ($err = User::validateContact($data['contact'])) {
            return $this->fail($err);
        }
        if ($err = User::validateEmail($data['email'])) {
            return $this->fail($err);
        }
        if (!empty($data['password'])) {
            if ($err = User::validatePassword($data['password'])) {
                return $this->fail($err);
            }
        }
        if ($this->userModel->emailExists($data['email'], $id)) {
            return $this->fail('Email already in use by another account!');
        }

        // Detect email change before updating
        $existing      = $this->userModel->findById($id);
        $emailChanged  = $existing
            && strtolower(trim($existing['email'])) !== strtolower($data['email']);

        // Image upload (optional)
        $data['image'] = $data['existing_image'];
        try {
            $newPath = $this->uploader->handle($_FILES['image'] ?? null);
            if ($newPath) {
                $this->uploader->delete($data['existing_image']);
                $data['image'] = $newPath;
            }
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage());
        }

        if (!$this->userModel->update($id, $data)) {
            return $this->fail('Database error: update failed.');
        }

        $fullName = "{$data['firstname']} {$data['lastname']}";

        if ($emailChanged) {
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

    // ─────────────────────────────────────────────────────────────
    //  Resend verification
    // ─────────────────────────────────────────────────────────────

    private function resendVerify(): never
    {
        $id  = (int) ($_POST['user_id'] ?? 0);
        $usr = $this->userModel->findById($id);

        if (!$usr) {
            return $this->fail('Staff member not found.');
        }
        if ($usr['email_verified']) {
            $name = htmlspecialchars($usr['firstname'] . ' ' . $usr['lastname']);
            return $this->fail("\"$name\" is already verified.");
        }

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

    // ─────────────────────────────────────────────────────────────
    //  Delete
    // ─────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    /** Collect and sanitise common form fields. */
    private function collectFormData(): array
    {
        return [
            'firstname'      => trim($_POST['firstname']      ?? ''),
            'lastname'       => trim($_POST['lastname']       ?? ''),
            'email'          => trim($_POST['email']          ?? ''),
            'password'       => $_POST['password']            ?? '',
            'position'       => trim($_POST['position']       ?? ''),
            'contact'        => trim($_POST['contact']        ?? ''),
            'address'        => trim($_POST['address']        ?? ''),
            'existing_image' => trim($_POST['existing_image'] ?? ''),
        ];
    }

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