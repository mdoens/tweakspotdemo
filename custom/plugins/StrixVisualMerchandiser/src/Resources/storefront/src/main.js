import Plugin from 'src/plugin-system/plugin.class';

export default class MerchClickTrackingPlugin extends Plugin {
    static options = {
        trackingUrl: '/store-api/merch/click',
        batchInterval: 30000,
    };

    init() {
        this._eventQueue = [];
        this._startBatchTimer();
        this._registerClickListeners();
        this._registerAddToCartListeners();
    }

    _registerClickListeners() {
        document.addEventListener('click', (event) => {
            const productLink = event.target.closest('[data-merch-product-id]');
            if (!productLink) {
                return;
            }

            const metadata = this._collectMetadata(productLink);

            this._eventQueue.push({
                productId: productLink.dataset.merchProductId,
                categoryId: productLink.dataset.merchCategoryId,
                eventType: 'click',
                position: parseInt(productLink.dataset.merchPosition, 10) || null,
                metadata,
            });
        });
    }

    _registerAddToCartListeners() {
        // Listen for Shopware's add-to-cart event on the document
        document.addEventListener('click', (event) => {
            const addToCartBtn = event.target.closest('.btn-buy, [data-add-to-cart]');
            if (!addToCartBtn) return;

            // Find the closest product context (product box or product detail)
            const productBox = addToCartBtn.closest('[data-merch-product-id], .product-box, .product-detail');
            if (!productBox) return;

            const productId = productBox.dataset?.merchProductId
                || productBox.querySelector('[data-merch-product-id]')?.dataset?.merchProductId
                || productBox.querySelector('input[name="productId"]')?.value;

            const categoryId = productBox.dataset?.merchCategoryId
                || this.el?.dataset?.merchCategoryId
                || document.querySelector('[data-merch-category-id]')?.dataset?.merchCategoryId;

            if (productId && categoryId) {
                this._eventQueue.push({
                    productId,
                    categoryId,
                    eventType: 'add_to_cart',
                    position: parseInt(productBox.dataset?.merchPosition, 10) || null,
                    metadata: this._collectMetadata(productBox),
                });
                // Flush immediately for add-to-cart (important event)
                this._flush();
            }
        });
    }

    _collectMetadata(element) {
        const metadata = {};

        // Capture document referrer
        if (document.referrer) {
            metadata.referrer = document.referrer;
        }

        // Capture search term from URL params
        const urlParams = new URLSearchParams(window.location.search);
        const searchTerm = urlParams.get('search') || urlParams.get('query') || urlParams.get('term');
        if (searchTerm) {
            metadata.searchTerm = searchTerm;
        }

        // Capture filter property from data attributes if available
        const filterProperty = element.dataset.merchFilterProperty;
        if (filterProperty) {
            metadata.filterProperty = filterProperty;
        }

        return Object.keys(metadata).length > 0 ? metadata : null;
    }

    _startBatchTimer() {
        setInterval(() => this._flush(), this.options.batchInterval);
        window.addEventListener('beforeunload', () => this._flush());
    }

    _flush() {
        if (this._eventQueue.length === 0) {
            return;
        }

        const events = [...this._eventQueue];
        this._eventQueue = [];

        const blob = new Blob([JSON.stringify({ events })], { type: 'application/json' });
        navigator.sendBeacon(this.options.trackingUrl, blob);
    }
}

window.PluginManager.register('MerchClickTracking', MerchClickTrackingPlugin, '[data-merch-click-tracking]');

import MerchFilterExpandPlugin from './plugin/merch-filter-expand.plugin';
window.PluginManager.register('MerchFilterExpand', MerchFilterExpandPlugin, '[data-merch-filter-expand]');
