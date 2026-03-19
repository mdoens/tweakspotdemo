<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchPin;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MerchPinEntity>
 */
class MerchPinCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MerchPinEntity::class;
    }
}
