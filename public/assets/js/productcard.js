export function ProductCard(product, onAdd) {
    const card = document.createElement('div');
    const img = document.createElement('div');
    const content = document.createElement('div');
    const name = document.createElement('div');
    const meta = document.createElement('div');
    const price = document.createElement('span');
    const addBtn = document.createElement('button');

    card.classList.add('restaurant-card');
    img.classList.add('card-image');
    content.classList.add('card-content');
    name.classList.add('restaurant-name');
    meta.classList.add('restaurant-meta');

    img.innerHTML = product.image_url || product.image || null;
    name.innerHTML = product.name || "Product";
    price.innerHTML = `RM ${Number(product.price || 0).toFixed(2)}`;

    addBtn.textContent = "Add";
    addBtn.className = "add-btn";

    card.append(img, content);
    content.append(name, meta, addBtn);
    meta.append(price);

    addBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (typeof onAdd === 'function') onAdd(product);
    });

    // Add click event to navigate to product detail page
    card.addEventListener('click', (e) => {
        if (e.target === addBtn) return; // Don't navigate if clicking add button
        
        // Get vendor info from URL or context
        const currentPath = window.location.pathname;
        let vendorType = 'restaurant';
        let vendorId = '1';
        
        if (currentPath.includes('/mart/')) {
            vendorType = 'mart';
            const urlParams = new URLSearchParams(window.location.search);
            vendorId = urlParams.get('id') || '1';
        } else if (currentPath.includes('/restaurant/')) {
            vendorType = 'restaurant';
            const urlParams = new URLSearchParams(window.location.search);
            vendorId = urlParams.get('id') || '1';
        }
        
        // Navigate to product page
        window.location.href = `/public/product.html?id=${product.id}&type=${vendorType}&vendorId=${vendorId}`;
    });

    return card;
}