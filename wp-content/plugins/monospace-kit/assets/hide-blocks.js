/* Hide a block (modules/editor/hide-blocks.php): attribute msHidden, toggle in the block sidebar, grayed out in the editor */
( function ( wp ) {
    var el = wp.element.createElement, Fragment = wp.element.Fragment;
    var hooks = wp.hooks, compose = wp.compose;

    hooks.addFilter( 'blocks.registerBlockType', 'monospace/hide-blocks-attr', function ( settings ) {
        settings.attributes = Object.assign( {}, settings.attributes, { msHidden: { type: 'boolean', default: false } } );
        return settings;
    } );

    hooks.addFilter( 'editor.BlockEdit', 'monospace/hide-blocks-control', compose.createHigherOrderComponent( function ( BlockEdit ) {
        return function ( props ) {
            return el( Fragment, null,
                el( BlockEdit, props ),
                props.isSelected ? el( wp.blockEditor.InspectorControls, null,
                    el( wp.components.PanelBody, { title: 'Visibility', initialOpen: true },
                        el( wp.components.ToggleControl, {
                            label: 'Hide this block',
                            help: props.attributes.msHidden ? 'Hidden on the site. Switch off to show it again.' : 'Leaves it out of the site; it stays here.',
                            checked: !! props.attributes.msHidden,
                            onChange: function ( v ) { props.setAttributes( { msHidden: v } ); }
                        } ) ) ) : null );
        };
    }, 'withHideControl' ) );

    hooks.addFilter( 'editor.BlockListBlock', 'monospace/hide-blocks-class', compose.createHigherOrderComponent( function ( BlockListBlock ) {
        return function ( props ) {
            if ( props.attributes && props.attributes.msHidden ) {
                props = Object.assign( {}, props, { className: ( props.className ? props.className + ' ' : '' ) + 'ms-block-hidden' } );
            }
            return el( BlockListBlock, props );
        };
    }, 'withHiddenClass' ) );
} )( window.wp );
