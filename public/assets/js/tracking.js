
let db = null;
let currentUser = null;
let order = null;
let progressTimer = null;
let remoteRefreshTimer = null;

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

const STATUS_STAGES = [
    { label: 'Order Confirmed', keywords: ['confirm', 'placed', 'accept'] },
    { label: 'Being Prepared', keywords: ['prepar', 'cook', 'kitchen'] },
    { label: 'Out for Delivery', keywords: ['delivery', 'dispatch', 'way', 'pickup', 'picked'] },
    { label: 'Delivered', keywords: ['delivered', 'complete', 'arriv', 'done'] },
];

const ESTIMATE_BY_STAGE = [30, 20, 10, 0];

const REMOTE_REFRESH_INTERVAL = 20000;

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

function getStageIndexFromValue(value) {
    const text = (value || '').toString().toLowerCase();
    if (!text) {
        return -1;
    }
    
    for (let index = STATUS_STAGES.length - 1; index >= 0; index -= 1) {
        const { keywords } = STATUS_STAGES[index];
        if (keywords.some(keyword => text.includes(keyword))) {
            return index;
        }
    }
    return -1;
}

function normaliseStatus(status) {
    const index = getStageIndexFromValue(status);
    return index >= 0 ? STATUS_STAGES[index].label : STATUS_STAGES[0].label;
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

function getStageIndexFromSteps(steps) {
    if (!Array.isArray(steps)) {
        return -1;
    }

    return steps.reduce((acc, step) => {
        if (!step) {
            return acc;
        }
        const done = step.done === true || step.completed === true || step.isCompleted === true;
        const active = step.active === true;
        if (!done && !active) {
            return acc;
        }
        const candidate = getStageIndexFromValue(step.title || step.label || step.key || step.status);
        return candidate > acc ? candidate : acc;
    }, -1);
}

function getStageIndexFromOrder(targetOrder) {
    if (!targetOrder) {
        return 0;
    }
    const fromSteps = getStageIndexFromSteps(targetOrder.statusSteps);
    const fromStatusText = getStageIndexFromValue(targetOrder.statusText);
    const fromStatus = getStageIndexFromValue(targetOrder.status);
    const candidate = Math.max(fromSteps, fromStatusText, fromStatus);
    return candidate >= 0 ? candidate : 0;
}

function updateProgressSteps(targetOrder) {
    const steps = [stepConfirmed, stepPrepared, stepDelivery, stepDelivered];
    const activeIndex = Math.max(0, getStageIndexFromOrder(targetOrder));
    steps.forEach((step, index) => {
        if (!step) return;
        step.classList.remove('active', 'completed');
        if (index < activeIndex) {
            step.classList.add('completed');
        } else if (index === activeIndex) {
            step.classList.add('active');
        }
        const labelEl = step.querySelector('.step-label');
        if (labelEl) {
            labelEl.textContent = STATUS_STAGES[index]?.label || labelEl.textContent;
        }
    });
    if (successEl) {
        successEl.hidden = activeIndex !== STATUS_STAGES.length - 1;
    }
}

function render() {
    if (!order) return;
    const stageIndex = getStageIndexFromOrder(order);
    const status = STATUS_STAGES[stageIndex]?.label || STATUS_STAGES[0].label;
    const statusText = (order.statusText || '').trim();
    order.status = status;
    if (orderIdEl) {
        orderIdEl.textContent = order.id ? `Order #${order.id}` : 'Order #';
    }
    if (estimatedTimeEl) {
        const remaining = ESTIMATE_BY_STAGE[stageIndex] ?? ESTIMATE_BY_STAGE[0];
        estimatedTimeEl.textContent = `Estimated: ${remaining} min`;
    }

    const statusMessage = statusText && statusText.toLowerCase() !== status.toLowerCase()
        ? `${statusText} (${status})`
        : status;
    setStatusMessage(`Status: ${statusMessage}`);
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
    updateProgressSteps(order);
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
    const flow = STATUS_STAGES.map(stage => stage.label);
    const idx = flow.indexOf(order.status);
    if (idx === -1 || idx >= flow.length - 1) {
        if (order.status === 'Delivered') {
            setStatusMessage('Status: Delivered 🎉', 'success');
        }
        return false;
    }
    
    const nextStatus = flow[idx + 1];
    order = { ...order, status: nextStatus, statusText: nextStatus, timestamp: new Date().toISOString() };
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

async function loadOrderFromServer(id, options = {}) {
    const { silent = false } = options;
    try {
        const response = await fetch(`/public/get_order.php?id=${encodeURIComponent(id)}`, { credentials: 'include' });
        const payload = await response.json();
        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to fetch order from server.');
        }
        const srv = payload.order || {};
        const remoteId = srv.externalId || srv.id || id;
        const rawStatus = srv.status || srv.orderStatus || '';
        const mapped = {
            id: remoteId,
            orderNumber: srv.orderNumber ?? (typeof srv.id === 'number' ? srv.id : null),
            externalId: srv.externalId ?? (typeof remoteId === 'string' ? remoteId : null),
            status: normaliseStatus(rawStatus),
            statusText: rawStatus,
            statusSteps: Array.isArray(srv.statusSteps) ? srv.statusSteps : [],
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
        if (!silent) {
            setStatusMessage(err.message || 'Unable to load order.', 'error');
        }
        return null;
    }
}

function stopProgressTimer() {
    if (progressTimer) {
        clearTimeout(progressTimer);
        progressTimer = null;
    }
}

function stopRemoteRefresh() {
    if (remoteRefreshTimer) {
        clearInterval(remoteRefreshTimer);
        remoteRefreshTimer = null;
    }
}

function startRemoteRefresh(id) {
    stopRemoteRefresh();
    remoteRefreshTimer = setInterval(async () => {
        const latest = await loadOrderFromServer(id, { silent: true });
        if (!latest) {
            return;
        }
        const mergedItems = Array.isArray(latest.items) && latest.items.length > 0
            ? latest.items
            : (Array.isArray(order?.items) ? order.items : []);
        order = {
            ...(order || {}),
            ...latest,
            items: mergedItems,
            statusSteps: Array.isArray(latest.statusSteps) && latest.statusSteps.length > 0
                ? latest.statusSteps
                : (Array.isArray(order?.statusSteps) ? order.statusSteps : []),
        };
        render();
        if (order.status === 'Delivered') {
            stopRemoteRefresh();
        }
    }, REMOTE_REFRESH_INTERVAL);
}

async function init() {
    await loadDB();
    const id = getParamId();
    if (!id) {
        setStatusMessage('Order id is missing from the link.', 'error');
        return;
    }
    const localOrder = findOrder(id);
    const remoteOrder = await loadOrderFromServer(id, { silent: Boolean(localOrder) });

    if (remoteOrder) {
        const mergedItems = Array.isArray(remoteOrder.items) && remoteOrder.items.length > 0
            ? remoteOrder.items
            : (Array.isArray(localOrder?.items) ? localOrder.items : []);
        order = {
            ...(localOrder || {}),
            ...remoteOrder,
            items: mergedItems,
            statusSteps: Array.isArray(remoteOrder.statusSteps) && remoteOrder.statusSteps.length > 0
                ? remoteOrder.statusSteps
                : (Array.isArray(localOrder?.statusSteps) ? localOrder.statusSteps : []),
        };
    } else if (localOrder) {
        order = localOrder;
    }

    if (!order) {
        setStatusMessage('Order not found.', 'error');
        return;
    }
    if (!Array.isArray(order.items)) {
        order.items = [];
    }
    setStatusMessage(`Status: ${normaliseStatus(order.status)}`);
    render();
    if (remoteOrder) {
        stopProgressTimer();
        startRemoteRefresh(id);
    } else {
        scheduleProgress();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    init();
});