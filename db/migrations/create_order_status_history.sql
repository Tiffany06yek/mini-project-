CREATE TABLE `Order_Status_History` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `status` enum('placed','accepted','preparing','on_the_way','delivered','cancelled') NOT NULL,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;