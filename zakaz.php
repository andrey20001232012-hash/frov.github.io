<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$dbHost = '127.0.0.1';
$dbName = 'flover';
$dbUser = 'root';
$dbPass = '';
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
  echo "Ошибка подключения к базе: " . htmlspecialchars($e->getMessage());
  exit;
}
$q = trim((string)($_GET['q'] ?? ''));
$contact = trim((string)($_GET['contact'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));  
$sql = "
SELECT
  z.id AS order_id,
  z.summ AS total,
  c.name AS client_name,
  c.phone AS client_phone,
  c.email AS client_email,
  c.address AS client_address,
  c.comment AS client_comment,
  s.status AS status_label,
  GROUP_CONCAT(CONCAT(f.name, ' x', COALESCE(zf.kol_vo,0), ' (', COALESCE(zf.sum,0), ' )') SEPARATOR '||') AS items_list
FROM zakaz z
LEFT JOIN client c ON z.id_client = c.id
LEFT JOIN zakaz_flover zf ON z.id_zakaz_flover = zf.id
LEFT JOIN flovers f ON zf.id_flover = f.id
LEFT JOIN status_zakaza s ON s.id_zakaz_flover = zf.id
WHERE 1=1
";
$params = [];
if ($q !== '') {
  $sql .= " AND z.id LIKE :q";
  $params[':q'] = "%{$q}%";
}
if ($contact !== '') {
  $sql .= " AND (c.phone LIKE :contact OR c.email LIKE :contact OR c.name LIKE :contact)";
  $params[':contact'] = "%{$contact}%";
}
if ($status !== '') {
  $sql .= " AND s.status = :status";
  $params[':status'] = $status;
}
$sql .= " GROUP BY z.id ORDER BY z.id DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Поиск и отслеживание заказов</title>
  <style>
   :root{
  --bg:#faf9f7;
  --card:#ffffff;
  --muted:#6b6b6b;
  --accent:#2ecc71;
  --accent-dark:#27b864;
  --danger:#e74c3c;
  --border:#e9e9e9;
  --shadow: 0 6px 18px rgba(21,21,21,0.06);
  --max-width:1100px;
  --radius:10px;
  --gap:18px;
  --font-sans:"Inter", "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family:var(--font-sans);
  background:linear-gradient(180deg,var(--bg),#fff 60%);
  color:#222;
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
  line-height:1.45;
  font-size:16px;
  padding:18px 12px 80px;
}
.container{max-width:var(--max-width);margin:0 auto;padding:0 16px}
.header{background:transparent;padding:12px 0;border-bottom:1px solid rgba(0,0,0,0.03);position:sticky;top:0;backdrop-filter:blur(6px);z-index:40}
.header__inner{display:flex;align-items:center;justify-content:space-between;gap:12px}
.header__brand{display:flex;align-items:center;gap:12px}
.header__logo{width:44px;height:44px;border-radius:8px;object-fit:cover;box-shadow:var(--shadow)}
.header__site-name{font-weight:700;color:#163a24}
.header__nav{display:flex;gap:10px;align-items:center;margin-left:18px}
.header__nav-link{color:var(--muted);text-decoration:none;padding:8px 12px;border-radius:8px;font-weight:600}
.header__nav-link.active, .header__nav-link:hover{background:rgba(46,204,113,0.06);color:var(--accent-dark)}
.header__contact{display:flex;flex-direction:column;align-items:flex-end;font-size:13px;color:var(--muted)}
.header__search-form{display:flex;align-items:center;gap:8px;background:#fff;padding:6px;border-radius:8px;border:1px solid var(--border)}
.header__search-input{border:0;outline:none;padding:8px 10px;width:220px;border-radius:6px;font-size:14px}
.header__search-btn{background:transparent;border:0;color:var(--muted);cursor:pointer;font-size:18px;padding:6px}
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;margin-bottom:18px}
h2{margin:0 0 8px 0;font-size:1.2rem;color:#163a24}
.muted{color:var(--muted);font-size:0.95rem}
.form-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.form-row input[type="search"],
.form-row input[type="text"],
.form-row select{
  padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:#fff;min-width:180px;
  outline:none;transition:box-shadow .12s,border-color .12s;
}
.form-row input:focus, .form-row select:focus{box-shadow:0 6px 18px rgba(39,184,100,0.08);border-color:var(--accent)}
.form-row button[type="submit"]{background:var(--accent);color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer;font-weight:600}
.btn-clear{background:var(--accent-dark);border:none;color:#fff;padding:10px 14px;border-radius:8px;cursor:pointer}
table{width:100%;border-collapse:collapse;font-size:0.95rem}
thead th{ text-align:left;padding:12px 10px;background:linear-gradient(90deg, rgba(46,204,113,0.04), transparent);color:var(--muted);font-weight:700;border-bottom:1px solid var(--border) }
tbody td{padding:12px 10px;border-bottom:1px dashed var(--border);vertical-align:top}
tbody tr:hover{background:linear-gradient(90deg, rgba(46,204,113,0.02), transparent)}
.items ul{margin:0;padding-left:18px}
strong{color:#111}
.status-pill{
  display:inline-block;padding:6px 10px;border-radius:999px;font-weight:700;font-size:0.85rem;color:#fff;
}
.status-pill.pending{background:#f59e0b}
.status-pill.done{background:var(--accent)}
.status-pill.cancel{background:var(--danger)}
.muted.empty{display:block;padding:14px;background:#fff;border:1px dashed var(--border);border-radius:10px;text-align:center}
@media (max-width:880px){
  .header__nav{display:none}
  .form-row{flex-direction:column;align-items:stretch}
  thead{display:none}
  tbody td{display:block;padding:12px 0;border-bottom:1px solid var(--border)}
  tbody td:before{display:block;font-weight:700;margin-bottom:6px;color:var(--muted)}
  tbody td:nth-child(1):before{content:"Номер"}
  tbody td:nth-child(2):before{content:"Клиент / Контакт"}
  tbody td:nth-child(3):before{content:"Сумма"}
  tbody td:nth-child(4):before{content:"Статус"}
  tbody td:nth-child(5):before{content:"Позиции"}
}
.footer{position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid #e4e7ea;padding:8px 0;font-size:12px;color:#666;z-index:999}
.footer .container{max-width:var(--max-width);margin:0 auto;padding:0 16px;display:flex;justify-content:center;align-items:center;height:32px}
.client-address, .client-comment{color:var(--muted);font-size:13px;margin-top:6px}
td .muted{display:block;color:var(--muted)}
.items li{margin:4px 0;font-size:14px}
#clearBtn{background:var(--accent-dark);border:none;color:#fff;padding:8px 12px;border-radius:8px;cursor:pointer}
.form-row button[type="submit"]:hover{filter:brightness(.98)}
.btn-clear:hover{filter:brightness(.95)}
.header__quick-action{
  display:flex;
  gap:12px;
  align-items:center;
  margin-left:12px;
  margin-right:8px;
  font-family:inherit;
}
.header__quick-action a{
  display:inline-flex;
  align-items:center;
  gap:10px;
  padding:8px 12px;
  border-radius:8px;
  text-decoration:none;
  color:var(--text);
  background:transparent;
  transition:transform .12s ease, box-shadow .12s ease, background .12s;
  border:1px solid transparent;
  font-weight:700;
  font-size:14px;
}
.header__quick-action i{font-size:18px;color:var(--accent-strong);line-height:1}
.header__cart{
  position:relative;
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 10px;
  border-radius:10px;
  background:var(--btn-bg);
  border:1px solid var(--border);
  box-shadow:0 6px 18px rgba(6,40,18,0.03);
}
.header__account{
  padding:8px 10px;
  border-radius:8px;
  border:1px solid transparent;
  background:transparent;
  display:inline-flex;
  align-items:center;
  gap:8px;
}
.header__account i{color:var(--muted)}
.header__cart:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(6,40,18,0.06);border-color:rgba(157,214,166,0.25)}
.header__account:hover{background:rgba(11,66,32,0.03);transform:translateY(-1px)}
.header__cart .cart-count-wrapper{display:inline-flex;align-items:center;min-width:28px;justify-content:center}
.cart-count{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:20px;
  height:20px;
  padding:0 6px;
  font-size:12px;
  font-weight:800;
  color:#063216;
  background:linear-gradient(180deg,var(--accent-strong),#bfe9c6);
  border-radius:999px;
  border:1px solid rgba(6,40,18,0.06);
  box-shadow:0 4px 10px rgba(6,40,18,0.04);
  transform-origin:center;
}
.header__cart .cart-count:empty,
.header__cart .cart-count[aria-hidden="true"]{display:none}
@keyframes cart-pop{
  0%{transform:scale(.6);opacity:0}
  60%{transform:scale(1.08);opacity:1}
  100%{transform:scale(1);opacity:1}
}
.cart-count.animate{animation:cart-pop .36s cubic-bezier(.2,.9,.3,1)}
@media (max-width:600px){
  .header__quick-action span{display:none}
  .header__quick-action a{padding:8px;border-radius:50%}
  .header__quick-action{gap:8px;margin-left:8px}
}
.header__cart[aria-live]{outline:none}
  </style>
</head>
<body>
<header class="header">
  <div class="container">
     <div class="header__inner" style="display:flex;align-items:center;justify-content:space-between;padding:12px 0">
  <div class="header__brand" style="display:flex;align-items:center;gap:10px">
    <img src="icons/logo.png" alt="Логотип Флориста" class="header__logo" style="width:40px;height:40px">
    <span class="header__site-name">Флорист</span>
  </div>
  <nav class="header__nav" style="flex:1;margin-left:24px">
    <a href="index.php" class="header__nav-link " style="margin-right:12px">Главная</a>
    <a href="index.php#about-us" class="header__nav-link">Доставка</a>
    <a href="zakaz.php" class="header__nav-link active"style="margin-right:12px">Заказы</a>
  </nav>
<div class="header__contact">
        <a href="tel:+79999999999" class="header__phone">
          <img src="icons/telefon.png" alt="Телефон" class="header__phone-icon" height="50">
          <span class="header__phone-number">+7 999 999 99 99</span>
        </a>
        <p class="header__work-hours">Ежедневно с 9:00 до 19:00</p>
      </div>
  <div style="display:flex;gap:12px;align-items:center">
    <form class="header__search-form" method="GET" action="">
      <input type="search" name="q" value="<?php echo h($_GET['q'] ?? '') ?>" placeholder="Поиск..." class="header__search-input" aria-label="Поиск" style="padding:6px 8px;border:1px solid #ccc;border-radius:6px">
      <button type="submit" class="header__search-btn" style="margin-left:6px;padding:6px 8px">🔍</button>
    </form>
  </div>
  <?php $cartCount = intval($cartCount ?? 0); ?>
<div class="header__quick-action">
  <a href="cart.php" class="header__cart" aria-label="Корзина" aria-live="polite" aria-atomic="true">
    <i class="ri-shopping-basket-line" aria-hidden="true"></i>
    <span>Корзина</span>
    <span class="cart-count-wrapper">
      <sup id="cartCount" class="cart-count<?= $cartCount ? ' animate' : '' ?>" aria-hidden="<?= $cartCount ? 'false' : 'true' ?>"><?= $cartCount ?: '' ?></sup>
    </span>
  </a>
  <a href="vhod_admin.php" class="header__account" aria-label="Личный кабинет">
    <i class="ri-user-fill" aria-hidden="true"></i>
    <span>Личный кабинет</span>
  </a>
</div>
</div>
   </div>
</header>
<main class="container" style="padding-top:18px">
  <div class="card">
    <h2>Поиск заказа / Отслеживание статуса</h2>
    <p class="muted">Введите номер заказа или контактные данные для поиска. Можно выбрать фильтр по статусу.</p>
    <form method="get" class="form-row" id="searchForm" style="margin-top:12px">
      <input name="q" id="orderNumber" type="search" value="<?php echo h($q) ?>" placeholder="Номер заказа (например 12345)" />
      <input name="contact" id="contact" type="text" value="<?php echo h($contact) ?>" placeholder="Еmail клиента" />
      <select name="status" id="statusFilter">
        <option value="">Все статусы</option>
        <?php
          $stStmt = $pdo->query("SELECT DISTINCT status FROM status_zakaza ORDER BY status");
          $statuses = $stStmt->fetchAll();
          foreach ($statuses as $st) {
            $sel = ($status !== '' && $status === $st['status']) ? 'selected' : '';
            echo '<option value="'.h($st['status']).'" '.$sel.'>'.h($st['status']).'</option>';
          }
        ?>
      </select>
      <button type="submit">Найти</button>
      <button type="button" id="clearBtn" class="btn-clear" style="padding:10px 14px;border-radius:8px;color:#fff;margin-left:6px;cursor:pointer">Очистить</button>
    </form>
  </div>
  <div class="card">
    <?php if (count($orders) === 0): ?>
      <div class="muted">Ничего не найдено</div>
    <?php else: ?>
      <table>
        <thead>
          <tr><th>Номер</th><th>Клиент / Контакт</th><th>Сумма</th><th>Статус</th><th>Позиции</th></tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><strong><?php echo h($o['order_id']) ?></strong></td>
            <td>
              <?php echo h($o['client_name'] ?? '') ?>
              <div class="muted"><?php echo h($o['client_phone'] ?? '') ?> <?php if(!empty($o['client_email'])) echo ' / '.h($o['client_email']); ?></div>
              <?php if(!empty($o['client_address'])): ?><div class="muted">Адрес: <?php echo h($o['client_address']); ?></div><?php endif; ?>
              <?php if(!empty($o['client_comment'])): ?><div class="muted">Комментарий: <?php echo h($o['client_comment']); ?></div><?php endif; ?>
            </td>
            <td><?php echo h($o['total'] ?? '') ?></td>
            <td><?php echo h($o['status_label'] ?? '—') ?></td>
            <td class="items">
              <?php
                if (!empty($o['items_list'])) {
                  $parts = explode('||', $o['items_list']);
                  echo '<ul style="margin:0;padding-left:18px">';
                  foreach ($parts as $p) {
                    echo '<li>'.h($p).'</li>';
                  }
                  echo '</ul>';
                } else {
                  echo '<span class="muted">Нет позиций</span>';
                }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</main>
<script>
document.getElementById('clearBtn').addEventListener('click', function(){
  var orderEl = document.getElementById('orderNumber');
  var contactEl = document.getElementById('contact');
  var statusEl = document.getElementById('statusFilter');
  if(orderEl) orderEl.value = '';
  if(contactEl) contactEl.value = '';
  if(statusEl) statusEl.value = '';
  history.replaceState(null, '', window.location.pathname);
});
function updateCartCount(newCount){
    const el = document.getElementById('cartCount');
    if(!el) return;
    el.textContent = newCount ? String(newCount) : '';
    if(newCount){
      el.setAttribute('aria-hidden','false');
      el.classList.remove('animate');
      void el.offsetWidth; // reflow
      el.classList.add('animate');
    } else {
      el.setAttribute('aria-hidden','true');
      el.classList.remove('animate');
    }
  }
</script>
</body>
</html>
