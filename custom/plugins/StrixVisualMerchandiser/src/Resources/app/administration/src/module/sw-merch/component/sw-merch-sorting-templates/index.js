import template from './sw-merch-sorting-templates.html.twig';

const { Component, Context, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-merch-sorting-templates', {
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
            availableRules: [],
            isLoading: false,
            showCreateModal: false,
            editingTemplate: null,
            newTemplate: this.getEmptyTemplate(),
        };
    },

    computed: {
        templateRepository() {
            return this.repositoryFactory.create('strix_merch_sorting_template');
        },

        templateCategoryRepository() {
            return this.repositoryFactory.create('strix_merch_sorting_template_category');
        },

        ruleRepository() {
            return this.repositoryFactory.create('strix_merch_rule');
        },

        canEdit() {
            return this.acl.isAdmin() || this.acl.can('strix_merch_sorting_template:create');
        },

        linkedTemplate() {
            if (!this.categoryLink) return null;
            return this.templates.find((t) => t.id === this.categoryLink.sortingTemplateId) || null;
        },

        sortTypeOptions() {
            return [
                { value: 'merch_rule', label: 'Merchandising Rule' },
                { value: 'shopware', label: 'Shopware Field' },
            ];
        },

        shopwareFieldOptions() {
            return [
                { value: 'price', label: 'Price' },
                { value: 'name', label: 'Name' },
                { value: 'createdAt', label: 'Created At' },
                { value: 'ratingAverage', label: 'Rating' },
                { value: 'sales', label: 'Sales' },
            ];
        },

        overrideModeOptions() {
            return [
                { value: 'active', label: this.$tc('sw-merch.filters.overrideMode.active') },
                { value: 'inherit', label: this.$tc('sw-merch.filters.overrideMode.inherit') },
                { value: 'disabled', label: this.$tc('sw-merch.filters.overrideMode.disabled') },
            ];
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
                sortOptions: [],
                active: true,
            };
        },

        async loadData() {
            this.isLoading = true;

            const [templates, link, rules] = await Promise.all([
                this.loadTemplates(),
                this.loadCategoryLink(),
                this.loadRules(),
            ]);

            this.templates = templates;
            this.categoryLink = link;
            this.availableRules = rules;
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

        async loadRules() {
            const result = await this.ruleRepository.search(new Criteria().addFilter(Criteria.equals('active', true)), Context.api);
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
                sortOptions: JSON.parse(JSON.stringify(template.sortOptions)),
                active: template.active,
            };
            this.showCreateModal = true;
        },

        onAddSortOption() {
            this.newTemplate.sortOptions.push({
                label: { en: '', nl: '', de: '' },
                type: 'shopware',
                field: 'price',
                direction: 'asc',
                ruleId: null,
                position: this.newTemplate.sortOptions.length + 1,
                active: true,
            });
        },

        onRemoveSortOption(index) {
            this.newTemplate.sortOptions.splice(index, 1);
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
                    this.editingTemplate.sortOptions = this.newTemplate.sortOptions;
                    this.editingTemplate.active = this.newTemplate.active;
                    await this.templateRepository.save(this.editingTemplate, Context.api);
                } else {
                    const tmpl = this.templateRepository.create(Context.api);
                    tmpl.name = this.newTemplate.name;
                    tmpl.sortOptions = this.newTemplate.sortOptions;
                    tmpl.active = this.newTemplate.active;
                    await this.templateRepository.save(tmpl, Context.api);
                }

                this.createNotificationSuccess({ message: this.$tc('sw-merch.sorting.templateSaved') });
                this.onCloseModal();
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: error.message || this.$tc('sw-merch.sorting.failedToSave') });
            }
        },

        async onDeleteTemplate(template) {
            try {
                await this.templateRepository.delete(template.id, Context.api);
                this.createNotificationSuccess({ message: this.$tc('sw-merch.sorting.templateDeleted') });
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.sorting.failedToDelete') });
            }
        },

        async onLinkTemplate(templateId) {
            try {
                if (this.categoryLink) {
                    this.categoryLink.sortingTemplateId = templateId;
                    await this.templateCategoryRepository.save(this.categoryLink, Context.api);
                } else {
                    const link = this.templateCategoryRepository.create(Context.api);
                    link.sortingTemplateId = templateId;
                    link.categoryId = this.categoryId;
                    link.overrideMode = 'active';
                    await this.templateCategoryRepository.save(link, Context.api);
                }
                this.createNotificationSuccess({ message: this.$tc('sw-merch.sorting.templateLinked') });
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.sorting.failedToLink') });
            }
        },

        async onUnlinkTemplate() {
            if (!this.categoryLink) return;
            try {
                await this.templateCategoryRepository.delete(this.categoryLink.id, Context.api);
                this.createNotificationSuccess({ message: this.$tc('sw-merch.sorting.templateUnlinked') });
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.sorting.failedToUnlink') });
            }
        },

        async onOverrideModeChange(mode) {
            if (!this.categoryLink) return;
            try {
                this.categoryLink.overrideMode = mode;
                await this.templateCategoryRepository.save(this.categoryLink, Context.api);
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.sorting.failedToUpdateMode') });
            }
        },

        getRuleName(ruleId) {
            const rule = this.availableRules.find((r) => r.id === ruleId);
            return rule?.name || ruleId;
        },
    },
});
