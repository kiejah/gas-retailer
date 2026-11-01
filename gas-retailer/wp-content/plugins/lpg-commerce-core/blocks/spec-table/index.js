/**
 * Editor script for the `lpg/spec-table` dynamic block.
 * Rendering is server-side (render.php → LPG\Commerce\Blocks\SpecTable).
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.css';

function EmptyPlaceholder() {
	return (
		<Placeholder
			icon="list-view"
			label={ __( 'Product specifications', 'lpg-commerce-core' ) }
			instructions={ __(
				'Shows the technical specifications of the current product. Nothing is displayed for products without specifications.',
				'lpg-commerce-core'
			) }
		/>
	);
}

function Edit( { attributes, setAttributes, context } ) {
	const { heading } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'lpg-commerce-core' ) }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Heading', 'lpg-commerce-core' ) }
						help={ __( 'Leave empty to hide the heading.', 'lpg-commerce-core' ) }
						value={ heading }
						onChange={ ( value ) => setAttributes( { heading: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					urlQueryArgs={ context?.postId ? { post_id: context.postId } : {} }
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
