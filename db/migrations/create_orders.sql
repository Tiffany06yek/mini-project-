--Orders Table
CREATE TABLE IF NOT EXISTS orders (
  id              INT PRIMARY KEY AUTO_INCREMENT,
  buyer_id        BIGINT UNSIGNED NOT NULL,   -- FK → users.id
  merchant_id     INT UNSIGNED NOT NULL,      -- FK → merchants.id
  address         TEXT,
  notes           TEXT,
  delivery_fee    DECIMAL(10,2) NOT NULL DEFAULT 0,
  subtotal        DECIMAL(10,2) NOT NULL,
  total           DECIMAL(10,2) NOT NULL,
  payment_method  ENUM('cash','card','wallet','online_banking') NOT NULL,
  payment_status  ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_buyer
    FOREIGN KEY (buyer_id) REFERENCES users(id),
  CONSTRAINT fk_orders_merchant
    FOREIGN KEY (merchant_id) REFERENCES merchants(id),
  INDEX idx_orders_buyer_created    (buyer_id, created_at),
  INDEX idx_orders_merchant_created (merchant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;