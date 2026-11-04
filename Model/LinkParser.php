<?php
declare(strict_types=1);

namespace TuVo\AutoAddToCart\Model;

/**
 * Parses "SKU:qty,SKU:qty" into [sku => qty].
 *
 * - qty is optional and defaults to 1 ("SKU1,SKU2:3")
 * - invalid chunks are ignored, duplicate SKUs are merged
 * - item count and qty are capped so a crafted link cannot flood the cart
 */
class LinkParser
{
    const MAX_ITEMS = 50;
    const MAX_QTY = 100;

    /**
     * @param string $value
     * @return int[] keyed by SKU
     */
    public function parse(string $value): array
    {
        $items = [];

        foreach (explode(',', $value) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            // Split on the last colon so SKUs that contain ":" still work
            $pos = strrpos($chunk, ':');
            if ($pos === false) {
                $sku = $chunk;
                $qty = 1;
            } else {
                $sku = trim(substr($chunk, 0, $pos));
                $qtyRaw = trim(substr($chunk, $pos + 1));
                if (!ctype_digit($qtyRaw)) {
                    continue;
                }
                $qty = (int)$qtyRaw;
            }

            if ($sku === '' || $qty < 1) {
                continue;
            }

            if (!isset($items[$sku]) && count($items) >= self::MAX_ITEMS) {
                break;
            }

            $items[$sku] = min(($items[$sku] ?? 0) + $qty, self::MAX_QTY);
        }

        return $items;
    }
}
