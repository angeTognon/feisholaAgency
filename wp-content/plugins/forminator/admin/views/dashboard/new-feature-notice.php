<?php
$user      = wp_get_current_user();
$banner_1x = forminator_plugin_url() . 'assets/images/Feature_highlight.png';
$banner_2x = forminator_plugin_url() . 'assets/images/Feature_highlight@2x.png';
?>

<div class="sui-modal sui-modal-md">

	<div
		role="dialog"
		id="forminator-new-feature"
		class="sui-modal-content"
		aria-live="polite"
		aria-modal="true"
		aria-labelledby="forminator-new-feature__title"
	>

		<div class="sui-box forminator-feature-modal" data-prop="forminator_dismiss_feature_1280" data-nonce="<?php echo esc_attr( wp_create_nonce( 'forminator_dismiss_notification' ) ); ?>">

			<div class="sui-box-header sui-flatten sui-content-center">

				<figure class="sui-box-banner" aria-hidden="true">
					<img
						src="<?php echo esc_url( $banner_1x ); ?>"
						srcset="<?php echo esc_url( $banner_1x ); ?> 1x, <?php echo esc_url( $banner_2x ); ?> 2x"
						alt=""
					/>
				</figure>

				<button class="sui-button-icon sui-button-white sui-button-float--right forminator-dismiss-new-feature" data-type="dismiss" data-modal-close>
					<span class="sui-icon-close sui-md" aria-hidden="true"></span>
					<span class="sui-screen-reader-text"><?php esc_html_e( 'Close this dialog.', 'forminator' ); ?></span>
				</button>

				<h3 class="sui-box-title sui-lg" style="overflow: initial; white-space: initial; text-overflow: initial;">
					<?php esc_html_e( 'New: User Role Permissions and Mailjet', 'forminator' ); ?>
                </h3>

				<p class="sui-description">
					<?php
					printf(
						/* translators: 1. Admin name 2. Open 'b' tag 3. Close 'b' tag */
						esc_html__( 'Hey, %1$s! We\'re thrilled to introduce our new %2$sUser Role Permissions%3$s feature and seamless %2$sMailjet Integration%3$s to take your form building experience to the next level.', 'forminator' ),
						esc_html( ucfirst( $user->display_name ) ),
						'<b>',
						'</b>'
					);
					?>
				</p>

				<div class="sui-modal-list">
					<ul style="text-align: left;">

						<li>
							<h3 style="margin-bottom: 0;">
								<span class="sui-icon-check sui-sm" aria-hidden="true"></span>
								&nbsp;&nbsp;
								<?php esc_html_e( 'User Role Permissions', 'forminator' ); ?></h3>
							<p class="sui-description" style="margin-bottom: 30px;"><?php esc_html_e( 'You can now control the access and management of Forminator\'s features by assigning access to selected users or user roles, ensuring tailored and secure form and data management.', 'forminator' ); ?></p>
						</li>

						<li>
							<h3 style="margin-bottom: 0;">
								<span class="sui-icon-check sui-sm" aria-hidden="true"></span>
								&nbsp;&nbsp;
								<?php esc_html_e( 'Mailjet Integration', 'forminator' ); ?>
							</h3>
							<p class="sui-description"><?php esc_html_e( 'With the latest version of Forminator, you can seamlessly connect and send your form submissions to Mailjet, enabling easy creation and delivery of marketing and transactional emails.', 'forminator' ); ?></p>
						</li>

					</ul>
				</div>
			</div>

			<div class="sui-box-footer sui-flatten sui-content-center">

				<button class="sui-button forminator-dismiss-new-feature" data-modal-close>
					<?php esc_html_e( 'Got it!', 'forminator' ); ?>
                </button>

			</div>

		</div>

	</div>

</div>

<script type="text/javascript">
  jQuery('#forminator-new-feature .forminator-dismiss-new-feature').on('click', function (e) {
    e.preventDefault()

    var $notice = jQuery(e.currentTarget).closest('.forminator-feature-modal'),
      ajaxUrl = '<?php echo esc_url( forminator_ajax_url() ); ?>',
      dataType = jQuery(this).data('type'),
      ajaxData = {
        action: 'forminator_dismiss_notification',
        prop: $notice.data('prop'),
        _ajax_nonce: $notice.data('nonce')
      }

    if ( 'save' === dataType ) {
      ajaxData['usage_value'] = jQuery('#forminator-new-feature-toggle').is(':checked')
    }

    jQuery.post(ajaxUrl, ajaxData)
      .always(function () {
        $notice.hide()
      })
  })
</script>
