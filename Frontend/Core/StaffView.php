<?php
// Frontend/Core/StaffView.php

require_once __DIR__ . '/View.php';

/**
 * StaffView – data for Frontend/ADMIN/staff-list.php.
 *
 * Also handles the lightweight AJAX endpoints that the page embeds:
 *   ?rt_staff=1           → JSON list of all staff (real-time polling)
 *   ?rt_add_staff=1       → POST: quick-add staff (AJAX form, returns JSON)
 *   ?rt_resend_verify=1   → POST: resend verification email for a staff member
 *
 * Usage at the top of staff-list.php:
 *   require_once '../../Frontend/Core/StaffView.php';
 *   $view = new StaffView();   // constructor dispatches AJAX early if needed
 */
class StaffView extends View
{
    public array  $staffRows  = [];
    public string $csrfToken  = '';

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();

        // Dispatch lightweight AJAX before any HTML is sent
        if (isset($_GET['rt_staff']))         { $this->ajaxStaffList();    /* exits */ }
        if (isset($_GET['rt_add_staff']))     { $this->ajaxAddStaff();     /* exits */ }
        if (isset($_GET['rt_resend_verify'])) { $this->ajaxResendVerify(); /* exits */ }

        $this->load();
    }

    private function load(): void
    {
        $this->staffRows = $this->fetchAll(
            "SELECT * FROM user WHERE position = 'staff' ORDER BY id ASC"
        );
        $this->csrfToken = View::csrfToken();
    }

    // ─────────────────────────────────────────────────────────────
    //  AJAX: real-time staff list (GET ?rt_staff=1)
    // ─────────────────────────────────────────────────────────────

    private function ajaxStaffList(): never
    {
        $rows = $this->fetchAll(
            "SELECT id, firstname, lastname, email, email_verified,
                    position, contact, address, image
             FROM user WHERE position = 'staff' ORDER BY id ASC"
        );

        $out = array_map(fn($r) => [
            'id'             => (int)  $r['id'],
            'firstname'      =>        $r['firstname'],
            'lastname'       =>        $r['lastname'],
            'email'          =>        $r['email'],
            'email_verified' => (int) ($r['email_verified'] ?? 0),
            'position'       =>        $r['position'],
            'contact'        =>        $r['contact'],
            'address'        =>        $r['address'],
            'image'          =>        $r['image'] ?? '',
        ], $rows);

        header('Content-Type: application/json');
        echo json_encode(['staff' => $out, 'ts' => time()]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────
    //  AJAX: quick-add staff (POST ?rt_add_staff=1)
    // ─────────────────────────────────────────────────────────────

    private function ajaxAddStaff(): never
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'POST required.']);
            exit();
        }

        // CSRF check
        $submitted = $_POST['csrf_token'] ?? '';
        $stored    = $_SESSION['csrf_token'] ?? '';
        if (empty($submitted) || !hash_equals($stored, $submitted)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit();
        }

        // Collect fields
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname  = trim($_POST['lastname']  ?? '');
        $email     = trim($_POST['email']     ?? '');
        $password  = $_POST['password']       ?? '';
        $contact   = trim($_POST['contact']   ?? '');
        $address   = trim($_POST['address']   ?? '');
        $position  = 'staff';

        // Required-field check
        if (!$firstname || !$lastname || !$email || !$password || !$contact || !$address) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit();
        }

        // Validate email domain
        require_once __DIR__ . '/../../Backend/Models/User.php';
        if ($err = User::validateEmail($email)) {
            echo json_encode(['success' => false, 'message' => $err]);
            exit();
        }
        if ($err = User::validateContact($contact)) {
            echo json_encode(['success' => false, 'message' => $err]);
            exit();
        }
        if ($err = User::validatePassword($password)) {
            echo json_encode(['success' => false, 'message' => $err]);
            exit();
        }

        // Duplicate email check
        $userModel = new User();
        if ($userModel->emailExists($email)) {
            echo json_encode(['success' => false, 'message' => 'Email address is already in use.']);
            exit();
        }

        // Image upload (optional)
        $imagePath = '';
        if (!empty($_FILES['image']['name'])) {
            require_once __DIR__ . '/../../Backend/Services/ImageUploadService.php';
            $uploader = new ImageUploadService('staff');
            try {
                $imagePath = $uploader->handle($_FILES['image']) ?? '';
            } catch (RuntimeException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit();
            }
        }

        // Insert
        $newId = $userModel->create([
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'email'     => $email,
            'password'  => $password,
            'contact'   => $contact,
            'address'   => $address,
            'position'  => $position,
            'image'     => $imagePath,
        ]);

        if (!$newId) {
            echo json_encode(['success' => false, 'message' => 'Database error: could not create staff.']);
            exit();
        }

        // Send verification email
        $token = $userModel->getVerifyToken($newId);
        if ($token !== '') {
            require_once __DIR__ . '/../../Backend/Services/MailerService.php';
            $mailer = new MailerService();
            $sent   = $mailer->sendEmailVerification($email, $firstname, $token);
            if (!$sent) {
                // Log the failure but don't block the success response —
                // the admin can resend manually from the staff list.
                error_log("MailerService failed for new staff #{$newId}: " . $mailer->lastError);
            }
        }

        // Rotate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        echo json_encode([
            'success'  => true,
            'message'  => 'Staff member added successfully.',
            'new_id'   => $newId,
            'new_csrf' => $_SESSION['csrf_token'],
        ]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────
    //  AJAX: resend verification email (POST ?rt_resend_verify=1)
    // ─────────────────────────────────────────────────────────────

    private function ajaxResendVerify(): never
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'POST required.']);
            exit();
        }

        // CSRF check
        $submitted = $_POST['csrf_token'] ?? '';
        $stored    = $_SESSION['csrf_token'] ?? '';
        if (empty($submitted) || !hash_equals($stored, $submitted)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit();
        }

        $staffId = (int) ($_POST['staff_id'] ?? 0);
        if ($staffId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid staff ID.']);
            exit();
        }

        require_once __DIR__ . '/../../Backend/Models/User.php';
        $userModel = new User();
        $staff     = $userModel->findById($staffId);

        if (!$staff || $staff['position'] !== 'staff') {
            echo json_encode(['success' => false, 'message' => 'Staff member not found.']);
            exit();
        }

        if ((int) $staff['email_verified'] === 1) {
            echo json_encode(['success' => false, 'message' => 'Email is already verified.']);
            exit();
        }

        // Generate a fresh token (resets expiry to +24 h)
        $token = $userModel->getVerifyToken($staffId);

        require_once __DIR__ . '/../../Backend/Services/MailerService.php';
        $mailer = new MailerService();
        $sent   = $mailer->sendEmailVerification(
            $staff['email'],
            $staff['firstname'],
            $token
        );

        if (!$sent) {
            error_log("Resend verify failed for staff #{$staffId}: " . $mailer->lastError);
            echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']);
            exit();
        }

        echo json_encode(['success' => true, 'message' => 'Verification email resent successfully.']);
        exit();
    }
}