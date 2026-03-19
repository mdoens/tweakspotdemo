import template from './sw-merch-personalization.html.twig';

const { Component, Context, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-merch-personalization', {
    template,

    inject: ['repositoryFactory', 'acl'],

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
            segments: [],
            isLoading: false,
            showCreateModal: false,
            editingSegment: null,
            newSegment: this.getEmptySegment(),
        };
    },

    computed: {
        segmentRepository() {
            return this.repositoryFactory.create('strix_merch_customer_segment');
        },

        canEdit() {
            return this.acl.isAdmin() || this.acl.can('strix_merch_customer_segment:create');
        },

        canDelete() {
            return this.acl.isAdmin() || this.acl.can('strix_merch_customer_segment:delete');
        },

        typeOptions() {
            return [
                { value: 'purchase_history', label: this.$tc('sw-merch.personalization.types.purchase_history') },
                { value: 'click_behavior', label: this.$tc('sw-merch.personalization.types.click_behavior') },
                { value: 'filter_behavior', label: this.$tc('sw-merch.personalization.types.filter_behavior') },
                { value: 'combined', label: this.$tc('sw-merch.personalization.types.combined') },
            ];
        },
    },

    created() {
        this.loadSegments();
    },

    methods: {
        getEmptySegment() {
            return {
                name: '',
                type: 'purchase_history',
                config: {},
                active: true,
                boostFactor: 1.0,
            };
        },

        async loadSegments() {
            this.isLoading = true;
            try {
                const criteria = new Criteria();
                criteria.addSorting(Criteria.sort('name', 'ASC'));
                criteria.addAssociation('memberships');

                const result = await this.segmentRepository.search(criteria, Context.api);
                this.segments = [...result];
            } catch (error) {
                this.createNotificationError({ message: error.message || 'Failed to load segments' });
            } finally {
                this.isLoading = false;
            }
        },

        onCreateSegment() {
            this.editingSegment = null;
            this.newSegment = this.getEmptySegment();
            this.showCreateModal = true;
        },

        onEditSegment(segment) {
            this.editingSegment = segment;
            this.newSegment = {
                name: segment.name,
                type: segment.type,
                config: JSON.parse(JSON.stringify(segment.config)),
                active: segment.active,
                boostFactor: segment.boostFactor,
            };
            this.showCreateModal = true;
        },

        onCloseModal() {
            this.showCreateModal = false;
            this.editingSegment = null;
        },

        async onSaveSegment() {
            if (!this.newSegment.name) {
                this.createNotificationError({ message: 'Name is required' });
                return;
            }

            try {
                if (this.editingSegment) {
                    // Update existing entity proxy — repository.save() requires a proxy, not a plain object
                    Object.assign(this.editingSegment, this.newSegment);
                    await this.segmentRepository.save(this.editingSegment, Context.api);
                } else {
                    const segment = this.segmentRepository.create(Context.api);
                    Object.assign(segment, this.newSegment);
                    await this.segmentRepository.save(segment, Context.api);
                }

                this.createNotificationSuccess({ message: 'Segment saved' });
                this.onCloseModal();
                await this.loadSegments();
            } catch (error) {
                this.createNotificationError({ message: error.message || 'Failed to save' });
            }
        },

        async onDeleteSegment(segment) {
            try {
                await this.segmentRepository.delete(segment.id, Context.api);
                this.createNotificationSuccess({ message: 'Segment deleted' });
                await this.loadSegments();
            } catch (error) {
                this.createNotificationError({ message: 'Failed to delete' });
            }
        },

        getMemberCount(segment) {
            return segment.memberships?.length || 0;
        },

        getTypeLabel(type) {
            return this.$tc(`sw-merch.personalization.types.${type}`) || type;
        },
    },
});
