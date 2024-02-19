<?php
/**
 * Inverted Index TablePress Compatibility.
 *
 * @package IS
 * @subpackage IS/includes
 * @since 5.0
 */
class IS_TablePress_Compat {
	public function __construct() {
		add_action( 'init', array( $this, 'compatibility' ) );
	}

	public function compatibility() {
		$index_opt = IS_Index_Options::getInstance();
		if ( class_exists( 'TablePress' ) && $index_opt->expand_shortcodes ) {
			$this->fix_order();
			$this->include_shortcodes();
		}
	}

	/**
	 * Include Table Press shortcode in the admin.
	 *
	 * @since 5.0
	 */
	public function include_shortcodes() {

		if ( ! isset( TablePress::$model_options ) ) {
			include_once TABLEPRESS_ABSPATH . 'classes/class-model.php';
			include_once TABLEPRESS_ABSPATH . 'models/model-options.php';
			TablePress::$model_options = new TablePress_Options_Model();
		}
		$tb_controller = TablePress::load_controller( 'frontend' );
		$tb_controller->init_shortcodes();
	}

	/**
	 * Fix plugins order to allow other plugins to hook earlier.
	 * Ivory search is set to the last one.
	 * 
	 * @since 5.0
	 */
	public function fix_order() {
		$this_plugin = IS_PLUGIN_BASE;
		$active_plugins = get_option( 'active_plugins' );
		$key = array_search( $this_plugin, $active_plugins );

		if( false !== $key && $key != count( $active_plugins ) -1 ) {
			unset( $active_plugins[ $key ] );
			$active_plugins[] = $this_plugin;
			
			$active_plugins = array_values( array_unique( $active_plugins ) );
			
			if( ! empty( $active_plugins ) ) {
				update_option( 'active_plugins', $active_plugins );
			}
		}
	}
}

new IS_TablePress_Compat();