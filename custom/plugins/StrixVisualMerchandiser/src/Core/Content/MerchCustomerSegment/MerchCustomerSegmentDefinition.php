<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchCustomerSegment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Strix\VisualMerchandiser\Core\Content\MerchCustomerSegmentMembership\MerchCustomerSegmentMembershipDefinition;

class MerchCustomerSegmentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'strix_merch_customer_segment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MerchCustomerSegmentEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey(), new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new StringField('type', 'type'))->addFlags(new Required(), new ApiAware()),
            (new JsonField('config', 'config'))->addFlags(new Required(), new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            (new FloatField('boost_factor', 'boostFactor'))->addFlags(new ApiAware()),
            (new IntField('customer_count', 'customerCount'))->addFlags(new ApiAware()),
            (new StringField('signal_description', 'signalDescription', 500))->addFlags(new ApiAware()),
            (new JsonField('categories', 'categories'))->addFlags(new ApiAware()),
            (new DateTimeField('last_calculated_at', 'lastCalculatedAt'))->addFlags(new ApiAware()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            new OneToManyAssociationField('memberships', MerchCustomerSegmentMembershipDefinition::class, 'segment_id'),
        ]);
    }
}
