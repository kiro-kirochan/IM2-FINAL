-- =====================================================================
--  ClearDrop - SQL queries, view, stored procedure, trigger demos and
--  test cases for the Week 3 screenshots and the Week 4 defense.
--  Run AFTER importing cleardrop.sql.  Run one block at a time in
--  phpMyAdmin (SQL tab) or MySQL Workbench and screenshot each output.
-- =====================================================================
USE cleardrop_db;

-- ---------------------------------------------------------------------
-- A. FIVE REQUIRED QUERIES
-- ---------------------------------------------------------------------

-- Q1. All customers in alphabetical order (archived ones hidden)
SELECT c.customer_id, c.full_name, c.contact_number, c.street, z.barangay
FROM customers c
LEFT JOIN delivery_zones z ON z.zone_id = c.zone_id
WHERE c.is_deleted = 0
ORDER BY c.full_name;

-- Q2. Orders not yet finished, with customer name and status
SELECT o.order_no, o.queue_no, c.full_name, o.order_type, o.order_status
FROM orders o
JOIN customers c ON c.customer_id = o.customer_id
WHERE o.is_deleted = 0
  AND o.order_status NOT IN ('Released','Delivered','Delivery Unsuccessful','Cancelled')
ORDER BY o.order_date;

-- Q3. Total payments collected per day (aggregate + GROUP BY)
SELECT DATE(payment_date) AS sales_date,
       COUNT(*)           AS no_of_payments,
       SUM(amount_paid)   AS total_collected
FROM payments
GROUP BY DATE(payment_date)
ORDER BY sales_date DESC;

-- Q4. Unpaid or partially paid orders with their remaining balance
SELECT order_no, customer_name, order_total, total_paid, balance, payment_status
FROM v_order_summary
WHERE balance > 0 AND order_status <> 'Cancelled' AND is_deleted = 0
ORDER BY balance DESC;

-- Q5. Units sold and sales amount per product (uses the price charged at order time)
SELECT p.product_code, p.product_name,
       SUM(oi.qty)                        AS units_sold,
       SUM(oi.qty * oi.unit_price_charged) AS sales_amount
FROM order_items oi
JOIN products p ON p.product_code = oi.product_code
JOIN orders o   ON o.order_no = oi.order_no
WHERE oi.is_removed = 0 AND o.is_deleted = 0 AND o.order_status <> 'Cancelled'
GROUP BY p.product_code, p.product_name
ORDER BY sales_amount DESC;

-- ---------------------------------------------------------------------
-- B. VIEW  (v_order_summary, v_daily_sales)
-- ---------------------------------------------------------------------
SELECT * FROM v_order_summary ORDER BY order_no;
SELECT * FROM v_daily_sales   ORDER BY sales_date DESC;

-- ---------------------------------------------------------------------
-- C. STORED PROCEDURE  (sp_record_payment)
--    Order 1009 owes 130.00.  Pay 100 -> balance should become 30.00.
-- ---------------------------------------------------------------------
SELECT order_no, order_total, total_paid, balance FROM v_order_summary WHERE order_no = 1009;
CALL sp_record_payment(1009, 100.00, 'Cash', @receipt);
SELECT @receipt AS new_receipt_no;
SELECT order_no, order_total, total_paid, balance FROM v_order_summary WHERE order_no = 1009;

-- ---------------------------------------------------------------------
-- D. TRIGGER DEMOS  (each of these should FAIL with a clear message)
-- ---------------------------------------------------------------------

-- D1. trg_payments_bi : overpayment is rejected
CALL sp_record_payment(1009, 999.00, 'Cash', @r);

-- D2. trg_orders_bi : delivery order for a customer with no address (Walk-in Customer = id 9)
INSERT INTO orders (customer_id, order_type) VALUES (9, 'Delivery');

-- D3. trg_*_bd : hard deletes are blocked
DELETE FROM orders WHERE order_no = 1001;

-- D4. trg_orders_bi : queue number and claim stub are generated automatically
INSERT INTO orders (customer_id, order_type) VALUES (9, 'Walk-in');
SELECT o.order_no, o.queue_date, o.queue_no, o.delivery_fee_charged, cs.stub_no
FROM orders o JOIN claim_stubs cs ON cs.order_no = o.order_no
ORDER BY o.order_no DESC LIMIT 1;

-- D5. trg_items_bi : price is copied from PRODUCTS (we send 0, DB stores 25.00)
INSERT INTO order_items (order_no, product_code, qty, unit_price_charged)
VALUES (LAST_INSERT_ID(), 'SLM-RF', 2, 0);
SELECT * FROM order_items ORDER BY order_no DESC LIMIT 1;

-- D6. Delivery trigger : dispatching a delivery sets the order status
INSERT INTO deliveries (order_no, rider_id) VALUES (1011, 2);
SELECT order_no, order_status FROM orders WHERE order_no = 1011;   -- Out for Delivery

-- ---------------------------------------------------------------------
-- E. SOFT DELETE  (sp_set_deleted)
-- ---------------------------------------------------------------------
CALL sp_set_deleted('products', 'BTL-RF', 1);                      -- archive
SELECT product_code, is_deleted, deleted_at FROM products WHERE product_code = 'BTL-RF';
CALL sp_set_deleted('products', 'BTL-RF', 0);                      -- restore
CALL sp_set_deleted('orders', '1012', 1);                          -- fails: order still active

-- ---------------------------------------------------------------------
-- F. SAMPLE TEST CASES (record Actual Result / Pass-Fail in your document)
-- ---------------------------------------------------------------------
-- TC01 create customer ......... INSERT INTO customers (full_name, contact_number, street, zone_id) VALUES ('Test User','09170000000','Purok 1',1);
-- TC02 order for customer ...... see D4
-- TC03 non-existent customer ... INSERT INTO orders (customer_id, order_type) VALUES (9999,'Walk-in');   -- FK error
-- TC04 payment of 100 .......... see section C  (balance drops by 100)
-- TC05 daily sales matches ..... compare Q3 with: SELECT SUM(amount_paid) FROM payments WHERE DATE(payment_date)=CURDATE();
-- TC06 status Ready for Pickup . UPDATE orders SET order_status='Ready for Pickup' WHERE order_no=1012;
-- TC07 invalid status for type . UPDATE orders SET order_status='Delivered' WHERE order_no=1012;       -- CHECK error (walk-in)
-- TC08 zero quantity ........... INSERT INTO order_items VALUES (1012,'SLM-CON',0,0,0);                -- CHECK error
