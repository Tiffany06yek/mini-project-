let db = null;
let currentUser = null;
let order = null;
let progressTimer = null;

const orderIdEl = document.getElementById('order-id');
const estimatedTimeEl = document.getElementById('estimated-time');
const statusMessageEl = document.getElementById('status-message');
const dropOffEl = document.getElementById('drop-off');
const staffNameEl = document.getElementById('staff-name');
const staffPhoneEl = document.getElementById('staff-phone');
const callBtn = document.getElementById('call-btn');
const msgBtn = document.getElementById('msg-btn');
const itemsEl = document.getElementById('items');
const billSub = document.getElementById('bill-subtotal');
const billDel = document.getElementById('bill-delivery');
const billTot = document.getElementById('bill-total');
const successEl = document.getElementById('success');

const stepConfirmed = document.getElementById('step-confirmed');
const stepPrepared = document.getElementById('step-prepared');
const stepDelivery = document.getElementById('step-delivery');
const stepDelivered = document.getElementById('step-delivered');

const STATUS_FLOW = [
    'Order Confirmed',
    'Being Prepared',
    'Out for Delivery',
    'Delivered'
];

const ESTIMATE_BY_STATUS = {
    'Order Confirmed': 30,
    'Being Prepared': 20,
    'Out for Delivery': 10,
    'Delivered': 0
};

function getParamId() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

function getEffectiveDB() {
    try {
        const override = localStorage.getItem('xiapee_db_override');
        if (override) {
            return JSON.parse(override);
        }
    } catch (err) {
        console.warn('Failed to read xiapee_db_override', err);
    }
    return null;
}

async function loadDB() {
    if (db) return;
    let base = null;
    try {
        const res = await fetch('/data/db.json', { cache: 'no-store' });
        if (res.ok) {
            base = await res.json();
        }
    } catch (err) {
        console.warn('loadDB fallback to override only', err);
    }
    const override = getEffectiveDB();
    db = override || base || {};
    currentUser = Array.isArray(db.users) && db.users.length > 0 ? db.users[0] : null;
}

function findOrder(id) {
    if (!id) return null;
    const { orders = [] } = db || {};
    const fromOrders = orders.find(o => (o?.id || '').toString() === id);
    if (fromOrders) return { ...fromOrders };
    const histories = Array.isArray(db?.users)
        ? db.users.flatMap(user => Array.isArray(user.orderHistory) ? user.orderHistory : [])
        : [];
    const fromHistory = histories.find(o => (o?.id || '').toString() === id);
    return fromHistory ? { ...fromHistory } : null;
}

function normaliseStatus(status) {
    const raw = (status || '').toString().toLowerCase();
    if (!raw) return STATUS_FLOW[0];
    if (raw.includes('deliver') || raw.includes('arriv') || raw.includes('complete')) {
        return 'Delivered';
    }
    if (raw.includes('way') || raw.includes('picked') || raw.includes('out')) {
        return 'Out for Delivery';
    }
    if (raw.includes('prep') || raw.includes('cook')) {
        return 'Being Prepared';
    }
    return 'Order Confirmed';
}

function setStatusMessage(message, tone = 'info') {
    if (!statusMessageEl) return;
    if (!message) {
        statusMessageEl.textContent = '';
        statusMessageEl.className = 'status-message';
        return;
    }
    statusMessageEl.textContent = message;
    statusMessageEl.className = `status-message status-${tone}`;
}

function renderItems(items) {
    if (!itemsEl) return;
    if (!Array.isArray(items) || items.length === 0) {
        itemsEl.innerHTML = '<p class="empty-items">No items found for this order.</p>';
        return;
    }
    itemsEl.innerHTML = items.map(item => {
        const qty = Number(item.qty || 0);
        const unitPrice = Number(item.price || item.unitPrice || 0);
        const lineTotal = Number(item.total || unitPrice * qty || 0);
        return `
            <div class="item-row">
                <span>${item.name || 'Item'} × ${qty}</span>
                <span>RM ${lineTotal.toFixed(2)}</span>
            </div>
        `;
    }).join('');
}

function updateProgressSteps(status) {
    const steps = [stepConfirmed, stepPrepared, stepDelivery, stepDelivered];
    const activeIndex = Math.max(0, STATUS_FLOW.indexOf(status));
    steps.forEach((step, index) => {
        if (!step) return;
        step.classList.remove('active', 'completed');
        if (index < activeIndex) {
            step.classList.add('completed');
        } else if (index === activeIndex) {
            step.classList.add('active');
        }
    });
    if (successEl) {
        successEl.hidden = status !== 'Delivered';
    }
}

function render() {
    if (!order) return;
    const status = normaliseStatus(order.status);
    order.status = status;
    if (orderIdEl) {
        orderIdEl.textContent = order.id ? `Order #${order.id}` : 'Order #';
    }
    if (estimatedTimeEl) {
        const remaining = ESTIMATE_BY_STATUS[status] ?? ESTIMATE_BY_STATUS['Order Confirmed'];
        estimatedTimeEl.textContent = `Estimated: ${remaining} min`;
    }
    setStatusMessage(`Status: ${status}`);
    if (dropOffEl) {
        dropOffEl.textContent = order.dropOff || '-';
    }
    if (staffNameEl) {
        staffNameEl.textContent = order.staff?.name || 'Assigned Courier';
    }
    const phone = order.staff?.phone || '';
    if (staffPhoneEl) {
        staffPhoneEl.textContent = phone || '-';
    }
    if (callBtn) {
        if (phone) {
            callBtn.disabled = false;
            callBtn.onclick = () => window.open(`tel:${phone}`);
        } else {
            callBtn.disabled = true;
            callBtn.onclick = null;
        }
    }
    if (msgBtn) {
        if (phone) {
            msgBtn.disabled = false;
            msgBtn.onclick = () => window.open(`sms:${phone}`);
        } else {
            msgBtn.disabled = true;
            msgBtn.onclick = null;
        }
    }
    renderItems(order.items);
    if (billSub) billSub.textContent = `RM ${Number(order.subtotal || 0).toFixed(2)}`;
    if (billDel) billDel.textContent = `RM ${Number(order.deliveryFee || 0).toFixed(2)}`;
    if (billTot) billTot.textContent = `RM ${Number(order.total || 0).toFixed(2)}`;
    updateProgressSteps(status);
}

function persistOrderOverride(updated) {
    try {
        const base = getEffectiveDB() || db || {};
        const orders = Array.isArray(base.orders) ? base.orders.filter(o => (o?.id || '') !== updated.id) : [];
        orders.push(updated);
        let users = Array.isArray(base.users) ? base.users.map(user => {
            if ((user?.id || user?.userId) !== (updated.userId ?? user?.id)) {
                return user;
            }
            const history = Array.isArray(user.orderHistory) ? user.orderHistory.filter(o => (o?.id || '') !== updated.id) : [];
            history.push(updated);
            return { ...user, orderHistory: history };
        }) : [];
        if (users.length === 0 && currentUser) {
            users = [{ ...currentUser, orderHistory: [updated] }];
        }
        const snapshot = { ...base, orders, users };
        localStorage.setItem('xiapee_db_override', JSON.stringify(snapshot));
        db = snapshot;
    } catch (err) {
        console.warn('persistOrderOverride failed', err);
    }
}

function progressStatus() {
    if (!order) return false;
    const idx = STATUS_FLOW.indexOf(order.status);
    if (idx === -1 || idx >= STATUS_FLOW.length - 1) {
        if (order.status === 'Delivered') {
            setStatusMessage('Status: Delivered 🎉', 'success');
        }
        return false;
    }
    const nextStatus = STATUS_FLOW[idx + 1];
    order = { ...order, status: nextStatus, timestamp: new Date().toISOString() };
    persistOrderOverride(order);
    render();
    if (nextStatus === 'Delivered') {
        setStatusMessage('Status: Delivered 🎉', 'success');
        return false;
    }
    return true;
}

function scheduleProgress() {
    if (progressTimer) {
        clearTimeout(progressTimer);
        progressTimer = null;
    }
    if (!order || order.status === 'Delivered') return;
    progressTimer = setTimeout(() => {
        const keepGoing = progressStatus();
        if (keepGoing) {
            scheduleProgress();
        }
    }, 10000);
}

async function loadOrderFromServer(id) {
    try {
        const response = await fetch(`/backend/get_order.php?id=${encodeURIComponent(id)}`, { credentials: 'include' });
        const payload = await response.json();
        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to fetch order from server.');
        }
        const srv = payload.order || {};
        const mapped = {
            id: srv.id || id,
            status: normaliseStatus(srv.status || srv.orderStatus),
            dropOff: srv.dropOff || '',
            subtotal: Number(srv.subtotal || 0),
            deliveryFee: Number(srv.deliveryFee || 0),
            total: Number(srv.total || 0),
            items: Array.isArray(srv.items) ? srv.items.map(item => ({
                id: item.id || item.productId || item.name,
                name: item.name || 'Item',
                qty: Number(item.qty || item.quantity || 0),
                price: Number(item.price || item.unitPrice || 0),
                total: Number(item.total || 0)
            })) : [],
            staff: {
                name: srv.courier?.name || 'Assigned Courier',
                phone: srv.courier?.phone || ''
            },
            userId: srv.userId || currentUser?.id || 0,
            timestamp: srv.timestamp || new Date().toISOString()
        };
        persistOrderOverride(mapped);
        return mapped;
    } catch (err) {
        console.warn('loadOrderFromServer failed', err);
        setStatusMessage(err.message || 'Unable to load order.', 'error');
        return null;
    }
}

async function init() {
    await loadDB();
    const id = getParamId();
    if (!id) {
        setStatusMessage('Order id is missing from the link.', 'error');
        return;
    }
    order = findOrder(id);
    if (!order) {
        order = await loadOrderFromServer(id);
    }
    if (!order) {
        setStatusMessage('Order not found.', 'error');
        return;
    }
    setStatusMessage(`Status: ${normaliseStatus(order.status)}`);
    render();
    scheduleProgress();
}

document.addEventListener('DOMContentLoaded', () => {
    init();
});