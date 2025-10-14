import { Header } from '/public/assets/js/header.js';
import { globalCart } from '/public/assets/js/cart.js';

Header();

let db = {};
let currentUser = null;

const nameInput = document.getElementById('cust-name');
const numberInput = document.getElementById('cust-number');
const addressSelect = document.getElementById('cust-address');
const manualWrap = document.getElementById('manual-address-wrap');
const manualAddress = document.getElementById('manual-address');
const walletBalance = document.getElementById('wallet-balance');
const placeBtn = document.getElementById('place-order-btn');
const errorMsg = document.getElementById('error-msg');
const summaryItems = document.getElementById('summary-items');
const sumSubtotal = document.getElementById('sum-subtotal');
const sumDelivery = document.getElementById('sum-delivery');
const sumTotal = document.getElementById('sum-total');
const paymentHistory = document.getElementById('payment-history');

function formatCurrency(value) {
    return `RM ${Number(value || 0).toFixed(2)}`;
}

function escapeHtml(str) {
    if (str === undefined || str === null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderSummaryItem(item) {
    const qty = Number(item.qty || 0);
    const price = Number(item.price || 0) * qty;
    const vendorInfo = item.vendorName
        ? `<div class="ci-vendor">${escapeHtml(item.vendorName)}${item.vendorLocation ? ` • ${escapeHtml(item.vendorLocation)}` : ''}</div>`
        : '';
    const addons = Array.isArray(item.addons) && item.addons.length > 0
        ? `<div class="ci-addons">${item.addons.map(addon => `<span class="addon">+ ${escapeHtml(addon.name || '')}</span>`).join('')}</div>`
        : '';

    return `
        <div class="order-item">
            <div class="ci-details">
                <div class="ci-title">${vendorInfo}${escapeHtml(item.name)} × ${qty}</div>
                ${addons}
            </div>
            <div class="ci-price">${formatCurrency(price)}</div>
        </div>
    `;
}

function loadCartSummary() {
    if (!summaryItems || !sumSubtotal || !sumDelivery || !sumTotal) {
        return;
    }
    const items = globalCart.getItems();
    if (!Array.isArray(items) || items.length === 0) {
        summaryItems.innerHTML = '<div class="empty">Cart is empty</div>';
        sumSubtotal.textContent = formatCurrency(0);
        sumDelivery.textContent = formatCurrency(0);
        sumTotal.textContent = formatCurrency(0);
        if (placeBtn) placeBtn.disabled = true;
        return;
    }

    summaryItems.innerHTML = items.map(renderSummaryItem).join('');
    const s = globalCart.getCartSummary();
    sumSubtotal.textContent = formatCurrency(s.subtotal);
    sumDelivery.textContent = formatCurrency(s.deliveryFee);
    sumTotal.textContent = formatCurrency(s.total);
    if (placeBtn) placeBtn.disabled = false;
}

function handleCartUpdate() {
    loadCartSummary();
}

async function loadDB() {
    const res = await fetch('/backend/database.php');
    db = await res.json();
    currentUser = db.users?.[0] || null;
}


function autofillUser() {
    if (!currentUser) return;
    if (nameInput) nameInput.value = currentUser.name || '';
    if (numberInput) numberInput.value = currentUser.customerNumber || '';

    // Address select
    if (currentUser.address && addressSelect) {
        const lower = String(currentUser.address).toLowerCase();
        const opt = Array.from(addressSelect.options).find(o => o.value.toLowerCase() === lower);
        if (opt) {
            addressSelect.value = opt.value;
        } else if (manualWrap && manualAddress) {
            addressSelect.value = 'custom';
            manualWrap.style.display = 'block';
            manualAddress.value = String(currentUser.address);
        }
    }
    if (walletBalance) walletBalance.textContent = `RM ${(currentUser.balance ?? 0).toFixed(2)}`;

}

function loadPaymentHistory() {
    if (!paymentHistory) return;
    const orders = currentUser?.orderHistory || [];
    const recentOrders = orders.slice(-5).reverse(); // Last 5 orders
    
    if (recentOrders.length === 0) {
        paymentHistory.innerHTML = '<div class="no-payments">No recent payments</div>';
        return;
    }
    
    paymentHistory.innerHTML = recentOrders.map(order => `
        <div class="payment-item">
            <div>
                <div>${order.id}</div>
                <div class="payment-date">${new Date(order.timestamp).toLocaleDateString()}</div>
            </div>
            <div class="payment-amount">RM ${Number(order.total).toFixed(2)}</div>
        </div>
    `).join('');
}

function bindControls() {
    if (!addressSelect) return;
    addressSelect.addEventListener('change', () => {
        if (!manualWrap) return;
        if (addressSelect.value === 'custom') { manualWrap.style.display = 'block'; }
        else { manualWrap.style.display = 'none'; }
    });
}

function makeOrderPayload() {
    const addressValue = addressSelect
        ? (addressSelect.value === 'custom'
            ? ((manualAddress && manualAddress.value) || '')
            : addressSelect.value)
        : ((manualAddress && manualAddress.value) || '');
    const rawItems = globalCart.getItems();
    const items = rawItems.map(it => ({
        id: it.id,
        productId: it.productId,
        name: it.name,
        qty: it.qty,
        price: it.price,
        addons: it.addons || [],
        vendorId: it.vendorId,
        vendorName: it.vendorName,
        vendorType: it.vendorType,
        vendorLocation: it.vendorLocation
    }));
    const summary = globalCart.getCartSummary();
    const orderId = `ORD-${Date.now()}-${Math.random().toString(36).substring(2,7).toUpperCase()}`;
    const staff = db.staff?.[0] || null;
    return {
        id: orderId,
        userId: currentUser?.id || 'u1',
        items,
        subtotal: summary.subtotal,
        deliveryFee: summary.deliveryFee,
        total: summary.total,
        status: 'Order Confirmed',
        staffId: staff?.id,
        staff,
        dropOff: addressValue,
        timestamp: new Date().toISOString()
    };
}

function simulateWriteBack(newDB) {
    // Persist simulated DB to localStorage for prototype
    localStorage.setItem('xiapee_db_override', JSON.stringify(newDB));
}

function getEffectiveDB() {
    try {
        const o = localStorage.getItem('xiapee_db_override');
        if (o) return JSON.parse(o);
    } catch {}
    return db;
}

async function placeOrder() {
    if (errorMsg) errorMsg.style.display = 'none';
    const effDB = getEffectiveDB();
    const order = makeOrderPayload();
    if (!order.items || order.items.length === 0) {
        showError('Your cart is empty.');
        return;
    }
    if (!currentUser) { showError('No user found.'); return; }
    if (!order.dropOff) { showError('Please provide a drop-off address.'); return; }
    // Balance check
    const balance = Number(currentUser.balance ?? 0);
    if (balance < order.total) {
        showError('Insufficient balance. Please remove items or top up.');
        return;
    }
    // Deduct and update
    const updatedUser = { ...currentUser, name: nameInput.value || currentUser.name, customerNumber: numberInput.value || currentUser.customerNumber, address: order.dropOff, balance: Number((balance - order.total).toFixed(2)), orderHistory: [...(currentUser.orderHistory||[]), order] };
    const users = (effDB.users||[]).map(u => u.id === updatedUser.id ? updatedUser : u);
    const orders = [...(effDB.orders||[]), order];
    const newDB = { ...effDB, users, orders };
    simulateWriteBack(newDB);
    // Update UI
    if (walletBalance) {
        walletBalance.textContent = `RM ${updatedUser.balance.toFixed(2)}`;
    }
    // Clear cart
    globalCart.clear();
    // Redirect to tracking
    window.location.href = `/pages/tracking/index.html?id=${order.id}`;
}

function showError(msg) {
    if (!errorMsg) return;
    errorMsg.textContent = msg;
    errorMsg.style.display = 'block';
}


document.addEventListener('DOMContentLoaded', async () => {
    try {
        await loadDB();
    } catch (err) {
        console.warn('loadDB failed', err);
    }
    // Prefer override DB if exists
    const eff = getEffectiveDB();
    if (eff) { db = eff; currentUser = eff.users?.[0] || currentUser; }
    autofillUser();
    handleCartUpdate();
    loadPaymentHistory();
    bindControls();
    if (placeBtn) {
        placeBtn.addEventListener('click', placeOrder);
    }
    globalCart.addListener(handleCartUpdate);
    window.addEventListener('xiapee.cart.updated', handleCartUpdate);
});