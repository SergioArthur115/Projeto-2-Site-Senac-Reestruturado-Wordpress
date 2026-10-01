<?php
/**
 * Form creation and builder redirection.
 *
 * @package MetForm\Core\Forms
 */

namespace MetForm\Core\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Creates `metform-form` posts and hands them to the Elementor editor.
 */
class Builder {

	use \MetForm\Traits\Singleton;

	/**
	 * Meta key holding the Elementor element tree.
	 *
	 * @since 4.1.6
	 * @var string
	 */
	const META_ELEMENTOR_DATA = '_elementor_data';

	/**
	 * Meta key recording which remote template a form was cloned from.
	 *
	 * @since 4.1.6
	 * @var string
	 */
	const META_CLONED_ID = '_metform_cloned_id';

	/**
	 * Redirect to a form's Elementor editor.
	 *
	 * @param int $form_id Form post ID.
	 * @return void
	 */
	public function get_editor( $form_id ) {
		$form = get_post( $form_id );

		if ( ! $form ) {
			wp_die( esc_html__( 'The requested form could not be found.', 'metform' ) );
		}

		wp_safe_redirect( $this->get_editor_url( $form->ID ) );
		exit;
	}

	/**
	 * Build the Elementor editor URL for a form.
	 *
	 * @since 4.1.6
	 *
	 * @param int $form_id Form post ID.
	 * @return string Editor URL.
	 */
	public function get_editor_url( $form_id ) {
		return add_query_arg(
			[
				'post'   => absint( $form_id ),
				'action' => 'elementor',
			],
			admin_url( 'post.php' )
		);
	}

	/**
	 * Create a `metform-form` post, optionally seeded from an imported template.
	 *
	 * @param string     $title       Form title. Falls back to a generated name.
	 * @param int|string $template_id Remote template ID, or `0` for a blank form.
	 * @param array      $data {
	 *     Optional. Imported template payload.
	 *
	 *     @type string $form_type         MetForm form type.
	 *     @type array  $template_content  Processed Elementor element tree.
	 *     @type array  $template_settings MetForm settings from the template.
	 * }
	 * @return int|\WP_Error Created form post ID, or an error when insertion fails.
	 */
	public function create_form( $title, $template_id = 0, $data = [] ) {
		$data              = is_array( $data ) ? $data : [];
		$template_content  = isset( $data['template_content'] ) && is_array( $data['template_content'] ) ? $data['template_content'] : null;
		$template_settings = isset( $data['template_settings'] ) && is_array( $data['template_settings'] ) ? $data['template_settings'] : [];
		$title             = '' === trim( (string) $title ) ? 'New Form # ' . time() : $title;

		$form_id = wp_insert_post(
			[
				'post_author'  => get_current_user_id(),
				'post_content' => '',
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_type'    => Base::instance()->form->get_name(),
			],
			true
		);

		if ( is_wp_error( $form_id ) ) {
			return $form_id;
		}

		if ( ! $form_id ) {
			return new \WP_Error(
				'metform_form_not_created',
				esc_html__( 'The form could not be created.', 'metform' ),
				[ 'status' => 500 ]
			);
		}

		$this->save_form_settings( $form_id, $title, $template_settings, $data );
		$this->save_editor_meta( $form_id, $template_id );

		if ( null !== $template_content ) {
			$this->save_form_content( $form_id, $template_content );
		}

		return (int) $form_id;
	}

	/**
	 * Store the MetForm settings meta for a newly created form.
	 *
	 * Imported settings are layered over MetForm's own defaults, so every
	 * declared field is present even when the template omits it. Keys the
	 * template carries but this version does not declare are kept rather than
	 * filtered out: Pro builds and future releases add settings that a Free
	 * install cannot enumerate, and dropping them would quietly break the
	 * imported form.
	 *
	 * @since 4.1.6
	 *
	 * @param int    $form_id           Form post ID.
	 * @param string $title             Form title.
	 * @param array  $template_settings Settings imported from the template.
	 * @param array  $data              Full creation payload.
	 * @return void
	 */
	private function save_form_settings( $form_id, $title, array $template_settings, array $data ) {
		$fields = Base::instance()->form->get_form_settings_fields();

		$settings = array_map(
			function () {
				return '';
			},
			$fields
		);

		$settings = array_merge( $settings, $template_settings );

		$settings['success_message'] = esc_html__( 'Thank you! Form submitted successfully.', 'metform' );
		$settings['store_entries']   = '1';
		$settings['form_title']      = $title;

		if ( ! empty( $data['form_type'] ) ) {
			$settings['form_type'] = sanitize_key( $data['form_type'] );
		}

		update_post_meta( $form_id, Base::instance()->form->get_key_form_settings(), $settings );
	}

	/**
	 * Store the Elementor editor metadata for a newly created form.
	 *
	 * @since 4.1.6
	 *
	 * @param int        $form_id     Form post ID.
	 * @param int|string $template_id Remote template ID, or `0` for a blank form.
	 * @return void
	 */
	private function save_editor_meta( $form_id, $template_id ) {
		update_post_meta( $form_id, '_wp_page_template', 'elementor_canvas' );
		update_post_meta( $form_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $form_id, self::META_CLONED_ID, 'template-' . absint( $template_id ) );
	}

	/**
	 * Persist an Elementor element tree on a form.
	 *
	 * Elementor reads `_elementor_data` as a slashed JSON string, so the value is
	 * encoded and slashed the same way Elementor's own document save does.
	 *
	 * @since 4.1.6
	 *
	 * @param int   $form_id Form post ID.
	 * @param array $content Element tree.
	 * @return bool True when the meta was written.
	 */
	public function save_form_content( $form_id, array $content ) {
		$encoded = wp_json_encode( $content );

		if ( false === $encoded ) {
			return false;
		}

		return (bool) update_post_meta( $form_id, self::META_ELEMENTOR_DATA, wp_slash( $encoded ) );
	}
}
