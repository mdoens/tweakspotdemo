import template from './sw-merch-category-tree-item.html.twig';

const { Component } = Shopware;

Component.register('sw-merch-category-tree-item', {
    template,

    emits: ['select', 'toggle'],

    props: {
        item: {
            type: Object,
            required: true,
        },
        selectedId: {
            type: String,
            required: false,
            default: null,
        },
        indicators: {
            type: Object,
            required: false,
            default: () => ({}),
        },
        depth: {
            type: Number,
            required: false,
            default: 0,
        },
    },

    data() {
        return {
            expanded: false,
        };
    },

    computed: {
        isSelected() {
            return this.selectedId === this.item.id;
        },

        hasChildren() {
            return this.item.children && this.item.children.length > 0;
        },

        indicatorText() {
            const ind = this.indicators[this.item.id];
            if (!ind) return '';
            const parts = [];
            if (ind.rules > 0) parts.push(`${ind.rules}R`);
            if (ind.pins > 0) parts.push(`${ind.pins}P`);
            if (ind.filters > 0) parts.push(`${ind.filters}F`);
            return parts.join(' ');
        },

        rowClasses() {
            return {
                'sw-merch-category-tree-item__row': true,
                'sw-merch-category-tree-item__row--selected': this.isSelected,
            };
        },
    },

    methods: {
        onToggle() {
            if (!this.hasChildren) return;
            this.expanded = !this.expanded;
        },

        onClick() {
            this.$emit('select', this.item);
        },

        onChildSelect(item) {
            this.$emit('select', item);
        },

        onChildToggle(item) {
            this.$emit('toggle', item);
        },
    },
});
