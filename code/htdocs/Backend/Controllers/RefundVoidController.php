<?php
// Backend/Controllers/RefundVoidController.php

/**
 * RefundVoidController - JSON API for voiding or refunding an existing
 * order. Companion to PosController (which only places new orders).
 *
 * Routes (POST JSON body):
 *   { action: 'void',   order_id: N, reason: '...' }
 *   { action: 'refund', order_id: N, reason: '...' }
 *
 * Both actions require:
 *   - an active ADMIN session (custom cookie: admin_sid)
 *   - a non-empty `reason` string (for the audit trail)
 *
 * This is a JSON-only endpoint, so - like PosController - it suppresses
 * HTML error display and buffers all output so a stray warning can never
 * corrupt the JSON response the frontend is expecting.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

// Buffer ALL output from the very start - including session cookie headers side-effects
ob_start();

require_once __DIR__ . '/../Core/Session.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Services/OrderService.php';

// Resume the ADMIN named session (custom cookie: admin_sid)
// WITHOUT redirecting — this is a JSON endpoint, not a page.
Session::start(Session::ADMIN);

class RefundVoidController
{
    // The only two actions this endpoint will ever accept; anything else
    // is rejected outright.
    private const ALLOWED_ACTIONS = ['void', 'refund'];

    // All the actual database mutation (marking order rows void/refunded,
    // restoring inventory if applicable, logging) happens in OrderService.
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

        // Auth check — same keys Auth::requireAdmin() uses.
        // (Implemented manually here, rather than calling Auth::requireAdmin(),
        // because that helper would normally *redirect* on failure - which
        // would send an HTML Location response back to a fetch() call
        // expecting JSON. So this duplicates the check but answers with
        // a JSON error body instead.)
        $user     = Session::get('user', '');
        $position = Session::get('position', '');

        if (!$user || $position !== 'admin') {
            $this->json([
                'success' => false,
                'message' => 'Not authenticated. Please log in again.',
            ]);
            return;
        }

        // Read the JSON body manually (this isn't a normal form POST).
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

        // A reason is mandatory for both void and refund - this is the
        // accountability trail for why money/stock was reversed.
        $reason = trim($payload['reason'] ?? '');
        if ($reason === '') {
            $this->json(['success' => false, 'message' => 'A reason is required to ' . $action . ' an order.']);
            return;
        }

        $payload['reason']     = $reason;
        // Record *who* performed the void/refund, preferring their full
        // name; falling back to whatever session "user" identity string
        // is available, and finally to the literal string 'unknown'.
        $payload['created_by'] = trim(Session::get('firstname', '') . ' ' . Session::get('lastname', ''))
                               ?: Session::get('user', 'unknown');

        try {
            $result = $this->orderService->voidOrRefund($payload);
            $this->json($result);
        } catch (Throwable $e) {
            // Catch absolutely everything here (Throwable, not just
            // Exception) so a fatal error/TypeError still produces valid
            // JSON instead of a half-finished HTML error page.
            $this->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Emit a JSON response and terminate the script, after discarding
     * any buffered output that might otherwise precede/corrupt it.
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

// Global exception safety net — catch any fatal before it produces HTML.
// This is a second, outer layer of protection beyond the try/catch inside
// handle(): if something throws *outside* that try block (e.g. during
// the auth check, JSON decode, or controller construction), this handler
// still guarantees the client receives JSON rather than a broken HTML
// error page with a 500 status and no parseable body.
set_exception_handler(function (Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()]);
    exit();
});

// ── Bootstrap ──────────────────────────────────────────────────────
(new RefundVoidController())->handle();
