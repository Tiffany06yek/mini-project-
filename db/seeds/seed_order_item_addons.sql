-- Sample data for order_item_addons
-- Assume addon_id 301 = "Extra Egg", 302 = "Cheese"
INSERT INTO order_item_addons (order_item_id, addon_id, price)
VALUES
(1, 301, 1.50),
(2, 302, 2.00),
(3, 301, 1.50);
