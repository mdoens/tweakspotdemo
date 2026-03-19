import template from './sw-merch-category-tree.html.twig';
import './sw-merch-category-tree.scss';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-merch-category-tree', {
    template,

    inject: ['repositoryFactory'],

    emits: ['category-select'],

    data() {
        return {
            categories: [],
            isLoading: false,
            selectedId: null,
            expandedIds: [],
            merchIndicators: {},
        };
    },

    computed: {
        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        merchRuleCategoryRepository() {
            return this.repositoryFactory.create('strix_merch_rule_category');
        },

        merchPinRepository() {
            return this.repositoryFactory.create('strix_merch_pin');
        },

        filterTemplateCategoryRepository() {
            return this.repositoryFactory.create('strix_merch_filter_template_category');
        },
    },

    created() {
        this.loadRootCategories();
    },

    methods: {
        async loadRootCategories() {
            this.isLoading = true;

            try {
                const criteria = new Criteria();
                criteria.addSorting(Criteria.sort('name', 'ASC'));
                criteria.setLimit(500);

                const result = await this.categoryRepository.search(criteria, Context.api);
                const allCategories = [...result];

                // Build the full tree from the flat list
                this.categories = this.buildTree(allCategories);

                // Load merch indicators for all categories (non-blocking)
                const allIds = allCategories.map((c) => c.id);
                this.loadMerchIndicators(allIds).catch(() => {});
            } catch (error) {
                console.error('sw-merch-category-tree: Failed to load categories', error);
            }

            this.isLoading = false;
        },

        buildTree(entities) {
            // Create a map of id → tree item
            const map = {};
            for (const cat of entities) {
                map[cat.id] = {
                    id: cat.id,
                    name: cat.translated?.name || cat.name,
                    parentId: cat.parentId,
                    childCount: cat.childCount || 0,
                    children: [],
                    isLoaded: true,
                };
            }

            // Build parent-child relationships
            const roots = [];
            for (const item of Object.values(map)) {
                if (item.parentId && map[item.parentId]) {
                    map[item.parentId].children.push(item);
                    // Update childCount based on actual children found
                    map[item.parentId].childCount = map[item.parentId].children.length;
                } else if (!item.parentId) {
                    // This is the root category — skip it, show its children as top-level
                    continue;
                } else {
                    // Parent not in result set — show as top-level
                    roots.push(item);
                }
            }

            // If we found a root category, return its children as top-level
            const rootCat = Object.values(map).find((item) => item.parentId === null);
            if (rootCat && rootCat.children.length > 0) {
                return rootCat.children;
            }

            // Fallback: return items without parents in the result set
            return roots.length > 0 ? roots : Object.values(map).filter((item) => item.parentId !== null);
        },

        async loadMerchIndicators(categoryIds) {
            if (categoryIds.length === 0) return;

            try {
                // Batch-load all indicators in 3 parallel queries (not per-category)
                const [ruleLinks, pins, filterLinks] = await Promise.all([
                    // Rule-category junction records — 1 query instead of N
                    this.merchRuleCategoryRepository.search(
                        new Criteria().addFilter(Criteria.equalsAny('categoryId', categoryIds)),
                        Context.api,
                    ).catch(() => []),
                    this.merchPinRepository.search(
                        new Criteria().addFilter(Criteria.equalsAny('categoryId', categoryIds)).addFilter(Criteria.equals('active', true)),
                        Context.api,
                    ).catch(() => []),
                    this.filterTemplateCategoryRepository.search(
                        new Criteria().addFilter(Criteria.equalsAny('categoryId', categoryIds)),
                        Context.api,
                    ).catch(() => []),
                ]);

                const indicators = { ...this.merchIndicators };

                // Count unique ruleIds per category from junction records
                const ruleLinkArr = [...ruleLinks];
                const pinArr = [...pins];
                const filterArr = [...filterLinks];

                for (const id of categoryIds) {
                    const uniqueRuleIds = new Set(ruleLinkArr.filter((r) => r.categoryId === id).map((r) => r.merchRuleId));
                    indicators[id] = {
                        rules: uniqueRuleIds.size,
                        pins: pinArr.filter((p) => p.categoryId === id).length,
                        filters: filterArr.filter((f) => f.categoryId === id).length,
                    };
                }

                this.merchIndicators = indicators;
            } catch {
                // Indicators are non-critical — don't block the tree
            }
        },

        onItemSelect(item) {
            this.selectedId = item.id;
            this.$emit('category-select', item.id);
        },

        onItemToggle(item) {
            const idx = this.expandedIds.indexOf(item.id);
            if (idx >= 0) {
                this.expandedIds.splice(idx, 1);
            } else {
                this.expandedIds.push(item.id);
            }
        },

        hasIndicators(categoryId) {
            const ind = this.merchIndicators[categoryId];
            return ind && (ind.rules > 0 || ind.pins > 0 || ind.filters > 0);
        },

        getIndicatorText(categoryId) {
            const ind = this.merchIndicators[categoryId];
            if (!ind) return '';
            const parts = [];
            if (ind.rules > 0) parts.push(`${ind.rules}R`);
            if (ind.pins > 0) parts.push(`${ind.pins}P`);
            if (ind.filters > 0) parts.push(`${ind.filters}F`);
            return parts.join(' ');
        },
    },
});
