<?php
/**
 * Registers the plugin's dynamic blocks.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Blocks;

use LPG\Commerce\Service;

defined( 'ABSPATH' ) || exit;

/**
 * §11.9: registers `lpg/*` blocks from the build manifest, or (before the
 * first build) from the source block.json files without editor assets, so
 * the front end renders and no missing-asset notice is raised.
 */
final class Registry implements Service {

	/**
	 * Block directory names (source `blocks/<name>`, build `build/blocks/<name>`).
	 */
	public const BLOCKS = array( 'spec-table', 'compatible-products', 'product-documents', 'product-warranty', 'enquiry-button', 'rfq-form', 'exchange-form', 'whatsapp-link', 'business-info' );

	/**
	 * Attach hooks.
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'register_blocks' ) );
	}

	/**
	 * Built blocks directory.
	 */
	public static function build_dir(): string {
		return LPG_COMMERCE_PATH . 'build/blocks';
	}

	/**
	 * Source blocks directory.
	 */
	public static function source_dir(): string {
		return LPG_COMMERCE_PATH . 'blocks';
	}

	/**
	 * Register all blocks (hooked to `init`).
	 */
	public static function register_blocks(): void {
		add_filter( 'block_type_metadata_settings', array( self::class, 'defer_view_scripts' ), 10, 2 );

		$build    = self::build_dir();
		$manifest = $build . '/blocks-manifest.php';

		if ( is_readable( $manifest ) ) {
			// WordPress 6.8+ bulk registration, only when none of our blocks exist yet:
			// it does not skip registered types, so a repeat call would trigger
			// "block type already registered" notices.
			if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) && ! self::any_registered() ) {
				wp_register_block_types_from_metadata_collection( $build, $manifest );
				return;
			}
			foreach ( self::BLOCKS as $name ) {
				self::register_one( $build . '/' . $name );
			}
			return;
		}

		self::register_from_source();
	}

	/**
	 * Core enqueues a block's `viewScript` whenever the block is rendered, even
	 * when the renderer outputs nothing (no WhatsApp number, no product...).
	 * For LPG blocks the handle stays registered but is not attached to the
	 * block type; the renderer enqueues it only when it prints markup.
	 *
	 * @param array<string, mixed> $settings Block type settings.
	 * @param array<string, mixed> $metadata Block metadata.
	 * @return array<string, mixed>
	 */
	public static function defer_view_scripts( $settings, $metadata ) {
		if ( is_array( $settings ) && is_array( $metadata ) && 0 === strpos( (string) ( $metadata['name'] ?? '' ), 'lpg/' ) ) {
			$settings['view_script_handles'] = array();
		}
		return $settings;
	}

	/**
	 * Enqueue a block's front-end script (built `viewScript`), if registered.
	 *
	 * @param string $block_name Block name, e.g. `lpg/rfq-form`.
	 */
	public static function enqueue_view_script( string $block_name ): void {
		$handle = generate_block_asset_handle( $block_name, 'viewScript' );
		if ( wp_script_is( $handle, 'registered' ) ) {
			wp_enqueue_script( $handle );
		}
	}

	/**
	 * Register from the source block.json files, without editor scripts.
	 */
	public static function register_from_source(): void {
		add_filter( 'block_type_metadata', array( self::class, 'strip_source_assets' ) );
		foreach ( self::BLOCKS as $name ) {
			self::register_one( self::source_dir() . '/' . $name );
		}
		remove_filter( 'block_type_metadata', array( self::class, 'strip_source_assets' ) );
	}

	/**
	 * Source block.json files reference build output (`index.js`,
	 * `style-index.css`) that does not exist before the build. Drop the scripts
	 * and point `style` at the source `style.css`, if present.
	 *
	 * @param array<string, mixed> $metadata Block metadata.
	 * @return array<string, mixed>
	 */
	public static function strip_source_assets( $metadata ) {
		if ( ! is_array( $metadata ) || empty( $metadata['file'] ) || empty( $metadata['name'] ) ) {
			return $metadata;
		}
		$source = trailingslashit( wp_normalize_path( (string) realpath( self::source_dir() ) ) );
		if ( 0 !== strpos( wp_normalize_path( (string) $metadata['file'] ), $source ) ) {
			return $metadata;
		}

		unset( $metadata['editorScript'], $metadata['script'], $metadata['viewScript'], $metadata['viewScriptModule'], $metadata['editorStyle'] );
		if ( is_readable( dirname( (string) $metadata['file'] ) . '/style.css' ) ) {
			$metadata['style'] = 'file:./style.css';
		} else {
			unset( $metadata['style'] );
		}

		return $metadata;
	}

	/**
	 * Whether any LPG block type is already registered.
	 */
	private static function any_registered(): bool {
		$registry = \WP_Block_Type_Registry::get_instance();
		foreach ( self::BLOCKS as $name ) {
			if ( $registry->is_registered( 'lpg/' . $name ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Register one block from a directory containing block.json, unless already registered.
	 *
	 * @param string $dir Block directory.
	 */
	private static function register_one( string $dir ): void {
		$file = $dir . '/block.json';
		if ( ! is_readable( $file ) ) {
			return;
		}
		$metadata = wp_json_file_decode( $file, array( 'associative' => true ) );
		$name     = is_array( $metadata ) && isset( $metadata['name'] ) ? (string) $metadata['name'] : '';
		if ( '' === $name || \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
			return;
		}
		register_block_type_from_metadata( $dir );
	}
}
