(function (blocks, element, blockEditor) {
    const el = element.createElement;
    const { InspectorControls } = blockEditor;
    const { PanelBody, TextControl, SelectControl, CheckboxControl } = wp.components;

    // 1. Keyword Block
    blocks.registerBlockType('jankx/search-keyword', {
        title: 'Search Keyword',
        icon: 'search',
        category: 'widgets',
        attributes: {
            placeholder: { type: 'string', default: 'Search...' }
        },
        edit: function (props) {
            return [
                el('div', { className: 'jankx-search-keyword-preview' },
                    el('input', { type: 'text', placeholder: props.attributes.placeholder, disabled: true })
                ),
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Settings' },
                        el(TextControl, {
                            label: 'Placeholder',
                            value: props.attributes.placeholder,
                            onChange: (val) => props.setAttributes({ placeholder: val })
                        })
                    )
                )
            ];
        },
        save: () => null // Dynamic block
    });

    // 2. Filter Block
    blocks.registerBlockType('jankx/search-filter', {
        title: 'Search Filter',
        icon: 'filter',
        category: 'widgets',
        attributes: {
            title: { type: 'string', default: 'Filter' },
            taxonomy: { type: 'string', default: 'industry' }
        },
        edit: function (props) {
            return [
                el('div', { className: 'jankx-search-filter-preview' },
                    el('strong', {}, props.attributes.title),
                    el('p', { style: { fontSize: '11px', color: '#666' } }, 'Taxonomy: ' + props.attributes.taxonomy)
                ),
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Settings' },
                        el(TextControl, {
                            label: 'Filter Title',
                            value: props.attributes.title,
                            onChange: (val) => props.setAttributes({ title: val })
                        }),
                        el(SelectControl, {
                            label: 'Taxonomy',
                            value: props.attributes.taxonomy,
                            options: [
                                { label: 'Industries', value: 'industry' },
                                { label: 'Authors', value: 'thought_leader' },
                                { label: 'Content Types', value: 'content_type' }
                            ],
                            onChange: (val) => props.setAttributes({ taxonomy: val })
                        })
                    )
                )
            ];
        },
        save: () => null
    });

    // 3. Results Block
    blocks.registerBlockType('jankx/search-results', {
        title: 'Search Results',
        icon: 'list-view',
        category: 'widgets',
        attributes: {
            show_featured: { type: 'boolean', default: true },
            preset: { type: 'string', default: 'default' }
        },
        edit: function (props) {
            return [
                el('div', {
                    className: 'jankx-search-results-preview',
                    style: { padding: '20px', border: '1px dashed #ccc', textAlign: 'center' }
                }, 'Search Results Grid Placeholder'),
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Settings' },
                        el(CheckboxControl, {
                            label: 'Show Featured Items',
                            checked: props.attributes.show_featured,
                            onChange: (val) => props.setAttributes({ show_featured: val })
                        }),
                        el(SelectControl, {
                            label: 'UI Preset',
                            value: props.attributes.preset,
                            options: [
                                { label: 'Default List', value: 'default' },
                                { label: 'Grid/Cards', value: 'grid' },
                                { label: 'Akselos Official', value: 'akselos' }
                            ],
                            onChange: (val) => props.setAttributes({ preset: val })
                        })
                    )
                )
            ];
        },
        save: () => null
    });

})(window.wp.blocks, window.wp.element, window.wp.blockEditor);
