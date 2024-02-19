<?php

/**
 * Mixpanel General Events class
 */
class Forminator_Mixpanel_General extends Events {

	/**
	 * Initialize class.
	 *
	 * @since 1.27.0
	 */
	public static function init() {
		add_action( 'forminator_after_form_import', array( __CLASS__, 'tracking_import' ) );
		add_action( 'forminator_after_form_export', array( __CLASS__, 'tracking_export' ) );
		add_action( 'forminator_before_manual_export_download', array( __CLASS__, 'tracking_manual_export' ), 10, 2 );
		add_action( 'forminator_after_export_schedule_save', array( __CLASS__, 'tracking_schedule_export' ), 10, 3 );
	}

	/**
	 * Tracking Import
	 *
	 * @param string $slug
	 *
	 * @return void
	 * @since 1.27.0
	 *
	 */
	public static function tracking_import( $slug ) {
		self::event_form_import_export( $slug, 'import' );
	}

	/**
	 * Tracking Export
	 *
	 * @param string $slug
	 *
	 * @return void
	 * @since 1.27.0
	 *
	 */
	public static function tracking_export( $slug ) {
		self::event_form_import_export( $slug, 'export' );
	}

	/**
	 * Tracking Schedule Export
	 *
	 * @param int $form_id
	 * @param string $form_type
	 *
	 * @return void
	 * @since 1.27.0
	 *
	 */
	public static function tracking_manual_export( $form_id, $form_type ) {
		if ( ! self::is_tracking_active() ) {
			return;
		}

		self::event_submission_export( $form_type, 'manual' );
	}

	/**
	 * Tracking Schedule Export
	 *
	 * @param int $form_id
	 * @param string $form_type
	 * @param array $data
	 *
	 * @return void
	 * @since 1.27.0
	 *
	 */
	public static function tracking_schedule_export( $form_id, $form_type, $data ) {
		if ( ! self::is_tracking_active() ) {
			return;
		}

		$schedule_active = isset( $data[ $form_id . $form_type ] ) ? $data[ $form_id . $form_type ] : array();

		if ( ! $schedule_active['enabled'] ) {
			return;
		}

		self::event_submission_export( $form_type, 'schedule' );
	}

	/**
	 * Event Import/Export
	 *
	 * @param string $slug
	 * @param string $type
	 *
	 * @return void
	 */
	private static function event_form_import_export( $slug, $type ) {
		if ( ! self::is_tracking_active() ) {
			return;
		}

		self::track_event(
			'form_import_export',
			array(
				'form type' => esc_html( $slug ),
				'exim type' => esc_html( $type )
			)
		);
	}

	/**
	 * Event Submission export
	 *
	 * @param string $form_type
	 * @param string $action
	 *
	 * @return void
	 */
	private static function event_submission_export( $form_type, $action ) {
		if ( 'cform' === $form_type ) {
			$form_type = 'form';
		}
		self::track_event(
			'for_submission_exported',
			array(
				'form type'   => esc_html( $form_type ),
				'export type' => esc_html( $action )
			)
		);
	}
}
