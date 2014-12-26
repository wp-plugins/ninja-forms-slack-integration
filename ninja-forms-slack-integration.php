<?php
/**
 * Plugin Name: Ninja Forms Slack Notifications
 * Description: Sends notifications when a new form is submitted through Ninja Forms (Inspired by the Slack bbPress plugin)
 * Author: Nikhil Vimal
 * Author URI: http://nik.techvoltz.com
 * Version: 1.0
 * Plugin URI:
 * License: GNU GPLv2+
 */
/**
 * Copyright (c) 2014 Nikhil Vimal
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The most important function, this sends all the info to Slack
 *
 * @since 1.0
 */
add_action( 'ninja_forms_post_process',  'ninja_forms_slack_integration', 20, 2 );

/**
 * Primary Ninja Forms Slack function
 *
 * Uses the Slack API to send a slack notification
 *
 * @since 1.0
 */
function ninja_forms_slack_integration() {
	$options = get_option('ninja-forms-slack-hook');

	$webhook_url = $options['ninja_forms_slack_hook'];
	if ( !$webhook_url == "" ) {
		global $ninja_forms_processing;
		$args = array(
			'form_id'   => $form_id,
			'user_id'   => $user_id,
			'date_submitted' => '$date_submitted',
		);

		$subs = Ninja_Forms()->subs()->get( $args );
		foreach ( $subs as $sub ) {
			$user_id = $sub->user_id;
			$date_submitted = $sub->date_submitted;
			$sub_id = $sub->sub_id;
		}

		$current_action = $ninja_forms_processing->get_action();
		$form_title = $ninja_forms_processing->get_form_setting('form_title');


		if ( is_user_logged_in() ) {
			$user_info = get_userdata( $user_id );
			$payload = array(
				'text'        => __( 'Form Submitted', 'ninja-forms-slack' ),
				'username'    => 'FormBot',
				'attachments' => array(
					'fallback' => 'Form Submitted',
					'color'    => '#EF4748',
					'fields'   => array(
						'title' => 'Form Submitted',
						'value' => 'Form Submitted',
						'text'  => 'A User ' . $user_info->user_login . ' - ' . $user_info->user_email . ' has submitted a form on your site ' . site_url() . '' .
						           '
					           Form Name: ' . $form_title . '    " ' . $current_action . 'ted" on ' . $date_submitted . '
					           ' . 'Submission ID: ' . $sub_id . '
					           ' .
						           'View Submissions at ' . admin_url( 'edit.php?post_type=nf_sub' ) . ''
					)
				),
			);
			$output  = 'payload=' . json_encode( $payload );
			$response = wp_remote_post( $webhook_url, array(
				'body' => $output,
			) );
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				echo "Something went wrong: $error_message";
			}
		}
		//If user is not logged in
		else {
			$payload = array(
				'text'        => __( 'Form Submitted', 'ninja-forms-slack' ),
				'username'    => 'FormBot',
				'attachments' => array(
					'fallback' => 'Form Submitted',
					'color'    => '#EF4748',
					'fields'   => array(
						'title' => 'Form Submitted',
						'value' => 'Form Submitted',
						'text'  => 'A User has submitted a form on your site ' . site_url() . '' .
						           '
					           Form Name: ' . $form_title . '    " ' . $current_action . 'ted" on ' . $date_submitted . '
					           ' . 'Submission ID: ' . $sub_id . '
					           ' .
						           'View Submissions at ' . admin_url( 'edit.php?post_type=nf_sub' ) . ''
					)
				),
			);

			//Use json_encode() to send the message to the Slack API
			$output  = 'payload=' . json_encode( $payload );
			$response = wp_remote_post( $webhook_url, array(
				'body' => $output,
			) );
			//If there is an issue with the response, get error message
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				echo "Something went wrong: $error_message";
			}
		}

		/**
		 * Fires after the Slack message is sent to slack.
		 *
		 * @param array $response Response from server.
		 *
		 * @since 1.0
		 */
		do_action('ninja_forms_slack_sent', $response);
	}
}

if ( is_admin() ) {
	add_action( 'admin_menu', 'ninja_forms_slack_integration_menu' );
}

/**
 * The admin menu function
 *
 * @since 1.0
 */
function ninja_forms_slack_integration_menu() {
	add_options_page(
		__( 'Ninja Forms Slack Integration', 'ninja-forms-slack' ),
		__( 'Ninja Forms Slack Integration', 'ninja-forms-slack' ),
		'manage_options',
		'ninja_forms_slack',
		'ninja_forms_slack_integration_options_page'
	);
}

//The HTML for the Option Page
function ninja_forms_slack_integration_options_page() {
	?>
	<div class="wrap">
		<h2>Ninja Forms Slack Integration</h2>
		<form action="options.php" method="POST">
			<?php settings_fields( 'ninja-forms-slack-group' ); ?>
			<?php do_settings_sections( 'ninja-forms-slack' ); ?>
			<?php submit_button(); ?>
		</form>
	</div>

<?php
}


add_action('admin_init', 'ninja_forms_slack_admin_init');

/**
 * Register the setting, validation, section, callbacks, and more
 *
 * @since 1.0
 */
function ninja_forms_slack_admin_init(){
	register_setting( 'ninja-forms-slack-group', 'ninja-forms-slack-hook', 'ninja_forms_slack_validate' );
	add_settings_section( 'ninja-forms-slack-section', 'Slack Settings', 'ninja_forms_slack_section_callback', 'ninja-forms-slack' );
	add_settings_field( 'ninja_forms_slack_field', 'Webhook URL', 'ninja_forms_slack_field_callback', 'ninja-forms-slack', 'ninja-forms-slack-section' );
}

/**
 * Settings Section HTML (Instruction for using plugin)
 */
function ninja_forms_slack_section_callback() {
	echo '<ol>
		<li>Go To https://slack.com/services/new/incoming-webhook</li>
		<li>Create a new webhook</li>
		<li>Set a channel to receive the notifications</li>
		<li>Copy the URL for the webhook</li>
		<li>Paste the URL into the field below and click submit</li>
		</ol>';
}

/**
 * The URL field HTML/Callback
 */
function ninja_forms_slack_field_callback() {
	$setting = get_option( 'ninja-forms-slack-hook' );

	echo '<input type="url" id="ninja-forms-slack-hook" name="ninja-forms-slack-hook[ninja_forms_slack_hook]" value="' . esc_url( $setting[ 'ninja_forms_slack_hook' ] ) . '" />';
}

/**
 * Validate the Slack webhook field
 *
 * @return mixed|void
 */
function ninja_forms_slack_validate($input) {
	$options = get_option('ninja-forms-slack-hook');
	$options['ninja_forms_slack_hook'] = trim($input['ninja_forms_slack_hook']);
	if(!preg_match('/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', $options['ninja_forms_slack_hook'])) {
		$options['ninja_forms_slack_hook'] = '';
	}

	return $options;
}

