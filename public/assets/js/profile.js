// Profile Page JavaScript

// Load data from db.json with local override for prototype
const sampleData = {
    user: {
        name: "John Doe",
        email: "john.doe@example.com",
        phone: "+60 12-345 6789",
        dob: "1990-05-15",
        gender: "male",
        language: "en",
        dietaryPreferences: ["vegetarian", "halal"],
        totalOrders: 24,
        memberSince: 2023,
        loyaltyPoints: 1250,
        walletBalance: 125.50
    },
    orders: [
        {
            id: "ORD-001",
            date: "2024-01-15",
            status: "delivered",
            restaurant: "Nasi Lemak Makelk",
            items: 3,
            total: 25.50,
            deliveryTime: "25 min"
        },
        {
            id: "ORD-002",
            date: "2024-01-12",
            status: "delivered",
            restaurant: "Teh Tarik Corner",
            items: 2,
            total: 18.75,
            deliveryTime: "15 min"
        },
        {
            id: "ORD-003",
            date: "2024-01-10",
            status: "pending",
            restaurant: "Sushi Ten",
            items: 4,
            total: 45.20,
            deliveryTime: "30 min"
        },
        {
            id: "ORD-004",
            date: "2024-01-08",
            status: "cancelled",
            restaurant: "Ayam Geprek",
            items: 2,
            total: 22.00,
            deliveryTime: "20 min"
        }
    ],
    addresses: [
        {
            id: 1,
            type: "Home",
            name: "John Doe",
            address: "123 Jalan Universiti, Taman Universiti, 81300 Skudai, Johor",
            isDefault: true
        },
        {
            id: 2,
            type: "Office",
            name: "John Doe",
            address: "456 Jalan Teknologi, Taman Teknologi, 81300 Skudai, Johor",
            isDefault: false
        }
    ],
    transactions: [
        {
            id: "TXN-001",
            type: "topup",
            amount: 50.00,
            description: "Wallet Top Up",
            date: "2024-01-15",
            time: "14:30",
            status: "completed"
        },
        {
            id: "TXN-002",
            type: "payment",
            amount: -25.50,
            description: "Order Payment - Nasi Lemak Makelk",
            date: "2024-01-15",
            time: "12:15",
            status: "completed"
        },
        {
            id: "TXN-003",
            type: "refund",
            amount: 18.75,
            description: "Order Refund - Teh Tarik Corner",
            date: "2024-01-12",
            time: "16:45",
            status: "completed"
        },
        {
            id: "TXN-004",
            type: "topup",
            amount: 100.00,
            description: "Wallet Top Up",
            date: "2024-01-10",
            time: "09:20",
            status: "completed"
        },
        {
            id: "TXN-005",
            type: "withdrawal",
            amount: -30.00,
            description: "Withdrawal to Bank Account",
            date: "2024-01-08",
            time: "11:30",
            status: "completed"
        }
    ],
    paymentMethods: [
        {
            id: 1,
            type: "card",
            name: "Visa ****1234",
            icon: "💳",
            isDefault: true
        },
        {
            id: 2,
            type: "bank",
            name: "Maybank ****5678",
            icon: "🏦",
            isDefault: false
        },
        {
            id: 3,
            type: "ewallet",
            name: "GrabPay",
            icon: "📱",
            isDefault: false
        }
    ]
};

function getEffectiveDB() {
    try {
        const o = localStorage.getItem('xiapee_db_override');
        if (o) return JSON.parse(o);
    } catch {}
    return null;
}

async function ensureDB() {
    try {
        const res = await fetch('/data/db.json');
        const base = await res.json();
        const override = getEffectiveDB();
        return override || base;
    } catch {
        return null;
    }
}

// Initialize the profile page
document.addEventListener('DOMContentLoaded', async function() {
    const db = await ensureDB();
    if (db?.users?.length) {
        const user = db.users[0];
        sampleData.user.name = user.name;
        sampleData.user.email = `${user.name.toLowerCase().replace(/\s+/g,'')}@example.com`;
        sampleData.user.phone = '+60 12-345 6789';
        sampleData.user.walletBalance = Number(user.balance || 0);
        // Map orders
        const orders = user.orderHistory || [];
        sampleData.orders = orders.map(o => ({
            id: o.id,
            date: new Date(o.timestamp||Date.now()).toISOString().split('T')[0],
            status: (o.status||'pending').toLowerCase().replace(/\s+/g,' '),
            restaurant: (o.items?.[0]?.vendorName) || 'Mixed Vendors',
            items: o.items?.reduce((n,it)=>n+Number(it.qty||0),0) || 0,
            total: Number(o.total||0),
            deliveryTime: '-' 
        }));
    }
    initializeProfile();
    loadOrderHistory();
    loadPaymentHistory();
    loadAddresses();
    loadWalletData();
    loadTransactions();
    loadPaymentMethods();
    // Live updates from tracking page: poll override DB
    setInterval(async () => {
        const dbLive = getEffectiveDB();
        if (dbLive?.users?.length) {
            const user = dbLive.users[0];
            const orders = user.orderHistory || [];
            sampleData.user.walletBalance = Number(user.balance || 0);
            sampleData.orders = orders.map(o => ({
                id: o.id,
                date: new Date(o.timestamp||Date.now()).toISOString().split('T')[0],
                status: (o.status||'pending').toLowerCase().replace(/\s+/g,' '),
                restaurant: (o.items?.[0]?.vendorName) || 'Mixed Vendors',
                items: o.items?.reduce((n,it)=>n+Number(it.qty||0),0) || 0,
                total: Number(o.total||0),
                deliveryTime: '-' 
            }));
            loadOrderHistory();
            loadPaymentHistory();
            loadWalletData();
        }
    }, 5000);
});

// Initialize profile data
function initializeProfile() {
    const user = sampleData.user;
    
    // Update profile header
    document.getElementById('profileName').textContent = user.name;
    document.getElementById('profileEmail').textContent = user.email;
    document.getElementById('profilePhone').textContent = user.phone;
    document.getElementById('totalOrders').textContent = user.totalOrders;
    document.getElementById('memberSince').textContent = user.memberSince;
    document.getElementById('loyaltyPoints').textContent = user.loyaltyPoints.toLocaleString();
    document.getElementById('walletBalance').textContent = `RM ${user.walletBalance.toFixed(2)}`;
    
    // Update personal info form
    document.getElementById('fullName').value = user.name;
    document.getElementById('email').value = user.email;
    document.getElementById('phone').value = user.phone;
    document.getElementById('dob').value = user.dob;
    document.getElementById('gender').value = user.gender;
    document.getElementById('language').value = user.language;
    
    // Update dietary preferences
    const checkboxes = document.querySelectorAll('.checkbox-group input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        const label = checkbox.nextSibling.textContent.trim().toLowerCase();
        if (user.dietaryPreferences.includes(label)) {
            checkbox.checked = true;
        }
    });
}

// Tab switching functionality
function showTab(tabName) {
    // Remove active class from all tabs and panels
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    
    // Add active class to selected tab and panel
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
    document.getElementById(`${tabName}-panel`).classList.add('active');
    
    // Load specific data when switching tabs
    if (tabName === 'orders') {
        loadOrderHistory();
    } else if (tabName === 'addresses') {
        loadAddresses();
    } else if (tabName === 'wallet') {
        loadWalletData();
        loadTransactions();
        loadPaymentMethods();
    }
}

// Load order history
function loadOrderHistory() {
    const ordersList = document.getElementById('ordersList');
    const filter = document.getElementById('orderFilter').value;
    
    let filteredOrders = sampleData.orders;
    if (filter !== 'all') {
        filteredOrders = sampleData.orders.filter(order => order.status === filter);
    }
    
    ordersList.innerHTML = '';
    
    if (filteredOrders.length === 0) {
        ordersList.innerHTML = '<div class="no-orders">No orders found.</div>';
        return;
    }
    
    filteredOrders.forEach(order => {
        const orderElement = createOrderElement(order);
        ordersList.appendChild(orderElement);
    });
}

// Load payment history
function loadPaymentHistory() {
    const paymentsList = document.getElementById('paymentsList');
    const filter = document.getElementById('paymentFilter').value;
    
    let filteredPayments = sampleData.orders || [];
    if (filter === 'recent') {
        filteredPayments = filteredPayments.slice(-10);
    } else if (filter === 'this-month') {
        const thisMonth = new Date().getMonth();
        const thisYear = new Date().getFullYear();
        filteredPayments = filteredPayments.filter(order => {
            const orderDate = new Date(order.date);
            return orderDate.getMonth() === thisMonth && orderDate.getFullYear() === thisYear;
        });
    }
    
    paymentsList.innerHTML = '';
    
    if (filteredPayments.length === 0) {
        paymentsList.innerHTML = '<div class="no-payments">No payments found.</div>';
        return;
    }
    
    filteredPayments.forEach(order => {
        const paymentElement = createPaymentElement(order);
        paymentsList.appendChild(paymentElement);
    });
}

// Create order element
function createOrderElement(order) {
    const orderDiv = document.createElement('div');
    orderDiv.className = 'order-item';
    
    const statusClass = order.status === 'delivered' ? 'delivered' : 
                       order.status === 'pending' ? 'pending' : 'cancelled';
    
    orderDiv.innerHTML = `
        <div class="order-header">
            <span class="order-id">${order.id}</span>
            <span class="order-status ${statusClass}">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span>
        </div>
        <div class="order-details">
            <div class="order-detail">
                <div class="order-detail-label">Restaurant</div>
                <div class="order-detail-value">${order.restaurant}</div>
            </div>
            <div class="order-detail">
                <div class="order-detail-label">Items</div>
                <div class="order-detail-value">${order.items}</div>
            </div>
            <div class="order-detail">
                <div class="order-detail-label">Total</div>
                <div class="order-detail-value">RM ${order.total.toFixed(2)}</div>
            </div>
        </div>
        <div class="order-actions">
            <button class="order-action-btn" onclick="viewOrderDetails('${order.id}')">View Details</button>
            ${order.status === 'delivered' ? `<button class="order-action-btn" onclick="reorder('${order.id}')">Reorder</button>` : ''}
            ${order.status === 'pending' ? `<button class="order-action-btn" onclick="cancelOrder('${order.id}')">Cancel</button>` : ''}
        </div>
    `;
    
    return orderDiv;
}

// Create payment element
function createPaymentElement(order) {
    const paymentDiv = document.createElement('div');
    paymentDiv.className = 'payment-item';
    
    paymentDiv.innerHTML = `
        <div class="payment-info">
            <div class="payment-id">${order.id}</div>
            <div class="payment-date">${order.date}</div>
            <div class="payment-status">${order.status}</div>
        </div>
        <div class="payment-amount">RM ${order.total.toFixed(2)}</div>
    `;
    
    paymentDiv.addEventListener('click', () => {
        window.location.href = `/pages/tracking/index.html?id=${order.id}`;
    });
    
    return paymentDiv;
}

// Load addresses
function loadAddresses() {
    const addressesGrid = document.getElementById('addressesGrid');
    addressesGrid.innerHTML = '';
    
    sampleData.addresses.forEach(address => {
        const addressElement = createAddressElement(address);
        addressesGrid.appendChild(addressElement);
    });
}

// Create address element
function createAddressElement(address) {
    const addressDiv = document.createElement('div');
    addressDiv.className = `address-card ${address.isDefault ? 'default' : ''}`;
    
    addressDiv.innerHTML = `
        <div class="address-type ${address.isDefault ? 'default' : ''}">${address.type} ${address.isDefault ? '- Default' : ''}</div>
        <div class="address-name">${address.name}</div>
        <div class="address-text">${address.address}</div>
        <div class="address-actions">
            <button class="address-action-btn" onclick="editAddress(${address.id})">Edit</button>
            ${!address.isDefault ? `<button class="address-action-btn" onclick="setDefaultAddress(${address.id})">Set Default</button>` : ''}
            <button class="address-action-btn danger" onclick="deleteAddress(${address.id})">Delete</button>
        </div>
    `;
    
    return addressDiv;
}

// Personal info editing
function editPersonalInfo() {
    const inputs = document.querySelectorAll('#personal-panel input, #personal-panel select');
    const checkboxes = document.querySelectorAll('#personal-panel input[type="checkbox"]');
    const editBtn = document.querySelector('.edit-btn');
    
    if (editBtn.textContent === 'Edit') {
        // Enable editing
        inputs.forEach(input => {
            input.removeAttribute('readonly');
            input.removeAttribute('disabled');
        });
        checkboxes.forEach(checkbox => {
            checkbox.removeAttribute('disabled');
        });
        editBtn.textContent = 'Save';
        editBtn.style.background = '#28a745';
    } else {
        // Save changes
        savePersonalInfo();
        inputs.forEach(input => {
            input.setAttribute('readonly', 'readonly');
            input.setAttribute('disabled', 'disabled');
        });
        checkboxes.forEach(checkbox => {
            checkbox.setAttribute('disabled', 'disabled');
        });
        editBtn.textContent = 'Edit';
        editBtn.style.background = '#4a7c59';
    }
}

// Save personal info
function savePersonalInfo() {
    const formData = {
        name: document.getElementById('fullName').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        dob: document.getElementById('dob').value,
        gender: document.getElementById('gender').value,
        language: document.getElementById('language').value
    };
    
    // Update sample data
    Object.assign(sampleData.user, formData);
    
    // Update profile header
    document.getElementById('profileName').textContent = formData.name;
    document.getElementById('profileEmail').textContent = formData.email;
    document.getElementById('profilePhone').textContent = formData.phone;
    
    // Show success message
    showNotification('Personal information updated successfully!', 'success');
}

// Avatar editing
function editAvatar() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.querySelector('.avatar-img').src = e.target.result;
                showNotification('Profile picture updated successfully!', 'success');
            };
            reader.readAsDataURL(file);
        }
    };
    input.click();
}

// Address management
function addNewAddress() {
    const newAddress = {
        type: prompt('Address type (Home/Office/Other):') || 'Other',
        name: prompt('Full name:') || sampleData.user.name,
        address: prompt('Full address:') || '',
        isDefault: sampleData.addresses.length === 0
    };
    
    if (newAddress.address) {
        newAddress.id = sampleData.addresses.length + 1;
        sampleData.addresses.push(newAddress);
        loadAddresses();
        showNotification('Address added successfully!', 'success');
    }
}

function editAddress(addressId) {
    const address = sampleData.addresses.find(addr => addr.id === addressId);
    if (address) {
        const newType = prompt('Address type:', address.type) || address.type;
        const newName = prompt('Full name:', address.name) || address.name;
        const newAddress = prompt('Full address:', address.address) || address.address;
        
        if (newAddress) {
            address.type = newType;
            address.name = newName;
            address.address = newAddress;
            loadAddresses();
            showNotification('Address updated successfully!', 'success');
        }
    }
}

function setDefaultAddress(addressId) {
    sampleData.addresses.forEach(addr => {
        addr.isDefault = addr.id === addressId;
    });
    loadAddresses();
    showNotification('Default address updated!', 'success');
}

function deleteAddress(addressId) {
    if (confirm('Are you sure you want to delete this address?')) {
        const address = sampleData.addresses.find(addr => addr.id === addressId);
        if (address && address.isDefault && sampleData.addresses.length > 1) {
            // Set another address as default
            const otherAddress = sampleData.addresses.find(addr => addr.id !== addressId);
            if (otherAddress) {
                otherAddress.isDefault = true;
            }
        }
        
        sampleData.addresses = sampleData.addresses.filter(addr => addr.id !== addressId);
        loadAddresses();
        showNotification('Address deleted successfully!', 'success');
    }
}

// Order management
function viewOrderDetails(orderId) {
    const order = sampleData.orders.find(ord => ord.id === orderId);
    if (order) {
        alert(`Order Details:\n\nID: ${order.id}\nRestaurant: ${order.restaurant}\nItems: ${order.items}\nTotal: RM ${order.total.toFixed(2)}\nStatus: ${order.status}\nDate: ${order.date}`);
    }
}

function reorder(orderId) {
    const order = sampleData.orders.find(ord => ord.id === orderId);
    if (order) {
        if (confirm(`Reorder from ${order.restaurant} for RM ${order.total.toFixed(2)}?`)) {
            showNotification('Order added to cart!', 'success');
        }
    }
}

function cancelOrder(orderId) {
    if (confirm('Are you sure you want to cancel this order?')) {
        const order = sampleData.orders.find(ord => ord.id === orderId);
        if (order) {
            order.status = 'cancelled';
            loadOrderHistory();
            showNotification('Order cancelled successfully!', 'success');
        }
    }
}

// Settings functions
function changePassword() {
    const currentPassword = prompt('Enter current password:');
    if (currentPassword) {
        const newPassword = prompt('Enter new password:');
        if (newPassword) {
            const confirmPassword = prompt('Confirm new password:');
            if (newPassword === confirmPassword) {
                showNotification('Password changed successfully!', 'success');
            } else {
                showNotification('Passwords do not match!', 'error');
            }
        }
    }
}

function downloadData() {
    showNotification('Your data download has started. You will receive an email when ready.', 'success');
}

function deleteAccount() {
    if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
        if (confirm('This will permanently delete all your data. Type "DELETE" to confirm.')) {
            const confirmation = prompt('Type "DELETE" to confirm account deletion:');
            if (confirmation === 'DELETE') {
                showNotification('Account deletion request submitted. You will receive an email confirmation.', 'success');
            }
        }
    }
}

// Order filter
document.getElementById('orderFilter').addEventListener('change', function() {
    loadOrderHistory();
});

// Payment filter
document.getElementById('paymentFilter').addEventListener('change', function() {
    loadPaymentHistory();
});

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 1000;
        animation: slideIn 0.3s ease;
        max-width: 300px;
    `;
    
    if (type === 'success') {
        notification.style.background = '#28a745';
    } else if (type === 'error') {
        notification.style.background = '#dc3545';
    } else {
        notification.style.background = '#4a7c59';
    }
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Wallet Functions
function loadWalletData() {
    const user = sampleData.user;
    document.getElementById('walletBalanceDisplay').textContent = `RM ${user.walletBalance.toFixed(2)}`;
    document.getElementById('availableBalance').textContent = `RM ${user.walletBalance.toFixed(2)}`;
    document.getElementById('currentBalanceDisplay').value = `RM ${user.walletBalance.toFixed(2)}`;
}

document.addEventListener("DOMContentLoaded", () => {
    // 1️⃣ 如果用 hash，比如 /profile.html#wallet
    if (window.location.hash === "#wallet") {
      showTab("wallet");
    }
  
    // 2️⃣ 如果用 query，比如 /profile.html?panel=wallet
    const params = new URLSearchParams(window.location.search);
    if (params.get("panel") === "wallet") {
      showTab("wallet");
    }
  });

// Update wallet balance manually
async function updateWalletBalance() {
    const balanceInput = document.getElementById('currentBalanceDisplay');
    const newBalance = parseFloat(balanceInput.value.replace('RM ', ''));
    
    if (isNaN(newBalance) || newBalance < 0) {
        showNotification('Please enter a valid balance amount', 'error');
        return;
    }
    
    // Update local data
    sampleData.user.walletBalance = newBalance;
    
    // Update database
    await updateDatabaseBalance(newBalance);
    
    loadWalletData();
    showNotification('Wallet balance updated successfully', 'success');
}

// Update database balance
async function updateDatabaseBalance(newBalance) {
    try {
        // Get current database
        const response = await fetch('/data/db.json');
        const db = await response.json();
        
        // Update user balance
        if (db.users && db.users.length > 0) {
            db.users[0].balance = newBalance;
        }
        
        // Store in localStorage for persistence (since we can't write to file directly)
        localStorage.setItem('xiapee_db_override', JSON.stringify(db));
        
        // Update global balance display
        updateGlobalBalanceDisplay(newBalance);
        
    } catch (error) {
        console.error('Failed to update database:', error);
        showNotification('Failed to update database', 'error');
    }
}

// Update global balance display across all pages
function updateGlobalBalanceDisplay(newBalance) {
    // Update any wallet balance displays on the current page
    const balanceElements = document.querySelectorAll('[id*="balance"], [id*="Balance"]');
    balanceElements.forEach(element => {
        if (element.textContent.includes('RM')) {
            element.textContent = `RM ${newBalance.toFixed(2)}`;
        }
    });
    
    // Dispatch custom event for other pages to listen to
    window.dispatchEvent(new CustomEvent('balanceUpdated', { 
        detail: { newBalance } 
    }));
}

// Top up wallet
function topUpWallet() {
    const topUpInput = document.getElementById('topUpAmount');
    const amount = parseFloat(topUpInput.value);
    
    if (isNaN(amount) || amount <= 0) {
        showNotification('Please enter a valid top-up amount', 'error');
        return;
    }
    
    sampleData.user.walletBalance += amount;
    loadWalletData();
    topUpInput.value = '';
    showNotification(`Successfully topped up RM ${amount.toFixed(2)}`, 'success');
}

function loadTransactions() {
    const transactionsList = document.getElementById('transactionsList');
    const filter = document.getElementById('transactionFilter').value;
    
    let filteredTransactions = sampleData.transactions;
    if (filter !== 'all') {
        filteredTransactions = sampleData.transactions.filter(transaction => transaction.type === filter);
    }
    
    transactionsList.innerHTML = '';
    
    if (filteredTransactions.length === 0) {
        transactionsList.innerHTML = '<div class="no-transactions">No transactions found.</div>';
        return;
    }
    
    filteredTransactions.forEach(transaction => {
        const transactionElement = createTransactionElement(transaction);
        transactionsList.appendChild(transactionElement);
    });
}

function createTransactionElement(transaction) {
    const transactionDiv = document.createElement('div');
    transactionDiv.className = 'transaction-item';
    
    const iconMap = {
        topup: '💰',
        payment: '💳',
        refund: '↩️',
        withdrawal: '💸'
    };
    
    const isPositive = transaction.amount > 0;
    const amountClass = isPositive ? 'positive' : 'negative';
    const amountPrefix = isPositive ? '+' : '';
    
    transactionDiv.innerHTML = `
        <div class="transaction-info">
            <div class="transaction-icon ${transaction.type}">
                ${iconMap[transaction.type]}
            </div>
            <div class="transaction-details">
                <h4>${transaction.description}</h4>
                <p>${transaction.date} at ${transaction.time}</p>
            </div>
        </div>
        <div class="transaction-amount">
            <div class="amount ${amountClass}">${amountPrefix}RM ${Math.abs(transaction.amount).toFixed(2)}</div>
            <div class="date">${transaction.status}</div>
        </div>
    `;
    
    return transactionDiv;
}

function loadPaymentMethods() {
    const paymentMethods = document.getElementById('paymentMethods');
    paymentMethods.innerHTML = '';
    
    sampleData.paymentMethods.forEach(method => {
        const methodElement = createPaymentMethodElement(method);
        paymentMethods.appendChild(methodElement);
    });
}

function createPaymentMethodElement(method) {
    const methodDiv = document.createElement('div');
    methodDiv.className = 'payment-method-item';
    
    methodDiv.innerHTML = `
        <div class="payment-method-info">
            <div class="payment-method-icon">${method.icon}</div>
            <div class="payment-method-details">
                <h4>${method.name} ${method.isDefault ? '- Default' : ''}</h4>
                <p>${method.type.charAt(0).toUpperCase() + method.type.slice(1)}</p>
            </div>
        </div>
        <div class="payment-method-actions">
            ${!method.isDefault ? `<button class="payment-method-btn" onclick="setDefaultPaymentMethod(${method.id})">Set Default</button>` : ''}
            <button class="payment-method-btn danger" onclick="removePaymentMethod(${method.id})">Remove</button>
        </div>
    `;
    
    return methodDiv;
}

// Modal Functions
function showTopUpModal() {
    document.getElementById('topUpModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function showWithdrawModal() {
    document.getElementById('withdrawModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function editBalance() {
    document.getElementById('editBalanceModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Top-up Functions
function selectAmount(amount) {
    // Remove selected class from all buttons
    document.querySelectorAll('.amount-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    
    // Add selected class to clicked button
    event.target.classList.add('selected');
    
    // Set custom amount input
    document.getElementById('customAmount').value = amount;
}

async function quickTopUp(amount) {
    const newBalance = sampleData.user.walletBalance + amount;
    sampleData.user.walletBalance = newBalance;
    
    // Add transaction
    const newTransaction = {
        id: `TXN-${Date.now()}`,
        type: 'topup',
        amount: amount,
        description: 'Quick Top Up',
        date: new Date().toISOString().split('T')[0],
        time: new Date().toTimeString().split(' ')[0].substring(0, 5),
        status: 'completed'
    };
    
    sampleData.transactions.unshift(newTransaction);
    
    // Update database
    await updateDatabaseBalance(newBalance);
    
    // Update UI
    loadWalletData();
    loadTransactions();
    showNotification(`Successfully topped up RM ${amount.toFixed(2)}!`, 'success');
}

async function processTopUp() {
    const customAmount = parseFloat(document.getElementById('customAmount').value);
    const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
    
    if (!customAmount || customAmount <= 0) {
        showNotification('Please enter a valid amount!', 'error');
        return;
    }
    
    if (customAmount > 10000) {
        showNotification('Maximum top-up amount is RM 10,000!', 'error');
        return;
    }
    
    // Simulate payment processing
    showNotification('Processing payment...', 'info');
    
    setTimeout(async () => {
        const newBalance = sampleData.user.walletBalance + customAmount;
        sampleData.user.walletBalance = newBalance;
        
        // Add transaction
        const newTransaction = {
            id: `TXN-${Date.now()}`,
            type: 'topup',
            amount: customAmount,
            description: `Wallet Top Up via ${paymentMethod}`,
            date: new Date().toISOString().split('T')[0],
            time: new Date().toTimeString().split(' ')[0].substring(0, 5),
            status: 'completed'
        };
        
        sampleData.transactions.unshift(newTransaction);
        
        // Update database
        await updateDatabaseBalance(newBalance);
        
        // Update UI
        loadWalletData();
        loadTransactions();
        closeModal('topUpModal');
        showNotification(`Successfully topped up RM ${customAmount.toFixed(2)}!`, 'success');
        
        // Reset form
        document.getElementById('customAmount').value = '';
        document.querySelectorAll('.amount-btn').forEach(btn => {
            btn.classList.remove('selected');
        });
    }, 2000);
}

// Withdrawal Functions
function processWithdrawal() {
    const amount = parseFloat(document.getElementById('withdrawAmount').value);
    const bankAccount = document.getElementById('bankAccount').value;
    
    if (!amount || amount <= 0) {
        showNotification('Please enter a valid amount!', 'error');
        return;
    }
    
    if (amount > sampleData.user.walletBalance) {
        showNotification('Insufficient balance!', 'error');
        return;
    }
    
    if (!bankAccount) {
        showNotification('Please select a bank account!', 'error');
        return;
    }
    
    // Simulate withdrawal processing
    showNotification('Processing withdrawal...', 'info');
    
    setTimeout(() => {
        sampleData.user.walletBalance -= amount;
        
        // Add transaction
        const newTransaction = {
            id: `TXN-${Date.now()}`,
            type: 'withdrawal',
            amount: -amount,
            description: `Withdrawal to ${bankAccount}`,
            date: new Date().toISOString().split('T')[0],
            time: new Date().toTimeString().split(' ')[0].substring(0, 5),
            status: 'completed'
        };
        
        sampleData.transactions.unshift(newTransaction);
        
        // Update UI
        loadWalletData();
        loadTransactions();
        closeModal('withdrawModal');
        showNotification(`Withdrawal of RM ${amount.toFixed(2)} processed!`, 'success');
        
        // Reset form
        document.getElementById('withdrawAmount').value = '';
        document.getElementById('bankAccount').value = '';
    }, 2000);
}

// Balance Edit Functions
async function processBalanceEdit() {
    const newBalance = parseFloat(document.getElementById('newBalance').value);
    const reason = document.getElementById('balanceReason').value;
    const notes = document.getElementById('balanceNotes').value;
    
    if (newBalance < 0) {
        showNotification('Balance cannot be negative!', 'error');
        return;
    }
    
    const oldBalance = sampleData.user.walletBalance;
    const difference = newBalance - oldBalance;
    
    if (Math.abs(difference) < 0.01) {
        showNotification('No change in balance!', 'error');
        return;
    }
    
    sampleData.user.walletBalance = newBalance;
    
    // Add transaction
    const newTransaction = {
        id: `TXN-${Date.now()}`,
        type: difference > 0 ? 'refund' : 'payment',
        amount: difference,
        description: `Balance ${reason}${notes ? ': ' + notes : ''}`,
        date: new Date().toISOString().split('T')[0],
        time: new Date().toTimeString().split(' ')[0].substring(0, 5),
        status: 'completed'
    };
    
    sampleData.transactions.unshift(newTransaction);
    
    // Update database
    await updateDatabaseBalance(newBalance);
    
    // Update UI
    loadWalletData();
    loadTransactions();
    closeModal('editBalanceModal');
    showNotification(`Balance updated to RM ${newBalance.toFixed(2)}!`, 'success');
    
    // Reset form
    document.getElementById('newBalance').value = '';
    document.getElementById('balanceNotes').value = '';
}

// Payment Method Functions
function addPaymentMethod() {
    const methodType = prompt('Payment method type (card/bank/ewallet):');
    const methodName = prompt('Payment method name:');
    
    if (methodType && methodName) {
        const iconMap = {
            card: '💳',
            bank: '🏦',
            ewallet: '📱'
        };
        
        const newMethod = {
            id: sampleData.paymentMethods.length + 1,
            type: methodType,
            name: methodName,
            icon: iconMap[methodType] || '💳',
            isDefault: sampleData.paymentMethods.length === 0
        };
        
        sampleData.paymentMethods.push(newMethod);
        loadPaymentMethods();
        showNotification('Payment method added successfully!', 'success');
    }
}

function setDefaultPaymentMethod(methodId) {
    sampleData.paymentMethods.forEach(method => {
        method.isDefault = method.id === methodId;
    });
    loadPaymentMethods();
    showNotification('Default payment method updated!', 'success');
}

function removePaymentMethod(methodId) {
    if (confirm('Are you sure you want to remove this payment method?')) {
        const method = sampleData.paymentMethods.find(m => m.id === methodId);
        if (method && method.isDefault && sampleData.paymentMethods.length > 1) {
            // Set another method as default
            const otherMethod = sampleData.paymentMethods.find(m => m.id !== methodId);
            if (otherMethod) {
                otherMethod.isDefault = true;
            }
        }
        
        sampleData.paymentMethods = sampleData.paymentMethods.filter(m => m.id !== methodId);
        loadPaymentMethods();
        showNotification('Payment method removed successfully!', 'success');
    }
}

// Transaction filter
document.addEventListener('DOMContentLoaded', function() {
    const transactionFilter = document.getElementById('transactionFilter');
    if (transactionFilter) {
        transactionFilter.addEventListener('change', function() {
            loadTransactions();
        });
    }
});

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
});

// Add CSS animations for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .no-orders,
    .no-transactions {
        text-align: center;
        color: #666;
        font-style: italic;
        padding: 40px;
    }
`;
document.head.appendChild(style);