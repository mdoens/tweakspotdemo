import template from './sw-merch-product-search.html.twig';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-merch-product-search', {
    template,

    inject: ['repositoryFactory'],

    emits: ['product-selected', 'close'],

    data() {
        return {
            searchTerm: '',
            products: [],
            isLoading: false,
            page: 1,
            limit: 12,
            total: 0,
            debounceTimer: null,
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        totalPages() {
            return Math.ceil(this.total / this.limit);
        },
    },

    beforeUnmount() {
        clearTimeout(this.debounceTimer);
    },

    methods: {
        onSearchTermChange(term) {
            this.searchTerm = term;
            this.page = 1;

            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.searchProducts();
            }, 300);
        },

        async searchProducts() {
            if (!this.searchTerm || this.searchTerm.length < 2) {
                this.products = [];
                this.total = 0;
                return;
            }

            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.searchTerm);
            criteria.addAssociation('cover.media');

            const result = await this.productRepository.search(criteria, Context.api);
            this.products = [...result];
            this.total = result.total;

            this.isLoading = false;
        },

        onSelectProduct(product) {
            this.$emit('product-selected', product);
        },

        onClose() {
            this.$emit('close');
        },

        onPageChange(page) {
            this.page = page;
            this.searchProducts();
        },

        getProductImage(product) {
            return product.cover?.media?.url || null;
        },
    },
});
