<?php

require_once dirname( __FILE__ ) . '/class-forminator-addon-mailjet-quiz-settings-exception.php';

/**
 * Class Forminator_Addon_Mailjet_Quiz_Settings
 * Form Settings Mailjet Process
 */
class Forminator_Addon_Mailjet_Quiz_Settings extends Forminator_Addon_Quiz_Settings_Abstract {

	/**
	 * Addon object
	 *
	 * @var Forminator_Addon_Mailjet
	 */
	protected $addon;

	/**
	 * For settings Wizard steps
	 *
	 * @return array
	 */
	public function quiz_settings_wizards() {
		// already filtered on Abstract.
		$this->addon_quiz_settings = $this->get_quiz_settings_values();

		// numerical array steps.
		$steps = array(
			// 1
			array(
				'callback'     => array( $this, 'choose_mail_list' ),
				'is_completed' => array( $this, 'step_choose_mail_list_is_completed' ),
			),
			// 2
			array(
				'callback'     => array( $this, 'map_fields' ),
				'is_completed' => array( $this, 'step_map_fields_is_completed' ),
			),
		);

		return $steps;
	}

	/**
	 * Get mail list data
	 *
	 * @param array $submitted_data Submitted data.
	 * @return array
	 */
	private function mail_list_data( $submitted_data ) {
		$default_data = array(
			'mail_list_id' => '',
		);
		$current_data = $this->get_current_data( $default_data, $submitted_data );

		return $current_data;
	}

	/**
	 * Choose Mail wizard
	 *
	 * @param array $submitted_data Submitted data.
	 *
	 * @return array
	 */
	public function choose_mail_list( $submitted_data ) {
		$current_data = $this->mail_list_data( $submitted_data );

		$api_error  = '';
		$list_error = '';
		$lists      = array();

		try {
			$lists = $this->addon->get_api()->get_prepared_lists();
			if ( empty( $lists ) ) {
				$list_error = __( 'Your Mailjet List is empty, please create one.', 'forminator' );
			} elseif ( ! empty( $submitted_data ) ) {
				// logic when user submit mail list.
				$mail_list_name = $this->get_choosen_mail_list_name( $lists, $submitted_data );

				if ( empty( $mail_list_name ) ) {
					$list_error = __( 'Please select a valid Email List', 'forminator' );
				} else {
					$this->save_settings( $submitted_data, $mail_list_name );
				}
			}
		} catch ( Forminator_Addon_Mailjet_Exception $e ) {
			// send error back to client.
			$api_error = self::get_error_notice( $e );
		}

		$html = self::get_choose_list_header( $api_error );

		if ( ! $api_error ) {
			$html .= '<form enctype="multipart/form-data">';
			$html .= self::get_choose_list_field( $current_data, $lists, $list_error );
			$html .= '</form>';
		}

		return array(
			'html'       => $html,
			'redirect'   => false,
			'buttons'    => $this->get_choose_list_buttons( $api_error ),
			'has_errors' => ! empty( $api_error ) || ! empty( $list_error ),
			'size'       => 'small',
		);
	}

	/**
	 * Get HTML for buttons on Choose List step.
	 *
	 * @param string $api_error API error.
	 * @return array
	 */
	private function get_choose_list_buttons( $api_error ) {
		$buttons = array();

		if ( ! $api_error ) {
			// add disconnect button if already is_quiz_connected.
			if ( $this->addon->is_quiz_connected( $this->quiz_id ) ) {
				$buttons['disconnect']['markup'] = Forminator_Addon_Mailjet::get_button_markup(
					esc_html__( 'Deactivate', 'forminator' ),
					'sui-button-ghost sui-tooltip sui-tooltip-top-center forminator-addon-form-disconnect',
					esc_html__( 'Deactivate Mailjet from this quiz.', 'forminator' )
				);
			}

			$buttons['next']['markup'] = '<div class="sui-actions-right">' .
				Forminator_Addon_Abstract::get_button_markup( esc_html__( 'Next', 'forminator' ), 'forminator-addon-next' ) .
			'</div>';
		}

		return $buttons;
	}

	/**
	 * Get addon custom fields
	 *
	 * @return array
	 */
	protected function get_addon_custom_fields() {
		$custom_fields = array(
			// Add default fields.
			'email' => (object) array(
				'name'     => __( 'Email', 'forminator' ),
				'id'       => 'email',
				'datatype' => 'email',
				'required' => true,
			),
			'name'  => (object) array(
				'name'     => __( 'Name', 'forminator' ),
				'id'       => 'name',
				'datatype' => 'str',
			),
		);
		// get fields.
		$fields_list = $this->addon->get_api()->get_contact_properties();

		$custom_fields += $fields_list;

		return $custom_fields;
	}

	/**
	 * Save submitted settings
	 *
	 * @param array  $submitted_data Submitted data.
	 * @param string $list_name List name.
	 */
	private function save_settings( $submitted_data, $list_name ) {
		$this->addon_quiz_settings['mail_list_id']   = $submitted_data['mail_list_id'];
		$this->addon_quiz_settings['mail_list_name'] = $list_name;

		$this->save_quiz_settings_values( $this->addon_quiz_settings );
	}

	/**
	 * Step mapping fields on wizard
	 *
	 * @param array $submitted_data Submitted data.
	 *
	 * @return array
	 */
	public function map_fields( $submitted_data ) {
		$is_close              = false;
		$is_submit             = ! empty( $submitted_data );
		$error_message         = '';
		$html_input_map_fields = '';
		$input_error_messages  = array();

		try {
			$fields_map      = array();
			$fields_list     = $this->get_addon_custom_fields();
			$fields_list_ids = wp_list_pluck( $fields_list, 'id' );

			foreach ( $fields_list_ids as $key ) {
				$fields_map[ $key ] = $submitted_data['fields_map'][ $key ] ?? $this->addon_quiz_settings['fields_map'][ $key ] ?? '';
			}

			/** Build table map fields input */
			$html_input_map_fields = $this->get_input_map_fields( $fields_list, $fields_map );

			if ( $is_submit ) {
				$this->step_map_fields_validate( $fields_list, $submitted_data );
				$this->save_module_settings_values();
				$is_close = true;
			}
		} catch ( Forminator_Addon_Mailjet_Quiz_Settings_Exception $e ) {
			$input_error_messages = $e->get_input_exceptions();
			if ( ! empty( $html_input_map_fields ) ) {
				foreach ( $input_error_messages as $input_id => $message ) {
					if ( is_array( $message ) ) {
						foreach ( $message as $addr => $m ) {
							$html_input_map_fields = str_replace( '{{$error_css_class_' . $input_id . '_' . $addr . '}}', 'sui-form-field-error', $html_input_map_fields );
							$html_input_map_fields = str_replace( '{{$error_message_' . $input_id . '_' . $addr . '}}', '<span class="sui-error-message">' . esc_html( $m ) . '</span>', $html_input_map_fields );
						}
					} else {
						$html_input_map_fields = str_replace( '{{$error_css_class_' . $input_id . '}}', 'sui-form-field-error', $html_input_map_fields );
						$html_input_map_fields = str_replace( '{{$error_message_' . $input_id . '}}', '<span class="sui-error-message">' . esc_html( $message ) . '</span>', $html_input_map_fields );
					}
				}
			}
		} catch ( Forminator_Addon_Mailjet_Exception $e ) {
			$error_message = '<div role="alert" class="sui-notice sui-notice-red sui-active" style="display: block; text-align: left;" aria-live="assertive">';

				$error_message .= '<div class="sui-notice-content">';

					$error_message .= '<div class="sui-notice-message">';

						$error_message .= '<span class="sui-notice-icon sui-icon-info" aria-hidden="true"></span>';

						$error_message .= '<p>' . $e->getMessage() . '</p>';

					$error_message .= '</div>';

				$error_message .= '</div>';

			$error_message .= '</div>';
		}

		// cleanup map fields input markup placeholder.
		if ( ! empty( $html_input_map_fields ) ) {
			$replaced_html_input_map_fields = $html_input_map_fields;
			$replaced_html_input_map_fields = preg_replace( '/\{\{\$error_css_class_(.+)\}\}/', '', $replaced_html_input_map_fields );
			$replaced_html_input_map_fields = preg_replace( '/\{\{\$error_message_(.+)\}\}/', '', $replaced_html_input_map_fields );
			if ( ! is_null( $replaced_html_input_map_fields ) ) {
				$html_input_map_fields = $replaced_html_input_map_fields;
			}
		}

		$buttons = array(
			'cancel' => array(
				'markup' => Forminator_Addon_Abstract::get_button_markup( esc_html__( 'Back', 'forminator' ), 'sui-button-ghost forminator-addon-back' ),
			),
			'next'   => array(
				'markup' => '<div class="sui-actions-right">' .
					Forminator_Addon_Abstract::get_button_markup( esc_html__( 'Save', 'forminator' ), 'sui-button-primary forminator-addon-finish' ) .
				'</div>',
			),
		);

		$notification = array();

		if ( $is_submit && empty( $error_message ) && empty( $input_error_messages ) ) {
			$notification = array(
				'type' => 'success',
				'text' => '<strong>' . $this->addon->get_title() . '</strong> ' . esc_html__( 'is activated successfully.', 'forminator' ),
			);
		}

		$html      = '<div class="forminator-integration-popup__header">';
			$html .= '<h3 id="dialogTitle2" class="sui-box-title sui-lg" style="overflow: initial; text-overflow: none; white-space: normal;">' . esc_html__( 'Map Fields', 'forminator' ) . '</h3>';
			$html .= '<p class="sui-description">' . esc_html__( 'Lastly, match up your quiz fields with the campaign fields to ensure that the data is sent to the right place.', 'forminator' ) . '</p>';
			$html .= $error_message;
		$html     .= '</div>';
		$html     .= '<form enctype="multipart/form-data">';
			$html .= $html_input_map_fields;
		$html     .= '</form>';

		return array(
			'html'         => $html,
			'redirect'     => false,
			'is_close'     => $is_close,
			'buttons'      => $buttons,
			'has_errors'   => ! empty( $error_message ) || ! empty( $input_error_messages ),
			'notification' => $notification,
			'size'         => 'normal',
			'has_back'     => true,
		);
	}

	/**
	 * Get current data based on submitted or saved data
	 *
	 * @param array $current_data Default data.
	 * @param array $submitted_data Submitted data.
	 * @return array
	 */
	private function get_current_data( $current_data, $submitted_data ) {
		foreach ( array_keys( $current_data ) as $key ) {
			if ( isset( $submitted_data[ $key ] ) ) {
				$current_data[ $key ] = $submitted_data[ $key ];
			} elseif ( isset( $this->addon_quiz_settings[ $key ] ) ) {
				$current_data[ $key ] = $this->addon_quiz_settings[ $key ];
			}
		}

		forminator_addon_maybe_log( __METHOD__, 'current_data', $current_data );

		return $current_data;
	}

	/**
	 * Get input of Map Fields
	 * its table with html select options as input
	 *
	 * @param array $addon_fields Addon fields.
	 * @param array $fields_map Fields map.
	 * @return string HTML table
	 */
	protected function get_input_map_fields( $addon_fields, $fields_map ) {
		ob_start();
		?>
		<table class="sui-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Provider Fields', 'forminator' ); ?></th>
				<th><?php esc_html_e( 'Forminator Field', 'forminator' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $addon_fields as $item ) {
				$type       = $item->type ?? $item->datatype;
				$all_fields = $this->get_fields_for_type( $type );
				?>
				<tr>
					<td><?php echo esc_html( $item->name ); ?>
						<?php if ( ! empty( $item->required ) ) { ?>
						<span class="integrations-required-field">*</span>
						<?php } ?>
					</td>
					<td>
						<div class="sui-form-field {{$error_css_class_<?php echo esc_attr( $item->id ); ?>}}">
							<select class="sui-select" name="fields_map[<?php echo esc_attr( $item->id ); ?>]">
								<option value=""><?php esc_html_e( 'None', 'forminator' ); ?></option>
								<?php foreach ( $all_fields as $form_field ) { ?>
									<option value="<?php echo esc_attr( $form_field['element_id'] ); ?>"
										<?php selected( $fields_map[ $item->id ], $form_field['element_id'] ); ?>>
										<?php echo esc_html( wp_strip_all_tags( $form_field['field_label'] ) . ' | ' . $form_field['element_id'] ); ?>
									</option>
								<?php } ?>
							</select>
							{{$error_message_<?php echo esc_attr( $item->id ); ?>}}
						</div>
					</td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get mail List Name of submitted data
	 *
	 * @param array $lists Lists.
	 * @param array $submitted_data Submitted data.
	 *
	 * @return string
	 */
	private function get_choosen_mail_list_name( $lists, $submitted_data ) {
		forminator_addon_maybe_log( __METHOD__, '$submitted_data', $submitted_data );
		$mail_list_id   = $submitted_data['mail_list_id'] ?? 0;
		$mail_list_name = $lists[ $mail_list_id ] ?? '';
		forminator_addon_maybe_log( __METHOD__, '$mail_list_name', $mail_list_name );

		return $mail_list_name;
	}

	/**
	 * Validate submitted data by user as expected by merge field on mailjet mail list
	 *
	 * @param array $mailjet_fields_list List of Miljet fields.
	 * @param array $post_data POST data.
	 *
	 * @return array current addon form settings
	 * @throws Forminator_Addon_Mailjet_Exception
	 * @throws Forminator_Addon_Mailjet_Form_Settings_Exception
	 */
	public function step_map_fields_validate( $mailjet_fields_list, $post_data ) {

		$forminator_field_element_ids = array();
		$forminator_quiz_element_ids  = array();
		$address_value                = array();
		foreach ( $this->form_fields as $form_field ) {
			$forminator_field_element_ids[] = $form_field['element_id'];
		}

		$quiz_questions = $this->get_quiz_fields();
		foreach ( $quiz_questions as $quiz_question ) {
			// collect element ids.
			$forminator_quiz_element_ids[] = $quiz_question['slug'];
		}
		if ( 'knowledge' === $this->quiz->quiz_type ) {
			array_push( $forminator_quiz_element_ids, 'quiz-name', 'correct-answers', 'total-answers' );
		} elseif ( 'nowrong' === $this->quiz->quiz_type ) {
			array_push( $forminator_quiz_element_ids, 'quiz-name', 'result-answers' );
		}

		$forminator_field_element_ids = array_merge( $forminator_field_element_ids, $forminator_quiz_element_ids );

		// map mailjet maped with tag as its key.
		$tag_maped_mailjet_fields = array();
		foreach ( $mailjet_fields_list as $item ) {
			$tag_maped_mailjet_fields[ $item->id ] = $item;
		}

		if ( ! isset( $post_data['fields_map'] ) ) {
			$this->_update_quiz_settings_error_message = 'Please assign fields.';
			throw new Forminator_Addon_Mailjet_Exception( $this->_update_quiz_settings_error_message );
		}
		$post_data = $post_data['fields_map'];

		if ( ! isset( $this->addon_quiz_settings['fields_map'] ) ) {
			$this->addon_quiz_settings['fields_map'] = array();
		}

		// set fields_map from post_data for reuse.
		foreach ( $post_data as $mailjet_field_tag => $forminator_field_id ) {
			$this->addon_quiz_settings['fields_map'][ $mailjet_field_tag ] = $post_data[ $mailjet_field_tag ];
		}

		$input_exceptions = new Forminator_Addon_Mailjet_Quiz_Settings_Exception();
		// email is required.
		if ( empty( $post_data['email'] ) ) {
			$this->_update_quiz_settings_error_message = esc_html__( 'Please choose valid Forminator field for email address.', 'forminator' );
			$input_exceptions->add_input_exception( $this->_update_quiz_settings_error_message, 'email' );
		}

		// Check availibility on forminator field.
		foreach ( $this->addon_quiz_settings['fields_map'] as $mailjet_field_tag => $forminator_field_id ) {
			if ( empty( $forminator_field_id ) ) {
				continue;
			}
			if ( is_array( $forminator_field_id ) ) {
				foreach ( $forminator_field_id as $addr => $field_id ) {
					if ( ! empty( $field_id ) ) {
						$address_value[ $mailjet_field_tag ][ $addr ] = $field_id;
					}
				}
				foreach ( $forminator_field_id as $addr => $field_id ) {
					if ( 'addr2' === $addr ) {
						continue;
					}
					if ( ! empty( $address_value ) && ! in_array( $field_id, $forminator_field_element_ids, true ) ) {
						$mailjet_field      = $tag_maped_mailjet_fields[ $mailjet_field_tag ];
						$mailjet_field_name = $mailjet_field->name;

						$this->_update_quiz_settings_error_message =
							/* translators: %s: Mailjet field name */
							sprintf( esc_html__( 'Please choose valid Forminator field for %s.', 'forminator' ), esc_html( $mailjet_field_name ) );
						$input_exceptions->add_sub_input_exception( $this->_update_quiz_settings_error_message, $mailjet_field_tag, $addr );
					}
				}
			}
			if ( ! is_array( $forminator_field_id ) && ! in_array( $forminator_field_id, $forminator_field_element_ids, true ) ) {
				if ( 'email' === $mailjet_field_tag ) {
					$mailjet_field_name = esc_html__( 'Email Address', 'forminator' );
				} else {
					$mailjet_field      = $tag_maped_mailjet_fields[ $mailjet_field_tag ];
					$mailjet_field_name = $mailjet_field->name;
				}

				$this->_update_quiz_settings_error_message =
					/* translators: %s: Mailjet field name */
					sprintf( esc_html__( 'Please choose valid Forminator field for %s.', 'forminator' ), esc_html( $mailjet_field_name ) );
				$input_exceptions->add_input_exception( $this->_update_quiz_settings_error_message, $mailjet_field_tag );
			}
		}

		if ( $input_exceptions->input_exceptions_is_available() ) {
			throw $input_exceptions;
		}

		return $this->addon_quiz_settings;
	}

	/**
	 * Check if map fields is completed
	 *
	 * @return bool
	 */
	public function step_map_fields_is_completed() {
		$this->addon_quiz_settings = $this->get_quiz_settings_values();
		if ( ! $this->step_choose_mail_list_is_completed() ) {
			return false;
		}

		if ( empty( $this->addon_quiz_settings['fields_map'] ) ) {
			return false;
		}

		if ( ! is_array( $this->addon_quiz_settings['fields_map'] ) ) {
			return false;
		}

		if ( count( $this->addon_quiz_settings['fields_map'] ) < 1 ) {
			return false;
		}

		/**
		 * TODO: check if saved fields_map still valid, by request merge_fields on mailjet
		 * Easy achieved but will add overhead on site
		 * force_form_disconnect();
		 * save_force_quiz_disconnect_reason();
		 */

		return true;

	}

	/**
	 * Check if mail list already selected completed
	 *
	 * @return bool
	 */
	public function step_choose_mail_list_is_completed() {
		$this->addon_quiz_settings = $this->get_quiz_settings_values();
		if ( ! isset( $this->addon_quiz_settings['mail_list_id'] ) ) {
			// preliminary value.
			$this->addon_quiz_settings['mail_list_id'] = 0;

			return false;
		}

		if ( empty( $this->addon_quiz_settings['mail_list_id'] ) ) {
			return false;
		}

		/**
		 * TODO: check if saved mail list id still valid, by request info on mailjet
		 * Easy achieved but will add overhead on site
		 * force_quiz_disconnect();
		 * save_force_quiz_disconnect_reason();
		 */

		return true;
	}
}
