<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchFilterTemplate;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class MerchFilterTemplateEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    /** @var array<int, mixed> */
    protected array $filters;

    protected bool $active = true;

    protected ?string $salesChannelId = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?EntityCollection $categories = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /** @return array<int, mixed> */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /** @param array<int, mixed> $filters */
    public function setFilters(array $filters): void
    {
        $this->filters = $filters;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getCategories(): ?EntityCollection
    {
        return $this->categories;
    }

    public function setCategories(?EntityCollection $categories): void
    {
        $this->categories = $categories;
    }
}
