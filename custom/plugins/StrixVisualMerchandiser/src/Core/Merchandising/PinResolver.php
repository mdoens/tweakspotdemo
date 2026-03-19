<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Merchandising;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Strix\VisualMerchandiser\Core\Content\MerchPin\MerchPinCollection;
use Strix\VisualMerchandiser\Core\Content\MerchPin\MerchPinEntity;

class PinResolver
{
    /**
     * In-memory cache for active pins per category, avoiding duplicate DB queries within a single request.
     *
     * @var array<string, MerchPinCollection>
     */
    private array $pinCache = [];

    public function __construct(
        private readonly EntityRepository $merchPinRepository,
        private readonly LoggerInterface $logger,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function resolve(string $categoryId, ProductListingResult $result, Context $context, ?string $salesChannelId = null): void
    {
        $pins = $this->getActivePins($categoryId, $context);
        if ($pins->count() === 0) {
            return;
        }

        // Deduplicate pins on same position: lowest product_id wins
        $pins = $this->deduplicatePins($pins);

        $limit = $result->getLimit() ?: 24;
        $currentPage = $result->getPage();

        $hideOutOfStock = $this->systemConfigService->get(
            'StrixVisualMerchandiser.config.pinOutOfStockBehavior',
            $salesChannelId,
        ) === 'hide';

        $elements = $result->getElements();

        foreach ($pins as $pin) {
            $productId = $pin->getProductId();
            $position = $pin->getPosition();

            // Calculate which page this pin belongs to and the position within that page
            $pinPage = (int) ceil($position / $limit);

            // Only apply pins that belong to the current page
            if ($pinPage !== $currentPage) {
                continue;
            }

            // Calculate position within this page (0-based index)
            $positionOnPage = (($position - 1) % $limit);

            if (!isset($elements[$productId])) {
                $this->logger->info('Pinned product not found in listing result, skipping', [
                    'productId' => $productId,
                    'categoryId' => $categoryId,
                    'position' => $position,
                ]);
                continue;
            }

            // Check out-of-stock behavior
            if ($hideOutOfStock) {
                $product = $result->get($productId);
                if ($product !== null && $product->getStock() <= 0) {
                    $this->logger->info('Pinned product out of stock, skipping due to config', [
                        'productId' => $productId,
                        'categoryId' => $categoryId,
                        'position' => $position,
                        'stock' => $product->getStock(),
                    ]);
                    continue;
                }
            }

            // Remove product from current position
            $product = $elements[$productId];
            unset($elements[$productId]);

            // Re-insert at pinned position within the page
            $reindexed = array_values($elements);
            $insertAt = max(0, min($positionOnPage, \count($reindexed)));
            array_splice($reindexed, $insertAt, 0, [$product]);

            $elements = [];
            foreach ($reindexed as $element) {
                $elements[$element->getId()] = $element;
            }
        }

        $result->clear();
        foreach ($elements as $element) {
            $result->add($element);
        }
    }

    /**
     * @return list<string>
     */
    public function getPinnedProductIds(string $categoryId, Context $context): array
    {
        $pins = $this->getActivePins($categoryId, $context);

        return array_values($pins->map(fn (MerchPinEntity $pin) => $pin->getProductId()));
    }

    /**
     * Deduplicate: if two pins target the same position, the one with the lowest product_id wins.
     */
    private function deduplicatePins(MerchPinCollection $pins): MerchPinCollection
    {
        $byPosition = [];

        foreach ($pins as $pin) {
            $pos = $pin->getPosition();
            if (!isset($byPosition[$pos]) || strcmp($pin->getProductId(), $byPosition[$pos]->getProductId()) < 0) {
                $byPosition[$pos] = $pin;
            }
        }

        ksort($byPosition);

        $result = new MerchPinCollection();
        foreach ($byPosition as $pin) {
            $result->add($pin);
        }

        return $result;
    }

    public function getActivePins(string $categoryId, Context $context): MerchPinCollection
    {
        if (isset($this->pinCache[$categoryId])) {
            return $this->pinCache[$categoryId];
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categoryId', $categoryId));
        $criteria->addFilter(new EqualsFilter('active', true));

        // Only include pins within their validity window
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('validFrom', null),
            new RangeFilter('validFrom', [RangeFilter::LTE => $now]),
        ]));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('validUntil', null),
            new RangeFilter('validUntil', [RangeFilter::GTE => $now]),
        ]));

        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->setLimit(100);

        /** @var MerchPinCollection $pins */
        $pins = $this->merchPinRepository->search($criteria, $context)->getEntities();

        $this->pinCache[$categoryId] = $pins;

        return $pins;
    }
}
