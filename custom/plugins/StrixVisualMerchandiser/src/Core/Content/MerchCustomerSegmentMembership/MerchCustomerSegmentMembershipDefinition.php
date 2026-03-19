<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchCustomerSegmentMembership;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Strix\VisualMerchandiser\Core\Content\MerchCustomerSegment\MerchCustomerSegmentDefinition;

class MerchCustomerSegmentMembershipDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'strix_merch_customer_segment_membership';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey(), new ApiAware()),
            (new FkField('segment_id', 'segmentId', MerchCustomerSegmentDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new FloatField('score', 'score'))->addFlags(new ApiAware()),
            (new DateTimeField('calculated_at', 'calculatedAt'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('segment', 'segment_id', MerchCustomerSegmentDefinition::class, 'id', false),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
        ]);
    }
}
