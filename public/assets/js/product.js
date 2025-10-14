// /public/assets/js/product.js
import { Header } from '/public/assets/js/header.js';
import { globalCart } from '/public/assets/js/cart.js';

// Initialize header
Header();

// API endpoint (确认路径正确)
const API_PATH = '/public/get_product.php';


// DOM elements (与你的 product.html 对应)
const productTitle = document.getElementById('product-title');
const productImage = document.getElementById('product-image');
const productName = document.getElementById('product-name');
const productCategory = document.getElementById('product-category');
const productPrice = document.getElementById('product-price');
const productDescription = document.getElementById('product-description');
const addonsSection = document.getElementById('addons-section');
const addonsContainer = document.getElementById('addons-container');
const qtyDisplay = document.getElementById('qty-display');
const basePriceElement = document.getElementById('base-price');
const addonsPriceLine = document.getElementById('addons-price-line');
const addonsPriceElement = document.getElementById('addons-price');
const totalPriceElement = document.getElementById('total-price');
const addToCartBtn = document.getElementById('add-to-cart-btn');
const buyNowBtn = document.getElementById('buy-now-btn');
const productNotFound = document.getElementById('product-not-found');
// Vendor info elements
const vendorInfoBox = document.getElementById('vendor-info');
const vendorNameEl = document.getElementById('vendor-name');
const vendorCategoryEl = document.getElementById('vendor-category');
const vendorLocationEl = document.getElementById('vendor-location');
const vendorDescEl = document.getElementById('vendor-desc');
const vendorLinkEl = document.getElementById('vendor-link');
// Mini cart elements
const miniItems = document.getElementById('cart-items-mini');
const miniSubtotal = document.getElementById('mini-subtotal');
const miniDelivery = document.getElementById('mini-delivery');
const miniTotal = document.getElementById('mini-total');

// State
let currentProduct = null;
let selectedAddons = [];
let quantity = 1;
let currentVendor = null;

function resolveVendorInfo() {
    const vendorId = currentVendor?.vendorId
        ?? currentProduct?.vendorId
        ?? currentProduct?.vendor_id
        ?? currentProduct?.merchant_id
        ?? null;

    const vendorType = currentVendor?.vendorType
        ?? currentProduct?.vendorType
        ?? currentProduct?.vendor_type
        ?? (vendorId ? 'restaurant' : 'vendor');

    return {
        vendorId,
        vendorName: currentVendor?.vendorName
            ?? currentProduct?.vendorName
            ?? currentProduct?.merchant_name
            ?? currentProduct?.restaurant_name
            ?? null,
        vendorType,
        vendorLocation: currentVendor?.vendorLocation
            ?? currentProduct?.vendorLocation
            ?? currentProduct?.location
            ?? null
    };
}
// Helpers
function getParams() {
    const params = new URLSearchParams(window.location.search);
    const productId = params.get('id');
    const vendorType = params.get('type') || params.get('vendor') || '';
    const vendorId = params.get('vendorId') || params.get('vendor') || '';
    return { productId, vendorType, vendorId };
}

function escapeHtml(unsafe) {
  return String(unsafe)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// Render product details to DOM
function renderProduct(product) {
    currentProduct = product;

    document.title = `${product.name} | XIApee`;
    productTitle.textContent = product.name || 'Product Details';

    // Image
    const imgUrl = product.image_url || '';
    if (imgUrl) {
        productImage.style.backgroundImage = `url("${imgUrl}")`;
        productImage.style.backgroundSize = 'cover';
        productImage.style.backgroundPosition = 'center';
        productImage.textContent = '';

    } else {
        productImage.style.backgroundImage = '';
        productImage.textContent = product.icon || '🍽️';
    }

    productName.textContent = product.name || '';
    productCategory.textContent = product.category || currentVendor?.cuisine || 'General';
    productPrice.textContent = `RM ${Number(product.price || 0).toFixed(2)}`;
    productDescription.textContent = product.description || '';

    basePriceElement.textContent = `RM ${Number(product.price || 0).toFixed(2)}`;

    const addons = product.addons || [];
    if (addons.length > 0) {
        renderAddons(addons);
        addonsSection.style.display = 'block';
    } else {
        addonsSection.style.display = 'none';
        selectedAddons = [];
        updateTotalPrice();
    }

    updateTotalPrice();
}

// Render addons and attach listeners
function renderAddons(addons) {
    // normalize addon id -> string to safely use in DOM ids
    addonsContainer.innerHTML = addons.map(addon => {
        const aid = addon.id ?? addon.addon_ID ?? addon.addonId ?? addon.addonIdLower ?? '';
        const price = Number(addon.price || 0).toFixed(2);
        return `
        <div class="addon-option" data-addon-id="${aid}">
            <div class="addon-info">
                <input type="checkbox" class="addon-checkbox" id="addon-${aid}" data-addon-id="${aid}">
                <label for="addon-${aid}" class="addon-name">${escapeHtml(addon.name || '')}</label>
            </div>
            <div class="addon-price">${Number(addon.price) > 0 ? `+RM ${price}` : 'Free'}</div>
        </div>`;
    }).join('');

    // reset selectedAddons
    selectedAddons = [];

    // delegate change events (works for dynamically created checkboxes)
    if (!addonsContainer.dataset.bound) {
        addonsContainer.addEventListener('change', onAddonChange);
        addonsContainer.dataset.bound = '1';
    }
}

function onAddonChange(e) {
    if (!e.target.classList.contains('addon-checkbox')) return;

    const rawId = e.target.getAttribute('data-addon-id');
    // find addon in currentProduct.addons by loose equality to tolerate string/number
    const addon = (currentProduct.addons || []).find(a => {
        const aid = a.id ?? a.addon_ID ?? a.addonId ?? a.addonIdLower ?? '';
        return String(aid) === String(rawId);
    });
    if (!addon) return;

    const addonOption = e.target.closest('.addon-option');
    if (e.target.checked) {
        // avoid duplicates
        if (!selectedAddons.find(a => String(a.id ?? a.addon_ID ?? '') === String(rawId))) {
            selectedAddons.push({
                id: addon.id ?? addon.addon_ID ?? addon.addonId ?? rawId,
                name: addon.name || '',
                price: Number(addon.price || 0)
            });
        }
        addonOption.classList.add('selected');
    } else {
        selectedAddons = selectedAddons.filter(a => String(a.id) !== String(rawId));
        addonOption.classList.remove('selected');
    }

    updateTotalPrice();
}

// Price calc & UI update
function updateTotalPrice() {
    if (!currentProduct) return;
    const basePrice = Number(currentProduct.price || 0);
    const addonsPrice = selectedAddons.reduce((s, a) => s + (Number(a.price || 0)), 0);
    const totalPrice = (basePrice + addonsPrice) * quantity;

    if (addonsPrice > 0) {
        addonsPriceLine.style.display = 'flex';
        addonsPriceElement.textContent = `RM ${addonsPrice.toFixed(2)}`;
    } else {
        addonsPriceLine.style.display = 'none';
        addonsPriceElement.textContent = `RM 0.00`;
    }

    totalPriceElement.textContent = `RM ${totalPrice.toFixed(2)}`;
}

// Quantity controls
function handleQuantityControls() {
    const qtyDec = document.getElementById('qty-dec');
    const qtyInc = document.getElementById('qty-inc');

    qtyDec.addEventListener('click', () => {
        if (quantity > 1) {
            quantity--;
            qtyDisplay.textContent = quantity;
            updateTotalPrice();
        }
    });

    qtyInc.addEventListener('click', () => {
        quantity++;
        qtyDisplay.textContent = quantity;
        updateTotalPrice();
    });
}

// Add to cart
// Replace your current handleAddToCart() with this minimal binding version
// Add to cart: 清晰、只绑定一次，并保证传 vendor 信息
function handleAddToCart() {
    const btn = addToCartBtn || document.getElementById('add-to-cart-btn');
    if (!btn) {
      console.error('Add to cart button not found');
      return;
    }
    if (btn.dataset.bound === '1') return;
    btn.dataset.bound = '1';
  
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      if (!currentProduct) {
        alert('Failed to load product');
        return;
      }

      const vendorInfo = resolveVendorInfo();
  
      // 构造传给 cart 的 product 对象（保持和 cart.addItem 期望字段一致）
      const productForCart = {
        id: currentProduct.product_id ?? currentProduct.id ?? currentProduct.productId,
        productId: currentProduct.product_id ?? currentProduct.id ?? currentProduct.productId,
        name: currentProduct.name ?? '',
        price: Number(currentProduct.price || 0),
        icon: currentProduct.icon || currentProduct.image_url || null,
        category: currentProduct.category || '',
        vendorId: vendorInfo.vendorId,
        vendorType: vendorInfo.vendorType,
        vendorName: vendorInfo.vendorName,
        vendorLocation: vendorInfo.vendorLocation};
  
      // add quantity in one call (cart.addItem handles qty argument)
      const qty = Number(quantity || 1);
      globalCart.addItem(productForCart, selectedAddons.slice(), vendorInfo, qty);
  
      // UI feedback
      const originalText = btn.textContent;
      btn.textContent = 'Added!';
      btn.classList.add('just-added');
      setTimeout(() => {
        btn.textContent = originalText;
        btn.classList.remove('just-added');
      }, 1200);
    });
  }
  
  // Buy now: add then go to cart page (pass vendor info the same way)
  function handleBuyNow() {
    const btn = buyNowBtn || document.getElementById('buy-now-btn');
    if (!btn) return;
    if (btn.dataset.bound === '1') return;
    btn.dataset.bound = '1';
  
    btn.addEventListener('click', () => {
      if (!currentProduct) return;
      const vendorInfo = resolveVendorInfo();
  
      const productForCart = {
        id: currentProduct.product_id ?? currentProduct.id ?? currentProduct.productId,
        productId: currentProduct.product_id ?? currentProduct.id ?? currentProduct.productId,
        name: currentProduct.name ?? '',
        price: Number(currentProduct.price || 0),
        icon: currentProduct.icon || currentProduct.image_url || null,
        category: currentProduct.category || '',
        vendorId: vendorInfo.vendorId,
        vendorType: vendorInfo.vendorType,
        vendorName: vendorInfo.vendorName,
        vendorLocation: vendorInfo.vendorLocation
      };
  
      const qty = Number(quantity || 1);
      globalCart.addItem(productForCart, selectedAddons.slice(), vendorInfo, qty);
  
      // navigate to cart page
      window.location.href = '/public/cart.php';
    });
  }
  

// Vendor render
function renderVendorInfo() {
    if (!currentVendor) {
        vendorInfoBox.style.display = 'none';
        return;
    }
    vendorInfoBox.style.display = 'block';
    vendorNameEl.textContent = currentVendor.vendorName || 'Vendor';
    vendorCategoryEl.textContent = currentVendor.cuisine || currentVendor.category || 'General';
    vendorLocationEl.textContent = currentVendor.vendorLocation || '-';
    vendorDescEl.textContent = currentVendor.description || '';
    vendorLinkEl.href = currentVendor.vendorType === 'mart'
        ? `/public/mart.html?id=${currentVendor.vendorId}`
        : `/public/menu.html?id=${currentVendor.vendorId}`;
}

// Mini cart render
function renderMiniCart() {
    const items = globalCart.getItems();

    if (items.length === 0) {
        miniItems.innerHTML = '<div class="mini-item empty">No items yet</div>';
        miniSubtotal.textContent = 'RM 0.00';
        miniDelivery.textContent = 'RM 0.00';
        miniTotal.textContent = 'RM 0.00';
        return;
    }

    miniItems.innerHTML = items.map(it => {
        // 处理 addon 信息
        let addonHtml = '';
        if (it.addons && it.addons.length > 0) {
            addonHtml = `
                <div class="mini-addons">
                    ${it.addons.map(a => `<div class="mini-addon">+ ${a.name} (RM ${a.price.toFixed(2)})</div>`).join('')}
                </div>
            `;
        }
        return `
            <div class="mini-item">
                <div>
                    <span>${it.name} × ${it.qty}</span>
                    ${addonHtml}
                </div>
                <span>RM ${(it.price * it.qty).toFixed(2)}</span>
                <hr>
            </div>
        `;
    }).join('');

    const summary = globalCart.getCartSummary();
    miniSubtotal.textContent = `RM ${summary.subtotal.toFixed(2)}`;
    miniDelivery.textContent = `RM ${summary.deliveryFee.toFixed(2)}`;
    miniTotal.textContent = `RM ${summary.total.toFixed(2)}`;
}

// Init - fetch product from backend
document.addEventListener('DOMContentLoaded', async () => {
    const { productId } = getParams();
    if (!productId) {
        productNotFound.style.display = 'block';
        document.querySelector('.product-content').style.display = 'none';
        return;
    }

    try {
        const res = await fetch(`${API_PATH}?id=${encodeURIComponent(productId)}`);
        if (!res.ok) throw new Error('Network response not ok');
        const data = await res.json();

        if (!data.success) {
            console.error('API error', data);
            productNotFound.style.display = 'block';
            document.querySelector('.product-content').style.display = 'none';
            return;
        }

        // normalize product fields
        const p = data.product || {};
        p.product_id = p.product_id ?? p.id ?? p.productId;
        p.id = p.product_id;
        p.name = p.name ?? p.product_name ?? '';
        p.description = p.description ?? p.desc ?? '';
        p.price = Number(p.price ?? 0);
        p.image_url = p.image_url
            ? `/public/assets/img/food_image/${p.image_url}`
            : '';

        const vendorIdFromProduct = p.merchant_id ?? p.vendor_id ?? p.vendorId ?? null;
        if (vendorIdFromProduct) {
            p.vendorId = vendorIdFromProduct;
            p.vendor_id = vendorIdFromProduct;
        }

        // normalize addons: map id/name/price into {id, name, price}
        const rawAddons = data.addons || [];
        p.addons = rawAddons.map(a => {
            return {
                id: a.addon_ID ?? a.id ?? a.addonId ?? a.addon_id ?? '',
                name: a.name ?? a.addon_name ?? '',
                price: Number(a.price ?? 0)
            };
        });

        // vendor (optional)
        if (data.vendor) {
            currentVendor = {
                vendorType: data.vendor.vendorType ?? data.vendor.type ?? '',
                vendorId: data.vendor.vendorId ?? data.vendor.id ?? '',
                vendorName: data.vendor.name ?? '',
                vendorLocation: data.vendor.location ?? '',
                cuisine: data.vendor.cuisine ?? data.vendor.category ?? '',
                description: data.vendor.description ?? ''
            };
        } else if (vendorIdFromProduct) {
            currentVendor = {
                vendorType: 'restaurant',
                vendorId: vendorIdFromProduct,
                vendorName: p.vendorName ?? p.merchant_name ?? p.restaurant_name ?? '',
                vendorLocation: p.vendorLocation ?? p.location ?? '',
                cuisine: p.category ?? p.cuisine ?? p.tags ?? '',
                description: p.description ?? ''
            };currentVendor = null;
        }

        // render UI
        renderProduct(p);
        renderVendorInfo();
        handleQuantityControls();
        handleAddToCart();
        handleBuyNow();

    } catch (err) {
        console.error('Failed to load product', err);
        productNotFound.style.display = 'block';
        document.querySelector('.product-content').style.display = 'none';
    }

        // 保证在 DOMContentLoaded 内并且 cart.js 已加载后运行
    // 1) 同页监听（直接用 globalCart 的 listener）
    globalCart.addListener(renderMiniCart);

    // 2) 监听自定义事件（当 saveToStorage dispatch xiapee.cart.updated 时也能收到）——双保险
    window.addEventListener('xiapee.cart.updated', (e) => {
    // 如果你想直接用后端传来的内容，可用: const items = e.detail;
    renderMiniCart();
    });

    // 3) 跨 tab/窗口（localStorage 的 storage 事件）在 cart.js 已经处理并更新 Map，因此直接初始渲染就好
    renderMiniCart();

});
