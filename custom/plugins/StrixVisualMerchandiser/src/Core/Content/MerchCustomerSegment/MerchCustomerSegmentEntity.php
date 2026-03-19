<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchCustomerSegment;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class MerchCustomerSegmentEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $type;

    /** @var array<string, mixed> */
    protected array $config;

    protected bool $active = true;

    protected float $boostFactor = 1.0;

    protected ?int $customerCount = null;

    protected ?string $signalDescription = null;

    /** @var array<string>|null */
    protected ?array $categories = null;

    protected ?\DateTimeInterface $lastCalculatedAt = null;

    protected ?string $salesChannelId = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?EntityCollection $memberships = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    /** @param array<string, mixed> $config */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getBoostFactor(): float
    {
        return $this->boostFactor;
    }

    public function setBoostFactor(float $boostFactor): void
    {
        $this->boostFactor = $boostFactor;
    }

    public function getCustomerCount(): ?int
    {
        return $this->customerCount;
    }

    public function setCustomerCount(?int $customerCount): void
    {
        $this->customerCount = $customerCount;
    }

    public function getSignalDescription(): ?string
    {
        return $this->signalDescription;
    }

    public function setSignalDescription(?string $signalDescription): void
    {
        $this->signalDescription = $signalDescription;
    }

    /** @return array<string>|null */
    public function getCategories(): ?array
    {
        return $this->categories;
    }

    /** @param array<string>|null $categories */
    public function setCategories(?array $categories): void
    {
        $this->categories = $categories;
    }

    public function getLastCalculatedAt(): ?\DateTimeInterface
    {
        return $this->lastCalculatedAt;
    }

    public function setLastCalculatedAt(?\DateTimeInterface $lastCalculatedAt): void
    {
        $this->lastCalculatedAt = $lastCalculatedAt;
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

    public function getMemberships(): ?EntityCollection
    {
        return $this->memberships;
    }

    public function setMemberships(?EntityCollection $memberships): void
    {
        $this->memberships = $memberships;
    }
}
