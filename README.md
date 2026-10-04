# SilexCore Technologies — E-Commerce Platform

A full-stack e-commerce web application for a conductive-materials brand, built from
scratch: custom storefront, hosted payment checkout, real-time shipping rates and label
purchasing, a customer accounts system, product reviews, and a full admin dashboard.

> **Live production site:** [silexcore.com](https://silexcore.com/). This repository is the source code, published as a portfolio
> reference for a project built by a team of six engineers. Secrets and deployment credentials are intentionally excluded (see
> [Configuration](#configuration)).

---

## Highlights

- **Custom storefront** — responsive catalog, product pages with per-variant image
  galleries, cart, and a multi-step checkout. No framework: hand-written HTML/CSS and
  vanilla JavaScript, theme-aware and mobile-first.
- **Payments** — Zoho Payments hosted checkout with a **self-healing OAuth token layer**
  (auto-refreshes and auto-invalidates the cache when the refresh token changes) and a
  webhook that acknowledges immediately to avoid provider timeouts.
- **Shipping** — live USPS/UPS/FedEx rates and one-click label purchasing via the EasyPost
  API, with server-side money-safety guards so a charged label is never lost to a
  transient DB or network error.
- **Accounts** — registration, login, password reset, saved addresses (with Geoapify
  address autocomplete), and per-user order history.
- **Admin dashboard** — order management, packing-slip PDF generation (jsPDF, with proper
  pagination), shipping-label workflow, customer-notes visibility, and transactional
  email resend tools.
- **Reviews** — verified-purchase product reviews with optional photo upload.
- **Email** — invoice, order-notification, and tracking emails, with an explicit second
  copy to the company inbox (shared hosting silently drops BCC headers).

## Tech stack

| Layer      | Technology                                                        |
|------------|-------------------------------------------------------------------|
| Backend    | PHP 8.1, PDO / MySQL (prepared statements throughout)             |
| Frontend   | Vanilla JavaScript, hand-written CSS (no build step)              |
| Payments   | Zoho Payments (hosted checkout + webhook, OAuth 2.0)             |
| Shipping   | EasyPost API (rates + label purchase)                            |
| Address    | Geoapify autocomplete · Google reCAPTCHA v2                       |
| PDF        | jsPDF (client-side packing slips)                                |
| Hosting    | DirectAdmin shared hosting, MySQL, SMTP                           |

## Project structure

```
/                     Storefront pages (index, shop, product-*, cart/checkout, account…)
/assets               CSS, JavaScript, images, product media
/api                  Backend endpoints (auth, cart, orders, payments, shipping, reviews, email)
admin.php             Admin dashboard (auth-gated)
db_*.sql              Schema migrations (schema only — no data)
```

## Configuration

All secrets live in `api/config.php`, which is **git-ignored**. To run locally:

```bash
cp api/config.php.example api/config.php
# then fill in real values: DB, Zoho, EasyPost, SMTP
```

Then create the database schema by running the `db_*.sql` files (start with
`db_setup.sql`) against a MySQL database.

## Security notes

- No credentials are committed. `api/config.php` and deploy config are git-ignored;
  `config.php.example` documents the required keys with placeholder values.
- All database access uses **parameterized queries** (PDO prepared statements).
- Admin endpoints are gated server-side by session checks; passwords are hashed.
- The included `.sql` files contain **schema only** — no customer data.

---

*Developed by a six-person engineering team at SilexCore Technologies. Published with permission for portfolio purposes.*
