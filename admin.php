<?php
session_start();
require_once 'functions.php';
$conn = getDatabaseConnection();
if (!$conn) {
    die('Ошибка подключения к базе данных.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['export_report'])) {
        $start = trim((string)($_POST['start_date'] ?? ''));
        $end   = trim((string)($_POST['end_date'] ?? ''));
        $start_dt = ($start !== '') ? date('Y-m-d', strtotime($start)) : null;
        $end_dt   = ($end !== '') ? date('Y-m-d', strtotime($end)) : null;
        $where = [];
        if ($start_dt) { $where[] = "z.date >= '" . $conn->real_escape_string($start_dt) . "'"; }
        if ($end_dt)   { $where[] = "z.date <= '" . $conn->real_escape_string($end_dt) . "'"; }
        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql_orders = "
            SELECT z.id, z.date, z.time, COALESCE(c.name,'') AS client_name,
                   z.id_zakaz_flover, COALESCE(f.name,'') AS product_name,
                   COALESCE(k.kol_vo,0) AS quantity, z.summ, COALESCE(z.buket,'') AS buket,
                   COALESCE(s.status,'') AS status
            FROM zakaz z
            LEFT JOIN client c ON c.id = z.id_client
            LEFT JOIN flovers f ON f.id = z.id_zakaz_flover
            LEFT JOIN zakaz_flover k ON k.id = z.id_zakaz_flover
            LEFT JOIN status_zakaza s ON s.id_zakaz_flover = z.id_zakaz_flover
            {$where_sql}
            ORDER BY z.date ASC, z.time ASC
        ";
        $res = $conn->query($sql_orders);
        $orders = [];
        if ($res) { while ($row = $res->fetch_assoc()) $orders[] = $row; $res->free(); }
        $filename = 'report_orders_' . ($start_dt ?? 'all') . '_' . ($end_dt ?? 'all') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $headers = ['ID заказа','Дата','Время','Клиент','id_zakaz_flover','Товар','Количество','Сумма','buket','Статус'];
        fputcsv($out, $headers, ';');
        foreach ($orders as $r) {
            $date = $r['date'];
            $time = $r['time'];
            $sum = is_numeric($r['summ']) ? number_format((float)$r['summ'], 2, '.', '') : $r['summ'];
            $quantity = is_numeric($r['quantity']) ? (int)$r['quantity'] : $r['quantity'];
            $buket_raw = trim((string)($r['buket'] ?? ''));
            $buket_display = ($buket_raw === 'Да') ? 'Да' : ($buket_raw === '' ? 'Нет' : $buket_raw);
            $row = [
                $r['id'],
                $date,
                $time,
                $r['client_name'],
                $r['id_zakaz_flover'],
                $r['product_name'],
                $quantity,
                $sum,
                $buket_display,
                $r['status']
            ];
            fputcsv($out, $row, ';');
        }
        fclose($out);
        $conn->close();
        exit;
    }
    if (isset($_POST['export_top_products'])) {
        $topN = 1000;
        $res = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'zakaz_items' LIMIT 1");
        $has_zakaz_items = ($res && $res->num_rows > 0);
        if ($res) $res->free();
        if ($has_zakaz_items) {
            $sqlTop = "
                SELECT f.id, f.name, COALESCE(SUM(ki.quantity),0) AS sold, COALESCE(f.kol_vo,0) AS stock
                FROM flovers f
                LEFT JOIN zakaz_items ki ON ki.product_id = f.id
                GROUP BY f.id, f.name, f.kol_vo
                ORDER BY sold DESC
            ";
        } else {
            $sqlTop = "
                SELECT f.id, f.name, COALESCE(SUM(k.kol_vo),0) AS sold, COALESCE(f.kol_vo,0) AS stock
                FROM flovers f
                LEFT JOIN zakaz_flover k ON k.id_flover = f.id
                GROUP BY f.id, f.name, f.kol_vo
                ORDER BY sold DESC
            ";
        }
        $r = $conn->query($sqlTop);
        $rows = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $rows[] = $row;
            }
            $r->free();
        }
        $filename = 'top_products.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID','Название','Продано','Остаток'], ';');
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id'],
                $row['name'],
                is_numeric($row['sold']) ? (int)$row['sold'] : $row['sold'],
                is_numeric($row['stock']) ? (int)$row['stock'] : $row['stock']
            ], ';');
        }
        fclose($out);
        $conn->close();
        exit;
    }
    if (isset($_POST['export_stock'])) {
        $res = $conn->query("SELECT id, name, COALESCE(kol_vo,0) AS stock FROM flovers ORDER BY name ASC");
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) $rows[] = $row;
            $res->free();
        }
        $filename = 'stock_report.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID','Название','Остаток'], ';');
        foreach ($rows as $row) {
            fputcsv($out, [$row['id'], $row['name'], (int)$row['stock']], ';');
        }
        fclose($out);
        $conn->close();
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id_zakaz_flover = (int)($_POST['id_zakaz_flover'] ?? 0);
    $new_status = trim((string)($_POST['status'] ?? ''));
    $allowed_statuses = ['Новый', 'В обработке', 'Готов к доставке', 'Доставлен', 'Отменён'];
    if ($id_zakaz_flover > 0 && $new_status !== '' && in_array($new_status, $allowed_statuses, true)) {
        $stmt = $conn->prepare('UPDATE status_zakaza SET status = ? WHERE id_zakaz_flover = ?');
        if ($stmt) {
            $stmt->bind_param('si', $new_status, $id_zakaz_flover);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($affected === 0) {
                $ins = $conn->prepare('INSERT INTO status_zakaza (id_zakaz_flover, status) VALUES (?, ?)');
                if ($ins) {
                    $ins->bind_param('is', $id_zakaz_flover, $new_status);
                    $ins->execute();
                    $ins->close();
                }
            }
        }
    }
    header('Location: admin.php');
    exit;
}
$totalRevenue = 0;
$totalSold = 0;
$res = $conn->query("SELECT COALESCE(SUM(summ),0) AS revenue FROM zakaz");
if ($res) {
    $row = $res->fetch_assoc();
    $totalRevenue = (float)$row['revenue'];
    $res->free();
}
$res = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'zakaz_items' LIMIT 1");
$has_zakaz_items = ($res && $res->num_rows > 0);
if ($res) $res->free();
if ($has_zakaz_items) {
    $res = $conn->query("SELECT COALESCE(SUM(quantity),0) AS sold FROM zakaz_items");
    if ($res) {
        $row = $res->fetch_assoc();
        $totalSold = (int)$row['sold'];
        $res->free();
    }
} else {
    $res = $conn->query("SELECT COALESCE(COUNT(*),0) AS sold FROM zakaz");
    if ($res) {
        $row = $res->fetch_assoc();
        $totalSold = (int)$row['sold'];
        $res->free();
    }
}
$products = [];
$res = $conn->query("SELECT id, image, name, price, COALESCE(kol_vo,0) AS kol_vo FROM flovers ORDER BY name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $products[] = $r;
    }
    $res->free();
}
$sql = "
SELECT
  z.id AS zakaz_id,
  z.id_client,
  z.id_zakaz_flover,
  z.summ,
  z.time AS delivery_time,
  z.date AS delivery_date,
  COALESCE(z.buket, '') AS buket,
  COALESCE(c.name, '') AS client_name,
  COALESCE(s.status, '') AS status,
  COALESCE(f.name, '') AS product_name,
  COALESCE(k.kol_vo, 0) AS quantity
FROM zakaz z
LEFT JOIN status_zakaza s ON s.id_zakaz_flover = z.id_zakaz_flover
LEFT JOIN client c ON c.id = z.id_client
LEFT JOIN flovers f ON f.id = z.id_zakaz_flover
LEFT JOIN zakaz_flover k ON k.id = z.id_zakaz_flover
ORDER BY z.id DESC
";
$result = $conn->query($sql);
$orders = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $delivery_time_raw = isset($row['delivery_time']) ? trim($row['delivery_time']) : '';
        $delivery_date_raw = isset($row['delivery_date']) ? trim($row['delivery_date']) : '';
        $delivery_time_formatted = '-';
        if ($delivery_date_raw !== '' && $delivery_time_raw !== '') {
            $dt = $delivery_date_raw . ' ' . $delivery_time_raw;
            $ts = strtotime($dt);
            if ($ts !== false) {
                $delivery_time_formatted = date('d.m.Y H:i', $ts);
            } else {
                $delivery_time_formatted = htmlspecialchars($delivery_date_raw . ' ' . $delivery_time_raw, ENT_QUOTES);
            }
        } elseif ($delivery_date_raw !== '') {
            $ts = strtotime($delivery_date_raw);
            $delivery_time_formatted = ($ts !== false) ? date('d.m.Y', $ts) : htmlspecialchars($delivery_date_raw, ENT_QUOTES);
        } elseif ($delivery_time_raw !== '') {
            $ts = strtotime($delivery_time_raw);
            $delivery_time_formatted = ($ts !== false) ? date('H:i', $ts) : htmlspecialchars($delivery_time_raw, ENT_QUOTES);
        }
        $row['buket'] = trim((string)($row['buket'] ?? ''));
        $row['buket_display'] = ($row['buket'] === 'Да') ? 'Да' : 'Нет';
        $row['delivery_time_formatted'] = $delivery_time_formatted;
        $row['delivery_time_raw'] = $delivery_time_raw;
        $row['delivery_date_raw'] = $delivery_date_raw;
        $orders[] = $row;
    }
    $result->free();
}
$topN = 10;
$topProducts = [];
$stockList = [];
if ($conn) {
    if ($has_zakaz_items) {
        $sqlTop = "
            SELECT f.id, f.name, COALESCE(SUM(ki.quantity),0) AS sold, COALESCE(f.kol_vo,0) AS stock
            FROM flovers f
            LEFT JOIN zakaz_items ki ON ki.product_id = f.id
            GROUP BY f.id, f.name, f.kol_vo
            ORDER BY sold DESC
            LIMIT " . (int)$topN;
        $r = $conn->query($sqlTop);
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $topProducts[] = ['id'=>(int)$row['id'], 'name'=>$row['name'], 'sold'=>(int)$row['sold']];
                $stockList[]   = ['id'=>(int)$row['id'], 'name'=>$row['name'], 'stock'=>(int)$row['stock']];
            }
            $r->free();
        }
    } else {
        $sqlTop2 = "
            SELECT f.id, f.name, COALESCE(SUM(k.kol_vo),0) AS sold, COALESCE(f.kol_vo,0) AS stock
            FROM flovers f
            LEFT JOIN zakaz_flover k ON k.id_flover = f.id
            GROUP BY f.id, f.name, f.kol_vo
            ORDER BY sold DESC
            LIMIT " . (int)$topN;
        $r = $conn->query($sqlTop2);
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $topProducts[] = ['id'=>(int)$row['id'], 'name'=>$row['name'], 'sold'=>(int)$row['sold']];
                $stockList[]   = ['id'=>(int)$row['id'], 'name'=>$row['name'], 'stock'=>(int)$row['stock']];
            }
            $r->free();
        }
    }
    if (empty($stockList)) {
        $r = $conn->query("SELECT id, name, COALESCE(kol_vo,0) AS stock FROM flovers ORDER BY name ASC");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $stockList[] = ['id'=>(int)$row['id'], 'name'=>$row['name'], 'stock'=>(int)$row['stock']];
            }
            $r->free();
        }
    }
}
$conn->close();
$uploadBasePath = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . '/uploads/';
$uploadBaseUrl  = '/uploads/';
function renderProductImage($imgValue, $name, $uploadBasePath, $uploadBaseUrl) {
    $imgValue = trim((string)$imgValue);
    if ($imgValue === '') {
        return '<div style="width:80px;height:60px;display:flex;align-items:center;justify-content:center;background:#f8f8f8;color:#666;border:1px solid #e6e9ec;font-size:12px;">Нет фото</div>';
    }
    if (preg_match('#^https?://#i', $imgValue)) {
        $url = $imgValue;
        return '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="' . htmlspecialchars($name, ENT_QUOTES) . '" style="max-width:80px;max-height:60px;object-fit:cover;">';
    }
    $relative = ltrim($imgValue, '/\\');
    $url = rtrim($uploadBaseUrl, '/') . '/' . str_replace('\\', '/', $relative);
    return '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="' . htmlspecialchars($name, ENT_QUOTES) . '" style="max-width:80px;max-height:60px;object-fit:cover;">';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - Флорист</title>
    <link rel="stylesheet" href="css/styleadmin.css">
    <style>
:root{
  --bg: #f6fbf6;          
  --panel-bg: #ffffff;   
  --muted: #6b7a6b;       
  --accent: #8fc9a1;    
  --accent-2: #bfe8c9;    
  --accent-dark: #5f9a74;   
  --border: #e6efe6;      
  --danger: #e07a7a;       
  --shadow: 0 6px 18px rgba(95,154,116,0.08);
  --glass: rgba(255,255,255,0.7);
  --radius: 10px;
  --text: #2f3f2f;
  --muted-2: #8a9c8a;
  --success: #3e9a63;
  --table-head: #f0fbf2;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family: "Inter", "Segoe UI", Roboto, Arial, sans-serif;
  background: linear-gradient(180deg,var(--bg), #f2fbf2 60%);
  color:var(--text);
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
  line-height:1.45;
  font-size:15px;
}
.container{max-width:1100px;margin:0 auto;padding:0 16px}
.wrapper{display:flex;gap:20px;padding:20px 16px}
.site-header{
  background: linear-gradient(90deg,var(--accent-2), rgba(191,232,201,0.55));
  border-bottom:1px solid var(--border);
  padding:12px 0;
  box-shadow: var(--shadow);
}
.header-inner{display:flex;align-items:center;justify-content:flex-start;gap:12px}
.brand{display:flex;align-items:center;gap:12px}
.header__logo{border-radius:8px;object-fit:cover}
.site-title{font-size:18px;margin:0;color:var(--text)}
.sub{margin:0;font-size:13px;color:var(--muted)}
.sidebar{
  width:220px;
  background: linear-gradient(180deg, rgba(255,255,255,0.7), var(--glass));
  border:1px solid var(--border);
  border-radius:var(--radius);
  padding:12px;
  display:flex;
  flex-direction:column;
  gap:8px;
  height:calc(100vh - 120px);
  position:sticky;
  top:16px;
  align-self:flex-start;
  box-shadow: var(--shadow);
}
.sidebar a{
  display:block;
  color:var(--muted);
  text-decoration:none;
  padding:10px 12px;
  border-radius:8px;
  font-weight:600;
  transition:all .18s ease;
}
.sidebar a:hover{background:var(--accent-2);color:var(--text);transform:translateX(4px)}
.sidebar a.active{
  background:linear-gradient(90deg,var(--accent),var(--accent-2));
  color:#fff;
  box-shadow:0 4px 12px rgba(87,156,110,0.18);
}
.content{flex:1;min-width:0}
.card{
  background:var(--panel-bg);
  border:1px solid var(--border);
  border-radius:12px;
  padding:16px;
  margin-bottom:16px;
  box-shadow: var(--shadow);
}
.welcome-card{display:flex;flex-direction:column;gap:8px;align-items:flex-start}
.btn-primary{
  display:inline-block;
  background:linear-gradient(180deg,var(--accent),var(--accent-dark));
  color:#fff;
  padding:9px 14px;
  border-radius:9px;
  text-decoration:none;
  font-weight:700;
  border:0;
  cursor:pointer;
  transition:transform .12s ease,box-shadow .12s ease;
}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(95,154,116,0.18)}
.data-table, table{
  width:100%;
  border-collapse:collapse;
  background:transparent;
}
.data-table thead th, table thead th{
  background:var(--table-head);
  text-align:left;
  padding:10px;
  font-size:13px;
  color:var(--muted-2);
  border-bottom:1px solid var(--border);
}
.data-table tbody td, table tbody td{
  padding:10px;
  vertical-align:middle;
  border-bottom:1px dashed var(--border);
  color:var(--text);
  font-size:14px;
}
.data-table img{max-width:72px;border-radius:8px;border:1px solid var(--border)}
.edit-btn, .delete-btn{
  display:inline-block;
  padding:6px 8px;
  border-radius:6px;
  text-decoration:none;
  font-weight:600;
  font-size:13px;
  margin-right:6px;
}
.edit-btn{
  background:transparent;
  border:1px solid var(--accent);
  color:var(--accent-dark);
}
.edit-btn:hover{background:var(--accent);color:#fff}
.delete-btn{
  background:transparent;border:1px solid #f2c2c2;color:var(--danger)
}
.delete-btn:hover{background:var(--danger);color:#fff}
input[type="date"], select{
  padding:7px 8px;border:1px solid var(--border);
  border-radius:8px;background:#fff;color:var(--text);
  font-size:14px;
}
button[type="submit"]{
  padding:8px 12px;border-radius:8px;border:0;
  background:var(--accent);color:#fff;font-weight:700;
  cursor:pointer;
}
button[type="submit"]:hover{background:var(--accent-dark)}
.stat-blocks{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px}
.stat-block{
  flex:1;
  min-width:160px;
  background:linear-gradient(180deg,rgba(143,201,161,0.08),rgba(191,232,201,0.04));
  border:1px solid var(--border);
  padding:12px;border-radius:10px;text-align:center;
}
.stat-block span{display:block;margin-top:8px;font-size:20px;font-weight:800;color:var(--accent-dark)}
.site-footer{
  border-top:1px solid var(--border);
  padding:14px 0;
  margin-top:18px;
  background:linear-gradient(180deg,transparent,rgba(245,252,245,0.6));
}
.footer-inner{display:flex;align-items:center;justify-content:space-between;gap:12px;max-width:1100px;margin:0 auto;padding:0 16px}
.footer-title{margin:0;color:var(--muted)}
.legal{margin:0;color:var(--muted-2);font-size:13px}
@media (max-width:880px){
  .wrapper{flex-direction:column;padding:12px}
  .sidebar{width:100%;height:auto;position:relative;top:0;flex-direction:row;overflow:auto}
  .sidebar a{white-space:nowrap}
  .content{width:100%}
  .header-inner{flex-wrap:wrap;gap:10px}
}
a{color:var(--accent-dark)}
strong{color:var(--success)}
small{color:var(--muted)}
    </style>
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <div class="brand">
      <img src="icons/logo.png" alt="Логотип Флориста" class="header__logo" style="width:40px;height:40px">
      <div class="brand-text">
        <h1 class="site-title">Админ-панель "Флорист"</h1>
        <p class="sub">Управление магазином</p>
      </div>
    </div>
  </div>
</header>
<main>
  <div class="wrapper">
    <aside class="sidebar" aria-label="Навигация">
        <a href="#" class="active">Главная</a>
        <a href="#catalog">Каталог товаров</a>
        <a href="#orders">Заказы</a>
        <a href="#stats">Статистика</a>
        <div>
            <a href="vhod_admin.php">Выйти</a>
        </div>
    </aside>
    <section class="content" role="main">
        <div class="card welcome-card">
            <h3>Добро пожаловать!</h3>
            <p>Здесь вы можете управлять всеми аспектами вашего интернет-магазина цветов.</p>
            <a href="add_product.php" class="btn-primary">Добавить новый букет</a>
        </div>
        <div class="card" id="catalog">
            <h3>Каталог товаров</h3>
            <p>Список всех доступных букетов и композиций. Здесь можно редактировать цены и наличие.</p>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Изображение</th>
                        <th>Название</th>
                        <th>Цена</th>
                        <th>Остаток</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="5">Товары не найдены.</td></tr>
                <?php else: foreach ($products as $p): ?>
                    <tr>
                        <td>
                            <?php
                                $img = !empty($p['image']) ? 'kartinka/' . $p['image'] : 'icons/no-image.png';
                                echo renderProductImage($img, $p['name'] ?? '', $uploadBasePath, $uploadBaseUrl);
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['name'] ?? '-', ENT_QUOTES); ?></td>
                        <td><?php echo number_format((float)($p['price'] ?? 0), 0, '.', ' '); ?> ₽</td>
                        <td><?php echo (int)($p['kol_vo'] ?? 0); ?></td>
                        <td>
                            <a href="edit_category.php?id=<?php echo (int)$p['id']; ?>" class="edit-btn">Ред.</a>
                            <a href="delete_category.php?id=<?php echo (int)$p['id']; ?>" class="delete-btn" onclick="return confirm('Удалить запись?');">Уд.</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card" id="orders">
            <h3>Новые заказы</h3>
            <p>Список последних оформленных заказов от клиентов.</p>
            <div style="margin:12px 0;padding:10px;border:1px dashed #ddd;">
                <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <label>От: <input type="date" name="start_date"></label>
                    <label>До: <input type="date" name="end_date"></label>
                    <button type="submit" name="export_report">Экспорт заказов (CSV)</button>
                    <span style="color:#666;font-size:12px;">CSV откроется в Excel</span>
                </form>
            </div>
            <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
    <thead>
        <tr>
            <th>ID</th>
            <th>Клиент (имя)</th>
            <th>Товар</th>
            <th>id_zakaz_flover</th>
            <th>Количество</th>
            <th>Сумма</th>
            <th>Время доставки</th>
            <th>Собран</th>
            <th>Статус</th>
            <th>Изменить статус</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
        <tr>
            <td><?php echo htmlspecialchars($o['zakaz_id'], ENT_QUOTES); ?></td>
            <td><?php echo htmlspecialchars($o['client_name'] ?: '-', ENT_QUOTES); ?></td>
            <td style="white-space:nowrap;">
                <?php
                    $prod = htmlspecialchars($o['product_name'] ?: '-', ENT_QUOTES);
                    echo $prod;
                ?>
            </td>
            <td><?php echo htmlspecialchars($o['id_zakaz_flover'] ?? '-', ENT_QUOTES); ?></td>
            <td><?php echo htmlspecialchars($o['quantity'] ?? 0, ENT_QUOTES); ?></td>
            <td><?php echo htmlspecialchars(number_format((float)($o['summ'] ?? 0), 2, ',', ' '), ENT_QUOTES); ?></td>
            <td><?php echo htmlspecialchars($o['delivery_time_formatted'] ?? '-', ENT_QUOTES); ?></td>
            <td><?php echo ($o['buket_display'] === 'Да') ? '<strong>Да</strong>' : 'Нет'; ?></td>
            <td><?php echo htmlspecialchars($o['status'] ?: 'Новый', ENT_QUOTES); ?></td>
            <td>
                <form method="post" style="display:inline-flex;gap:6px;align-items:center;">
                    <input type="hidden" name="id_zakaz_flover" value="<?php echo (int)($o['id_zakaz_flover'] ?? 0); ?>">
                    <select name="status" required>
                        <?php
                        $allowed_statuses = ['Новый', 'В обработке', 'Готов к доставке', 'Доставлен', 'Отменён'];
                        $cur = $o['status'] ?? 'Новый';
                        foreach ($allowed_statuses as $s) {
                            $sel = ($s === $cur) ? ' selected' : '';
                            echo '<option value="'.htmlspecialchars($s, ENT_QUOTES).'"'.$sel.'>'.htmlspecialchars($s, ENT_QUOTES).'</option>';
                        }
                        ?>
                    </select>
                    <button type="submit" name="update_status">Сохранить</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($orders)): ?>
        <tr><td colspan="10" style="text-align:center;color:#666;">Заказов не найдено</td></tr>
    <?php endif; ?>
    </tbody>
</table>
        </div>
        <div class="card stats-card" id="stats">
            <h3>Статистика продаж</h3>
            <p>Общая выручка, количество проданных букетов и популярные товары.</p>
            <div class="stat-blocks">
                <div class="stat-block revenue">Выручка<br><span><?php echo number_format($totalRevenue, 0, '.', ' '); ?> ₽</span></div>
                <div class="stat-block sold">Букетов продано<br><span><?php echo number_format($totalSold, 0, '.', ' '); ?></span></div>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <form method="post" style="margin:0;">
                    <button type="submit" name="export_top_products">Экспорт топ-продаваемых (CSV)</button>
                </form>          
            </div>
            <h4 style="margin-top:12px;">Топ продаваемых товаров (топ <?php echo (int)$topN; ?>)</h4>
            <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;margin-bottom:12px;">
                <thead><tr><th>ID</th><th>Название</th><th>Продано</th></tr></thead>
                <tbody>
                <?php if (empty($topProducts)): ?>
                    <tr><td colspan="3" style="text-align:center;color:#666;">Нет данных</td></tr>
                <?php else: foreach ($topProducts as $p): ?>
                    <tr>
                        <td><?php echo (int)$p['id']; ?></td>
                        <td><?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?></td>
                        <td><?php echo (int)$p['sold']; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
                 <form method="post" style="margin:0;">
                    <button type="submit" name="export_stock">Экспорт остатков (CSV)</button>
                </form>
            <h4>Остатки на складе</h4>
            <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
                <thead><tr><th>ID</th><th>Название</th><th>Остаток</th></tr></thead>
                <tbody>
                <?php if (empty($stockList)): ?>
                    <tr><td colspan="3" style="text-align:center;color:#666;">Нет данных</td></tr>
                <?php else: foreach ($stockList as $s): ?>
                    <tr>
                        <td><?php echo (int)$s['id']; ?></td>
                        <td><?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?></td>
                        <td><?php echo (int)$s['stock']; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
  </div>
</main>
<footer class="site-footer">
  <div class="container footer-inner">
    <div class="brand footer-brand">
      <span class="logo">Ф</span>
      <div>
        <p class="footer-title"><strong>Интернет-магазин флористики</strong></p>
        <p class="legal">Все права защищены.</p>
      </div>
    </div>
    <div class="copyright">© <strong><?php echo date("Y"); ?></strong></div>
  </div>
</footer>
</body>
</html>