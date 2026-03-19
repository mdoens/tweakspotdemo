<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchRule;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MerchRuleEntity>
 */
class MerchRuleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MerchRuleEntity::class;
    }
}
