import template from './sw-merch-audit-log.html.twig';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-merch-audit-log', {
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
            logs: [],
            isLoading: false,
            page: 1,
            limit: 25,
            total: 0,
            entityTypeFilter: null,
            actionFilter: null,
            selectedLog: null,
        };
    },

    computed: {
        auditLogRepository() {
            return this.repositoryFactory.create('strix_merch_audit_log');
        },

        totalPages() {
            return Math.ceil(this.total / this.limit);
        },

        entityTypeOptions() {
            return [
                { value: null, label: 'All' },
                { value: 'strix_merch_rule', label: 'Rule' },
                { value: 'strix_merch_pin', label: 'Pin' },
                { value: 'strix_merch_filter_template', label: 'Filter Template' },
                { value: 'strix_merch_sorting_template', label: 'Sorting Template' },
                { value: 'strix_merch_customer_segment', label: 'Segment' },
            ];
        },

        actionOptions() {
            return [
                { value: null, label: 'All' },
                { value: 'create', label: this.$tc('sw-merch.auditLog.actions.create') },
                { value: 'update', label: this.$tc('sw-merch.auditLog.actions.update') },
                { value: 'delete', label: this.$tc('sw-merch.auditLog.actions.delete') },
                { value: 'activate', label: this.$tc('sw-merch.auditLog.actions.activate') },
                { value: 'deactivate', label: this.$tc('sw-merch.auditLog.actions.deactivate') },
            ];
        },
    },

    created() {
        this.loadLogs();
    },

    watch: {
        categoryId() { this.page = 1; this.loadLogs(); },
        entityTypeFilter() { this.page = 1; this.loadLogs(); },
        actionFilter() { this.page = 1; this.loadLogs(); },
    },

    methods: {
        async loadLogs() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            // Filter by selected category if available
            if (this.categoryId) {
                criteria.addFilter(Criteria.multi('OR', [
                    Criteria.equals('categoryId', this.categoryId),
                    Criteria.equals('categoryId', null),
                ]));
            }

            if (this.entityTypeFilter) {
                criteria.addFilter(Criteria.equals('entityType', this.entityTypeFilter));
            }
            if (this.actionFilter) {
                criteria.addFilter(Criteria.equals('action', this.actionFilter));
            }

            const result = await this.auditLogRepository.search(criteria, Context.api);
            this.logs = [...result];
            this.total = result.total;

            this.isLoading = false;
        },

        onViewDetails(log) {
            this.selectedLog = log;
        },

        onCloseDetails() {
            this.selectedLog = null;
        },

        onPageChange(page) {
            this.page = page;
            this.loadLogs();
        },

        onExportCsv() {
            const headers = ['Date', 'Entity Type', 'Entity ID', 'Action', 'User'];
            const rows = this.logs.map((l) => [
                l.createdAt,
                l.entityType,
                l.entityId,
                l.action,
                l.userId || '',
            ]);

            const escapeCsv = (val) => '"' + String(val).replace(/"/g, '""') + '"';
            const csv = [headers.map(escapeCsv).join(','), ...rows.map((r) => r.map(escapeCsv).join(','))].join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'merch-audit-log.csv';
            a.click();
            URL.revokeObjectURL(url);
        },

        getActionLabel(action) {
            return this.$tc(`sw-merch.auditLog.actions.${action}`) || action;
        },

        getActionColor(action) {
            const colors = {
                create: '#37d046',
                update: '#189eff',
                delete: '#ff6b6b',
                activate: '#37d046',
                deactivate: '#ffab00',
            };
            return colors[action] || '#758698';
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
                + ' ' + date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        },

        formatChanges(changes) {
            return JSON.stringify(changes, null, 2);
        },
    },
});
