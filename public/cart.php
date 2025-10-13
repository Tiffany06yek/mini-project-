<?php
// public/cart.php
// 兼容多种 session/cart 存储格式并避免 "Undefined array key" 警告
session_start();

// 取 session 中的 cart（可能为空、可能不是期望结构）
$rawCart = $_SESSION['cart'] ?? [];

function normalize_cart_item($it) {
    // 如果 item 是数组且有 numeric index 1 且 index 1 为数组，说明是 [key, value] 形式
    if (is_array($it) && array_key_exists(1, $it) && is_array($it[1])) {
        $it = $it[1];
    }

    // 如果还是非数组，返回默认空项
    if (!is_array($it)) {
        return [
            'id' => null,
            'price' => 0.0,
            'qty' => 0,
            'vendor_id' => null,
            'vendorId' => null,
            'name' => '',
            'addons' => []
        ];
    }

    // 取值并设置默认
    $price = 0.0;
    if (array_key_exists('price', $it)) {
        $price = floatval($it['price']);
    } elseif (array_key_exists('price_total', $it)) { // 备用字段名
        $price = floatval($it['price_total']);
    }

    $qty = 0;
    if (array_key_exists('qty', $it)) {
        $qty = intval($it['qty']);
    } elseif (array_key_exists('quantity', $it)) {
        $qty = intval($it['quantity']);
    }

    $vendor = null;
    if (array_key_exists('vendor_id', $it)) $vendor = $it['vendor_id'];
    elseif (array_key_exists('vendorId', $it)) $vendor = $it['vendorId'];
    elseif (array_key_exists('restaurant_id', $it)) $vendor = $it['restaurant_id'];
    elseif (array_key_exists('restaurantId', $it)) $vendor = $it['restaurantId'];

    return [
        'id' => $it['id'] ?? ($it['item_key'] ?? null),
        'price' => $price,
        'qty' => $qty,
        'vendor_id' => $vendor,
        'vendorId' => $vendor,
        'name' => $it['name'] ?? ($it['product_name'] ?? ''),
        'addons' => $it['addons'] ?? []
    ];
}

// 逐项归一化并计算小计与 vendor 去重
$subtotal = 0.0;
$vendors = [];

if (is_array($rawCart)) {
    foreach ($rawCart as $rawIt) {
        $it = normalize_cart_item($rawIt);

        // 跳过 qty 为 0 的项（没有商品就不计）
        if (empty($it['qty'])) continue;

        $subtotal += floatval($it['price']) * intval($it['qty']);

        if ($it['vendor_id'] !== null && $it['vendor_id'] !== '') {
            $vendors[(string)$it['vendor_id']] = true;
        }
    }
}

// 计算运费：示例为每个商家 RM2.00（可按需修改）
$vendorCount = count($vendors);
$deliveryFeePerVendor = 2.00;
$deliveryFee = $vendorCount * $deliveryFeePerVendor;

$total = $subtotal + $deliveryFee;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>XIApee - Shopping Cart</title>
  <link rel="stylesheet" href="/public/assets/css/cart.css">
</head>
<body>
  <header class="container"></header>

  <div class="main-container">
    <div class="cart-page-header">
      <h1 class="hero-title">Shopping Cart</h1>
      <p class="cart-subtitle">Review your items before checkout</p>
    </div>

    <div class="cart-content">
      <div class="cart-items-section">
        <div class="cart-items-container" id="cart-items-container">
          <!-- Cart items will be populated by JavaScript -->
        </div>
      </div>

      <div class="cart-summary-section">
        <div class="cart-summary">
          <h3>Order Summary</h3>
          <div class="summary-line">
            <span>Subtotal:</span>
            <span id="subtotal">RM <?php echo number_format($subtotal, 2); ?></span>
          </div>
          <div class="summary-line">
            <span>Delivery Fee:</span>
            <span id="delivery-fee">RM <?php echo number_format($deliveryFee, 2); ?></span>
          </div>
          <div class="summary-line total">
            <span>Total:</span>
            <span id="total">RM <?php echo number_format($total, 2); ?></span>
          </div>
          <button class="checkout-btn" id="checkout-btn" <?php echo ($subtotal <= 0 ? 'disabled' : '') ; ?>>
            Proceed to Checkout
          </button>
          <button class="clear-cart-btn" id="clear-cart-btn">
            Clear Cart
          </button>
        </div>
      </div>
    </div>

    <div class="empty-cart" id="empty-cart" style="<?php echo ($subtotal <= 0 ? 'display:block;' : 'display:none;'); ?>">
      <div class="empty-cart-content">
        <div class="empty-cart-icon">🛒</div>
        <h2>Your cart is empty</h2>
        <p>Add some delicious items to get started!</p>
        <a href="/public/mainpage.php" class="continue-shopping-btn">Continue Shopping</a>
      </div>
    </div>
  </div>

  <script type="module">
    import { createCart, globalCart } from '/public/assets/js/cart.js';

    // render widget into container
    const widget = createCart('#cart-items-container');

    // Optional: listen for custom events (debug)
    window.addEventListener('xiapee.cart.updated', (e) => {
      console.log('cart updated on cart.php', e.detail);
    });

    // Optionally re-render (should be automatic)
    widget && widget.render && widget.render();
  </script>
</body>
</html>
