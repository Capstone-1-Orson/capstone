<?php
// Backend/Controllers/PosController.php

/**
 * PosController – JSON API for the POS terminal.
 *
 * Replaces: Backend/pos_process.php  +  Backend/pos_void_refund.php
 *
 * Routes (POST JSON body):
 *   { items: [...] }           → place order  (pos_process)
 *   { action: 'void'|'refund', order_id } → void or refund  (pos_void_refund)
 */

// ── MUST be the very first executable lines ──────────────────────────────────
// Suppress HTML error output so PHP warnings never corrupt the JSON response.
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../Core/Session.php';
Session::start(Session::STAFF);

// NOTE: Auth.php intentionally omitted — it may output HTML or redirect,
//       which would corrupt the JSON response. Auth is enforced manually below.
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Services/OrderService.php';

class PosController
{
    private OrderService $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }

    public function handle(): void
    {
        header('Content-Type: application/json');
        // Discard anything that leaked before this point (PHP warnings, BOM, etc.)
        ob_clean();

        // Guard: enforce auth manually — never rely on Auth.php here
        $userKey  = $_SESSION['user']     ?? '';
        $position = $_SESSION['position'] ?? '';
        if (!$userKey || $position !== 'staff') {
            $this->json(['success' => false, 'message' => 'Not authenticated. Please log in again.']);
            return;
        }

        $raw     = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        if (!$payload) {
            $this->json(['success' => false, 'message' => 'Invalid JSON payload.']);
            return;
        }

        // Route by payload shape
        if (isset($payload['action']) && in_array($payload['action'], ['void', 'refund'], true)) {
            $this->handleVoidRefund($payload);
        } elseif (isset($payload['items'])) {
            $this->handlePlaceOrder($payload);
        } else {
            $this->json(['success' => false, 'message' => 'Unrecognised action.']);
        }
    }

    // ─────────────────────────────────────────────────────────────

    private function handlePlaceOrder(array $payload): void
    {
        $payload['user_id'] = $this->resolveUserId();
        $result = $this->orderService->place($payload);
        $this->json($result);
    }

    private function handleVoidRefund(array $payload): void
    {
        $payload['created_by'] = $this->resolveFullName();
        $result                = $this->orderService->voidOrRefund($payload);
        $this->json($result);
    }

    // ─────────────────────────────────────────────────────────────
    //  Session helpers
    // ─────────────────────────────────────────────────────────────

    /** Return the DB integer id for the currently logged-in staff user. */
    private function resolveUserId(): ?int
    {
        $key = Session::get('user', '');
        if (!$key) {
            return null;
        }

        $db = Database::getInstance()->getConnection();

        // Try email, username, or firstname
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

        // Fallback: match by firstname + lastname from session
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

    /** Return "Firstname Lastname" for the audit log. */
    private function resolveFullName(): string
    {
        $first = trim(Session::get('firstname', ''));
        $last  = trim(Session::get('lastname', ''));
        $full  = trim("$first $last");

        if ($full !== '') {
            return $full;
        }

        $email = Session::get('user', '');
        if (!$email) {
            return 'unknown';
        }

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT firstname, lastname FROM user WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($fn, $ln);
        if ($stmt->fetch()) {
            Session::set('firstname', $fn);
            Session::set('lastname', $ln);
            $full = trim("$fn $ln");
        }
        $stmt->close();

        return $full !== '' ? $full : $email;
    }

    // ─────────────────────────────────────────────────────────────

    private function json(array $data): void
    {
        // Drain ALL output buffer levels — safe even if ob_start() was never called
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo json_encode($data);
        exit();
    }
}

(new PosController())->handle();