<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchFilterTemplateCategory;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Strix\VisualMerchandiser\Core\Content\MerchFilterTemplate\MerchFilterTemplateEntity;

class MerchFilterTemplateCategoryEntity extends Entity
{
    use EntityIdTrait;

    protected string $filterTemplateId;

    protected string $categoryId;

    protected string $overrideMode;

    protected ?MerchFilterTemplateEntity $filterTemplate = null;

    protected ?CategoryEntity $category = null;

    public function getFilterTemplateId(): string
    {
        return $this->filterTemplateId;
    }

    public function setFilterTemplateId(string $filterTemplateId): void
    {
        $this->filterTemplateId = $filterTemplateId;
    }

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function setCategoryId(string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getOverrideMode(): string
    {
        return $this->overrideMode;
    }

    public function setOverrideMode(string $overrideMode): void
    {
        $this->overrideMode = $overrideMode;
    }

    public function getFilterTemplate(): ?MerchFilterTemplateEntity
    {
        return $this->filterTemplate;
    }

    public function setFilterTemplate(?MerchFilterTemplateEntity $filterTemplate): void
    {
        $this->filterTemplate = $filterTemplate;
    }

    public function getCategory(): ?CategoryEntity
    {
        return $this->category;
    }

    public function setCategory(?CategoryEntity $category): void
    {
        $this->category = $category;
    }
}
