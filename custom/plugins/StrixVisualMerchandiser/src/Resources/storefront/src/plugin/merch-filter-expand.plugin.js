import Plugin from 'src/plugin-system/plugin.class';

/**
 * MerchFilterExpandPlugin — "Show more" toggle for filter option lists.
 *
 * Activated via data-merch-filter-expand="true" on the filter list container.
 * Reads maxVisible from data-merch-filter-expand-options or plugin options.
 * When the number of filter items exceeds maxVisible, items beyond the limit
 * are hidden and a "Show more" button is appended.
 */
export default class MerchFilterExpandPlugin extends Plugin {
    static options = { maxVisible: 5 };

    init() {
        const maxVisible = this.options.maxVisible;

        // maxVisible of 0 means no limit
        if (!maxVisible || maxVisible <= 0) return;

        this._items = this.el.querySelectorAll(
            '.filter-multi-select-option, .filter-property-select-option, ' +
            '.filter-multi-select-list-item, .filter-property-select-list-item, ' +
            '.merch-filter-colorbox__item, .merch-filter-bucket__btn'
        );

        if (this._items.length <= maxVisible) return;

        // Hide items beyond maxVisible
        this._items.forEach((item, i) => {
            if (i >= maxVisible) {
                item.style.display = 'none';
            }
        });

        // Add "Show more" button
        const hiddenCount = this._items.length - maxVisible;
        const btn = document.createElement('button');
        btn.className = 'merch-filter-expand__btn btn btn-sm btn-outline-secondary mt-2';
        btn.type = 'button';
        btn.textContent = `Show ${hiddenCount} more`;
        btn.addEventListener('click', () => this._expand(btn));
        this.el.appendChild(btn);
    }

    _expand(btn) {
        this._items.forEach(item => {
            item.style.display = '';
        });
        btn.remove();
    }
}
