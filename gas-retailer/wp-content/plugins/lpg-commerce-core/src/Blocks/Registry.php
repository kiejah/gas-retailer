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
	public const BLOCKS = array( 'spec-table', 'compatible-products', 'product-documents' );

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
		$build    = self::build_dir();
		$manifest = $build . '/blocks-manifest.php';

		if ( is_readable( $manifest ) ) {
			if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
				// WordPress 6.8+.
				wp_register_block_types_from_metadata_collection( $build, $manifest );
				return;
			}
			if ( function_exists( 'wp_register_block_metadata_collection' ) ) {
				// WordPress 6.7.
				wp_register_block_metadata_collection( $build, $manifest );
			}
			foreach ( self::BLOCKS as $name ) {
				self::register_one( $build . '/' . $name );
			}
			return;
		}

		self::register_from_source();
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
	 * Register one block directory unless that block is already registered.
	 *
	 * @param string $dir Directory containing block.json.
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
