<?php
require __DIR__ . '/../backend/database.php';
$conn = mysqli_connect($db_server, $db_user, $db_pass, $db_name, $port);

$tag = $_GET['tag'] ?? 'all';
$allow = ['all','Asian','Western','Chinese','Halal', 'Drinks'];

if (!in_array($tag, $allow, true)) {
    $tag = 'all';
}

$sql = "SELECT res_id, image_url, name, merchant_type, tags, location, open_hours, delivery_fee, eta, rating
        FROM merchants WHERE 1";
$params = []; 
$types = '';

if ($tag !== 'all') {
  $sql .= " AND FIND_IN_SET(?, tags)";  
  $params[] = $tag; 
  $types .= 's';
}

$sql .= " ORDER BY rating DESC, name ASC";

$stmt = mysqli_prepare($conn, $sql);
if ($params) { 
    mysqli_stmt_bind_param($stmt, $types, ...$params); 
}
mysqli_stmt_execute($stmt);
$res  = mysqli_stmt_get_result($stmt);
$rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>XIApee - Main Page</title>
  <!-- 改成 assets/ 前缀 -->
  <link rel="stylesheet" href="assets/css/main.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="main-container">
    <div class="left-section">
      <h1 class="hero-title">Get it fast, fresh, and local</h1>

      <div class="tabs">
        <button class="tab active">Restaurants</button>
        <button class="tab inactive">Minimarts</button>
      </div>

      <!-- 分类按钮：用 ?tag=xxx 刷新 -->
      <div class="categories" id="cats">
        <a href="?tag=all"     class="category<?= $tag==='all'?' active':''?>">All</a>
        <a href="?tag=Asian"   class="category<?= $tag==='Asian'?' active':''?>">Asian</a>
        <a href="?tag=Chinese" class="category<?= $tag==='Chinese'?' active':''?>">Chinese</a>
        <a href="?tag=Halal"   class="category<?= $tag==='Halal'?' active':''?>">Halal</a>
        <a href="?tag=Drinks"  class="category<?= $tag==='Drinks'?' active':''?>">Drinks</a>
      </div>
    </div>

    <div class="right-section">
      <h2 class="section-title">Nearby Restaurants</h2>
      <div class="restaurant-grid">
        <?php if (!$rows): ?>
          <p>No results.</p>
        <?php else: foreach ($rows as $m): ?>
          <div class="restaurant-card">
            <div class="card-image">
              <?php if (!empty($m['image_url'])): ?>
                <img src="<?= htmlspecialchars($m['image_url']) ?>"
                     alt="<?= htmlspecialchars($m['name']) ?>">
              <?php else: ?>
                <span class="image-placeholder">🍳</span>
              <?php endif; ?>
            </div>

            <div class="card-content">
              <div class="restaurant-name"><?= htmlspecialchars($m['name']) ?></div>

              <div class="restaurant-meta">
                <div class="rating">★ <?= htmlspecialchars($m['rating'] ?? '—') ?></div>
                <span>RM <?= number_format((float)$m['delivery_fee'], 2) ?></span>
              </div>

              <div class="restaurant-meta">
                <span><?= htmlspecialchars($m['tags'] ?? '') ?></span>
                <?php if (!empty($m['eta'])): ?>
                  <span>· <?= (int)$m['eta'] ?> min</span>
                <?php endif; ?>
              </div>

              <div class="restaurant-meta"><?= htmlspecialchars($m['location'] ?? '') ?></div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- 引入 JS 脚本 -->
  <script src="assets/js/header.js"></script>
  <script src="assets/js/main.js"></script>
  <script src="assets/js/get_restaurant.js"></script>
  <script src="assets/js/product.js"></script>
</body>
</html>
