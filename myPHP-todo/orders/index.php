<?php
require __DIR__ . '/data.php'; // 提供 $ORDERS、safe()、order_total()、status_label()

// 1) 读取并校验 type
$type = isset($_GET['type']) ? $_GET['type'] : 'delivery';
if ($type !== 'delivery' && $type !== 'pickup') {
  $type = 'delivery';
}

// 2) 过滤出当前类型的订单（用最直观的 foreach）
$orders = [];
foreach ($ORDERS as $o) {
  if ($o['type'] === $type) {
    $orders[] = $o;
  }
}

// 3) 计算标签的激活样式（少用行内复杂表达式）
$activeDelivery = ($type === 'delivery') ? 'active' : '';
$activePickup   = ($type === 'pickup')   ? 'active' : '';

?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>订单追踪</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
  /* 为了更直观，这里直接写具体颜色，不用 CSS 变量 */
  body{
    font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial;
    margin:24px;background:#f7f7fb;color:#111;
  }
  .wrap{max-width:920px;margin:auto;}

  .tabs{display:flex;gap:12px;margin-bottom:20px;}
  .tab{
    padding:10px 16px;border:1px solid #e5e7eb;border-radius:999px;background:#fff;
    text-decoration:none;color:#111
  }
  .tab.active{box-shadow:0 2px 10px rgba(0,0,0,.05);font-weight:600}

  .empty{color:#6b7280;margin-top:24px}

  .list{display:grid;gap:12px}
  .card{
    display:block;background:#fff;border:1px solid #e5e7eb;border-radius:14px;
    padding:14px 16px;text-decoration:none;color:inherit
  }
  .row{display:flex;justify-content:space-between;align-items:center}
  .id{font-weight:700}
  .eta{color:#6b7280;font-size:14px}
  .meta{
    margin-top:6px;color:#6b7280;display:flex;justify-content:space-between;font-size:14px
  }

  .status{
    display:inline-flex;align-items:center;gap:8px;padding:4px 10px;border-radius:999px;
    border:1px solid #e5e7eb;background:#fff;font-size:14px
  }
  .dot{width:8px;height:8px;border-radius:50%}

  /* 不同状态的颜色 */
  .status.preparing{color:#f59e0b}.status.preparing .dot{background:#f59e0b}
  .status.on_the_way{color:#3b82f6}.status.on_the_way .dot{background:#3b82f6}
  .status.ready_for_pickup{color:#10b981}.status.ready_for_pickup .dot{background:#10b981}
  .status.delivered{color:#6b7280}.status.delivered .dot{background:#6b7280}
  .status.picked_up{color:#6b7280}.status.picked_up .dot{background:#6b7280}
</style>
</head>

<body>
<div class="wrap">
  <h1>订单追踪</h1>

  <!-- 4) 顶部标签：激活态用预先算好的变量 -->
  <div class="tabs">
    <a class="tab <?php echo $activeDelivery; ?>" href="?type=delivery">Delivery</a>
    <a class="tab <?php echo $activePickup;   ?>" href="?type=pickup">Pickup</a>
  </div>

  <!-- 5) 如果没有订单，显示空提示；否则渲染列表 -->
  <?php if (count($orders) === 0): ?>
    <p class="empty">暂无 <?php echo safe($type); ?> 订单。</p>
  <?php else: ?>
    <div class="list">
      <?php foreach ($orders as $o): ?>
        <a class="card"
           href="order.php?id=<?php echo (int)$o['id']; ?>&type=<?php echo safe($type); ?>">

          <div class="row">
            <div>
              <div class="id">#<?php echo safe($o['id']); ?></div>
              <div class="eta">
                预计到达：
                <?php
                  // 显示 “时:分” 更直观
                  $t = strtotime($o['eta']);
                  echo safe(date('H:i', $t));
                ?>
              </div>
            </div>

            <div class="status <?php echo safe($o['status']); ?>">
              <span class="dot"></span>
              <?php echo safe(status_label($o['status'])); ?>
            </div>
          </div>

          <div class="meta">
            <span><?php echo safe($o['destination']); ?></span>
            <span>
              共 <?php echo count($o['items']); ?> 件 |
              RM <?php echo number_format(order_total($o), 2); ?>
            </span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
