<?php
/**
 * Free/Pro access rules for the template library.
 *
 * Every decision about whether a remote template may be imported lives here so
 * the REST controller, the Elementor source, and the importer all agree. The
 * React modal dims Pro cards for convenience only; this class is the authority.
 *
 * @package MetForm\Core\Forms\Template_Library
 * @since 4.1.6
 */

namespace MetForm\Core\Forms\Template_Library;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the site's package and gates Pro template access.
 *
 * @since 4.1.6
 */
class Access {

	/**
	 * Package slug used by remote templates that everyone may import.
	 *
	 * @since 4.1.6
	 * @var string
	 */
	const PACKAGE_FREE = 'free';

	/**
	 * Package slug used by remote templates that require a Pro licence.
	 *
	 * @since 4.1.6
	 * @var string
	 */
	const PACKAGE_PRO = 'pro';

	/**
	 * Check whether MetForm Pro is installed and loaded.
	 *
	 * Guarded with `class_exists()` so Free installs never fatal on the Pro
	 * namespace.
	 *
	 * @since 4.1.6
	 *
	 * @return bool True when the Pro plugin is active.
	 */
	public static function has_pro() {
		return class_exists( '\MetForm_Pro\Base\Package' );
	}

	/**
	 * Check whether the Pro quiz feature is available.
	 *
	 * @since 4.1.6
	 *
	 * @return bool True when quiz forms may be created.
	 */
	public static function has_quiz() {
		return class_exists( '\MetForm_Pro\Core\Features\Quiz\Integration' );
	}

	/**
	 * Check whether a paid licence tier is currently stored.
	 *
	 * @since 4.1.6
	 *
	 * @return bool True when the licence tier is anything other than Free.
	 */
	public static function has_licence() {
		if ( ! class_exists( '\MetForm\Utils\Util' ) ) {
			return false;
		}

		return \MetForm\Utils\Util::is_starter()
			|| \MetForm\Utils\Util::is_mid_tier()
			|| \MetForm\Utils\Util::is_top_tier();
	}

	/**
	 * Resolve the package slug this site should be served.
	 *
	 * Both the Pro plugin and an active licence tier are required, matching the
	 * `plugin_package` argument the ElementsKit layout-manager API expects.
	 *
	 * @since 4.1.6
	 *
	 * @return string Either `pro` or `free`.
	 */
	public static function get_package() {
		return ( self::has_pro() && self::has_licence() ) ? self::PACKAGE_PRO : self::PACKAGE_FREE;
	}

	/**
	 * Check whether a template of the given package may be imported.
	 *
	 * @since 4.1.6
	 *
	 * @param string $package Package slug declared by the remote template.
	 * @return bool True when the current site may import the template.
	 */
	public static function can_import( $package ) {
		if ( self::PACKAGE_PRO !== strtolower( (string) $package ) ) {
			return true;
		}

		return self::PACKAGE_PRO === self::get_package();
	}

	/**
	 * Build the error returned when a Pro template is requested without a licence.
	 *
	 * @since 4.1.6
	 *
	 * @return \WP_Error Payment-required error describing the restriction.
	 */
	public static function denied_error() {
		return new \WP_Error(
			'metform_template_requires_pro',
			esc_html__( 'This template is available in MetForm Pro. Please upgrade your plan to import it.', 'metform' ),
			[ 'status' => 402 ]
		);
	}
}
