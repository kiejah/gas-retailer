<?php
/**
 * `wp lpg seed`: starter spec profiles and product categories.
 *
 * @package LPG\Commerce
 */

namespace LPG\Commerce\Cli;

use LPG\Commerce\Specs\ProfilePostType;
use LPG\Commerce\Specs\ProfileRepository;
use LPG\Commerce\Specs\ProfileResolver;
use LPG\Commerce\Taxonomies\CylinderBrand;

defined( 'ABSPATH' ) || exit;

/**
 * Seeds the four spec profiles (field definitions only), the starter
 * product_cat tree and the starter cylinder brands (§4.1, §11.3, §13.2, C8). Idempotent: creates missing items only
 * and never overwrites profiles, field definitions or category assignments.
 * Never creates products or spec values.
 */
final class SeedCommand {

	/**
	 * Seeds the starter spec profiles, product categories and cylinder brands.
	 *
	 * Creates missing items only. Existing profiles, field definitions,
	 * category profile assignments and cylinder brands (and their sizes) are
	 * never changed. No products are created.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lpg seed
	 *
	 * @param array<int, string>    $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Associative arguments (unused).
	 */
	public function __invoke( array $args = array(), array $assoc_args = array() ): void {
		unset( $args, $assoc_args );
		$summary = self::run();

		foreach ( $summary['log'] as $line ) {
			\WP_CLI::log( $line );
		}
		foreach ( $summary['errors'] as $line ) {
			\WP_CLI::warning( $line );
		}
		\WP_CLI::success(
			sprintf(
				'Profiles: %d created, %d already present. Categories: %d created, %d already present. Default profiles: %d assigned, %d left unchanged. Cylinder brands: %d created, %d already present.',
				$summary['profiles_created'],
				$summary['profiles_existing'],
				$summary['categories_created'],
				$summary['categories_existing'],
				$summary['assignments_made'],
				$summary['assignments_kept'],
				$summary['brands_created'],
				$summary['brands_existing']
			)
		);
	}

	/**
	 * Perform the seed. Usable without WP-CLI (tests).
	 *
	 * @return array{profiles_created: int, profiles_existing: int, categories_created: int, categories_existing: int, assignments_made: int, assignments_kept: int, brands_created: int, brands_existing: int, log: string[], errors: string[]}
	 */
	public static function run(): array {
		$summary = array(
			'profiles_created'    => 0,
			'profiles_existing'   => 0,
			'categories_created'  => 0,
			'categories_existing' => 0,
			'assignments_made'    => 0,
			'assignments_kept'    => 0,
			'brands_created'      => 0,
			'brands_existing'     => 0,
			'log'                 => array(),
			'errors'              => array(),
		);

		$profile_ids = array();
		foreach ( self::profiles() as $slug => $profile ) {
			$existing = get_posts(
				array(
					'post_type'        => ProfilePostType::POST_TYPE,
					'name'             => $slug,
					'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'numberposts'      => 1,
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			);
			if ( $existing ) {
				$profile_ids[ $slug ] = (int) $existing[0];
				++$summary['profiles_existing'];
				$summary['log'][] = sprintf( 'Profile "%s" already exists (#%d); left unchanged.', $profile['title'], $existing[0] );
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'   => ProfilePostType::POST_TYPE,
					'post_title'  => $profile['title'],
					'post_name'   => $slug,
					'post_status' => 'publish',
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$summary['errors'][] = sprintf( 'Could not create profile "%s": %s', $profile['title'], $id->get_error_message() );
				continue;
			}
			foreach ( ProfilePostType::save_fields( (int) $id, $profile['fields'] ) as $error ) {
				$summary['errors'][] = sprintf( '%s: %s', $profile['title'], $error );
			}
			$profile_ids[ $slug ] = (int) $id;
			++$summary['profiles_created'];
			$summary['log'][] = sprintf( 'Created profile "%s" (#%d) with %d fields.', $profile['title'], $id, count( ProfileRepository::get_fields( (int) $id ) ) );
		}//end foreach

		foreach ( self::categories() as $slug => $category ) {
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			if ( $term instanceof \WP_Term ) {
				$term_id = (int) $term->term_id;
				++$summary['categories_existing'];
			} else {
				$inserted = wp_insert_term( $category['name'], 'product_cat', array( 'slug' => $slug ) );
				if ( is_wp_error( $inserted ) ) {
					$summary['errors'][] = sprintf( 'Could not create category "%s": %s', $category['name'], $inserted->get_error_message() );
					continue;
				}
				$term_id = (int) $inserted['term_id'];
				++$summary['categories_created'];
				$summary['log'][] = sprintf( 'Created category "%s" (#%d).', $category['name'], $term_id );
			}

			if ( empty( $category['profile'] ) || empty( $profile_ids[ $category['profile'] ] ) ) {
				continue;
			}
			if ( get_term_meta( $term_id, ProfileResolver::TERM_META, true ) ) {
				++$summary['assignments_kept'];
				continue;
			}
			update_term_meta( $term_id, ProfileResolver::TERM_META, $profile_ids[ $category['profile'] ] );
			++$summary['assignments_made'];
			$summary['log'][] = sprintf( 'Assigned default profile "%s" to category "%s".', self::profiles()[ $category['profile'] ]['title'], $category['name'] );
		}//end foreach

		$brands                     = CylinderBrand::seed();
		$summary['brands_created']  = $brands['created'];
		$summary['brands_existing'] = $brands['existing'];
		$summary['log']             = array_merge( $summary['log'], $brands['log'] );
		$summary['errors']          = array_merge( $summary['errors'], $brands['errors'] );

		return $summary;
	}

	/**
	 * Starter top-level categories from architecture §4.1, slug => name and
	 * default profile slug. Subcategories are left to the store owner.
	 *
	 * @return array<string, array{name: string, profile?: string}>
	 */
	public static function categories(): array {
		return array(
			'cylinders'            => array(
				'name'    => 'Cylinders',
				'profile' => 'cylinder',
			),
			'cookers-ovens'        => array(
				'name'    => 'Cookers & Ovens',
				'profile' => 'gas-cooker',
			),
			'burners'              => array(
				'name'    => 'Burners',
				'profile' => 'burner',
			),
			'regulators'           => array(
				'name'    => 'Regulators',
				'profile' => 'regulator',
			),
			'hoses-fittings'       => array( 'name' => 'Hoses & Fittings' ),
			'manifolds-changeover' => array( 'name' => 'Manifolds & Changeover' ),
			'valves-piping'        => array( 'name' => 'Valves & Piping' ),
			'gauges-monitoring'    => array( 'name' => 'Gauges & Monitoring' ),
			'safety-equipment'     => array( 'name' => 'Safety Equipment' ),
			'lpg-accessories'      => array( 'name' => 'LPG Accessories' ),
			'refills-exchange'     => array( 'name' => 'Refills & Exchange' ),
		);
	}

	/**
	 * The four profiles from the brief: field definitions only, no values.
	 * Select options are generic categories, never product or certification claims.
	 *
	 * @return array<string, array{title: string, fields: array<int, array<string, mixed>>}>
	 */
	public static function profiles(): array {
		$pressure_help = 'Pressure or pressure range with its unit, exactly as stated by the supplier.';

		return array(
			'regulator'  => array(
				'title'  => 'Regulator',
				'fields' => array(
					self::field( 'regulator_type', 'Regulator type', 'select', '', true, '', array( 'Low pressure', 'High pressure', 'Adjustable', 'Two-stage', 'Automatic changeover' ) ),
					self::field( 'operating_pressure', 'Operating pressure', 'text', '', true, $pressure_help ),
					self::field( 'outlet_pressure', 'Outlet pressure', 'number', 'mbar', true ),
					self::field( 'flow_rate', 'Flow rate', 'number', 'kg/h' ),
					self::field( 'inlet_connection', 'Inlet connection', 'text', '', false, 'Connection type or size as stated by the supplier.' ),
					self::field( 'outlet_connection', 'Outlet connection', 'text', '', false, 'Connection type or size as stated by the supplier.' ),
					self::field( 'compatible_applications', 'Compatible applications', 'text', '', false, 'Appliances or uses the supplier lists, separated by commas.' ),
				),
			),
			'burner'     => array(
				'title'  => 'Burner',
				'fields' => array(
					self::field( 'burner_type', 'Burner type', 'select', '', true, '', array( 'Ring burner', 'Jet burner', 'Infrared burner', 'Other' ) ),
					self::field( 'number_of_rings', 'Number of rings', 'number' ),
					self::field( 'heat_output', 'Heat output', 'number', 'kW' ),
					self::field( 'gas_consumption', 'Gas consumption', 'number', 'kg/h' ),
					self::field( 'operating_pressure', 'Operating pressure', 'text', '', true, $pressure_help ),
					self::field( 'material', 'Material', 'text' ),
					self::field( 'dimensions', 'Dimensions', 'text', '', false, 'For example: length × width × height in mm.' ),
				),
			),
			'gas-cooker' => array(
				'title'  => 'Gas Cooker',
				'fields' => array(
					self::field( 'number_of_burners', 'Number of burners', 'number', '', true ),
					self::field( 'ignition_type', 'Ignition type', 'select', '', true, '', array( 'Manual', 'Piezo', 'Electronic' ) ),
					self::field( 'oven', 'Oven', 'boolean', '', true, 'Yes if the cooker includes an oven.' ),
					self::field( 'dimensions', 'Dimensions', 'text', '', false, 'For example: length × width × height in mm.' ),
					self::field( 'gas_compatibility', 'Gas compatibility', 'text', '', false, 'Gas types the supplier states the cooker works with.' ),
				),
			),
			'cylinder'   => array(
				'title'  => 'Cylinder',
				'fields' => array(
					self::field( 'capacity', 'Capacity', 'number', 'kg', true ),
					self::field( 'cylinder_classification', 'Ownership / exchange', 'select', '', true, '', array( 'New (outright purchase)', 'Exchange', 'Refill' ) ),
					self::field( 'valve_type', 'Valve type', 'select', '', true, '', array( 'Clip-on', 'Screw-on', 'Other' ) ),
					self::field( 'compatibility_info', 'Compatibility information', 'text', '', false, 'Regulators or appliances the supplier states it works with.' ),
				),
			),
		);
	}

	/**
	 * Build a field definition row.
	 *
	 * @param string   $key        Key.
	 * @param string   $label      Label.
	 * @param string   $type       Type.
	 * @param string   $unit       Unit.
	 * @param bool     $filterable Filterable.
	 * @param string   $help       Help text.
	 * @param string[] $options    Select options.
	 * @return array<string, mixed>
	 */
	private static function field( string $key, string $label, string $type, string $unit = '', bool $filterable = false, string $help = '', array $options = array() ): array {
		return array(
			'key'        => $key,
			'label'      => $label,
			'type'       => $type,
			'unit'       => $unit,
			'options'    => $options,
			'filterable' => $filterable,
			'help'       => $help,
		);
	}
}
