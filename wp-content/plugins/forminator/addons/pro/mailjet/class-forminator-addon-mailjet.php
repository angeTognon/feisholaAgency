<?php
/** @noinspection HtmlUnknownTarget */

require_once dirname( __FILE__ ) . '/class-forminator-addon-mailjet-exception.php';
require_once dirname( __FILE__ ) . '/lib/class-forminator-addon-mailjet-wp-api.php';

/**
 * Class Forminator_Addon_Mailjet
 * The class that defines mailjet addon
 */
class Forminator_Addon_Mailjet extends Forminator_Addon_Abstract {

	/**
	 * Mailjet Addon Instance
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var string
	 */
	protected $_slug = 'mailjet';

	/**
	 * @var string
	 */
	protected $_version = FORMINATOR_ADDON_MAILJET_VERSION;

	/**
	 * @var string
	 */
	protected $_min_forminator_version = '1.28';

	/**
	 * @var string
	 */
	protected $_short_title = 'Mailjet';

	/**
	 * @var string
	 */
	protected $_title = 'Mailjet';

	/**
	 * @var string
	 */
	protected $_url = 'https://wpmudev.com';

	/**
	 * @var string
	 */
	protected $_full_path = __FILE__;

	/**
	 * Class name of form settings
	 *
	 * @var string
	 */
	protected $_form_settings = 'Forminator_Addon_Mailjet_Form_Settings';

	/**
	 * Class name of form hooks
	 *
	 * @var string
	 */
	protected $_form_hooks = 'Forminator_Addon_Mailjet_Form_Hooks';

	/**
	 * Class name of quiz settings
	 *
	 * @var string
	 */
	protected $_quiz_settings = 'Forminator_Addon_Mailjet_Quiz_Settings';

	/**
	 * Class name of quiz hooks
	 *
	 * @var string
	 */
	protected $_quiz_hooks = 'Forminator_Addon_Mailjet_Quiz_Hooks';

	/**
	 * Hold account information that currently connected
	 * Will be saved to @see Forminator_Addon_Mailjet::save_settings_values()
	 *
	 * @var array
	 */
	private $_connected_account = array();

	protected $_position = 3;

	/**
	 * Forminator_Addon_Mailjet constructor.
	 * - Set dynamic translatable text(s) that will be displayed to end-user
	 * - Set dynamic icons and images
	 */
	public function __construct() {
		// late init to allow translation.
		$this->_description    = esc_html__( 'Get awesome by your form.', 'forminator' );
		$this->is_multi_global = true;

		$this->_icon     = forminator_addon_mailjet_assets_url() . 'icon.png';
		$this->_icon_x2  = forminator_addon_mailjet_assets_url() . 'icon@2x.png';
		$this->_image    = forminator_addon_mailjet_assets_url() . 'image.png';
		$this->_image_x2 = forminator_addon_mailjet_assets_url() . 'image@2x.png';

	}

	/**
	 * Get addon instance
	 *
	 * @return self|null
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hook before save settings values
	 * to include @see Forminator_Addon_Mailjet::$_connected_account
	 * for future reference
	 *
	 * @param array $values Values to save.
	 *
	 * @return array
	 */
	public function before_save_settings_values( $values ) {
		forminator_addon_maybe_log( __METHOD__, $values );

		if ( ! empty( $this->_connected_account ) ) {
			$values['connected_account'] = $this->_connected_account;
		}

		return $values;
	}

	/**
	 * Flag for check whether mailjet addon is connected globally
	 *
	 * @return bool
	 */
	public function is_connected() {
		try {
			// check if its active.
			if ( ! $this->is_active() ) {
				throw new Forminator_Addon_Mailjet_Exception( esc_html__( 'Mailjet is not active', 'forminator' ) );
			}

			// if user completed settings.
			$is_connected = $this->settings_is_complete();

		} catch ( Forminator_Addon_Mailjet_Exception $e ) {
			$is_connected = false;
		}

		/**
		 * Filter connected status of mailjet
		 *
		 * @param bool $is_connected
		 */
		$is_connected = apply_filters( 'forminator_addon_mailjet_is_connected', $is_connected );

		return $is_connected;
	}

	/**
	 * Check if user already completed settings
	 *
	 * @return bool
	 */
	private function settings_is_complete() {
		$setting_values = $this->get_settings_values();

		// check api_key and connected_account exists and not empty.
		return ! empty( $setting_values['api_key'] ) && ! empty( $setting_values['secret_key'] ) && ! empty( $setting_values['connected_account'] );
	}

	/**
	 * Flag for check if and addon connected to a form
	 * by default it will check if last step of form settings already completed by user
	 *
	 * @param $form_id
	 *
	 * @return bool
	 */
	public function is_form_connected( $form_id ) {

		try {
			// initialize with null.
			$form_settings_instance = null;
			if ( ! $this->is_connected() ) {
				throw new Forminator_Addon_Mailjet_Exception( esc_html__( 'Mailjet addon not connected.', 'forminator' ) );
			}

			$form_settings_instance = $this->get_addon_settings( $form_id, 'form' );
			if ( ! $form_settings_instance instanceof Forminator_Addon_Mailjet_Form_Settings ) {
				throw new Forminator_Addon_Mailjet_Exception( esc_html__( 'Form settings instance is not valid Forminator_Addon_Mailjet_Form_Settings.', 'forminator' ) );
			}
			$wizards = $form_settings_instance->form_settings_wizards();
			//last step is completed
			$last_step             = end( $wizards );
			$last_step_is_complete = call_user_func( $last_step['is_completed'] );
			if ( ! $last_step_is_complete ) {
				throw new Forminator_Addon_Mailjet_Exception( esc_html__( 'Form settings is not yet completed.', 'forminator' ) );
			}

			$is_form_connected = true;
		} catch ( Forminator_Addon_Mailjet_Exception $e ) {
			$is_form_connected = false;

			forminator_addon_maybe_log( __METHOD__, $e->getMessage() );
		}

		/**
		 * Filter connected status of mailjet with the form
		 *
		 * @param bool                                          $is_form_connected
		 * @param int                                           $form_id                Current Form ID.
		 * @param Forminator_Addon_Mailjet_Form_Settings|null $form_settings_instance Instance of form settings, or null when unavailable.
		 *
		 */
		$is_form_connected = apply_filters( 'forminator_addon_mailjet_is_form_connected', $is_form_connected, $form_id, $form_settings_instance );

		return $is_form_connected;

	}

	/**
	 * Flag for check if and addon connected to a form
	 * by default it will check if last step of form settings already completed by user
	 *
	 * @param $quiz_id
	 *
	 * @return bool
	 */
	public function is_quiz_connected( $quiz_id ) {

		try {
			// initialize with null.
			$quiz_settings_instance = null;
			if ( ! $this->is_connected() ) {
				throw new Forminator_Addon_Mailjet_Exception( esc_html__( 'Mailjet addon not connected.', 'forminator' ) );
			}

			$quiz_settings_instance = $this->get_addon_settings( $quiz_id, 'quiz' );
			if ( ! $quiz_settings_instance instanceof Forminator_Addon_Mailjet_Quiz_Settings ) {
				throw new Forminator_Addon_Mailjet_Exception( esc_html__( 'Form settings instance is not valid Forminator_Addon_Mailjet_Quiz_Settings.', 'forminator' ) );
			}
			$wizards = $quiz_settings_instance->quiz_settings_wizards();
			//last step is completed
			$last_step             = end( $wizards );
			$last_step_is_complete = call_user_func( $last_step['is_completed'] );
			if ( ! $last_step_is_complete ) {
				throw new Forminator_Addon_Mailjet_Exception( esc_html__( 'Form settings is not yet completed.', 'forminator' ) );
			}

			$is_quiz_connected = true;
		} catch ( Forminator_Addon_Mailjet_Exception $e ) {
			$is_quiz_connected = false;

			forminator_addon_maybe_log( __METHOD__, $e->getMessage() );
		}

		/**
		 * Filter connected status of mailjet with the form
		 *
		 * @param bool                                          $is_quiz_connected
		 * @param int                                           $quiz_id                Current Form ID.
		 * @param Forminator_Addon_Mailjet_Quiz_Settings|null $quiz_settings_instance Instance of form settings, or null when unavailable.
		 *
		 */
		$is_quiz_connected = apply_filters( 'forminator_addon_mailjet_is_form_connected', $is_quiz_connected, $quiz_id, $quiz_settings_instance );

		return $is_quiz_connected;

	}

	/**
	 * Return with true / false, you may update you setting update message too
	 *
	 * @param string $api_key API key.
	 * @param string $secret_key Secret key.
	 *
	 * @return bool
	 */
	protected function validate_api_keys( $api_key, $secret_key ) {
		try {
			// Check API Key and Secret key.
			$info = $this->get_api( $api_key, $secret_key )->get_info();
			forminator_addon_maybe_log( __METHOD__, $info );

			$this->_connected_account = array(
				'account_name' => $info->data[0]->username ?? '',
				'email'        => $info->data[0]->email ?? '',
			);

		} catch ( Forminator_Addon_Mailjet_Wp_Api_Exception $e ) {
			$this->_update_settings_error_message = $e->getMessage();
			return false;
		}

		return true;
	}

	/**
	 * Get API Instance
	 *
	 * @param null $api_key
	 *
	 * @return Forminator_Addon_Mailjet_Wp_Api|null
	 */
	public function get_api( $api_key = null, $secret_key = null ) {
		if ( is_null( $api_key ) ) {
			$api_key = $this->get_api_key();
		}
		if ( is_null( $secret_key ) ) {
			$secret_key = $this->get_secret_key();
		}
		$api = Forminator_Addon_Mailjet_Wp_Api::get_instance( $api_key, $secret_key );
		return $api;
	}

	/**
	 * Get currently saved api key
	 *
	 * @return string|null
	 */
	private function get_api_key() {
		$setting_values = $this->get_settings_values();
		if ( isset( $setting_values['api_key'] ) ) {
			return $setting_values['api_key'];
		}

		return null;
	}

	/**
	 * Get currently saved secret key
	 *
	 * @return string|null
	 */
	private function get_secret_key() {
		$setting_values = $this->get_settings_values();
		if ( isset( $setting_values['secret_key'] ) ) {
			return $setting_values['secret_key'];
		}

		return null;
	}

	/**
	 * Build settings help on settings
	 *
	 * @return string
	 */
	public function settings_help() {

		// Display how to get mailjet API Key by default.
		/* Translators: 1. Opening <a> tag with link to the Mailjet API Key, 2. closing <a> tag. */
		$help = sprintf( esc_html__( 'Please get your Mailjet API keys %1$shere%2$s', 'forminator' ), '<a href="https://app.mailjet.com/account/apikeys" target="_blank">', '</a>' );

		$help = '<span class="sui-description" style="margin-top: 20px;">' . $help . '</span>';

		$setting_values = $this->get_settings_values();

		if (
			! empty( $setting_values['api_key'] )
			&& ! empty( $setting_values['secret_key'] )
			&& ! empty( $setting_values['connected_account'] )
		) {
			// Show currently connected mailjet account if its already connected.
			$help = '<span class="sui-description" style="margin-top: 20px;">' . esc_html__( 'Change your API Key and Secret Key or disconnect this Mailjet Integration below.', 'forminator' ) . '</span>';
		}

		return $help;

	}

	/**
	 * Settings description
	 *
	 * @return string
	 */
	public function settings_description() {
		$description    = '';
		$setting_values = $this->get_settings_values();

		if (
			! empty( $setting_values['api_key'] )
			&& ! empty( $setting_values['secret_key'] )
			&& ! empty( $setting_values['connected_account'] )
		) {
			// Show currently connected mailjet account if its already connected.
			$description .= '<span class="sui-description">' . esc_html__( 'Please note that changing your API Key and Secret Key or disconnecting this integration will affect ALL of your connected forms.', 'forminator' ) . '</span>';
		}

		return $description;
	}

	/**
	 * Connected account info
	 *
	 * @return string
	 */
	public function settings_account() {
		$myaccount = '';
		$settings  = $this->get_settings_values();

		if ( ! empty( $settings['api_key'] ) && ! empty( $settings['secret_key'] ) && ! empty( $settings['connected_account'] ) ) {

			$connected_account = $settings['connected_account'];

			// Show currently connected mailjet account if its already connected.
			$myaccount .= sprintf(
				/* translators:  placeholder is Name and Email of Connected MailJet Account */
				esc_html__( 'Your Mailjet is connected to %1$s: %2$s.', 'forminator' ),
				'<strong>' . esc_html( $connected_account['account_name'] ) . '</strong>',
				sanitize_email( $connected_account['email'] )
			);

			$myaccount = '<div role="alert" class="sui-notice sui-notice-red sui-active" style="display: block; text-align: left;" aria-live="assertive">
				<div class="sui-notice-content">
					<div class="sui-notice-message">
						<span class="sui-notice-icon sui-icon-info" aria-hidden="true"></span>
						<p>' . $myaccount . '</p>
					</div>
				</div>
			</div>';

		}

		return $myaccount;

	}

	/**
	 * Flag to show full log on entries
	 * By default API request(s) are not shown on submissions page
	 * set @see FORMINATOR_ADDON_MAILJET_SHOW_FULL_LOG to `true` on wp-config.php to show it
	 *
	 * @return bool
	 */
	public static function is_show_full_log() {
		if ( defined( 'FORMINATOR_ADDON_MAILJET_SHOW_FULL_LOG' ) && FORMINATOR_ADDON_MAILJET_SHOW_FULL_LOG ) {
			return true;
		}

		return false;
	}

	/**
	 * Settings wizard
	 *
	 * @return array
	 */
	public function settings_wizards() {
		return array(
			array(
				'callback'     => array( $this, 'configure_api_key' ),
				'is_completed' => array( $this, 'settings_is_complete' ),
			),
		);
	}

	/**
	 * Wizard of configure_api_key
	 *
	 * @param     $submitted_data
	 * @param int $form_id
	 *
	 * @return array
	 */
	public function configure_api_key( $submitted_data, $form_id = 0 ) {
		$error_message         = '';
		$api_key_error_message = '';
		$secret_key_error      = '';
		$setting_values        = $this->get_settings_values();
		$identifier            = $setting_values['identifier'] ?? '';
		$api_key               = $this->get_api_key();
		$secret_key            = $this->get_secret_key();
		$show_success          = false;

		// ON Submit.
		if ( isset( $submitted_data['api_key'] ) ) {
			$api_key    = $submitted_data['api_key'];
			$secret_key = $submitted_data['secret_key'] ?? '';
			$identifier = $submitted_data['identifier'] ?? '';

			if ( empty( $api_key ) ) {
				$api_key_error_message = esc_html__( 'Please add valid Mailjet API Key.', 'forminator' );
			} elseif ( empty( $secret_key ) ) {
				$secret_key_error = esc_html__( 'Please add valid Mailjet Secret Key.', 'forminator' );
			} else {
				$api_key_validated = $this->validate_api_keys( $api_key, $secret_key );

				/**
				 * Filter validating api key result
				 *
				 * @param bool   $api_key_validated
				 * @param string $api_key API Key to be validated.
				 */
				$api_key_validated = apply_filters( 'forminator_addon_mailjet_validate_api_keys', $api_key_validated, $api_key, $secret_key );

				if ( ! $api_key_validated ) {
					$error_message = $this->_update_settings_error_message;
				} else {
					$save_values = array(
						'api_key'    => $api_key,
						'secret_key' => $secret_key,
						'identifier' => $identifier,
					);

					if ( ! forminator_addon_is_active( $this->_slug ) ) {
						$activated = Forminator_Addon_Loader::get_instance()->activate_addon( $this->_slug );
						if ( ! $activated ) {
							$error_message = Forminator_Addon_Loader::get_instance()->get_last_error_message();
						} else {
							$this->save_settings_values( $save_values );
							$show_success = true;
						}
					} else {
						$this->save_settings_values( $save_values );
						$show_success = true;
					}
				}
			}

			if ( $show_success ) {
				if ( ! empty( $form_id ) ) {
					// initiate form settings wizard.
					return $this->get_form_settings_wizard( array(), $form_id, 0, 0 );
				}

				$html = '<div class="forminator-integration-popup__header">';
				/* translators: ... */
				$html .= '<h3 id="dialogTitle2" class="sui-box-title sui-lg" style="overflow: initial; text-overflow: none; white-space: normal;">' . /* translators: 1: Add-on name */ sprintf( esc_html__( '%1$s Added', 'forminator' ), 'Mailjet' ) . '</h3>';
				$html .= '</div>';
				$html .= '<p class="sui-description" style="text-align: center;">' . esc_html__( 'You can now go to your forms and assign them to this integration.', 'forminator' ) . '</p>';

				return array(
					'html'         => $html,
					'buttons'      => array(
						'close' => array(
							'markup' => self::get_button_markup( esc_html__( 'Close', 'forminator' ), 'forminator-addon-close forminator-integration-popup__close' ),
						),
					),
					'redirect'     => false,
					'has_errors'   => false,
					'notification' => array(
						'type' => 'success',
						'text' => '<strong>' . $this->get_title() . '</strong> ' . esc_html__( 'is connected successfully.', 'forminator' ),
					),
				);
			}
		}

		$buttons = array();

		if ( $this->is_connected() ) {
			$buttons['disconnect'] = array(
				'markup' => self::get_button_markup( esc_html__( 'Disconnect', 'forminator' ), 'sui-button-ghost forminator-addon-disconnect' ),
			);

			$buttons['submit'] = array(
				'markup' => '<div class="sui-actions-right">' .
							self::get_button_markup( esc_html__( 'Save', 'forminator' ), 'forminator-addon-connect' ) .
							'</div>',
			);
		} else {
			$buttons['submit'] = array(
				'markup' => self::get_button_markup( esc_html__( 'Connect', 'forminator' ), 'forminator-addon-connect' ),
			);
		}

		$html = '<div class="forminator-integration-popup__header">';
		/* translators: ... */
		$html .= '<h3 id="dialogTitle2" class="sui-box-title sui-lg" style="overflow: initial; text-overflow: none; white-space: normal;">' . /* translators: 1: Add-on name */ sprintf( esc_html__( 'Configure %1$s', 'forminator' ), 'Mailjet' ) . '</h3>';
		$html .= $this->settings_help();
		$html .= $error_message ? '<div class="sui-notice sui-notice-error"><div class="sui-notice-content"><div class="sui-notice-message">
										<span class="sui-notice-icon sui-icon-info" aria-hidden="true" ></span>
										<p>' . $error_message . '</p>
									</div></div></div>' : '';
		$html .= '</div>';
		$html .= '<form>';
		// FIELD: API Key.
		$html .= '<div class="sui-form-field ' . ( ! empty( $api_key_error_message ) ? 'sui-form-field-error' : '' ) . '">';
		$html .= '<label class="sui-label">' . esc_html__( 'API Key', 'forminator' ) . '</label>';
		$html .= '<div class="sui-control-with-icon">';
		/* translators: ... */
		$html .= '<input name="api_key" value="' . esc_attr( $api_key ) . '" placeholder="' . /* translators: 1: Add-on name */ sprintf( esc_html__( 'Enter %1$s API Key', 'forminator' ), 'Mailjet' ) . '" class="sui-form-control" />';
		$html .= '<i class="sui-icon-key" aria-hidden="true"></i>';
		$html .= '</div>';
		$html .= ( ! empty( $api_key_error_message ) ? '<span class="sui-error-message">' . esc_html( $api_key_error_message ) . '</span>' : '' );
		$html .= $this->settings_description();
		$html .= '</div>';
		// FIELD: API Secret.
		$html .= '<div class="sui-form-field ' . ( ! empty( $secret_key_error ) ? 'sui-form-field-error' : '' ) . '">';
		$html .= '<label class="sui-label">' . esc_html__( 'API Secret', 'forminator' ) . '</label>';
		$html .= '<div class="sui-control-with-icon">';
		/* translators: ... */
		$html .= '<input name="secret_key" value="' . esc_attr( $secret_key ) . '" placeholder="' . /* translators: 1: Add-on name */ sprintf( esc_html__( 'Enter %1$s API Secret', 'forminator' ), 'Mailjet' ) . '" class="sui-form-control" />';
		$html .= '<i class="sui-icon-lock" aria-hidden="true"></i>';
		$html .= '</div>';
		$html .= ( ! empty( $secret_key_error ) ? '<span class="sui-error-message">' . esc_html( $secret_key_error ) . '</span>' : '' );
		$html .= $this->settings_description();
		$html .= '</div>';
		// FIELD: Identifier.
		$html .= '<div class="sui-form-field">';
		$html .= '<label class="sui-label">' . esc_html__( 'Identifier', 'forminator' ) . '</label>';
		$html .= '<input name="identifier" value="' . esc_attr( $identifier ) . '" placeholder="' . esc_attr__( 'E.g., Business Account', 'forminator' ) . '" class="sui-form-control" />';
		$html .= '<span class="sui-description">' . esc_html__( 'Helps distinguish between integrations if connecting to the same third-party app with multiple accounts.', 'forminator' ) . '</span>';
		$html .= '</div>';
		$html .= '</form>';
		$html .= $this->settings_account();

		return array(
			'html'       => $html,
			'buttons'    => $buttons,
			'redirect'   => false,
			'has_errors' => ! empty( $error_message ) || ! empty( $api_key_error_message ) || ! empty( $secret_key_error ),
		);
	}

	/**
	 * Flag for check if and addon connected to a poll(poll settings such as list id completed)
	 *
	 * Please apply necessary WordPress hook on the inheritance class
	 *
	 * @param $poll_id
	 *
	 * @return boolean
	 */
	public function is_poll_connected( $poll_id ) {
		return false;
	}

	/**
	 * Flag for check if has lead form addon connected to a quiz
	 * by default it will check if last step of form settings already completed by user
	 *
	 * @param $quiz_id
	 *
	 * @return bool
	 */
	public function is_quiz_lead_connected( $quiz_id ) {

		try {
			// initialize with null.
			$quiz_settings_instance = null;
			if ( ! $this->is_connected() ) {
				throw new Forminator_Addon_Mailjet_Exception( esc_html__( 'Mailjet addon not connected.', 'forminator' ) );
			}
			$quiz_settings_instance = $this->get_addon_settings( $quiz_id, 'quiz' );

			if ( ! $quiz_settings_instance instanceof Forminator_Addon_Mailjet_Quiz_Settings ) {
				throw new Forminator_Addon_Mailjet_Exception( esc_html__( 'Form settings instance is not valid Forminator_Addon_Mailjet_Quiz_Settings.', 'forminator' ) );
			}

			$quiz_settings = $quiz_settings_instance->get_quiz_settings();

			if ( isset( $quiz_settings['hasLeads'] ) && $quiz_settings['hasLeads'] ) {
				$is_quiz_connected = true;
			} else {
				$is_quiz_connected = false;
			}
		} catch ( Forminator_Addon_Mailjet_Exception $e ) {
			$is_quiz_connected = false;

			forminator_addon_maybe_log( __METHOD__, $e->getMessage() );
		}

		/**
		 * Filter connected status of mailjet with the form
		 *
		 * @param bool                                          $is_quiz_connected
		 * @param int                                           $quiz_id                Current Form ID.
		 * @param Forminator_Addon_Mailjet_Quiz_Settings|null $quiz_settings_instance Instance of form settings, or null when unavailable.
		 *
		 */
		$is_quiz_connected = apply_filters( 'forminator_addon_mailjet_is_quiz_lead_connected', $is_quiz_connected, $quiz_id, $quiz_settings_instance );

		return $is_quiz_connected;

	}
}
