<?php
/**
 * Render-time page link helpers shared by the patterns and nav items.
 *
 * One place for the "link to this page only when it is published" check
 * (architecture §12.2, C23), instead of a copy in each pattern. Presentation
 * only: it reads which pages exist and changes no data.
 *
 * Load with `require_once get_theme_file_path( 'inc/page-links.php' );`.
 *
 * @package lpg_storefront
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'lpg_storefront_published_page_url' ) ) {
	/**
	 * Returns the permalink of a published page with the given slug.
	 *
	 * @param string $slug Page slug, e.g. `request-a-quote`.
	 * @return string Permalink, or '' when no published page has that slug.
	 */
	function lpg_storefront_published_page_url( $slug ) {
		$page = get_page_by_path( $slug );
		if ( ! $page || 'publish' !== get_post_status( $page ) ) {
			return '';
		}
		$url = get_permalink( $page );
		return $url ? $url : '';
	}
}

if ( ! function_exists( 'lpg_storefront_term_url' ) ) {
	/**
	 * Returns the archive link of a term, or '' when it does not exist.
	 *
	 * @param string $slug     Term slug.
	 * @param string $taxonomy Taxonomy name.
	 * @return string
	 */
	function lpg_storefront_term_url( $slug, $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}
		$term = get_term_by( 'slug', $slug, $taxonomy );
		$url  = $term ? get_term_link( $term ) : '';
		return ( $url && ! is_wp_error( $url ) ) ? $url : '';
	}
}

if ( ! function_exists( 'lpg_storefront_refills_url' ) ) {
	/**
	 * Where "Refills & Exchange" links point (DEF-014, C23): the published
	 * `refills-exchange` page (with the exchange form) when it exists,
	 * otherwise the product category.
	 *
	 * @return string URL, or '' when neither exists.
	 */
	function lpg_storefront_refills_url() {
		$url = lpg_storefront_published_page_url( 'refills-exchange' );
		return $url ? $url : lpg_storefront_term_url( 'refills-exchange', 'product_cat' );
	}
}
