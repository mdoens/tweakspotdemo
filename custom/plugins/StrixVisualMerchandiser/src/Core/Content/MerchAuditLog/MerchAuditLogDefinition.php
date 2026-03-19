<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchAuditLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MerchAuditLogDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'strix_merch_audit_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MerchAuditLogEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey(), new ApiAware()),
            (new StringField('entity_type', 'entityType'))->addFlags(new Required(), new ApiAware()),
            (new StringField('entity_id', 'entityId'))->addFlags(new Required(), new ApiAware()),
            (new StringField('action', 'action'))->addFlags(new Required(), new ApiAware()),
            (new JsonField('changes', 'changes'))->addFlags(new ApiAware()),
            (new StringField('user_id', 'userId'))->addFlags(new ApiAware()),
            (new StringField('category_id', 'categoryId'))->addFlags(new ApiAware()),
        ]);
    }
}
