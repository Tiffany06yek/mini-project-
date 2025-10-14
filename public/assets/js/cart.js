import { Header } from "/public/assets/js/header.js";
Header()

const STORAGE_KEY = 'xiapee_cart';
const API_PATH = '/public/cart_api.php'; // optional backend endpoint

// Helpers
function debounce(fn, wait = 300) {
  let t = null;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn.apply(null, args), wait);
  };
}

class CartManager {
  constructor() {
    this.items = new Map();
    this.listeners = [];
    this.loadFromStorage();

    this.syncToServerDebounced = debounce((itemsArray) => {
      fetch(`${API_PATH}?action=sync`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: itemsArray })
      }).then(r => r.json()).catch(err => console.warn('cart sync failed', err));
    }, 500);

    window.addEventListener('storage', (ev) => {
      if (ev.key === STORAGE_KEY) {
        try {
          const arr = ev.newValue ? JSON.parse(ev.newValue) : [];
          this.items = new Map(arr);
          this.notifyListeners();
        } catch (e) {
          console.warn('Failed to parse storage event', e);
        }
      }
    });
  }

  loadFromStorage() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        const arr = JSON.parse(raw);

        // 支持两种格式：
        // 1) 新格式：arr 是 [{...},{...}] （values）
        // 2) 旧格式：arr 是 [[key,value],[key,value]] （entries）
        if (Array.isArray(arr) && arr.length > 0 && Array.isArray(arr[0]) && arr[0].length === 2) {
          // entries format -> convert to Map directly
          this.items = new Map(arr);
        } else if (Array.isArray(arr)) {
          // values format -> recreate a Map using item.id as key (fallback 随机生成 id)
          this.items = new Map(arr.map(it => {
            const key = it && it.id ? it.id : (it && it.productId ? `${it.productId}@${it.vendorId||''}` : Math.random().toString(36).slice(2));
            return [key, it];
          }));
        } else {
          this.items = new Map();
        }
      } else {
        this.items = new Map();
      }
    } catch (e) {
      console.warn('Failed to load cart from storage:', e);
      this.items = new Map();
    }
  }

  saveToStorage() {
    try {
      // 存为 values（array of item objects），便于其它代码直接读取 value
      const arr = Array.from(this.items.values());
      localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
      this.notifyListeners();
      const payload = this.getItems();
      window.dispatchEvent(new CustomEvent('xiapee.cart.updated', { detail: payload }));
      this.syncToServerDebounced(payload);
    } catch (e) {
      console.warn('Failed to save cart to storage:', e);
    }
  }

  notifyListeners() {
    const items = this.getItems();
    const total = this.getTotal(); // subtotal
    this.listeners.forEach(cb => {
      try { cb(items, total); } catch (e) { console.error('cart listener error', e); }
    });
  }

  addListener(cb) {
    if (typeof cb === 'function') this.listeners.push(cb);
  }

  removeListener(cb) {
    const idx = this.listeners.indexOf(cb);
    if (idx >= 0) this.listeners.splice(idx, 1);
  }

  getItems() {
    return Array.from(this.items.values());
  }

  // 商品小计（不含运费）
  getTotal() {
    let total = 0;
    this.items.forEach(it => {
      total += Number(it.price || 0) * Number(it.qty || 0);
    });
    return total;
  }

  getItemCount() {
    let count = 0;
    this.items.forEach(it => { count += Number(it.qty || 0); });
    return count;
  }


  generateItemId(product, addons = [], vendorInfo = {}) {
    const addonIds = (addons || []).map(a => a.id ?? a.addon_ID ?? '').sort().join(',');
    const vendorKey = vendorInfo.vendorId || product.vendorId || product.restaurant_id || '';
    return `${product.id}${vendorKey ? `@${vendorKey}` : ''}${addonIds ? `_${addonIds}` : ''}`;
  }

  addItem(product, addons = [], vendorInfo = {}, qty = 1) {
    if (!product || !product.id) {
      console.warn('addItem: invalid product', product);
      return;
    }
    const id = this.generateItemId(product, addons, vendorInfo);
    const existing = this.items.get(id);
    const addQty = Math.max(1, Number(qty) || 1);

    const resolvedVendorId = vendorInfo?.vendorId
      ?? product.vendorId
      ?? product.vendor_id
      ?? product.restaurant_id
      ?? null;

    const resolvedVendorType = vendorInfo?.vendorType
      ?? product.vendorType
      ?? product.vendor_type
      ?? (resolvedVendorId ? 'restaurant' : 'vendor');

    const resolvedVendorName = vendorInfo?.vendorName
      ?? product.vendorName
      ?? product.merchant_name
      ?? product.restaurant_name
      ?? null;

    const resolvedVendorLocation = vendorInfo?.vendorLocation
      ?? product.vendorLocation
      ?? product.location
      ?? null;
  
    if (existing) {
      existing.qty = (existing.qty || 0) + addQty;
    } else {
      const addonPrice = (addons || []).reduce((s, a) => s + (Number(a.price || 0)), 0);
      const basePrice = Number(product.price || 0);
      this.items.set(id, {
        id,
        productId: product.id,
        name: product.name || product.product_name || '',
        price: Number(basePrice + addonPrice),
        qty: Math.floor(addQty),
        addons: (addons || []).map(a => ({ id: a.id ?? a.addon_ID ?? '', name: a.name || '', price: Number(a.price || 0) })),
        icon: product.icon || product.image_url || null,
        category: product.category || '',
        // 兼容多种来源字段（优先 vendorInfo，再看 product 的多种命名）
        vendorId: resolvedVendorId,
        vendorName: resolvedVendorName,
        vendorType: resolvedVendorType,
        vendorLocation: resolvedVendorLocation
      });
    }
  
    console.log('adding item vendorId:', vendorInfo.vendorId ?? product.vendorId ?? product.restaurant_id);

    this.saveToStorage();
  }
  
  getCartSummary() {
    const items = this.getItems() || [];
    // 确保 items 是数组（防止 null/undefined）
    if (!Array.isArray(items)) {
      console.warn('getCartSummary: items is not an array', items);
      return {
        subtotal: 0,
        vendorCount: 0,
        deliveryFee: 0,
        total: 0
      };
    }
  
    const subtotal = Number(this.getTotal() || 0);
  
    // 统计不同餐厅（支持多种字段名）
    const uniqueVendors = new Set();
    items.forEach(it => {
      const vendor_id = it.vendorId ?? it.vendor_id ?? it.restaurant_id ?? it.restaurantId ?? null;
      const type = it.vendorType ?? it.vendor_type ?? 'vendor';
      if (vendor_id !== undefined && vendor_id !== null && vendor_id !== '') {
        uniqueVendors.add(`${String(type)}:${String(vendor_id)}`); // 修正：用 vendor_id，不是 id
      }
    });
  
    const vendorCount = uniqueVendors.size;
    const deliveryFee = vendorCount * 2.0; // RM2 per restaurant
    const total = subtotal + deliveryFee;
  
    return {
      subtotal: Number(subtotal),
      vendorCount,
      deliveryFee: Number(deliveryFee),
      total: Number(total)
    };
  }
  

  removeItem(id) {
    if (this.items.delete(id)) this.saveToStorage();
  }

  changeQty(id, delta) {
    const it = this.items.get(id);
    if (!it) return;
    it.qty = (it.qty || 0) + Number(delta || 0);
    if (it.qty <= 0) this.items.delete(id);
    this.saveToStorage();
  }

  updateQty(id, qty) {
    const it = this.items.get(id);
    if (!it) return;
    const newQty = Math.floor(Number(qty) || 0);
    if (newQty <= 0) this.items.delete(id);
    else it.qty = newQty;
    this.saveToStorage();
  }

  clear() {
    if (this.items.size === 0) return;
    this.items.clear();
    this.saveToStorage();
  }
}

// singleton
export const globalCart = new CartManager();

export function createCart(containerSelector, options = {}) {
  const container = document.querySelector(containerSelector);
  if (!container) {
    console.warn('createCart: container not found', containerSelector);
    return null;
  }

  const checkoutUrl = options.checkoutUrl || '/public/payment.html';

  function escapeHtml(s = '') {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // 更新页面上独立 summary 元素（如果存在）
  function updateExternalSummary(summary) {
    try {
      const subtotalEl = document.getElementById('subtotal');
      const deliveryEl = document.getElementById('delivery-fee');
      const totalEl = document.getElementById('total');
      const checkoutBtn = document.getElementById('checkout-btn');
      const emptyBox = document.getElementById('empty-cart');

      if (subtotalEl) subtotalEl.textContent = `RM ${Number(summary.subtotal || 0).toFixed(2)}`;
      if (deliveryEl) deliveryEl.textContent = `RM ${Number(summary.deliveryFee || 0).toFixed(2)}`;
      if (totalEl) totalEl.textContent = `RM ${Number(summary.total || 0).toFixed(2)}`;

      if (checkoutBtn) checkoutBtn.disabled = (Number(summary.subtotal || 0) <= 0);

      if (emptyBox) {
        if (Number(summary.subtotal || 0) <= 0) emptyBox.style.display = 'block';
        else emptyBox.style.display = 'none';
      }
    } catch (e) {
      console.warn('updateExternalSummary error', e);
    }
  }

  function render(items) {
    const summary = globalCart.getCartSummary();
    const count = globalCart.getItemCount();

    container.innerHTML = `
      <div class="cart-widget">
        <div class="cart-header">
          <h3>Shopping Cart</h3>
          <div class="cart-count">${count} item${count !== 1 ? 's' : ''}</div>
        </div>
        <div class="cart-items">
          ${items.length === 0 ? `<div class="cart-empty">Your cart is empty</div>` :
            items.map(it => `
              <div class="cart-item" data-id="${escapeHtml(it.id)}">
                <div class="ci-left">
                  <div class="ci-title">${escapeHtml(it.name)}</div>
                  ${it.addons && it.addons.length ? `<div class="ci-addons">${it.addons.map(a => `<span class="addon">+ ${escapeHtml(a.name)}</span>`).join(' ')}</div>` : ''}
                </div>
                <div class="ci-right">
                  <div class="ci-controls">
                    <button class="qty-btn dec">-</button>
                    <span class="qty">${Number(it.qty || 0)}</span>
                    <button class="qty-btn inc">+</button>
                  </div>
                  <div class="ci-price">RM ${(Number(it.price || 0) * Number(it.qty || 0)).toFixed(2)}</div>
                  <button class="remove-btn">Remove</button>
                </div>
              </div>
            `).join('')
          }
        </div>
        <div class="cart-summary">
          <div>Subtotal: <strong>RM ${Number(summary.subtotal || 0).toFixed(2)}</strong></div>
          <div>Delivery: <strong>RM ${Number(summary.deliveryFee || 0).toFixed(2)}</strong></div>
          <div>Total: <strong>RM ${Number(summary.total || 0).toFixed(2)}</strong></div>
        </div>
        <div class="cart-actions">
          <button class="clear-cart">Clear Cart</button>
          <button class="checkout-btn">Proceed to Checkout</button>
        </div>
      </div>
    `;

    // 同步更新外部 summary（如果页面有这些元素）
    updateExternalSummary(summary);
  }

  // initial render
  render(globalCart.getItems());

  // register listener
  const listener = () => render(globalCart.getItems());
  globalCart.addListener(listener);

  // also listen to custom event
  const evtHandler = (e) => render(e.detail || globalCart.getItems());
  window.addEventListener('xiapee.cart.updated', evtHandler);

  // event delegation for controls inside the cart container
  container.addEventListener('click', (ev) => {
    const btn = ev.target;
    const itemEl = btn.closest('.cart-item');
    if (btn.classList.contains('inc') && itemEl) {
      globalCart.changeQty(itemEl.dataset.id, 1);
    } else if (btn.classList.contains('dec') && itemEl) {
      globalCart.changeQty(itemEl.dataset.id, -1);
    } else if (btn.classList.contains('remove-btn') && itemEl) {
      globalCart.removeItem(itemEl.dataset.id);
    } else if (btn.classList.contains('clear-cart')) {
      if (confirm('Clear entire cart?')) globalCart.clear();
    } else if (btn.classList.contains('checkout-btn')) {
      if (globalCart.getItemCount() === 0) {
        alert('Your cart is empty!');
        return;
      }
      window.location.href = checkoutUrl;
    }
  });

  // === 外部（页面其他位置）Order Summary 按钮绑定（#clear-cart-btn, #checkout-btn）===
  // 例如你的模板可能有：
  // <button id="clear-cart-btn">Clear Cart</button>
  // <button id="checkout-btn">Checkout</button>
  function bindExternalButtons() {
    const extClear = document.getElementById('clear-cart-btn');
    if (extClear) {
      extClear.addEventListener('click', () => {
        if (confirm('Are you sure you want to clear your cart?')) {
          globalCart.clear();
        }
      });
    }

    const extCheckout = document.getElementById('checkout-btn');
    if (extCheckout) {
      extCheckout.addEventListener('click', () => {
        if (globalCart.getItemCount() === 0) {
          alert('Your cart is empty!');
          return;
        }
        window.location.href = checkoutUrl;
      });
    }
  }

  // immediately bind external buttons (safe if elements exist; if not, no-op)
  bindExternalButtons();

  return {
    render: () => render(globalCart.getItems()),
    destroy: () => {
      globalCart.removeListener(listener);
      window.removeEventListener('xiapee.cart.updated', evtHandler);
      container.innerHTML = '';
    }
  };
}