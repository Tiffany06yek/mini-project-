
import { globalCart } from '/public/assets/js/cart.js';

const DEFAULT_COURIERS = [
    { name: 'Assigned Courier', phone: '', merchantId: null }
];

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
    let payload = null;
    try {
        const res = await fetch('/backend/database.php', { credentials: 'include' });
        payload = await res.json();
        if (!res.ok) {
            throw new Error(payload?.message || `Failed to load user data (HTTP ${res.status})`);
        }
    } catch (err) {
        console.warn('loadDB request failed', err);
        return;
    }

    if (!payload || typeof payload !== 'object') {
        return;
    }

    db = payload;

    const userCandidates = [];
    if (payload.currentUser) userCandidates.push(payload.currentUser);
    if (Array.isArray(payload.users)) {
        userCandidates.push(...payload.users);
    }

    const resolved = userCandidates.find(u => u && (u.id || u.userId));
    if (resolved) {
        const history = Array.isArray(resolved.orderHistory)
            ? resolved.orderHistory
            : Array.isArray(payload.orders)
                ? payload.orders
                : [];
        currentUser = {
            ...resolved,
            id: resolved.id ?? resolved.userId,
            customerNumber: resolved.customerNumber ?? resolved.phone ?? resolved.school_email ?? '',
            address: resolved.address ?? resolved.default_address ?? '',
            balance: Number(resolved.balance ?? resolved.walletBalance ?? 0),
            orderHistory: history
        };
    }
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
    if (walletBalance) walletBalance.textContent = formatCurrency(currentUser.balance);

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
    const merchantIds = new Set();
    items.forEach(item => {
        if (item.vendorId !== undefined && item.vendorId !== null && item.vendorId !== '') {
            merchantIds.add(item.vendorId);
        }
    });

    return {
        id: orderId,
        userId: currentUser?.id || 0,
        customerName: nameInput?.value?.trim() || '',
        customerNumber: numberInput?.value?.trim() || '',
        items,
        subtotal: Number(summary.subtotal || 0),
        deliveryFee: Number(summary.deliveryFee || 0),
        total: Number(summary.total || 0),
        paymentMethod: 'wallet',
        paymentStatus: 'paid',
        orderStatus: 'placed',
        merchantId: merchantIds.size === 1 ? Array.from(merchantIds)[0] : null,
        dropOff: addressValue,
        timestamp: new Date().toISOString()
    };
}

function getEffectiveDB() {
    try {
        const o = localStorage.getItem('xiapee_db_override');
        if (o) return JSON.parse(o);
    } catch {}
    return db;
}

function getCourierList() {
    if (Array.isArray(db?.couriers) && db.couriers.length > 0) {
        return db.couriers;
    }
    const override = getEffectiveDB();
    if (override && Array.isArray(override.couriers) && override.couriers.length > 0) {
        return override.couriers;
    }
    return DEFAULT_COURIERS;
}

function selectCourierForOrder(merchantId) {
    const list = getCourierList();
    if (!Array.isArray(list) || list.length === 0) {
        return { name: 'Assigned Courier', phone: '' };
    }
    if (!merchantId) {
        const first = list[0];
        return { name: first?.name || 'Assigned Courier', phone: first?.phone || '' };
    }
    const needle = merchantId.toString();
    const courier = list.find(item => {
        const id = item?.merchant_id ?? item?.merchantId ?? item?.id;
        return id && id.toString() === needle;
    }) || list[0];
    return { name: courier?.name || 'Assigned Courier', phone: courier?.phone || '' };
}

function normaliseTrackingItems(items) {
    if (!Array.isArray(items)) return [];
    return items.map(item => ({
        id: item.id ?? item.productId ?? item.name,
        name: item.name ?? 'Item',
        qty: Number(item.qty ?? 0),
        price: Number(item.price ?? 0)
    }));
}

function persistOrderForTracking(orderRecord) {
    try {
        const base = getEffectiveDB() || {};
        const orders = Array.isArray(base.orders)
            ? base.orders.filter(o => (o?.id || '').toString() !== (orderRecord.id || '').toString())
            : [];
        orders.push(orderRecord);

        let users = Array.isArray(base.users) ? base.users.map(user => {
            const userId = user?.id ?? user?.userId;
            if (userId !== orderRecord.userId) {
                return user;
            }
            const history = Array.isArray(user.orderHistory)
                ? user.orderHistory.filter(o => (o?.id || '').toString() !== (orderRecord.id || '').toString())
                : [];
            history.push(orderRecord);
            return { ...user, orderHistory: history };
        }) : [];

        if (!users.length && orderRecord.userId) {
            users = [{
                id: orderRecord.userId,
                name: currentUser?.name || '',
                phone: currentUser?.customerNumber || currentUser?.phone || '',
                orderHistory: [orderRecord]
            }];
        }

        const couriers = getCourierList();
        const snapshot = { ...base, orders, users, couriers };
        localStorage.setItem('xiapee_db_override', JSON.stringify(snapshot));
        db = snapshot;
    } catch (err) {
        console.warn('persistOrderForTracking failed', err);
    }
}

async function placeOrder() {
    if (errorMsg) errorMsg.style.display = 'none';
    const order = makeOrderPayload();
    if (!order.items || order.items.length === 0) {
        showError('Your cart is empty.');
        return;
    }
    if (!currentUser || !currentUser.id) {
        showError('No user found.');
        return;
    }
    if (!order.dropOff) {
        showError('Please provide a drop-off address.');
        return;
    }
    // Balance check
    const balance = Number(currentUser.balance ?? 0);
    if (!Number.isNaN(balance) && balance < Number(order.total || 0)) {
        showError('Insufficient balance. Please remove items or top up.');
        return;
    }
    // Deduct and update
    try {
        const response = await fetch('/public/place_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(order)
        });
        const responseText = await response.text();
        let result = {};
        try {
            result = responseText ? JSON.parse(responseText) : {};
        } catch (err) {
            console.warn('Failed to parse place_order response', err, responseText);
        }

        if (!response.ok || result.success === false) {
            const message = result.message || `Failed to place order (HTTP ${response.status})`;
            throw new Error(message);
        }

        const resolvedOrderId = result.orderId ?? order.id;
        const resolvedOrderNumber = result.orderNumber ?? (typeof result.orderId === 'number' ? result.orderId : null);
        const resolvedExternalId = result.externalId ?? (typeof resolvedOrderId === 'string' ? resolvedOrderId : null);

        const newBalance = balance - Number(order.total || 0);
        if (!Number.isNaN(newBalance) && walletBalance) {
            walletBalance.textContent = formatCurrency(Math.max(newBalance, 0));
        }
        const orderHistory = Array.isArray(currentUser.orderHistory)
            ? [...currentUser.orderHistory]
            : [];
            const courierFromServer = result.courier || null;
            const fallbackCourier = selectCourierForOrder(order.merchantId);
            const resolvedCourier = courierFromServer || fallbackCourier || { name: 'Assigned Courier', phone: '' };
            const staffInfo = {
                name: resolvedCourier.name || 'Assigned Courier',
                phone: resolvedCourier.phone || ''
            };
            const historyRecord = {
                ...order,
                id: resolvedOrderId,
                orderNumber: resolvedOrderNumber,
                externalId: resolvedExternalId,
                courier: resolvedCourier,
                staff: staffInfo
            };
            orderHistory.push(historyRecord);

        currentUser = {
            ...currentUser,
            name: nameInput.value || currentUser.name,
            customerNumber: numberInput.value || currentUser.customerNumber,
            address: order.dropOff,
            balance: newBalance,
            orderHistory
        };

        const trackingOrder = {
            id: resolvedOrderId,
            orderNumber: resolvedOrderNumber ?? historyRecord.orderNumber ?? null,
            externalId: resolvedExternalId ?? historyRecord.externalId ?? null,
            userId: currentUser.id || order.userId || 0,
            status: 'Order Confirmed',
            dropOff: order.dropOff,
            staff: staffInfo,
            courier: resolvedCourier,
            items: normaliseTrackingItems(order.items),
            subtotal: Number(order.subtotal || 0),
            deliveryFee: Number(order.deliveryFee || 0),
            total: Number(order.total || 0),
            timestamp: new Date().toISOString()
        };
        persistOrderForTracking(trackingOrder);


        globalCart.clear();
        const redirectId = result.orderId ?? order.id;
        window.location.href = `/public/tracking.html?id=${encodeURIComponent(resolvedOrderId)}`;
    } catch (err) {
        console.error('placeOrder failed', err);
        showError(err.message || 'Failed to place order. Please try again.');
    }
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