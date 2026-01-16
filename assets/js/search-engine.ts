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

    constructor() {
        this.$results = document.querySelector('.jankx-search-results-container .results-grid');
        this.$pagination = document.querySelector('.pagination-container');
        this.$selectedFilters = document.querySelector('.jankx-search-selected-filters');
        this.init();
    }

    private init() {
        this.collectSettings();
        this.collectInitialState();
        this.bindEvents();
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
            const target = e.target as HTMLInputElement;
            if (target.closest('.jankx-search-keyword .search-input')) {
                this.state.q = target.value;
                this.state.page = 1;
                this.debounceSearch();
            }
        });

        // Filters
        document.addEventListener('change', (e) => {
            const target = e.target as HTMLInputElement;
            if (target.matches('.jankx-search-filters input[type="checkbox"]')) {
                const nameMatch = target.name.match(/filter\[([^\]]+)\]/);
                if (nameMatch) {
                    const taxonomy = nameMatch[1];
                    if (!this.state.filters[taxonomy]) {
                        this.state.filters[taxonomy] = [];
                    }

                    const val = target.value;
                    if (target.checked) {
                        this.state.filters[taxonomy].push(val);
                    } else {
                        this.state.filters[taxonomy] = this.state.filters[taxonomy].filter(item => item !== val);
                    }

                    this.state.page = 1;
                    this.search();
                }
            }
        });

        // Sorter
        document.addEventListener('change', (e) => {
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
                const pageNum = paginationLink.getAttribute('data-page');
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
                    if (taxonomy && termId && this.state.filters[taxonomy]) {
                        // Remove term from state
                        this.state.filters[taxonomy] = this.state.filters[taxonomy].filter(t => t !== termId);

                        // Uncheck the checkbox in the sidebar if present
                        const checkbox = document.querySelector(`.jankx-search-filters input[name="filter[${taxonomy}][]"][value="${termId}"]`) as HTMLInputElement;
                        if (checkbox) checkbox.checked = false;

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

    private async search() {
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
                if (result.data.selected_filters !== undefined && this.$selectedFilters) {
                    this.$selectedFilters.outerHTML = result.data.selected_filters;
                    this.$selectedFilters = document.querySelector('.jankx-search-selected-filters');
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
}

document.addEventListener('DOMContentLoaded', () => {
    new JankxSearchHub();
});
