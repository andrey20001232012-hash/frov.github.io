<?php

session_start();
require_once 'functions.php'; 

function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$catalogData = function_exists('getCatalogWithCategories') ? getCatalogWithCategories() : [];

$currentCategory = isset($_GET['category']) ? (string)$_GET['category'] : 'all';
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'default';
$q = trim((string)($_GET['q'] ?? ''));

if ($currentCategory !== 'all' && $currentCategory !== '' && isset($catalogData[$currentCategory])) {
    $itemsToShow = $catalogData[$currentCategory];
} else {
    $itemsToShow = [];
    foreach ($catalogData as $items) {
        if (is_array($items)) $itemsToShow = array_merge($itemsToShow, $items);
    }
}


if ($q !== '') {
    $qLower = mb_strtolower($q);
    $itemsToShow = array_filter($itemsToShow, function($it) use ($qLower) {
        $name = mb_strtolower($it['name'] ?? '');
        $desc = mb_strtolower($it['opisanie'] ?? $it['description'] ?? '');
        return mb_stripos($name . ' ' . $desc, $qLower) !== false;
    });
    $itemsToShow = array_values($itemsToShow);
}


if ($sort === 'price_asc') {
    usort($itemsToShow, function($a, $b) {
        return (float)($a['price'] ?? 0) <=> (float)($b['price'] ?? 0);
    });
} elseif ($sort === 'price_desc') {
    usort($itemsToShow, function($a, $b) {
        return (float)($b['price'] ?? 0) <=> (float)($a['price'] ?? 0);
    });
}


$cartCount = 0;
foreach ($_SESSION['cart'] ?? [] as $cqty) $cartCount += (int)$cqty;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Интернет-магазин флористики</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.9.0/fonts/remixicon.css">
  <style>
:root{
  --bg:#f6fbf7;
  --card:#ffffff;
  --muted:#607d6a;
  --accent:#dff7e6;
  --border:#d8eddc;
  --text:#0e3e23;
  --accent-strong:#9fd6a6;
  --btn-bg:linear-gradient(180deg,#eaf9ee,#dff3e6);
}

.header__inner{display:flex;align-items:center;justify-content:space-between;padding:12px 0}
.header__brand{display:flex;align-items:center;gap:10px}
.header__nav{flex:1;margin-left:24px}
.header__contact{display:flex;flex-direction:column;align-items:flex-end;gap:4px}

.container{max-width:1100px;margin:0 auto;padding:0 16px;color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial}

.header{background:var(--bg);border-bottom:1px solid var(--border)}
.header__logo{width:40px;height:40px;border-radius:6px;object-fit:cover}
.header__site-name{font-weight:700;color:var(--text);font-size:18px}
.header__nav-link{color:var(--text);text-decoration:none;padding:6px 8px;border-radius:6px}
.header__nav-link.active,
.header__nav-link:hover{background:var(--accent);color:#08331a}

.header__phone{display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--text)}
.header__phone-icon{width:40px;height:40px;border-radius:6px;object-fit:contain}
.header__work-hours{font-size:12px;color:var(--muted);margin:0}

.site-banner{background:linear-gradient(180deg,#f0fbf5,#ecf8ef);padding:22px 0;text-align:center}
.site-banner h1{margin:0;color:#0b4f27;font-size:28px}
.site-banner p{margin:6px 0 0;color:var(--muted)}

.about-us .container{background:var(--card);border:1px solid var(--border);padding:18px;border-radius:12px;display:flex;gap:18px;align-items:center}
.about-us h2{margin:0 0 8px;font-size:20px;color:#13512b}
.about-us p, .about-us ul{color:var(--muted);margin:0}

.main.container{padding-top:16px}
.breadcrumbs{color:var(--muted);font-size:14px}

.controls-row{display:flex;gap:12px;align-items:center;background:var(--accent);padding:10px;border-radius:10px;border:1px solid var(--border)}
#controlsForm{display:flex;gap:8px;align-items:center;margin:0}
#controlsForm label{font-weight:600;color:#0b4f1f;margin-right:4px;font-size:14px}
#controlsForm input[type="search"],
#controlsForm input[type="text"],
#controlsForm select{background:#fff;border:1px solid #cde9d7;padding:8px 10px;border-radius:6px;color:var(--text);font-size:14px;min-height:36px}
#controlsForm input::placeholder{color:#86a985}

.btn{display:inline-flex;align-items:center;justify-content:center;border-radius:8px;padding:8px 12px;border:1px solid transparent;cursor:pointer;font-weight:600}
.btn.btn-outline{background:var(--btn-bg);border:1px solid var(--accent-strong);color:#0b4f1f;box-shadow:0 1px 0 rgba(6,40,18,0.03)}
.btn.btn-primary{background:linear-gradient(180deg,#bfe9c6,#9fd6a6);color:#053217;border:1px solid #84bf8a}
.btn:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(11,66,32,0.06)}

.catalog-sidebar{width:200px;flex:0 0 200px}
.catalog-sidebar h3{margin:0 0 8px;color:#0d4725}
.catalog-sidebar ul{list-style:none;padding-left:0;margin:0}
.catalog-sidebar a{display:block;padding:6px 8px;border-radius:6px;color:var(--text);text-decoration:none}
.catalog-sidebar a[aria-current="true"], .catalog-sidebar a:hover{background:var(--accent-strong);color:#052512}

.products-grid{display:grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap:18px;
  align-items:start;
}
.product-card:hover{
  transform:translateY(-4px);
  box-shadow:0 10px 30px rgba(6,40,18,0.06);
}

.product-card .pc-image{
  display:block;
  width:100%;
  border-radius:8px;
  overflow:hidden;
  text-decoration:none;
}
.product-card{
  background:var(--card);
  border:1px solid var(--border);
  padding:12px;
  border-radius:10px;
  display:flex;
  flex-direction:column;
  gap:12px;
  min-height:160px;
  box-shadow:0 4px 10px rgba(6,40,18,0.03);
  transition:transform .12s ease, box-shadow .12s ease;
}
.product-card img{
  display:block;
  width:100%;
  height:160px;
  object-fit:cover;
  border-radius:8px;
  border:1px solid rgba(0,0,0,0.03);
}
.search-input-wrap input[type="text"],
.search-input-wrap input[type="search"]{
  border:0;
  padding:8px 6px;
  font-size:15px;
  outline:none;
  background:transparent;
  color:var(--text);
  width:260px;
  min-width:120px;
}

.pc-body{display:flex;flex-direction:column;gap:8px}
.pc-title{margin:0;font-size:16px;color:var(--text);line-height:1.2}
.pc-info{display:flex;justify-content:space-between;align-items:flex-end;gap:12px}
.pc-text{display:flex;flex-direction:column;gap:6px}
.pc-price{font-weight:800;color:#08421d;font-size:18px}
.small.text-muted{color:var(--muted);font-size:13px}
.out-of-stock{color:#d00;font-weight:700;margin-top:4px;display:none}


.product-card.is-out .out-of-stock{display:block}
.product-card.is-out .btn.btn-primary{opacity:0.6;cursor:not-allowed;pointer-events:none;}


.pc-actions{display:flex;flex-direction:column;gap:8px;align-items:flex-end}
.pc-actions .btn{min-width:96px}
.pc-meta{margin:0;color:var(--muted);font-size:13px}
.pc-price{font-weight:800;color:#08421d}
.small.text-muted{color:var(--muted);font-size:13px}
.pc-actions{display:flex;gap:8px;align-items:center;margin-top:8px}

footer{margin-top:28px;padding:18px 0;background:#fbfdf9;color:var(--muted);border-top:1px solid var(--border)}

@media (max-width:900px){
  .header__nav{display:none}
  .catalog-sidebar{display:none}
  .product-card{
  background:var(--card,#fff);
  border:2px solid var(--border,#d8eddc);
  box-shadow:0 6px 18px rgba(11,66,32,0.03);
  padding:16px;
  border-radius:12px;
  display:flex;
  gap:16px;
  align-items:center;
}
  .header__contact{align-items:flex-start}
  .controls-row{flex-wrap:wrap}
}
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
    <a href="index.php" class="header__nav-link active" style="margin-right:12px">Главная</a>
    <a href="#about-us" class="header__nav-link">Доставка</a>
    <a href="zakaz.php" class="header__nav-link" style="margin-right:12px">Заказы</a>
    
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
      <input type="hidden" name="category" value="<?php echo e($currentCategory); ?>">
      <input type="hidden" name="sort" value="<?php echo e($sort); ?>">
      <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Поиск..." class="header__search-input" aria-label="Поиск" style="padding:6px 8px;border:1px solid #ccc;border-radius:6px">
      <button type="submit" class="header__search-btn" style="margin-left:6px;padding:6px 8px"><i class="ri-search-line" aria-hidden="true"></i></button>
    </form>

    <div class="header__quick-action" style="display:flex;gap:12px;align-items:center;margin-left:12px">
      <a href="cart.php" class="header__cart" aria-label="Корзина" style="text-decoration:none;color:inherit">
        <i class="ri-shopping-basket-line" aria-hidden="true"></i>
        <span>Корзина</span><sup class="cart-count"><?php echo $cartCount ?: ''; ?></sup>
      </a>
      <a href="vhod_admin.php" class="header__account" aria-label="Личный кабинет" style="text-decoration:none;color:inherit">
        <i class="ri-user-fill" aria-hidden="true"></i>
        <span>Личный кабинет</span>
      </a>
    </div>
  </div>
</div>

   </div>
</header>
<div class="container">
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="flash flash-error" role="alert"><?php echo e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="flash flash-success" role="status"><?php echo e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>
</div>

<section class="site-banner">
  <div class="container" style="padding:18px 0;text-align:center"  id="about-us">
      <h1>Каталог цветов</h1>
      <p>Свежие букеты и авторские композиции каждый день</p>
  </div>
</section>
<section class="about-us" style="margin:20px 0;" >
  <div class="container" style="background:var(--card);border:1px solid var(--border);padding:18px;border-radius:12px;display:flex;gap:18px;align-items:center;">
    <div style="flex:0 0 160px;">
      <img src="icons/florist.jpg" alt="О нас" style="width:160px;height:120px;object-fit:cover;border-radius:8px;">
    </div>
    <div style="flex:1;">
      <h2 style="margin:0 0 8px;font-size:20px;color:#13512b;">О нас</h2>
      <p style="margin:0 0 8px;color:var(--muted);">
          Наша команда — профессиональные флористы с многолетним опытом. Мы создаём свежие букеты и авторские композиции из отборных цветов, доставляем по городу и помогаем подобрать подарок под любой повод.
        </p>
        <ul style="margin:0;padding-left:18px;color:var(--muted);">
          <li><strong>Свежесть:</strong> ежедневная поставка цветов</li>
          <li><strong>Гарантия:</strong> аккуратная упаковка и качество</li>
          <li><strong>Доставка:</strong> только по Невинномысску — фиксированная стоимость 300 ₽</li>
          <li><strong>Оплата:</strong> картой или наличными курьеру</li>
        </ul>

    </div>
  </div>
</section>
<main class="main container" style="padding-top:16px" id="buket">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <div class="breadcrumbs">Главная › Каталог <?php if ($currentCategory !== 'all' && $currentCategory !== '') echo '› <strong>' . e($currentCategory) . '</strong>'; ?></div>

    <div class="controls-row">
  <form id="controlsForm" method="GET" class="search-form">
    <input type="hidden" name="category" id="controls-category" value="<?php echo e($currentCategory); ?>">
    <label for="q" class="visually-hidden">Поиск</label>

    <div class="search-input-wrap">
      <input id="q" name="q" value="<?php echo e($q); ?>" placeholder="Поиск по названию или описанию" aria-label="Поиск">
      <button type="submit" class="btn btn-outline search-btn" aria-label="Найти">
        <!-- SVG иконка лупы -->
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
          <path d="M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>

    <label for="sort-select">Сортировать:</label>
    <select id="sort-select" name="sort" onchange="document.getElementById('controlsForm').submit()">
      <option value="default" <?php echo $sort === 'default' ? 'selected' : ''; ?>>По умолчанию</option>
      <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Цена ↑</option>
      <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Цена ↓</option>
    </select>

    <button type="submit" class="btn btn-outline">Применить</button>
  </form>
</div>

  </div>

  <div style="display:flex;gap:18px;align-items:flex-start">
          <aside class="catalog-sidebar">
        <h3>Категории</h3>
        <ul>
          <li><a href="?category=all&sort=<?php echo urlencode($sort); ?>&q=<?php echo urlencode($q); ?>" data-cat="all" <?php echo $currentCategory === 'all' ? 'aria-current="true"' : ''; ?>>Все товары</a></li>
          <?php foreach ($catalogData as $catName => $items): ?>
            <li>
              <a href="?category=<?php echo urlencode($catName); ?>&sort=<?php echo urlencode($sort); ?>&q=<?php echo urlencode($q); ?>"
                data-cat="<?php echo e($catName); ?>" <?php echo $currentCategory === $catName ? 'aria-current="true"' : ''; ?>>
                <?php echo e($catName); ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </aside>


    <section style="flex:1">
        <h2>Товары</h2>

        <div class="products-grid">
          <?php if (!empty($itemsToShow)): ?>
            <?php foreach ($itemsToShow as $item):
              $itemId = isset($item['id']) ? (int)$item['id'] : 0;
              $name = $item['name'] ?? '';
              $price = isset($item['price']) ? (float)$item['price'] : 0.0;
              $kol_vo = isset($item['kol_vo']) ? (int)$item['kol_vo'] : 0;
              $imgFile = !empty($item['image']) ? 'kartinka/' . $item['image'] : 'icons/no-image.png';
              $isOut = $kol_vo <= 0;
            ?>
              <article class="product-card" id="product-<?php echo e($itemId); ?>" data-stock="<?php echo (int)$kol_vo; ?>">
                <a href="product.php?id=<?php echo urlencode($itemId ?: $name); ?>" class="pc-image">
                  <img src="<?php echo e($imgFile); ?>" alt="<?php echo e($name); ?>">
                </a>

                <div class="pc-body">
                  <h3 class="pc-title"><?php echo e($name); ?></h3>

                  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                    <div>
                      <div class="pc-price"><?php echo number_format($price, 0, '', ' '); ?> ₽</div>
                      <div class="small text-muted">Остаток: <span class="stock-count"><?php echo max(0, $kol_vo); ?></span></div>
                      <?php if ($isOut): ?>
                        <div style="color:#d00;font-weight:700;margin-top:6px">Нет в наличии</div>
                      <?php endif; ?>
                    </div>

                    <div class="pc-actions">
                      <a href="product.php?id=<?php echo urlencode($itemId ?: $name); ?>" class="btn btn-outline">Подробнее</a>

                      <?php if ($isOut): ?>
                        <button class="btn btn-outline" disabled style="opacity:0.7;cursor:not-allowed;">Нет в наличии</button>
                      <?php else: ?>
                        <form method="POST" action="cart.php" class="add-to-cart-form" data-id="<?php echo e($itemId); ?>" style="display:inline;">
                          <input type="hidden" name="action" value="add">
                          <input type="hidden" name="id" value="<?php echo e($itemId); ?>">
                          <input type="hidden" name="qty" value="1">
                          <input type="hidden" name="return" value="<?php echo e($_SERVER['REQUEST_URI']); ?>">
                          <button type="submit" class="btn btn-primary">В корзину</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php else: ?>
            <p>Товаров в этой категории пока нет.</p>
          <?php endif; ?>
        </div>
      </section>
  </div>
</main>

<footer style="margin-top:28px;padding:18px 0;background:#fafafa">
  <div class="container">
    <p>© <?php echo date("Y"); ?> Интернет-магазин флористики. Все права защищены.</p>
  </div>
</footer>

<script>
(function(){
  
  function ensureToastContainer(){
    var c = document.getElementById('toast-container');
    if (!c) {
      c = document.createElement('div');
      c.id = 'toast-container';
      Object.assign(c.style, { position:'fixed', right:'16px', bottom:'16px', display:'flex', flexDirection:'column', gap:'8px', zIndex:9999, maxWidth:'320px' });
      document.body.appendChild(c);
    }
    return c;
  }
  function showToast(text, type){
    var container = ensureToastContainer();
    var el = document.createElement('div');
    el.textContent = text;
    Object.assign(el.style, { padding:'10px 12px', borderRadius:'8px', color:'#fff', background: (type==='error'?'#d9534f':(type==='success'?'#28a745':'#333')), boxShadow:'0 4px 12px rgba(0,0,0,0.08)', fontSize:'14px', opacity:'0', transition:'opacity 160ms, transform 160ms', transform:'translateY(6px)' });
    container.appendChild(el);
    requestAnimationFrame(function(){ el.style.opacity='1'; el.style.transform='translateY(0)'; });
    var t = setTimeout(function(){ el.style.opacity='0'; el.style.transform='translateY(6px)'; el.addEventListener('transitionend', function(){ el.remove(); }); }, 4000);
    el.addEventListener('click', function(){ clearTimeout(t); el.remove(); });
    return el;
  }


  function $id(id){ return document.getElementById(id); }
  function clearSearchField(){
    var input = $id('q');
    var wrap = $id('searchWrap');
    if (input) input.value = '';
    if (wrap) wrap.classList.remove('has-value');
  }

  var controlsForm = $id('controlsForm');
  var productsGrid = document.querySelector('.products-grid');
  if (!controlsForm || !productsGrid) return;

  function ajaxLoadUrl(newUrl, pushState){
    if (pushState) history.pushState({ajaxCatalog: true}, '', newUrl);
    productsGrid.style.opacity = '0.6';
    return fetch(newUrl, { method: 'GET', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(resp){
        if (!resp.ok) throw new Error('Сервер вернул ' + resp.status);
        return resp.text();
      })
      .then(function(html){
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        var newGrid = tmp.querySelector('.products-grid');
        if (newGrid) {
          productsGrid.innerHTML = newGrid.innerHTML;
          
          var newSidebar = tmp.querySelector('.catalog-sidebar');
          if (newSidebar) {
            var oldSidebar = document.querySelector('.catalog-sidebar');
            if (oldSidebar) oldSidebar.innerHTML = newSidebar.innerHTML;
          }
        } else {
          showToast('Не удалось обновить список товаров (сервер не вернул нужный фрагмент).', 'error');
        }
      })
      .catch(function(err){
        console.error(err);
        showToast('Ошибка при обновлении каталога.', 'error');
      })
      .finally(function(){
        productsGrid.style.opacity = '';
        setTimeout(clearSearchField, 0);
      });
  }

  controlsForm.addEventListener('submit', function(e){
    e.preventDefault();

    
    var formData = new FormData(controlsForm);
    var params = new URLSearchParams();
    for (var pair of formData.entries()) {
      var key = pair[0], val = pair[1];
      if (val !== null && String(val).trim() !== '') params.append(key, val);
    }

    var base = location.pathname;
    var newUrl = base + (params.toString() ? ('?' + params.toString()) : '');

    
    ajaxLoadUrl(newUrl, true);
  }, false);

  document.addEventListener('click', function(e){
    var a = e.target.closest && e.target.closest('a[data-cat]');
    if (!a) return;
  
    if (a.hostname && a.hostname !== location.hostname) return;
    e.preventDefault();
    var href = a.getAttribute('href') || (a.pathname + (a.search || ''));
  
    var newUrl = href.indexOf('http') === 0 ? (new URL(href, location.origin)).pathname + (new URL(href, location.origin)).search : href;
    ajaxLoadUrl(newUrl, true);
  }, false);

    window.addEventListener('popstate', function(){
    var url = location.pathname + location.search;
    var pg = document.querySelector('.products-grid');
    if (!pg) return;
    pg.style.opacity = '0.6';
    fetch(url, { method: 'GET', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(resp){
        if (!resp.ok) throw new Error('Сервер вернул ' + resp.status);
        return resp.text();
      })
      .then(function(html){
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        var newGrid = tmp.querySelector('.products-grid');
        if (newGrid) {
          pg.innerHTML = newGrid.innerHTML;
          var newSidebar = tmp.querySelector('.catalog-sidebar');
          if (newSidebar) {
            var oldSidebar = document.querySelector('.catalog-sidebar');
            if (oldSidebar) oldSidebar.innerHTML = newSidebar.innerHTML;
          }
        }
      })
      .catch(function(err){ console.error(err); })
      .finally(function(){ pg.style.opacity = ''; });
  }, false);

})();
</script>



</body>
</html>
