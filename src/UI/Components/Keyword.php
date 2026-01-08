<?php
namespace Jankx\SearchEngine\UI\Components;

class Keyword extends AbstractComponent
{
    protected $name = 'keyword';

    public function render($atts = [])
    {
        ob_start();
        ?>
        <div class="jankx-search-keyword">
            <div class="search-bar-wrapper">
                <input type="text" name="q" placeholder="<?php echo esc_attr($atts['placeholder'] ?? 'Search...'); ?>"
                    class="search-input">
                <button type="submit" class="search-btn">
                    <i class="icon-search"></i>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
