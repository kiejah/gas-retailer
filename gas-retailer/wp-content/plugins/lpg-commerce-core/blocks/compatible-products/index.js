/**
 * Editor script for the `lpg/compatible-products` dynamic block.
 * Rendering is server-side (render.php → LPG\Commerce\Blocks\CompatibleProducts).
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	RangeControl,
	TextControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.css';

// Keep in sync with LPG\Commerce\Blocks\CompatibleProducts (also clamped in PHP).
const COLUMNS = { min: 2, max: 6 };
const LIMIT = { min: 1, max: 12 };

function EmptyPlaceholder() {
	return (
		<Placeholder
			icon="products"
			label={ __( 'Compatible products', 'lpg-commerce-core' ) }
			instructions={ __(
				'Shows the products marked as compatible with the current product. Nothing is displayed when there are none.',
				'lpg-commerce-core'
			) }
		/>
	);
}

function Edit( { attributes, setAttributes, context } ) {
	const { heading, columns, limit } = attributes;
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
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Columns', 'lpg-commerce-core' ) }
						value={ columns }
						min={ COLUMNS.min }
						max={ COLUMNS.max }
						onChange={ ( value ) => setAttributes( { columns: value } ) }
					/>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Maximum number of products', 'lpg-commerce-core' ) }
						value={ limit }
						min={ LIMIT.min }
						max={ LIMIT.max }
						onChange={ ( value ) => setAttributes( { limit: value } ) }
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
