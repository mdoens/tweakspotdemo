import template from './sw-merch-overview.html.twig';
import './sw-merch-overview.scss';

const { Component } = Shopware;

Component.register('sw-merch-overview', {
    template,

    data() {
        return {
            selectedCategoryId: null,
            activeTab: 'rules',
        };
    },

    computed: {
        tabItems() {
            return [
                { name: 'rules', label: this.$tc('sw-merch.rules.title') },
                { name: 'pins', label: this.$tc('sw-merch.pins.title') },
                { name: 'grid', label: 'Grid' },
                { name: 'filters', label: this.$tc('sw-merch.filters.title') },
                { name: 'sorting', label: this.$tc('sw-merch.sorting.title') },
                { name: 'personalization', label: this.$tc('sw-merch.personalization.title') },
                { name: 'analytics', label: this.$tc('sw-merch.analytics.title') },
                { name: 'audit', label: this.$tc('sw-merch.auditLog.title') },
            ];
        },
    },

    methods: {
        onCategorySelect(categoryId) {
            this.selectedCategoryId = categoryId;
        },

        onTabChange(tab) {
            this.activeTab = tab;
        },
    },
});
