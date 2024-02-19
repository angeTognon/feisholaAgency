<?php
/**
 * Represents the view for the plugin settings page.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user to configure plugin settings.
 *
 * @package IS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exits if accessed directly.
}
?>

<div class="wrap">

	<h1 class="wp-heading-inline">
		<span class="is-search-image"></span>
		<?php esc_html_e( 'Ivory Search Settings', 'add-search-to-menu' ); ?>
	</h1>

	<hr class="wp-header-end">

	<?php do_action( 'is_admin_notices' ); ?>
	<?php settings_errors(); ?>

		<div id="poststuff">
		<div id="search-body" class="metabox-holder columns-2">
			<form id="ivory_search_options" action="options.php" method="post">
			<div id="searchtbox-container-1" class="postbox-container">
			<div id="search-form-editor">
			<?php
				settings_fields( 'ivory_search' );

				$panels = array(
						'settings' => array(
								'settings',
								'Settings',
                                                                'Advanced Website Search Settings',
						),
						'menu-search' => array(
								'menu-search',
								'Menu Search',
                                                                'Configure Menu Search',
						),
						'analytics' => array(
								'analytics',
								'Analytics',
                                                                'Search Analytics',
						),
						'index' => array(
								'index',
								'Index',
															'Search Index',
						),
				);

				$tab = 'settings';
				if ( isset( $_GET['tab'] ) ) {
					switch ( $_GET['tab'] ) {
						case 'menu-search':
							$tab = 'menu-search';
							break;
						case 'analytics':
							$tab = 'analytics';
							break;
						case 'index':
							$tab = 'index';
							break;
						}
				}
				$url = menu_page_url( 'ivory-search-settings', false );
				?>
					<ul id="search-form-editor-tabs">				
				<?php
				foreach ( $panels as $id => $panel ) {
					$class = ( $tab == $id ) ? 'active' : '';
					echo sprintf( '<li id="%1$s-tab" class="%2$s"><a href="%3$s" title="%4$s">%5$s</a></li>',
						esc_attr( $panel[0] ), esc_attr( $class ), esc_url( $url ) . '&tab=' . $panel[0], esc_attr( $panel[2] ), esc_html( $panel[1] ) );
				}
				?>
					</ul>
				<?php

				$settings_fields = IS_Settings_Fields::getInstance();

				if ( 'settings'  == $tab ) {
					$settings_fields->is_do_settings_sections( 'ivory_search', 'ivory_search_settings' );
				} else if ( 'menu-search' ==  $tab ) {
					$settings_fields->is_do_settings_sections( 'ivory_search', 'ivory_search_section' );
				} else if ( 'analytics' ==  $tab ) {
					$settings_fields->is_do_settings_sections( 'ivory_search', 'ivory_search_analytics' );
				} else if ( 'index' ==  $tab ) {
					$settings_fields->is_do_settings_sections( 'ivory_search', 'ivory_search_index' );
				}

			?>
			</div><!-- #search-form-editor -->

			<?php if ( current_user_can( 'is_edit_search_form' ) ) :
				submit_button( 'Save', 'primary', 'ivory_search_options_submit' );
			endif; ?>

			</div><!-- #searchtbox-container-1 -->
			<div id="searchtbox-container-2" class="postbox-container">
				<?php if ( current_user_can( 'is_edit_search_form' ) ) : ?>
				<div id="submitdiv" class="searchbox">
					<div class="inside">
						<div class="submitbox" id="submitpost">
							<div id="major-publishing-actions">
								<div id="publishing-action">
									<span class="spinner"></span>
									<?php submit_button( 'Save', 'primary', 'ivory_search_options_submit', false ); ?>
									<?php if( 'index' == $tab ): ?>
										<?php 
											$action = 'index-reset';
											$confirm_msg = __( "You are about to reset this index settings.\n  'Cancel' to stop, 'OK' to reset.", 'add-search-to-menu' );
											$data = array(
												'action' => esc_html( $action ),
												'_wpnonce' => wp_create_nonce( $action ),
												'confirm_msg' => esc_html( $confirm_msg ),
											);
											$data = esc_attr( json_encode( $data ) );
										?>
										<p>
											<input 
												type="submit" 
												id="is-index-reset" 
												name="is-index-reset" 
												class="reset button" 
												value="<?php echo esc_attr( __( 'Reset', 'add-search-to-menu' ) ); ?>"
												data-is="<?php echo esc_attr( $data ); ?>" 
											/>
										</p>
									<?php endif; ?>
								</div>
								<div class="clear"></div>
							</div><!-- #major-publishing-actions -->
						</div><!-- #submitpost -->
					</div>
				</div><!-- #submitdiv -->
				<?php endif; ?>

				<div id="informationdiv" class="searchbox">
					<div class="inside">
						<ul>
							<li><a href="https://ivorysearch.com/documentation/" target="_blank"><?php _e( 'Documentation', 'add-search-to-menu' ); ?></a></li>
							<li><a href="https://ivorysearch.com/support/" target="_blank"><?php _e( 'Support', 'add-search-to-menu' ); ?></a></li>
							<li><a href="https://ivorysearch.com/contact/" target="_blank"><?php _e( 'Contact Us', 'add-search-to-menu' ); ?></a></li>
							<li><a href="https://wordpress.org/support/plugin/add-search-to-menu/reviews/?filter=5#new-post" target="_blank"><?php _e( 'Give us a rating', 'add-search-to-menu' ); ?></a></li>
						</ul>
					</div>
				</div><!-- #informationdiv -->
			</div><!-- #searchtbox-container-2 -->
			</form>
		</div><!-- #post-body -->
		<br class="clear" />
		</div><!-- #poststuff -->

</div><!-- .wrap -->

<?php do_action( 'is_admin_footer' );