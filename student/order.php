<?php
// This page uses the student_id established by student/login.php.
session_start();

if (empty($_SESSION['student_id']) || !filter_var($_SESSION['student_id'], FILTER_VALIDATE_INT) || (int) $_SESSION['student_id'] < 1) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/database.php';

$studentId = (int) $_SESSION['student_id'];
if (empty($_SESSION['food_order_csrf'])) {
    $_SESSION['food_order_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$orderId = isset($_GET['order']) && ctype_digit((string) $_GET['order']) ? (int) $_GET['order'] : 0;
$orderConfirmation = null;

try {
    $pdo = getDatabaseConnection();

    // Display a confirmation only when this order belongs to the logged-in student.
    if ($orderId > 0) {
        $confirmationQuery = $pdo->prepare(
            'SELECT id, total_amount, created_at
             FROM food_orders
             WHERE id = :order_id AND student_id = :student_id
             LIMIT 1'
        );
        $confirmationQuery->execute(['order_id' => $orderId, 'student_id' => $studentId]);
        $orderConfirmation = $confirmationQuery->fetch() ?: null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        $quantities = $_POST['quantity'] ?? [];

        if (!is_string($csrfToken) || !hash_equals($_SESSION['food_order_csrf'], $csrfToken)) {
            $error = 'Your session expired. Refresh the page and try again.';
        } elseif (!is_array($quantities) || count($quantities) > 50) {
            $error = 'Your order could not be processed. Please try again.';
        } else {
            $requested = [];
            foreach ($quantities as $rawItemId => $rawQuantity) {
                if (!ctype_digit((string) $rawItemId) || !ctype_digit((string) $rawQuantity)) {
                    $error = 'Please select valid menu quantities.';
                    break;
                }

                $itemId = (int) $rawItemId;
                $quantity = (int) $rawQuantity;
                if ($itemId < 1 || $quantity < 0 || $quantity > 20) {
                    $error = 'Choose between 1 and 20 of each item.';
                    break;
                }
                if ($quantity > 0) {
                    $requested[$itemId] = $quantity;
                }
            }

            if ($error === '' && $requested === []) {
                $error = 'Choose at least one menu item.';
            }

            if ($error === '') {
                $pdo->beginTransaction();
                $placeholders = implode(',', array_fill(0, count($requested), '?'));
                $itemQuery = $pdo->prepare(
                    "SELECT id, name, price
                     FROM food_items
                     WHERE is_available = 1 AND id IN ($placeholders)
                     FOR UPDATE"
                );
                $itemQuery->execute(array_keys($requested));
                $items = $itemQuery->fetchAll();

                if (count($items) !== count($requested)) {
                    throw new RuntimeException('One or more selected menu items are no longer available.');
                }

                $totalCents = 0;
                $lines = [];
                foreach ($items as $item) {
                    $priceCents = (int) round(((float) $item['price']) * 100);
                    $quantity = $requested[(int) $item['id']];
                    $lineCents = $priceCents * $quantity;
                    $totalCents += $lineCents;
                    $lines[] = [
                        'food_item_id' => (int) $item['id'],
                        'item_name' => $item['name'],
                        'unit_price' => number_format($priceCents / 100, 2, '.', ''),
                        'quantity' => $quantity,
                        'line_total' => number_format($lineCents / 100, 2, '.', ''),
                    ];
                }

                $insertOrder = $pdo->prepare(
                    'INSERT INTO food_orders (student_id, total_amount)
                     VALUES (:student_id, :total_amount)'
                );
                $insertOrder->execute([
                    'student_id' => $studentId,
                    'total_amount' => number_format($totalCents / 100, 2, '.', ''),
                ]);
                $newOrderId = (int) $pdo->lastInsertId();

                $insertLine = $pdo->prepare(
                    'INSERT INTO food_order_items
                        (order_id, food_item_id, item_name, unit_price, quantity, line_total)
                     VALUES
                        (:order_id, :food_item_id, :item_name, :unit_price, :quantity, :line_total)'
                );
                foreach ($lines as $line) {
                    $insertLine->execute([
                        'order_id' => $newOrderId,
                        'food_item_id' => $line['food_item_id'],
                        'item_name' => $line['item_name'],
                        'unit_price' => $line['unit_price'],
                        'quantity' => $line['quantity'],
                        'line_total' => $line['line_total'],
                    ]);
                }

                $pdo->commit();
                header('Location: order.php?order=' . $newOrderId);
                exit;
            }
        }
    }

    $menuQuery = $pdo->query(
        'SELECT id, name, description, price
         FROM food_items
         WHERE is_available = 1
         ORDER BY name'
    );
    $menuItems = $menuQuery->fetchAll();
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Student food order error: ' . $exception->getMessage());
    if ($error === '') {
        $error = 'Ordering is temporarily unavailable. Please try again later.';
    }
    $menuItems = $menuItems ?? [];
}

function orderHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Place a food order</title>
</head>
<body>
<main>
    <h1>Place a food order</h1>

    <?php if ($error !== ''): ?>
        <p role="alert"><?= orderHtml($error) ?></p>
    <?php endif; ?>

    <?php if ($orderConfirmation !== null): ?>
        <p role="status">Order #<?= (int) $orderConfirmation['id'] ?> placed successfully. Total: $<?= orderHtml($orderConfirmation['total_amount']) ?>.</p>
    <?php endif; ?>

    <?php if ($menuItems === []): ?>
        <p>No menu items are currently available.</p>
    <?php else: ?>
        <form method="post" action="order.php">
            <input type="hidden" name="csrf_token" value="<?= orderHtml($_SESSION['food_order_csrf']) ?>">
            <?php foreach ($menuItems as $item): ?>
                <fieldset>
                    <legend><?= orderHtml($item['name']) ?> — $<?= orderHtml($item['price']) ?></legend>
                    <?php if ($item['description'] !== null && $item['description'] !== ''): ?>
                        <p><?= orderHtml($item['description']) ?></p>
                    <?php endif; ?>
                    <label for="quantity-<?= (int) $item['id'] ?>">Quantity</label>
                    <input
                        type="number"
                        id="quantity-<?= (int) $item['id'] ?>"
                        name="quantity[<?= (int) $item['id'] ?>]"
                        min="0"
                        max="20"
                        value="0"
                    >
                </fieldset>
            <?php endforeach; ?>
            <button type="submit">Place order</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
