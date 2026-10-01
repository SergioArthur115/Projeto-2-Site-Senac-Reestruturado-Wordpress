<?php
/**
 * MetForm remote template-library source for Elementor.
 *
 * Extends Elementor's `Source_Base` so remote MetForm templates are processed
 * with the very same pipeline Elementor uses for its own library: element IDs
 * are regenerated, `on_import` element callbacks run, and the resulting tree is
 * normalized through the created document.
 *
 * @package MetForm\Core\Forms\Template_Library
 * @since 4.1.6
 */

namespace MetForm\Core\Forms\Template_Library;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only Elementor source backed by the wpmet template API.
 *
 * @since 4.1.6
 */
class Source extends \Elementor\TemplateLibrary\Source_Base {

	/**
	 * Identifier Elementor uses to address this source.
	 *
	 * @since 4.1.6
	 * @var string
	 */
	const SOURCE_ID = 'metform-template-library';

	/**
	 * Register the source with Elementor's template manager.
	 *
	 * Safe to call unconditionally; it no-ops when Elementor is unavailable.
	 *
	 * @since 4.1.6
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! self::is_elementor_ready() ) {
			return;
		}

		\Elementor\Plugin::$instance->templates_manager->register_source(
			'MetForm\Core\Forms\Template_Library\Source'
		);
	}

	/**
	 * Check whether Elementor is loaded far enough to process template data.
	 *
	 * @since 4.1.6
	 *
	 * @return bool True when Elementor's singleton is available.
	 */
	public static function is_elementor_ready() {
		return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance );
	}

	/**
	 * Retrieve an instance without going through Elementor's manager.
	 *
	 * The importer needs the processing helpers even when the request never
	 * touches the Elementor editor.
	 *
	 * @since 4.1.6
	 *
	 * @return self Source instance.
	 */
	public static function instance() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get the source ID.
	 *
	 * @since 4.1.6
	 *
	 * @return string Source identifier.
	 */
	public function get_id() {
		return self::SOURCE_ID;
	}

	/**
	 * Get the human readable source title.
	 *
	 * @since 4.1.6
	 *
	 * @return string Source title.
	 */
	public function get_title() {
		return esc_html__( 'MetForm Templates', 'metform' );
	}

	/**
	 * Register source data.
	 *
	 * Nothing is stored locally, so there is nothing to register.
	 *
	 * @since 4.1.6
	 *
	 * @return void
	 */
	public function register_data() {}

	/**
	 * List remote templates in the shape Elementor's library expects.
	 *
	 * @since 4.1.6
	 *
	 * @param array $args Optional listing arguments.
	 * @return array Formatted template items.
	 */
	public function get_items( $args = [] ) {
		$data = Remote_Api::get_templates( is_array( $args ) ? $args : [] );

		if ( is_wp_error( $data ) ) {
			return [];
		}

		$items = [];

		// `layouts` is a map keyed by each layout's searchable unique name.
		foreach ( ( isset( $data['layouts'] ) ? (array) $data['layouts'] : [] ) as $layout ) {
			$items[] = $this->format_item( (array) $layout );
		}

		return $items;
	}

	/**
	 * Get a single listing item.
	 *
	 * @since 4.1.6
	 *
	 * @param int|string $template_id Remote template ID.
	 * @return array Formatted template item, or an empty array when missing.
	 */
	public function get_item( $template_id ) {
		foreach ( $this->get_items() as $item ) {
			if ( (string) $item['template_id'] === (string) $template_id ) {
				return $item;
			}
		}

		return [];
	}

	/**
	 * Get processed template content for Elementor.
	 *
	 * @since 4.1.6
	 *
	 * @param array $args Must contain `template_id`.
	 * @return array Content, type, and page settings.
	 */
	public function get_data( array $args ) {
		$form = $this->get_form_data( isset( $args['template_id'] ) ? $args['template_id'] : 0 );

		if ( is_wp_error( $form ) ) {
			return [
				'content'       => [],
				'type'          => 'section',
				'page_settings' => [],
			];
		}

		return [
			'content'       => $form['content'],
			'type'          => 'section',
			'page_settings' => [],
		];
	}

	/**
	 * Fetch a remote template and extract its MetForm form payload.
	 *
	 * The remote envelope carries the form flat under `metform_content.forms`,
	 * where `elementor_data` holds the builder tree and `form_setting` holds
	 * MetForm's own settings; the package and title live under `metadata`. The
	 * extracted tree is passed through
	 * `replace_elements_ids()` and `process_export_import_content()` before it
	 * is handed back.
	 *
	 * @since 4.1.6
	 *
	 * @param int $template_id Remote template ID.
	 * @return array|\WP_Error {
	 *     Normalized template payload.
	 *
	 *     @type array  $content  Processed Elementor element tree.
	 *     @type array  $settings MetForm form settings.
	 *     @type string $title    Remote form title.
	 *     @type string $package  Remote package slug.
	 * }
	 */
	public function get_form_data( $template_id ) {
		$template = Remote_Api::get_template( $template_id );

		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$metadata = isset( $template['metadata'] ) ? (array) $template['metadata'] : [];
		$package  = isset( $metadata['package'] ) ? (string) $metadata['package'] : Access::PACKAGE_FREE;

		// The library also refuses Pro content upstream; this local check keeps
		// the gate in place if an API site runs without key enforcement.
		if ( ! Access::can_import( $package ) ) {
			return Access::denied_error();
		}

		$form = $this->extract_form( $template );

		if ( is_wp_error( $form ) ) {
			return $form;
		}

		return [
			'content'  => $this->prepare_content( $form['content'] ),
			'settings' => $form['settings'],
			'title'    => $form['title'],
			'package'  => $package,
		];
	}

	/**
	 * Pull the first usable form out of a remote template payload.
	 *
	 * @since 4.1.6
	 *
	 * @param array $template Decoded remote template payload.
	 * @return array|\WP_Error Raw content, settings, and title, or an error.
	 */
	private function extract_form( array $template ) {
		$forms = isset( $template['metform_content']['forms'] ) ? (array) $template['metform_content']['forms'] : [];

		foreach ( $this->form_candidates( $forms ) as $form ) {
			$form    = (array) $form;
			$content = $this->decode_elementor_data( isset( $form['elementor_data'] ) ? $form['elementor_data'] : null );

			if ( empty( $content ) ) {
				continue;
			}

			$fallback_title = isset( $template['metadata']['title'] ) ? (string) $template['metadata']['title'] : '';

			return [
				'content'  => $content,
				'settings' => ( isset( $form['form_setting'] ) && is_array( $form['form_setting'] ) ) ? $form['form_setting'] : [],
				'title'    => ! empty( $form['title'] ) ? (string) $form['title'] : $fallback_title,
			];
		}

		return new \WP_Error(
			'metform_template_empty',
			esc_html__( 'The selected template does not contain a form.', 'metform' ),
			[ 'status' => 422 ]
		);
	}

	/**
	 * Normalize the `forms` payload into a list of form definitions.
	 *
	 * A layout is fetched by ID and bundles a single form, so the endpoint
	 * returns that form flat: `forms: {elementor_data, form_setting}`. Earlier
	 * payloads nested a list of form objects under the same key, so both shapes
	 * are accepted and responses already cached keep importing.
	 *
	 * @since 4.1.6
	 *
	 * @param array $forms Raw `metform_content.forms` value.
	 * @return array List of form definitions.
	 */
	private function form_candidates( array $forms ) {
		if ( empty( $forms ) ) {
			return [];
		}

		// A flat definition is recognized by its own keys; anything else is a
		// list or map whose values are the definitions.
		if ( array_key_exists( 'elementor_data', $forms ) || array_key_exists( 'form_setting', $forms ) ) {
			return [ $forms ];
		}

		return $forms;
	}

	/**
	 * Decode an `elementor_data` value that may arrive as JSON or as an array.
	 *
	 * @since 4.1.6
	 *
	 * @param mixed $data Raw `elementor_data` value.
	 * @return array Element tree, or an empty array when undecodable.
	 */
	private function decode_elementor_data( $data ) {
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Run Elementor's import pipeline over a raw element tree.
	 *
	 * @since 4.1.6
	 *
	 * @param array $content Raw element tree.
	 * @return array Processed element tree.
	 */
	public function prepare_content( array $content ) {
		if ( empty( $content ) || ! self::is_elementor_ready() ) {
			return $content;
		}

		$content = $this->replace_elements_ids( $content );
		$content = $this->process_export_import_content( $content, 'on_import' );

		return is_array( $content ) ? $content : [];
	}

	/**
	 * Normalize processed content through the created form's document.
	 *
	 * Running `get_elements_raw_data()` lets the document apply its own element
	 * defaults and migrations, exactly as Elementor does when loading data it
	 * saved itself.
	 *
	 * @since 4.1.6
	 *
	 * @param int   $post_id Created `metform-form` post ID.
	 * @param array $content Processed element tree.
	 * @return array Document-normalized element tree.
	 */
	public function normalize_document_content( $post_id, array $content ) {
		if ( empty( $content ) || ! self::is_elementor_ready() ) {
			return $content;
		}

		$document = \Elementor\Plugin::$instance->documents->get( $post_id );

		if ( ! $document ) {
			return $content;
		}

		try {
			$normalized = $document->get_elements_raw_data( $content );
		} catch ( \Exception $exception ) {
			return $content;
		}

		return ( is_array( $normalized ) && ! empty( $normalized ) ) ? $normalized : $content;
	}

	/**
	 * Map a remote listing item onto Elementor's library item shape.
	 *
	 * @since 4.1.6
	 *
	 * @param array $item Remote listing item.
	 * @return array Elementor library item.
	 */
	private function format_item( array $item ) {
		$package = isset( $item['package'] ) ? (string) $item['package'] : Access::PACKAGE_FREE;

		return [
			'template_id'     => (string) ( isset( $item['ID'] ) ? $item['ID'] : 0 ),
			'source'          => $this->get_id(),
			'type'            => 'section',
			'subtype'         => isset( $item['type'] ) ? (string) $item['type'] : 'form',
			'title'           => isset( $item['title'] ) ? (string) $item['title'] : '',
			'thumbnail'       => isset( $item['thumbnail'] ) ? (string) $item['thumbnail'] : '',
			'date'            => time(),
			'author'          => isset( $item['author'] ) ? (string) $item['author'] : '',
			// Categories arrive as a term map keyed by slug.
			'tags'            => isset( $item['categories'] ) ? array_keys( (array) $item['categories'] ) : [],
			// Locked only when this install cannot actually import it, which is
			// how the layout-client source reports it too; a licensed Pro
			// install sees no lock on a Pro layout.
			'isPro'           => ! Access::can_import( $package ),
			'url'             => isset( $item['live_demo_url'] ) ? (string) $item['live_demo_url'] : '',
			'hasPageSettings' => false,
		];
	}

	/* --- Read-only source: every write operation below is refused. --- */

	/**
	 * Refuse to save a template into this source.
	 *
	 * @since 4.1.6
	 *
	 * @param array $template_data Ignored.
	 * @return \WP_Error Always an error.
	 */
	public function save_item( $template_data ) {
		return $this->read_only_error();
	}

	/**
	 * Refuse to update a template in this source.
	 *
	 * @since 4.1.6
	 *
	 * @param array $new_data Ignored.
	 * @return \WP_Error Always an error.
	 */
	public function update_item( $new_data ) {
		return $this->read_only_error();
	}

	/**
	 * Refuse to delete a template from this source.
	 *
	 * @since 4.1.6
	 *
	 * @param int|string $template_id Ignored.
	 * @return \WP_Error Always an error.
	 */
	public function delete_template( $template_id ) {
		return $this->read_only_error();
	}

	/**
	 * Refuse to export a template from this source.
	 *
	 * @since 4.1.6
	 *
	 * @param int|string $template_id Ignored.
	 * @return \WP_Error Always an error.
	 */
	public function export_template( $template_id ) {
		return $this->read_only_error();
	}

	/**
	 * Build the shared read-only rejection.
	 *
	 * @since 4.1.6
	 *
	 * @return \WP_Error Read-only error.
	 */
	private function read_only_error() {
		return new \WP_Error(
			'metform_template_read_only',
			esc_html__( 'The MetForm template library is read-only.', 'metform' ),
			[ 'status' => 403 ]
		);
	}
}
