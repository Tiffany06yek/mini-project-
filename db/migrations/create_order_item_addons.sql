-- order_item_addons
-- Depends on: order_items(id), product_addons(id)

CREATE TABLE IF NOT EXISTS order_item_addons (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  order_item_id INT NOT NULL,             -- FK → order_items.id
  addon_id      INT NOT NULL,             -- FK → product_addons.id (match this type to product_addons.id)
  price         DECIMAL(10,2) NOT NULL,   -- addon price snapshot (per unit)

  CONSTRAINT fk_oia_item
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_oia_addon
    FOREIGN KEY (addon_id)     REFERENCES product_addons(id),

  INDEX idx_oia_item (order_item_id),
  INDEX idx_oia_addon(addon_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
