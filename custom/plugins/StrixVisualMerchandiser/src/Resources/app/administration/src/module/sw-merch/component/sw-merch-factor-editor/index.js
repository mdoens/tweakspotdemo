import template from './sw-merch-factor-editor.html.twig';
import './sw-merch-factor-editor.scss';

const { Component } = Shopware;

Component.register('sw-merch-factor-editor', {
    template,

    emits: ['update:config'],

    props: {
        config: {
            type: Object,
            required: true,
        },
        maxFactors: {
            type: Number,
            required: false,
            default: 5,
        },
    },

    computed: {
        factors() {
            return this.config.factors || [];
        },

        totalWeight() {
            return this.factors.reduce((sum, f) => sum + (f.weight || 0), 0);
        },

        isValid() {
            return this.totalWeight === 100 && this.factors.length > 0;
        },

        canAddFactor() {
            return this.factors.length < this.maxFactors;
        },

        fieldOptions() {
            return [
                { value: 'stock', label: 'Stock' },
                { value: 'price', label: 'Price' },
                { value: 'sales', label: 'Sales (total)' },
                { value: 'ratingAverage', label: 'Rating Average' },
                { value: 'releaseDate', label: 'Release Date' },
                { value: 'createdAt', label: 'Created At' },
                { value: 'sales_30d', label: 'Sales (30 days)' },
                { value: 'margin_pct', label: 'Margin %' },
                { value: 'popularity', label: 'Popularity' },
            ];
        },

        directionOptions() {
            return [
                { value: 'desc', label: 'High to Low' },
                { value: 'asc', label: 'Low to High' },
            ];
        },
    },

    methods: {
        onAddFactor() {
            if (!this.canAddFactor) return;

            const newFactors = [...this.factors, {
                field: 'sales',
                weight: 0,
                direction: 'desc',
            }];

            this.emitUpdate(newFactors);
        },

        onRemoveFactor(index) {
            const newFactors = this.factors.filter((_, i) => i !== index);
            this.emitUpdate(newFactors);
        },

        onFieldChange(index, field) {
            const newFactors = [...this.factors];
            newFactors[index] = { ...newFactors[index], field };
            this.emitUpdate(newFactors);
        },

        onWeightChange(index, weight) {
            const newFactors = [...this.factors];
            newFactors[index] = { ...newFactors[index], weight: parseInt(weight, 10) || 0 };
            this.emitUpdate(newFactors);
        },

        onDirectionChange(index, direction) {
            const newFactors = [...this.factors];
            newFactors[index] = { ...newFactors[index], direction };
            this.emitUpdate(newFactors);
        },

        onDistributeEvenly() {
            if (this.factors.length === 0) return;

            const baseWeight = Math.floor(100 / this.factors.length);
            const remainder = 100 - baseWeight * this.factors.length;

            const newFactors = this.factors.map((f, i) => ({
                ...f,
                weight: baseWeight + (i < remainder ? 1 : 0),
            }));

            this.emitUpdate(newFactors);
        },

        emitUpdate(factors) {
            this.$emit('update:config', { ...this.config, factors });
        },

        getWeightBarWidth(weight) {
            return `${Math.min(weight, 100)}%`;
        },

        getWeightBarColor(weight) {
            if (this.totalWeight > 100) return '#ff6b6b';
            if (this.totalWeight === 100) return '#37d046';
            return '#189eff';
        },
    },
});
