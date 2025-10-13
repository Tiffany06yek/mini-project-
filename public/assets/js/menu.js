import { Header } from '/public/assets/js/header.js';

// Initialize header
Header();

// ---------- 配置：确保这个路径与后端实际文件一致 ----------
const API_PATH = '/public/get_restaurant.php'; // <--- 如果你的 API 在 /api/get_restaurant.php，把这里改成那个路径

// DOM elements
const restaurantTitle = document.getElementById('restaurant-title');
const restaurantImage = document.getElementById('restaurant-image');
const restaurantName = document.getElementById('restaurant-name');
const restaurantCuisine = document.getElementById('restaurant-cuisine');
const restaurantLocation = document.getElementById('restaurant-location');
const restaurantDescription = document.getElementById('restaurant-description');
const productsGrid = document.getElementById('products-grid');
const restaurantNotFound = document.getElementById('restaurant-not-found');
const restaurantContent = document.querySelector('.restaurant-content');

// Small helper: get id from URL
function getRestaurantId() {
  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');
  return id ? id.trim() : null;
}

// Small helper: escape text for safety (used for setting innerHTML where required)
function escapeHtml(str) {
  if (str === undefined || str === null) return '';
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

// Fetch restaurant from backend API
async function fetchRestaurant(id) {
  try {
    const res = await fetch(`${API_PATH}?id=${encodeURIComponent(id)}`, {
      headers: { 'Accept': 'application/json' }
    });
    console.log('API status', res.status);

    // If backend returned non-JSON HTML error page, try to catch nicely
    if (!res.ok) {
      // try to parse body as json to show message, otherwise throw
      let errBody;
      try { errBody = await res.json(); } catch(e) { errBody = null; }
      throw errBody && errBody.error ? new Error(errBody.error) : new Error(`API returned status ${res.status}`);
    }

    const data = await res.json();
    console.log('API response', data);

    if (data.error) throw new Error(data.error);

    return data;
  } catch (err) {
    console.error('fetchRestaurant error:', err);
    throw err;
  }
}

// Render functions
function showNotFound() {
  if (restaurantContent) restaurantContent.style.display = 'none';
  if (restaurantNotFound) restaurantNotFound.style.display = 'block';
}

function clearNotFound() {
  if (restaurantContent) restaurantContent.style.display = '';
  if (restaurantNotFound) restaurantNotFound.style.display = 'none';
}

function renderRestaurant(restaurant, products) {
  clearNotFound();

  // normalize keys: some APIs return image_url, some 'image'
  const rImage = restaurant.image ?? restaurant.image_url ?? null;
  const rName = restaurant.name ?? 'Restaurant';
  const rTags = restaurant.tags ?? "None";

  document.title = `${rName} | XIApee`;
  restaurantTitle.textContent = rName;
  restaurantName.textContent = rName;
  restaurantCuisine.textContent = restaurant.tags ?? '';
  restaurantLocation.textContent = restaurant.location ?? '';
  restaurantDescription.textContent = restaurant.description ?? '';

  // image: set via DOM API to avoid injecting raw HTML
  restaurantImage.innerHTML = ''; // clear
  if (rImage) {
    const img = document.createElement('img');
    img.src =  `/public/assets/img/merchant_image/${rImage}`;
    img.alt = rName;
    img.loading = 'lazy';
    restaurantImage.appendChild(img);
  } else {
    restaurantImage.textContent = '🍽️';
  }

  renderProducts(products || []);
}

function renderProducts(products) {
  productsGrid.innerHTML = ''; // clear

  if (!products || products.length === 0) {
    const p = document.createElement('p');
    p.textContent = '此餐廳尚未上傳任何菜單。';
    productsGrid.appendChild(p);
    return;
  }

  products.forEach(product => {
    // normalize product fields
    const pid = product.id ?? product.product_id ?? '';
    const name = product.name ?? 'Unnamed';
    const desc = product.description ?? '';
    const price = (product.price !== undefined && product.price !== null) ? Number(product.price) : 0;
    const image = product.image ?? product.image_url ?? null;

    const card = document.createElement('div');
    card.className = 'product-card';

    // image block
    const imgWrap = document.createElement('div');
    imgWrap.className = 'product-image';
    if (image) {
      const img = document.createElement('img');
      img.src = `/public/assets/img/food_image/${image}`;
      img.alt = name;
      img.loading = 'lazy';
      imgWrap.appendChild(img);
    } else {
      imgWrap.textContent = '🍽️';
    }
    
    card.appendChild(imgWrap);

    // name
    const nameEl = document.createElement('div');
    nameEl.className = 'product-name';
    nameEl.textContent = name;
    card.appendChild(nameEl);

    // description
    const descEl = document.createElement('div');
    descEl.className = 'product-description';
    descEl.textContent = desc;
    card.appendChild(descEl);

    // price
    const priceEl = document.createElement('div');
    priceEl.className = 'product-price';
    priceEl.textContent = `RM ${price.toFixed(2)}`;
    card.appendChild(priceEl);

    // click behavior (preserve your original jump)
    card.addEventListener('click', () => {
      // vendorId use restaurant.id if available; otherwise pass empty
      const vendorId = encodeURIComponent(product.vendorId ?? product.merchant_id ?? product.merchantId ?? (document.location.search ? new URLSearchParams(document.location.search).get('id') : ''));
      const pidEnc = encodeURIComponent(pid);
      window.location.href = `/public/product.html?id=${pidEnc}&type=restaurant&vendorId=${vendorId}`;
    });

    productsGrid.appendChild(card);
  });
}

// Entry
document.addEventListener('DOMContentLoaded', async () => {
  const id = getRestaurantId();
  if (!id || isNaN(Number(id))) {
    console.warn('No valid id in URL:', id);
    showNotFound();
    return;
  }

  try {
    const data = await fetchRestaurant(id);
    // handle both shapes: { restaurant, products } or combined json
    const restaurant = data.restaurant ?? data.restautant ?? data; // tolerant with misspelling
    const products = data.products ?? [];

    if (!restaurant || Object.keys(restaurant).length === 0) {
      showNotFound();
      return;
    }

    renderRestaurant(restaurant, products);
  } catch (err) {
    showNotFound();
  }
});
