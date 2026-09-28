<?php

namespace App\Ordering;

use App\Entity\CartLine;
use App\Entity\Supplier;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A way to hand a cart's lines over to a store's own website ("drive"), the user finishing (and paying) there
 * themselves: nothing is ever bought automatically. Implementations are tagged automatically.
 *
 * Only Amazon offers a public pre-filled cart link (AmazonConnector). Retailer drives (Carrefour, Leclerc, Auchan…)
 * expose no public cart API: filling their carts needs a partnership (affiliation / retail-media programme or a
 * provider such as a "shoppable recipes" service). Until then those stores use the per-line "Voir sur le site" link
 * (Supplier::searchUrlTemplate, ItemOffer::productUrl).
 */
#[AutoconfigureTag(self::TAG)]
interface StoreConnectorInterface
{
    public const TAG = 'app.store_connector';

    public function supports(Supplier $store): bool;

    /**
     * @param list<CartLine> $lines the cart's lines at this store
     *
     * @return array{urls: list<string>, lines: list<CartLine>, skipped: list<CartLine>} links to open (one or more
     *                                                                                     pages) and the lines left out
     */
    public function buildCart(Supplier $store, array $lines): array;
}
