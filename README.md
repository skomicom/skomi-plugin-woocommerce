# Skomi for WooCommerce

Purchases and checkout starts, reported to the script the WordPress plugin loads.

An add-on. It needs [`https://github.com/skomicom/skomi-plugin-wordpress`](skomi-plugin-wordpress) — that plugin loads the
bundle, this one calls `skomi.track()` on it — and it says so on the plugins
screen if either that or WooCommerce is missing, rather than doing nothing
quietly.

There is no settings page. Two events, no configuration.

## The events

| Event | When | Carries |
|---|---|---|
| `purchase` | thank-you page | order total, currency, **order number**, item count, payment method, coupons |
| `begin_checkout` | checkout form shown with a non-empty cart | cart total, currency, item count |

`begin_checkout` is the denominator. The rate from a product page to reaching
checkout is the number most shops are trying to move, and without it the funnel
ends at the cart.

## Firing exactly once

The whole difficulty. A thank-you page is reloaded — a refresh, a back button,
the confirmation email opened a week later — and each is a page view while none
is a second sale.

**Two defences, and both are needed.**

1. **Order meta.** Once reported the order carries `_skomi_tracked` and the
   handler returns early ever after, in any browser.
2. **The order number travels to Skomi.** The server drops a repeat of an order
   id it has already counted. So a reinstall, or meta lost in a migration, still
   does not double a shop's revenue.

The first stops the request being made; the second stops it mattering. Revenue is
the figure nobody re-checks, which is why it gets two.

## What is deliberately not sent

**Line items.** Skomi keeps 25 properties per event, 255 characters per value,
and stores them as text — an order of ten products does not fit. There is also no
product dimension anywhere in Skomi to put them in. Which product sold is a
WooCommerce question.

**A converted total.** Each order is reported in its own currency because Skomi
never converts. There is no single cross-currency figure, deliberately.

## Filters

| Filter | Default |
|---|---|
| `skomi_wc_order_value` | `$order->get_total()` — tax and shipping included, what the customer paid |
| `skomi_wc_order_props` | item count, payment method title, coupon codes |

## HPOS

Compatibility with High-Performance Order Storage is declared, and the flag is
written through the order object rather than to post meta — so it works on both
storage modes. Without the declaration WooCommerce shows a shop a warning telling
them to deactivate the plugin.

## Testing it

`php -l` is the only automated check in this repository. On a real store:

1. Place a test order. Confirm one `purchase` event with the right total,
   currency and order number.
2. **Reload the thank-you page twice.** Confirm no further events — this is the
   one that matters.
3. Load checkout with a full cart; confirm one `begin_checkout`.
4. Turn **Enabled** off in Skomi Analytics; confirm neither event fires.
