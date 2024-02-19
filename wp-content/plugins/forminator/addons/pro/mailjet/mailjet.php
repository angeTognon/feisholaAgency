<?php
/**
 * Addon Name: Mailjet
 * Version: 1.0
 * Plugin URI:  https://wpmudev.com/
 * Description: Integrate Forminator Modules with Mailjet email list easily
 * Author: WPMU DEV
 * Author URI: http://wpmudev.com
 */

define( 'FORMINATOR_ADDON_MAILJET_VERSION', '1.0' );

/**
 * Mailjet Assets URL
 *
 * @return string
 */
function forminator_addon_mailjet_assets_url() {
	return trailingslashit( forminator_plugin_url() . 'addons/pro/mailjet/assets' );
}

require_once dirname( __FILE__ ) . '/class-forminator-addon-mailjet.php';
require_once dirname( __FILE__ ) . '/class-forminator-addon-mailjet-form-settings.php';
require_once dirname( __FILE__ ) . '/class-forminator-addon-mailjet-form-hooks.php';

require_once dirname( __FILE__ ) . '/class-forminator-addon-mailjet-quiz-settings.php';
require_once dirname( __FILE__ ) . '/class-forminator-addon-mailjet-quiz-hooks.php';

Forminator_Addon_Loader::get_instance()->register( 'Forminator_Addon_Mailjet' );
