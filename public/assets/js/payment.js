import { globalCart } from '/public/assets/js/cart.js';

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

function loadCartSummary() {
    const items = globalCart.getItems();
    summaryItems.innerHTML = items.map(it => `
        <div class="summary-item">
            <div>
                <div class="name">${it.name} × ${it.qty}</div>
                ${it.addons?.length ? `<div class="addons">${it.addons.map(a=>` + ${a.name}`).join(', ')}</div>` : ''}
                ${it.vendorName ? `<div class="vendor">${it.vendorName}</div>` : ''}
            </div>
            <div class="price">RM ${(it.price * it.qty).toFixed(2)}</div>
        </div>
    `).join('') || '<div class="empty">Cart is empty</div>';
    const s = globalCart.getCartSummary();
    sumSubtotal.textContent = `RM ${s.subtotal.toFixed(2)}`;
    sumDelivery.textContent = `RM ${s.deliveryFee.toFixed(2)}`;
    sumTotal.textContent = `RM ${s.total.toFixed(2)}`;
}

async function loadDB() {
    const res = await fetch('/data/db.json');
    db = await res.json();
    currentUser = db.users?.[0] || null;
}

function autofillUser() {
    if (!currentUser) return;
    nameInput.value = currentUser.name || '';
    numberInput.value = currentUser.customerNumber || '';
    // Address select
    if (currentUser.address) {
        const lower = String(currentUser.address).toLowerCase();
        const opt = Array.from(addressSelect.options).find(o => o.value.toLowerCase() === lower);
        if (opt) addressSelect.value = opt.value; else { addressSelect.value = 'custom'; manualWrap.style.display = 'block'; manualAddress.value = String(currentUser.address); }
    }
    walletBalance.textContent = `RM ${(currentUser.balance ?? 0).toFixed(2)}`;
}

function loadPaymentHistory() {
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
    addressSelect.addEventListener('change', () => {
        if (addressSelect.value === 'custom') { manualWrap.style.display = 'block'; }
        else { manualWrap.style.display = 'none'; }
    });
}

function makeOrderPayload() {
    const addressValue = addressSelect.value === 'custom' ? (manualAddress.value || '') : addressSelect.value;
    const items = globalCart.getItems().map(it => ({
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
    const effDB = getEffectiveDB();
    const order = makeOrderPayload();
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
    walletBalance.textContent = `RM ${updatedUser.balance.toFixed(2)}`;
    // Clear cart
    globalCart.clear();
    // Redirect to tracking
    window.location.href = `/pages/tracking/index.html?id=${order.id}`;
}

function showError(msg) {
    errorMsg.textContent = msg;
    errorMsg.style.display = 'block';
}

document.addEventListener('DOMContentLoaded', async () => {
    await loadDB();
    // Prefer override DB if exists
    const eff = getEffectiveDB();
    if (eff) { db = eff; currentUser = eff.users?.[0] || currentUser; }
    autofillUser();
    loadCartSummary();
    loadPaymentHistory();
    bindControls();
    placeBtn.addEventListener('click', placeOrder);
});
