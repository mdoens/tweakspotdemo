import template from './sw-merch-preview.html.twig';
import './sw-merch-preview.scss';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-merch-preview', {
    template,

    inject: ['repositoryFactory'],

    props: {
        categoryId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isOpen: false,
            products: [],
            pins: [],
            rules: [],
            isLoading: false,
            selectedSalesChannelId: null,
            salesChannels: [],
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        pinRepository() {
            return this.repositoryFactory.create('strix_merch_pin');
        },

        ruleRepository() {
            return this.repositoryFactory.create('strix_merch_rule');
        },

        // Rules are loaded via the rule repository with category association filter
        // (strix_merch_rule_category is a MappingEntityDefinition — can't search directly)

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        pinnedProductIds() {
            return new Set(this.pins.map((p) => p.productId));
        },

        previewProducts() {
            // Store API returns products in the correct storefront order
            // (with merchandising rules + pin repositioning already applied)
            // No client-side repositioning needed.
            return this.products;
        },
    },

    watch: {
        categoryId() {
            if (this.isOpen) {
                this.loadPreview();
            }
        },
    },

    created() {
        this.loadSalesChannels();
    },

    methods: {
        onToggle() {
            this.isOpen = !this.isOpen;
            if (this.isOpen && this.categoryId) {
                this.loadPreview();
            }
        },

        async loadSalesChannels() {
            const result = await this.salesChannelRepository.search(new Criteria().setLimit(50), Context.api);
            this.salesChannels = [...result];
        },

        async loadPreview() {
            if (!this.categoryId) return;

            this.isLoading = true;

            try {
                // Load products via Store API (with merch rules + pins applied)
                const salesChannelId = this.selectedSalesChannelId || this.salesChannels[0]?.id;
                let storeApiProducts = [];

                if (salesChannelId) {
                    try {
                        const accessKey = await this.getSalesChannelAccessKey(salesChannelId);
                        // Use native fetch — the admin httpClient adds Authorization header
                        // which conflicts with Store API's sw-access-key auth
                        const baseUrl = window.location.origin;
                        const response = await fetch(`${baseUrl}/store-api/product-listing/${this.categoryId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'sw-access-key': accessKey,
                            },
                            body: JSON.stringify({ limit: 24 }),
                        });

                        if (response.ok) {
                            const data = await response.json();
                            storeApiProducts = data.elements || [];
                        }
                    } catch {
                        storeApiProducts = [];
                    }
                }

                // Fallback: admin API (no merchandising applied)
                if (storeApiProducts.length === 0) {
                    const productCriteria = new Criteria(1, 24);
                    productCriteria.addFilter(Criteria.equals('categoriesRo.id', this.categoryId));
                    productCriteria.addAssociation('cover.media');

                    const productResult = await this.productRepository.search(productCriteria, Context.api);
                    storeApiProducts = [...productResult];
                }

                this.products = storeApiProducts;

                // Load pins and rules for display
                const pinCriteria = new Criteria();
                pinCriteria.addFilter(Criteria.equals('categoryId', this.categoryId));
                pinCriteria.addFilter(Criteria.equals('active', true));
                pinCriteria.addSorting(Criteria.sort('position', 'ASC'));

                const ruleCriteria = new Criteria();
                ruleCriteria.addFilter(Criteria.equals('categories.id', this.categoryId));
                ruleCriteria.addFilter(Criteria.equals('active', true));
                ruleCriteria.addSorting(Criteria.sort('priority', 'DESC'));
                ruleCriteria.setLimit(50);

                const [pinResult, ruleResult] = await Promise.all([
                    this.pinRepository.search(pinCriteria, Context.api),
                    this.ruleRepository.search(ruleCriteria, Context.api),
                ]);

                this.pins = [...pinResult];
                this.rules = [...ruleResult];
            } catch (error) {
                console.error('Preview load failed:', error);
            }

            this.isLoading = false;
        },

        async getSalesChannelAccessKey(salesChannelId) {
            try {
                const result = await this.salesChannelRepository.get(salesChannelId, Context.api);
                return result?.accessKey || '';
            } catch {
                return '';
            }
        },

        onSalesChannelChange(id) {
            this.selectedSalesChannelId = id;
            this.loadPreview();
        },

        isPinned(productId) {
            return this.pinnedProductIds.has(productId);
        },

        getPinLabel(productId) {
            const pin = this.pins.find((p) => p.productId === productId);
            return pin?.label || null;
        },

        getProductName(product) {
            // Store API returns flat object, admin API returns entity proxy
            if (product.translated && product.translated.name) return product.translated.name;
            if (product.name) return product.name;
            // Fallback: truncated ID
            return product.id ? product.id.substring(0, 8) + '...' : '';
        },

        getProductNumber(product) {
            return product.productNumber || '';
        },

        getProductImage(product) {
            return product.cover?.media?.url || null;
        },
    },
});
