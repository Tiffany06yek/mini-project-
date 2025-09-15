export function Product(merchants) {
  const card = document.createElement('div')
  const img = document.createElement('div')
  const content = document.createElement('div')
  const name = document.createElement('div')
  const meta = document.createElement('div')
  const rating = document.createElement('div')
  const price = document.createElement('span')

  card.classList.add('restaurant-card')
  img.classList.add('card-image')
  content.classList.add('card-content')
  name.classList.add('restaurant-name')
  meta.classList.add('restaurant-meta')
  rating.classList.add('rating')

  const title = merchant.name ?? 'No Name';
  const stars = merchant.rating ?? 'No ratings';
  const tags = merchant.tags;
  const location = merchant.location ?? 'Unavailable';
  const open = merchant.open_hours ?? 'Closed';
  const eta = merchant.eta;

  img.textContent = '🍜';
  if (merchant.image_url){
    img.style.backgroundImage = `url($(merchant.image_url))`;
    img.textContent ='';
  }
  name.textContent = title;
  rating.textContent = stars;
  tags.textContent = tags;

  card.append(img, content)
  content.append(name, meta)
  meta.append(rating, price)
  
  const restaurant_grid = document.querySelector('.restaurant-grid')
  restaurant_grid.append(card)

}