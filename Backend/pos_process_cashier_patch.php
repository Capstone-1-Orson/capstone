<?php
// ================================================================
// PATCH for Backend/pos_process.php
// Add these lines to save the cashier_id when placing an order
// ================================================================

// ── Near the top of pos_process.php, after session_start() ──────
// Resolve the logged-in user's DB id from session email
$cashier_id = null;
$email_key  = $_SESSION['user'] ?? '';
if ($email_key) {
    $uStmt = $conn->prepare("SELECT id FROM user WHERE email = ? OR firstname = ? LIMIT 1");
    $uStmt->bind_param('ss', $email_key, $email_key);
    $uStmt->execute();
    $uStmt->bind_result($cashier_id);
    $uStmt->fetch();
    $uStmt->close();
}

// ── When inserting into orders, include cashier_id ───────────────
// BEFORE (typical insert):
//   $stmt = $conn->prepare(
//       "INSERT INTO orders (table_no, status, total_amt, discount_amt, discount_type)
//        VALUES (?, 'pending', ?, ?, ?)"
//   );
//   $stmt->bind_param('sdds', $table_no, $total_amt, $discount_amt, $discount_type);

// AFTER (with cashier_id):
//   $stmt = $conn->prepare(
//       "INSERT INTO orders (table_no, cashier_id, status, total_amt, discount_amt, discount_type)
//        VALUES (?, ?, 'pending', ?, ?, ?)"
//   );
//   $stmt->bind_param('sidsds', $table_no, $cashier_id, $total_amt, $discount_amt, $discount_type);
//   NOTE: 'i' = integer for cashier_id
