import template from './sw-merch-bulk-edit.html.twig';

const { Component, Context, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-merch-bulk-edit', {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    props: {
        categoryId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            rules: [],
            selectedIds: new Set(),
            isLoading: false,
            showConfirmModal: false,
            bulkAction: null,
            bulkSchedule: {
                validFrom: null,
                validUntil: null,
            },
        };
    },

    computed: {
        ruleRepository() {
            return this.repositoryFactory.create('strix_merch_rule');
        },

        hasSelection() {
            return this.selectedIds.size > 0;
        },

        selectionCount() {
            return this.selectedIds.size;
        },

        allSelected() {
            return this.rules.length > 0 && this.selectedIds.size === this.rules.length;
        },
    },

    created() {
        this.loadRules();
    },

    methods: {
        async loadRules() {
            this.isLoading = true;
            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort('name', 'ASC'));
            criteria.setLimit(500);

            const result = await this.ruleRepository.search(criteria, Context.api);
            this.rules = [...result];
            this.isLoading = false;
        },

        onToggleSelect(ruleId) {
            const newSet = new Set(this.selectedIds);
            if (newSet.has(ruleId)) {
                newSet.delete(ruleId);
            } else {
                newSet.add(ruleId);
            }
            this.selectedIds = newSet;
        },

        onToggleAll() {
            if (this.allSelected) {
                this.selectedIds = new Set();
            } else {
                this.selectedIds = new Set(this.rules.map((r) => r.id));
            }
        },

        onBulkActivate() {
            this.bulkAction = 'activate';
            this.showConfirmModal = true;
        },

        onBulkDeactivate() {
            this.bulkAction = 'deactivate';
            this.showConfirmModal = true;
        },

        onBulkSchedule() {
            this.bulkAction = 'schedule';
            this.bulkSchedule = { validFrom: null, validUntil: null };
            this.showConfirmModal = true;
        },

        onBulkDelete() {
            this.bulkAction = 'delete';
            this.showConfirmModal = true;
        },

        onCloseConfirm() {
            this.showConfirmModal = false;
            this.bulkAction = null;
        },

        async onConfirmBulk() {
            const ids = [...this.selectedIds];

            try {
                for (const id of ids) {
                    const rule = this.rules.find((r) => r.id === id);
                    switch (this.bulkAction) {
                        case 'activate':
                            if (rule) { rule.active = true; await this.ruleRepository.save(rule, Context.api); }
                            break;
                        case 'deactivate':
                            if (rule) { rule.active = false; await this.ruleRepository.save(rule, Context.api); }
                            break;
                        case 'schedule':
                            if (rule) {
                                rule.validFrom = this.bulkSchedule.validFrom;
                                rule.validUntil = this.bulkSchedule.validUntil;
                                await this.ruleRepository.save(rule, Context.api);
                            }
                            break;
                        case 'delete':
                            await this.ruleRepository.delete(id, Context.api);
                            break;
                    }
                }

                this.createNotificationSuccess({
                    message: `${ids.length} rules ${this.bulkAction}d`,
                });

                this.selectedIds = new Set();
                this.onCloseConfirm();
                await this.loadRules();
            } catch (error) {
                this.createNotificationError({
                    message: error.message || `Failed to ${this.bulkAction} rules`,
                });
            }
        },
    },
});
