import { Header } from "/public/assets/js/header.js";
Header() 

let db = null;
let currentRestaurant = null;

// DOM elements
const restaurantTitle = document.getElementById('restaurant-title');
const restaurantImage = document.getElementById('restaurant-image');
const restaurantName = document.getElementById('restaurant-name');
const restaurantCuisine = document.getElementById('restaurant-cuisine');
const restaurantLocation = document.getElementById('restaurant-location');
const restaurantDescription = document.getElementById('restaurant-description');
const productsGrid = document.getElementById('products-grid');
const restaurantNotFound = document.getElementById('restaurant-not-found');

// Get restaurant ID from URL parameters
function getRestaurantId() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

// Load database
async function loadDB() {
    try {
        const response = await fetch('/backend/database.php');
        db = await response.json();
    } catch (error) {
        console.error('Failed to load database:', error);
    }
}

// Find restaurant by ID
function findRestaurant(id) {
    if (!db || !db.restaurants) return null;
    return db.restaurants.find(restaurant => restaurant.id === id);
}

// Render restaurant details
function renderRestaurant(restaurant) {
    currentRestaurant = restaurant;
    
    // Update page title
    document.title = `${restaurant.name} | XIApee`;
    restaurantTitle.textContent = restaurant.name;
    
    // Update restaurant details
    if (restaurant.image) {
        restaurantImage.innerHTML = `<img src="${restaurant.image}" alt="${restaurant.name}">`;
    } else {
        restaurantImage.textContent = '🍽️';
    }
    
    restaurantName.textContent = restaurant.name;
    restaurantCuisine.textContent = restaurant.cuisine;
    restaurantLocation.textContent = restaurant.location;
    restaurantDescription.textContent = restaurant.description;
    
    // Render products
    renderProducts(restaurant.products || []);
}

// Render products
function renderProducts(products) {
    productsGrid.innerHTML = '';
    
    products.forEach(product => {
        const productCard = createProductCard(product);
        productsGrid.appendChild(productCard);
    });
}


// Initialize page
document.addEventListener('DOMContentLoaded', async () => {
    await loadDB();
    
    const restaurantId = getRestaurantId();
    if (!restaurantId) {
        restaurantNotFound.style.display = 'block';
        document.querySelector('.restaurant-content').style.display = 'none';
        return;
    }
    
    const restaurant = findRestaurant(restaurantId);
    if (!restaurant) {
        restaurantNotFound.style.display = 'block';
        document.querySelector('.restaurant-content').style.display = 'none';
        return;
    }
    
    renderRestaurant(restaurant);
});


// Add interactivity
const navigationTabs = document.querySelectorAll('.tab');
navigationTabs.forEach(tab => {
    tab.addEventListener('click', function () {
        navigationTabs.forEach(t => {
            t.classList.remove('active');
            t.classList.add('inactive');
        });
        this.classList.remove('inactive');
        this.classList.add('active');

        const targetId = this.dataset.target;
        if (targetId) {
            const sectionElement = document.getElementById(targetId);
            if (sectionElement) {
                sectionElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
});

// Add hover effects to cards
    document.querySelectorAll('.restaurant-card, .minimart-card').forEach(card => {
     card.addEventListener('mouseenter', function() {
         this.style.transform = 'translateY(-5px)';
     });
    
     card.addEventListener('mouseleave', function() {
         this.style.transform = 'translateY(0)';
     });
 });
