<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Merchandising;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class PersonalizationService
{
    public function __construct(
        private readonly EntityRepository $membershipRepository,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * Get personalization boosts for a logged-in customer.
     *
     * Accepts either a SalesChannelContext (convenience) or explicit salesChannelId + Context
     * so it can be called from both storefront subscribers and the ES query subscriber.
     *
     * @return array<int, array{segmentId: string, boostFactor: float}>
     */
    public function getBoosts(string $customerId, SalesChannelContext $context): array
    {
        return $this->getBoostsForCustomer(
            $customerId,
            $context->getSalesChannelId(),
            $context->getContext(),
        );
    }

    /**
     * @return array<int, array{segmentId: string, boostFactor: float}>
     */
    public function getBoostsForCustomer(string $customerId, string $salesChannelId, Context $context): array
    {
        $enabled = $this->systemConfigService->get(
            'StrixVisualMerchandiser.config.enablePersonalization',
            $salesChannelId,
        );

        if (!$enabled) {
            return [];
        }

        $maxSegments = (int) ($this->systemConfigService->get(
            'StrixVisualMerchandiser.config.maxSegmentsPerCustomer',
            $salesChannelId,
        ) ?: 10);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addAssociation('segment');
        $criteria->setLimit($maxSegments);

        $memberships = $this->membershipRepository->search($criteria, $context);

        $boosts = [];
        foreach ($memberships as $membership) {
            $segment = $membership->getSegment();
            if ($segment === null || !$segment->isActive()) {
                continue;
            }

            $boosts[] = [
                'segmentId' => $segment->getId(),
                'boostFactor' => $segment->getBoostFactor(),
            ];
        }

        return $boosts;
    }
}
