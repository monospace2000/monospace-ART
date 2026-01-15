(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, MediaUpload, MediaUploadCheck, RichText } = wp.blockEditor;
    const { PanelBody, Button, RangeControl } = wp.components;
    const { createElement: el } = wp.element;

    registerBlockType('monospace/miniature-set', {
        title: 'Miniature Set',
        icon: 'images-alt2',
        category: 'common',

        attributes: {
            images: {
                type: 'array',
                default: []
            },
            caption: {
                type: 'string',
                default: ''
            },
            gap: {
                type: 'number',
                default: 20
            },
            padding: {
                type: 'number',
                default: 0
            }
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { images, caption, gap, padding } = attributes;

            const onSelectImages = function(newImages) {
                setAttributes({
                    images: newImages.slice(0, 3).map(function(img) {
                        return {
                            id: img.id,
                            url: img.url,
                            alt: img.alt || ''
                        };
                    })
                });
            };

            const removeImage = function(index) {
                const newImages = images.slice();
                newImages.splice(index, 1);
                setAttributes({ images: newImages });
            };

            return el('div', {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Settings' },
                        el(RangeControl, {
                            label: 'Gap Between Images',
                            value: gap,
                            onChange: function(val) { setAttributes({ gap: val }); },
                            min: 0,
                            max: 50
                        }),
                        el(RangeControl, {
                            label: 'Outer Padding',
                            value: padding,
                            onChange: function(val) { setAttributes({ padding: val }); },
                            min: 0,
                            max: 100
                        })
                    )
                ),

                el('div', {
                    className: 'monospace-miniature-set-editor',
                    style: { padding: padding + 'px', background: '#f5f5f5', borderRadius: '4px' }
                },
                    images.length > 0 ? [
                        el('div', {
                            key: 'grid',
                            style: {
                                display: 'grid',
                                gridTemplateColumns: 'repeat(' + images.length + ', 1fr)',
                                gap: gap + 'px',
                                marginBottom: '15px'
                            }
                        },
                            images.map(function(image, index) {
                                return el('div', {
                                    key: image.id,
                                    style: { position: 'relative' }
                                },
                                    el('img', {
                                        src: image.url,
                                        alt: image.alt,
                                        style: { width: '100%', height: 'auto', display: 'block', borderRadius: '5px' }
                                    }),
                                    el(Button, {
                                        isDestructive: true,
                                        isSmall: true,
                                        onClick: function() { removeImage(index); },
                                        style: {
                                            position: 'absolute',
                                            top: '8px',
                                            right: '8px',
                                            background: 'rgba(0,0,0,0.7)'
                                        }
                                    }, 'Remove')
                                );
                            })
                        ),

                        el(RichText, {
                            key: 'caption',
                            tagName: 'p',
                            value: caption,
                            onChange: function(val) { setAttributes({ caption: val }); },
                            placeholder: 'Add caption...',
                            style: { textAlign: 'center', fontStyle: 'italic', color: '#666' }
                        }),

                        images.length < 3 ? el(MediaUploadCheck, { key: 'replace' },
                            el(MediaUpload, {
                                onSelect: onSelectImages,
                                allowedTypes: ['image'],
                                multiple: true,
                                gallery: true,
                                value: images.map(function(img) { return img.id; }),
                                render: function(obj) {
                                    return el(Button, {
                                        onClick: obj.open,
                                        isSecondary: true,
                                        style: { marginTop: '10px' }
                                    }, 'Replace Images');
                                }
                            })
                        ) : null
                    ] : el(MediaUploadCheck, {},
                        el(MediaUpload, {
                            onSelect: onSelectImages,
                            allowedTypes: ['image'],
                            multiple: true,
                            gallery: true,
                            render: function(obj) {
                                return el(Button, {
                                    onClick: obj.open,
                                    isPrimary: true
                                }, 'Select 2-3 Images');
                            }
                        })
                    )
                )
            );
        },

        save: function() {
            return null;
        }
    });
})(window.wp);
