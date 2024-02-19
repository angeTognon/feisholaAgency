<?php

/**
 * Class Forminator_Addon_Mailjet_Form_Hooks
 *
 * Hooks that used by Mailjet Addon defined here
 */
class Forminator_Addon_Mailjet_Form_Hooks extends Forminator_Addon_Form_Hooks_Abstract {

	/**
	 * Addon instance
	 *
	 * @var Forminator_Addon_Mailjet
	 */
	protected $addon;

	/**
	 * Form settings instance
	 *
	 * @var Forminator_Addon_Mailjet_Form_Settings | null
	 *
	 */
	protected $form_settings_instance;

	/**
	 * Forminator_Addon_Mailjet_Form_Hooks constructor.
	 *
	 * @param Forminator_Addon_Abstract $addon
	 * @param                           $form_id
	 *
	 * @throws Forminator_Addon_Exception
	 */
	public function __construct( Forminator_Addon_Abstract $addon, $form_id ) {
		parent::__construct( $addon, $form_id );
		$this->_submit_form_error_message = esc_html__( 'Mailjet failed to process submitted data. Please check your form and try again', 'forminator' );
	}

	/**
	 * Render extra fields after all forms fields rendered
	 */
	public function on_after_render_form_fields() {
		$form_id                = $this->form_id;
		$form_settings_instance = $this->form_settings_instance;

		/**
		 * Fires when mailjet rendering extra output after connected form fields rendered
		 *
		 * @param int                                      $form_id                current Form ID.
		 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet Form settings Instance.
		 */
		do_action(
			'forminator_addon_mailjet_on_after_render_form_fields',
			$form_id,
			$form_settings_instance
		);
	}

	/**
	 * Check GDPR field - Experimental
	 *
	 * @param $submitted_data
	 *
	 * @return bool
	 */
	public function on_form_submit( $submitted_data ) {
		$is_success = true;

		$form_id                = $this->form_id;
		$form_settings_instance = $this->form_settings_instance;

		/**
		 * Filter mailjet submitted form data to be processed
		 *
		 * @param array                                    $submitted_data
		 * @param int                                      $form_id                current Form ID.
		 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet API Form Settings instance.
		 */
		$submitted_data = apply_filters(
			'forminator_addon_mailjet_form_submitted_data',
			$submitted_data,
			$form_id,
			$form_settings_instance
		);

		/**
		 * Fires when mailjet connected form submit data
		 *
		 * Return `true` if success, or **(string) error message** on fail
		 *
		 * @param bool                                     $is_success
		 * @param int                                      $form_id                current Form ID.
		 * @param array                                    $submitted_data
		 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet API Form Settings instance.
		 */
		$is_success = apply_filters(
			'forminator_addon_mailjet_on_form_submit_result',
			$is_success,
			$form_id,
			$submitted_data,
			$form_settings_instance
		);

		// process filter.
		if ( true !== $is_success ) {
			// only update `_submit_form_error_message` when not empty.
			if ( ! empty( $is_success ) ) {
				$this->_submit_form_error_message = (string) $is_success;
			}

			return $is_success;
		}

		return true;
	}

	/**
	 * Check submitted_data met requirement to sent to mailjet
	 * Send if possible, add result to entry fields
	 *
	 * @param array $submitted_data
	 * @param array $form_entry_fields
	 *
	 * @return array
	 */
	public function add_entry_fields( $submitted_data, $form_entry_fields = array() ) {
		$form_id                = $this->form_id;
		$form_settings_instance = $this->form_settings_instance;

		/**
		 * Filter mailjet submitted form data to be processed
		 *
		 * @param array                                    $submitted_data
		 * @param int                                      $form_id                current Form ID.
		 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet API Form Settings instance.
		 */
		$submitted_data = apply_filters(
			'forminator_addon_mailjet_form_submitted_data',
			$submitted_data,
			$form_id,
			$form_settings_instance
		);

        // Check if there is a date field-type then modify it to a format that mailjet accepts.
        foreach ( $submitted_data as $field => $value ) {
			// Also Check the date field doesn't include the '-year', '-month' or '-day'.
			if (
				false !== stripos( $field, 'date-' ) &&
				false === stripos( $field, '-year' ) &&
				false === stripos( $field, '-month' ) &&
				false === stripos( $field, '-day' ) &&
				! empty( $value )
				)
			{
				$date_format            = Forminator_API::get_form_field( $form_id, $field, false )->date_format;
				$normalized_format      = new Forminator_Date();
				$normalized_format      = $normalized_format->normalize_date_format( $date_format );
				$mailjet_format         = DateTime::createFromFormat( $normalized_format, $value);
				$mailjet_formatted      = $mailjet_format->format( 'Y-m-d' );
				$submitted_data[$field] = $mailjet_formatted;
            }
        }

		forminator_addon_maybe_log( __METHOD__, $submitted_data );

		$addon_setting_values = $form_settings_instance->get_form_settings_values();
		// initialize as null.
		$mailjet_api = null;

		$settings_values = $this->addon->get_settings_values();
		$identifier      = isset( $settings_values['identifier'] )
				? $settings_values['identifier'] : '';
		$entry_name      = 'status';
		if ( ! empty( $this->addon->multi_global_id ) ) {
			$entry_name .= "-{$this->addon->multi_global_id}";
		}

		// check required fields.
		try {
			$mailjet_api = $this->addon->get_api();

			// email : super required**.
			if ( ! isset( $addon_setting_values['fields_map']['email'] ) ) {
				throw new Forminator_Addon_Mailjet_Exception(
				/* translators: 1: email */
					sprintf( esc_html__( 'Required Field %1$s not mapped yet to Forminator Form Field, Please check your Mailjet Configuration on Form Settings', 'forminator' ), 'email' )
				);
			}

			if ( empty( $submitted_data[ $addon_setting_values['fields_map']['email'] ] ) ) {
				throw new Forminator_Addon_Mailjet_Exception(
				/* translators: 1: Email */
					sprintf( esc_html__( 'Required Field %1$s is not filled by user', 'forminator' ), 'email' )
				);
			}

			$mailjet_fields_list = $this->addon->get_api()->get_contact_properties();
			forminator_addon_maybe_log( __METHOD__, $mailjet_fields_list );

			$email = strtolower( trim( $submitted_data[ $addon_setting_values['fields_map']['email'] ] ) );
			$name  = $submitted_data[ $addon_setting_values['fields_map']['name'] ] ?? '';

			$merge_fields = array();
			foreach ( $mailjet_fields_list as $item ) {
				// its mapped ?
				if ( ! empty( $addon_setting_values['fields_map'][ $item->id ] ) ) {
					$element_id = $addon_setting_values['fields_map'][ $item->id ];
					if ( ! isset( $submitted_data[ $element_id ] ) ) {
						continue;
					}
					$value = $submitted_data[ $element_id ];
					if ( 'datetime' === $item->datatype ) {
						$time = strtotime( $submitted_data[ $element_id ] );
						if ( $time ) {
							$value = gmdate( 'U', $time );
						} else {
							$value = '';
						}
					} elseif ( 'int' === $item->datatype ) {
						$value = (int) $value;
					} elseif ( 'float' === $item->datatype ) {
						$value = (float) $value;
					} elseif ( 'bool' === $item->datatype ) {
						$value = (bool) $value;
					} else {
						$value = (string) $value;
					}
					$merge_fields[ $item->name ] = $value;
				}
			}

			forminator_addon_maybe_log( __METHOD__, $mailjet_fields_list, $addon_setting_values, $submitted_data, $merge_fields );

			$args = array();
			if ( ! empty( $merge_fields ) ) {
				$args['merge_fields'] = $merge_fields;
			}

			$mail_list_id = $addon_setting_values['mail_list_id'];

			/**
			 * Filter mail list id to send to Mailjet API
			 *
			 * Change $mail_list_id that will be send to Mailjet API,
			 * Any validation required by the mail list should be done.
			 * Else if it's rejected by Mailjet API, It will only add Request to Log.
			 * Log can be viewed on Entries Page
			 *
			 * @param string                                   $mail_list_id
			 * @param int                                      $form_id                current Form ID.
			 * @param array                                    $submitted_data         Submitted data.
			 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet Form Settings.
			 */
			$mail_list_id = apply_filters(
				'forminator_addon_mailjet_add_update_member_request_mail_list_id',
				$mail_list_id,
				$form_id,
				$submitted_data,
				$form_settings_instance
			);

			/**
			 * Filter Mailjet API request arguments
			 *
			 * Request Arguments will be added to request body.
			 * Default args that will be send contains these keys:
			 * - status
			 * - status_if_new
			 * - merge_fields
			 * - email_address
			 * - interests
			 *
			 * @param array                                    $args
			 * @param int                                      $form_id                current Form ID.
			 * @param array                                    $submitted_data         Submitted data.
			 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet Form Settings.
			 */
			$args = apply_filters(
				'forminator_addon_mailjet_add_update_member_request_args',
				$args,
				$form_id,
				$submitted_data,
				$form_settings_instance
			);

			/**
			 * Fires before Addon send request `add_or_update_member` to Mailjet API
			 *
			 * If this action throw an error,
			 * then `add_or_update_member` process will be cancelled
			 *
			 * @param int                                      $form_id                current Form ID.
			 * @param array                                    $submitted_data         Submitted data.
			 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet Form Settings.
			 */
			do_action( 'forminator_addon_mailjet_before_add_update_member', $form_id, $submitted_data, $form_settings_instance );

			$add_member_request = $mailjet_api->add_or_update_member( $mail_list_id, $email, $name, $args );
			if ( empty( $add_member_request->total ) ) {
				throw new Forminator_Addon_Mailjet_Exception(
					esc_html__( 'Failed adding or updating member on Mailjet list', 'forminator' )
				);
			}

			forminator_addon_maybe_log( __METHOD__, 'Success Add Member' );

			$entry_fields = array(
				array(
					'value' => array(
						'is_sent'       => true,
						'description'   => esc_html__( 'Successfully added or updated member on Mailjet list', 'forminator' ),
						'data_sent'     => $mailjet_api->get_last_data_sent(),
						'data_received' => $mailjet_api->get_last_data_received(),
						'url_request'   => $mailjet_api->get_last_url_request(),
					),
				),
			);

		} catch ( Forminator_Addon_Mailjet_Exception $e ) {
			forminator_addon_maybe_log( __METHOD__, 'Failed to Add Member' );

			$entry_fields = array(
				array(
					'value' => array(
						'is_sent'       => false,
						'description'   => $e->getMessage(),
						'data_sent'     => ( ( $mailjet_api instanceof Forminator_Addon_Mailjet_Wp_Api ) ? $mailjet_api->get_last_data_sent() : array() ),
						'data_received' => ( ( $mailjet_api instanceof Forminator_Addon_Mailjet_Wp_Api ) ? $mailjet_api->get_last_data_received() : array() ),
						'url_request'   => ( ( $mailjet_api instanceof Forminator_Addon_Mailjet_Wp_Api ) ? $mailjet_api->get_last_url_request() : '' ),
					),
				),
			);
		}

		$entry_fields[0]['name']                     = $entry_name;
		$entry_fields[0]['value']['connection_name'] = $identifier;

		/**
		 * Filter mailjet entry fields to be saved to entry model
		 *
		 * @param array                                    $entry_fields
		 * @param int                                      $form_id                current Form ID.
		 * @param array                                    $submitted_data
		 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet API Form Settings instance.
		 */
		$entry_fields = apply_filters(
			'forminator_addon_mailjet_entry_fields',
			$entry_fields,
			$form_id,
			$submitted_data,
			$form_settings_instance
		);

		return $entry_fields;
	}

	/**
	 * Add new row of Mailjet Integration on render entry
	 * subentries that included are:
	 * - Sent To Mailjet : whether Yes/No, addon send data to Mailjet API
	 * - Info : Additional info when addon tried to send data to Mailjet API
	 * - Member Status : Member status that received from Mailjet API after sending request
	 * - Below subentries will be added if full log enabled, @see Forminator_Addon_Mailjet::is_show_full_log() @see FORMINATOR_ADDON_MAILCHIMP_SHOW_FULL_LOG
	 *      - API URL : URL that wes requested when sending data to Mailjet
	 *      - Data sent to Mailjet : json encoded body request that was sent
	 *      - Data received from Mailjet : json encoded body response that was received
	 *
	 * @param Forminator_Form_Entry_Model $entry_model
	 * @param                             $addon_meta_datas
	 *
	 * @return array
	 */
	public function on_render_entry( Forminator_Form_Entry_Model $entry_model, $addon_meta_datas ) {

		$form_id                = $this->form_id;
		$form_settings_instance = $this->form_settings_instance;

		/**
		 *
		 * Filter mailjet metadata that previously saved on db to be processed
		 *
		 * @param array                                    $addon_meta_data
		 * @param int                                      $form_id                current Form ID.
		 * @param Forminator_Form_Entry_Model              $entry_model            Forminator Entry Model.
		 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet API Form Settings instance.
		 */
		$addon_meta_datas = apply_filters(
			'forminator_addon_mailjet_metadata',
			$addon_meta_datas,
			$form_id,
			$entry_model,
			$form_settings_instance
		);

		$entry_items = array();
		foreach ( $addon_meta_datas as $addon_meta_data ) {
			$entry_items[] = $this->format_metadata_for_entry( $entry_model, $addon_meta_data );
		}

		/**
		 * Filter mailjet row(s) to be displayed on entries page
		 *
		 * @param array                                    $entry_items            row(s) to be displayed on entries page.
		 * @param int                                      $form_id                current Form ID.
		 * @param Forminator_Form_Entry_Model              $entry_model            Form Entry Model.
		 * @param array                                    $addon_meta_data        meta data saved by addon on entry fields.
		 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet API Form Settings instance.
		 */
		$entry_items = apply_filters(
			'forminator_addon_mailjet_entry_items',
			$entry_items,
			$form_id,
			$entry_model,
			$addon_meta_datas,
			$form_settings_instance
		);

		return $entry_items;
	}

	/**
	 * Format metadata saved before to be rendered on entry
	 *
	 * @param Forminator_Form_Entry_Model $entry_model
	 * @param                             $addon_meta_data
	 *
	 * @return array
	 */
	private function format_metadata_for_entry( Forminator_Form_Entry_Model $entry_model, $addon_meta_data ) {
		// make sure its `status`, because we only add this.
		if ( 0 !== strpos( $addon_meta_data['name'], 'status' ) ) {
			return array();
		}

		if ( ! isset( $addon_meta_data['value'] ) || ! is_array( $addon_meta_data['value'] ) ) {
			return array();
		}

		$additional_entry_item = array(
			'label' => esc_html__( 'Mailjet Integration', 'forminator' ),
			'value' => '',
		);

		$status      = $addon_meta_data['value'];
		$sub_entries = array();
		if ( isset( $status['connection_name'] ) ) {
			$sub_entries[] = array(
				'label' => esc_html__( 'Integration Name', 'forminator' ),
				'value' => $status['connection_name'],
			);
		}
		if ( isset( $status['is_sent'] ) ) {
			$is_sent       = true === $status['is_sent'] ? esc_html__( 'Yes', 'forminator' ) : esc_html__( 'No', 'forminator' );
			$sub_entries[] = array(
				'label' => esc_html__( 'Sent To Mailjet', 'forminator' ),
				'value' => $is_sent,
			);
		}

		if ( isset( $status['description'] ) ) {
			$sub_entries[] = array(
				'label' => esc_html__( 'Info', 'forminator' ),
				'value' => $status['description'],
			);
		}

		if ( isset( $status['data_received'] ) && is_object( $status['data_received'] ) ) {
			$data_received = $status['data_received'];
			if ( isset( $data_received->status ) && ! empty( $data_received->status ) && is_string( $data_received->status ) ) {
				$sub_entries[] = array(
					'label' => esc_html__( 'Member Status', 'forminator' ),
					'value' => strtoupper( $data_received->status ),
				);
			}
		}

		if ( Forminator_Addon_Mailjet::is_show_full_log() ) {
			// too long to be added on entry data enable this with `define('FORMINATOR_ADDON_MAILCHIMP_SHOW_FULL_LOG', true)`.
			if ( isset( $status['url_request'] ) ) {
				$sub_entries[] = array(
					'label' => esc_html__( 'API URL', 'forminator' ),
					'value' => $status['url_request'],
				);
			}

			if ( isset( $status['data_sent'] ) ) {
				$sub_entries[] = array(
					'label' => esc_html__( 'Data sent to Mailjet', 'forminator' ),
					'value' => '<pre class="sui-code-snippet">' . wp_json_encode( $status['data_sent'], JSON_PRETTY_PRINT ) . '</pre>',
				);
			}

			if ( isset( $status['data_received'] ) ) {
				$sub_entries[] = array(
					'label' => esc_html__( 'Data received from Mailjet', 'forminator' ),
					'value' => '<pre class="sui-code-snippet">' . wp_json_encode( $status['data_received'], JSON_PRETTY_PRINT ) . '</pre>',
				);
			}
		}

		$additional_entry_item['sub_entries'] = $sub_entries;

		return $additional_entry_item;
	}

	/**
	 * Add new Column called `Mailjet Info` on header of export file
	 *
	 * @return array
	 */
	public function on_export_render_title_row() {
		$export_headers = array(
			'info' => 'Mailjet Info',
		);

		$form_id                = $this->form_id;
		$form_settings_instance = $this->form_settings_instance;

		/**
		 * Filter mailjet headers on export file
		 *
		 * @param array                                    $export_headers         headers to be displayed on export file.
		 * @param int                                      $form_id                current Form ID.
		 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet API Form Settings instance.
		 */
		$export_headers = apply_filters(
			'forminator_addon_mailjet_export_headers',
			$export_headers,
			$form_id,
			$form_settings_instance
		);

		return $export_headers;
	}

	/**
	 * Add description of status mailjet addon after form submitted similar with render entry
	 *
	 * @param Forminator_Form_Entry_Model $entry_model
	 * @param                             $addon_meta_data
	 *
	 * @return array
	 */
	public function on_export_render_entry( Forminator_Form_Entry_Model $entry_model, $addon_meta_data ) {

		$form_id                = $this->form_id;
		$form_settings_instance = $this->form_settings_instance;

		/**
		 *
		 * Filter mailjet metadata that previously saved on db to be processed
		 *
		 * @param array                                    $addon_meta_data
		 * @param int                                      $form_id                current Form ID.
		 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet API Form Settings instance.
		 */
		$addon_meta_data = apply_filters(
			'forminator_addon_mailjet_metadata',
			$addon_meta_data,
			$form_id,
			$form_settings_instance
		);

		$export_columns = array(
			'info' => $this->get_from_addon_meta_data( $addon_meta_data, 'description', '' ),
		);

		/**
		 * Filter mailjet columns to be displayed on export submissions
		 *
		 * @param array                                    $export_columns         column to be exported.
		 * @param int                                      $form_id                current Form ID.
		 * @param Forminator_Form_Entry_Model              $entry_model            Form Entry Model.
		 * @param array                                    $addon_meta_data        meta data saved by addon on entry fields.
		 * @param Forminator_Addon_Mailjet_Form_Settings $form_settings_instance Mailjet API Form Settings instance.
		 */
		$export_columns = apply_filters(
			'forminator_addon_mailjet_export_columns',
			$export_columns,
			$form_id,
			$entry_model,
			$addon_meta_data,
			$form_settings_instance
		);

		return $export_columns;
	}

	/**
	 * Helper to get addon meta data with key specified
	 *
	 * @param        $addon_meta_data
	 * @param        $key
	 * @param string $default
	 *
	 * @return string
	 */
	private function get_from_addon_meta_data( $addon_meta_data, $key, $default = '' ) {
		$addon_meta_datas = $addon_meta_data;
		if ( ! isset( $addon_meta_data[0] ) || ! is_array( $addon_meta_data[0] ) ) {
			return $default;
		}

		$addon_meta_data = $addon_meta_data[0];

		// make sure its `status`, because we only add this.
		if ( 'status' !== $addon_meta_data['name'] ) {
			if ( stripos( $addon_meta_data['name'], 'status-' ) === 0 ) {
				$meta_data = array();
				foreach ( $addon_meta_datas as $addon_meta_data ) {
					// make it like single value so it will be processed like single meta data.
					$addon_meta_data['name'] = 'status';

					// add it on an array for next recursive process.
					$meta_data[] = $this->get_from_addon_meta_data( array( $addon_meta_data ), $key, $default );
				}

				return implode( ', ', $meta_data );
			}

			return $default;

		}

		if ( ! isset( $addon_meta_data['value'] ) || ! is_array( $addon_meta_data['value'] ) ) {
			return $default;
		}
		$status = $addon_meta_data['value'];
		if ( isset( $status[ $key ] ) ) {
			$connection_name = '';
			if ( 'connection_name' !== $key ) {
				if ( isset( $status['connection_name'] ) ) {
					$connection_name = '[' . $status['connection_name'] . '] ';
				}
			}

			return $connection_name . $status[ $key ];
		}

		return $default;
	}
}
