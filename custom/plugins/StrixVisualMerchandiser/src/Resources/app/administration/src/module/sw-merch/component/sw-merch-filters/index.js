import template from './sw-merch-filters.html.twig';

const { Component, Context, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-merch-filters', {
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
            templates: [],
            categoryLink: null,
            isLoading: false,
            showCreateModal: false,
            editingTemplate: null,
            newTemplate: this.getEmptyTemplate(),
            propertyGroups: [],
        };
    },

    computed: {
        templateRepository() {
            return this.repositoryFactory.create('strix_merch_filter_template');
        },

        templateCategoryRepository() {
            return this.repositoryFactory.create('strix_merch_filter_template_category');
        },

        propertyGroupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        canEdit() {
            return this.acl.isAdmin() || this.acl.can('strix_merch_filter_template:create');
        },

        overrideModeOptions() {
            return [
                { value: 'active', label: this.$tc('sw-merch.filters.overrideMode.active') },
                { value: 'inherit', label: this.$tc('sw-merch.filters.overrideMode.inherit') },
                { value: 'disabled', label: this.$tc('sw-merch.filters.overrideMode.disabled') },
            ];
        },

        displayTypeOptions() {
            return [
                { value: 'checkbox', label: this.$tc('sw-merch.filters.displayTypes.checkbox') },
                { value: 'link', label: this.$tc('sw-merch.filters.displayTypes.link') },
                { value: 'colorbox', label: this.$tc('sw-merch.filters.displayTypes.colorbox') },
                { value: 'slider', label: this.$tc('sw-merch.filters.displayTypes.slider') },
                { value: 'bucket', label: this.$tc('sw-merch.filters.displayTypes.bucket') },
            ];
        },

        linkedTemplate() {
            if (!this.categoryLink) return null;
            return this.templates.find((t) => t.id === this.categoryLink.filterTemplateId) || null;
        },
    },

    watch: {
        categoryId: {
            immediate: true,
            handler(newVal) {
                if (newVal) this.loadData();
            },
        },
    },

    methods: {
        getEmptyTemplate() {
            return {
                name: '',
                filters: [],
                active: true,
            };
        },

        async loadData() {
            this.isLoading = true;

            const [templates, link, groups] = await Promise.all([
                this.loadTemplates(),
                this.loadCategoryLink(),
                this.loadPropertyGroups(),
            ]);

            this.templates = templates;
            this.categoryLink = link;
            this.propertyGroups = groups;
            this.isLoading = false;
        },

        async loadTemplates() {
            const result = await this.templateRepository.search(new Criteria().addSorting(Criteria.sort('name', 'ASC')), Context.api);
            return [...result];
        },

        async loadCategoryLink() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('categoryId', this.categoryId));
            criteria.setLimit(1);
            const result = await this.templateCategoryRepository.search(criteria, Context.api);
            return result.first() || null;
        },

        async loadPropertyGroups() {
            const result = await this.propertyGroupRepository.search(new Criteria().setLimit(500), Context.api);
            return [...result];
        },

        onCreateTemplate() {
            this.editingTemplate = null;
            this.newTemplate = this.getEmptyTemplate();
            this.showCreateModal = true;
        },

        onEditTemplate(template) {
            this.editingTemplate = template;
            this.newTemplate = {
                name: template.name,
                filters: JSON.parse(JSON.stringify(template.filters)),
                active: template.active,
            };
            this.showCreateModal = true;
        },

        onAddFilter() {
            this.newTemplate.filters.push({
                propertyGroupId: '',
                label: { en: '', nl: '', de: '' },
                displayType: 'checkbox',
                position: this.newTemplate.filters.length + 1,
                collapsed: false,
                active: true,
            });
        },

        onRemoveFilter(index) {
            this.newTemplate.filters.splice(index, 1);
        },

        onCloseModal() {
            this.showCreateModal = false;
            this.editingTemplate = null;
        },

        async onSaveTemplate() {
            if (!this.newTemplate.name) {
                this.createNotificationError({ message: this.$tc('sw-merch.general.nameRequired') });
                return;
            }

            try {
                if (this.editingTemplate) {
                    this.editingTemplate.name = this.newTemplate.name;
                    this.editingTemplate.filters = this.newTemplate.filters;
                    this.editingTemplate.active = this.newTemplate.active;
                    await this.templateRepository.save(this.editingTemplate, Context.api);
                } else {
                    const tmpl = this.templateRepository.create(Context.api);
                    Object.assign(tmpl, this.newTemplate);
                    await this.templateRepository.save(tmpl, Context.api);
                }

                this.createNotificationSuccess({ message: this.$tc('sw-merch.filters.templateSaved') });
                this.onCloseModal();
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: error.message || this.$tc('sw-merch.filters.failedToSave') });
            }
        },

        async onDeleteTemplate(template) {
            try {
                await this.templateRepository.delete(template.id, Context.api);
                this.createNotificationSuccess({ message: this.$tc('sw-merch.filters.templateDeleted') });
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.filters.failedToDelete') });
            }
        },

        async onLinkTemplate(templateId) {
            try {
                if (this.categoryLink) {
                    this.categoryLink.filterTemplateId = templateId;
                    await this.templateCategoryRepository.save(this.categoryLink, Context.api);
                } else {
                    const link = this.templateCategoryRepository.create(Context.api);
                    link.filterTemplateId = templateId;
                    link.categoryId = this.categoryId;
                    link.overrideMode = 'active';
                    await this.templateCategoryRepository.save(link, Context.api);
                }
                this.createNotificationSuccess({ message: this.$tc('sw-merch.filters.templateLinked') });
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.filters.failedToLink') });
            }
        },

        async onUnlinkTemplate() {
            if (!this.categoryLink) return;
            try {
                await this.templateCategoryRepository.delete(this.categoryLink.id, Context.api);
                this.createNotificationSuccess({ message: this.$tc('sw-merch.filters.templateUnlinked') });
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.filters.failedToUnlink') });
            }
        },

        async onOverrideModeChange(mode) {
            if (!this.categoryLink || !mode) return;
            try {
                this.categoryLink.overrideMode = mode;
                await this.templateCategoryRepository.save(this.categoryLink, Context.api);
                await this.loadData();
            } catch (error) {
                console.error('onOverrideModeChange error:', error);
                this.createNotificationError({ message: this.$tc('sw-merch.filters.failedToUpdateMode') });
            }
        },

        getPropertyGroupName(id) {
            const group = this.propertyGroups.find((g) => g.id === id);
            return group?.translated?.name || group?.name || id;
        },
    },
});
