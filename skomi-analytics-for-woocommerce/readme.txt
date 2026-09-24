=== Skomi Analytics for WooCommerce ===
Contributors: skomi
Tags: woocommerce, analytics, ecommerce, revenue, privacy
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: skomi-analytics
WC requires at least: 7.0
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Reports WooCommerce purchases to Skomi with the order's own total, currency and number, so revenue is attributed to the visit that earned it.

== Description ==

An add-on to **Skomi Analytics**. That plugin loads the script; this one gives it
the two events a shop needs.

**What it reports**

* `purchase` on the thank-you page — the order total, its currency and its order
  number.
* `begin_checkout` when the checkout form is shown with something in the cart.
  Not a goal in itself: it is the denominator for the rate most shops are
  actually trying to move.

**Why the order number matters**

A thank-you page is reloaded. Somebody refreshes it, presses back, or opens the
confirmation email a week later and lands on the same URL. Without an order
number each of those is another sale.

This plugin defends twice. It flags the order as reported, so it returns early
ever after — and it sends the order number, which Skomi uses to drop a repeat of
an order it has already counted. The first stops the request; the second stops it
mattering if the flag is ever lost to a migration.

**Currency**

Skomi never converts between currencies. A sale in euros is reported in euros
beside a sale in dollars, and each order's own currency is sent with it. There is
no single cross-currency total, deliberately — that sum has to happen somewhere
that knows which rate on which day.

**What it does not report**

Line items. Skomi keeps at most 25 properties per event and stores them as text,
so an order of ten products does not fit — and there is no product dimension
anywhere in Skomi to put them in. Which product sold is a question for
WooCommerce's own reports, and always will be.

== Installation ==

1. Install and activate **Skomi Analytics**, and put your site ID in it.
2. Install and activate this plugin.

There is nothing to configure. If either the other plugin or WooCommerce is
missing, this one says so on the plugins screen rather than doing nothing
quietly.

== Frequently Asked Questions ==

= Does this work with High-Performance Order Storage? =

Yes. Compatibility is declared, and the order flag is written through the order
object rather than to post meta directly.

= Can I change what is sent? =

Two filters:

* `skomi_wc_order_value` — the amount. Defaults to the order total, which
  includes tax and shipping: what the customer paid.
* `skomi_wc_order_props` — the properties sent alongside it.

= Why is nothing reported on Shopify? =

Different product, and worth knowing if you also run one: Shopify has retired
every hook a script had into its checkout, so no tool measures a purchase there.
A WooCommerce checkout is a page of your own site, which is why this plugin can
exist.

== Changelog ==

= 1.0.0 =
* First release.
