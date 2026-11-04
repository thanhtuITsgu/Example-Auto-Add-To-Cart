# TuVo_AutoAddToCart — Magento 2 checkout links

A small Magento 2 module that fills the cart from a URL and lands the shopper on checkout:

```
https://example.com/checkout/?products=SKU-RED-M:2,SKU-BLUE-L:1&cart_origin=meta_shops
```

Out of the box, Magento ignores the `products` parameter and redirects an empty quote back to an empty cart page. That breaks **Meta / Facebook & Instagram Shops "checkout on website"** and any other channel that sends shoppers to a pre-filled checkout link: ads, email campaigns, influencer links.

## What it does

1. Reads `products=SKU:qty,SKU:qty` from `/checkout/` (the parameter name is configurable).
2. Loads each product by SKU and adds it to the visitor's cart with the requested quantity.
3. Redirects (302) to a clean `/checkout/` URL with the items in the cart.
4. Handles bad input gracefully:
   - unknown, disabled, other-website or out-of-stock SKUs are **skipped** and the rest are still added
   - if nothing is valid, the shopper goes to the **homepage**, never an error page
   - qty and item count are capped, so a crafted link cannot flood the cart
5. **Configurable products:** when the link contains a child (variant) SKU, the item is added through its configurable parent with the correct options, so the cart shows the right name, options and price.
6. Requests **without** the parameter go straight to the core controller, so the normal cart/checkout flow is unchanged.

No core files are modified. It is implemented as a single `around` plugin on `Magento\Checkout\Controller\Index\Index`.

## Compatibility

- Magento Open Source / Adobe Commerce **2.3.x and 2.4.x**
- PHP **7.2+** (no PHP 8-only syntax)

## Installation

**Manual (app/code):**

```bash
mkdir -p app/code/TuVo/AutoAddToCart
# copy the repository contents into app/code/TuVo/AutoAddToCart
bin/magento module:enable TuVo_AutoAddToCart
bin/magento setup:upgrade
bin/magento setup:di:compile        # production mode only
bin/magento cache:flush
```

**Composer:**

```bash
composer config repositories.tuvo-auto-add-to-cart vcs https://github.com/thanhtuITsgu/Example-Auto-Add-To-Cart
composer require tuvo/module-auto-add-to-cart:dev-main
bin/magento setup:upgrade && bin/magento cache:flush
```

## Configuration

**Stores → Configuration → Sales → Auto Add To Cart Links**

| Setting | Default | Description |
|---|---|---|
| Enabled | Yes | Turn the link handling on or off per store view |
| Query Parameter | `products` | URL parameter that holds `SKU:qty` pairs |

## Link format

| Example | Result |
|---|---|
| `?products=SKU1:2` | 2 × SKU1 |
| `?products=SKU1,SKU2:3` | qty defaults to 1 → 1 × SKU1, 3 × SKU2 |
| `?products=SKU1:1,SKU1:2` | duplicates merged → 3 × SKU1 |
| `?products=UNKNOWN:1,SKU2:1` | UNKNOWN skipped, SKU2 added |
| `?products=UNKNOWN:1` | nothing valid → homepage |

Skipped SKUs are written to `var/log/system.log` so catalog mismatches (e.g. Meta retailer ID ≠ Magento SKU) are easy to spot.

## Uninstall

```bash
bin/magento module:disable TuVo_AutoAddToCart
rm -rf app/code/TuVo/AutoAddToCart    # or: composer remove tuvo/module-auto-add-to-cart
bin/magento setup:upgrade && bin/magento cache:flush
```

The module adds no database tables, so nothing else needs cleaning up.

## Tests

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/TuVo/AutoAddToCart/Test/Unit
```

## Structure

```
Plugin/Checkout/IndexPlugin.php   entry point: intercepts /checkout/?products=...
Model/LinkParser.php              "SKU:qty,..." → [sku => qty], validation and caps
Model/CartFiller.php              product lookup, stock check, configurable parent resolution, quote save
Model/Config.php                  admin settings
etc/frontend/di.xml               plugin declaration (frontend area only)
etc/adminhtml/system.xml          admin configuration
Test/Unit/                        unit tests
```

## License

MIT
