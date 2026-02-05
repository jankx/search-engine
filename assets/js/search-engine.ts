interface SearchState extends Record<string, any> {
    q: string;
    filters: Record<string, string[]>;
    sort: string;
    page: number;
}

interface JankxConfig {
    ajax_url: string;
    nonce: string;
}

declare const jankx_search_config: JankxConfig;

class JankxSearchHub {
    private state: SearchState = {
        q: '',
        filters: {},
        sort: 'relevance',
        page: 1
    };

    private settings: Record<string, any> = {};
    private debounceTimer: number | null = null;
    private $results: HTMLElement | null = null;
    private $pagination: HTMLElement | null = null;
    private $selectedFilters: HTMLElement | null = null;
    private $featuredItems: HTMLElement | null = null;
    private isInitializing: boolean = true;

    constructor() {
        this.$results = document.querySelector('.jankx-search-results-container .results-grid');
        this.$pagination = document.querySelector('.pagination-container');
        this.$selectedFilters = document.querySelector('.jankx-search-selected-filters');
        this.$featuredItems = document.querySelector('.jankx-featured-items');
        this.init();
    }

    private init() {
        this.collectSettings();
        this.collectInitialState();
        this.bindEvents();
        this.disableEmptyFiltersOnLoad();

        window.addEventListener('popstate', (event) => {
            if (event.state) {
                this.state = event.state;
                this.updateUIFromState();
                this.search(false);
            }
        });

        // Trigger initial search if URL state implies non-default view
        const hasFilters = Object.values(this.state.filters).some(t => t.length > 0);
        // We compare sort against 'relevance' or we can trust that if sort param exists it might need update
        // But crucially for Page > 1:
        if (this.state.page > 1 || this.state.q || (this.state.sort && this.state.sort !== 'relevance') || hasFilters) {
            this.search(false);
        }

        // Release initialization lock
        setTimeout(() => {
            this.isInitializing = false;
        }, 800);
    }

    private collectInitialState() {
        const sortSelect = document.querySelector('.jankx-search-sorter .sort-select') as HTMLSelectElement;
        if (sortSelect) {
            this.state.sort = sortSelect.value;
        }

        const queryInput = document.querySelector('.jankx-search-keyword .search-input') as HTMLInputElement;
        if (queryInput) {
            this.state.q = queryInput.value;
        }

        // Parse from URL
        const queryParams = new URLSearchParams(window.location.search);

        // Page
        if (queryParams.has('page')) {
            const p = parseInt(queryParams.get('page') || '1', 10);
            if (p > 1) this.state.page = p;
        }

        // Sort
        if (queryParams.has('sort')) {
            this.state.sort = queryParams.get('sort') || 'relevance';
            if (sortSelect) sortSelect.value = this.state.sort;
        }

        // Q
        if (queryParams.has('q')) {
            this.state.q = queryParams.get('q') || '';
            if (queryInput) queryInput.value = this.state.q;
        }

        // Filters
        // We know known taxes from settings or hardcoded?
        // Let's use the hardcoded list or discover from DOM?
        const knownTaxes = ['featured_item_category', 'industry', 'category', 'thought_leader'];

        knownTaxes.forEach(tax => {
            // Check for tax or tax[]
            const vals = queryParams.getAll(tax).concat(queryParams.getAll(tax + '[]'));
            if (vals.length > 0) {
                if (!this.state.filters[tax]) this.state.filters[tax] = [];
                vals.forEach(val => {
                    if (!this.state.filters[tax].includes(val)) {
                        this.state.filters[tax].push(val);
                    }
                    // Check UI
                    const cb = document.querySelector(`.jankx-search-filters input[name="filter[${tax}][]"][value="${val}"]`) as HTMLInputElement;
                    if (cb) cb.checked = true;
                });
            }
        });

        this.updateFeaturedItemsVisibility();
    }

    private disableEmptyFiltersOnLoad() {
        const checkboxes = document.querySelectorAll('.jankx-search-filters input[type="checkbox"]');
        checkboxes.forEach((e) => {
            const input = e as HTMLInputElement;
            const label = input.closest('label');
            if (label) {
                const countSpan = label.querySelector('.term-count');
                if (countSpan && countSpan.textContent) {
                    const match = countSpan.textContent.match(/\((\d+)\)/);
                    if (match) {
                        const count = parseInt(match[1], 10);
                        if (count === 0 && !input.checked) {
                            label.classList.add('disabled');
                            input.disabled = true;
                        }
                    }
                }
            }
        });
    }

    private updateUIFromState() {
        // Restore UI based on state (useful for back button)
        // Checkboxes
        document.querySelectorAll('.jankx-search-filters input[type="checkbox"]').forEach(el => {
            (el as HTMLInputElement).checked = false;
        });
        Object.keys(this.state.filters).forEach(tax => {
            this.state.filters[tax].forEach(val => {
                const cb = document.querySelector(`.jankx-search-filters input[name="filter[${tax}][]"][value="${val}"]`) as HTMLInputElement;
                if (cb) cb.checked = true;
            });
        });

        // Selected Filters UI
        this.clearAllSelectedFiltersUI();
        Object.keys(this.state.filters).forEach(tax => {
            this.state.filters[tax].forEach(val => {
                // Try to find label
                const cb = document.querySelector(`.jankx-search-filters input[name="filter[${tax}][]"][value="${val}"]`) as HTMLInputElement;
                let name = val;
                let taxLabel = '';
                if (cb) {
                    name = cb.closest('label')?.querySelector('.term-name')?.textContent?.trim() || val;
                    taxLabel = cb.closest('.filter-group')?.querySelector('.filter-title')?.textContent?.trim() || '';
                }
                this.updateSelectedFilterUI(tax, val, name, true, taxLabel);
            });
        });

        // Keyword
        const qInput = document.querySelector('.jankx-search-keyword .search-input') as HTMLInputElement;
        if (qInput) qInput.value = this.state.q;
        if (this.state.q) {
            this.updateSelectedFilterUI('search_keyword', 'current_query', this.state.q, true, '');
        }

        this.updateFeaturedItemsVisibility();
    }

    private collectSettings() {
        const components = document.querySelectorAll('[data-settings]');
        components.forEach(comp => {
            const raw = comp.getAttribute('data-settings');
            if (raw) {
                try {
                    const parsed = JSON.parse(raw);
                    this.settings = { ...this.settings, ...parsed };
                } catch (e) {
                    console.error('Failed to parse settings', e);
                }
            }
        });
    }

    private bindEvents() {
        // Keyword Search
        document.addEventListener('input', (e) => {
            if (this.isInitializing || !e.isTrusted) return;
            const target = e.target as HTMLInputElement;
            if (target.closest('.jankx-search-keyword .search-input')) {
                const val = target.value;
                this.state.q = val;
                this.state.page = 1;

                // Treat keyword as a selected filter
                this.updateSelectedFilterUI('search_keyword', 'current_query', val, !!val, '');
                this.updateFeaturedItemsVisibility();

                this.debounceSearch();
            }
        });

        // Filters
        document.addEventListener('change', (e) => {
            if (this.isInitializing || !e.isTrusted) return;
            const target = e.target as HTMLInputElement;
            if (target.matches('.jankx-search-filters input[type="checkbox"]')) {
                const nameMatch = target.name.match(/filter\[([^\]]+)\]/);
                if (nameMatch) {
                    const taxonomy = nameMatch[1];
                    if (!this.state.filters[taxonomy]) {
                        this.state.filters[taxonomy] = [];
                    }

                    const val = target.value;
                    const label = target.closest('label');
                    const nameSpan = label ? label.querySelector('.term-name') : null;
                    const rawName = nameSpan ? (nameSpan.textContent || '').trim() : val;
                    const termName = this.decodeHTMLEntities(rawName);

                    const filterGroup = target.closest('.filter-group');
                    const taxTitle = filterGroup ? filterGroup.querySelector('.filter-title') : null;
                    const taxLabel = taxTitle ? (taxTitle.textContent || '').trim() : '';

                    if (target.checked) {
                        this.state.filters[taxonomy].push(val);
                        this.updateSelectedFilterUI(taxonomy, val, termName, true, taxLabel);
                    } else {
                        this.state.filters[taxonomy] = this.state.filters[taxonomy].filter(item => item !== val);
                        this.updateSelectedFilterUI(taxonomy, val, termName, false, taxLabel);
                    }
                    this.updateFeaturedItemsVisibility();

                    this.state.page = 1;
                    this.search();
                }
            }
        });

        // Sorter
        document.addEventListener('change', (e) => {
            if (this.isInitializing || !e.isTrusted) return;
            const target = e.target as HTMLSelectElement;
            if (target.closest('.jankx-search-sorter .sort-select')) {
                this.state.sort = target.value;
                this.search();
            }
        });

        // Pagination
        document.addEventListener('click', (e) => {
            const target = e.target as HTMLElement;
            const paginationLink = target.closest('.pagination-container .page-number') as HTMLElement;
            if (paginationLink) {
                e.preventDefault();
                let pageNum = paginationLink.getAttribute('data-page');

                // Fallback: Try to parse from HREF if data-page missing
                if (!pageNum) {
                    const href = paginationLink.getAttribute('href');
                    if (href) {
                        const match = href.match(/[?&]page=(\d+)/) || href.match(/\/page\/(\d+)/);
                        if (match) {
                            pageNum = match[1];
                        }
                    }
                }

                if (pageNum) {
                    this.state.page = parseInt(pageNum, 10);
                    this.search();
                    if (this.$results) {
                        window.scrollTo({
                            top: this.$results.getBoundingClientRect().top + window.scrollY - 100,
                            behavior: 'smooth'
                        });
                    }
                }
            }
        });

        // Selected Filters: Remove single filter
        document.addEventListener('click', (e) => {
            const target = e.target as HTMLElement;
            const removeBtn = target.closest('.selected-filter-item .remove-filter');
            if (removeBtn) {
                e.preventDefault();
                const item = removeBtn.closest('.selected-filter-item');
                if (item) {
                    const taxonomy = item.getAttribute('data-taxonomy');
                    const termId = item.getAttribute('data-term-id');

                    if (taxonomy === 'search_keyword') {
                        this.state.q = '';
                        const input = document.querySelector('.jankx-search-keyword .search-input') as HTMLInputElement;
                        if (input) input.value = '';

                        this.updateSelectedFilterUI(taxonomy, termId || '', '', false);
                        this.updateFeaturedItemsVisibility();

                        this.state.page = 1;
                        this.search();
                    } else if (taxonomy && termId && this.state.filters[taxonomy]) {
                        // Remove term from state
                        this.state.filters[taxonomy] = this.state.filters[taxonomy].filter(t => t !== termId);

                        // Uncheck the checkbox in the sidebar if present
                        const checkbox = document.querySelector(`.jankx-search-filters input[name="filter[${taxonomy}][]"][value="${termId}"]`) as HTMLInputElement;
                        if (checkbox) checkbox.checked = false;

                        this.updateSelectedFilterUI(taxonomy, termId, '', false);
                        this.updateFeaturedItemsVisibility();

                        this.state.page = 1;
                        this.search();
                    }
                }
            }
        });

        // Selected Filters: Clear all
        document.addEventListener('click', (e) => {
            const target = e.target as HTMLElement;
            if (target.matches('.clear-all-filters-btn') || target.closest('.clear-all-filters-btn')) {
                e.preventDefault();
                this.state.filters = {};

                // Uncheck all checkboxes
                document.querySelectorAll('.jankx-search-filters input[type="checkbox"]').forEach((element) => {
                    const cb = element as HTMLInputElement;
                    cb.checked = false;
                });

                // Keyword
                this.state.q = '';
                const input = document.querySelector('.jankx-search-keyword .search-input') as HTMLInputElement;
                if (input) input.value = '';

                this.clearAllSelectedFiltersUI();
                this.updateFeaturedItemsVisibility();

                this.state.page = 1;
                this.search();
            }
        });
    }

    private debounceSearch() {
        if (this.debounceTimer) {
            window.clearTimeout(this.debounceTimer);
        }
        this.debounceTimer = window.setTimeout(() => {
            this.search();
        }, 500);
    }

    private async search(pushState = true) {
        if (!this.$results) return;

        // Show skeletons
        this.showSkeleton();

        const formData = new FormData();
        formData.append('action', 'jankx_search_query');
        formData.append('nonce', jankx_search_config.nonce);
        // Explicitly resolve post types array from individual settings (post_type_{slug})
        const activePostTypes: string[] = [];
        Object.keys(this.settings).forEach(key => {
            if (key.startsWith('post_type_') && (this.settings[key] === 'true' || this.settings[key] === true)) {
                activePostTypes.push(key.replace('post_type_', ''));
            }
        });

        const fullState = {
            ...this.settings,
            ...this.state,
            post_types: activePostTypes
        };

        formData.append('state', JSON.stringify(fullState));

        try {
            const response = await fetch(jankx_search_config.ajax_url, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.$results.innerHTML = result.data.html;
                if (this.$pagination) {
                    this.$pagination.innerHTML = result.data.pagination;
                }
                if (result.data.term_counts !== undefined) {
                    this.updateTermCounts(result.data.term_counts);
                }
                if (result.data.selected_filters !== undefined) {
                    this.syncSelectedFiltersFromHTML(result.data.selected_filters);
                }

                if (pushState) {
                    this.updateBrowserUrl();
                }
            }
        } catch (error) {
            console.error('Search failed:', error);
            this.$results.innerHTML = '<p class="error-message">An error occurred while searching. Please try again.</p>';
        } finally {
            this.$results.classList.remove('loading');
            this.$results.style.opacity = '1';
        }
    }

    private updateBrowserUrl() {
        const params = new URLSearchParams();

        // Q
        if (this.state.q) params.set('q', this.state.q);

        // Sort
        if (this.state.sort && this.state.sort !== 'relevance') {
            params.set('sort', this.state.sort);
        }

        // Page
        if (this.state.page > 1) {
            params.set('page', this.state.page.toString());
        }

        // Filters
        Object.keys(this.state.filters).forEach(tax => {
            const terms = this.state.filters[tax];
            if (terms && terms.length > 0) {
                if (terms.length === 1) {
                    params.set(tax, terms[0]);
                } else {
                    terms.forEach(id => params.append(tax + '[]', id));
                }
            }
        });

        const newUrl = `${window.location.pathname}?${params.toString()}`;
        window.history.pushState(this.state, '', newUrl);
    }

    private updateTermCounts(counts: Record<string, number>) {
        const checkboxes = document.querySelectorAll('.jankx-search-filters input[type="checkbox"]');
        checkboxes.forEach((e) => {
            const input = e as HTMLInputElement;
            const termId = input.value;
            // Default to 0 if not returned
            const count = counts[termId] ?? 0;

            const label = input.closest('label');
            if (label) {
                const countSpan = label.querySelector('.term-count');
                if (countSpan) {
                    countSpan.textContent = `(${count})`;
                }

                if (count === 0 && !input.checked) {
                    label.classList.add('disabled');
                    input.disabled = true;
                } else {
                    label.classList.remove('disabled');
                    input.disabled = false;
                }
            }
        });
    }

    private showSkeleton() {
        if (!this.$results) return;

        this.$results.classList.add('loading');
        this.$results.innerHTML = this.getSkeletonHtml();
    }

    private getSkeletonHtml(): string {
        let html = '';
        const count = 3;

        for (let i = 0; i < count; i++) {
            html += `
                <div class="jankx-skeleton-item">
                    <div class="skeleton-img"></div>
                    <div class="skeleton-text">
                        <div class="skeleton-line title"></div>
                        <div class="skeleton-line"></div>
                        <div class="skeleton-line"></div>
                        <div class="skeleton-line" style="width: 40%"></div>
                    </div>
                </div>
            `;
        }
        return html;
    }

    private clearAllSelectedFiltersUI() {
        if (!this.$selectedFilters) return;
        const list = this.$selectedFilters.querySelector('.selected-filters-list');
        if (list) {
            list.innerHTML = '';
        }
        this.$selectedFilters.style.display = 'none';
    }

    private updateSelectedFilterUI(taxonomy: string, termId: string, termName: string, isSelected: boolean, taxonomyLabel: string = '') {
        if (!this.$selectedFilters) return;

        termName = this.decodeHTMLEntities(termName);

        let list = this.$selectedFilters.querySelector('.selected-filters-list');

        if (isSelected) {
            // Ensure list exists
            if (!list) {
                const ul = document.createElement('ul');
                ul.className = 'selected-filters-list';
                // Append it before the clear-all-wrapper if it exists, otherwise just prepend
                const clearWrapper = this.$selectedFilters.querySelector('.clear-all-wrapper');
                if (clearWrapper) {
                    this.$selectedFilters.insertBefore(ul, clearWrapper);
                } else {
                    this.$selectedFilters.prepend(ul);
                }
                list = ul;
            }

            // --- NEW: Re-inject keyword chip if missing (persistence check) ---
            // Prevent recursion: do not check if we are currently updating the keyword itself
            if (taxonomy !== 'search_keyword') {
                const keywordInput = document.querySelector('.jankx-search-keyword .search-input') as HTMLInputElement;
                if (keywordInput && keywordInput.value && !list.querySelector('.selected-filter-item[data-taxonomy="search_keyword"]')) {
                    this.updateSelectedFilterUI('search_keyword', 'current_query', keywordInput.value, true, '');
                }
            }
            // ----------------------------------------------------------------

            // Check if already exists
            const existingItem = list.querySelector(`.selected-filter-item[data-taxonomy="${taxonomy}"][data-term-id="${termId}"]`);
            if (existingItem) {
                // Update text if needed (e.g. for keyword)
                const nameSpan = existingItem.querySelector('.filter-name');
                if (nameSpan) {
                    nameSpan.textContent = taxonomyLabel ? `${taxonomyLabel}: ${termName}` : termName;
                }
                return;
            }

            const li = document.createElement('li');
            li.className = 'selected-filter-item';
            li.setAttribute('data-taxonomy', taxonomy);
            li.setAttribute('data-term-id', termId);
            li.setAttribute('data-taxonomy-label', taxonomyLabel);

            const nameSpan = document.createElement('span');
            nameSpan.className = 'filter-name';
            nameSpan.textContent = taxonomyLabel ? `${taxonomyLabel}: ${termName}` : termName;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'remove-filter';
            btn.setAttribute('aria-label', 'Remove filter');
            btn.textContent = '×';

            li.appendChild(nameSpan);
            li.appendChild(btn);
            list.appendChild(li);

            this.$selectedFilters.style.display = '';
        } else {
            if (!list) return;
            const item = list.querySelector(`.selected-filter-item[data-taxonomy="${taxonomy}"][data-term-id="${termId}"]`);
            if (item) {
                item.remove();
            }
            if (list.children.length === 0) {
                this.$selectedFilters.style.display = 'none';
            }
        }
    }

    private syncSelectedFiltersFromHTML(html: string) {
        if (!this.$selectedFilters) {
            const tempContainer = document.createElement('div');
            tempContainer.innerHTML = html;
            const newRoot = tempContainer.firstElementChild;
            if (newRoot) {
                const dest = document.querySelector('.jankx-search-selected-filters');
                if (dest) dest.outerHTML = newRoot.outerHTML;
                this.$selectedFilters = document.querySelector('.jankx-search-selected-filters');
            }
            return;
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newContainer = doc.body.firstElementChild as HTMLElement;

        if (!newContainer) {
            // Empty response means no filters selected
            this.clearAllSelectedFiltersUI();
            return;
        }

        // 1. Sync Visibility
        const hasSelected = newContainer.style.display !== 'none';

        // 2. Sync Items
        const newItems = Array.from(newContainer.querySelectorAll('.selected-filter-item'));
        const currentList = this.$selectedFilters.querySelector('.selected-filters-list');

        // If there are new items but no list in current DOM, create it
        let list = currentList;
        if (newItems.length > 0 && !list) {
            const ul = document.createElement('ul');
            ul.className = 'selected-filters-list';
            const clearWrapper = this.$selectedFilters.querySelector('.clear-all-wrapper');
            if (clearWrapper) {
                this.$selectedFilters.insertBefore(ul, clearWrapper);
            } else {
                this.$selectedFilters.prepend(ul);
            }
            list = ul;
        }

        // Create a map of new items keys
        const newItemKeys = new Set();
        newItems.forEach((item) => {
            const tax = item.getAttribute('data-taxonomy');
            const termId = item.getAttribute('data-term-id');
            const key = `${tax}|${termId}`;
            newItemKeys.add(key);

            // Add or Update
            const termNameRaw = item.querySelector('.filter-name')?.textContent || '';
            const termName = termNameRaw.trim();
            const taxonomyLabel = (item as HTMLElement).getAttribute('data-taxonomy-label') || '';

            // Let's strip the prefix if it exists when pulling from server HTML
            let cleanTermName = termName;
            if (taxonomyLabel && termName.startsWith(`${taxonomyLabel}: `)) {
                cleanTermName = termName.replace(`${taxonomyLabel}: `, '');
            }

            this.updateSelectedFilterUI(tax!, termId!, cleanTermName, true, taxonomyLabel);
        });

        // 3. Remove old items that are not in new list
        if (list) {
            const currentItems = Array.from(list.querySelectorAll('.selected-filter-item'));
            currentItems.forEach(item => {
                const tax = item.getAttribute('data-taxonomy') || '';
                const termId = item.getAttribute('data-term-id') || '';
                const key = `${tax}|${termId}`;

                if (!newItemKeys.has(key)) {
                    // Special case: Maintain Keyword (current_query) if input has value
                    if (tax === 'search_keyword') {
                        // Check input value again just to be safe?
                        // The server logic should ideally handle this.
                    } else {
                        item.remove();
                    }
                }
            });

            if (list.children.length === 0) {
                this.$selectedFilters.style.display = 'none';
            } else {
                this.$selectedFilters.style.display = '';
            }
        }
    }

    private decodeHTMLEntities(text: string): string {
        const textArea = document.createElement('textarea');
        textArea.innerHTML = text;
        return textArea.value;
    }

    private updateFeaturedItemsVisibility() {
        if (!this.$featuredItems) return;
        const hasKeyword = !!this.state.q;
        const hasFilters = Object.values(this.state.filters).some(terms => terms.length > 0);

        if (hasKeyword || hasFilters) {
            this.$featuredItems.style.display = 'none';
        } else {
            this.$featuredItems.style.display = '';
        }
    }
}


document.addEventListener('DOMContentLoaded', () => {
    new JankxSearchHub();
});
