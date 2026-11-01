/**
 * Editor script for the `lpg/whatsapp-link` dynamic block.
 * Rendering is server-side (render.php → LPG\Commerce\Blocks\WhatsAppLink).
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';

function EmptyPlaceholder() {
	return (
		<Placeholder
			icon="phone"
			label={ __( 'WhatsApp and phone link', 'lpg-commerce-core' ) }
			instructions={ __(
				'Nothing is displayed until a WhatsApp number is set in WooCommerce → Settings → LPG.',
				'lpg-commerce-core'
			) }
		/>
	);
}

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'lpg-commerce-core' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show phone number', 'lpg-commerce-core' ) }
						checked={ !! attributes.showPhone }
						onChange={ ( value ) =>
							setAttributes( { showPhone: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					skipBlockSupportAttributes
					EmptyResponsePlaceholder={ EmptyPlaceholder }
				/>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
