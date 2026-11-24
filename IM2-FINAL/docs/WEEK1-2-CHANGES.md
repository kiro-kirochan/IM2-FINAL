# What changed from the Week 1 / Week 2 documents (copy into your write-up)

## Problems found in the Week 2 sample data (and fixed in `cleardrop.sql`)
1. **Delivery #4 used rider_id 3**, but only riders 1 and 2 existed, so the foreign key would fail → rider 3 (Rey Alcantara) added.
2. **Payment #2 paid ₱80.00 for order 1002**, whose total is ₱65.00, so the remaining balance (Rule 10) would be negative → set to ₱65.00.
3. The old front-end stored data only in the browser (`localStorage`); it is now a PHP + MySQL system.

## New / changed tables and columns
| Change | Why |
|---|---|
| `orders.delivery_fee_charged` | Snapshot of the barangay fee, like `unit_price_charged` (Rule 5), so changing a fee never changes old orders |
| `order_items.is_removed` | Items cannot be deleted; removing a line while editing marks it removed |
| `is_deleted`, `deleted_at` on customers, products, orders, riders, delivery_zones, container_loans | Soft delete |
| `customers.is_system` | Marks the built-in "Walk-in Customer" (Rule 2: walk-ins stay anonymous) |
| `container_loans` (new table) | Week 1 problem: nobody tracks which customer holds which gallon |
| `receipt_seq` (new table) | Safe running receipt numbers (RCPT-2026-0001…) |

## New features added to the original counter app
* Separate page per feature (add / edit / view / pay / status) instead of one screen
* Delivery Board: dispatch to rider, mark delivered/failed, cash-on-delivery balance
* Borrowed-container tracking (lend / return)
* Reports: daily sales (cash vs GCash), product sales, unpaid balances, rider trips
* Soft-delete Archive with Restore
* Validation: PH mobile number format, delivery needs contact + street + barangay, no overpayment, order keeps ≥ 1 item

## Business rules enforced by the database (not just the interface)
Rule 3 (≥1 item) · 4 and 11 (foreign keys) · 5 (price snapshot) · 6 (qty > 0) · 7 (valid status per order type) · 8 (delivery needs address) ·
9 and 10 (total and balance computed in view `v_order_summary`) · 12 (unique receipt) · 13 (queue date + number unique, auto-assigned) ·
15 (receipt shows balance after payment) · 16 (claim stub says "not proof of payment").
Extras we added: overpayment is rejected, and "no deleting" (delete triggers + soft delete).
Note on Rule 2: the built-in *Walk-in Customer* has no contact/address; any customer used for a Delivery order must have all three (trigger `trg_orders_bi`).
