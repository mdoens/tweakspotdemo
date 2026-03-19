import template from './sw-merch-pins.html.twig';
import './sw-merch-pins.scss';

const { Component, Context, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-merch-pins', {
    template,

    inject: ['repositoryFactory', 'acl'],

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
            pins: [],
            isLoading: false,
            showCreateModal: false,
            showProductSearch: false,
            editingPin: null,
            newPin: this.getEmptyPin(),
            selectedProduct: null,
        };
    },

    computed: {
        pinRepository() {
            return this.repositoryFactory.create('strix_merch_pin');
        },

        canEdit() {
            return this.acl.isAdmin() || this.acl.can('strix_merch_pin:create');
        },

        canDelete() {
            return this.acl.isAdmin() || this.acl.can('strix_merch_pin:delete');
        },

        labelOptions() {
            return [
                { value: 'sponsored', label: this.$tc('sw-merch.pins.labels.sponsored') },
                { value: 'new_arrival', label: this.$tc('sw-merch.pins.labels.new_arrival') },
                { value: 'staff_pick', label: this.$tc('sw-merch.pins.labels.staff_pick') },
                { value: 'sale', label: this.$tc('sw-merch.pins.labels.sale') },
                { value: 'trending', label: this.$tc('sw-merch.pins.labels.trending') },
                { value: 'custom', label: this.$tc('sw-merch.pins.labels.custom') },
            ];
        },

        nextPosition() {
            if (this.pins.length === 0) return 1;
            return Math.max(...this.pins.map((p) => p.position)) + 1;
        },

        sortedPins() {
            return [...this.pins].sort((a, b) => a.position - b.position);
        },
    },

    watch: {
        categoryId: {
            immediate: true,
            handler(newVal) {
                if (newVal) {
                    this.loadPins();
                }
            },
        },
    },

    methods: {
        getEmptyPin() {
            return {
                position: 1,
                label: 'sponsored',
                customLabel: { en: '', nl: '', de: '' },
                active: true,
                validFrom: null,
                validUntil: null,
            };
        },

        async loadPins() {
            if (!this.categoryId) return;

            this.isLoading = true;

            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('categoryId', this.categoryId));
            criteria.addSorting(Criteria.sort('position', 'ASC'));
            criteria.addAssociation('product');
            criteria.addAssociation('product.cover.media');

            const result = await this.pinRepository.search(criteria, Context.api);
            this.pins = [...result];

            this.isLoading = false;
        },

        onAddPin() {
            this.editingPin = null;
            this.selectedProduct = null;
            this.newPin = this.getEmptyPin();
            this.newPin.position = this.nextPosition;
            this.showCreateModal = true;
        },

        onEditPin(pin) {
            this.editingPin = pin;
            this.selectedProduct = pin.product;
            this.newPin = {
                position: pin.position,
                label: pin.label || 'sponsored',
                customLabel: pin.customLabel || { en: '', nl: '', de: '' },
                active: pin.active,
                validFrom: pin.validFrom,
                validUntil: pin.validUntil,
            };
            this.showCreateModal = true;
        },

        onOpenProductSearch() {
            this.showProductSearch = true;
        },

        onProductSelected(product) {
            this.selectedProduct = product;
            this.showProductSearch = false;
        },

        onCloseProductSearch() {
            this.showProductSearch = false;
        },

        onCloseModal() {
            this.showCreateModal = false;
            this.editingPin = null;
            this.selectedProduct = null;
        },

        // S7.7: Check for position conflict
        hasPositionConflict(position) {
            const editingId = this.editingPin?.id;
            return this.pins.some((p) => p.position === position && p.id !== editingId);
        },

        getPositionConflictMessage(position) {
            const conflicting = this.pins.find((p) => p.position === position && p.id !== this.editingPin?.id);
            if (!conflicting) return null;
            const name = conflicting.product?.translated?.name || conflicting.product?.name || conflicting.productId;
            return `Position ${position} is already used by "${name}". The pin with the lowest product ID will take priority.`;
        },

        async onSavePin() {
            if (!this.selectedProduct) {
                this.createNotificationError({ message: this.$tc('sw-merch.general.selectProductRequired') });
                return;
            }

            // S7.7: Warn on position conflict (but allow save)
            if (this.hasPositionConflict(this.newPin.position)) {
                this.createNotificationWarning({
                    message: this.getPositionConflictMessage(this.newPin.position),
                });
            }

            try {
                if (this.editingPin) {
                    this.editingPin.position = this.newPin.position;
                    this.editingPin.label = this.newPin.label;
                    this.editingPin.customLabel = this.newPin.label === 'custom' ? this.newPin.customLabel : null;
                    this.editingPin.active = this.newPin.active;
                    this.editingPin.validFrom = this.newPin.validFrom;
                    this.editingPin.validUntil = this.newPin.validUntil;
                    await this.pinRepository.save(this.editingPin, Context.api);
                } else {
                    const pin = this.pinRepository.create(Context.api);
                    pin.categoryId = this.categoryId;
                    pin.productId = this.selectedProduct.id;
                    pin.position = this.newPin.position;
                    pin.label = this.newPin.label;
                    pin.customLabel = this.newPin.label === 'custom' ? this.newPin.customLabel : null;
                    pin.active = this.newPin.active;
                    pin.validFrom = this.newPin.validFrom;
                    pin.validUntil = this.newPin.validUntil;

                    await this.pinRepository.save(pin, Context.api);
                }

                this.createNotificationSuccess({
                    message: this.editingPin ? this.$tc('sw-merch.pins.pinUpdated') : this.$tc('sw-merch.pins.pinCreated'),
                });
                this.onCloseModal();
                await this.loadPins();
            } catch (error) {
                this.createNotificationError({
                    message: error.message || this.$tc('sw-merch.pins.failedToSave'),
                });
            }
        },

        async onDeletePin(pin) {
            try {
                await this.pinRepository.delete(pin.id, Context.api);
                this.createNotificationSuccess({ message: this.$tc('sw-merch.pins.pinDeleted') });
                await this.loadPins();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.pins.failedToDelete') });
            }
        },

        async onToggleActive(pin) {
            try {
                pin.active = !pin.active;
                await this.pinRepository.save(pin, Context.api);
                await this.loadPins();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.pins.failedToUpdate') });
            }
        },

        getProductName(pin) {
            return pin.product?.translated?.name || pin.product?.name || pin.productId;
        },

        getProductImage(pin) {
            return pin.product?.cover?.media?.url || null;
        },

        getLabelDisplay(label) {
            return this.$tc(`sw-merch.pins.labels.${label}`) || label;
        },

        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString(undefined, {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        },

        isScheduled(pin) {
            if (!pin.validFrom) return false;
            return new Date(pin.validFrom) > new Date();
        },

        isExpired(pin) {
            if (!pin.validUntil) return false;
            return new Date(pin.validUntil) < new Date();
        },
    },
});
