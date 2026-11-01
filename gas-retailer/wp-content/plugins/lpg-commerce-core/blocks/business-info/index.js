/**
 * Editor script for the `lpg/business-info` dynamic block.
 * Rendering is server-side (render.php → LPG\Commerce\Blocks\BusinessInfo).
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.css';

function EmptyPlaceholder() {
	return (
		<Placeholder
			icon="location"
			label={ __( 'Business information', 'lpg-commerce-core' ) }
			instructions={ __(
				'Address, opening hours and phone from WooCommerce → Settings → LPG.',
				'lpg-commerce-core'
			) }
		/>
	);
}

function Edit( { attributes } ) {
	const blockProps = useBlockProps();

	return (
		<>
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
