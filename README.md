# ClearDrop – Water Refilling Station Ordering & Delivery System

IT116 Information Management 2 · Final Project · PHP + MySQL (XAMPP)

Replaces the paper-and-memory process of a Philippine water refilling station with one system:
walk-in and delivery orders, queue numbers, thermal-style receipts and **unpaid claim stubs**,
partial payments, riders, barangay delivery fees, borrowed-container tracking and daily reports.

## 1. Run it on your PC (XAMPP)

1. Install **XAMPP** (PHP 8.1+). Open the Control Panel and start **Apache** and **MySQL**.
2. Put this folder in `C:\xampp\htdocs\cleardrop` (clone it there, see section 3).
3. Open <http://localhost/phpmyadmin> → **Import** → choose `database/cleardrop.sql` → **Import**.
   (This creates the database `cleardrop_db` with sample data. Re-importing resets your local data.)
4. Open <http://localhost/cleardrop/>.

Default XAMPP login (`root`, no password) works out of the box. If yours differs, copy
`includes/config.local.example.php` to `includes/config.local.php` and edit it (that file is git-ignored,
so nobody's password ever goes to GitHub).

Using MySQL Workbench instead? File → Run SQL Script → `database/cleardrop.sql`.

## 2. What each page does (one page per feature)

| Feature | Pages |
|---|---|
| Dashboard | `index.php` – today's stats, live queue, latest payments |
| Orders | `orders/index.php` list + search/filters · `add.php` · `edit.php` · `view.php` (receipt + claim tab) · `pay.php` · `status.php` |
| Deliveries | `deliveries/index.php` – dispatch to a rider, mark delivered/failed, "collect on arrival" |
| Customers | `customers/` list · `add.php` · `edit.php` · `view.php` (history, balance, borrowed containers) |
| Products | `products/` list · `add.php` · `edit.php` |
| Containers *(new)* | `containers/` – who still holds our gallons · `add.php` (lend) · `return.php` |
| Reports *(new)* | `reports/` – daily sales (cash vs GCash), product sales, unpaid balances, rider trips |
| Riders / Barangay fees | `riders/`, `zones/` – list, add, edit |
| Archive | `trash/` – everything that was "deleted"; **Restore** brings it back |

### Soft delete (no real deleting)
The database does not allow deleting: every table has a `BEFORE DELETE` trigger that rejects it.
The red **Archive** button sets `is_deleted = 1` and `deleted_at` through the stored procedure
`sp_set_deleted`. The record disappears from the app but stays in MySQL, and can be restored from **Archive**.
Payments are permanent and cannot be archived at all. Archive is refused when it would cause problems
(an order still in progress, a customer still holding containers, a rider currently out on a delivery).

### Database features (for the SQL requirements)
* **Tables**: delivery_zones, riders, customers, products, orders, order_items, payments, claim_stubs, deliveries, container_loans, receipt_seq
* **Views**: `v_order_summary` (total, paid, balance, payment status), `v_daily_sales`
* **Stored procedures**: `sp_record_payment` (receipt number + payment), `sp_set_deleted` (soft delete / restore)
* **Triggers**: price snapshot, queue number, auto claim stub, delivery fee snapshot, delivery-status sync,
  overpayment check, delivery customer must have contact/address, block all hard deletes
* **Queries and tests** to screenshot: `database/queries_and_tests.sql`

## 3. Group workflow on GitHub

**One person (the repo owner), once:**
1. github.com → **New repository** → name `cleardrop` → Create (no README, we already have one).
2. In the project folder:
   ```bash
   git init
   git add .
   git commit -m "Initial commit: ClearDrop system"
   git branch -M main
   git remote add origin https://github.com/<owner>/cleardrop.git
   git push -u origin main
   ```
3. Repo → **Settings → Collaborators → Add people** (add every groupmate).
4. Repo → **Settings → Branches → Add rule** for `main`: *Require a pull request before merging*.
   Now nobody can break `main` by accident.

**Every groupmate, once:**
```bash
cd C:\xampp\htdocs
git clone https://github.com/<owner>/cleardrop.git
```
Then import `database/cleardrop.sql` in phpMyAdmin (step 1.3).

**Every time you work (feature-branch flow):**
```bash
git checkout main
git pull                          # get everyone's latest work
git checkout -b feature/riders-report   # one branch per task
# ... edit files, test in the browser ...
git add .
git commit -m "Add rider performance report"
git push -u origin feature/riders-report
```
Then on GitHub click **Compare & pull request** → write what you changed → a groupmate reviews → **Merge**.
After merging: `git checkout main && git pull`.

**Suggested split (one folder each = almost no merge conflicts):**

| Member | Folders |
|---|---|
| 1 | `orders/`, `assets/js/order-form.js` |
| 2 | `customers/`, `containers/` |
| 3 | `deliveries/`, `riders/`, `zones/` |
| 4 | `products/`, `reports/`, `trash/` |
| Shared (ask before editing) | `includes/`, `assets/css/app.css`, `database/cleardrop.sql` |

**Rules that save the group:**
* **The database is shared by file, not by server.** If you change a table, edit `database/cleardrop.sql`, say so in the pull request, and everyone re-imports after pulling. Never "fix" only your own local database.
* Pull before you start, commit small and often, one branch per task, never commit directly to `main`.
* If Git says *merge conflict*: open the file, keep both people's work between the `<<<<<<<` and `>>>>>>>` markers, delete the markers, `git add` the file, `git commit`. VS Code shows buttons for this.
* Never commit `includes/config.local.php` (already in `.gitignore`).

## 4. Customizing the look
All colors, fonts and shadows are variables at the top of `assets/css/app.css`
(`--aqua`, `--coral`, `--paper`, `--head` …). Change them to give your group's own identity.
