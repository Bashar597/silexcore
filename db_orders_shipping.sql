ALTER TABLE orders
  ADD COLUMN tracking_number    VARCHAR(100)  NOT NULL DEFAULT '' AFTER zoho_payment_id,
  ADD COLUMN carrier            VARCHAR(50)   NOT NULL DEFAULT '' AFTER tracking_number,
  ADD COLUMN estimated_delivery DATE          NULL     AFTER carrier,
  ADD COLUMN shipped_at         TIMESTAMP     NULL     AFTER estimated_delivery;
