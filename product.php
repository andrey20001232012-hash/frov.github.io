<?php
session_start();
require_once 'functions.php';
$pdo = new PDO('mysql:host=127.0.0.1;dbname=flover;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
$catalog = getCatalogWithCategories();
$item = null;
foreach ($catalog as $catItems) {
    foreach ($catItems as $it) {
        $itId = isset($it['id']) ? $it['id'] : $it['name'];
        if ((string)$itId === (string)$id) {
            $item = $it;
            break 2;
        }
    }
}
if (!$item) {
    $stmt = $pdo->prepare('SELECT id, name, image, kol_vo, opisanie, price, categori FROM flovers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if ($row) {
        $item = [
            'id' => $row['id'],
            'name' => $row['name'],
            'image' => $row['image'],
            'quantity' => $row['kol_vo'],
            'description' => $row['opisanie'], 
            'opisanie' => $row['opisanie'],    
            'price' => $row['price'],
            'category' => $row['categori'],
        ];
    }
}

if (!$item) {

    header('Location: index.php');
    exit;
}
$cartCount = 0;
foreach ($_SESSION['cart'] ?? [] as $cqty) $cartCount += (int)$cqty;
$flashAdded = null;
if (!empty($_SESSION['flash_added'])) {
    $flashAdded = $_SESSION['flash_added'];
    unset($_SESSION['flash_added']);
}
$itemId = isset($item['id']) ? $item['id'] : $item['name'];
$img = !empty($item['image']) ? 'kartinka/' . $item['image'] : 'icons/no-image.png';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?php echo e($item['name']); ?> — товар</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.9.0/fonts/remixicon.css">
  <style> 
:root{
  --card:#ffffff;
  --muted:#6b6b6b;
  --accent:#2ecc71;
  --accent-dark:#27b864;
  --danger:#e74c3c;
  --border:#e9e9e9;
  --shadow:0 6px 18px rgba(21,21,21,0.06);
  --radius:10px;
  --gap:20px;
  --font-sans:"Inter","Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
}
*{box-sizing:border-box}
html,body{height:100%;margin:0;font-family:var(--font-sans);background:#f5f7f8;color:#222}
.container{max-width:1100px;margin:0 auto;padding:0 16px}
.header{background:transparent;border-bottom:1px solid rgba(0,0,0,0.04)}
.header .container{padding:0}
.header__inner{display:flex;gap:20px;align-items:center;justify-content:space-between;padding:12px 6px}
.header__brand{display:flex;align-items:center;gap:12px}
.header__logo{width:46px;height:46px;object-fit:cover;border-radius:8px;box-shadow:var(--shadow)}
.header__site-name{font-weight:700;font-size:18px;color:#163a24}
.header__nav{display:flex;gap:14px}
.header__nav-link{color:var(--muted);text-decoration:none;padding:8px 12px;border-radius:8px;font-weight:600}
.header__nav-link.active{background:rgba(46,204,113,0.08);color:var(--accent-dark)}
.header__actions{display:flex;align-items:center;gap:12px}
.main.container{
  width:100vw;
  max-width:none;
  margin-left:50%;
  transform:translateX(-50%);
  padding:28px 16px;
  background:linear-gradient(180deg,#fff7f0 0%,#ffeede 100%);
}
.product-page{display:flex;gap:28px;align-items:flex-start;max-width:1100px;margin:0 auto}
.product-media{flex:0 0 420px;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px;display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow)}
.product-media__img{max-width:100%;height:auto;border-radius:8px;object-fit:cover}
.product-info{flex:1;min-width:0;padding:6px 4px}
.product-info h1{margin:0 0 8px;font-size:1.6rem;color:#163a24}
.product-info p{margin:0 0 14px;color:var(--muted);line-height:1.45}
.pc-price{font-weight:800;font-size:1.4rem;color:var(--accent-dark);margin-bottom:14px}
.pc-actions{display:flex;gap:12px;align-items:center}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;border:1px solid var(--border);cursor:pointer;font-weight:700;background:#fff}
.btn-primary{background:var(--accent);color:#fff;border-color:var(--accent)}
.btn-outline{background:transparent;color:var(--muted);border-color:rgba(0,0,0,0.06)}
.notice{padding:10px 12px;border-radius:10px;margin-bottom:12px}
.notice.success{background:#eafaf0;color:#155b36;border:1px solid rgba(23,91,58,0.06)}
.footer{position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid #e4e7ea;padding:8px 0;font-size:12px;color:#666;z-index:999}
.footer .container{display:flex;justify-content:center;align-items:center;height:32px;padding:0}
.products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;margin-top:18px}
.product-card{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;display:flex;flex-direction:column;box-shadow:var(--shadow)}
.pc-image img{width:100%;height:220px;object-fit:cover}
.pc-body{padding:16px;flex:1;display:flex;flex-direction:column}
.pc-title{font-size:18px;margin:0 0 8px;font-weight:800}
.pc-meta{color:var(--muted);font-size:13px;margin:0 0 10px}
.pc-price{font-weight:800;margin-top:auto;font-size:18px;color:var(--accent-dark)}
.pc-actions{display:flex;gap:8px;margin-top:12px;align-items:center}
@media (max-width:900px){
  .product-page{flex-direction:column;align-items:center}
  .product-media{width:90%;flex-basis:auto}
  .main.container{padding:20px 12px}
}
@media (max-width:520px){
  .header__inner{flex-direction:column;align-items:flex-start;gap:10px;padding:10px}
  .header__logo{width:40px;height:40px}
  .product-info h1{font-size:1.2rem}
  .pc-image img{height:160px}
}

  </style>
</head>
<body>

<header class="header">
  <div class="container">
     <div class="header__inner">
       <div class="header__brand">
         <img src="icons/logo.png" alt="Логотип" class="header__logo">
         <span class="header__site-name">Флорист</span>
       </div>

       <nav class="header__nav">
         <a href="index.php" class="header__nav-link">Главная</a>
         <a href="index.php" class="header__nav-link active">Каталог</a>
       </nav>

       <div class="header__actions">
         <a href="cart.php" class="header__cart" id="headerCart" data-count="<?php echo (int)$cartCount; ?>">
           <i class="ri-shopping-basket-line"></i><span>Корзина</span>
           <sup class="cart-count"><?php echo $cartCount ?: ''; ?></sup>
         </a>
       </div>
     </div>
   </div>
</header>

<main class="main container">
  <?php if ($flashAdded && (string)$flashAdded === (string)$itemId): ?>
    <div class="notice success">Товар добавлен в корзину.</div>
  <?php elseif ($flashAdded): ?>
    <div class="notice success">Товар добавлен в корзину.</div>
  <?php endif; ?>

  <div class="product-page">
    <div class="product-media">
      <img src="<?php echo e($img); ?>" alt="<?php echo e($item['name']); ?>" class="product-media__img">
    </div>

    <div class="product-info">
      <h1><?php echo e($item['name']); ?></h1>

      <?php
       
        $dbDesc = $item['opisanie'] ?? null;
        $catalogDesc = $item['description'] ?? null;
        $desc = $dbDesc ?: $catalogDesc;
      ?>

      <?php if (!empty($desc)): ?>
        <p><?php echo nl2br(e($desc)); ?></p>
      <?php endif; ?>

      <div class="pc-price"><?php echo number_format($item['price'], 0, '', ' '); ?> ₽</div>

      <div class="pc-actions">
        <form method="POST" action="cart.php" style="display:inline;">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="id" value="<?php echo e($itemId); ?>">
          <input type="hidden" name="qty" value="1">
          <input type="hidden" name="return" value="<?php echo e($_SERVER['REQUEST_URI']); ?>">
          <button type="submit" class="btn btn-primary">Заказать</button>
        </form>

       <a href="index.php#buket" class="btn btn-outline">Вернуться в каталог</a>
      </div>
    </div>
  </div>
</main>

<footer class="footer">
  <div class="container">
    <p>© <?php echo date("Y"); ?> Интернет-магазин флористики. Все права защищены.</p>
  </div>
</footer>

</body>
</html>
