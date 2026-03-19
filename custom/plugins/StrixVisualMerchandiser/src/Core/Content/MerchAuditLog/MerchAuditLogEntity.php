<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchAuditLog;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MerchAuditLogEntity extends Entity
{
    use EntityIdTrait;

    protected string $entityType;

    protected string $entityId;

    protected string $action;

    /** @var array<string, mixed>|null */
    protected ?array $changes = null;

    protected ?string $userId = null;

    protected ?string $categoryId = null;

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): void
    {
        $this->entityType = $entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): void
    {
        $this->entityId = $entityId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    /** @return array<string, mixed>|null */
    public function getChanges(): ?array
    {
        return $this->changes;
    }

    /** @param array<string, mixed>|null $changes */
    public function setChanges(?array $changes): void
    {
        $this->changes = $changes;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function setCategoryId(?string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }
}
