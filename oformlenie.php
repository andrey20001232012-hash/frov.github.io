<?php
session_start();
require_once 'functions.php';

// helper
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// toggle assemble (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_assemble') {
    $assemble = (isset($_POST['assemble']) && $_POST['assemble'] === '1') ? 1 : 0;
    $_SESSION['assemble'] = $assemble;
    $cart = $_SESSION['cart'] ?? [];
    $catalogData = getCatalogWithCategories();
    $all = [];
    foreach ($catalogData as $cat) {
        foreach ($cat as $item) {
            $key = $item['id'] ?? $item['name'];
            $all[$key] = $item;
        }
    }
    $cartTotal = 0;
    foreach ($cart as $cid => $cqty) {
        if (!isset($all[$cid])) continue;
        $price = (int)($all[$cid]['price'] ?? 0);
        $qty = max(0, (int)$cqty);
        $cartTotal += $price * $qty;
    }
    $deliveryCost = 300;
    $assembleCost = $assemble ? 300 : 0;
    $total = $cartTotal + $deliveryCost + $assembleCost;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'assemble' => $assemble,
        'cartTotal' => $cartTotal,
        'deliveryCost' => $deliveryCost,
        'assembleCost' => $assembleCost,
        'total' => $total,
        'totalFormatted' => number_format($total, 0, '', ' ') . ' ₽'
    ]);
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_token'];

// Load catalog / cart
$catalogData = getCatalogWithCategories();
$all = [];
foreach ($catalogData as $cat) {
    foreach ($cat as $item) {
        $key = $item['id'] ?? $item['name'];
        $all[$key] = $item;
    }
}
$cart = $_SESSION['cart'] ?? [];
$cartItems = [];
$cartTotal = 0;
foreach ($cart as $cid => $cqty) {
    if (!isset($all[$cid])) continue;
    $price = (int)($all[$cid]['price'] ?? 0);
    $qty = max(0, (int)$cqty);
    $sum = $price * $qty;
    $cartTotal += $sum;
    $cartItems[] = ['id' => (int)$cid, 'name' => $all[$cid]['name'], 'price' => $price, 'qty' => $qty, 'sum' => $sum];
}

// defaults
$assembleSelected = !empty($_SESSION['assemble']);
$assembleCost = $assembleSelected ? 300 : 0;
$orderSuccess = '';
$orderError = '';

// helper for delivery (keeps storing address from POST)
function getDelivery(): array {
    if (!empty($_POST['address'])) {
        $_SESSION['address'] = trim($_POST['address']);
    }
    return [300, 'Доставка (Невинномысск)'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $errors = [];

    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)$token)) {
        $errors[] = 'Ошибка безопасности (CSRF).';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneRaw = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $delivery_date = trim($_POST['delivery_date'] ?? '');
    $delivery_time = trim($_POST['delivery_time'] ?? '');
    $assemble = isset($_SESSION['assemble']) ? ($_SESSION['assemble'] == 1) : ((isset($_POST['assemble']) && $_POST['assemble'] === '1') ? true : false);

    // required fields
    if ($name === '') $errors[] = 'Введите имя.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Неверный e-mail.';
    if ($address === '') $errors[] = 'Введите адрес доставки.';
    if ($address !== '' && mb_stripos($address, 'невинномысск') === false) $errors[] = 'Доставка доступна только в Невинномысске. Укажите адрес в Невинномысске.';

    // normalize phone: keep digits only
    $phoneDigits = preg_replace('/\D+/', '', $phoneRaw);
    if (strlen($phoneDigits) === 11 && $phoneDigits[0] === '8') {
        $phoneDigits = '7' . substr($phoneDigits, 1);
    } elseif (strlen($phoneDigits) === 11 && $phoneDigits[0] === '7') {
        // ok
    } elseif (strlen($phoneDigits) === 10) {
        $phoneDigits = '7' . $phoneDigits;
    }
    if (!(strlen($phoneDigits) === 11 && $phoneDigits[0] === '7')) {
        $errors[] = 'Неверный телефон. Укажите корректный номер (например +7 (912) 345-67-89).';
    }
    // format normalized phone for DB
    $phone = '+'.$phoneDigits;

    // date validation (optional field)
    if ($delivery_date !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $delivery_date)) {
            $errors[] = 'Некорректная дата доставки.';
        } else {
            $d = DateTime::createFromFormat('Y-m-d', $delivery_date);
            if (!$d) {
                $errors[] = 'Неверная дата доставки.';
            } else {
                $d->setTime(0,0,0);
                $today = (new DateTime())->setTime(0,0,0);
                if ($d < $today) $errors[] = 'Дата доставки не может быть в прошлом.';
            }
        }
    }
    if ($delivery_time !== '' && !preg_match('/^\d{2}:\d{2}$/', $delivery_time)) {
        $errors[] = 'Некорректное время доставки.';
    }
    if ($delivery_date !== '' && $delivery_time === '') {
        $errors[] = 'Выберите время доставки.';
    }
    if (empty($cartItems)) $errors[] = 'Корзина пуста.';

    if (!empty($errors)) {
        $orderError = implode(' ', array_map('htmlspecialchars', $errors));
    } else {
        list($deliveryCost, $deliveryLabel) = getDelivery();
        $conn = getDatabaseConnection();
        if (!$conn) {
            $orderError = 'Ошибка соединения с базой данных.';
        } else {
            $conn->begin_transaction();
            try {
                $parts = [];
                if ($comment !== '') $parts[] = $comment;
                if ($assemble) $parts[] = 'Требуется сборка букета';
                if ($delivery_date !== '') {
                    $dt = $delivery_date;
                    $tt = $delivery_time !== '' ? " в {$delivery_time}" : '';
                    $parts[] = "Доставка: {$dt}{$tt}";
                }
                $fullComment = implode(' | ', $parts);

                $stmt = $conn->prepare('INSERT INTO client (name, email, phone, address, comment) VALUES (?, ?, ?, ?, ?)');
                if ($stmt === false) throw new Exception('prepare client failed: ' . $conn->error);
                $stmt->bind_param('sssss', $name, $email, $phone, $address, $fullComment);
                if (!$stmt->execute()) throw new Exception('execute client failed: ' . $stmt->error);
                $clientId = $stmt->insert_id;
                $stmt->close();

                $insF = $conn->prepare('INSERT INTO zakaz_flover (id_flover, kol_vo, sum) VALUES (?, ?, ?)');
                if ($insF === false) throw new Exception('prepare zakaz_flover failed: ' . $conn->error);
                $updStock = $conn->prepare('UPDATE flovers SET kol_vo = kol_vo - ? WHERE id = ? AND kol_vo >= ?');
                if ($updStock === false) throw new Exception('prepare update flovers failed: ' . $conn->error);
                $insertedZakazFloverIds = [];
                foreach ($cartItems as $it) {
                    $id_f = (int)$it['id'];
                    $k = (int)$it['qty'];
                    $s = (int)$it['sum'];
                    $insF->bind_param('iii', $id_f, $k, $s);
                    if (!$insF->execute()) throw new Exception('execute zakaz_flover failed: ' . $insF->error);
                    $insertedZakazFloverIds[] = $insF->insert_id;
                    $updStock->bind_param('iii', $k, $id_f, $k);
                    if (!$updStock->execute()) throw new Exception('execute update flovers failed: ' . $updStock->error);
                    if ($updStock->affected_rows === 0) throw new Exception('Недостаточно товара на складе для товара ID ' . $id_f);
                }
                $insF->close();
                $updStock->close();

                $firstZakazFloverId = $insertedZakazFloverIds[0] ?? 0;
                $dateForDb = $delivery_date === '' ? date('Y-m-d') : $delivery_date;
                $summForDb = $cartTotal + $deliveryCost + ($assemble ? 300 : 0);

                if ($delivery_time !== '') {
                    $timeForDb = preg_replace('/^(\d{2}:\d{2}).*$/', '$1:00', $delivery_time);
                    $stmt = $conn->prepare('INSERT INTO zakaz (id_client, id_zakaz_flover, summ, date, time, buket) VALUES (?, ?, ?, ?, ?, ?)');
                    if ($stmt === false) throw new Exception('prepare zakaz failed: ' . $conn->error);
                    $buketValue = $assemble ? 'да' : 'нет';
                    $stmt->bind_param('iiisss', $clientId, $firstZakazFloverId, $summForDb, $dateForDb, $timeForDb, $buketValue);
                } else {
                    $stmt = $conn->prepare('INSERT INTO zakaz (id_client, id_zakaz_flover, summ, date, buket) VALUES (?, ?, ?, ?, ?)');
                    if ($stmt === false) throw new Exception('prepare zakaz failed: ' . $conn->error);
                    $buketValue = $assemble ? 'да' : 'нет';
                    $stmt->bind_param('iiiss', $clientId, $firstZakazFloverId, $summForDb, $dateForDb, $buketValue);
                }
                if (!$stmt->execute()) throw new Exception('execute zakaz failed: ' . $stmt->error);
                $zakazId = $stmt->insert_id;
                $stmt->close();

                $statusText = 'на проверке';
                $stmt = $conn->prepare('INSERT INTO status_zakaza (id_zakaz_flover, status) VALUES (?, ?)');
                if ($stmt === false) throw new Exception('prepare status_zakaza failed: ' . $conn->error);
                $stmt->bind_param('is', $zakazId, $statusText);
                if (!$stmt->execute()) throw new Exception('execute status_zakaza failed: ' . $stmt->error);
                $stmt->close();

                $conn->commit();

                unset($_SESSION['cart']);
                unset($_SESSION['assemble']);
                $cart = [];
                $cartItems = [];
                $cartTotal = 0;
                $assembleSelected = false;
                $assembleCost = 0;

                $orderSuccess = 'Спасибо! Ваш заказ №' . $zakazId . ' принят. Сумма: ' . number_format($summForDb, 0, '', ' ') . ' ₽';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
                $csrf = $_SESSION['csrf_token'];
            } catch (Exception $e) {
                $conn->rollback();
                error_log('Order save error: ' . $e->getMessage());
                $orderError = 'Ошибка при сохранении заказа.';
            } finally {
                $conn->close();
            }
        }
    }
}

list($deliveryCostDisplay, $deliveryLabelDisplay) = getDelivery();
$deliveryCostDisplay = 300;
$deliveryLabelDisplay = 'Доставка (Невинномысск)';
$totalWithDelivery = $cartTotal + $deliveryCostDisplay + ($assembleSelected ? 300 : 0);
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Оформление заказа</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
   <style>
    :root{
      --bg:#f6fbf7;
      --text:#163b2b;
      --muted:#6b8b7a;
      --card:#ffffff;
      --input-bg:#fbfff9;
      --border:#dceee3;
      --accent-1:#2b8f5a;
      --accent-2:#217a4a;
      --success-bg:#e8f9ee;
      --danger-bg:#fff6f6;
    }

    *{box-sizing:border-box}
    body{
      margin:0;
      font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      background:var(--bg);
      color:var(--text);
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
      padding:28px;
    }
    .container{
      max-width:980px;
      margin:0 auto;
      display:flex;
      gap:20px;
      align-items:flex-start;
      padding:20px;
      border-radius:12px;
      background:linear-gradient(180deg, rgba(255,255,255,0.4), transparent);
    }
    .layout{display:flex;gap:20px;align-items:flex-start;width:100%}
    .card{
      background:var(--card);
      padding:18px;
      border-radius:10px;
      box-shadow:0 6px 18px rgba(33,122,74,0.06);
      border:1px solid #e6f3ec;
      flex:1;
    }
    .mini-cart{
      width:320px;
      flex:0 0 320px;
    }
    h1{font-size:20px;margin:0 0 12px;color:var(--accent-2)}
    h2{font-size:16px;margin:0 0 10px;color:var(--text)}
    .form-row{margin-bottom:12px}
    label{display:block;margin-bottom:6px;font-weight:600;color:var(--text);font-size:13px}
    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="date"],
    select,
    textarea{
      width:100%;
      padding:10px 12px;
      border:1px solid var(--border);
      border-radius:8px;
      background:var(--input-bg);
      color:var(--text);
      font-size:14px;
      outline:none;
      transition:box-shadow .15s, border-color .15s;
    }
    input:focus, select:focus, textarea:focus{
      border-color:var(--accent-1);
      box-shadow:0 6px 18px rgba(43,143,90,0.08);
    }
    .btn-row{display:flex;gap:10px;align-items:center;margin-top:12px}
    button{
      background:linear-gradient(180deg,var(--accent-1),var(--accent-2));
      color:#fff;
      border:0;
      padding:10px 14px;
      border-radius:10px;
      font-weight:700;
      cursor:pointer;
      font-size:14px;
      box-shadow:0 6px 18px rgba(33,122,74,0.12);
    }
    button.secondary{
      background:#eaf7ef;
      color:var(--accent-2);
      border:1px solid #d3eedf;
      font-weight:700;
    }
    .muted{color:var(--muted);font-size:13px}
    .success{background:var(--success-bg);padding:10px;border-radius:8px;color:var(--accent-1);margin-bottom:12px;border:1px solid #cfeedb}
    .error{background:var(--danger-bg);padding:10px;border-radius:8px;color:#8a1f2b;margin-bottom:12px;border:1px solid #f5d6d6}
    .item{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f0f6f2;color:var(--text);align-items:center}
    .item:last-child{border-bottom:0}
    .item .meta{font-size:13px;color:var(--muted)}
    .total{display:flex;justify-content:space-between;padding-top:12px;font-weight:700;border-top:1px dashed #e9f3ec;margin-top:12px}
    @media (max-width:860px){
      .layout{flex-direction:column}
      .mini-cart{width:100%;flex:1}
    }
  </style>
</head>
<body>
  <h1>Оформление заказа</h1>
  <?php if ($orderSuccess): ?><div class="success"><?php echo e($orderSuccess); ?></div><?php endif; ?>
  <?php if ($orderError): ?><div class="error"><?php echo e($orderError); ?></div><?php endif; ?>
  <div class="container">
    <div class="card">
      <form method="post" action="oformlenie.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
        <div class="form-row">
          <label for="name">Имя</label>
          <input id="name" name="name" type="text" required placeholder="Иван Иванов" value="<?php echo e($_POST['name'] ?? ''); ?>">
        </div>
        <div class="form-row">
          <label for="email">Электронная почта</label>
          <input id="email" name="email" type="email" required placeholder="ivan@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>">
        </div>
        <div class="form-row">
          <label for="phone">Телефон</label>
          <input id="phone" name="phone" type="tel" required placeholder="+7 (___) ___-__-__" value="<?php echo e($_POST['phone'] ?? ''); ?>">
        </div>
        <div class="form-row">
          <label for="address">Адрес доставки (только Невинномысск)</label>
          <input id="address" name="address" type="text" required placeholder="Невинномысск, улица, дом, кв." value="<?php echo e($_POST['address'] ?? $_SESSION['address'] ?? ''); ?>">
        </div>
        <div class="form-row">
          <label for="delivery_date">Дата доставки</label>
          <input id="delivery_date" name="delivery_date" type="date" value="<?php echo e($_POST['delivery_date'] ?? ''); ?>">
        </div>
        <div class="form-row">
          <label for="delivery_time">Время доставки (час)</label>
          <select id="delivery_time" name="delivery_time">
            <option value="">—</option>
            <?php for ($h = 9; $h <= 19; $h++): $v = sprintf('%02d:00', $h); ?>
              <option value="<?php echo $v; ?>" <?php if(($_POST['delivery_time'] ?? '') === $v) echo 'selected'; ?>><?php echo $v; ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-row">
          <label for="comment">Комментарий</label>
          <textarea id="comment" name="comment" rows="4" placeholder="Пожелания к букету..."><?php echo e($_POST['comment'] ?? ''); ?></textarea>
        </div>
        <div class="form-row" style="display:flex;align-items:center;gap:8px">
          <input id="assemble" name="assemble" type="checkbox" value="1" <?php if($assembleSelected) echo 'checked'; ?>>
          <label for="assemble" style="margin:0">Нужно собрать букет (+300 ₽)</label>
        </div>
        <div class="btn-row" style="margin-top:14px">
          <button id="submitBtn" type="submit">Подтвердить заказ — <?php echo number_format($totalWithDelivery, 0, '', ' '); ?> ₽</button>
          <a href="cart.php" style="text-decoration:none;color:#6b2740;padding:10px 12px;border:1px solid #6b2740;border-radius:6px;margin-left:10px;display:inline-block">Открыть корзину</a>
        </div>
      </form>
    </div>

    <aside class="card mini-cart" aria-live="polite">
      <strong>Содержимое корзины</strong>
      <?php if (empty($cartItems)): ?>
        <div>Корзина пуста.</div>
      <?php else: ?>
        <?php foreach ($cartItems as $it): ?>
          <div class="item" style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f5f5f5">
            <div>
              <div style="font-weight:600"><?php echo e($it['name']); ?></div>
              <div style="font-size:13px;color:#7a5a63">Цена: <?php echo number_format($it['price'],0,'',' '); ?> ₽ × <?php echo (int)$it['qty']; ?></div>
            </div>
            <div style="text-align:right;font-weight:700"><?php echo number_format($it['sum'],0,'',' '); ?> ₽</div>
          </div>
        <?php endforeach; ?>
        <div style="margin-top:12px;font-weight:800;display:flex;justify-content:space-between">
          <div>Товары</div>
          <div id="cartTotalDisplay"><?php echo number_format($cartTotal,0,'',' '); ?> ₽</div>
        </div>
        <div style="margin-top:8px;display:flex;justify-content:space-between;align-items:center;padding-top:8px;border-top:1px dashed #eee">
          <div style="color:#5a2f3f"><?php echo e($deliveryLabelDisplay); ?></div>
          <div style="font-weight:700" id="deliveryCostDisplay"><?php echo number_format($deliveryCostDisplay,0,'',' '); ?> ₽</div>
        </div>
        <div id="assembleRow" style="margin-top:8px;display:flex;justify-content:space-between;align-items:center;<?php if(!$assembleSelected) echo 'display:none;'; ?>">
          <div style="color:#5a2f3f">Сборка букета</div>
          <div style="font-weight:700" id="assembleCostDisplay">300 ₽</div>
        </div>
        <div id="totalBlock" style="margin-top:12px;font-weight:900;font-size:16px;display:flex;justify-content:space-between">
          <div>Итого с доставкой</div>
          <div id="totalAmount"><?php echo number_format($totalWithDelivery,0,'',' '); ?> ₽</div>
        </div>
      <?php endif; ?>
    </aside>
  </div>

  <div id="jsData" style="display:none"
       data-cart-total="<?php echo (int)$cartTotal; ?>"
       data-delivery-cost="<?php echo (int)$deliveryCostDisplay; ?>"
       data-assemble-cost="300"></div>

<script>
(function(){
  function q(id){ return document.getElementById(id); }
  var form = document.querySelector('form');
  var nameEl = q('name'), emailEl = q('email'), phoneEl = q('phone'),
      addrEl = q('address'), dateEl = q('delivery_date'), timeEl = q('delivery_time'),
      commentEl = q('comment');

  function setCursorPos(elem, pos) { try { elem.setSelectionRange(pos, pos); elem.focus(); } catch(e){} }

  function fmtFromDigits(digits) {
    if (!digits) digits = '7';
    digits = digits.replace(/^8/, '7');
    if (digits.charAt(0) !== '7') digits = '7' + digits;
    digits = digits.slice(0, 11);
    var rest = digits.slice(1);
    var part1 = rest.slice(0, 3), part2 = rest.slice(3, 6), part3 = rest.slice(6, 8), part4 = rest.slice(8, 10);
    function pad(s, len){ return s + '_'.repeat(Math.max(0, len - s.length)); }
    return '+7 (' + pad(part1,3) + ') ' + pad(part2,3) + '-' + pad(part3,2) + '-' + pad(part4,2);
  }
  function digitsFromFormatted(val){ return (val||'').replace(/\D/g,''); }

  if (phoneEl) {
    var initDigits = digitsFromFormatted(phoneEl.value) || '7';
    initDigits = initDigits.replace(/^8/,'7');
    if (initDigits.charAt(0) !== '7') initDigits = '7' + initDigits;
    initDigits = initDigits.slice(0,11);
    phoneEl.dataset.prevDigits = initDigits;
    phoneEl.value = fmtFromDigits(initDigits);

    phoneEl.addEventListener('focus', function(){
      var digits = digitsFromFormatted(phoneEl.value) || phoneEl.dataset.prevDigits || '7';
      digits = digits.replace(/^8/,'7');
      if (digits.charAt(0) !== '7') digits = '7' + digits;
      digits = digits.slice(0,11);
      phoneEl.dataset.prevDigits = digits;
      phoneEl.value = fmtFromDigits(digits);
      var next = phoneEl.value.indexOf('_');
      setCursorPos(phoneEl, next === -1 ? phoneEl.value.length : next);
    });

    phoneEl.addEventListener('keydown', function(e){
      var val = phoneEl.value, selStart = phoneEl.selectionStart, selEnd = phoneEl.selectionEnd;
      var afterArea = val.indexOf(')') + 2; if (afterArea < 4) afterArea = 4;
      if (e.key === 'Backspace' && selStart === selEnd && selStart <= afterArea) { e.preventDefault(); setCursorPos(phoneEl, afterArea); return; }
      if (e.key === 'Delete' && selStart === selEnd && selStart < afterArea) { e.preventDefault(); setCursorPos(phoneEl, afterArea); return; }
      if (/\d/.test(e.key) && selStart < afterArea) { setCursorPos(phoneEl, afterArea); }
    });

    phoneEl.addEventListener('paste', function(e){
      e.preventDefault();
      var txt = (e.clipboardData || window.clipboardData).getData('text') || '';
      var digits = txt.replace(/\D/g,'').replace(/^8/,'7');
      if (digits.charAt(0) !== '7') digits = '7' + digits;
      digits = digits.slice(0,11);
      phoneEl.dataset.prevDigits = digits;
      phoneEl.value = fmtFromDigits(digits);
      var next = phoneEl.value.indexOf('_');
      setCursorPos(phoneEl, next === -1 ? phoneEl.value.length : next);
    });

    phoneEl.addEventListener('input', function(){
      var rawDigits = digitsFromFormatted(phoneEl.value);
      var prev = phoneEl.dataset.prevDigits || '7';
      if (!rawDigits) rawDigits = prev || '7';
      rawDigits = rawDigits.replace(/^8/,'7');
      if (rawDigits.charAt(0) !== '7') rawDigits = '7' + rawDigits;
      rawDigits = rawDigits.slice(0,11);
      phoneEl.dataset.prevDigits = rawDigits;
      phoneEl.value = fmtFromDigits(rawDigits);
      var next = phoneEl.value.indexOf('_');
      setCursorPos(phoneEl, next === -1 ? phoneEl.value.length : next);
    });
  }

  function showError(el, msg){
    var next = el.nextElementSibling;
    if (!next || !next.classList || !next.classList.contains('field-error')) {
      next = document.createElement('div'); next.className = 'field-error'; el.parentNode.insertBefore(next, el.nextSibling);
    }
    next.textContent = msg; el.setAttribute('aria-invalid', 'true');
  }
  function clearError(el){ var next = el.nextElementSibling; if (next && next.classList && next.classList.contains('field-error')) next.textContent = ''; el.removeAttribute('aria-invalid'); }

  function validEmail(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
  function validPhone(v){ var digits = v.replace(/\D/g,''); return (digits.length === 11 && digits.charAt(0) === '7') || digits.length === 10; }
  function validAddress(v){ if (!v) return false; var s = v.trim(); if (s.length < 10) return false; return /невинномысск/i.test(s); }
  function validName(v){ if (!v) return false; var s = v.trim(); if (s.length < 2) return false; return /^[\p{L}\s\-\.'"]+$/u.test(s); }
  function sanitizeComment(v){ var s = v.replace(/<[^>]*>/g,''); if (s.length > 500) s = s.slice(0,500); return s; }

  // Строгая проверка: дата и время обязаны быть выбраны
  function isValidDateString(s){ if (!s) return false; var d = new Date(s); return !isNaN(d.getTime()); }
  function isTodayOrLater(s){ if (!s) return false; var sel = new Date(s); sel.setHours(0,0,0,0); var today=new Date(); today.setHours(0,0,0,0); return sel>=today; }
  function parseHourFromSelect(val){ if (!val) return null; var m = val.match(/^(\d{2}):/); return m ? parseInt(m[1],10) : null; }

  function validDeliveryDateTimeRequired(dateVal, timeVal){
    if (!dateVal) return { ok:false, field: dateEl, msg:'Укажите дату доставки' };
    if (!timeVal) return { ok:false, field: timeEl, msg:'Выберите час доставки' };
    if (!isValidDateString(dateVal)) return { ok:false, field: dateEl, msg:'Неверный формат даты' };
    if (!isTodayOrLater(dateVal)) return { ok:false, field: dateEl, msg:'Дата должна быть сегодня или позже' };
    var hour = parseHourFromSelect(timeVal);
    if (hour === null) return { ok:false, field: timeEl, msg:'Неверное время доставки' };
    if (hour < 9 || hour > 19) return { ok:false, field: timeEl, msg:'Время должно быть между 09:00 и 19:00' };
    var sel = new Date(dateVal); sel.setHours(0,0,0,0);
    var today = new Date(); today.setHours(0,0,0,0);
    if (sel.getTime() === today.getTime()) {
      var nowHour = new Date().getHours();
      if (hour < nowHour) return { ok:false, field: timeEl, msg:'Выбранный час уже прошёл сегодня' };
    }
    return { ok:true };
  }

  if (form) {
    form.addEventListener('submit', function(e){
      var ok = true;
      [nameEl,emailEl,phoneEl,addrEl,dateEl,timeEl,commentEl].forEach(function(el){ if(el) clearError(el); });

      if (!validName(nameEl.value || '')) { showError(nameEl,'Введите корректное имя (2+ букв)'); ok = false; }
      if (!validEmail(emailEl.value.trim())) { showError(emailEl,'Неверный формат e-mail'); ok = false; }
      if (!validPhone(phoneEl.value.trim())) { showError(phoneEl,'Неверный номер телефона'); ok = false; }
      if (!validAddress(addrEl.value || '')) { showError(addrEl,'Адрес должен содержать "Невинномысск" и быть подробнее'); ok = false; }

      var dtCheck = validDeliveryDateTimeRequired(dateEl.value, timeEl.value);
      if (!dtCheck.ok) { showError(dtCheck.field, dtCheck.msg); ok = false; }

      if (commentEl && commentEl.value) {
        var clean = sanitizeComment(commentEl.value);
        if (clean.length === 0) { showError(commentEl,'Комментарий содержит недопустимые символы'); ok = false; }
        else commentEl.value = clean;
      }

      if (!ok) {
        e.preventDefault();
        var first = document.querySelector('.field-error');
        if (first && first.previousElementSibling) first.previousElementSibling.focus();
      }
    });

    [nameEl,emailEl,phoneEl,addrEl,dateEl,timeEl,commentEl].forEach(function(el){
      if (!el) return;
      el.addEventListener('input', function(){ clearError(el); });
    });
  }
})();
</script>
</body>
</html>
