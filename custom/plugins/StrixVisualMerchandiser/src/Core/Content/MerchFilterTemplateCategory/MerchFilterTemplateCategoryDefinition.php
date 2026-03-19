<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchFilterTemplateCategory;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Strix\VisualMerchandiser\Core\Content\MerchFilterTemplate\MerchFilterTemplateDefinition;

class MerchFilterTemplateCategoryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'strix_merch_filter_template_category';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MerchFilterTemplateCategoryEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey(), new ApiAware()),
            (new FkField('filter_template_id', 'filterTemplateId', MerchFilterTemplateDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new FkField('category_id', 'categoryId', CategoryDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new StringField('override_mode', 'overrideMode'))->addFlags(new Required(), new ApiAware()),

            new ManyToOneAssociationField('filterTemplate', 'filter_template_id', MerchFilterTemplateDefinition::class, 'id', false),
            new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class, 'id', false),
        ]);
    }
}
