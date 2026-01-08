(function ($) {
    'use strict';

    class JankxSearchHub {
        constructor() {
            this.state = {
                q: '',
                filters: {},
                sort: 'relevance',
                page: 1
            };
            this.$container = $('.jankx-search-results-container');
            this.debounceTimer = null;

            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            const self = this;

            // Keyword Search (Debounced)
            $(document).on('input', '.jankx-search-keyword .search-input', function () {
                self.state.q = $(this).val();
                self.state.page = 1;
                self.debounceSearch();
            });

            // Filters
            $(document).on('change', '.jankx-search-filter input[type="checkbox"]', function () {
                const $filter = $(this).closest('.jankx-search-filter');
                const taxonomy = $filter.attr('class').split('filter-')[1].split(' ')[0];

                if (!self.state.filters[taxonomy]) {
                    self.state.filters[taxonomy] = [];
                }

                const val = $(this).val();
                if ($(this).is(':checked')) {
                    self.state.filters[taxonomy].push(val);
                } else {
                    self.state.filters[taxonomy] = self.state.filters[taxonomy].filter(item => item !== val);
                }

                self.state.page = 1;
                self.search();
            });

            // Sorter
            $(document).on('change', '.jankx-search-sorter .sort-select', function () {
                self.state.sort = $(this).val();
                self.state.page = 1;
                self.search();
            });
        }

        debounceSearch() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.search();
            }, 500);
        }

        search() {
            const self = this;
            const $results = $('.jankx-search-results-container .results-grid');

            $results.addClass('loading').css('opacity', 0.5);

            $.ajax({
                url: jankx_search_config.ajax_url,
                type: 'POST',
                data: {
                    action: 'jankx_search_query',
                    nonce: jankx_search_config.nonce,
                    state: self.state
                },
                success: function (response) {
                    if (response.success) {
                        $results.html(response.data.html);
                        $('.search-pagination').html(response.data.pagination);
                    }
                },
                complete: function () {
                    $results.removeClass('loading').css('opacity', 1);
                }
            });
        }
    }

    $(document).ready(function () {
        new JankxSearchHub();
    });

})(jQuery);
