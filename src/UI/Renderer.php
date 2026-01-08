<?php
namespace Jankx\SearchEngine\UI;

class Renderer
{
    public function render_hub($atts)
    {
        ob_start();
        ?>
        <div class="jankx-search-hub container">
            <div class="row">
                <!-- Sidebar Filters -->
                <aside class="col-md-3 search-sidebar">
                    <?php $this->render_filters($atts['taxonomies'] ?? []); ?>
                </aside>

                <!-- Main Content Area -->
                <main class="col-md-9 search-main">
                    <div class="search-header">
                        <div class="search-bar-wrapper">
                            <input type="text" placeholder="Search..." class="search-input">
                            <button class="search-btn"><i class="icon-search"></i></button>
                        </div>
                        <div class="sort-wrapper">
                            <span>Sort by</span>
                            <select class="sort-select">
                                <option value="relevance">Relevance</option>
                                <option value="date">Newest</option>
                            </select>
                        </div>
                    </div>

                    <!-- Featured Section -->
                    <div class="featured-results row">
                        <!-- Placeholder for 2 featured items -->
                    </div>

                    <!-- Regular Results -->
                    <div class="search-results list-view">
                        <!-- Results loop here -->
                    </div>
                </main>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function render_filters($taxonomies)
    {
        foreach ($taxonomies as $tax) {
            $taxonomy = get_taxonomy($tax);
            if (!$taxonomy)
                continue;
            ?>
            <div class="filter-group">
                <h4>
                    <?php echo $taxonomy->label; ?>
                </h4>
                <ul class="filter-list">
                    <?php
                    $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
                    foreach ($terms as $term) {
                        echo "<li><label><input type='checkbox' value='{$term->slug}'> {$term->name} ({$term->count})</label></li>";
                    }
                    ?>
                </ul>
            </div>
            <?php
        }
    }
}
