<?php
// Backend/Controllers/PosController.php

/**
 * PosController – JSON API for placing POS orders only.
 *
 * Routes (POST JSON body):
 *   { items: [...] } → place order
 *
 * Void   → VoidController.php
 * Refund → RefundController.php
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../Core/Session.php';
Session::start(Session::STAFF);

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
        ob_clean();

        // Auth: admin only
        $userKey  = $_SESSION['user']     ?? '';
        $position = $_SESSION['position'] ?? '';
        $allowed  = ['admin'];
        if (!$userKey || !in_array(strtolower($position), $allowed, true)) {
            $this->json(['success' => false, 'message' => 'Not authenticated. Please log in again.']);
            return;
        }

        $raw     = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        if (!$payload) {
            $this->json(['success' => false, 'message' => 'Invalid JSON payload.']);
            return;
        }

        if (isset($payload['items'])) {
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

    // ─────────────────────────────────────────────────────────────
    //  Session helpers
    // ─────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────

    private function json(array $data): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo json_encode($data);
        exit();
    }
}

(new PosController())->handle();