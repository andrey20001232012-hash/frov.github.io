<?php
session_start();
require_once 'functions.php';

// --- Настройки PDO (подставьте свои параметры) ---
$dbHost = 'localhost';
$dbName = 'flover';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    // Для AJAX возвращаем JSON, для обычного запроса — простое сообщение
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'DB connection error']);
        exit;
    }
    die('DB connection error');
}

// Собираем каталог в ассоциативный массив по id (или name)
$catalogData = function_exists('getCatalogWithCategories') ? getCatalogWithCategories() : [];
$all = [];
foreach ($catalogData as $cat) {
    foreach ($cat as $item) {
        $key = (string)($item['id'] ?? $item['name']);
        $all[$key] = $item;
    }
}

// Вспомогательная экранировка
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Получить запас товара из таблицы flovers
function getStock(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT kol_vo FROM flovers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ? (int)$row['kol_vo'] : 0;
}

// Утилиты
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Обработка действий: добавление, обновление количества или удаление (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = (string)($_POST['id'] ?? '');
    $return = $_POST['return'] ?? ($_SERVER['HTTP_REFERER'] ?? 'cart.php');

    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

    // Добавление в корзину
    if ($action === 'add' && $id) {
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $currentInCart = isset($_SESSION['cart'][$id]) ? (int)$_SESSION['cart'][$id] : 0;
        $stock = getStock($pdo, $id);

        $availableToAdd = max(0, $stock - $currentInCart);
        if ($availableToAdd <= 0) {
            if (isAjax()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Невозможно добавить: на складе отсутствует достаточное количество.', 'available' => 0]);
                exit;
            }
            $_SESSION['flash_error'] = 'Невозможно добавить: на складе отсутствует достаточное количество.';
            header('Location: ' . $return);
            exit;
        }

        $addQty = min($qty, $availableToAdd);
        $_SESSION['cart'][$id] = $currentInCart + $addQty;

        if ($addQty < $qty) {
            $msg = "Добавлено только {$addQty} шт. — больше нет на складе.";
        } else {
            $msg = 'Товар добавлен в корзину';
        }

        // Подсчёт общего количества в корзине
        $cartCount = 0;
        foreach ($_SESSION['cart'] as $cqty) $cartCount += (int)$cqty;

        $newStock = $stock - $_SESSION['cart'][$id];

        if (isAjax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => $msg,
                'cartCount' => $cartCount,
                'newStock' => $newStock
            ]);
            exit;
        }

        $_SESSION['flash_added'] = $id;
        if ($addQty < $qty) $_SESSION['flash_error'] = $msg;
        header('Location: ' . $return);
        exit;
    }

    // Обновление количества
    if ($action === 'update' && $id) {
        $qty = max(0, (int)($_POST['qty'] ?? 0));
        $stock = getStock($pdo, $id);

        if ($qty > $stock) {
            if (isAjax()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => "Невозможно установить количество {$qty}: на складе только {$stock} шт.", 'available' => $stock]);
                exit;
            }
            $_SESSION['flash_error'] = "Невозможно установить количество {$qty}: на складе только {$stock} шт.";
            if ($stock > 0) {
                $_SESSION['cart'][$id] = $stock;
            } else {
                unset($_SESSION['cart'][$id]);
            }
            header('Location: ' . $return);
            exit;
        } else {
            if ($qty === 0) {
                unset($_SESSION['cart'][$id]);
            } else {
                $_SESSION['cart'][$id] = $qty;
            }

            // Подсчёт общего количества в корзине
            $cartCount = 0;
            foreach ($_SESSION['cart'] as $cqty) $cartCount += (int)$cqty;

            if (isAjax()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => 'Количество обновлено', 'cartCount' => $cartCount, 'newStock' => $stock - ($_SESSION['cart'][$id] ?? 0)]);
                exit;
            }

            unset($_SESSION['flash_error']);
            header('Location: ' . $return);
            exit;
        }
    }

    // Удаление
    if ($action === 'remove' && $id) {
        if (isset($_SESSION['cart'][$id])) {
            unset($_SESSION['cart'][$id]);
        }

        // Подсчёт общего количества в корзине
        $cartCount = 0;
        foreach ($_SESSION['cart'] as $cqty) $cartCount += (int)$cqty;

        if (isAjax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'Товар удалён', 'cartCount' => $cartCount]);
            exit;
        }

        header('Location: ' . $return);
        exit;
    }
}

// Текущая корзина и подсчёт общего количества
$cart = $_SESSION['cart'] ?? [];
$total = 0;
$cartCount = 0;
foreach ($cart as $v) $cartCount += (int)$v;

// Подготовка данных корзины: гарантируем, что qty не превышает kol_vo
$cartItems = [];
foreach ($cart as $id => $qty) {
    $id = (string)$id;
    $prod = $all[$id] ?? null;

    if (!$prod) {
        $stmt = $pdo->prepare('SELECT id, name, image, kol_vo, opisanie, price FROM flovers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $prod = [
                'id' => $row['id'],
                'name' => $row['name'],
                'image' => $row['image'],
                'kol_vo' => (int)$row['kol_vo'],
                'opisanie' => $row['opisanie'],
                'price' => $row['price'],
            ];
        }
    } else {
        // если в $all нет kol_vo, попытаемся получить из БД
        if (!isset($prod['kol_vo'])) {
            $prod['kol_vo'] = getStock($pdo, $id);
        }
    }

    if (!$prod) {
        // товар не найден в каталоге и БД — удалим из корзины
        unset($_SESSION['cart'][$id]);
        continue;
    }

    $stock = isset($prod['kol_vo']) ? (int)$prod['kol_vo'] : getStock($pdo, $id);

    // Если в корзине больше, чем на складе — ограничиваем и сохраняем в сессии
    if ($qty > $stock) {
        if ($stock > 0) {
            $_SESSION['cart'][$id] = $stock;
            $qty = $stock;
            $_SESSION['flash_error'] = "Количество товара «" . (e($prod['name'] ?? $id)) . "» было уменьшено до доступного остатка ({$stock} шт.).";
        } else {
            unset($_SESSION['cart'][$id]);
            $_SESSION['flash_error'] = "Товар «" . (e($prod['name'] ?? $id)) . "» удалён — нет в наличии.";
            continue;
        }
    }

    $price = (float)($prod['price'] ?? 0);
    $sum = $price * $qty;
    $total += $sum;

    $img = !empty($prod['image']) ? ('kartinka/' . $prod['image']) : 'icons/no-image.png';

    $cartItems[] = [
        'id' => $id,
        'product' => $prod,
        'qty' => $qty,
        'price' => $price,
        'sum' => $sum,
        'img' => $img,
        'stock' => $stock,
    ];
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Корзина заказов</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.9.0/fonts/remixicon.css">
  <script>
    function updateQty(id) {
      const form = document.getElementById('form-' + id);
      form.submit();
    }
    function removeItem(id) {
      if (!confirm('Удалить товар из корзины?')) return;
      const f = document.getElementById('remove-' + id);
      f.submit();
    }
  </script>
   <style>
    
:root{
  --card:#ffffff;
  --muted:#6b6b6b;
  --accent:#2ecc71;
  --accent-dark:#27b864;
  --danger:#e74c3c;
  --border:#e9e9e9;
  --shadow: 0 6px 18px rgba(21,21,21,0.06);
  --radius:10px;
  --gap:20px;
  --font-sans:"Inter", "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

/* Сброс и базовые правила */
*{box-sizing:border-box}
body{font-family:var(--font-sans);margin:0;background:#f5f7f8;color:#222;line-height:1.45}
.container{max-width:1100px;margin:0 auto;padding:0 16px}

/* Header — увеличенная шапка */
.header__inner{
  display:flex;
  gap:24px;
  align-items:center;
  justify-content:space-between;
  width:100%;
  padding:16px 6px; /* увеличенные вертикальные отступы */
}
.header__brand{display:flex;align-items:center;gap:14px}
.header__logo{
  width:64px;height:64px;object-fit:cover;border-radius:10px;box-shadow:var(--shadow);
}
.header__site-name{font-weight:800;font-size:22px;color:#163a24}

/* Nav */
.header__nav{display:flex;gap:16px;align-items:center}
.header__nav-link{
  color:var(--muted);text-decoration:none;padding:10px 14px;border-radius:8px;font-weight:700;font-size:15px;
}
.header__nav-link:hover,.header__nav-link.active{
  background:rgba(46,204,113,0.08);color:var(--accent-dark);
}

/* Contact block */
.header__contact{display:flex;flex-direction:column;align-items:flex-end;gap:4px}
.header__phone{display:flex;align-items:center;gap:8px;text-decoration:none;color:#222}
.header__phone-icon{width:18px;height:18px;opacity:0.95}
.header__phone-number{font-weight:700;font-size:15px}
.header__work-hours{margin:0;font-size:13px;color:var(--muted)}

/* Actions: search, cart, account */
.header__actions{display:flex;align-items:center;gap:14px}
.header__search-form{display:flex;align-items:center;gap:8px;background:#fff;padding:8px;border-radius:10px;border:1px solid var(--border)}
.header__search-input{border:0;outline:none;padding:10px 12px;width:300px;border-radius:8px;font-size:15px}
.header__search-btn{background:transparent;border:0;color:var(--muted);cursor:pointer;font-size:18px;padding:6px}

.header__quick-action{display:flex;align-items:center;gap:12px}
.header__cart, .header__account{
  display:flex;align-items:center;gap:8px;text-decoration:none;color:#222;padding:8px 10px;border-radius:8px;
}
.header__cart:hover, .header__account:hover{background:rgba(0,0,0,0.03)}
.cart-count{
  background:var(--danger);color:#fff;border-radius:999px;padding:4px 8px;font-size:12px;vertical-align:super;margin-left:6px;
}

/* Основная часть — увеличенные отступы и заголовки */
.main.container{
  padding:28px 16px;       /* увеличенное пространство для основного контента */
}
.section-title{
  font-size:24px;
  margin:0 0 14px;
  color:#163a24;
  font-weight:800;
}

/* Сетка товаров и карточки (чуть крупнее) */
.products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px}
.product-card{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;display:flex;flex-direction:column;box-shadow:var(--shadow)}
.pc-image img{width:100%;height:220px;object-fit:cover;display:block}
.pc-body{padding:16px;flex:1;display:flex;flex-direction:column}
.pc-title{font-size:18px;margin:0 0 8px;font-weight:800}
.pc-meta{color:var(--muted);font-size:13px;margin:0 0 10px}
.pc-price{font-weight:800;margin-top:auto;font-size:18px;color:var(--accent-dark)}
.pc-actions{display:flex;gap:8px;margin-top:12px;align-items:center}

/* Кнопки */
.btn{padding:8px 12px;border-radius:8px;text-decoration:none;border:1px solid #ccc;background:#fff;cursor:pointer;font-weight:700}
.btn-primary{background:var(--accent);color:#fff;border-color:var(--accent)}
.btn-outline{background:#fff}

/* Cart / table styles (оставлены вашими глобальными правилами) */
.cart-page{display:flex;gap:var(--gap);align-items:flex-start;padding:20px 0}
.cart-table{
  width:100%;
  border-collapse:collapse;
  background:var(--card);
  border:1px solid var(--border);
  border-radius:12px;
  overflow:hidden;
  box-shadow:var(--shadow);
}
.cart-table thead{background:linear-gradient(90deg, rgba(46,204,113,0.04), rgba(46,204,113,0.02));}
.cart-table th, .cart-table td{
  padding:14px 12px;text-align:left;vertical-align:middle;border-bottom:1px solid rgba(0,0,0,0.03);font-size:14px;color:#222;
}
.cart-table th{font-weight:700;color:#163a24;font-size:13px}
.cart-table tbody tr:last-child td{border-bottom:0}
.cart-table img{display:block;max-width:100px;height:auto;border-radius:8px;object-fit:cover;box-shadow:0 6px 18px rgba(16,16,16,0.04)}
.cart-table input[type="number"]{width:84px;padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:14px}
.cart-table input[type="number"]::-webkit-outer-spin-button,.cart-table input[type="number"]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.delete-btn{background:transparent;border:1px solid var(--border);color:#333;padding:8px 10px;border-radius:8px;cursor:pointer;font-weight:600;}
.delete-btn:hover{background:rgba(231,76,60,0.06);border-color:rgba(231,76,60,0.14);color:var(--danger)}
.cart-sidebar{width:320px;min-width:260px;background:var(--card);border:1px solid var(--border);padding:18px;border-radius:12px;box-shadow:var(--shadow);margin-left:20px;height:max-content;display:flex;flex-direction:column;gap:12px}
.cart-sidebar .cart-total{margin:0;font-size:22px;color:#163a24;font-weight:800}
.checkout-btn{width:100%;padding:12px 14px;border-radius:10px;background:var(--accent);color:#fff;border:0;font-weight:700;cursor:pointer}
.checkout-btn:hover{background:var(--accent-dark)}
.cart-sidebar a{color:var(--muted);text-decoration:none;font-size:14px}
.cart-table td[colspan]{text-align:center;padding:28px;color:var(--muted);font-weight:600}
.price, .sum{font-weight:700;color:var(--accent-dark);white-space:nowrap}



/* Utilities */
.text-muted{color:var(--muted)}
.small{font-size:13px}

/* Адаптив */
@media (max-width:1024px){
  .cart-sidebar{width:300px}
}
@media (max-width:820px){
  .header__contact{display:none}
  .header__search-input{width:160px}
  .products-grid{grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
  .pc-image img{height:180px}
  .main.container{padding:20px 12px}
}
@media (max-width:520px){
  .header__inner{flex-direction:column;align-items:flex-start;gap:12px;padding:12px}
  .header__logo{width:48px;height:48px}
  .header__site-name{font-size:18px}
  .header__search-input{width:100%}
  .products-grid{grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px}
  .pc-image img{height:140px}
  .section-title{font-size:20px}
  .cart-table th:nth-child(1), .cart-table td:nth-child(1){display:none}
  .cart-table th:nth-child(4), .cart-table td:nth-child(4){display:none}
  .cart-table th, .cart-table td{padding:10px}
  .cart-sidebar{padding:14px;width:100%;order:2;margin-left:0}
}

/* Базовая типографика (наследует body из style.css) */
*{box-sizing:border-box}
.cart-page{display:flex;gap:var(--gap);align-items:flex-start;padding:20px 0}
.cart-table{
  width:100%;
  border-collapse:collapse;
  background:var(--card);
  border:1px solid var(--border);
  border-radius:12px;
  overflow:hidden;
  box-shadow:var(--shadow);
}
.cart-table thead{
  background:linear-gradient(90deg, rgba(46,204,113,0.04), rgba(46,204,113,0.02));
}
.cart-table th, .cart-table td{
  padding:14px 12px;
  text-align:left;
  vertical-align:middle;
  border-bottom:1px solid rgba(0,0,0,0.03);
  font-size:14px;
  color:#222;
}
.cart-table th{font-weight:700;color:#163a24;font-size:13px}
.cart-table tbody tr:last-child td{border-bottom:0}

/* Изображения в таблице */
.cart-table img{display:block;max-width:100px;height:auto;border-radius:8px;object-fit:cover;box-shadow:0 6px 18px rgba(16,16,16,0.04)}

/* Ссылки и названия */
.cart-table a{color:var(--accent-dark);text-decoration:none;font-weight:700}
.cart-table a:hover{text-decoration:underline}

/* Количество — input */
.cart-table input[type="number"]{
  width:84px;
  padding:8px 10px;
  border:1px solid var(--border);
  border-radius:8px;
  font-size:14px;
  
}
.cart-table input[type="number"]::-webkit-outer-spin-button,
.cart-table input[type="number"]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}

/* Кнопка удаления */
.delete-btn{
  background:transparent;border:1px solid var(--border);color:#333;padding:8px 10px;border-radius:8px;cursor:pointer;font-weight:600;
}
.delete-btn:hover{background:rgba(231,76,60,0.06);border-color:rgba(231,76,60,0.14);color:var(--danger)}

/* Сайдбар с итогом */
.cart-sidebar{
  width:320px;
  min-width:260px;
  background:var(--card);
  border:1px solid var(--border);
  padding:18px;
  border-radius:12px;
  box-shadow:var(--shadow);
  margin-left:20px;
  height:max-content;
  display:flex;
  flex-direction:column;
  gap:12px;
}
.cart-sidebar .cart-total{
  margin:0;font-size:22px;color:#163a24;font-weight:800;
}
.checkout-btn{
  width:100%;
  padding:12px 14px;
  border-radius:10px;
  background:var(--accent);
  color:#fff;
  border:0;
  font-weight:700;
  cursor:pointer;
}
.checkout-btn:hover{background:var(--accent-dark)}
.cart-sidebar a{color:var(--muted);text-decoration:none;font-size:14px}

/* Пустая корзина */
.cart-table td[colspan]{
  text-align:center;
  padding:28px;
  color:var(--muted);
  font-weight:600;
}

/* Мелкие стили для сумм/цен */
.price, .sum{
  font-weight:700;color:var(--accent-dark);
  white-space:nowrap;
}


/* Небольшие утилиты */
.text-muted{color:var(--muted)}
.small{font-size:13px}

/* Header layout for .header__inner and children (matches your theme variables) */
.header__inner{
  display:flex;
  gap:20px;
  align-items:center;
  justify-content:space-between;
  width:100%;
  padding:0 6px;
}

/* Brand */
.header__brand{display:flex;align-items:center;gap:12px}
.header__logo{
  width:46px;height:46px;object-fit:cover;border-radius:8px;box-shadow:var(--shadow);
}
.header__site-name{font-weight:700;font-size:18px;color:#222}

/* Nav */
.header__nav{display:flex;gap:14px;align-items:center}
.header__nav-link{
  color:var(--muted);text-decoration:none;padding:8px 12px;border-radius:8px;font-weight:600;
}
.header__nav-link:hover,.header__nav-link.active{
  background:rgba(46,204,113,0.08);color:var(--accent-dark);
}

/* Contact block */
.header__contact{display:flex;flex-direction:column;align-items:flex-end;gap:2px}
.header__phone{display:flex;align-items:center;gap:8px;text-decoration:none;color:#222}
.header__phone-icon{width:18px;height:18px;opacity:0.9}
.header__work-hours{margin:0;font-size:12px;color:var(--muted)}

/* Actions: search, cart, account */
.header__actions{display:flex;align-items:center;gap:12px}
.header__search-form{display:flex;align-items:center;gap:8px;background:#fff;padding:6px;border-radius:8px;border:1px solid var(--border)}
.header__search-input{border:0;outline:none;padding:8px 10px;width:220px;border-radius:6px;font-size:14px}
.header__search-btn{background:transparent;border:0;color:var(--muted);cursor:pointer;font-size:18px;padding:6px}

.header__quick-action{display:flex;align-items:center;gap:12px}
.header__cart, .header__account{
  display:flex;align-items:center;gap:8px;text-decoration:none;color:#222;padding:6px 10px;border-radius:8px;
}
.header__cart:hover, .header__account:hover{background:rgba(0,0,0,0.03)}
.cart-count{
  background:var(--danger);color:#fff;border-radius:999px;padding:2px 6px;font-size:12px;vertical-align:super;margin-left:6px;
}

/* Responsive tweaks */
@media (max-width:820px){
  .header__contact{display:none}
  .header__search-input{width:140px}
}
@media (max-width:520px){
  .header__inner{flex-direction:column;align-items:flex-start;gap:12px}
  .header__actions{width:100%;justify-content:space-between}
  .header__search-input{width:100%}
}
.footer {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  background: #fff;
  border-top: 1px solid #e4e7ea;
  padding: 8px 0;
  font-size: 12px;
  color: #666;
  z-index: 999;
}

.footer .container {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 16px;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 32px;
}
.checkout-btn[disabled]{opacity:0.55;cursor:not-allowed;pointer-events:none}
a[aria-disabled="true"]{pointer-events:none}

  </style>
</head>
<body>
<header class="header">
  <div class="container">
    <div class="header__inner">
      <div class="header__brand">
        <img src="icons/logo.png" alt="Логотип Флориста" class="header__logo">
        <span class="header__site-name">Флорист</span>
      </div>
      <nav class="header__nav">
        <a href="index.php" class="header__nav-link">Главная</a>
        <a href="zakaz.php" class="header__nav-link">Заказы</a>
      </nav>
      <div class="header__actions">
        <form class="header__search-form" action="index.php" method="GET">
          <input type="search" name="q" placeholder="Поиск..." class="header__search-input">
          <button type="submit" class="header__search-btn"><i class="ri-search-line"></i></button>
        </form>
        <div class="header__quick-action">
          <a href="cart.php" class="header__cart"><i class="ri-shopping-basket-line"></i><span>Корзина</span><sup class="cart-count"><?php echo $cartCount ?: ''; ?></sup></a>
        </div>
      </div>
    </div>
  </div>
</header>

<main class="main container">
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="notice error"><?php echo e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_added'])): ?>
    <div class="notice success">Товар добавлен в корзину.</div>
    <?php unset($_SESSION['flash_added']); ?>
  <?php endif; ?>

  <section class="cart-page">
    <table class="cart-table">
      <thead>
        <tr>
          <th>Изображение</th>
          <th>Название</th>
          <th>Количество</th>
          <th>Цена за единицу</th>
          <th>Общая стоимость</th>
          <th>Действие</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($cartItems)): ?>
        <tr><td colspan="6">Корзина пуста.</td></tr>
      <?php else: ?>
        <?php foreach ($cartItems as $row):
          $id = e($row['id']);
          $it = $row['product'];
        ?>
        <tr>
          <td><img src="<?php echo e($row['img']); ?>" alt="<?php echo e($it['name'] ?? ''); ?>" style="max-width:100px;"></td>
          <td><a href="product.php?id=<?php echo urlencode($id); ?>"><?php echo e($it['name'] ?? $id); ?></a></td>
          <td>
            <form id="form-<?php echo $id; ?>" method="POST" action="cart.php">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <input type="number" name="qty" value="<?php echo (int)$row['qty']; ?>" min="1" max="<?php echo (int)$row['stock']; ?>" onchange="updateQty('<?php echo $id; ?>')">
              <div style="font-size:12px;color:#666;">В наличии: <?php echo (int)$row['stock']; ?> шт.</div>
            </form>
          </td>
          <td><?php echo number_format($row['price'],0,'',' '); ?> ₽</td>
          <td><?php echo number_format($row['sum'],0,'',' '); ?> ₽</td>
          <td>
            <form id="remove-<?php echo $id; ?>" method="POST" action="cart.php" style="display:inline;">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <button type="button" onclick="removeItem('<?php echo $id; ?>')">Удалить</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>

    <div class="cart-sidebar">
  <h2 class="cart-total">Итого: <?php echo number_format($total,0,'',' '); ?> ₽</h2>

  <?php $canCheckout = ($total > 0 && !empty($cartItems)); ?>
  <a href="<?php echo $canCheckout ? 'oformlenie.php' : '#'; ?>" <?php if (!$canCheckout) echo 'aria-disabled="true"'; ?>>
    <button class="checkout-btn" <?php if (!$canCheckout) echo 'disabled'; ?>>Оформить заказ</button>
  </a>

  <?php if (!$canCheckout): ?>
    <p style="color:var(--muted);font-size:14px;margin:8px 0 0;">Корзина пуста — добавьте товары, чтобы оформить заказ.</p>
    <p><a href="index.php">Продолжить покупки</a></p>
  <?php else: ?>
    <p><a href="index.php">Продолжить покупки</a></p>
  <?php endif; ?>
</div>

  </section>
</main>

<footer class="footer">
  <div class="container">
    <div class="footer__inner">
      <p class="footer__copyright">© <?php echo date("Y"); ?> Интернет-магазин флористики. Все права защищены.</p>
    </div>
  </div>
</footer>
</body>
</html>
