import './acl';
import './page/sw-merch-overview';
import './component/sw-merch-category-tree';
import './component/sw-merch-category-tree-item';
import './component/sw-merch-rules';
import './component/sw-merch-pins';
import './component/sw-merch-filters';
import './component/sw-merch-sorting-templates';
import './component/sw-merch-personalization';
import './component/sw-merch-analytics';
import './component/sw-merch-grid';
import './component/sw-merch-factor-editor';
import './component/sw-merch-preview';
import './component/sw-merch-audit-log';
import './component/sw-merch-product-search';
import './component/sw-merch-bulk-edit';

import enGB from '../../snippet/en-GB.json';
import deDE from '../../snippet/de-DE.json';
import nlNL from '../../snippet/nl-NL.json';

Shopware.Module.register('sw-merch', {
    type: 'plugin',
    name: 'sw-merch',
    title: 'sw-merch.general.title',
    description: 'sw-merch.general.description',
    color: '#ff68b4',
    icon: 'regular-shopping-bag',

    snippets: {
        'en-GB': enGB,
        'de-DE': deDE,
        'nl-NL': nlNL,
    },

    navigation: [{
        id: 'sw-merch',
        label: 'sw-merch.general.title',
        color: '#ff68b4',
        path: 'sw.merch.overview',
        icon: 'regular-shopping-bag',
        parent: 'sw-catalogue',
        position: 100,
    }],

    routes: {
        overview: {
            component: 'sw-merch-overview',
            path: 'overview',
            meta: {
                privilege: 'strix_merch_rule:read',
            },
        },
    },
});
