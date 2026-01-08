<?php
namespace Jankx\SearchEngine\UI\Components;

class Filter extends AbstractComponent
{
    protected $name = 'filter';

    public function render($atts = [])
    {
        $taxonomy = $atts['taxonomy'] ?? 'category';
        $tax_obj = get_taxonomy($taxonomy);

        if (!$tax_obj) {
            return '';
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => $atts['hide_empty'] ?? false,
        ]);

        ob_start();
        ?>
        <div class="jankx-search-filter filter-<?php echo esc_attr($taxonomy); ?>"
            data-settings="<?php echo esc_attr(json_encode($atts)); ?>">
            <h4 class="filter-title">
                <?php echo esc_html($atts['title'] ?? $tax_obj->label); ?>
            </h4>
            <ul class="filter-list">
                <?php foreach ($terms as $term): ?>
                    <li>
                        <label>
                            <input type="checkbox" name="filter[<?php echo $taxonomy; ?>][]"
                                value="<?php echo esc_attr($term->slug); ?>">
                            <span class="term-name">
                                <?php echo esc_html($term->name); ?>
                            </span>
                            <span class="term-count">(
                                <?php echo $term->count; ?>)
                            </span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}
