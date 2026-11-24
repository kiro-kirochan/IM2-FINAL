-- =====================================================================
--  ClearDrop Water Refilling Station Ordering & Delivery System
--  IT116 - Information Management 2  |  Final Project
--  Target: MySQL 8 / MariaDB 10.4+ (the version bundled with XAMPP)
--
--  HOW TO IMPORT (XAMPP):
--    1. Start Apache + MySQL in the XAMPP Control Panel.
--    2. Open http://localhost/phpmyadmin  ->  "Import" tab.
--    3. Choose this file  ->  "Import".
--    (or in MySQL Workbench: File > Run SQL Script)
--
--  WARNING: this script DROPS and recreates the database cleardrop_db.
--           Re-importing resets everybody's local data to the samples.
--
--  DESIGN NOTES (changes vs. the Week 2 data dictionary are marked [NEW]):
--   * Nothing is ever hard-deleted.  Every DELETE is blocked by a trigger,
--     and "deleting" in the app = soft delete (is_deleted / deleted_at).
--   * [NEW] orders.delivery_fee_charged  - snapshot of the zone fee, so a
--     later fee change does not alter old orders (same idea as
--     order_items.unit_price_charged, Business Rule 5).
--   * [NEW] order_items.is_removed       - items cannot be deleted either, so
--     removing a line while editing an order marks it removed.
--   * [NEW] container_loans              - tracks gallons lent to customers
--     (the "nobody tracks which customer holds which container" problem
--     in the Week 1 problem statement).
--   * [NEW] receipt_seq                  - safe running receipt numbers.
--   * Customers.contact_number is NULL-able so walk-ins can stay anonymous
--     (Rule 2); a trigger makes contact/street/barangay mandatory for
--     DELIVERY orders (Rules 2 and 8).
-- =====================================================================

DROP DATABASE IF EXISTS cleardrop_db;
CREATE DATABASE cleardrop_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cleardrop_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. TABLES
-- ---------------------------------------------------------------------

CREATE TABLE delivery_zones (
  zone_id       INT AUTO_INCREMENT PRIMARY KEY,
  barangay      VARCHAR(100) NOT NULL,
  delivery_fee  DECIMAL(10,2) NOT NULL,
  is_deleted    TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at    DATETIME NULL,
  CONSTRAINT uq_zone_barangay UNIQUE (barangay),
  CONSTRAINT ck_zone_fee CHECK (delivery_fee >= 0)
) ENGINE=InnoDB;

CREATE TABLE riders (
  rider_id    INT AUTO_INCREMENT PRIMARY KEY,
  rider_name  VARCHAR(100) NOT NULL,
  vehicle     VARCHAR(50)  NOT NULL,
  is_deleted  TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at  DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE customers (
  customer_id     INT AUTO_INCREMENT PRIMARY KEY,
  full_name       VARCHAR(100) NOT NULL,
  contact_number  VARCHAR(20)  NULL,
  street          VARCHAR(100) NULL,
  zone_id         INT NULL,
  is_system       TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = built-in "Walk-in Customer"
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_deleted      TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at      DATETIME NULL,
  CONSTRAINT fk_customers_zone FOREIGN KEY (zone_id) REFERENCES delivery_zones(zone_id)
) ENGINE=InnoDB;

CREATE TABLE products (
  product_code        VARCHAR(20)  PRIMARY KEY,
  product_name        VARCHAR(100) NOT NULL,
  container_type      VARCHAR(30)  NOT NULL,
  service_type        VARCHAR(30)  NOT NULL,
  current_unit_price  DECIMAL(10,2) NOT NULL,
  is_deleted          TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at          DATETIME NULL,
  CONSTRAINT ck_prod_container CHECK (container_type IN ('Slim','Round','Small Bottle')),
  CONSTRAINT ck_prod_service   CHECK (service_type IN ('Refill Only','Buy Container','Refill + New Container')),
  CONSTRAINT ck_prod_price     CHECK (current_unit_price >= 0)
) ENGINE=InnoDB;

CREATE TABLE orders (
  order_no              INT AUTO_INCREMENT PRIMARY KEY,
  customer_id           INT NOT NULL,
  order_type            VARCHAR(20) NOT NULL,
  order_status          VARCHAR(30) NOT NULL DEFAULT 'Received',
  order_date            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  queue_date            DATE NOT NULL,
  queue_no              INT NOT NULL,
  delivery_fee_charged  DECIMAL(10,2) NOT NULL DEFAULT 0,      -- [NEW]
  notes                 VARCHAR(255) NULL,
  is_deleted            TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at            DATETIME NULL,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
  CONSTRAINT uq_orders_queue UNIQUE (queue_date, queue_no),    -- Rule 13
  CONSTRAINT ck_orders_fee CHECK (delivery_fee_charged >= 0),
  CONSTRAINT ck_orders_type CHECK (order_type IN ('Walk-in','Delivery')),
  CONSTRAINT ck_orders_status CHECK (                          -- Rule 7
    (order_type = 'Walk-in'  AND order_status IN ('Received','Preparing','Ready for Pickup','Released','Cancelled'))
 OR (order_type = 'Delivery' AND order_status IN ('Received','Preparing','To be Delivered','Out for Delivery','Delivered','Delivery Unsuccessful','Cancelled'))
  )
) ENGINE=InnoDB AUTO_INCREMENT=1001;

CREATE TABLE order_items (
  order_no            INT NOT NULL,
  product_code        VARCHAR(20) NOT NULL,
  qty                 INT NOT NULL,
  unit_price_charged  DECIMAL(10,2) NOT NULL,
  is_removed          TINYINT(1) NOT NULL DEFAULT 0,           -- [NEW]
  PRIMARY KEY (order_no, product_code),
  CONSTRAINT fk_items_order   FOREIGN KEY (order_no)     REFERENCES orders(order_no),
  CONSTRAINT fk_items_product FOREIGN KEY (product_code) REFERENCES products(product_code),
  CONSTRAINT ck_items_qty   CHECK (qty > 0),                   -- Rule 6
  CONSTRAINT ck_items_price CHECK (unit_price_charged >= 0)
) ENGINE=InnoDB;

CREATE TABLE payments (
  payment_id    INT AUTO_INCREMENT PRIMARY KEY,
  order_no      INT NOT NULL,
  receipt_no    VARCHAR(30) NOT NULL,
  payment_date  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  amount_paid   DECIMAL(10,2) NOT NULL,
  payment_mode  VARCHAR(20) NOT NULL,
  CONSTRAINT fk_payments_order FOREIGN KEY (order_no) REFERENCES orders(order_no),
  CONSTRAINT uq_payments_receipt UNIQUE (receipt_no),          -- Rule 12
  CONSTRAINT ck_payments_amount CHECK (amount_paid > 0),
  CONSTRAINT ck_payments_mode CHECK (payment_mode IN ('Cash','GCash'))
) ENGINE=InnoDB;

CREATE TABLE claim_stubs (
  stub_no      INT AUTO_INCREMENT PRIMARY KEY,
  order_no     INT NOT NULL,
  issued_date  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_stub_order UNIQUE (order_no),                  -- 1:1 with orders
  CONSTRAINT fk_stub_order FOREIGN KEY (order_no) REFERENCES orders(order_no)
) ENGINE=InnoDB;

CREATE TABLE deliveries (
  delivery_id      INT AUTO_INCREMENT PRIMARY KEY,
  order_no         INT NOT NULL,
  rider_id         INT NOT NULL,
  delivery_status  VARCHAR(30) NOT NULL DEFAULT 'Dispatched',
  dispatch_time    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completion_time  DATETIME NULL,
  CONSTRAINT fk_deliv_order FOREIGN KEY (order_no) REFERENCES orders(order_no),
  CONSTRAINT fk_deliv_rider FOREIGN KEY (rider_id) REFERENCES riders(rider_id),
  CONSTRAINT ck_deliv_status CHECK (delivery_status IN ('Dispatched','Delivered','Failed'))
) ENGINE=InnoDB;

-- [NEW] Gallons lent out to customers (Week 1 problem: "nobody tracks containers")
CREATE TABLE container_loans (
  loan_id          INT AUTO_INCREMENT PRIMARY KEY,
  customer_id      INT NOT NULL,
  container_type   VARCHAR(30) NOT NULL,
  qty              INT NOT NULL,
  qty_returned     INT NOT NULL DEFAULT 0,
  date_lent        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_returned_at DATETIME NULL,
  notes            VARCHAR(255) NULL,
  is_deleted       TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at       DATETIME NULL,
  CONSTRAINT fk_loans_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
  CONSTRAINT ck_loans_type CHECK (container_type IN ('Slim','Round','Small Bottle')),
  CONSTRAINT ck_loans_qty  CHECK (qty > 0 AND qty_returned >= 0 AND qty_returned <= qty)
) ENGINE=InnoDB;

-- [NEW] running receipt number per year (RCPT-2026-0001, ...)
CREATE TABLE receipt_seq (
  seq_year  INT PRIMARY KEY,
  last_no   INT NOT NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 2. VIEWS
-- ---------------------------------------------------------------------

-- One row per order with computed total, amount paid and balance
-- (Business Rules 9 and 10).  Subqueries avoid duplicated rows when an
-- order has several items AND several payments.
CREATE VIEW v_order_summary AS
SELECT
  o.order_no, o.queue_date, o.queue_no, o.order_date, o.order_type, o.order_status,
  o.customer_id, c.full_name AS customer_name, c.contact_number, c.street, z.barangay,
  COALESCE(i.item_count, 0)  AS item_count,
  COALESCE(i.items_total, 0) AS items_total,
  o.delivery_fee_charged,
  COALESCE(i.items_total, 0) + o.delivery_fee_charged AS order_total,
  COALESCE(p.total_paid, 0)  AS total_paid,
  GREATEST(COALESCE(i.items_total, 0) + o.delivery_fee_charged - COALESCE(p.total_paid, 0), 0) AS balance,
  CASE
    WHEN COALESCE(p.total_paid, 0) = 0 THEN 'Unpaid'
    WHEN COALESCE(p.total_paid, 0) >= COALESCE(i.items_total, 0) + o.delivery_fee_charged THEN 'Paid'
    ELSE 'Partial'
  END AS payment_status,
  cs.stub_no, o.notes, o.is_deleted, o.deleted_at
FROM orders o
JOIN customers c           ON c.customer_id = o.customer_id
LEFT JOIN delivery_zones z ON z.zone_id = c.zone_id
LEFT JOIN (SELECT order_no, SUM(qty) AS item_count, SUM(qty * unit_price_charged) AS items_total
           FROM order_items WHERE is_removed = 0 GROUP BY order_no) i ON i.order_no = o.order_no
LEFT JOIN (SELECT order_no, SUM(amount_paid) AS total_paid
           FROM payments GROUP BY order_no) p ON p.order_no = o.order_no
LEFT JOIN claim_stubs cs   ON cs.order_no = o.order_no;

-- Daily sales report (money actually collected per day)
CREATE VIEW v_daily_sales AS
SELECT DATE(payment_date) AS sales_date,
       COUNT(*)           AS payments_count,
       SUM(amount_paid)   AS total_collected,
       SUM(CASE WHEN payment_mode = 'Cash'  THEN amount_paid ELSE 0 END) AS cash_total,
       SUM(CASE WHEN payment_mode = 'GCash' THEN amount_paid ELSE 0 END) AS gcash_total
FROM payments
GROUP BY DATE(payment_date);

-- ---------------------------------------------------------------------
-- 3. TRIGGERS and STORED PROCEDURES
-- ---------------------------------------------------------------------
DELIMITER $$

-- 3.1 ORDERS -----------------------------------------------------------
-- Rules 2, 8, 13: validates the customer for delivery, snapshots the
-- delivery fee, and assigns the acceptance time + daily queue number.
CREATE TRIGGER trg_orders_bi BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
  DECLARE v_contact VARCHAR(20);
  DECLARE v_street  VARCHAR(100);
  DECLARE v_zone    INT;
  DECLARE v_gone    TINYINT;
  DECLARE v_fee     DECIMAL(10,2);

  SELECT contact_number, street, zone_id, is_deleted
    INTO v_contact, v_street, v_zone, v_gone
    FROM customers WHERE customer_id = NEW.customer_id;

  IF v_gone = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This customer is archived. Restore the customer first.';
  END IF;

  IF NEW.order_type = 'Delivery' THEN
    IF v_contact IS NULL OR v_contact = '' OR v_street IS NULL OR v_street = '' OR v_zone IS NULL THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Delivery orders need the customer''s contact number, street and barangay.';
    END IF;
    SELECT delivery_fee INTO v_fee FROM delivery_zones WHERE zone_id = v_zone;
    SET NEW.delivery_fee_charged = v_fee;
  ELSE
    SET NEW.delivery_fee_charged = 0;
  END IF;

  IF NEW.order_date IS NULL THEN SET NEW.order_date = NOW(); END IF;
  IF NEW.queue_date IS NULL THEN SET NEW.queue_date = DATE(NEW.order_date); END IF;
  IF NEW.queue_no IS NULL THEN
    SELECT COALESCE(MAX(queue_no), 0) + 1 INTO v_fee FROM orders WHERE queue_date = NEW.queue_date;
    SET NEW.queue_no = v_fee;
  END IF;
END$$

-- Every new order automatically gets its claim stub (Rule 16 is shown on the slip).
CREATE TRIGGER trg_orders_ai AFTER INSERT ON orders
FOR EACH ROW
BEGIN
  INSERT INTO claim_stubs (order_no, issued_date) VALUES (NEW.order_no, NEW.order_date);
END$$

CREATE TRIGGER trg_orders_bu BEFORE UPDATE ON orders
FOR EACH ROW
BEGIN
  DECLARE v_contact VARCHAR(20);
  DECLARE v_street  VARCHAR(100);
  DECLARE v_zone    INT;
  DECLARE v_fee     DECIMAL(10,2);

  IF NEW.order_type <> OLD.order_type THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order type cannot be changed. Create a new order instead.';
  END IF;

  IF NEW.customer_id <> OLD.customer_id THEN
    SELECT contact_number, street, zone_id INTO v_contact, v_street, v_zone
      FROM customers WHERE customer_id = NEW.customer_id;
    IF NEW.order_type = 'Delivery' THEN
      IF v_contact IS NULL OR v_contact = '' OR v_street IS NULL OR v_street = '' OR v_zone IS NULL THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Delivery orders need the customer''s contact number, street and barangay.';
      END IF;
      SELECT delivery_fee INTO v_fee FROM delivery_zones WHERE zone_id = v_zone;
      SET NEW.delivery_fee_charged = v_fee;
    END IF;
  END IF;
END$$

-- 3.2 ORDER ITEMS ------------------------------------------------------
-- Rule 5: the price is always copied from PRODUCTS at the time of ordering.
CREATE TRIGGER trg_items_bi BEFORE INSERT ON order_items
FOR EACH ROW
BEGIN
  DECLARE v_price DECIMAL(10,2);
  DECLARE v_gone  TINYINT;
  DECLARE v_status VARCHAR(30);

  SELECT current_unit_price, is_deleted INTO v_price, v_gone
    FROM products WHERE product_code = NEW.product_code;
  IF v_price IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Product does not exist.';
  END IF;
  IF v_gone = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This product is archived and cannot be ordered.';
  END IF;
  SELECT order_status INTO v_status FROM orders WHERE order_no = NEW.order_no;
  IF v_status NOT IN ('Received','Preparing') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Items can only be added while the order is Received or Preparing.';
  END IF;
  SET NEW.unit_price_charged = v_price;
END$$

CREATE TRIGGER trg_items_bu BEFORE UPDATE ON order_items
FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(30);
  DECLARE v_left   INT;
  DECLARE v_price  DECIMAL(10,2);

  SELECT order_status INTO v_status FROM orders WHERE order_no = OLD.order_no;
  IF v_status NOT IN ('Received','Preparing') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Items can only be changed while the order is Received or Preparing.';
  END IF;

  -- Rule 3: an order must keep at least one item.
  IF NEW.is_removed = 1 AND OLD.is_removed = 0 THEN
    SELECT COUNT(*) INTO v_left FROM order_items
     WHERE order_no = OLD.order_no AND is_removed = 0 AND product_code <> OLD.product_code;
    IF v_left = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'An order must contain at least one item.';
    END IF;
  END IF;

  -- Re-adding a removed line uses today's price.
  IF NEW.is_removed = 0 AND OLD.is_removed = 1 THEN
    SELECT current_unit_price INTO v_price FROM products WHERE product_code = NEW.product_code;
    SET NEW.unit_price_charged = v_price;
  END IF;
END$$

-- 3.3 PAYMENTS ---------------------------------------------------------
-- Rejects payments for missing/archived/cancelled orders and overpayments.
CREATE TRIGGER trg_payments_bi BEFORE INSERT ON payments
FOR EACH ROW
BEGIN
  DECLARE v_total  DECIMAL(10,2);
  DECLARE v_paid   DECIMAL(10,2);
  DECLARE v_status VARCHAR(30);
  DECLARE v_gone   TINYINT;

  SELECT order_total, total_paid, order_status, is_deleted
    INTO v_total, v_paid, v_status, v_gone
    FROM v_order_summary WHERE order_no = NEW.order_no;

  IF v_total IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order does not exist.';
  END IF;
  IF v_gone = 1 OR v_status = 'Cancelled' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payments cannot be recorded for a cancelled or archived order.';
  END IF;
  IF NEW.amount_paid > (v_total - v_paid) + 0.001 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment is more than the remaining balance.';
  END IF;
END$$

-- Records a payment and generates the unique receipt number (Rules 12, 15).
CREATE PROCEDURE sp_record_payment(
  IN  p_order_no   INT,
  IN  p_amount     DECIMAL(10,2),
  IN  p_mode       VARCHAR(20),
  OUT p_receipt_no VARCHAR(30))
BEGIN
  DECLARE v_year INT;
  DECLARE v_seq  INT;

  IF p_amount IS NULL OR p_amount <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount must be greater than zero.';
  END IF;

  SET v_year = YEAR(NOW());
  INSERT INTO receipt_seq (seq_year, last_no) VALUES (v_year, LAST_INSERT_ID(1))
    ON DUPLICATE KEY UPDATE last_no = LAST_INSERT_ID(last_no + 1);
  SET v_seq = LAST_INSERT_ID();
  SET p_receipt_no = CONCAT('RCPT-', v_year, '-', LPAD(v_seq, 4, '0'));

  INSERT INTO payments (order_no, receipt_no, amount_paid, payment_mode)
  VALUES (p_order_no, p_receipt_no, p_amount, p_mode);
END$$

-- 3.4 DELIVERIES -------------------------------------------------------
CREATE TRIGGER trg_deliveries_bi BEFORE INSERT ON deliveries
FOR EACH ROW
BEGIN
  DECLARE v_type   VARCHAR(20);
  DECLARE v_status VARCHAR(30);
  DECLARE v_gone   TINYINT;
  DECLARE v_rgone  TINYINT;

  IF NEW.delivery_status = 'Dispatched' THEN
    SELECT order_type, order_status, is_deleted INTO v_type, v_status, v_gone
      FROM orders WHERE order_no = NEW.order_no;
    SELECT is_deleted INTO v_rgone FROM riders WHERE rider_id = NEW.rider_id;

    IF v_type <> 'Delivery' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only Delivery orders can be dispatched.';
    END IF;
    IF v_gone = 1 OR v_status IN ('Cancelled','Delivered') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This order can no longer be dispatched.';
    END IF;
    IF v_rgone = 1 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This rider is archived.';
    END IF;
    IF EXISTS (SELECT 1 FROM deliveries WHERE order_no = NEW.order_no AND delivery_status = 'Dispatched') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This order is already out for delivery.';
    END IF;
  END IF;
END$$

-- Keeps the order status in step with its delivery record.
CREATE TRIGGER trg_deliveries_ai AFTER INSERT ON deliveries
FOR EACH ROW
BEGIN
  UPDATE orders SET order_status = CASE NEW.delivery_status
      WHEN 'Dispatched' THEN 'Out for Delivery'
      WHEN 'Delivered'  THEN 'Delivered'
      ELSE 'Delivery Unsuccessful' END
   WHERE order_no = NEW.order_no;
END$$

CREATE TRIGGER trg_deliveries_bu BEFORE UPDATE ON deliveries
FOR EACH ROW
BEGIN
  IF OLD.delivery_status <> 'Dispatched' AND NEW.delivery_status <> OLD.delivery_status THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A finished delivery cannot be changed.';
  END IF;
  IF OLD.delivery_status = 'Dispatched' AND NEW.delivery_status <> 'Dispatched'
     AND NEW.completion_time IS NULL THEN
    SET NEW.completion_time = NOW();
  END IF;
END$$

CREATE TRIGGER trg_deliveries_au AFTER UPDATE ON deliveries
FOR EACH ROW
BEGIN
  IF NEW.delivery_status <> OLD.delivery_status THEN
    UPDATE orders SET order_status = CASE NEW.delivery_status
        WHEN 'Delivered' THEN 'Delivered'
        ELSE 'Delivery Unsuccessful' END
     WHERE order_no = NEW.order_no;
  END IF;
END$$

-- 3.5 SOFT DELETE / RESTORE --------------------------------------------
-- One procedure for every "Delete" and "Restore" button in the app.
--   p_entity: customers | products | orders | riders | zones | containers
--   p_flag  : 1 = archive (soft delete)   0 = restore
CREATE PROCEDURE sp_set_deleted(IN p_entity VARCHAR(20), IN p_id VARCHAR(30), IN p_flag TINYINT)
BEGIN
  DECLARE v_stamp DATETIME;
  DECLARE v_n INT DEFAULT 0;
  SET v_stamp = IF(p_flag = 1, NOW(), NULL);

  IF p_entity = 'customers' THEN
    IF p_flag = 1 THEN
      IF EXISTS (SELECT 1 FROM customers WHERE customer_id = p_id AND is_system = 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The built-in Walk-in Customer cannot be archived.';
      END IF;
      IF EXISTS (SELECT 1 FROM orders WHERE customer_id = p_id AND is_deleted = 0
                 AND order_status NOT IN ('Released','Delivered','Delivery Unsuccessful','Cancelled')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This customer still has an active order.';
      END IF;
      IF EXISTS (SELECT 1 FROM container_loans WHERE customer_id = p_id AND is_deleted = 0 AND qty > qty_returned) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This customer still holds borrowed containers.';
      END IF;
    END IF;
    UPDATE customers SET is_deleted = p_flag, deleted_at = v_stamp WHERE customer_id = p_id;

  ELSEIF p_entity = 'products' THEN
    UPDATE products SET is_deleted = p_flag, deleted_at = v_stamp WHERE product_code = p_id;

  ELSEIF p_entity = 'orders' THEN
    IF p_flag = 1 AND EXISTS (SELECT 1 FROM orders WHERE order_no = p_id
         AND order_status NOT IN ('Released','Delivered','Delivery Unsuccessful','Cancelled')) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cancel or complete this order before archiving it.';
    END IF;
    IF p_flag = 0 AND EXISTS (SELECT 1 FROM orders o JOIN customers c ON c.customer_id = o.customer_id
                              WHERE o.order_no = p_id AND c.is_deleted = 1) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Restore this order''s customer first.';
    END IF;
    UPDATE orders SET is_deleted = p_flag, deleted_at = v_stamp WHERE order_no = p_id;

  ELSEIF p_entity = 'riders' THEN
    IF p_flag = 1 AND EXISTS (SELECT 1 FROM deliveries WHERE rider_id = p_id AND delivery_status = 'Dispatched') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This rider is currently out on a delivery.';
    END IF;
    UPDATE riders SET is_deleted = p_flag, deleted_at = v_stamp WHERE rider_id = p_id;

  ELSEIF p_entity = 'zones' THEN
    UPDATE delivery_zones SET is_deleted = p_flag, deleted_at = v_stamp WHERE zone_id = p_id;

  ELSEIF p_entity = 'containers' THEN
    UPDATE container_loans SET is_deleted = p_flag, deleted_at = v_stamp WHERE loan_id = p_id;

  ELSE
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown record type.';
  END IF;
END$$

-- 3.6 BLOCK HARD DELETES (the database enforces "no deleting") -----------
CREATE TRIGGER trg_customers_bd  BEFORE DELETE ON customers  FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Records cannot be deleted. Use soft delete (archive) instead.'$$
CREATE TRIGGER trg_products_bd   BEFORE DELETE ON products   FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Records cannot be deleted. Use soft delete (archive) instead.'$$
CREATE TRIGGER trg_orders_bd     BEFORE DELETE ON orders     FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Records cannot be deleted. Use soft delete (archive) instead.'$$
CREATE TRIGGER trg_items_bd      BEFORE DELETE ON order_items FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Records cannot be deleted. Use soft delete (archive) instead.'$$
CREATE TRIGGER trg_payments_bd   BEFORE DELETE ON payments   FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payments are permanent records and cannot be deleted.'$$
CREATE TRIGGER trg_stubs_bd      BEFORE DELETE ON claim_stubs FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Records cannot be deleted. Use soft delete (archive) instead.'$$
CREATE TRIGGER trg_deliveries_bd BEFORE DELETE ON deliveries FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Records cannot be deleted. Use soft delete (archive) instead.'$$
CREATE TRIGGER trg_riders_bd     BEFORE DELETE ON riders     FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Records cannot be deleted. Use soft delete (archive) instead.'$$
CREATE TRIGGER trg_zones_bd      BEFORE DELETE ON delivery_zones FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Records cannot be deleted. Use soft delete (archive) instead.'$$
CREATE TRIGGER trg_loans_bd      BEFORE DELETE ON container_loans FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Records cannot be deleted. Use soft delete (archive) instead.'$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 4. SAMPLE DATA  (based on the Week 2 sample data - see fixes below)
--    FIX 1: Week 2 Delivery #4 used rider_id 3, but only 2 riders existed
--           (foreign key would fail)  ->  rider 3 added.
--    FIX 2: Week 2 Payment #2 paid 80.00 for order 1002 whose total is
--           65.00 (overpayment)       ->  changed to 65.00.
--    Orders are inserted as 'Received' and moved to their final status at
--    the end, because items cannot be added to finished orders.
-- ---------------------------------------------------------------------

INSERT INTO delivery_zones (zone_id, barangay, delivery_fee) VALUES
 (1,'Rawis',20.00),(2,'Bitano',20.00),(3,'Imalnod',30.00),
 (4,'Taysan',30.00),(5,'Homapon',25.00),(6,'Bogtong',25.00);

INSERT INTO riders (rider_id, rider_name, vehicle) VALUES
 (1,'Jun Dela Cruz','Motorcycle'),
 (2,'Carlo Nepomuceno','Motorcycle'),
 (3,'Rey Alcantara','Tricycle');

INSERT INTO customers (customer_id, full_name, contact_number, street, zone_id, is_system) VALUES
 (1,'Maria Teresa Belmonte','09171234501','Purok 3, Rizal St.',1,0),
 (2,'Jose Antonio Ramirez','09281234502','Purok 1, Mabini St.',2,0),
 (3,'Liza Marie Gonzales','09391234503','Purok 5, Magallanes St.',3,0),
 (4,'Ramon Aquino','09171234504','Purok 2, Aquino Subd.',4,0),
 (5,'Cristina Mendoza','09501234505','Purok 4, Homapon Rd.',5,0),
 (6,'Dennis Villanueva','09181234506','Purok 6, Bogtong Rd.',6,0),
 (7,'Rosalie Bernal','09981234507','Purok 2, Rizal St.',1,0),
 (8,'Angelo Fernandez','09451234508','Purok 3, Bitano Rd.',2,0),
 (9,'Walk-in Customer',NULL,NULL,NULL,1);

INSERT INTO products (product_code, product_name, container_type, service_type, current_unit_price) VALUES
 ('SLM-RF','Slim Gallon Refill','Slim','Refill Only',25.00),
 ('RND-RF','Round Gallon Refill','Round','Refill Only',25.00),
 ('BTL-RF','Small Bottle Refill (1 L)','Small Bottle','Refill Only',5.00),
 ('SLM-CON','Empty Slim Gallon','Slim','Buy Container',180.00),
 ('SLM-NEW','Slim Gallon with Water (New Container)','Slim','Refill + New Container',225.00),
 ('RND-NEW','Round Gallon with Water (New Container)','Round','Refill + New Container',200.00);

-- Past orders (Nov 10-12) --------------------------------------------------
INSERT INTO orders (order_no, customer_id, order_type, order_date, queue_date, queue_no) VALUES
 (1001,1,'Delivery','2026-11-10 08:15:00','2026-11-10',1),
 (1002,2,'Walk-in', '2026-11-10 09:40:00','2026-11-10',2),
 (1003,3,'Delivery','2026-11-10 14:05:00','2026-11-10',3),
 (1004,4,'Walk-in', '2026-11-11 08:30:00','2026-11-11',1),
 (1005,5,'Delivery','2026-11-11 10:20:00','2026-11-11',2),
 (1006,6,'Delivery','2026-11-11 15:45:00','2026-11-11',3),
 (1007,7,'Walk-in', '2026-11-12 07:50:00','2026-11-12',1),
 (1008,8,'Delivery','2026-11-12 11:10:00','2026-11-12',2);

-- Today's orders (dates are relative so the dashboard always has live data)
INSERT INTO orders (order_no, customer_id, order_type, order_date, queue_date, queue_no, notes) VALUES
 (1009,3,'Delivery', NOW() - INTERVAL 90 MINUTE, CURDATE(),1,'Gate is blue, ring twice'),
 (1010,9,'Walk-in',  NOW() - INTERVAL 60 MINUTE, CURDATE(),2,NULL),
 (1011,6,'Delivery', NOW() - INTERVAL 25 MINUTE, CURDATE(),3,NULL),
 (1012,9,'Walk-in',  NOW() - INTERVAL 5 MINUTE,  CURDATE(),4,NULL);

INSERT INTO order_items (order_no, product_code, qty, unit_price_charged) VALUES
 (1001,'SLM-RF',4,0),
 (1002,'RND-RF',2,0),(1002,'BTL-RF',3,0),
 (1003,'SLM-NEW',1,0),(1003,'SLM-RF',2,0),
 (1004,'RND-NEW',1,0),
 (1005,'RND-RF',5,0),
 (1006,'SLM-RF',3,0),(1006,'BTL-RF',2,0),
 (1007,'SLM-RF',2,0),
 (1008,'SLM-RF',6,0),(1008,'RND-NEW',1,0),
 (1009,'SLM-RF',3,0),(1009,'RND-RF',1,0),
 (1010,'SLM-RF',2,0),
 (1011,'SLM-NEW',1,0),
 (1012,'BTL-RF',4,0),(1012,'RND-RF',1,0);

INSERT INTO payments (order_no, receipt_no, payment_date, amount_paid, payment_mode) VALUES
 (1001,'RCPT-2026-0001','2026-11-10 09:05:00',100.00,'Cash'),
 (1002,'RCPT-2026-0002','2026-11-10 09:50:00', 65.00,'Cash'),
 (1003,'RCPT-2026-0003','2026-11-10 14:10:00',275.00,'GCash'),
 (1004,'RCPT-2026-0004','2026-11-11 08:40:00',200.00,'Cash'),
 (1005,'RCPT-2026-0005','2026-11-11 11:00:00',125.00,'Cash'),
 (1006,'RCPT-2026-0006','2026-11-11 15:50:00', 95.00,'GCash'),
 (1007,'RCPT-2026-0007','2026-11-12 08:00:00', 50.00,'Cash'),
 (1008,'RCPT-2026-0008','2026-11-12 11:15:00',350.00,'GCash'),
 (1010,'RCPT-2026-0009', NOW() - INTERVAL 58 MINUTE, 50.00,'Cash'),
 (1012,'RCPT-2026-0010', NOW() - INTERVAL 4 MINUTE,  25.00,'GCash');
INSERT INTO receipt_seq (seq_year, last_no) VALUES (2026, 10);

-- Deliveries (these triggers also set the order status) --------------------
INSERT INTO deliveries (order_no, rider_id, delivery_status, dispatch_time, completion_time) VALUES
 (1001,1,'Delivered','2026-11-10 08:40:00','2026-11-10 09:05:00'),
 (1003,2,'Delivered','2026-11-10 14:20:00','2026-11-10 14:45:00'),
 (1005,1,'Delivered','2026-11-11 10:35:00','2026-11-11 11:00:00'),
 (1006,3,'Delivered','2026-11-11 16:00:00','2026-11-11 16:25:00'),
 (1008,2,'Delivered','2026-11-12 11:30:00','2026-11-12 11:55:00');
INSERT INTO deliveries (order_no, rider_id, delivery_status) VALUES (1009,1,'Dispatched');

-- Final statuses ---------------------------------------------------------
UPDATE orders SET order_status='Released' WHERE order_no IN (1002,1004,1007);
UPDATE orders SET order_status='Preparing' WHERE order_no = 1010;
UPDATE orders SET order_status='To be Delivered' WHERE order_no = 1011;
-- 1009 is 'Out for Delivery' (set by trigger), 1012 stays 'Received'

-- Claim-stub print times in the Week 2 sample
UPDATE claim_stubs cs JOIN orders o ON o.order_no = cs.order_no
   SET cs.issued_date = o.order_date + INTERVAL 5 MINUTE
 WHERE cs.order_no <= 1008;

INSERT INTO container_loans (customer_id, container_type, qty, qty_returned, date_lent, notes) VALUES
 (6,'Slim',2,0,'2026-11-11 15:45:00','Lent while waiting for his own gallons'),
 (7,'Round',1,0,'2026-11-12 07:50:00',NULL),
 (2,'Slim',2,2,'2026-11-05 10:00:00','Returned complete');
UPDATE container_loans SET last_returned_at='2026-11-09 16:30:00' WHERE customer_id=2;
