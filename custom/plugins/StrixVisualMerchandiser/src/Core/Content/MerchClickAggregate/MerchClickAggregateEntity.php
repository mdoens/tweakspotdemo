<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchClickAggregate;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class MerchClickAggregateEntity extends Entity
{
    use EntityIdTrait;

    protected string $categoryId;

    protected string $productId;

    protected \DateTimeInterface $date;

    protected int $totalClicks;

    protected ?float $avgPosition = null;

    protected ?float $ctr = null;

    protected ?string $salesChannelId = null;

    protected ?CategoryEntity $category = null;

    protected ?SalesChannelEntity $salesChannel = null;

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function setCategoryId(string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): void
    {
        $this->date = $date;
    }

    public function getTotalClicks(): int
    {
        return $this->totalClicks;
    }

    public function setTotalClicks(int $totalClicks): void
    {
        $this->totalClicks = $totalClicks;
    }

    public function getAvgPosition(): ?float
    {
        return $this->avgPosition;
    }

    public function setAvgPosition(?float $avgPosition): void
    {
        $this->avgPosition = $avgPosition;
    }

    public function getCtr(): ?float
    {
        return $this->ctr;
    }

    public function setCtr(?float $ctr): void
    {
        $this->ctr = $ctr;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getCategory(): ?CategoryEntity
    {
        return $this->category;
    }

    public function setCategory(?CategoryEntity $category): void
    {
        $this->category = $category;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
