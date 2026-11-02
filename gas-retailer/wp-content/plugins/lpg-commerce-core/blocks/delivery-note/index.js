/**
 * Editor script for the `lpg/delivery-note` dynamic block.
 * Rendering is server-side (render.php → LPG\Commerce\Blocks\DeliveryNote).
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextareaControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'lpg-commerce-core' ) }>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Note text', 'lpg-commerce-core' ) }
						help={ __(
							'Leave empty for the default text. The WhatsApp link is added automatically when a number is set.',
							'lpg-commerce-core'
						) }
						value={ attributes.text }
						onChange={ ( value ) =>
							setAttributes( { text: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					skipBlockSupportAttributes
				/>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
