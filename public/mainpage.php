<?php
require __DIR__ . '/../backend/database.php'; // 假设此处會建立 $conn (mysqli)

$tag = $_GET['tag'] ?? 'all';
$allow = ['all','Asian','Western','Chinese','Halal','Drinks'];

if (!in_array($tag, $allow, true)) {
    $tag = 'all';
}

// 基本 SQL（固定只取 type = 'restaurant'）
$sql = "SELECT id AS res_id, image_url, name, `type` AS merchant_type, tags, location, open_hours, delivery_fee, eta, rating
        FROM merchants
        WHERE `type` = 'restaurant'";

$params = [];
// 若選擇特定 tag，加入條件並準備綁定
if ($tag !== 'all') {
    $sql .= " AND FIND_IN_SET(?, tags)";
    $params[] = $tag;
}

$sql .= " ORDER BY rating DESC, name ASC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt === false) {
    // 開發時可打 log 或顯示錯誤（production 不建議顯示詳細錯誤）
    error_log("Prepare failed: " . mysqli_error($conn));
    $rows = [];
} else {
    if ($params) {
        // 只有一個 param（tag），所以直接 bind
        mysqli_stmt_bind_param($stmt, 's', $params[0]);
    }
    mysqli_stmt_execute($stmt);
    $res  = mysqli_stmt_get_result($stmt);
    $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
}

// Minimarts：只要 type = 'grocery'
$mini_sql = "SELECT id AS mart_id, image_url, `type`, name, location, rating
             FROM merchants
             WHERE `type` = 'grocery'
             ORDER BY name ASC";

$mini_stmt = mysqli_prepare($conn, $mini_sql);
if ($mini_stmt) {
    mysqli_stmt_execute($mini_stmt);
    $mini_res  = mysqli_stmt_get_result($mini_stmt);
    $minimarts = $mini_res ? mysqli_fetch_all($mini_res, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($mini_stmt);
} else {
    error_log("Prepare minimart failed: " . mysqli_error($conn));
    $minimarts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>XIApee - Main Page</title>
  <link rel="stylesheet" href="/public/assets/css/main.css">
</head>
<body>

  <div class="main-container">
  <header class="container"></header>
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
        <a class="restaurant-card" href="/public/menu.html?id=<?= urlencode($m['res_id']) ?>">
          <div class="card-image">
            <?php if (!empty($m['image_url'])): ?>
              <img src="/public/assets/img/merchant_image/<?= htmlspecialchars($m['image_url']) ?>"
                  alt="<?= htmlspecialchars($m['name']) ?>">
            <?php else: ?>
              <span class="image-placeholder">🍳</span>
            <?php endif; ?>
          </div>

          <div class="card-content">
            <div class="restaurant-name"><?= htmlspecialchars($m['name']) ?></div>

            <div class="restaurant-meta">
              <div class="rating">★ <?= htmlspecialchars($m['rating'] ?? '—') ?></div>
              <span>RM <?= number_format((float)($m['delivery_fee'] ?? 0), 2) ?></span>
            </div>

            <div class="restaurant-meta">
              <span><?= htmlspecialchars($m['tags'] ?? '') ?></span>
              <?php if (!empty($m['eta'])): ?>
                <span>· <?= (int)$m['eta'] ?> min</span>
              <?php endif; ?>
            </div>

            <div class="restaurant-meta"><?= htmlspecialchars($m['location'] ?? '') ?></div>
          </div>
        </a>
      <?php endforeach; endif; ?>
        </div>
    </div>

    <h2 class="section-title">Show Minimarts</h2>
    <div class="minimarts-grid restaurant-grid">
      <?php if (!$minimarts): ?>
        <p>No Minimarts Found</p>
      <?php else: foreach ($minimarts as $mm): ?>
        <a class="restaurant-card" href="/public/menu.html?id=<?= urlencode($mm['mart_id']) ?>">
          <div class="card-image">
            <?php if (!empty($mm['image_url'])): ?>
              <img src="/public/assets/img/merchant_image/<?= htmlspecialchars($mm['image_url']) ?>"
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
              <span>Grocery</span>
            </div>
            <div class="restaurant-meta"><?= htmlspecialchars($mm['location'] ?? '') ?></div>
          </div>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

    <!-- 统一以模块方式加载入口文件（main.js 会负责 import 其它模块） -->
    <script type="module" src="/public/assets/js/main.js"></script>

</body>
</html>
