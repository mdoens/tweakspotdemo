<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchRuleCategory;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Strix\VisualMerchandiser\Core\Content\MerchRule\MerchRuleDefinition;

class MerchRuleCategoryDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'strix_merch_rule_category';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('merch_rule_id', 'merchRuleId', MerchRuleDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('category_id', 'categoryId', CategoryDefinition::class))->addFlags(new PrimaryKey(), new Required()),

            new ManyToOneAssociationField('merchRule', 'merch_rule_id', MerchRuleDefinition::class, 'id', false),
            new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class, 'id', false),
        ]);
    }
}
