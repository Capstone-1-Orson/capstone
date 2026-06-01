<?php
// Backend/Controllers/RefundVoidController.php

ini_set('display_errors', '0');
error_reporting(E_ALL);

// Buffer ALL output from the very start — including session cookie headers side-effects
ob_start();

require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Services/OrderService.php';

// Resume the ADMIN named session (custom cookie: admin_sid)
// WITHOUT redirecting — this is a JSON endpoint, not a page.
Session::start(Session::ADMIN);

class RefundVoidController
{
    private const ALLOWED_ACTIONS = ['void', 'refund'];
    private OrderService $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }

    public function handle(): void
    {
        // Discard any stray output (whitespace, BOM, session noise)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');

        // Auth check — same keys Auth::requireAdmin() uses
        $user     = Session::get('user', '');
        $position = Session::get('position', '');

        if (!$user || $position !== 'admin') {
            $this->json([
                'success' => false,
                'message' => 'Not authenticated. Please log in again.',
            ]);
            return;
        }

        $raw     = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        if (!$payload) {
            $this->json(['success' => false, 'message' => 'Invalid JSON payload.']);
            return;
        }

        $action = $payload['action'] ?? '';
        if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
            $this->json(['success' => false, 'message' => 'Invalid action. Expected: void or refund.']);
            return;
        }

        if (empty($payload['order_id'])) {
            $this->json(['success' => false, 'message' => 'order_id is required.']);
            return;
        }

        $reason = trim($payload['reason'] ?? '');
        if ($reason === '') {
            $this->json(['success' => false, 'message' => 'A reason is required to ' . $action . ' an order.']);
            return;
        }

        $payload['reason']     = $reason;
        $payload['created_by'] = trim(Session::get('firstname', '') . ' ' . Session::get('lastname', ''))
                               ?: Session::get('user', 'unknown');

        try {
            $result = $this->orderService->voidOrRefund($payload);
            $this->json($result);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
            ]);
        }
    }

    private function json(array $data): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo json_encode($data);
        exit();
    }
}

// Global exception safety net — catch any fatal before it produces HTML
set_exception_handler(function (Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()]);
    exit();
});

(new RefundVoidController())->handle();