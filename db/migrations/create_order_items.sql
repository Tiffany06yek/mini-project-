-- order_items
-- Depends on: orders(id), products(id)

CREATE TABLE IF NOT EXISTS order_items (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  order_id   INT NOT NULL,                -- FK → orders.id
  product_id INT NOT NULL,                -- FK → products.id (match this type to products.id)
  qty        INT NOT NULL,                -- quantity of the product
  unit_price DECIMAL(10,2) NOT NULL,      -- price snapshot at order time

  CONSTRAINT fk_oi_order
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product
    FOREIGN KEY (product_id) REFERENCES products(id),

  INDEX idx_oi_order   (order_id),
  INDEX idx_oi_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
