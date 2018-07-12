-- Orders table
CREATE TABLE `rupay_orders` (
  `id` INT(11) NOT NULL,
  `order_number` VARCHAR(64) NOT NULL,
  `transaction_id` VARCHAR(128) NOT NULL,
  `hash` VARCHAR(128) NOT NULL,
  `valid_through` DATETIME DEFAULT NULL,
  `buyer` VARCHAR(256) NOT NULL,
  `email` VARCHAR(64) NOT NULL,
  `phone` VARCHAR(64) NOT NULL,
  `address` VARCHAR(256) NOT NULL,
  `passport` VARCHAR(256) NOT NULL,
  `inn` VARCHAR(16) NOT NULL,
  `comment` VARCHAR(1024) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  `paid` DATETIME DEFAULT NULL,
  `fiscalized` DATETIME DEFAULT NULL,
  `refunded` DATETIME DEFAULT NULL,
  `refund_fiscalized` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `rupay_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE `order_number` (`order_number`),
  MODIFY `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;


-- Orders items table
CREATE TABLE `rupay_orders_items` (
  `id` INT(11) NOT NULL,
  `order_id` INT(11) NOT NULL,
  `product` VARCHAR(256) NOT NULL,
  `price` DECIMAL(20,2) NOT NULL,
  `quantity` DECIMAL(20,3) NOT NULL,
  `units` VARCHAR(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `rupay_orders_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  MODIFY `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  CHANGE `order_id` `order_id` INT(11) UNSIGNED NOT NULL;

ALTER TABLE `rupay_orders_items`
  ADD CONSTRAINT `rupay_orders_items_ibfk_1`
  FOREIGN KEY (`order_id`) REFERENCES `rupay_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;


-- Payments table
CREATE TABLE `rupay_orders_payments` (
  `id` INT(11) NOT NULL,
  `order_id` INT(11) NOT NULL,
  `gateway` VARCHAR(64) NOT NULL,
  `is_outdated` TINYINT(1) NOT NULL,
  `payment_url` VARCHAR(256) DEFAULT NULL,
  `gateway_order_id` VARCHAR(256) DEFAULT NULL,
  `data` VARCHAR(1024) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `rupay_orders_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  MODIFY `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  CHANGE `order_id` `order_id` INT(11) UNSIGNED NOT NULL;

ALTER TABLE `rupay_orders_payments`
  ADD CONSTRAINT `rupay_orders_payments_ibfk_1`
FOREIGN KEY (`order_id`) REFERENCES `rupay_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;