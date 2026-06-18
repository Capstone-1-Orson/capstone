<?php
// Backend/Controllers/PosController.php

/**
 * PosController - JSON API for placing POS orders only.
 *
 * Routes (POST JSON body):
 *   { items: [...] } -> place order
 *
 * Void   -> VoidController.php   (NOT this file)
 * Refund -> RefundController.php (NOT this file - see RefundVoidController
 *           in this same folder, which actually handles both void+refund)
 *
 * This is a pure JSON API endpoint (called via fetch()/AJAX from the POS
 * screen), not a page that renders HTML, hence the output-buffering and
 * `Content-Type: application/json` setup below.
 */

// Suppress HTML error output (a stray PHP warning/notice would otherwise
// corrupt the JSON response and break the frontend's JSON.parse()).
// Errors are still tracked internally via error_reporting(E_ALL).
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Start buffering output immediately, before any other code runs, so
// that *anything* unexpected (whitespace from an included file, a
// warning, etc.) can be discarded before we emit the real JSON body.
ob_start();

require_once __DIR__ . '/../Core/Session.php';
Session::start(Session::STAFF);

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Services/OrderService.php';

class PosController
{
    // All the actual order-creation business logic (price calculation,
    // inventory deduction, persistence) lives in OrderService - this
    // controller is just the HTTP/auth/JSON wrapper around it.
    private OrderService $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }

    public function handle(): void
    {
        header('Content-Type: application/json');
        // Discard anything that may have leaked into the output buffer
        // before this point (e.g. from session start) so the response
        // body is pure JSON.
        ob_clean();

        // Auth: admin only.
        // NOTE: despite this file being about the POS *order screen* that
        // staff use, placing an order through this specific endpoint is
        // restricted to the 'admin' position only - any other position
        // (including 'staff') is rejected here.
        $userKey  = $_SESSION['user']     ?? '';
        $position = $_SESSION['position'] ?? '';
        $allowed  = ['admin'];
        if (!$userKey || !in_array(strtolower($position), $allowed, true)) {
            $this->json(['success' => false, 'message' => 'Not authenticated. Please log in again.']);
            return;
        }

        // The frontend sends a JSON body (not a normal form-encoded POST),
        // so we read the raw request body and decode it ourselves rather
        // than using $_POST.
        $raw     = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        if (!$payload) {
            $this->json(['success' => false, 'message' => 'Invalid JSON payload.']);
            return;
        }

        // Only one action is currently supported by this endpoint:
        // placing an order (identified by the presence of an `items` key).
        if (isset($payload['items'])) {
            $this->handlePlaceOrder($payload);
        } else {
            $this->json(['success' => false, 'message' => 'Unrecognised action.']);
        }
    }

    // -----------------------------------------------------------------

    /**
     * Attach the resolved DB user id to the payload (so the order can be
     * attributed to whoever placed it) and hand off to OrderService,
     * which does the real work of validating items, computing totals,
     * deducting inventory, and inserting the order rows.
     */
    private function handlePlaceOrder(array $payload): void
    {
        $payload['user_id'] = $this->resolveUserId();
        $result = $this->orderService->place($payload);
        $this->json($result);
    }

    // -----------------------------------------------------------------
    //  Session helpers
    // -----------------------------------------------------------------

    /**
     * Figure out the numeric `user.id` of the currently logged-in person.
     *
     * The session only stores loosely-typed identity strings (set during
     * login/verify - see VerifyController), not a reliable numeric id, so
     * this does a best-effort lookup:
     *   1. Try matching the session "user" value against email, username,
     *      OR firstname (whichever the session happened to store).
     *   2. If that fails, fall back to matching by firstname+lastname pair.
     *   3. If that also fails, give up and return null (anonymous order).
     */
    private function resolveUserId(): ?int
    {
        $key = Session::get('user', '');
        if (!$key) {
            return null;
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            'SELECT id FROM user WHERE email = ? OR username = ? OR firstname = ? LIMIT 1'
        );
        $stmt->bind_param('sss', $key, $key, $key);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        $stmt->close();

        if ($id) {
            return $id;
        }

        // Primary lookup failed (e.g. session stored a display name that
        // doesn't match any single column) - try first+last name together
        // as a second, more specific attempt.
        $fn = trim(Session::get('firstname', ''));
        $ln = trim(Session::get('lastname', ''));
        if ($fn) {
            $stmt2 = $db->prepare(
                'SELECT id FROM user WHERE firstname = ? AND lastname = ? LIMIT 1'
            );
            $stmt2->bind_param('ss', $fn, $ln);
            $stmt2->execute();
            $stmt2->bind_result($id2);
            $stmt2->fetch();
            $stmt2->close();
            return $id2 ?? null;
        }

        return null;
    }

    // -----------------------------------------------------------------

    /**
     * Emit a JSON response and terminate the script.
     * Flushes/discards any remaining output buffers first so nothing
     * (warnings, whitespace) can sneak in before or after the JSON.
     */
    private function json(array $data): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo json_encode($data);
        exit();
    }
}

// ── Bootstrap ──────────────────────────────────────────────────────
(new PosController())->handle();
