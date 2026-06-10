<?php
session_start();
require_once 'functions.php';

$id = $_POST['id'] ?? null;
$qty = max(1, (int)($_POST['qty'] ?? 1));
$return = $_POST['return'] ?? 'cart.php';

if (!$id) {
    header('Location: index.php');
    exit;
}

// Проверяем наличие товара и получаем его данные
$catalog = getCatalogWithCategories();
$found = null;
foreach ($catalog as $catName => $items) {
    foreach ($items as $it) {
        if ((string)($it['id'] ?? '') === (string)$id) {
            $found = $it;
            $found['category'] = $catName;
            break 2;
        }
    }
}

if (!$found) {
    header('Location: index.php');
    exit;
}

// Инициализируем корзину как ассоциативный массив товаров
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Если товар уже есть — увеличить количество, иначе добавить с деталями
if (isset($_SESSION['cart'][$id])) {
    $_SESSION['cart'][$id]['qty'] = max(1, $_SESSION['cart'][$id]['qty'] + $qty);
} else {
    $_SESSION['cart'][$id] = [
        'id'    => $found['id'],
        'name'  => $found['name'],
        'price' => isset($found['price']) ? (float)$found['price'] : 0,
        'image' => $found['image'] ?? '',
        'qty'   => $qty,
    ];
}

// Подсчитать общее количество (если нужно где-то использовать)
$totalCount = 0;
foreach ($_SESSION['cart'] as $c) {
    $totalCount += (int)($c['qty'] ?? 0);
}

// Поддержка AJAX (X-Requested-With) — вернуть JSON
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'count' => $totalCount]);
    exit;
}

// Обычный редирект — используем поле return, если передано, иначе в корзину
header('Location: ' . $return);
exit;
