/**
 * Editor script for the `lpg/enquiry-button` dynamic block.
 * Rendering is server-side (render.php → LPG\Commerce\Blocks\EnquiryButton).
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.css';

function EmptyPlaceholder() {
	return (
		<Placeholder
			icon="format-chat"
			label={ __( 'Enquire on WhatsApp', 'lpg-commerce-core' ) }
			instructions={ __(
				'Shows the WhatsApp enquiry button for the current product. Nothing is displayed when no WhatsApp number is set in WooCommerce → Settings → LPG, or (by default) for products that can be bought online.',
				'lpg-commerce-core'
			) }
		/>
	);
}

function Edit( { attributes, setAttributes, context } ) {
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'lpg-commerce-core' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Show for', 'lpg-commerce-core' ) }
						value={ attributes.showFor }
						options={ [
							{
								label: __(
									'Enquiry-only products',
									'lpg-commerce-core'
								),
								value: 'enquiry-only',
							},
							{
								label: __(
									'All products',
									'lpg-commerce-core'
								),
								value: 'all',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { showFor: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					urlQueryArgs={
						context?.postId ? { post_id: context.postId } : {}
					}
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
