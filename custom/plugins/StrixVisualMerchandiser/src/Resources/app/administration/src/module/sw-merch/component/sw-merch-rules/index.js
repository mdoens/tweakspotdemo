import template from './sw-merch-rules.html.twig';
import './sw-merch-rules.scss';

const { Component, Context, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-merch-rules', {
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
            linkedRules: [],
            allRules: [],
            isLoading: false,
            showCreateModal: false,
            showDeleteConfirm: false,
            deleteTarget: null,
            editingRule: null,
            searchTerm: '',
            typeFilter: null,
            newRule: this.getEmptyRule(),
            overlapWarning: null,
            modalKey: 0,
            propertyGroups: [],
            manufacturers: [],
            tags: [],
            propertyOptions: {},  // { groupId: [options] }
            showApplyChildrenConfirm: false,
            applyChildrenTarget: null,
            childCategoryIds: [],
            isLoadingChildren: false,
        };
    },

    computed: {
        ruleRepository() {
            return this.repositoryFactory.create('strix_merch_rule');
        },

        ruleCategoryRepository() {
            return this.repositoryFactory.create('strix_merch_rule_category');
        },

        canEdit() {
            return this.acl.isAdmin() || this.acl.can('strix_merch_rule:create');
        },

        canDelete() {
            return this.acl.isAdmin() || this.acl.can('strix_merch_rule:delete');
        },

        typeOptions() {
            return [
                { value: 'weighted_sort', label: this.$tc('sw-merch.rules.types.weighted_sort') },
                { value: 'boost', label: this.$tc('sw-merch.rules.types.boost') },
                { value: 'bury', label: this.$tc('sw-merch.rules.types.bury') },
                { value: 'formula_order', label: this.$tc('sw-merch.rules.types.formula_order') },
            ];
        },

        propertyGroupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        propertyGroupOptionRepository() {
            return this.repositoryFactory.create('property_group_option');
        },

        manufacturerRepository() {
            return this.repositoryFactory.create('product_manufacturer');
        },

        /**
         * Field options for boost/bury criteria.
         * Groups: standard text fields + property groups from Shopware.
         */
        tagRepository() {
            return this.repositoryFactory.create('tag');
        },

        /**
         * Field dropdown options for boost/bury.
         * All store UUIDs as values — the ES subscriber uses UUID-based queries.
         */
        boostFieldOptions() {
            const fields = [
                { value: 'manufacturerId', label: this.$tc('sw-merch.rules.fieldOptions.manufacturer') },
                { value: 'tagIds', label: this.$tc('sw-merch.rules.fieldOptions.tags') },
            ];

            for (const g of this.propertyGroups) {
                fields.push({
                    value: `propertyIds:${g.id}`,
                    label: g.translated?.name || g.name,
                });
            }

            return fields;
        },

        unlinkedRules() {
            const linkedIds = new Set(this.linkedRules.map((r) => r.id));
            return this.filteredRules.filter((r) => !linkedIds.has(r.id));
        },

        filteredRules() {
            let rules = this.allRules;
            if (this.searchTerm) {
                const term = this.searchTerm.toLowerCase();
                rules = rules.filter((r) => r.name.toLowerCase().includes(term));
            }
            if (this.typeFilter) {
                rules = rules.filter((r) => r.type === this.typeFilter);
            }
            return rules;
        },
    },

    watch: {
        categoryId: {
            immediate: true,
            handler(newVal) {
                if (newVal) {
                    this.loadData();
                }
            },
        },
    },

    methods: {
        getEmptyRule() {
            return {
                name: '',
                type: 'weighted_sort',
                config: { factors: [{ field: 'sales', weight: 100, direction: 'desc' }] },
                priority: 0,
                active: true,
                validFrom: null,
                validUntil: null,
                salesChannelId: null,
            };
        },

        getDefaultConfig(type) {
            switch (type) {
                case 'weighted_sort':
                    return { factors: [{ field: 'sales', weight: 100, direction: 'desc' }] };
                case 'boost':
                    return { criteria: { field: '', operator: '=', value: '' }, multiplier: 2.0 };
                case 'bury':
                    return { criteria: { field: '', operator: '=', value: '' }, multiplier: 0.1 };
                case 'formula_order':
                    return { steps: [] };
                default:
                    return {};
            }
        },

        async loadData() {
            this.isLoading = true;

            const [linked, all] = await Promise.all([
                this.loadLinkedRules(),
                this.loadAllRules(),
                this.loadFieldData(),
            ]);

            this.linkedRules = linked;
            this.allRules = all;
            this.isLoading = false;
            this.checkBoostBuryOverlap();
        },

        async loadFieldData() {
            try {
                const [propGroups, mfrs, tags] = await Promise.all([
                    this.propertyGroupRepository.search(new Criteria().setLimit(500).addSorting(Criteria.sort('name', 'ASC')), Context.api),
                    this.manufacturerRepository.search(new Criteria().setLimit(500).addSorting(Criteria.sort('name', 'ASC')), Context.api),
                    this.tagRepository.search(new Criteria().setLimit(500).addSorting(Criteria.sort('name', 'ASC')), Context.api),
                ]);
                this.propertyGroups = [...propGroups];
                this.manufacturers = [...mfrs];
                this.tags = [...tags];
            } catch {
                // Non-critical
            }
        },

        async loadPropertyOptions(groupId) {
            if (this.propertyOptions[groupId]) return;
            try {
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equals('groupId', groupId));
                criteria.addSorting(Criteria.sort('name', 'ASC'));
                criteria.setLimit(500);
                const result = await this.propertyGroupOptionRepository.search(criteria, Context.api);
                this.propertyOptions = { ...this.propertyOptions, [groupId]: [...result] };
            } catch {
                this.propertyOptions = { ...this.propertyOptions, [groupId]: [] };
            }
        },

        onBoostFieldChange(fieldValue) {
            this.newRule.config.criteria.field = fieldValue;
            this.newRule.config.criteria.operator = '=';
            this.newRule.config.criteria.value = '';

            // Lazy-load property options when a property group is selected
            if (fieldValue.startsWith('propertyIds:')) {
                const groupId = fieldValue.replace('propertyIds:', '');
                this.loadPropertyOptions(groupId);
            }
        },

        /**
         * Returns dropdown options for the Value field based on the selected Field.
         * All options use entity UUIDs as values (for direct ES TermQuery matching).
         * Returns null for unsupported fields (falls back to text input).
         */
        getValueOptions() {
            const field = this.newRule.config?.criteria?.field || '';

            // Manufacturer → dropdown of manufacturer UUIDs
            if (field === 'manufacturerId') {
                return this.manufacturers.map((m) => ({
                    value: m.id,
                    label: m.translated?.name || m.name,
                }));
            }

            // Tags → dropdown of tag UUIDs
            if (field === 'tagIds') {
                return (this.tags || []).map((t) => ({
                    value: t.id,
                    label: t.translated?.name || t.name,
                }));
            }

            // Property group → dropdown of option UUIDs
            if (field.startsWith('propertyIds:')) {
                const groupId = field.replace('propertyIds:', '');
                return (this.propertyOptions[groupId] || []).map((o) => ({
                    value: o.id,
                    label: o.translated?.name || o.name,
                }));
            }

            return null; // null = show text field instead of dropdown
        },

        async loadLinkedRules() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('categories.id', this.categoryId));
            criteria.addSorting(Criteria.sort('priority', 'DESC'));

            const result = await this.ruleRepository.search(criteria, Context.api);
            return [...result];
        },

        async loadAllRules() {
            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort('name', 'ASC'));
            criteria.setLimit(500);

            const result = await this.ruleRepository.search(criteria, Context.api);
            return [...result];
        },

        onCreateRule() {
            this.editingRule = null;
            this.newRule = this.getEmptyRule();
            this.modalKey += 1;
            this.showCreateModal = true;
        },

        onEditRule(rule) {
            this.editingRule = rule;
            this.newRule = {
                name: rule.name,
                type: rule.type,
                config: JSON.parse(JSON.stringify(rule.config)),
                priority: rule.priority,
                active: rule.active,
                validFrom: rule.validFrom,
                validUntil: rule.validUntil,
                salesChannelId: rule.salesChannelId,
            };
            this.modalKey += 1;
            this.showCreateModal = true;
        },

        onTypeChange(type) {
            this.newRule.type = type;
            this.newRule.config = this.getDefaultConfig(type);
        },

        onCloseModal() {
            this.showCreateModal = false;
            this.editingRule = null;
        },

        async onSaveRule() {
            if (!this.newRule.name) {
                this.createNotificationError({ message: this.$tc('sw-merch.general.nameRequired') });
                return;
            }

            // Validate boost/bury criteria
            if (this.newRule.type === 'boost' || this.newRule.type === 'bury') {
                const criteria = this.newRule.config?.criteria || {};
                if (!criteria.field) {
                    this.createNotificationError({ message: this.$tc('sw-merch.rules.validation.fieldRequired') });
                    return;
                }
                if (!criteria.value) {
                    this.createNotificationError({ message: this.$tc('sw-merch.rules.validation.valueRequired') });
                    return;
                }
            }

            // Validate weighted_sort factors
            if (this.newRule.type === 'weighted_sort') {
                const factors = this.newRule.config?.factors || [];
                if (factors.length === 0) {
                    this.createNotificationError({ message: this.$tc('sw-merch.rules.validation.factorsRequired') });
                    return;
                }
                const totalWeight = factors.reduce((sum, f) => sum + (f.weight || 0), 0);
                if (totalWeight !== 100) {
                    this.createNotificationWarning({ message: this.$tc('sw-merch.rules.validation.weightsNot100', 0, { total: totalWeight }) });
                }
            }

            try {
                if (this.editingRule) {
                    // Update: modify the entity proxy directly and save
                    this.editingRule.name = this.newRule.name;
                    this.editingRule.type = this.newRule.type;
                    this.editingRule.config = this.newRule.config;
                    this.editingRule.priority = this.newRule.priority;
                    this.editingRule.active = this.newRule.active;
                    this.editingRule.validFrom = this.newRule.validFrom;
                    this.editingRule.validUntil = this.newRule.validUntil;
                    await this.ruleRepository.save(this.editingRule, Context.api);
                } else {
                    const rule = this.ruleRepository.create(Context.api);
                    rule.name = this.newRule.name;
                    rule.type = this.newRule.type;
                    rule.config = this.newRule.config;
                    rule.priority = this.newRule.priority;
                    rule.active = this.newRule.active;
                    rule.validFrom = this.newRule.validFrom;
                    rule.validUntil = this.newRule.validUntil;
                    await this.ruleRepository.save(rule, Context.api);
                }

                this.createNotificationSuccess({
                    message: this.editingRule ? this.$tc('sw-merch.rules.ruleUpdated') : this.$tc('sw-merch.rules.ruleCreated'),
                });
                this.onCloseModal();
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: error.message || this.$tc('sw-merch.rules.failedToSave') });
            }
        },

        onDeleteRule(rule) {
            // S7.7: Show delete confirmation with linked category count
            this.deleteTarget = rule;
            this.showDeleteConfirm = true;
        },

        async onConfirmDelete() {
            if (!this.deleteTarget) return;
            try {
                await this.ruleRepository.delete(this.deleteTarget.id, Context.api);
                this.createNotificationSuccess({ message: this.$tc('sw-merch.rules.ruleDeleted') });
                this.showDeleteConfirm = false;
                this.deleteTarget = null;
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.rules.failedToDelete') });
            }
        },

        onCancelDelete() {
            this.showDeleteConfirm = false;
            this.deleteTarget = null;
        },

        // S7.6: Duplicate a rule
        async onDuplicateRule(rule) {
            try {
                const duplicate = this.ruleRepository.create(Context.api);
                duplicate.name = `${rule.name} (copy)`;
                duplicate.type = rule.type;
                duplicate.config = JSON.parse(JSON.stringify(rule.config));
                duplicate.priority = rule.priority;
                duplicate.active = false; // Duplicates start inactive
                duplicate.validFrom = null;
                duplicate.validUntil = null;

                await this.ruleRepository.save(duplicate, Context.api);
                this.createNotificationSuccess({ message: this.$tc('sw-merch.rules.duplicateSuccess') });
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.rules.failedToDuplicate') });
            }
        },

        // S7.7: Check for boost/bury overlap on linked rules
        checkBoostBuryOverlap() {
            const boostRules = this.linkedRules.filter((r) => r.type === 'boost' && r.active);
            const buryRules = this.linkedRules.filter((r) => r.type === 'bury' && r.active);

            if (boostRules.length === 0 || buryRules.length === 0) {
                this.overlapWarning = null;
                return;
            }

            // Check if any boost and bury share the same criteria field
            for (const boost of boostRules) {
                const boostField = boost.config?.criteria?.field;
                if (!boostField) continue;

                for (const bury of buryRules) {
                    const buryField = bury.config?.criteria?.field;
                    if (boostField === buryField) {
                        this.overlapWarning = `"${boost.name}" (boost) and "${bury.name}" (bury) both target "${boostField}". Products matching both will have an effective score of ${(boost.config.multiplier * bury.config.multiplier).toFixed(2)}x.`;
                        return;
                    }
                }
            }

            this.overlapWarning = null;
        },

        // S7.6: Link rule to multiple categories at once
        async onLinkRuleMultiCategory(ruleId, categoryIds) {
            try {
                for (const catId of categoryIds) {
                    const link = this.ruleCategoryRepository.create(Context.api);
                    link.merchRuleId = ruleId;
                    link.categoryId = catId;
                    await this.ruleCategoryRepository.save(link, Context.api);
                }
                this.createNotificationSuccess({ message: `Rule linked to ${categoryIds.length} categories` });
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.rules.failedToLink') });
            }
        },

        async onLinkRule(ruleId) {
            try {
                const link = this.ruleCategoryRepository.create(Context.api);
                link.merchRuleId = ruleId;
                link.categoryId = this.categoryId;
                await this.ruleCategoryRepository.save(link, Context.api);

                this.createNotificationSuccess({ message: this.$tc('sw-merch.rules.ruleLinked') });
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.rules.failedToLink') });
            }
        },

        async onUnlinkRule(rule) {
            try {
                // MappingEntityDefinition delete requires the composite PK as an array
                // The API endpoint is: DELETE /api/strix-merch-rule/{ruleId}/categories/{categoryId}
                const httpClient = Shopware.Application.getContainer('init').httpClient;
                const headers = { Authorization: `Bearer ${Context.api.authToken.access}` };

                await httpClient.delete(
                    `/api/strix-merch-rule/${rule.id}/categories/${this.categoryId}`,
                    { headers },
                );

                this.createNotificationSuccess({ message: this.$tc('sw-merch.rules.ruleUnlinked') });
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.rules.failedToUnlink') });
            }
        },

        // Apply rule to all child categories recursively
        async onApplyToChildren(rule) {
            this.applyChildrenTarget = rule;
            this.childCategoryIds = [];
            this.isLoadingChildren = true;
            this.showApplyChildrenConfirm = true;

            try {
                const childIds = await this.loadAllDescendantIds(this.categoryId);
                this.childCategoryIds = childIds;
            } catch (error) {
                console.error('Failed to load child categories', error);
            }

            this.isLoadingChildren = false;
        },

        async loadAllDescendantIds(parentId) {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('parentId', parentId));
            criteria.setLimit(500);

            const result = await this.repositoryFactory.create('category').search(criteria, Context.api);
            const ids = [];

            for (const category of result) {
                ids.push(category.id);
                if (category.childCount > 0) {
                    const subIds = await this.loadAllDescendantIds(category.id);
                    ids.push(...subIds);
                }
            }

            return ids;
        },

        onCancelApplyChildren() {
            this.showApplyChildrenConfirm = false;
            this.applyChildrenTarget = null;
            this.childCategoryIds = [];
        },

        async onConfirmApplyChildren() {
            if (!this.applyChildrenTarget || this.childCategoryIds.length === 0) return;

            const ruleId = this.applyChildrenTarget.id;
            const ruleName = this.applyChildrenTarget.name;

            try {
                // Load existing links for this rule to avoid duplicates
                const existingCriteria = new Criteria();
                existingCriteria.addFilter(Criteria.equals('merchRuleId', ruleId));
                existingCriteria.setLimit(500);

                const existingLinks = await this.ruleCategoryRepository.search(existingCriteria, Context.api);
                const alreadyLinked = new Set([...existingLinks].map((l) => l.categoryId));

                const newCategoryIds = this.childCategoryIds.filter((id) => !alreadyLinked.has(id));

                if (newCategoryIds.length === 0) {
                    this.createNotificationInfo({ message: this.$tc('sw-merch.rules.alreadyLinkedToAll') });
                    this.onCancelApplyChildren();
                    return;
                }

                for (const catId of newCategoryIds) {
                    const link = this.ruleCategoryRepository.create(Context.api);
                    link.merchRuleId = ruleId;
                    link.categoryId = catId;
                    await this.ruleCategoryRepository.save(link, Context.api);
                }

                this.createNotificationSuccess({
                    message: this.$tc('sw-merch.rules.appliedToChildren', { rule: ruleName, count: newCategoryIds.length }),
                });
                this.onCancelApplyChildren();
                await this.loadData();
            } catch (error) {
                this.createNotificationError({ message: this.$tc('sw-merch.rules.failedToApplyChildren') });
            }
        },

        getTypeLabel(type) {
            return this.$tc(`sw-merch.rules.types.${type}`) || type;
        },

        getTypeColor(type) {
            const colors = {
                weighted_sort: '#189eff',
                boost: '#37d046',
                bury: '#ff6b6b',
                formula_order: '#ffab00',
            };
            return colors[type] || '#758698';
        },
    },
});
