<?php

namespace App\Ordering;

use App\Entity\Supplier;

/**
 * Pre-filled Amazon cart ("Add to cart form", https://www.<domain>/gp/aws/cart/add.html?ASIN.1=…&Quantity.1=…): the
 * page asks the user to confirm the additions, then they pay on Amazon. At most 50 articles per link (split beyond).
 * Quantity = number of packs. AMAZON_ASSOCIATE_TAG (empty by default) adds "tag=…".
 */
final class AmazonConnector implements StoreConnectorInterface
{
    public const MAX_PER_LINK = 50;

    public function __construct(private readonly string $amazonAssociateTag = '')
    {
    }

    public function supports(Supplier $store): bool
    {
        return $store->isAmazon();
    }

    public function buildCart(Supplier $store, array $lines): array
    {
        $kept = $skipped = [];
        foreach ($lines as $line) {
            $asin = $line->getAsin() ?? $line->getItem()->getAsin();
            if (null !== $asin && preg_match('/^[A-Z0-9]{10}$/', strtoupper($asin)) && $line->getQuantity() > 0) {
                $kept[] = $line;
            } else {
                $skipped[] = $line;
            }
        }
        $domain = preg_match('/^amazon\.[a-z.]{2,10}$/', $store->getAmazonDomain()) ? $store->getAmazonDomain() : 'amazon.fr';
        $urls = [];
        foreach (array_chunk($kept, self::MAX_PER_LINK) as $chunk) {
            $query = [];
            foreach ($chunk as $i => $line) {
                $query['ASIN.'.($i + 1)] = strtoupper((string) ($line->getAsin() ?? $line->getItem()->getAsin()));
                $query['Quantity.'.($i + 1)] = (int) max(1, $line->packs());
            }
            if ('' !== trim($this->amazonAssociateTag)) {
                $query['AssociateTag'] = trim($this->amazonAssociateTag);
            }
            $urls[] = 'https://www.'.$domain.'/gp/aws/cart/add.html?'.http_build_query($query);
        }

        return ['urls' => $urls, 'lines' => $kept, 'skipped' => $skipped];
    }
}
