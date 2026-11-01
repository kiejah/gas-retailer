/**
 * Editor script for the `lpg/product-warranty` dynamic block.
 * Rendering is server-side (render.php → LPG\Commerce\Blocks\ProductWarranty).
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';

function EmptyPlaceholder() {
	return (
		<Placeholder
			icon="shield"
			label={ __( 'Product warranty', 'lpg-commerce-core' ) }
			instructions={ __(
				'Shows the warranty of the current product. Nothing is displayed for products without a warranty.',
				'lpg-commerce-core'
			) }
		/>
	);
}

function Edit( { attributes, setAttributes, context } ) {
	const { label } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'lpg-commerce-core' ) }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Label', 'lpg-commerce-core' ) }
						help={ __(
							'Leave empty to hide the label.',
							'lpg-commerce-core'
						) }
						value={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
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
