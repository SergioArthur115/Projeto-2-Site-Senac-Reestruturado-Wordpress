<?php
namespace MetForm\Core\Forms;

use MetForm\Base\Assets_Enqueue;

defined( 'ABSPATH' ) || exit;

Class Base extends \MetForm\Base\Common{

    use \MetForm\Traits\Singleton;

    public $form;

    public $api;

    public function get_dir(){
        return dirname(__FILE__);
    }

    public function __construct(){
    }

    public function init(){
        $this->form = new Cpt();
        $this->api = new Api();
        Hooks::instance()->Init();
        \MetForm\Base\Shortcode::instance();

        add_action('admin_footer', [$this, 'modal_view']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_react_modal_scripts']);

        // Register the remote template library with Elementor's template manager.
        add_action('elementor/init', [Template_Library\Source::class, 'register']);
    }

    public function modal_view(){

        $screen = get_current_screen();

        if($screen->id == 'edit-metform-form' || $screen->id == 'metform_page_mt-form-settings'){
            include_once 'views/modal-editor.php';

            // Include new modal for add new form
            include_once 'views/modal-add-new-form.php';
        }
    }

    public function enqueue_react_modal_scripts(){
        $screen = get_current_screen();
        $settings = get_option('metform_option__settings', []);
       // $formAnalyticsEnabled = class_exists('\MetForm_Pro\Plugin') && (\MetForm\Utils\Util::is_mid_tier() || \MetForm\Utils\Util::is_top_tier()) && !empty($settings['mf_enable_form_analytics']);
        // Only enqueue on metform-form post type edit page
        if($screen->id == 'edit-metform-form'){
            $plugin = \MetForm\Plugin::instance();
            Assets_Enqueue::get('metform-add-new-form-modal');

            wp_set_script_translations('metform-add-new-form-modal', 'metform');

            // Templates are fetched and cached by the shared React provider;
            // only URLs, the nonce, and Free/Pro status are localized here.
            wp_localize_script('metform-add-new-form-modal', 'metformData', [
                'pluginUrl' => $plugin->plugin_url(),
                'restUrl'   => get_rest_url(),
                'adminUrl'  => admin_url(),
                'nonce'     => wp_create_nonce('wp_rest'),
                'hasPro'    => Template_Library\Access::has_pro(),
                'hasQuiz'   => Template_Library\Access::has_quiz(),
                'package'   => Template_Library\Access::get_package(),
                'wpVersion' => get_bloginfo('version'),
            ]);
        }
        if ($screen->id == 'metform_page_metform-analytics') {
            $plugin = \MetForm\Plugin::instance();
            $asset_file = $plugin->plugin_dir() . 'build/admin/form-analytics/index.asset.php';
            $is_analytics_enable = !empty($settings['mf_enable_form_analytics']);
            // analytics base class check as a pro activation
            $is_metform_pro_active = class_exists(\MetForm_Pro\Core\Analytics\Base::class);
            $is_mid_tier =  \MetForm\Utils\Util::is_mid_tier();
            $is_top_tier =  \MetForm\Utils\Util::is_top_tier();
            if (file_exists($asset_file)) {
                $asset = include $asset_file;
                Assets_Enqueue::get('metform-form-analytics');

                // Register script translations so strings inside the analytics JS are translated
                wp_set_script_translations( 'metform-form-analytics', 'metform' );


                wp_localize_script('metform-form-analytics', 'metformAnalytics', [
                    'apiUrl' => rest_url('metform-pro/v1/analytics'),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'admin_url' => admin_url(),
                    'is_analytics_enable' => $is_analytics_enable,
                    'is_metform_pro_active' => $is_metform_pro_active,
                    'is_mid_tier' => $is_mid_tier,
                    'is_top_tier' => $is_top_tier,
                ]);
            }
        }
    }

}
