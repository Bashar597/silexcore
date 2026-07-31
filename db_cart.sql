-- Run in phpMyAdmin SQL tab (silexcor_store database)
CREATE TABLE IF NOT EXISTS cart_items (
  id           INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED    NOT NULL,
  product_key  VARCHAR(120)    NOT NULL,
  product_name VARCHAR(255)    NOT NULL,
  variant      VARCHAR(120)    NOT NULL DEFAULT '',
  price        DECIMAL(10,2)   NOT NULL,
  qty          SMALLINT        NOT NULL DEFAULT 1,
  image        VARCHAR(500)    NOT NULL DEFAULT '',
  updated_at   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_product (user_id, product_key),
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
