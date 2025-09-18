-- Sample data for orders
INSERT INTO orders (buyer_id, merchant_id, address, notes, delivery_fee, subtotal, total, payment_method, payment_status)
VALUES
(1, 1000, 'Block A, Room 101', 'Extra spicy', 2.00, 18.00, 20.00, 'cash', 'pending'),
(2, 1001, 'Block B, Room 205', 'No peanuts', 2.00, 25.00, 27.00, 'card', 'paid');
