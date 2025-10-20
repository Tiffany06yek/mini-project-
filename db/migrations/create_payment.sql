CREATE TABLE `payments` (
  `payment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `payment_method` VARCHAR(32) NOT NULL,
  `status` ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `uniq_payments_order` (`order_id`),
  CONSTRAINT `fk_payments_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
