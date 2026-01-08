<?php
namespace Jankx\SearchEngine\UI\Components;

class Sorter extends AbstractComponent
{
    protected $name = 'sorter';

    public function render($atts = [])
    {
        ob_start();
        ?>
        <div class="jankx-search-sorter" data-settings="<?php echo esc_attr(json_encode($atts)); ?>">
            <span>
                <?php echo esc_html($atts['label'] ?? 'Sort by'); ?>
            </span>
            <select class="sort-select">
                <option value="relevance">Relevance</option>
                <option value="date_desc">Newest</option>
                <option value="date_asc">Oldest</option>
                <option value="alphabetical">A-Z</option>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }
}
