CREATE TABLE `Reviews` (
  `id` int NOT NULL,
  `merchant_id` int NOT NULL,
  `user_id` int NOT NULL,
  `rating` decimal(2,1) NOT NULL,
  `text` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ;
