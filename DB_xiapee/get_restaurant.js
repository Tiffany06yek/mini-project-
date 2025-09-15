import {Product} from './product.js';

const API_MERCHANTS = '/xiapee/api/mainpage.php'; // 返回 { ok, merchants:[...] }
const API_MENU      = '/xiapee/api/get_menu.php';      // 返回 { ok, items:[...] }
const grid = document.querySelector('.restaurant-grid');

function renderList(merchant){
    grid.innerHTML = '';
    merchant.forEach(res => grid.appendChild([Product(res)]));
}

export async function load_merchant(    {tag = 'all'} = {} ) {
    const url = new URL(API_MERCHANTS, location.origin);
    url.searchParams.set('tag', tag);
    const res = await fetch(url);
    const data = await res.json();

    if(!res.ok || !data.ok){
        throw new Error (data.error || 'Loading Failed');
    }

    renderList(data.merchants);
}

loadMerchants().catch(err => {
    grid.innerHTML = `<p style="color:#c00">${err.message}</p>`;
  });


