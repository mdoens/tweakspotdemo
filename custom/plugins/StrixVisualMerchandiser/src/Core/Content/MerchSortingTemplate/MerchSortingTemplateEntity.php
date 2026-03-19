<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchSortingTemplate;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class MerchSortingTemplateEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    /** @var array<int, mixed> */
    protected array $sortOptions;

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
    public function getSortOptions(): array
    {
        return $this->sortOptions;
    }

    /** @param array<int, mixed> $sortOptions */
    public function setSortOptions(array $sortOptions): void
    {
        $this->sortOptions = $sortOptions;
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
