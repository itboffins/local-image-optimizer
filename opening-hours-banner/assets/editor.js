/* Opening Hours by IT Boffins — block editor script.
 *
 * No build step: relies on the WordPress packages bundled with core, referenced
 * via the global `wp` object. The block is server-rendered (ServerSideRender),
 * so the editor preview matches the front end exactly.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var ServerSideRender = wp.serverSideRender;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType( 'iboh/opening-hours', {
		apiVersion: 2,
		title: __( 'Opening Hours', 'opening-hours-banner' ),
		description: __( 'Show your opening hours and live open/closed status.', 'opening-hours-banner' ),
		icon: 'clock',
		category: 'widgets',
		keywords: [ __( 'hours', 'opening-hours-banner' ), __( 'open', 'opening-hours-banner' ), __( 'business', 'opening-hours-banner' ) ],
		attributes: {
			show: { type: 'string', 'default': 'table' }
		},
		edit: function ( props ) {
			var blockProps = useBlockProps ? useBlockProps() : {};
			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{ key: 'inspector' },
					el(
						PanelBody,
						{ title: __( 'Display', 'opening-hours-banner' ) },
						el( SelectControl, {
							label: __( 'Show', 'opening-hours-banner' ),
							value: props.attributes.show,
							options: [
								{ label: __( 'Hours table', 'opening-hours-banner' ), value: 'table' },
								{ label: __( 'Open/closed status', 'opening-hours-banner' ), value: 'status' },
								{ label: __( 'Both', 'opening-hours-banner' ), value: 'both' }
							],
							onChange: function ( value ) {
								props.setAttributes( { show: value } );
							}
						} )
					)
				),
				el( ServerSideRender, {
					block: 'iboh/opening-hours',
					attributes: props.attributes
				} )
			);
		},
		save: function () {
			return null; // Dynamic block, rendered in PHP.
		}
	} );
} )( window.wp );
