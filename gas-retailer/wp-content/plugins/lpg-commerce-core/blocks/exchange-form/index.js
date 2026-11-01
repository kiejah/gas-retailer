/**
 * Editor script for the `lpg/exchange-form` dynamic block.
 * Rendering is server-side (render.php → LPG\Commerce\Blocks\ExchangeForm).
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
			icon="update"
			label={ __(
				'Cylinder refill / exchange form',
				'lpg-commerce-core'
			) }
			instructions={ __(
				'Nothing is displayed until a WhatsApp number is set in WooCommerce → Settings → LPG and at least one cylinder brand exists (Products → Cylinder brands).',
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
