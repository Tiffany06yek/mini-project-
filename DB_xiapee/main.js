import { Header } from "./components/header";
import { Product } from "./components/product";
Header() 
Product()
Product()
Product()
Product()
Product()
Product()
Product()
Product()


// Add interactivity
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.tab').forEach(t => {
            t.classList.remove('active');
            t.classList.add('inactive');
        });
        this.classList.remove('inactive');
        this.classList.add('active');
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

