<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Content\MerchSortingTemplateCategory;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Strix\VisualMerchandiser\Core\Content\MerchSortingTemplate\MerchSortingTemplateEntity;

class MerchSortingTemplateCategoryEntity extends Entity
{
    use EntityIdTrait;

    protected string $sortingTemplateId;

    protected string $categoryId;

    protected string $overrideMode;

    protected ?MerchSortingTemplateEntity $sortingTemplate = null;

    protected ?CategoryEntity $category = null;

    public function getSortingTemplateId(): string
    {
        return $this->sortingTemplateId;
    }

    public function setSortingTemplateId(string $sortingTemplateId): void
    {
        $this->sortingTemplateId = $sortingTemplateId;
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

    public function getSortingTemplate(): ?MerchSortingTemplateEntity
    {
        return $this->sortingTemplate;
    }

    public function setSortingTemplate(?MerchSortingTemplateEntity $sortingTemplate): void
    {
        $this->sortingTemplate = $sortingTemplate;
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
