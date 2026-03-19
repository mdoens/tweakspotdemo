import template from './sw-merch-analytics.html.twig';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-merch-analytics', {
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
            aggregates: [],
            addToCartCount: 0,
            isLoading: false,
            period: '30',
            sortBy: 'totalClicks',
            sortDir: 'DESC',
            productNames: {},
        };
    },

    computed: {
        aggregateRepository() {
            return this.repositoryFactory.create('strix_merch_click_aggregate');
        },

        clickEventRepository() {
            return this.repositoryFactory.create('strix_merch_click_event');
        },

        periodOptions() {
            return [
                { value: '7', label: 'Last 7 days' },
                { value: '30', label: 'Last 30 days' },
                { value: '90', label: 'Last 90 days' },
            ];
        },

        sortedAggregates() {
            return [...this.aggregates].sort((a, b) => {
                const aVal = a[this.sortBy] || 0;
                const bVal = b[this.sortBy] || 0;
                return this.sortDir === 'DESC' ? bVal - aVal : aVal - bVal;
            });
        },

        totalClicks() {
            return this.aggregates.reduce((sum, a) => sum + (a.totalClicks || 0), 0);
        },

        uniqueProductCount() {
            const ids = new Set(this.aggregates.map((a) => a.productId));
            return ids.size;
        },

        avgCtr() {
            const withCtr = this.aggregates.filter((a) => a.ctr != null);
            if (withCtr.length === 0) return 0;
            return withCtr.reduce((sum, a) => sum + a.ctr, 0) / withCtr.length;
        },

        addToCartRate() {
            if (this.totalClicks === 0) return 0;
            return this.addToCartCount / this.totalClicks;
        },

        positionHeatmap() {
            const positions = {};
            for (let i = 1; i <= 24; i++) {
                positions[i] = { position: i, clicks: 0, ctr: 0, count: 0 };
            }

            for (const agg of this.aggregates) {
                const pos = Math.round(agg.avgPosition || 0);
                if (pos >= 1 && pos <= 24) {
                    positions[pos].clicks += agg.totalClicks || 0;
                    if (agg.ctr != null) {
                        positions[pos].ctr += agg.ctr;
                        positions[pos].count += 1;
                    }
                }
            }

            // Calculate average CTR per position
            for (let i = 1; i <= 24; i++) {
                if (positions[i].count > 0) {
                    positions[i].avgCtr = positions[i].ctr / positions[i].count;
                } else {
                    positions[i].avgCtr = 0;
                }
            }

            return Object.values(positions);
        },

        heatmapMaxClicks() {
            return Math.max(1, ...this.positionHeatmap.map((p) => p.clicks));
        },
    },

    watch: {
        categoryId: {
            immediate: true,
            handler(newVal) {
                if (newVal) this.loadData();
            },
        },
        period() {
            this.loadData();
        },
    },

    methods: {
        heatmapCellStyle(cell) {
            const ratio = this.heatmapMaxClicks > 0 ? cell.clicks / this.heatmapMaxClicks : 0;
            // Interpolate from grey (#e5e7eb) to green (#22c55e)
            const r = Math.round(229 - ratio * (229 - 34));
            const g = Math.round(231 - ratio * (231 - 197));
            const b = Math.round(235 - ratio * (235 - 94));
            return {
                background: `rgb(${r}, ${g}, ${b})`,
                padding: '12px 8px',
                borderRadius: '6px',
                textAlign: 'center',
                cursor: 'default',
                transition: 'background 0.2s',
            };
        },

        async loadData() {
            if (!this.categoryId) return;

            this.isLoading = true;

            const cutoff = new Date();
            cutoff.setDate(cutoff.getDate() - parseInt(this.period, 10));

            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('categoryId', this.categoryId));
            criteria.addFilter(Criteria.range('date', { gte: cutoff.toISOString().split('T')[0] }));
            criteria.addSorting(Criteria.sort('totalClicks', 'DESC'));
            criteria.setLimit(100);

            const [aggregateResult] = await Promise.all([
                this.aggregateRepository.search(criteria, Context.api),
                this.loadAddToCartCount(cutoff),
            ]);
            this.aggregates = [...aggregateResult];

            // Resolve product names for display
            await this.loadProductNames();

            this.isLoading = false;
        },

        async loadAddToCartCount(cutoff) {
            try {
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equals('categoryId', this.categoryId));
                criteria.addFilter(Criteria.equals('eventType', 'add_to_cart'));
                criteria.addFilter(Criteria.range('createdAt', { gte: cutoff.toISOString() }));
                criteria.setLimit(1);
                criteria.setTotalCountMode(1);

                const result = await this.clickEventRepository.search(criteria, Context.api);
                this.addToCartCount = result.total || 0;
            } catch {
                this.addToCartCount = 0;
            }
        },

        async loadProductNames() {
            const productIds = [...new Set(this.aggregates.map((a) => a.productId).filter(Boolean))];
            if (productIds.length === 0) return;

            try {
                const productRepo = this.repositoryFactory.create('product');
                const criteria = new Criteria();
                criteria.setIds(productIds);
                criteria.setLimit(productIds.length);

                const result = await productRepo.search(criteria, Context.api);
                const names = {};
                for (const product of result) {
                    names[product.id] = {
                        name: product.translated?.name || product.name || product.id,
                        number: product.productNumber || '',
                    };
                }
                this.productNames = names;
            } catch {
                // Non-critical — table still shows IDs
            }
        },

        getProductDisplay(productId) {
            const info = this.productNames[productId];
            if (info) {
                return info.name;
            }
            // Truncate UUID for readability
            return productId ? productId.substring(0, 8) + '...' : '-';
        },

        getProductNumber(productId) {
            const info = this.productNames[productId];
            return info?.number || '';
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
        },

        onExportCsv() {
            const headers = ['Product ID', 'Date', 'Total Clicks', 'Avg Position', 'CTR'];
            const rows = this.sortedAggregates.map((a) => [
                a.productId,
                a.date,
                a.totalClicks,
                a.avgPosition?.toFixed(1) || '',
                a.ctr?.toFixed(4) || '',
            ]);

            const escapeCsv = (val) => '"' + String(val).replace(/"/g, '""') + '"';
            const csv = [headers.map(escapeCsv).join(','), ...rows.map((r) => r.map(escapeCsv).join(','))].join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `merch-analytics-${this.categoryId}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        },
    },
});
