=== PB Afiliados ===
Contributors: martins56
Tags: woocommerce, affiliates, pagbank, commissions, referral program
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCommerce affiliate program with PagBank: referral links, flexible commissions, manual or split payouts, reports, and affiliate dashboards.

== Description ==

**PB Affiliates** (PB Afiliados) is built for stores that want referral revenue without spreadsheets or duct tape. You set the rules; the plugin tracks clicks, attributes orders, calculates commissions, and gives each affiliate a clear hub inside their account — while you keep full control in WordPress.

= Why choose this plugin? =

* **Built for Brazil** — Deep **PagBank Connect** integration (split payouts or manual settlement).
* **Flexible** — Store-wide, category, per-affiliate, or coupon-based rules; percentage or fixed amounts.
* **Transparent** — Dashboards and history for affiliates and a powerful admin back office.
* **Professional tracking** — Cookie-based attribution, referral codes in URLs, and optional verified referer domains.

= What you get in practice =

* Higher conversion with **one-click referral links** affiliates actually use.
* **Link builder**: paste any product, category, or internal page URL — get a ready-to-share link with the referral code (fewer copy-paste mistakes).
* Optional **zip1.io** short links for campaigns and social posts.
* **Promotional materials**: host downloadable assets in the affiliate area.
* **Charts & date ranges** (7 / 14 / 30 / 90 days) for click and order trends.
* **Advanced admin reporting** including click analytics and per-affiliate performance.
* **Transactional emails** for registration, new commissions, and paid commissions (via WooCommerce).
* **Manual withdrawal workflow** with proofs — or **automatic PagBank split**, depending on store settings.

= Requirements =

* **WooCommerce** must be active  
* **PagBank Connect** must be active with at least one payment method available  
* **HPOS** (High-Performance Order Storage) must be enabled — required at plugin activation

These requirements are intentional: they keep payouts, orders, and gateways aligned in a modern WooCommerce stack.

== Installation ==

1. Upload the `pb-affiliates` folder to `wp-content/plugins/`.
2. Confirm **HPOS** is enabled in WooCommerce and **PagBank Connect** is configured.
3. Activate **PB Affiliates** under Plugins.
4. Configure **Affiliates → Settings** (default commission, cookie duration, payment mode, etc.).
5. (Optional) Assign a terms page and affiliate registration policy.

After activation, affiliates see the program under **My account** (custom endpoints).

== Frequently Asked Questions ==

= Do I need PagBank if I only use manual payouts? =

Yes. The plugin is designed around the PagBank Connect stack; that requirement keeps a single, supportable integration path with your store’s payment methods.

= Does the affiliate install anything? =

No. Everything runs on your store: customer account, cookies for tracking, and frontend scripts designed to work with full-page cache when needed.

= Can I combine commission rules? =

Yes. There is a clear precedence order: affiliate coupon rules and per-user overrides can take priority over per-line category logic and the store default. See the technical section below.

= Does split mode replace manual withdrawals? =

In split mode, payouts follow PagBank / connector rules. Manual withdrawal screens and payment history reflect what the store records — so affiliates who only get paid via split are not misled by empty “manual payment” lists when that behavior is intentional.

== Commission rules (technical reference) ==

Commissions are only calculated for orders attributed to an **active** affiliate when a valid calculation base exists.

* **Calculation base**  
  Derived from order line subtotals per **Affiliates → Settings** (fees may be excluded). Shipping is not part of the item subtotal base. The base never goes negative.

* **Rate precedence (which percentage or fixed amount applies)**  
  1. **Affiliate coupon** — If the order uses a program-linked coupon with its own commission type and value, that rule applies to the **entire order**.  
  2. **Affiliate profile** — If the admin set a custom commission on the user (type + value), that applies to the **entire order**.  
  3. **Otherwise** — **Per line item** mode applies, using the **store default** when no category rule applies to that line.

* **Per-line mode (no coupon / no profile override)**  
  - Total allocatable base is split **proportionally** across line item subtotals.  
  - Each line gets a **percent or fixed** rate on its allocated base.  
  - For variations, the **parent** product drives category rules.  
  - **Product categories:** categories with PB Affiliates rules (under **Products → Categories**) are collected; when multiple categories apply, the rule yielding the **lowest monetary commission** for that line’s base wins (percent vs fixed compared on that base).  
  - If no category rule applies, the **store default** from **Affiliates → Settings** is used.  
  - Final commission is the **sum** of line commissions; mixed rates may be stored as a blended type with an equivalent overall percent for reporting.

* **Where to configure**  
  - **Store default:** Affiliates → Settings.  
  - **Per category:** edit each product category.  
  - **Per affiliate:** user profile in admin (when enabled).  
  - **Per coupon:** affiliate coupon metadata in WooCommerce.

== Screenshots ==

1. Affiliate hub in WooCommerce My Account — metrics, sharing tools, and clarity for partners.

== Changelog ==

= 1.0.0 =
* Initial release.
