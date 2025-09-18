-- Sample data for order_items
-- Assume product_id 200 = "Pad Thai", 201 = "Fried Rice"
INSERT INTO order_items (order_id, product_id, qty, unit_price)
VALUES
(1, 200, 2, 9.00),
(1, 201, 1, 8.00),
(2, 200, 1, 9.00);
