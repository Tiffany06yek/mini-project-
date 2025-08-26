<?php
require __DIR__ . '/data.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = $_GET['type'] ?? null; // 用于“返回”链接的回到哪个标签
$order = find_order($id);
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>订单详情 #<?= safe($id) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root { --bg:#f7f7fb; --card:#fff; --line:#e5e7eb; --muted:#6b7280; }
  body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial;
       margin:24px;background:var(--bg);color:#111;}
  .wrap{max-width:920px;margin:auto}
  .back{display:inline-block;margin-bottom:16px;text-decoration:none;color:#2563eb}
  .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px;margin-bottom:12px}
  .grid{display:grid;grid-template-columns: 1fr 1fr;gap:12px}
  .label{color:var(--muted);font-size:14px}
  .value{font-weight:600}
  .status{display:inline-flex;align-items:center;gap:8px;padding:4px 10px;border:1px solid var(--line);
          border-radius:999px;background:#fff;font-size:14px}
  .dot{width:8px;height:8px;border-radius:50%}
  .status.preparing{color:#f59e0b}.status.preparing .dot{background:#f59e0b}
  .status.on_the_way{color:#3b82f6}.status.on_the_way .dot{background:#3b82f6}
  .status.ready_for_pickup{color:#10b981}.status.ready_for_pickup .dot{background:#10b981}
  .status.delivered{color:#6b7280}.status.delivered .dot{background:#6b7280}
  .status.picked_up{color:#6b7280}.status.picked_up .dot{background:#6b7280}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left}
  tfoot td{font-weight:700}
</style>
</head>
<body>
<div class="wrap">
  <a class="back" href="index.php<?= $type ? '?type='.safe($type) : '' ?>">&larr; 返回列表</a>

  <?php if (!$order): ?>
    <div class="card"><strong>找不到订单 #<?= safe($id) ?></strong></div>
  <?php else: ?>
    <div class="card">
      <h2>订单 #<?= safe($order['id']) ?></h2>
      <div class="grid">
        <div>
          <div class="label">类型</div>
          <div class="value"><?= safe($order['type'] === 'delivery' ? 'Delivery' : 'Pickup') ?></div>
        </div>
        <div>
          <div class="label">预计到达</div>
          <div class="value"><?= safe(date('Y-m-d H:i', strtotime($order['eta']))) ?></div>
        </div>
        <div>
          <div class="label">状态</div>
          <div class="value">
            <span class="status <?= safe($order['status']) ?>">
              <span class="dot"></span><?= safe(status_label($order['status'])) ?>
            </span>
          </div>
        </div>
        <div>
          <div class="label"><?= $order['type']==='delivery' ? '配送人员' : '取餐方式' ?></div>
          <div class="value">
            <?= $order['type']==='delivery' ? safe($order['courier'] ?? '-') : '到柜台自取' ?>
          </div>
        </div>∏
        <div>
          <div class="label"><?= $order['type']==='delivery' ? '送达地点' : '取餐地点' ?></div>
          <div class="value"><?= safe($order['destination']) ?></div>
        </div>
      </div>
    </div>

    <div class="card">
      <h3>Order Bills（账单）</h3>
      <table>
        <thead>
          <tr><th>食物</th><th>数量</th><th>单价（RM）</th><th>小计（RM）</th></tr>
        </thead>
        <tbody>
          <?php foreach ($order['items'] as $it): ?>
            <tr>
              <td><?= safe($it['name']) ?></td>
              <td><?= (int)$it['qty'] ?></td>
              <td><?= number_format($it['price'], 2) ?></td>
              <td><?= number_format($it['qty'] * $it['price'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" style="text-align:right">Total</td>
            <td><?= number_format(order_total($order), 2) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
