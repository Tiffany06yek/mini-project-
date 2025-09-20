<?php
require __DIR__ . '/../backend/database.php';
<<<<<<< HEAD

=======
>>>>>>> 714f3b0 (Save submodule changes: update pages/assets/db/config)
$tag = $_GET['tag'] ?? 'all';
$allow = ['all','Asian','Western','Chinese','Halal', 'Drinks'];

if (!in_array($tag, $allow, true)) {
    $tag = 'all';
}

<<<<<<< HEAD
$sql = "SELECT id AS res_id, image_url, name,  `type` AS merchant_type, tags, location, open_hours, delivery_fee, eta, rating
=======
$sql = "SELECT id, image_url, name, type, tags, location, open_hours, delivery_fee, eta, rating
>>>>>>> 714f3b0 (Save submodule changes: update pages/assets/db/config)
        FROM merchants WHERE 1";
$params = []; 
$types = '';
$sql.= " AND type = 'restaurant'";  

if ($tag !== 'all') {
  $sql.= " AND FIND_IN_SET(?, tags) ";  
  $params[] = $tag; 
  $types .= 's';
}

$sql .= " ORDER BY rating DESC, name ASC";

$stmt = mysqli_prepare($conn, $sql);
if ($params) { 
    mysqli_stmt_bind_param($stmt, $types, ...$params); }
    mysqli_stmt_execute($stmt);
    $res  = mysqli_stmt_get_result($stmt);
    $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

    // Minimarts：只要 grocery
$mini_sql = "SELECT id AS mart_id, image_url, type, name, location, rating
             FROM merchants
             WHERE type = 'grocery'
             ORDER BY name ASC";

$mini_stmt = mysqli_prepare($conn, $mini_sql);
mysqli_stmt_execute($mini_stmt);
$mini_res  = mysqli_stmt_get_result($mini_stmt);
$minimarts = $mini_res ? mysqli_fetch_all($mini_res, MYSQLI_ASSOC) : [];


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>XIApee - Main Page</title>
  <link rel="stylesheet" href="../design/css/main.css">
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
        <a href="?tag=Drinks"   class="category<?= $tag==='Drinks'?' active':''?>">Drinks</a>
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

        <h2 class="section-title">Show Minimarts</h2>
    <div class="minimarts-grid restaurant-grid">
      <?php if (!$minimarts): ?>
        <p>No Minimarts Found</p>
      <?php else: foreach ($minimarts as $mm): ?>
        <div class="restaurant-card">
          <div class="card-image">
            <?php if (!empty($mm['image_url'])): ?>
              <img src="<?= htmlspecialchars($mm['image_url']) ?>"
                  alt="<?= htmlspecialchars($mm['name']) ?>" loading="lazy">
            <?php else: ?>
              <span class="image-placeholder">🏪</span>
            <?php endif; ?>
          </div>

          <div class="card-content">
            <div class="restaurant-name"><?= htmlspecialchars($mm['name']) ?></div>
            <div class="restaurant-meta">
              <?php if (!empty($mm['rating'])): ?>
                <div class="rating">★ <?= htmlspecialchars($mm['rating']) ?></div>
              <?php endif; ?>
              <span><?= htmlspecialchars($mm['tags'] ?? 'Grocery') ?></span>
            </div>
            <div class="restaurant-meta"><?= htmlspecialchars($mm['location'] ?? '') ?></div>
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
