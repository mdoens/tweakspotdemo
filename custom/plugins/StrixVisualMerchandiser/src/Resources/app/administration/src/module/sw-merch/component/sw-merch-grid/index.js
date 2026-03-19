import template from './sw-merch-grid.html.twig';
import './sw-merch-grid.scss';

const { Component, Context, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-merch-grid', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        categoryId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            products: [],
            pins: {},
            isLoading: false,
            page: 1,
            limit: 24,
            total: 0,
            isDirty: false,
            pendingPinChanges: [],
            salesChannels: [],
            showPinConfirm: false,
            pendingDragResult: null,
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        pinRepository() {
            return this.repositoryFactory.create('strix_merch_pin');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        totalPages() {
            return Math.ceil(this.total / this.limit);
        },
    },

    watch: {
        categoryId: {
            immediate: true,
            async handler(newVal) {
                if (newVal) {
                    this.page = 1;
                    // Ensure sales channels are loaded before fetching products
                    if (this.salesChannels.length === 0) {
                        await this.loadSalesChannels();
                    }
                    this.loadProducts();
                }
            },
        },
    },

    methods: {
        async loadSalesChannels() {
            const result = await this.salesChannelRepository.search(new Criteria().setLimit(50), Context.api);
            this.salesChannels = [...result];
        },

        async loadProducts() {
            if (!this.categoryId) return;

            this.isLoading = true;

            try {
                // Try Store API first for real storefront order (with merch rules applied)
                const salesChannelId = this.salesChannels[0]?.id;
                let storeApiProducts = [];

                if (salesChannelId) {
                    try {
                        const accessKey = await this.getSalesChannelAccessKey(salesChannelId);
                        const baseUrl = window.location.origin;
                        const response = await fetch(`${baseUrl}/store-api/product-listing/${this.categoryId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'sw-access-key': accessKey,
                            },
                            body: JSON.stringify({ limit: this.limit, p: this.page }),
                        });

                        if (response.ok) {
                            const data = await response.json();
                            storeApiProducts = data.elements || [];
                            this.total = data.total || storeApiProducts.length;
                        }
                    } catch {
                        storeApiProducts = [];
                    }
                }

                // Fallback to admin API
                if (storeApiProducts.length === 0) {
                    const criteria = new Criteria(this.page, this.limit);
                    criteria.addFilter(Criteria.equals('categoriesRo.id', this.categoryId));
                    criteria.addAssociation('cover.media');

                    const productResult = await this.productRepository.search(criteria, Context.api);
                    storeApiProducts = [...productResult];
                    this.total = productResult.total;
                }

                this.products = storeApiProducts;
                await this.loadPins();
            } catch (error) {
                console.error('Grid load failed:', error);
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

        async loadPins() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('categoryId', this.categoryId));
            criteria.addFilter(Criteria.equals('active', true));

            const result = await this.pinRepository.search(criteria, Context.api);

            const pinMap = {};
            for (const pin of result) {
                pinMap[pin.productId] = pin;
            }
            this.pins = pinMap;
        },

        isPinned(productId) {
            return !!this.pins[productId];
        },

        getPinPosition(productId) {
            const pin = this.pins[productId];
            return pin ? pin.position : null;
        },

        getProductImage(product) {
            return product.cover?.media?.url || null;
        },

        onDragEnd(event) {
            const { oldIndex, newIndex } = event;
            if (oldIndex === newIndex) return;

            const moved = this.products.splice(oldIndex, 1)[0];
            this.products.splice(newIndex, 0, moved);

            const globalPosition = (this.page - 1) * this.limit + newIndex + 1;

            this.pendingDragResult = {
                productId: moved.id,
                productName: moved.translated?.name || moved.name || moved.id,
                position: globalPosition,
                oldIndex,
                newIndex,
            };
            this.showPinConfirm = true;
        },

        onConfirmPin() {
            if (!this.pendingDragResult) return;

            this.pendingPinChanges.push({
                productId: this.pendingDragResult.productId,
                position: this.pendingDragResult.position,
            });

            this.isDirty = true;
            this.showPinConfirm = false;
            this.pendingDragResult = null;
        },

        onCancelPin() {
            if (!this.pendingDragResult) return;

            // Revert the array reorder
            const { oldIndex, newIndex } = this.pendingDragResult;
            const moved = this.products.splice(newIndex, 1)[0];
            this.products.splice(oldIndex, 0, moved);

            this.showPinConfirm = false;
            this.pendingDragResult = null;
        },

        async onSave() {
            if (!this.isDirty) return;

            try {
                for (const change of this.pendingPinChanges) {
                    const existingPin = this.pins[change.productId];

                    if (existingPin) {
                        existingPin.position = change.position;
                        await this.pinRepository.save(existingPin, Context.api);
                    } else {
                        const pin = this.pinRepository.create(Context.api);
                        pin.categoryId = this.categoryId;
                        pin.productId = change.productId;
                        pin.position = change.position;
                        pin.label = 'sponsored';
                        pin.active = true;
                        await this.pinRepository.save(pin, Context.api);
                    }
                }

                this.pendingPinChanges = [];
                this.isDirty = false;
                this.createNotificationSuccess({ message: this.$tc('sw-merch.general.save') });

                await this.loadProducts();
            } catch (error) {
                this.createNotificationError({ message: error.message || 'Failed to save' });
            }
        },

        onPageChange(page) {
            this.page = page;
            this.loadProducts();
        },
    },
});
