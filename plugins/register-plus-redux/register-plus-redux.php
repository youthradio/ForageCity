<?php
/*
Author: radiok
Plugin Name: Register Plus Redux
Author URI: http://radiok.info/
Plugin URI: http://radiok.info/category/register-plus-redux/
Description: Enhances the user registration process with complete customization and additional administration options.
Version: 3.7.3
Text Domain: register-plus-redux
*/

if ( !class_exists("RegisterPlusReduxPlugin") ) {
	class RegisterPlusReduxPlugin {
		function RegisterPlusReduxPlugin() {
			global $wp_version;
			add_action("init", array($this, "InitL18n"), 10, 1); //Runs after WordPress has finished loading but before any headers are sent.

			if ( is_admin() ) {
				add_action("init", array($this, "InitOptions"), 10, 1); //Runs after WordPress has finished loading but before any headers are sent.
				add_action("init", array($this, "InitDeleteExpiredUsers"), 10, 1); //Runs after WordPress has finished loading but before any headers are sent.
				add_action("admin_menu", array($this, "AddPages"), 10, 1); //Runs after the basic admin panel menu structure is in place.
			}

			if ( is_multisite() ) {
				add_action("signup_extra_fields", array($this, "AlterRegisterSignupForm"), 10, 1);
				add_filter("wpmu_validate_user_signup", array($this, "CheckSignupForm"), 10, 1); //applied to the list of registration errors generated while registering a user for a new account. 
				//add_action("wpmu_activate_user", array($this, "UpdateSignup"), 10, 3);
			}

			if ( !is_multisite() ) {
				add_filter("login_headerurl", array($this, "filter_login_headerurl"), 10, 1);
				add_filter("login_headertitle", array($this, "filter_login_headertitle"), 10, 1);
				add_action("register_form", array($this, "AlterRegisterSignupForm"), 10, 1); //Runs just before the end of the new user registration form.
				add_filter("registration_errors", array($this, "CheckRegistrationForm"), 10, 3); //applied to the list of registration errors generated while registering a user for a new account. 
				add_filter("registration_redirect", array($this, "filter_registration_redirect"), 10, 1);
			}

			add_filter("pre_user_login", array($this, "filter_pre_user_login"), 10, 1);
			add_action("login_head", array($this, "LoginHead"), 10, 1); //Runs just before the end of the HTML head section of the login page. 
			add_filter("login_message", array($this, "filter_login_message"), 10, 1);
			add_filter("login_messages", array($this, "filter_login_messages"), 10, 1);
			add_action("admin_head-profile.php", array($this, "DatepickerHead"), 10, 1); //Runs in the HTML <head> section of the admin panel of a page or a plugin-generated page.
			add_action("admin_head-user-edit.php", array($this, "DatepickerHead"), 10, 1); //Runs in the HTML <head> section of the admin panel of a page or a plugin-generated page.
			add_action("show_user_profile", array($this, "ShowCustomFields"), 10, 1); //Runs near the end of the user profile editing screen.
			add_action("edit_user_profile", array($this, "ShowCustomFields"), 10, 1); //Runs near the end of the user profile editing screen in the admin menus. 
			add_action("profile_update", array($this, "SaveCustomFields"), 10, 1);	//Runs when a user's profile is updated. Action function argument: user ID.
			add_action("user_register", array($this, "SaveAddedFields"), 10, 1); //Runs when a user's profile is first created. Action function argument: user ID. 
			add_filter("allow_password_reset", array($this, "filter_password_reset"), 10, 2);
			add_filter("update_user_metadata", array($this, "filter_update_user_metadata"), 10, 5);
			
			if ( $wp_version < 3.0 )
				add_action("admin_notices", array($this, "VersionWarning"), 10, 1); //Runs after the admin menu is printed to the screen. 
		}

		function InitL18n() {
			//Place your language file in the languages subfolder and name it "register-plus-redux-{language}.mo replace {language} with your language value from wp-config.php
			load_plugin_textdomain("register-plus-redux", false, dirname(plugin_basename(__FILE__)) . "/languages/" );
		}

		function InitOptions() {
			global $wpdb;
			// Added 10/01/10 no longer separating unverified users by type
			// can be removed once all users are past 3.6.12
			$unverified_users = $wpdb->get_results("SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key=\"admin_verification_user_login\"");
			if ( $unverified_users ) {
				foreach ( $unverified_users as $unverified_user ) {
					update_user_meta($unverified_user->user_id, "stored_user_login", $unverified_user->meta_value);
					delete_user_meta($unverified_user->user_id, "admin_verification_user_login");
				}
			}
			$unverified_users = $wpdb->get_results("SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key=\"email_verification_user_login\"");
			if ( $unverified_users ) {
				foreach ( $unverified_users as $unverified_user ) {
					update_user_meta($unverified_user->user_id, "stored_user_login", $unverified_user->meta_value);
					delete_user_meta($unverified_user->user_id, "email_verification_user_login");
				}
			}
			$default = $this->defaultOptions();
			if ( !get_option("register_plus_redux_options") ) {
				//Check if settings exist, add defaults if necessary
				add_option("register_plus_redux_options", $default);
			} else {
				//Check settings for new variables, add as necessary
				$options = get_option("register_plus_redux_options");
				foreach ( $default as $k => $v ) {
					if ( !isset($options[$k]) ) {
						$options[$k] = $v;
						$update = true;
					}
				}
				if ( !empty($update) ) update_option("register_plus_redux_options", $options);
			}
		}

		function InitDeleteExpiredUsers() {
			$options = get_option("register_plus_redux_options");
			if ( !empty($options["delete_unverified_users_after"]) ) {
				global $wpdb;
				$unverified_users = $wpdb->get_results("SELECT user_id FROM $wpdb->usermeta WHERE meta_key=\"stored_user_login\"");
				if ( !empty($unverified_users) ) {
					$options = get_option("register_plus_redux_options");
					$expirationdate = date("Ymd", strtotime("-".$options["delete_unverified_users_after"]." days"));
					if (!function_exists("wp_delete_user")) require_once(ABSPATH."/wp-admin/includes/user.php");
					foreach ( $unverified_users as $unverified_user ) {
						$user_info = get_userdata($unverified_user->user_id);
						if ( !empty($user_info->stored_user_login) && substr($user_info->user_login, 0, 11) == "unverified_") {
							if ( date("Ymd", strtotime($user_info->user_registered)) < $expirationdate ) {
								if ( !empty($user_info->email_verification_sent) ) {
									if ( date("Ymd", strtotime($user_info->email_verification_sent)) < $expirationdate ) {
										if ( !empty($user_info->email_verified) ) {
											if ( date("Ymd", strtotime($user_info->email_verified)) < $expirationdate ) {
												wp_delete_user($unverified_user->user_id);
											}
										} else {
											wp_delete_user($unverified_user->user_id);
										}
									}
								} else {
									wp_delete_user($unverified_user->user_id);
								}
							}
						}
					}
				}
			}
		}

		function AddPages() {
			global $wpdb;
			$options = get_option("register_plus_redux_options");
			$options_page = add_submenu_page("options-general.php", __("Register Plus Redux Settings", "register-plus-redux"), "Register Plus Redux", "manage_options", "register-plus-redux", array($this, "OptionsPage"));
			//$options_page = settings_page_register-plus-redux 
			add_action("admin_head-$options_page", array($this, "OptionsHead"), 10, 1);
			add_action("admin_footer-$options_page", array($this, "OptionsFoot"), 10, 1);
			add_filter("plugin_action_links_".plugin_basename(__FILE__), array($this, "filter_plugin_actions"), 10, 4);
			if ( !empty($options["verify_user_email"]) || !empty($options["verify_user_admin"]) || $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->usermeta WHERE meta_key=\"stored_user_login\"") )
				add_submenu_page("users.php", __("Unverified Users", "register-plus-redux"), __("Unverified Users", "register-plus-redux"), "promote_users", "unverified-users", array($this, "UnverifiedUsersPage"));
		}

		function OptionsHead() {
			wp_enqueue_script("jquery");
		}

		function OptionsFoot() {
			$options = get_option("register_plus_redux_options");
			?>
			<script type="text/javascript">
			function addInvitationCode() {
				jQuery("#invitation_code_bank")
					.append(jQuery("<div>")
						.attr("class", "invitation_code")
						.append(jQuery("<input>")
							.attr("type", "text")
							.attr("name", "invitation_code_bank[]")
							.attr("value", "")
						)
						.append("&nbsp;")
						.append(jQuery("<img>")
							.attr("src", "<?php echo plugins_url("images\delete.png", __FILE__); ?>")
							.attr("alt", "<?php esc_attr_e("Remove Code", "register-plus-redux"); ?>")
							.attr("title", "<?php esc_attr_e("Remove Code", "register-plus-redux"); ?>")
							.attr("class", "removeInvitationCode")
							.attr("style", "cursor: pointer;")
						)
					);
			}

			function addCustomField() {
				jQuery("#custom_fields").find("tbody")
					.append(jQuery("<tr>")
						.attr("valign", "center")
						.attr("class", "custom_field")
						.append(jQuery("<td>")
							.attr("style", "padding-top: 0px; padding-bottom: 0px; padding-left: 0px;")
							.append(jQuery("<input>")
								.attr("type", "text")
								.attr("name", "custom_field_name[]")
								.attr("style", "width: 100%;")
							)
						)
						.append(jQuery("<td>")
							.attr("style", "padding-top: 0px; padding-bottom: 0px;")
							.append(jQuery("<select>")
								.attr("name", "custom_field_type[]")
								.attr("class", "enableDisableOptions")
								.attr("style", "width: 100%;")
								.append("<option value=\"text\"><?php esc_attr_e("Text Field", "register-plus-redux"); ?></option>")
								.append("<option value=\"select\"><?php esc_attr_e("Select Field", "register-plus-redux"); ?></option>")
								.append("<option value=\"checkbox\"><?php esc_attr_e("Checkbox Fields", "register-plus-redux"); ?></option>")
								.append("<option value=\"radio\"><?php esc_attr_e("Radio Fields", "register-plus-redux"); ?></option>")
								.append("<option value=\"textarea\"><?php esc_attr_e("Text Area", "register-plus-redux"); ?></option>")
								.append("<option value=\"date\"><?php esc_attr_e("Date Field", "register-plus-redux"); ?></option>")
								.append("<option value=\"url\"><?php esc_attr_e("URL Field", "register-plus-redux"); ?></option>")
								.append("<option value=\"hidden\"><?php esc_attr_e("Hidden Field", "register-plus-redux"); ?></option>")
								.append("<option value=\"static\"><?php esc_attr_e("Static Text", "register-plus-redux"); ?></option>")
							)
						)
						.append(jQuery("<td>")
							.attr("style", "padding-top: 0px; padding-bottom: 0px;")
							.append(jQuery("<input>")
								.attr("type", "text")
								.attr("name", "custom_field_options[]")
								.attr("style", "width: 100%;")
							)
						)
						.append(jQuery("<td>")
							.attr("align", "center")
							.attr("style", "padding-top: 0px; padding-bottom: 0px;")
							.append(jQuery("<img>")
								.attr("src", "<?php echo plugins_url("images\help.png", __FILE__); ?>")
								.attr("title", "<?php esc_attr_e("No help available", "register-plus-redux"); ?>")
								.attr("class", "helpCustomField")
							)
						)
						.append(jQuery("<td>")
							.attr("align", "center")
							.attr("style", "padding-top: 0px; padding-bottom: 0px;")
							.append(jQuery("<input>")
								.attr("type", "checkbox")
								.attr("name", "show_on_profile[]")
								.attr("value", "1")
							)
						)
						.append(jQuery("<td>")
							.attr("align", "center")
							.attr("style", "padding-top: 0px; padding-bottom: 0px;")
							.append(jQuery("<input>")
								.attr("type", "checkbox")
								.attr("name", "show_on_registration[]")
								.attr("value", "1")
								.attr("class", "modifyNextCellInput")
							)
						)
						.append(jQuery("<td>")
							.attr("align", "center")
							.attr("style", "padding-top: 0px; padding-bottom: 0px;")
							.append(jQuery("<input>")
								.attr("type", "checkbox")
								.attr("name", "required_on_registration[]")
								.attr("value", "1")
								.attr("disabled", "disabled")
							)
						)
						.append(jQuery("<td>")
							.attr("align", "center")
							.attr("style", "padding-top: 0px; padding-bottom: 0px;")
							.append(jQuery("<img>")
								.attr("src", "<?php echo plugins_url("images\delete.png", __FILE__); ?>")
								.attr("alt", "<?php esc_attr_e("Remove Field", "register-plus-redux"); ?>")
								.attr("title", "<?php esc_attr_e("Remove Field", "register-plus-redux"); ?>")
								.attr("class", "removeCustomField")
								.attr("style", "cursor: pointer;")
							)
							.append("&nbsp;")
							.append(jQuery("<img>")
								.attr("src", "<?php echo plugins_url("images\arrow_up.png", __FILE__); ?>")
								.attr("alt", "<?php esc_attr_e("Move this Field Up", "register-plus-redux"); ?>")
								.attr("title", "<?php esc_attr_e("Move this Field Up", "register-plus-redux"); ?>")
								.attr("class", "up")
								.attr("style", "cursor: pointer;")
							)
							.append("&nbsp;")
							.append(jQuery("<img>")
								.attr("src", "<?php echo plugins_url("images\arrow_down.png", __FILE__); ?>")
								.attr("alt", "<?php esc_attr_e("Move this Field Down", "register-plus-redux"); ?>")
								.attr("title", "<?php esc_attr_e("Move this Field Down", "register-plus-redux"); ?>")
								.attr("class", "down")
								.attr("style", "cursor: pointer;")
							)
						)
					);
			}

			function updateUserMessagesSummary() {
				var from_name, from_email, subject, content_type, body, when;
				var vwhen = "";
				var vmsg = "";
				var msg = "";
				if ( !jQuery("#verify_user_email").attr("checked") ) {
					jQuery("#custom_verification_message").attr("disabled", "disabled");
					jQuery("#custom_verification_message").removeAttr("checked");
					jQuery("#custom_verification_message_settings").hide();
				} else {
					jQuery("#custom_verification_message").removeAttr("disabled");
					vwhen = "<?php _e("The following message will be sent when a user is registered:", "register-plus-redux"); ?>";
					from_name = "<?php echo $this->defaultOptions("verification_message_from_name"); ?>";
					from_email = "<?php echo $this->defaultOptions("verification_message_from_email"); ?>";
					subject = "<?php echo stripslashes($this->defaultOptions("verification_message_subject")); ?>";
					content_type = "text/plain";
					body = "<?php echo str_replace(array("\r", "\r\n", "\n"), "", nl2br(stripslashes($this->defaultOptions("verification_message_body")))); ?>";
					if ( jQuery("#custom_verification_message").attr("checked") ) {
						from_name = jQuery("#verification_message_from_name").val();
						from_email = jQuery("#verification_message_from_email").val();
						subject = jQuery("#verification_message_subject").val();
						if ( jQuery("#send_verification_message_in_html").attr("checked") ) content_type = "text/html";
						body = jQuery("#verification_message_body").val().replace(new RegExp( "\\n", "g" ), "<br />");
					}
					vmsg = "<?php _e("To: ", "register-plus-redux"); ?>" + "%user_email%<br />";
					vmsg = vmsg + "<?php _e("From: ", "register-plus-redux"); ?>" + from_name + " (" + from_email + ")<br />";
					vmsg = vmsg + "<?php _e("Subject: ", "register-plus-redux"); ?>" + subject + "<br />";
					vmsg = vmsg + "<?php _e("Content-Type: ", "register-plus-redux"); ?>" + content_type + "<br />";
					vmsg = "<p style=\"font-size: 11px; display: block; width: 50%; background-color: #efefef; padding: 8px 10px; border: solid 1px #dfdfdf; margin: 1px; overflow:auto;\">" + vmsg + body + "</p><br />";
				}
				if ( jQuery("#disable_user_message_registered").attr("checked") && jQuery("#disable_user_message_created").attr("checked") ) {
					jQuery("#custom_user_message").attr("disabled", "disabled");
					jQuery("#custom_user_message").removeAttr("checked");
					jQuery("#custom_user_message_settings").hide();
					when = "<?php _e("No message will be sent to user whether they are registered or created by an administrator.", "register-plus-redux"); ?>";
				} else {
					jQuery("#custom_user_message").removeAttr("disabled");
					when = "<?php _e("The following message will be sent when a user is ", "register-plus-redux"); ?>";
					if ( !jQuery("#disable_user_message_registered").attr("checked") ) when = when + "<?php _e("registered", "register-plus-redux"); ?>";
					if ( !jQuery("#disable_user_message_registered").attr("checked") && !jQuery("#disable_user_message_created").attr("checked") ) when = when + "<?php _e(" or ", "register-plus-redux"); ?>";
					if ( !jQuery("#disable_user_message_created").attr("checked") ) when = when + "<?php _e("created", "register-plus-redux"); ?>";
					if ( jQuery("#verify_user_email").attr("checked") || jQuery("#verify_user_admin").attr("checked") ) when = when + "<?php _e(" after ", "register-plus-redux"); ?>";
					if ( jQuery("#verify_user_email").attr("checked"))
						when = when + "<?php _e("the user has verified their email address", "register-plus-redux"); ?>";
					if ( jQuery("#verify_user_email").attr("checked") && jQuery("#verify_user_admin").attr("checked") ) when = when + "<?php _e(" and/or ", "register-plus-redux"); ?>";
					if ( jQuery("#verify_user_admin").attr("checked"))
						when = when + "<?php _e("an administrator has approved the new user", "register-plus-redux"); ?>";
					when = when + ":";
					from_name = "<?php echo $this->defaultOptions("user_message_from_name"); ?>";
					from_email = "<?php echo $this->defaultOptions("user_message_from_email"); ?>";
					subject = "<?php echo stripslashes($this->defaultOptions("user_message_subject")); ?>";
					content_type = "text/plain";
					body = "<?php echo str_replace(array("\r", "\r\n", "\n"), "", nl2br(stripslashes($this->defaultOptions("user_message_body")))); ?>";
					if ( jQuery("#custom_user_message").attr("checked") ) {
						from_name = jQuery("#user_message_from_name").val();
						from_email = jQuery("#user_message_from_email").val();
						subject = jQuery("#user_message_subject").val();
						if ( jQuery("#send_user_message_in_html").attr("checked") ) content_type = "text/html";
						body = jQuery("#user_message_body").val().replace(new RegExp( "\\n", "g" ), "<br />");
					}
					msg = "<?php _e("To: ", "register-plus-redux"); ?>" + "%user_email%<br />";
					msg = msg + "<?php _e("From: ", "register-plus-redux"); ?>" + from_name + " (" + from_email + ")<br />";
					msg = msg + "<?php _e("Subject: ", "register-plus-redux"); ?>" + subject + "<br />";
					msg = msg + "<?php _e("Content-Type: ", "register-plus-redux"); ?>" + content_type + "<br />";
					msg = "<p style=\"font-size: 11px; display: block; width: 50%; background-color: #efefef; padding: 8px 10px; border: solid 1px #dfdfdf; margin: 1px; overflow:auto;\">" + msg + body + "</p>";
				}
				jQuery("#user_message_summary").html(vwhen + vmsg + when + msg);
			}

			function updateAdminMessageSummary() {
				var from_name, from_email, subject, content_type, body, when;
				var msg = "";
				if ( jQuery("#disable_admin_message_registered").attr("checked") && jQuery("#disable_admin_message_created").attr("checked") ) {
					jQuery("#custom_admin_message").attr("disabled", "disabled");
					jQuery("#custom_admin_message").removeAttr("checked");
					jQuery("#custom_admin_message_settings").hide();
					when = "<?php _e("No message will be sent to administrator whether a user is registered or created.", "register-plus-redux"); ?>";
				} else {
					jQuery("#custom_admin_message").removeAttr("disabled");
					when = "<?php _e("The following message will be sent when a user is ", "register-plus-redux"); ?>";
					if ( !jQuery("#disable_admin_message_registered").attr("checked") ) when = when + "<?php _e("registered", "register-plus-redux"); ?>";
					if ( !jQuery("#disable_admin_message_registered").attr("checked") && !jQuery("#disable_admin_message_created").attr("checked") ) when = when + "<?php _e(" or ", "register-plus-redux"); ?>";
					if ( !jQuery("#disable_admin_message_created").attr("checked") ) when = when + "<?php _e("created", "register-plus-redux"); ?>";
					when = when + ":";
					from_name = "<?php echo $this->defaultOptions("admin_message_from_name"); ?>";
					from_email = "<?php echo $this->defaultOptions("admin_message_from_email"); ?>";
					subject = "<?php echo stripslashes($this->defaultOptions("admin_message_subject")); ?>";
					content_type = "text/plain";
					body = "<?php echo str_replace(array("\r", "\r\n", "\n"), "", nl2br(stripslashes($this->defaultOptions("admin_message_body")))); ?>";
					if ( jQuery("#custom_admin_message").attr("checked") ) {
						from_name = jQuery("#admin_message_from_name").val();
						from_email = jQuery("#admin_message_from_email").val();
						subject = jQuery("#admin_message_subject").val();
						if ( jQuery("#send_admin_message_in_html").attr("checked") ) content_type = "text/html";
						body = jQuery("#admin_message_body").val().replace(new RegExp( "\\n", "g" ), "<br />");
					}
					msg = "<?php _e("To: ", "register-plus-redux"); echo get_option("admin_email"); ?><br />";
					msg = msg + "<?php _e("From: ", "register-plus-redux"); ?>" + from_name + " (" + from_email + ")<br />";
					msg = msg + "<?php _e("Subject: ", "register-plus-redux"); ?>" + subject + "<br />";
					msg = msg + "<?php _e("Content-Type: ", "register-plus-redux"); ?>" + content_type + "<br />";
					msg = "<p style=\"font-size: 11px; display: block; width: 50%; background-color: #efefef; padding: 8px 10px; border: solid 1px #dfdfdf; margin: 1px; overflow:auto;\">" + msg + body + "</p>";
				}
				jQuery("#admin_message_summary").html(when + msg);
			}

			jQuery(document).ready(function() {
				//alert("document ready");

				<?php if ( empty($options["verify_user_email"]) ) echo "\njQuery(\"#verify_user_email_settings\").hide();"; ?>
				<?php if ( empty($options["verify_user_admin"]) ) echo "\njQuery(\"#verify_user_admin_settings\").hide();"; ?>
				<?php if ( empty($options["user_set_password"]) ) echo "\njQuery(\"#password_settings\").hide();"; ?>
				<?php if ( empty($options["show_password_meter"]) ) echo "\njQuery(\"#meter_settings\").hide();"; ?>
				<?php if ( empty($options["enable_invitation_code"]) ) echo "\njQuery(\"#invitation_code_settings\").hide();"; ?>
				<?php if ( empty($options["show_disclaimer"]) ) echo "\njQuery(\"#disclaimer_settings\").hide();"; ?>
				<?php if ( empty($options["show_license"]) ) echo "\njQuery(\"#license_settings\").hide();"; ?>
				<?php if ( empty($options["show_privacy_policy"]) ) echo "\njQuery(\"#privacy_policy_settings\").hide();"; ?>
				<?php if ( empty($options["custom_user_message"]) ) echo "\njQuery(\"#custom_user_message_settings\").hide();"; ?>
				<?php if ( empty($options["custom_verification_message"]) ) echo "\njQuery(\"#custom_verification_message_settings\").hide();"; ?>
				<?php if ( empty($options["custom_admin_message"]) ) echo "\njQuery(\"#custom_admin_message_settings\").hide();"; ?>
				jQuery(".disabled").hide();

				jQuery(".showHideSettings").bind("click", function() {
					if ( jQuery(this).attr("checked") )
						jQuery(this).parent().nextAll("div").first().show();
					else
						jQuery(this).parent().nextAll("div").first().hide();
				});

				jQuery("#addInvitationCode").bind("click", function() {
					addInvitationCode();
				});

				jQuery(".removeInvitationCode").live("click", function() {
					jQuery(this).parent().remove();
				});

				jQuery(".enableDisableText").live("click", function() {
					if ( jQuery(this).attr("checked") )
						jQuery(this).parent().parent().next().find("input").removeAttr("readonly");
					else
						jQuery(this).parent().parent().next().find("input").attr("readonly", "readonly");
				});

				jQuery("#addCustomField").bind("click", function() {
					addCustomField();
				});

				jQuery(".removeCustomField").live("click", function() {
					jQuery(this).parent().parent().remove();
				});

				jQuery(".enableDisableOptions").live("change", function() {
					if ( jQuery(this).val() == "text" || jQuery(this).val() == "select" || jQuery(this).val() == "checkbox" || jQuery(this).val() == "radio" || jQuery(this).val() == "static" )
						jQuery(this).parent().next().find("input").removeAttr("readonly");
					else
						jQuery(this).parent().next().find("input").attr("readonly", "readonly");
				});

				jQuery(".modifyNextCellInput").live("click", function() {
					if ( jQuery(this).attr("checked") )
						jQuery(this).parent().next().find("input").removeAttr("disabled");
					else {
						jQuery(this).parent().next().find("input").removeAttr("checked");
						jQuery(this).parent().next().find("input").attr("disabled", "disabled");
					}
				});

				jQuery(".up,.down").live("click", function() {
					var row = jQuery(this).parents("tr:first");
					if ( jQuery(this).is(".up") ) {
						row.insertBefore(row.prev());
					} else {
						row.insertAfter(row.next());
					}
				});
				
				jQuery("#verify_user_email,#verify_user_admin,#disable_user_message_registered,#disable_user_message_created,#custom_user_message,#user_message_from_name,#user_message_from_email,#user_message_subject,#user_message_body,#send_user_message_in_html,#custom_verification_message,#verification_message_from_name,#verification_message_from_email,#verification_message_subject,#verification_message_body,#verification_admin_message_in_html").change(function() {
					updateUserMessagesSummary();
				});

				jQuery("#disable_admin_message_registered,#disable_admin_message_created,#custom_admin_message,#admin_message_from_name,#admin_message_from_email,#admin_message_subject,#admin_message_body,#send_admin_message_in_html").change(function() {
					updateAdminMessageSummary();
				});

				updateUserMessagesSummary();
				updateAdminMessageSummary();
			});
			</script>
			<?php
		}

		function OptionsPage() {
			if ( isset($_POST["update_settings"]) ) {
				check_admin_referer("register-plus-redux-update-settings");
				$this->UpdateSettings();
				echo "<div id=\"message\" class=\"updated\"><p>", __("Settings Saved", "register-plus-redux"), "</p></div>";
			}
			?>
			<div class="wrap">
			<h2><?php _e("Register Plus Redux Settings", "register-plus-redux") ?></h2>
			<form enctype="multipart/form-data" method="post">
				<?php wp_nonce_field("register-plus-redux-update-settings"); ?>
				<?php $options = get_option("register_plus_redux_options"); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e("Custom Logo URL", "register-plus-redux"); ?></th>
						<td>
							<input type="text" name="custom_logo_url" id="custom_logo_url" value="<?php echo $options["custom_logo_url"]; ?>" style="width: 60%;" /><br />
							<?php _e("Upload a new logo:", "register-plus-redux"); ?>&nbsp;<input type="file" name="upload_custom_logo" id="upload_custom_logo" value="1" /><br />
							<?php _e("You must Save Changes to upload logo.", "register-plus-redux"); ?><br />
							<?php _e("Custom Logo will be shown on Registration and Login Forms in place of the default Wordpress logo. For the best results custom logo should not exceed 350px width.", "register-plus-redux"); ?>
							<?php if ( !empty($options["custom_logo_url"]) ) { ?>
								<br /><img src="<?php echo $options["custom_logo_url"]; ?>" /><br />
								<?php if ( empty($options["disable_url_fopen"]) ) list($custom_logo_width, $custom_logo_height) = getimagesize($options["custom_logo_url"]); ?>
								<?php if ( empty($options["disable_url_fopen"]) ) echo $custom_logo_width, "x", $custom_logo_height, "<br />\n"; ?>
								<label><input type="checkbox" name="remove_logo" value="1" />&nbsp;<?php _e("Remove Logo", "register-plus-redux"); ?></label><br />
								<?php _e("You must Save Changes to remove logo.", "register-plus-redux"); ?>
							<?php } ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Email Verification", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="verify_user_email" id="verify_user_email" class="showHideSettings" value="1" <?php if ( !empty($options["verify_user_email"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Verify all new users email address...", "register-plus-redux"); ?></label><br />
							<?php _e("A verification code will be sent to any new users email address, new users will not be able to login or reset their password until they have completed the verification process. Administrators may authorize new users from the Unverified Users Page at their own discretion.", "register-plus-redux"); ?>
							<div id="verify_user_email_settings">
								<br /><?php _e("The following message will be shown to users after registering. You may include HTML in this message.", "register-plus-redux"); ?><br />
								<textarea name="message_verify_user_email" rows="2" style="width: 60%; display: block;"><?php echo stripslashes($options["message_verify_user_email"]); ?></textarea>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Admin Verification", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="verify_user_admin" id="verify_user_admin" class="showHideSettings" value="1" <?php if ( !empty($options["verify_user_admin"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Moderate all new user registrations...", "register-plus-redux"); ?></label><br />
							<?php _e("New users will not be able to login or reset their password until they have been authorized by an administrator from the Unverified Users Page. If both verification options are enabled, users will not be able to login until an administrator authorizes them, regardless of whether they complete the email verification process.", "register-plus-redux"); ?>
							<div id="verify_user_admin_settings">
								<br /><?php _e("The following message will be shown to users after registering (or verifying their email if both verification options are enabled). You may include HTML in this message.", "register-plus-redux"); ?><br />
								<textarea name="message_verify_user_admin" rows="2" style="width: 60%; display: block;"><?php echo stripslashes($options["message_verify_user_admin"]); ?></textarea>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Grace Period", "register-plus-redux"); ?></th>
						<td>
							<label><input type="text" name="delete_unverified_users_after" id="delete_unverified_users_after" style="width:50px;" value="<?php echo $options["delete_unverified_users_after"]; ?>" />&nbsp;<?php _e("days", "register-plus-redux"); ?></label><br />
							<?php _e("All unverified users will automatically be deleted after the Grace Period specified, to disable this process enter 0 to never automatically delete unverified users.", "register-plus-redux"); ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Registration Redirect", "register-plus-redux"); ?></th>
						<td>
							<input type="text" name="registration_redirect" id="registration_redirect" value="<?php echo $options["registration_redirect"]; ?>" style="width: 60%;" /><br />
							<?php echo sprintf(__("By default, after registering, users will be sent to %s/wp-login.php?checkemail=registered, leave this value empty if you do not wish to change this behavior. You may enter another address here, however, if that address is not on the same domain, Wordpress will ignore the redirect.", "register-plus-redux"), home_url()); ?><br />
						</td>
					</tr>
					<tr valign="top" class="disabled">
						<th scope="row"><?php _e("Verification Redirect", "register-plus-redux"); ?></th>
						<td>
							<input type="text" name="verification_redirect" id="verification_redirect" value="<?php echo $options["verification_redirect"]; ?>" style="width: 60%;" /><br />
							<?php echo sprintf(__("By default, after verifing, users will be sent to %s/wp-login.php, leave this value empty if you do not wish to change this behavior. You may enter another address here, however, if that addresses is not on the same domain, Wordpress will ignore the redirect.", "register-plus-redux"), home_url()); ?><br />
						</td>
					</tr>
				</table>
				<h3 class="title"><?php _e("Registration Page", "register-plus-redux"); ?></h3>
				<p><?php _e("Select which fields to show on the Registration Page. Users will not be able to register without completing any fields marked required.", "register-plus-redux"); ?></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e("Use Email as Username", "register-plus-redux"); ?></th>
						<td><label><input type="checkbox" name="username_is_email" value="1" <?php if ( !empty($options["username_is_email"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("New users will not be asked to enter a username, instead their email address will be used as their username.", "register-plus-redux"); ?></label></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Confirm Email", "register-plus-redux"); ?></th>
						<td><label><input type="checkbox" name="double_check_email" value="1" <?php if ( !empty($options["double_check_email"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Require new users to enter e-mail address twice during registration.", "register-plus-redux"); ?></label></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Profile Fields", "register-plus-redux"); ?></th>
						<td>
							<table>
								<thead valign="top">
									<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"></td>
									<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><?php _e("Show", "register-plus-redux"); ?></td>
									<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><?php _e("Require", "register-plus-redux"); ?></td>
								</thead>
								<tbody>
									<?php if ( !is_array($options["show_fields"]) ) $options["show_fields"] = array(); ?>
									<?php if ( !is_array($options["required_fields"]) ) $options["required_fields"] = array(); ?>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("First Name", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="first_name" <?php if ( in_array("first_name", $options["show_fields"]) ) echo "checked=\"checked\""; ?> class="modifyNextCellInput" /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="first_name" <?php if ( in_array("first_name", $options["required_fields"]) ) echo "checked=\"checked\""; ?> <?php if ( !in_array("first_name", $options["show_fields"]) ) echo "disabled=\"disabled\""; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("Last Name", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="last_name" <?php if ( in_array("last_name", $options["show_fields"]) ) echo "checked=\"checked\""; ?> class="modifyNextCellInput" /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="last_name" <?php if ( in_array("last_name", $options["required_fields"]) ) echo "checked=\"checked\""; ?> <?php if ( !in_array("last_name", $options["show_fields"]) ) echo "disabled=\"disabled\""; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("Website", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="user_url" <?php if ( in_array("user_url", $options["show_fields"]) ) echo "checked=\"checked\""; ?> class="modifyNextCellInput" /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="user_url" <?php if ( in_array("user_url", $options["required_fields"]) ) echo "checked=\"checked\""; ?> <?php if ( !in_array("user_url", $options["show_fields"]) ) echo "disabled=\"disabled\""; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("AIM", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="aim" <?php if ( in_array("aim", $options["show_fields"]) ) echo "checked=\"checked\""; ?> class="modifyNextCellInput" /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="aim" <?php if ( in_array("aim", $options["required_fields"]) ) echo "checked=\"checked\""; ?> <?php if ( !in_array("aim", $options["show_fields"]) ) echo "disabled=\"disabled\""; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("Yahoo IM", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="yahoo" <?php if ( in_array("yahoo", $options["show_fields"]) ) echo "checked=\"checked\""; ?> class="modifyNextCellInput" /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="yahoo" <?php if ( in_array("yahoo", $options["required_fields"]) ) echo "checked=\"checked\""; ?> <?php if ( !in_array("yahoo", $options["show_fields"]) ) echo "disabled=\"disabled\""; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("Jabber / Google Talk", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="jabber" <?php if ( in_array("jabber", $options["show_fields"]) ) echo "checked=\"checked\""; ?> class="modifyNextCellInput" /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="jabber" <?php if ( in_array("jabber", $options["required_fields"]) ) echo "checked=\"checked\""; ?> <?php if ( !in_array("jabber", $options["show_fields"]) ) echo "disabled=\"disabled\""; ?> /></td>
									</tr>
									<tr valign="center">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("About Yourself", "register-plus-redux"); ?></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="show_fields[]" value="about" <?php if ( in_array("about", $options["show_fields"]) ) echo "checked=\"checked\""; ?> class="modifyNextCellInput" /></td>
										<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><input type="checkbox" name="required_fields[]" value="about" <?php if ( in_array("about", $options["required_fields"]) ) echo "checked=\"checked\""; ?> <?php if ( !in_array("about", $options["show_fields"]) ) echo "disabled=\"disabled\""; ?> /></td>
									</tr>
								</tbody>
							</table>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("User Set Password", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="user_set_password" id="user_set_password" value="1" <?php if ( !empty($options["user_set_password"]) ) echo "checked=\"checked\""; ?> class="showHideSettings" />&nbsp;<?php _e("Require new users enter a password during registration...", "register-plus-redux"); ?></label><br />
							<div id="password_settings">
								<label><?php _e("Minimum password length: ","register-plus-redux"); ?><input type="text" name="min_password_length" id="min_password_length" style="width:50px;" value="<?php echo $options["min_password_length"]; ?>" /></label><br />
								<label><input type="checkbox" name="show_password_meter" id="show_password_meter" value="1" <?php if ( !empty($options["show_password_meter"]) ) echo "checked=\"checked\""; ?> class="showHideSettings" />&nbsp;<?php _e("Show password strength meter...","register-plus-redux"); ?></label>
								<div id="meter_settings">
									<table>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="message_empty_password"><?php _e("Empty", "register-plus-redux"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_empty_password" value="<?php echo stripslashes($options["message_empty_password"]); ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="message_short_password"><?php _e("Short", "register-plus-redux"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_short_password" value="<?php echo stripslashes($options["message_short_password"]); ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="message_bad_password"><?php _e("Bad", "register-plus-redux"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_bad_password" value="<?php echo stripslashes($options["message_bad_password"]); ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="message_good_password"><?php _e("Good", "register-plus-redux"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_good_password" value="<?php echo stripslashes($options["message_good_password"]); ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="message_strong_password"><?php _e("Strong", "register-plus-redux"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_strong_password" value="<?php echo stripslashes($options["message_strong_password"]); ?>" /></td>
										</tr>
										<tr>
											<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="message_mismatch_password"><?php _e("Mismatch", "register-plus-redux"); ?></label></td>
											<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="message_mismatch_password" value="<?php echo stripslashes($options["message_mismatch_password"]); ?>" /></td>
										</tr>
									</table>
								</div>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Invitation Code", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="enable_invitation_code" id="enable_invitation_code" value="1" <?php if ( !empty($options["enable_invitation_code"]) ) echo "checked=\"checked\""; ?> class="showHideSettings" />&nbsp;<?php _e("Use invitation codes to track or authorize new user registration...", "register-plus-redux"); ?></label>
							<div id="invitation_code_settings">
								<label><input type="checkbox" name="require_invitation_code" value="1" <?php if ( !empty($options["require_invitation_code"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Require new user enter one of the following invitation codes to register.", "register-plus-redux"); ?></label><br />
								<label><input type="checkbox" name="invitation_code_case_sensitive" value="1" <?php if ( !empty($options["invitation_code_case_sensitive"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Enforce case-sensitivity of invitation codes.", "register-plus-redux"); ?></label><br />
								<label><input type="checkbox" name="enable_invitation_tracking_widget" value="1" <?php if ( !empty($options["enable_invitation_tracking_widget"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Show Invitation Code Tracking widget on Dashboard.", "register-plus-redux"); ?></label>
								<div id="invitation_code_bank">
								<?php
									$invitation_codes = $options["invitation_code_bank"];
									if ( !is_array($options["invitation_code_bank"]) ) $options["invitation_code_bank"] = array();
									foreach ( $options["invitation_code_bank"] as $invitation_code )
										echo "\n<div class=\"invitation_code\"><input type=\"text\" name=\"invitation_code_bank[]\" value=\"$invitation_code\" />&nbsp;<img src=\"", plugins_url("images\delete.png", __FILE__), "\" alt=\"", __("Remove Code", "register-plus-redux"), "\" title=\"", __("Remove Code", "register-plus-redux"), "\" class=\"removeInvitationCode\" style=\"cursor: pointer;\" /></div>";
								?>
								</div>
								<img src="<?php echo plugins_url("images\add.png", __FILE__); ?>" alt="<?php esc_attr_e("Add Code", "register-plus-redux") ?>" title="<?php esc_attr_e("Add Code", "register-plus-redux") ?>" id="addInvitationCode" style="cursor: pointer;" />&nbsp;<?php _e("Add a new invitation code", "register-plus-redux") ?><br />
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Disclaimer", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="show_disclaimer" id="show_disclaimer" value="1" <?php if ( !empty($options["show_disclaimer"]) ) echo "checked=\"checked\""; ?> class="showHideSettings" />&nbsp;<?php _e("Show Disclaimer during registration...", "register-plus-redux"); ?></label>
							<div id="disclaimer_settings">
								<table width="60%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px; width: 40%;">
											<label for="message_disclaimer_title"><?php _e("Disclaimer Title", "register-plus-redux"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_disclaimer_title" value="<?php echo stripslashes($options["message_disclaimer_title"]); ?>" style="width: 100%;" />
										</td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="message_disclaimer"><?php _e("Disclaimer Content", "register-plus-redux"); ?></label><br />
											<textarea name="message_disclaimer" style="width: 100%; height: 160px; display: block;"><?php echo stripslashes($options["message_disclaimer"]); ?></textarea>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label><input type="checkbox" name="require_disclaimer_agree" class="enableDisableText" value="1" <?php if ( !empty($options["require_disclaimer_agree"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Require Agreement", "register-plus-redux"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_disclaimer_agree" value="<?php echo stripslashes($options["message_disclaimer_agree"]); ?>" <?php if ( empty($options["require_disclaimer_agree"]) ) echo "readonly=\"readonly\""; ?> style="width: 100%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("License Agreement", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="show_license" id="show_license" value="1" <?php if ( !empty($options["show_license"]) ) echo "checked=\"checked\""; ?> class="showHideSettings" />&nbsp;<?php _e("Show License Agreement during registration...", "register-plus-redux"); ?></label>
							<div id="license_settings">
								<table width="60%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px; width: 40%;">
											<label for="message_license_title"><?php _e("License Agreement Title", "register-plus-redux"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_license_title" value="<?php echo stripslashes($options["message_license_title"]); ?>" style="width: 100%;" />
										</td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="message_license"><?php _e("License Agreement Content", "register-plus-redux"); ?></label><br />
											<textarea name="message_license" style="width: 100%; height: 160px; display: block;"><?php echo stripslashes($options["message_license"]); ?></textarea>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label><input type="checkbox" name="require_license_agree" class="enableDisableText" value="1" <?php if ( !empty($options["require_license_agree"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Require Agreement", "register-plus-redux"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_license_agree" value="<?php echo stripslashes($options["message_license_agree"]); ?>" <?php if ( empty($options["require_license_agree"]) ) echo "readonly=\"readonly\""; ?> style="width: 100%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Privacy Policy", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="show_privacy_policy" id="show_privacy_policy" value="1" <?php if ( !empty($options["show_privacy_policy"]) ) echo "checked=\"checked\""; ?> class="showHideSettings" />&nbsp;<?php _e("Show Privacy Policy during registration...", "register-plus-redux"); ?></label>
							<div id="privacy_policy_settings">
								<table width="60%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px; width: 40%;">
											<label for="message_privacy_policy_title"><?php _e("Privacy Policy Title", "register-plus-redux"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_privacy_policy_title" value="<?php echo stripslashes($options["message_privacy_policy_title"]); ?>" style="width: 100%;" />
										</td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="message_privacy_policy"><?php _e("Privacy Policy Content", "register-plus-redux"); ?></label><br />
											<textarea name="message_privacy_policy" style="width: 100%; height: 160px; display: block;"><?php echo stripslashes($options["message_privacy_policy"]); ?></textarea>
										</td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label><input type="checkbox" name="require_privacy_policy_agree" class="enableDisableText" value="1" <?php if ( !empty($options["require_privacy_policy_agree"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Require Agreement", "register-plus-redux"); ?></label>
										</td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="message_privacy_policy_agree" value="<?php echo stripslashes($options["message_privacy_policy_agree"]); ?>" <?php if ( empty($options["require_privacy_policy_agree"]) ) echo "readonly=\"readonly\""; ?> style="width: 100%;" />
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Use Default Style Rules", "register-plus-redux"); ?></th>
						<td><label><input type="checkbox" name="default_css" value="1" <?php if ( !empty($options["default_css"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Apply default Wordpress 3.0.1 styling to all fields.", "register-plus-redux"); ?></label></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Required Fields Style Rules", "register-plus-redux"); ?></th>
						<td><input type="text" name="required_fields_style" value="<?php echo $options["required_fields_style"]; ?>" style="width: 60%;" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Required Fields Asterisk", "register-plus-redux"); ?></th>
						<td><label><input type="checkbox" name="required_fields_asterisk" value="1" <?php if ( !empty($options["required_fields_asterisk"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Add asterisk to left of all required field's name.", "register-plus-redux"); ?></label></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("Starting Tabindex", "register-plus-redux"); ?></th>
						<td>
							<input type="text" name="starting_tabindex" style="width:50px;" value="<?php echo $options["starting_tabindex"]; ?>" /><br />
							<?php _e("The first field added will have this tabindex, the tabindex will increment by 1 for each additional field. Enter 0 to remove all tabindex's.", "register-plus-redux"); ?>
						</td>
					</tr>
				</table>
				<h3 class="title"><?php _e("Additional Fields", "register-plus-redux"); ?></h3>
				<p><?php _e("Enter additional fields to show on the User Profile and/or Registration Pages. Additional fields will be shown after existing profile fields on User Profile, and after selected profile fields on Registration Page but before Password, Invitation Code, Disclaimer, License Agreement, or Privacy Policy (if any of those fields are enabled). Options must be entered for Select, Checkbox, and Radio fields. Options should be entered with commas separating each possible value. For example, a Radio field named \"Gender\" could have the following options, \"Male,Female\".", "register-plus-redux"); ?></p>
				<table id="custom_fields" style="width: 80%;">
					<thead valign="top">
						<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><?php _e("Name", "register-plus-redux"); ?></td>
						<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e("Type", "register-plus-redux"); ?></td>
						<td style="padding-top: 0px; padding-bottom: 0px;"><?php _e("Options", "register-plus-redux"); ?></td>
						<td align="center" style="padding-top: 0px; padding-bottom: 0px;"></td>
						<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><?php _e("Profile", "register-plus-redux"); ?></td>
						<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><?php _e("Registration", "register-plus-redux"); ?></td>
						<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><?php _e("Require", "register-plus-redux"); ?></td>
						<td align="center" style="padding-top: 0px; padding-bottom: 0px;"><?php _e("Actions", "register-plus-redux"); ?></td>
					</thead>
					<tbody>
						<?php
						$custom_fields = get_option("register_plus_redux_custom_fields");
						if ( !is_array($custom_fields) ) $custom_fields = array();
						foreach ( $custom_fields as $k => $v ) {
							echo "\n<tr valign=\"center\" class=\"custom_field\">";
							echo "\n	<td style=\"padding-top: 0px; padding-bottom: 0px;\"><input type=\"text\" name=\"custom_field_name[$k]\" value=\"", stripslashes($v["custom_field_name"]), "\" style=\"width: 100%;\" /></td>";
							echo "\n	<td style=\"padding-top: 0px; padding-bottom: 0px;\">";
							echo "\n		<select name=\"custom_field_type[$k]\" class=\"enableDisableOptions\" style=\"width: 100%;\">";
							echo "\n			<option value=\"text\""; if ( $v["custom_field_type"] == "text" ) echo " selected=\"selected\""; echo ">", __("Text Field", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"select\""; if ( $v["custom_field_type"] == "select" ) echo " selected=\"selected\""; echo ">", __("Select Field", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"checkbox\""; if ( $v["custom_field_type"] == "checkbox" ) echo " selected=\"selected\""; echo ">", __("Checkbox Fields", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"radio\""; if ( $v["custom_field_type"] == "radio" ) echo " selected=\"selected\""; echo ">", __("Radio Fields", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"textarea\""; if ( $v["custom_field_type"] == "textarea" ) echo " selected=\"selected\""; echo ">", __("Text Area", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"date\""; if ( $v["custom_field_type"] == "date" ) echo " selected=\"selected\""; echo ">", __("Date Field", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"url\""; if ( $v["custom_field_type"] == "url" ) echo " selected=\"selected\""; echo ">", __("URL Field", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"hidden\""; if ( $v["custom_field_type"] == "hidden" ) echo " selected=\"selected\""; echo ">", __("Hidden Field", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"static\""; if ( $v["custom_field_type"] == "static" ) echo " selected=\"selected\""; echo ">", __("Static Text", "register-plus-redux"), "</option>";
							echo "\n		</select>";
							echo "\n	</td>";
							echo "\n	<td style=\"padding-top: 0px; padding-bottom: 0px;\"><input type=\"text\" name=\"custom_field_options[$k]\" value=\"", stripslashes($v["custom_field_options"]), "\""; if ( $v["custom_field_type"] != "text" && $v["custom_field_type"] != "select" && $v["custom_field_type"] != "checkbox" && $v["custom_field_type"] != "radio" && $v["custom_field_type"] != "static" ) echo " readonly=\"readonly\""; echo " style=\"width: 100%;\" /></td>";
							echo "\n	<td align=\"center\" style=\"padding-top: 0px; padding-bottom: 0px;\"><img src=\"", plugins_url("images\help.png", __FILE__), "\" title=\"", __("No help available", "register-plus-redux"), "\" class=\"helpCustomField\" /></td>";
							echo "\n	<td align=\"center\" style=\"padding-top: 0px; padding-bottom: 0px;\"><input type=\"checkbox\" name=\"show_on_profile[$k]\" value=\"1\""; if ( !empty($v["show_on_profile"]) ) echo " checked=\"checked\""; echo " /></td>";
							echo "\n	<td align=\"center\" style=\"padding-top: 0px; padding-bottom: 0px;\"><input type=\"checkbox\" name=\"show_on_registration[$k]\" value=\"1\""; if ( !empty($v["show_on_registration"]) ) echo " checked=\"checked\""; echo " class=\"modifyNextCellInput\" /></td>";
							echo "\n	<td align=\"center\" style=\"padding-top: 0px; padding-bottom: 0px;\"><input type=\"checkbox\" name=\"required_on_registration[$k]\" value=\"1\""; if ( !empty($v["required_on_registration"]) ) echo " checked=\"checked\""; if ( empty($v["show_on_registration"]) ) echo " disabled=\"disabled\""; echo " /></td>";
							echo "\n	<td align=\"center\" style=\"padding-top: 0px; padding-bottom: 0px;\">";
							echo "\n	<img src=\"", plugins_url("images\delete.png", __FILE__), "\" alt=\"", __("Remove Field", "register-plus-redux"), "\" title=\"", __("Remove Field", "register-plus-redux"), "\" class=\"removeCustomField\" style=\"cursor: pointer;\" />";
							echo "\n	<img src=\"", plugins_url("images\arrow_up.png", __FILE__), "\" alt=\"", __("Move this Field Up", "register-plus-redux"), "\" title=\"", __("Move this Field Up", "register-plus-redux"), "\" class=\"up\" style=\"cursor: pointer;\" />";
							echo "\n	<img src=\"", plugins_url("images\arrow_down.png", __FILE__), "\" alt=\"", __("Move this Field Down", "register-plus-redux"), "\" title=\"", __("Move this Field Down", "register-plus-redux"), "\" class=\"down\" style=\"cursor: pointer;\" />";
							echo "\n	</td>";
							echo "\n</tr>";
						}
						if ( empty($custom_fields) ) {
							echo "\n<tr valign=\"center\" class=\"custom_field\">";
							echo "\n	<td style=\"padding-top: 0px; padding-bottom: 0px; padding-left: 0px;\"><input type=\"text\" name=\"custom_field_name[]\" value=\"\" style=\"width: 100%;\"/></td>";
							echo "\n	<td style=\"padding-top: 0px; padding-bottom: 0px;\">";
							echo "\n		<select name=\"custom_field_type[]\" class=\"enableDisableOptions\" style=\"width: 100%;\">";
							echo "\n			<option value=\"text\">", __("Text Field", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"select\">", __("Select Field", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"checkbox\">", __("Checkbox Fields", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"radio\">", __("Radio Fields", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"textarea\">", __("Text Area", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"date\">", __("Date Field", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"url\">", __("URL Field", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"hidden\">", __("Hidden Field", "register-plus-redux"), "</option>";
							echo "\n			<option value=\"static\">", __("Static Text", "register-plus-redux"), "</option>";
							echo "\n		</select>";
							echo "\n	</td>";
							echo "\n	<td style=\"padding-top: 0px; padding-bottom: 0px;\"><input type=\"text\" name=\"custom_field_options[]\" value=\"\" style=\"width: 100%;\"/></td>";
							echo "\n	<td align=\"center\" style=\"padding-top: 0px; padding-bottom: 0px;\"><img src=\"", plugins_url("images\help.png", __FILE__), "\" title=\"", __("No help available", "register-plus-redux"), "\" class=\"helpCustomField\" /></td>";
							echo "\n	<td align=\"center\" style=\"padding-top: 0px; padding-bottom: 0px;\"><input type=\"checkbox\" name=\"show_on_profile[]\" value=\"1\" /></td>";
							echo "\n	<td align=\"center\" style=\"padding-top: 0px; padding-bottom: 0px;\"><input type=\"checkbox\" name=\"show_on_registration[]\" value=\"1\" class=\"modifyNextCellInput\" /></td>";
							echo "\n	<td align=\"center\" style=\"padding-top: 0px; padding-bottom: 0px;\"><input type=\"checkbox\" name=\"required_on_registration[]\" value=\"1\" disabled=\"disabled\" /></td>";
							echo "\n	<td align=\"center\" style=\"padding-top: 0px; padding-bottom: 0px;\">";
							echo "\n	<img src=\"", plugins_url("images\delete.png", __FILE__), "\" alt=\"", __("Remove Field", "register-plus-redux"), "\" title=\"", __("Remove Field", "register-plus-redux"), "\" class=\"removeCustomField\" style=\"cursor: pointer;\" />";
							echo "\n	<img src=\"", plugins_url("images\arrow_up.png", __FILE__), "\" alt=\"", __("Move this Field Up", "register-plus-redux"), "\" title=\"", __("Move this Field Up", "register-plus-redux"), "\" class=\"up\" style=\"cursor: pointer;\" />";
							echo "\n	<img src=\"", plugins_url("images\arrow_down.png", __FILE__), "\" alt=\"", __("Move this Field Down", "register-plus-redux"), "\" title=\"", __("Move this Field Down", "register-plus-redux"), "\" class=\"down\" style=\"cursor: pointer;\" />";
							echo "\n	</td>";
							echo "\n</tr>";
						}
						?>
					</tbody>
				</table>
				<img src="<?php echo plugins_url("images\add.png", __FILE__); ?>" alt="<?php esc_attr_e("Add Field", "register-plus-redux") ?>" title="<?php esc_attr_e("Add Field", "register-plus-redux") ?>" id="addCustomField" style="cursor: pointer;" />&nbsp;<?php _e("Add a new custom field.", "register-plus-redux") ?>
				<table class="form-table">
					<tr valign="top" class="disabled">
						<th scope="row"><?php _e("Date Field Settings", "register-plus-redux"); ?></th>
						<td>
							<label for="datepicker_firstdayofweek"><?php _e("First Day of the Week", "register-plus-redux"); ?>:</label>
							<select type="select" name="datepicker_firstdayofweek">
								<option value="7" <?php if ( $options["datepicker_firstdayofweek"] == "7" ) echo "selected=\"selected\""; ?>><?php _e("Monday", "register-plus-redux"); ?></option>
								<option value="1" <?php if ( $options["datepicker_firstdayofweek"] == "1" ) echo "selected=\"selected\""; ?>><?php _e("Tuesday", "register-plus-redux"); ?></option>
								<option value="2" <?php if ( $options["datepicker_firstdayofweek"] == "2" ) echo "selected=\"selected\""; ?>><?php _e("Wednesday", "register-plus-redux"); ?></option>
								<option value="3" <?php if ( $options["datepicker_firstdayofweek"] == "3" ) echo "selected=\"selected\""; ?>><?php _e("Thursday", "register-plus-redux"); ?></option>
								<option value="4" <?php if ( $options["datepicker_firstdayofweek"] == "4" ) echo "selected=\"selected\""; ?>><?php _e("Friday", "register-plus-redux"); ?></option>
								<option value="5" <?php if ( $options["datepicker_firstdayofweek"] == "5" ) echo "selected=\"selected\""; ?>><?php _e("Saturday", "register-plus-redux"); ?></option>
								<option value="6" <?php if ( $options["datepicker_firstdayofweek"] == "6" ) echo "selected=\"selected\""; ?>><?php _e("Sunday", "register-plus-redux"); ?></option>
							</select><br />
							<label for="datepicker_dateformat"><?php _e("Date Format", "register-plus-redux"); ?>:</label><input type="text" name="datepicker_dateformat" value="<?php echo $options["datepicker_dateformat"]; ?>" style="width:100px;" /><br />
							<label for="datepicker_startdate"><?php _e("First Selectable Date", "register-plus-redux"); ?>:</label><input type="text" name="datepicker_startdate" id="datepicker_startdate" value="<?php echo $options["datepicker_startdate"]; ?>" style="width:100px;" /><br />
							<label for="datepicker_calyear"><?php _e("Default Year", "register-plus-redux"); ?>:</label><input type="text" name="datepicker_calyear" id="datepicker_calyear" value="<?php echo $options["datepicker_calyear"]; ?>" style="width:40px;" /><br />
							<label for="datepicker_calmonth"><?php _e("Default Month", "register-plus-redux"); ?>:</label>
							<select name="datepicker_calmonth" id="datepicker_calmonth">
								<option value="cur" <?php if ( $options["datepicker_calmonth"] == "cur" ) echo "selected=\"selected\""; ?>><?php _e("Current Month", "register-plus-redux"); ?></option>
								<option value="0" <?php if ( $options["datepicker_calmonth"] == "0" ) echo "selected=\"selected\""; ?>><?php _e("Jan", "register-plus-redux"); ?></option>
								<option value="1" <?php if ( $options["datepicker_calmonth"] == "1" ) echo "selected=\"selected\""; ?>><?php _e("Feb", "register-plus-redux"); ?></option>
								<option value="2" <?php if ( $options["datepicker_calmonth"] == "2" ) echo "selected=\"selected\""; ?>><?php _e("Mar", "register-plus-redux"); ?></option>
								<option value="3" <?php if ( $options["datepicker_calmonth"] == "3" ) echo "selected=\"selected\""; ?>><?php _e("Apr", "register-plus-redux"); ?></option>
								<option value="4" <?php if ( $options["datepicker_calmonth"] == "4" ) echo "selected=\"selected\""; ?>><?php _e("May", "register-plus-redux"); ?></option>
								<option value="5" <?php if ( $options["datepicker_calmonth"] == "5" ) echo "selected=\"selected\""; ?>><?php _e("Jun", "register-plus-redux"); ?></option>
								<option value="6" <?php if ( $options["datepicker_calmonth"] == "6" ) echo "selected=\"selected\""; ?>><?php _e("Jul", "register-plus-redux"); ?></option>
								<option value="7" <?php if ( $options["datepicker_calmonth"] == "7" ) echo "selected=\"selected\""; ?>><?php _e("Aug", "register-plus-redux"); ?></option>
								<option value="8" <?php if ( $options["datepicker_calmonth"] == "8" ) echo "selected=\"selected\""; ?>><?php _e("Sep", "register-plus-redux"); ?></option>
								<option value="9" <?php if ( $options["datepicker_calmonth"] == "9" ) echo "selected=\"selected\""; ?>><?php _e("Oct", "register-plus-redux"); ?></option>
								<option value="10" <?php if ( $options["datepicker_calmonth"] == "10" ) echo "selected=\"selected\""; ?>><?php _e("Nov", "register-plus-redux"); ?></option>
								<option value="11" <?php if ( $options["datepicker_calmonth"] == "11" ) echo "selected=\"selected\""; ?>><?php _e("Dec", "register-plus-redux"); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<h3 class="title"><?php _e("Auto-Complete Queries", "register-plus-redux"); ?></h3>
				<p><?php _e("You can now link to the registration page with queries to autocomplete specific fields for the user. I have included the query keys below and an example of a query URL.", "register-plus-redux"); ?></p>
				<?php
				$registration_fields = "%first_name% %last_name% %user_url% %aim% %yahoo% %jabber% %about% %invitation_code%";
				foreach ( $custom_fields as $k => $v ) {
					if ( !empty($v["show_on_registration"]) ) $registration_fields .= " %".$this->sanitizeText($v["custom_field_name"])."%";
				}
				?>
				<code><?php echo $registration_fields; ?></code>
				<p><?php _e("For any custom fields, use your custom field label with the text all lowercase, using underscores instead of spaces. For example if your custom field was \"Middle Name\" your query key would be %middle_name%", "register-plus-redux"); ?></p>
				<p><strong><?php _e("Example Query URL", "register-plus-redux"); ?></strong></p>
				<code>http://www.radiok.info/wp-login.php?action=register&user_login=radiok&user_email=radiok@radiok.info&first_name=Radio&last_name=K&user_url=www.radiok.info&aim=radioko&invitation_code=1979&middle_name=Billy</code>
				<h3 class="title"><?php _e("New User Message Settings", "register-plus-redux"); ?></h3>
				<table class="form-table"> 
					<tr valign="top">
						<th scope="row"><label><?php _e("New User Message", "register-plus-redux"); ?></label></th>
						<td>
							<label><input type="checkbox" name="disable_user_message_registered" id="disable_user_message_registered" value="1" <?php if ( !empty($options["disable_user_message_registered"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Do NOT send user an email after they are registered", "register-plus-redux"); ?></label><br />
							<label><input type="checkbox" name="disable_user_message_created" id="disable_user_message_created" value="1" <?php if ( !empty($options["disable_user_message_created"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Do NOT send user an email when created by an administrator", "register-plus-redux"); ?></label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e("Custom New User Message", "register-plus-redux"); ?></label></th>
						<td>
							<label><input type="checkbox" name="custom_user_message" id="custom_user_message" value="1" <?php if ( !empty($options["custom_user_message"]) ) echo "checked=\"checked\""; ?> class="showHideSettings" />&nbsp;<?php _e("Enable...", "register-plus-redux"); ?></label>
							<div id="custom_user_message_settings">
								<table width="60%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px; width: 20%;"><label for="user_message_from_email"><?php _e("From Email", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="user_message_from_email" id="user_message_from_email" style="width: 90%;" value="<?php echo $options["user_message_from_email"]; ?>" /><img src="<?php echo plugins_url("images\arrow_undo.png", __FILE__); ?>" alt="<?php _e("Restore Default", "register-plus-redux"); ?>" title="<?php _e("Restore Default", "register-plus-redux"); ?>" class="default" style="cursor: pointer;" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="user_message_from_name"><?php _e("From Name", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="user_message_from_name" id="user_message_from_name" style="width: 90%;" value="<?php echo stripslashes($options["user_message_from_name"]); ?>" /><img src="<?php echo plugins_url("images\arrow_undo.png", __FILE__); ?>" alt="<?php _e("Restore Default", "register-plus-redux"); ?>" title="<?php _e("Restore Default", "register-plus-redux"); ?>" class="default" style="cursor: pointer;" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="user_message_subject"><?php _e("Subject", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="user_message_subject" id="user_message_subject" style="width: 90%;" value="<?php echo stripslashes($options["user_message_subject"]); ?>" /><img src="<?php echo plugins_url("images\arrow_undo.png", __FILE__); ?>" alt="<?php _e("Restore Default", "register-plus-redux"); ?>" title="<?php _e("Restore Default", "register-plus-redux"); ?>" class="default" style="cursor: pointer;" /></td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="user_message_body"><?php _e("User Message", "register-plus-redux"); ?></label><br />
											<textarea name="user_message_body" id="user_message_body" style="width: 95%; height: 160px;"><?php echo stripslashes($options["user_message_body"]); ?></textarea><img src="<?php echo plugins_url("images\arrow_undo.png", __FILE__); ?>" alt="<?php _e("Restore Default", "register-plus-redux"); ?>" title="<?php _e("Restore Default", "register-plus-redux"); ?>" class="default" style="cursor: pointer;" /><br />
											<strong><?php _e("Replacement Keywords", "register-plus-redux"); ?>:</strong> %user_login% %user_password% %user_email% %blogname% %site_url% <?php echo $registration_fields; ?> %registered_from_ip% %registered_from_host% %http_referer% %http_user_agent% %stored_user_login%<br />
											<label><input type="checkbox" name="send_user_message_in_html" value="1" <?php if ( !empty($options["send_user_message_in_html"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Send as HTML", "register-plus-redux"); ?></label><br />
											<label><input type="checkbox" name="user_message_newline_as_br" value="1" <?php if ( !empty($options["user_message_newline_as_br"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Convert new lines to &lt;br /&gt; tags (HTML only)", "register-plus-redux"); ?></label>
										</td>
									</tr>
									<tr class="disabled">
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="user_message_login_link"><?php _e("Login URL", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;">
											<input type="text" name="user_message_login_link" id="user_message_login_link" style="width:250px;" value="<?php echo $options["user_message_login_link"]; ?>" /><br />
											<?php _e("This will redirect the users login after registration.", "register-plus-redux"); ?>
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e("Custom Verification Message", "register-plus-redux"); ?></label></th>
						<td>
							<label><input type="checkbox" name="custom_verification_message" id="custom_verification_message" value="1" <?php if ( !empty($options["custom_verification_message"]) ) echo "checked=\"checked\""; ?> class="showHideSettings" />&nbsp;<?php _e("Enable...", "register-plus-redux"); ?></label>
							<div id="custom_verification_message_settings">
								<table width="60%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px; width: 20%;"><label for="verification_message_from_email"><?php _e("From Email", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="verification_message_from_email" id="verification_message_from_email" style="width: 90%;" value="<?php echo $options["verification_message_from_email"]; ?>" /><img src="<?php echo plugins_url("images\arrow_undo.png", __FILE__); ?>" alt="<?php _e("Restore Default", "register-plus-redux"); ?>" title="<?php _e("Restore Default", "register-plus-redux"); ?>" class="default" style="cursor: pointer;" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="verification_message_from_name"><?php _e("From Name", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="verification_message_from_name" id="verification_message_from_name" style="width: 90%;" value="<?php echo stripslashes($options["verification_message_from_name"]); ?>" /><img src="<?php echo plugins_url("images\arrow_undo.png", __FILE__); ?>" alt="<?php _e("Restore Default", "register-plus-redux"); ?>" title="<?php _e("Restore Default", "register-plus-redux"); ?>" class="default" style="cursor: pointer;" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="verification_message_subject"><?php _e("Subject", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="verification_message_subject" id="verification_message_subject" style="width: 90%;" value="<?php echo stripslashes($options["verification_message_subject"]); ?>" /><img src="<?php echo plugins_url("images\arrow_undo.png", __FILE__); ?>" alt="<?php _e("Restore Default", "register-plus-redux"); ?>" title="<?php _e("Restore Default", "register-plus-redux"); ?>" class="default" style="cursor: pointer;" /></td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="verification_message_body"><?php _e("User Message", "register-plus-redux"); ?></label><br />
											<textarea name="verification_message_body" id="verification_message_body" style="width: 95%; height: 160px;"><?php echo stripslashes($options["verification_message_body"]); ?></textarea><img src="<?php echo plugins_url("images\arrow_undo.png", __FILE__); ?>" alt="<?php _e("Restore Default", "register-plus-redux"); ?>" title="<?php _e("Restore Default", "register-plus-redux"); ?>" class="default" style="cursor: pointer;" /><br />
											<strong><?php _e("Replacement Keywords", "register-plus-redux"); ?>:</strong> %user_login% %user_email% %blogname% %site_url% %verification_url% <?php echo $registration_fields; ?> %registered_from_ip% %registered_from_host% %http_referer% %http_user_agent% %stored_user_login%<br />
											<label><input type="checkbox" name="send_verification_message_in_html" value="1" <?php if ( !empty($options["send_verification_message_in_html"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Send as HTML", "register-plus-redux"); ?></label><br />
											<label><input type="checkbox" name="verification_message_newline_as_br" value="1" <?php if ( !empty($options["verification_message_newline_as_br"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Convert new lines to &lt;br /&gt; tags (HTML only)", "register-plus-redux"); ?></label>
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e("Summary", "register-plus-redux"); ?></label></th>
						<td>
							<span id="user_message_summary"></span>
						</td>
					</tr>
				</table>
				<h3 class="title"><?php _e("Admin Notification Settings", "register-plus-redux"); ?></h3>
				<table class="form-table"> 
					<tr valign="top">
						<th scope="row"><label><?php _e("Admin Notification", "register-plus-redux"); ?></label></th>
						<td>
							<label><input type="checkbox" name="disable_admin_message_registered" id="disable_admin_message_registered" value="1" <?php if ( !empty($options["disable_admin_message_registered"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Do NOT send administrator an email whenever a new user registers", "register-plus-redux"); ?></label><br />
							<label><input type="checkbox" name="disable_admin_message_created" id="disable_admin_message_created" value="1" <?php if ( !empty($options["disable_admin_message_created"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Do NOT send administrator an email whenever a new user is created by an administrator", "register-plus-redux"); ?></label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e("Custom Admin Notification", "register-plus-redux"); ?></label></th>
						<td>
							<label><input type="checkbox" name="custom_admin_message" id="custom_admin_message" value="1" <?php if ( !empty($options["custom_admin_message"]) ) echo "checked=\"checked\""; ?> class="showHideSettings" />&nbsp;<?php _e("Enable...", "register-plus-redux"); ?></label>
							<div id="custom_admin_message_settings">
								<table width="60%">
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px; width: 20%;"><label for="admin_message_from_email"><?php _e("From Email", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="admin_message_from_email" id="admin_message_from_email" style="width: 90%;" value="<?php echo $options["admin_message_from_email"]; ?>" /><img src="<?php echo plugins_url("images\arrow_undo.png", __FILE__); ?>" alt="<?php _e("Restore Default", "register-plus-redux"); ?>" title="<?php _e("Restore Default", "register-plus-redux"); ?>" class="default" style="cursor: pointer;" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="admin_message_from_name"><?php _e("From Name", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="admin_message_from_name" id="admin_message_from_name" style="width: 90%;" value="<?php echo stripslashes($options["admin_message_from_name"]); ?>" /><img src="<?php echo plugins_url("images\arrow_undo.png", __FILE__); ?>" alt="<?php _e("Restore Default", "register-plus-redux"); ?>" title="<?php _e("Restore Default", "register-plus-redux"); ?>" class="default" style="cursor: pointer;" /></td>
									</tr>
									<tr>
										<td style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;"><label for="admin_message_subject"><?php _e("Subject", "register-plus-redux"); ?></label></td>
										<td style="padding-top: 0px; padding-bottom: 0px;"><input type="text" name="admin_message_subject" id="admin_message_subject" style="width: 90%;" value="<?php echo stripslashes($options["admin_message_subject"]); ?>" /><img src="<?php echo plugins_url("images\arrow_undo.png", __FILE__); ?>" alt="<?php _e("Restore Default", "register-plus-redux"); ?>" title="<?php _e("Restore Default", "register-plus-redux"); ?>" class="default" style="cursor: pointer;" /></td>
									</tr>
									<tr>
										<td colspan="2" style="padding-top: 0px; padding-bottom: 0px; padding-left: 0px;">
											<label for="admin_message_body"><?php _e("Admin Message", "register-plus-redux"); ?></label><br />
											<textarea name="admin_message_body" id="admin_message_body" style="width: 95%; height: 160px;"><?php echo stripslashes($options["admin_message_body"]); ?></textarea><img src="<?php echo plugins_url("images\arrow_undo.png", __FILE__); ?>" alt="<?php _e("Restore Default", "register-plus-redux"); ?>" title="<?php _e("Restore Default", "register-plus-redux"); ?>" class="default" style="cursor: pointer;" /><br />
											<strong><?php _e("Replacement Keywords", "register-plus-redux"); ?>:</strong> %user_login% %user_email% %blogname% %site_url% <?php echo $registration_fields; ?> %registered_from_ip% %registered_from_host% %http_referer% %http_user_agent% %stored_user_login%<br />
											<label><input type="checkbox" name="send_admin_message_in_html" value="1" <?php if ( !empty($options["send_admin_message_in_html"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Send as HTML", "register-plus-redux"); ?></label><br />
											<label><input type="checkbox" name="admin_message_newline_as_br" value="1" <?php if ( !empty($options["admin_message_newline_as_br"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Convert new lines to &lt;br /&gt; tags (HTML only)", "register-plus-redux"); ?></label>
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e("Summary", "register-plus-redux"); ?></label></th>
						<td>
							<span id="admin_message_summary"></span>
						</td>
					</tr>
				</table>
				<br />
				<h3 class="title"><?php _e("Custom CSS for Register & Login Pages", "register-plus-redux"); ?></h3>
				<p><?php _e("CSS Rule Example:", "register-plus-redux"); ?>&nbsp;<code>#user_login { font-size: 20px; width: 97%; padding: 3px; margin-right: 6px; }</code></p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="custom_registration_page_css"><?php _e("Custom Register CSS", "register-plus-redux"); ?></label></th>
						<td><textarea name="custom_registration_page_css" id="custom_registration_page_css" style="width:60%; height:160px;"><?php echo $options["custom_registration_page_css"]; ?></textarea></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="custom_login_page_css"><?php _e("Custom Login CSS", "register-plus-redux"); ?></label></th>
						<td><textarea name="custom_login_page_css" id="custom_login_page_css" style="width:60%; height:160px;"><?php echo $options["custom_login_page_css"]; ?></textarea></td>
					</tr>
				</table>
				<br />
				<h3 class="title"><?php _e("Hacks & Fixes", "register-plus-redux"); ?></h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e("Non-English Custom Fields", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="disable_sanitize_key" value="1" <?php if ( !empty($options["disable_sanitize_key"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Do not sanitize keys.", "register-plus-redux"); ?></label><br />
							<?php _e("Custom fields with non-english characters may not work, this hack will stop sanitizing the custom field name which may resolve this issue.", "register-plus-redux"); ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e("URL File Access is Disabled", "register-plus-redux"); ?></th>
						<td>
							<label><input type="checkbox" name="disable_url_fopen" value="1" <?php if ( !empty($options["disable_url_fopen"]) ) echo "checked=\"checked\""; ?> />&nbsp;<?php _e("Do not open URL files.", "register-plus-redux"); ?></label><br />
							<?php _e("Some PHP configurations do not allow accessing URL objects like files (allow_url_fopen=disabled in php.ini), this hack will stop trying to open URL files as if they were local files. Custom logo must be exactly 326x67 with this hack enabled, otherwise it will be cropped.", "register-plus-redux"); ?>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php esc_attr_e("Save Changes", "register-plus-redux"); ?>" name="update_settings" />
					<input type="button" class="button" value="<?php esc_attr_e("Preview Registration Page", "register-plus-redux"); ?>" name="preview" onclick="window.open('<?php echo wp_login_url(), "?action=register"; ?>');" />
				</p>
			</form>
			</div>
			<?php
		}

		function UpdateSettings() {
			/*
			UpdateSettings is much harsher than it used to be, does not load old settings and
			update them, just pulls whatever is on the current settings page
			*/
			$options = array();
			if ( isset($_POST["custom_logo_url"]) ) $options["custom_logo_url"] = $_POST["custom_logo_url"];
			if ( !empty($_FILES["upload_custom_logo"]["name"]) ) {
				$upload = wp_upload_bits($_FILES["upload_custom_logo"]["name"], null, file_get_contents($_FILES["upload_custom_logo"]["tmp_name"]));
				if ( !$upload["error"] ) $options["custom_logo_url"] = $upload["url"];
			}
			if ( isset($_POST["remove_logo"]) ) $options["custom_logo_url"] = "";
			if ( isset($_POST["verify_user_email"]) ) $options["verify_user_email"] = $_POST["verify_user_email"];
			if ( isset($_POST["message_verify_user_email"]) ) $options["message_verify_user_email"] = $_POST["message_verify_user_email"];
			if ( isset($_POST["verify_user_admin"]) ) $options["verify_user_admin"] = $_POST["verify_user_admin"];
			if ( isset($_POST["message_verify_user_admin"]) ) $options["message_verify_user_admin"] = $_POST["message_verify_user_admin"];
			if ( isset($_POST["delete_unverified_users_after"]) ) $options["delete_unverified_users_after"] = $_POST["delete_unverified_users_after"];

			$options["username_is_email"] = isset($_POST["username_is_email"]) ? $_POST["username_is_email"] : "";
			if ( isset($_POST["double_check_email"]) ) $options["double_check_email"] = $_POST["double_check_email"];
			if ( isset($_POST["show_fields"]) ) $options["show_fields"] = $_POST["show_fields"];
			if ( isset($_POST["required_fields"]) ) $options["required_fields"] = $_POST["required_fields"];
			if ( isset($_POST["user_set_password"]) ) $options["user_set_password"] = $_POST["user_set_password"];
			if ( isset($_POST["min_password_length"]) ) $options["min_password_length"] = $_POST["min_password_length"];
			if ( isset($_POST["show_password_meter"]) ) $options["show_password_meter"] = $_POST["show_password_meter"];
			if ( isset($_POST["message_empty_password"]) ) $options["message_empty_password"] = $_POST["message_empty_password"];
			if ( isset($_POST["message_short_password"]) ) $options["message_short_password"] = $_POST["message_short_password"];
			if ( isset($_POST["message_bad_password"]) ) $options["message_bad_password"] = $_POST["message_bad_password"];
			if ( isset($_POST["message_good_password"]) ) $options["message_good_password"] = $_POST["message_good_password"];
			if ( isset($_POST["message_strong_password"]) ) $options["message_strong_password"] = $_POST["message_strong_password"];
			if ( isset($_POST["message_mismatch_password"]) ) $options["message_mismatch_password"] = $_POST["message_mismatch_password"];
			if ( isset($_POST["enable_invitation_code"]) ) $options["enable_invitation_code"] = $_POST["enable_invitation_code"];
			if ( isset($_POST["require_invitation_code"]) ) $options["require_invitation_code"] = $_POST["require_invitation_code"];
			if ( isset($_POST["invitation_code_case_sensitive"]) ) $options["invitation_code_case_sensitive"] = $_POST["invitation_code_case_sensitive"];
			if ( isset($_POST["enable_invitation_tracking_widget"]) ) $options["enable_invitation_tracking_widget"] = $_POST["enable_invitation_tracking_widget"];
			if ( isset($_POST["invitation_code_bank"]) ) $options["invitation_code_bank"] = $_POST["invitation_code_bank"];
			if ( isset($_POST["show_disclaimer"]) ) $options["show_disclaimer"] = $_POST["show_disclaimer"];
			if ( isset($_POST["message_disclaimer_title"]) ) $options["message_disclaimer_title"] = $_POST["message_disclaimer_title"];
			if ( isset($_POST["message_disclaimer"]) ) $options["message_disclaimer"] = $_POST["message_disclaimer"];
			$options["require_disclaimer_agree"] = isset($_POST["require_disclaimer_agree"]) ? $_POST["require_disclaimer_agree"] : "";
			if ( isset($_POST["message_disclaimer_agree"]) ) $options["message_disclaimer_agree"] = $_POST["message_disclaimer_agree"];
			if ( isset($_POST["show_license"]) ) $options["show_license"] = $_POST["show_license"];
			if ( isset($_POST["message_license_title"]) ) $options["message_license_title"] = $_POST["message_license_title"];
			if ( isset($_POST["message_license"]) ) $options["message_license"] = $_POST["message_license"];
			$options["require_license_agree"] = isset($_POST["require_license_agree"]) ? $_POST["require_license_agree"] : "";
			if ( isset($_POST["message_license_agree"]) ) $options["message_license_agree"] = $_POST["message_license_agree"];
			if ( isset($_POST["show_privacy_policy"]) ) $options["show_privacy_policy"] = $_POST["show_privacy_policy"];
			if ( isset($_POST["message_privacy_policy_title"]) ) $options["message_privacy_policy_title"] = $_POST["message_privacy_policy_title"];
			if ( isset($_POST["message_privacy_policy"]) ) $options["message_privacy_policy"] = $_POST["message_privacy_policy"];
			$options["require_privacy_policy_agree"] = isset($_POST["require_privacy_policy_agree"]) ? $_POST["require_privacy_policy_agree"] : "";
			if ( isset($_POST["message_privacy_policy_agree"]) ) $options["message_privacy_policy_agree"] = $_POST["message_privacy_policy_agree"];
			$options["default_css"] = isset($_POST["default_css"][$k]) ? $_POST["default_css"] : "";
			if ( isset($_POST["required_fields_style"]) ) $options["required_fields_style"] = $_POST["required_fields_style"];
			if ( isset($_POST["required_fields_asterisk"]) ) $options["required_fields_asterisk"] = $_POST["required_fields_asterisk"];
			if ( isset($_POST["starting_tabindex"]) ) $options["starting_tabindex"] = $_POST["starting_tabindex"];

			if ( isset($_POST["custom_field_name"]) ) {
				foreach ( $_POST["custom_field_name"] as $k => $v ) {
					if ( !empty($v) ) { 
						$custom_fields[$k] = array(
							"custom_field_name" => $v,
							"custom_field_type" => isset($_POST["custom_field_type"][$k]) ? $_POST["custom_field_type"][$k] : "",
							"custom_field_options" => isset($_POST["custom_field_options"][$k]) ? $_POST["custom_field_options"][$k] : "",
							"show_on_profile" => isset($_POST["show_on_profile"][$k]) ? $_POST["show_on_profile"][$k] : "",
							"show_on_registration" => isset($_POST["show_on_registration"][$k]) ? $_POST["show_on_registration"][$k] : "",
							"required_on_registration" => isset($_POST["required_on_registration"][$k]) ? $_POST["required_on_registration"][$k] : "");
					}
				}
			}
			if ( isset($_POST["datepicker_firstdayofweek"]) ) $options["datepicker_firstdayofweek"] = $_POST["datepicker_firstdayofweek"];
			if ( isset($_POST["datepicker_dateformat"]) ) $options["datepicker_dateformat"] = $_POST["datepicker_dateformat"];
			if ( isset($_POST["datepicker_startdate"]) ) $options["datepicker_startdate"] = $_POST["datepicker_startdate"];
			if ( isset($_POST["datepicker_calyear"]) ) $options["datepicker_calyear"] = $_POST["datepicker_calyear"];
			if ( isset($_POST["datepicker_calmonth"]) ) $options["datepicker_calmonth"] = $_POST["datepicker_calmonth"];

			if ( isset($_POST["disable_user_message_registered"]) ) $options["disable_user_message_registered"] = $_POST["disable_user_message_registered"];
			if ( isset($_POST["disable_user_message_created"]) ) $options["disable_user_message_created"] = $_POST["disable_user_message_created"];
			if ( isset($_POST["custom_user_message"]) ) $options["custom_user_message"] = $_POST["custom_user_message"];
			if ( isset($_POST["user_message_from_email"]) ) $options["user_message_from_email"] = $_POST["user_message_from_email"];
			if ( isset($_POST["user_message_from_name"]) ) $options["user_message_from_name"] = $_POST["user_message_from_name"];
			if ( isset($_POST["user_message_subject"]) ) $options["user_message_subject"] = $_POST["user_message_subject"];
			if ( isset($_POST["user_message_body"]) ) $options["user_message_body"] = $_POST["user_message_body"];
			if ( isset($_POST["send_user_message_in_html"]) ) $options["send_user_message_in_html"] = $_POST["send_user_message_in_html"];
			if ( isset($_POST["user_message_newline_as_br"]) ) $options["user_message_newline_as_br"] = $_POST["user_message_newline_as_br"];
			if ( isset($_POST["user_message_login_link"]) ) $options["user_message_login_link"] = $_POST["user_message_login_link"];
			if ( isset($_POST["custom_verification_message"]) ) $options["custom_verification_message"] = $_POST["custom_verification_message"];
			if ( isset($_POST["verification_message_from_email"]) ) $options["verification_message_from_email"] = $_POST["verification_message_from_email"];
			if ( isset($_POST["verification_message_from_name"]) ) $options["verification_message_from_name"] = $_POST["verification_message_from_name"];
			if ( isset($_POST["verification_message_subject"]) ) $options["verification_message_subject"] = $_POST["verification_message_subject"];
			if ( isset($_POST["verification_message_body"]) ) $options["verification_message_body"] = $_POST["verification_message_body"];
			if ( isset($_POST["send_verification_message_in_html"]) ) $options["send_verification_message_in_html"] = $_POST["send_verification_message_in_html"];
			if ( isset($_POST["verification_message_newline_as_br"]) ) $options["verification_message_newline_as_br"] = $_POST["verification_message_newline_as_br"];

			if ( isset($_POST["disable_admin_message_registered"]) ) $options["disable_admin_message_registered"] = $_POST["disable_admin_message_registered"];
			if ( isset($_POST["disable_admin_message_created"]) ) $options["disable_admin_message_created"] = $_POST["disable_admin_message_created"];
			if ( isset($_POST["custom_admin_message"]) ) $options["custom_admin_message"] = $_POST["custom_admin_message"];
			if ( isset($_POST["admin_message_from_email"]) ) $options["admin_message_from_email"] = $_POST["admin_message_from_email"];
			if ( isset($_POST["admin_message_from_name"]) ) $options["admin_message_from_name"] = $_POST["admin_message_from_name"];
			if ( isset($_POST["admin_message_subject"]) ) $options["admin_message_subject"] = $_POST["admin_message_subject"];
			if ( isset($_POST["admin_message_body"]) ) $options["admin_message_body"] = $_POST["admin_message_body"];
			if ( isset($_POST["send_admin_message_in_html"]) ) $options["send_admin_message_in_html"] = $_POST["send_admin_message_in_html"];
			if ( isset($_POST["admin_message_newline_as_br"]) ) $options["admin_message_newline_as_br"] = $_POST["admin_message_newline_as_br"];

			if ( isset($_POST["custom_registration_page_css"]) ) $options["custom_registration_page_css"] = $_POST["custom_registration_page_css"];
			if ( isset($_POST["custom_login_page_css"]) ) $options["custom_login_page_css"] = $_POST["custom_login_page_css"];

			$options["registration_redirect"] = isset($_POST["registration_redirect"]) ? $_POST["registration_redirect"] : "";
			$options["verification_redirect"] = isset($_POST["verification_redirect"]) ? $_POST["verification_redirect"] : "";

			$options["disable_sanitize_key"] = isset($_POST["disable_sanitize_key"]) ? $_POST["disable_sanitize_key"] : "";
			$options["disable_url_fopen"] = isset($_POST["disable_url_fopen"]) ? $_POST["disable_url_fopen"] : "";

			update_option("register_plus_redux_options", $options);
			update_option("register_plus_redux_custom_fields", $custom_fields);
		}

		function UnverifiedUsersPage() {
			global $wpdb;
			if ( (isset($_REQUEST["action"]) && $_REQUEST["action"] == "verify_users") || isset($_REQUEST["verify_users"]) ) {
				check_admin_referer("register-plus-redux-unverified-users");
				if ( isset($_REQUEST["users"]) && is_array($_REQUEST["users"]) ) {
					$update = "verify_users";
					$options = get_option("register_plus_redux_options");
					foreach ( $_REQUEST["users"] as $user_id ) {
						$stored_user_login = get_user_meta($user_id, "stored_user_login", true);
						$plaintext_pass = get_user_meta($user_id, "stored_user_password", true);
						$wpdb->query( $wpdb->prepare("UPDATE $wpdb->users SET user_login = \"$stored_user_login\" WHERE ID = \"$user_id\"") );
						delete_user_meta($user_id, "email_verification_code");
						delete_user_meta($user_id, "email_verification_sent");
						delete_user_meta($user_id, "email_verified");
						delete_user_meta($user_id, "stored_user_login");
						delete_user_meta($user_id, "stored_user_password");
						if ( empty($plaintext_pass) ) {
							$plaintext_pass = wp_generate_password();
							update_user_option($user_id, "default_password_nag", true, true);
							wp_set_password($plaintext_pass, $user_id);
						}
						if ( empty($options["disable_user_message_registered"]) )
							$this->sendUserMessage($user_id, $plaintext_pass);
					}
				}
			}
			if ( (isset($_REQUEST["action"]) && $_REQUEST["action"] == "send_verification_email") || isset($_REQUEST["send_verification_email"]) ) {
				check_admin_referer("register-plus-redux-unverified-users");
				if ( isset($_REQUEST["users"]) && is_array($_REQUEST["users"]) ) {
					$update = "send_verification_email";
					foreach ( $_REQUEST["users"] as $user_id ) {
						$id = (int) $user_id;
						if ( !current_user_can("promote_user", $id) )
							wp_die(__("You cannot edit that user.", "register-plus-redux"));
						$verification_code = wp_generate_password(20, false);
						update_user_meta($user_id, "email_verification_code", $verification_code);
						update_user_meta($user_id, "email_verification_sent", gmdate("Y-m-d H:i:s"));
						$this->sendVerificationMessage($user_id, $verification_code);
					}
				}
			}
			if ( (isset($_REQUEST["action"]) && $_REQUEST["action"] == "delete_users") || isset($_REQUEST["delete_users"]) ) {
				check_admin_referer("register-plus-redux-unverified-users");
				if ( isset($_REQUEST["users"]) && is_array($_REQUEST["users"]) ) {
					$update = "delete_users";
					if (!function_exists("wp_delete_user")) require_once(ABSPATH."/wp-admin/includes/user.php");
					foreach ( $_REQUEST["users"] as $user_id )
						wp_delete_user($user_id);
				}
			}
			if ( !empty($update) ) {
				switch( $update ) {
					case "verify_users":
						echo "<div id=\"message\" class=\"updated\"><p>", __("Users approved.", "register-plus-redux"), "</p></div>";
						break;
					case "send_verification_email":
						echo "<div id=\"message\" class=\"updated\"><p>", __("Verification emails sent.", "register-plus-redux"), "</p></div>";
						break;
					case "delete_users":
						echo "<div id=\"message\" class=\"updated\"><p>", __("Users deleted.", "register-plus-redux"), "</p></div>";
						break;
				}
			}
			?>
			<div class="wrap">
				<h2><?php _e("Unverified Users", "register-plus-redux") ?></h2>
				<form id="verify-filter" method="post">
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action">
							<option value="" selected="selected"><?php _e("Bulk Actions", "register-plus-redux"); ?></option>
							<?php if ( current_user_can("promote_users") ) echo "<option value=\"verify_users\">", __("Approve", "register-plus-redux"), "</option>\n"; ?>
							<option value="send_verification_email"><?php _e("Send E-mail Verification", "register-plus-redux"); ?></option>
							<?php if ( current_user_can("delete_users") ) echo "<option value=\"delete_users\">", __("Delete", "register-plus-redux"), "</option>\n"; ?>
						</select>
						<input type="submit" value="<?php esc_attr_e("Apply", "register-plus-redux"); ?>" name="doaction" id="doaction" class="button-secondary action" />
						<?php wp_nonce_field("register-plus-redux-unverified-users"); ?>

					</div>
					<br class="clear">
				</div>
				<table class="widefat fixed" cellspacing="0">
					<thead>
						<tr class="thead">
							<th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
							<th scope="col" id="stored_username" class="manage-column column-stored_username" style=""><?php _e("Username", "register-plus-redux"); ?></th>
							<th scope="col" id="temp_username" class="manage-column column-temp_username" style=""><?php _e("Temp Username", "register-plus-redux"); ?></th>
							<th scope="col" id="email" class="manage-column column-email" style=""><?php _e("E-mail", "register-plus-redux"); ?></th>
							<th scope="col" id="registered" class="manage-column column-registered" style=""><?php _e("Registered", "register-plus-redux"); ?></th>
							<th scope="col" id="verification_sent"class="manage-column column-verification_sent" style=""><?php _e("Verification Sent", "register-plus-redux"); ?></th>
							<th scope="col" id="verified"class="manage-column column-verified" style=""><?php _e("Verified", "register-plus-redux"); ?></th>
						</tr>
					</thead>
					<tbody id="users" class="list:user user-list">
						<?php 
						$unverified_users = $wpdb->get_results("SELECT user_id FROM $wpdb->usermeta WHERE meta_key=\"stored_user_login\"");
						$style = "";
						foreach ( $unverified_users as $unverified_user ) {
							$user_info = get_userdata($unverified_user->user_id);
							$style = ( $style == "class=\"alternate\"" ) ? "" : " class=\"alternate\"";
							?>

							<tr id="user-<?php echo $user_info->ID; ?>"<?php echo $style; ?>>
								<th scope="row" class="check-column"><input type="checkbox" name="users[]" id="user_<?php echo $user_info->ID; ?>" name="user_<?php echo $user_info->ID; ?>" value="<?php echo $user_info->ID; ?>"></th>
								<td class="username column-username">
									<strong><?php if ( current_user_can("edit_users") ) echo "<a href=\"", esc_url(add_query_arg("wp_http_referer", urlencode(esc_url(stripslashes($_SERVER["REQUEST_URI"]))), "user-edit.php?user_id=$user_info->ID")) , "\">$user_info->stored_user_login</a>"; else echo $user_info->stored_user_login; ?></strong><br />
									<div class="row-actions">
										<?php if ( current_user_can("edit_users") ) echo "<span class=\"edit\"><a href=\"", esc_url(add_query_arg("wp_http_referer", urlencode(esc_url(stripslashes($_SERVER["REQUEST_URI"]))), "user-edit.php?user_id=$user_info->ID")), "\">", __("Edit", "register-plus-redux"), "</a></span>\n"; ?>
										<?php if ( current_user_can("delete_users") ) echo "<span class=\"delete\"> | <a href=\"", wp_nonce_url(add_query_arg("wp_http_referer", urlencode(esc_url(stripslashes($_SERVER["REQUEST_URI"]))), "users.php?action=delete&amp;user=$user_info->ID"), "bulk-users"), "\" class=\"submitdelete\">", __("Delete", "register-plus-redux"), "</a></span>\n"; ?>
									</div>
								</td>
								<td><?php echo $user_info->user_login; ?></td>
								<td class="email column-email"><a href="mailto:<?php echo $user_info->user_email; ?>" title="<?php esc_attr_e("E-mail: ", "register-plus-redux"); echo $user_info->user_email; ?>"><?php echo $user_info->user_email; ?></a></td>
								<td><?php echo $user_info->user_registered; ?></td>
								<td><?php echo $user_info->email_verification_sent; ?></td>
								<td><?php echo $user_info->email_verified; ?></td>
							</tr>
						<?php } ?>

					</tbody>
				</table>
				<div class="tablenav">
					<div class="alignleft actions">
						<?php if ( current_user_can("promote_users") ) echo "<input type=\"submit\" value=\"", __("Approve Selected Users", "register-plus-redux"), "\" name=\"verify_users\" class=\"button-secondary action\" />&nbsp;\n"; ?>
						<input type="submit" value="<?php _e("Send E-mail Verification to Selected Users", "register-plus-redux");?>" name="send_verification_email" class="button-secondary action" />
						<?php if ( current_user_can("delete_users") ) echo "&nbsp;<input type=\"submit\" value=\"", __("Delete Selected Users", "register-plus-redux"), "\" name=\"delete_users\" class=\"button-secondary action\" />\n"; ?>
					</div>
					<br class="clear">
				</div>
				</form>
			</div>
			<br class="clear" />
			<?php
		}

		function LoginHead() {
			$options = get_option("register_plus_redux_options");
			if ( !empty($options["custom_logo_url"]) ) {
				if ( empty($options["disable_url_fopen"]) )
					list($width, $height, $type, $attr) = getimagesize($options["custom_logo_url"]);
				?>
				<style type="text/css">
					#login h1 a {
						background-image: url("<?php echo $options["custom_logo_url"]; ?>");
						margin: 0 0 0 8px;
						<?php if ( !empty($width) ) echo "width: ", $width, "px;\n"; ?>
						<?php if ( !empty($height) ) echo "height: ", $height, "px;\n"; ?>
					}
				</style>
				<?php
			}
			if ( isset($_GET["checkemail"]) && $_GET["checkemail"] == "registered" && (!empty($options["verify_user_admin"]) || !empty($options["verify_user_email"])) ) {
				?>
				<style type="text/css">
					#loginform { display: none; }
					#nav { display: none; }
				</style>
				<?php
				
			}
			if ( isset($_GET["action"]) && $_GET["action"] == "register" ) {
				if ( !empty($options["username_is_email"]) ) {
					if ( empty($jquery_loaded) ) {
						wp_print_scripts("jquery");
						$jquery_loaded = true;
					}
					?>
					<script type="text/javascript">
					jQuery(document).ready(function() {
						jQuery("#user_login").parent().parent().hide();
					});
					</script>
					<?php
				}
				if ( isset($_GET["user_login"]) ) $_POST["user_login"] = $_GET["user_login"];
				if ( isset($_GET["user_email"]) ) $_POST["user_email"] = $_GET["user_email"];
				if ( !empty($_POST["user_login"]) || !empty($_POST["user_email"]) ) {
					if ( empty($jquery_loaded) ) {
						wp_print_scripts("jquery");
						$jquery_loaded = true;
					}
					?>
					<script type="text/javascript">
					jQuery(document).ready(function() {
						jQuery("#user_login").val("<?php echo $_POST["user_login"]; ?>");
						jQuery("#user_email").val("<?php echo $_POST["user_email"]; ?>");
					});
					</script>
					<?php
				}
				$custom_fields = get_option("register_plus_redux_custom_fields");
				if ( !is_array($custom_fields) ) $custom_fields = array();
				foreach ( $custom_fields as $k => $v ) {
					if ( !empty($v["show_on_registration"]) ) {
						if ( $v["custom_field_type"] == "text" || $v["custom_field_type"] == "url" ) {
							if ( empty($show_custom_text_fields) )
								$show_custom_text_fields = "#".$this->sanitizeText($v["custom_field_name"]);
							else
								$show_custom_text_fields .= ", #".$this->sanitizeText($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "select" ) {
							if ( empty($show_custom_select_fields) )
								$show_custom_select_fields = "#".$this->sanitizeText($v["custom_field_name"]);
							else
								$show_custom_select_fields .= ", #".$this->sanitizeText($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "checkbox" ) {
							if ( empty($show_custom_checkbox_fields) )
								$show_custom_checkbox_fields = ".".$this->sanitizeText($v["custom_field_name"]);
							else
								$show_custom_checkbox_fields .= ", .".$this->sanitizeText($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "radio" ) {
							if ( empty($show_custom_radio_fields) )
								$show_custom_radio_fields = ".".$this->sanitizeText($v["custom_field_name"]);
							else
								$show_custom_radio_fields .= ", .".$this->sanitizeText($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "textarea" ) {
							if ( empty($show_custom_textarea_fields) )
								$show_custom_textarea_fields = "#".$this->sanitizeText($v["custom_field_name"]);
							else
								$show_custom_textarea_fields .= ", #".$this->sanitizeText($v["custom_field_name"]);
						}
						if ( $v["custom_field_type"] == "date" ) {
							if ( empty($show_custom_date_fields) )
								$show_custom_date_fields = "#".$this->sanitizeText($v["custom_field_name"]);
							else
								$show_custom_date_fields .= ", #".$this->sanitizeText($v["custom_field_name"]);
						}
						if ( !empty($v["required_on_registration"]) ) {
							if ( empty($required_custom_fields) )
								$required_custom_fields = "#".$this->sanitizeText($v["custom_field_name"]);
							else
								$required_custom_fields .= ", #".$this->sanitizeText($v["custom_field_name"]);
						}
					}
				}

				if ( !empty($options["show_fields"][0]) ) $show_fields = "#".implode(", #", $options["show_fields"]);
				if ( !empty($options["required_fields"][0]) ) $required_fields = "#".implode(", #", $options["required_fields"]);

				echo "\n<style type=\"text/css\">";
				echo "\nsmall { display:block; margin-bottom:8px; }";
				if ( !empty($options["default_css"]) ) {
					if ( !empty($options["double_check_email"]) ) echo "\n#user_email2 { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
					if ( !empty($show_fields) ) echo "\n$show_fields { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
					if ( in_array("about", $options["show_fields"]) ) echo "\n#about { font-size:24px; height: 60px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
					if ( !empty($show_custom_text_fields) ) echo "\n$show_custom_text_fields { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
					if ( !empty($show_custom_select_fields) ) echo "\n$show_custom_select_fields { font-size:24px; width:100%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
					if ( !empty($show_custom_checkbox_fields) ) echo "\n$show_custom_checkbox_fields { font-size:18px; }";
					if ( !empty($show_custom_radio_fields) ) echo "\n$show_custom_radio_fields { font-size:18px; }";
					if ( !empty($show_custom_textarea_fields) ) echo "\n$show_custom_textarea_fields { font-size:24px; height: 60px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
					if ( !empty($show_custom_date_fields) ) echo "\n$show_custom_date_fields { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
					if ( !empty($options["user_set_password"]) ) {
						echo "\n#pass1, #pass2 { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:16px; border:1px solid #e5e5e5; background:#fbfbfb; }";
					}
					if ( !empty($options["enable_invitation_code"]) ) echo "\n#invitation_code { font-size:24px; width:97%; padding:3px; margin-top:2px; margin-right:6px; margin-bottom:4px; border:1px solid #e5e5e5; background:#fbfbfb; }";
				}
				if ( !empty($options["show_disclaimer"]) ) { echo "\n#disclaimer { font-size:12px; display: block; width: 97%; padding: 3px; margin-top:2px; margin-right:6px; margin-bottom:8px; background-color:#fff; border:solid 1px #A7A6AA; font-weight:normal;"; if ( strlen($options["message_disclaimer"]) > 525) echo "height: 160px; overflow:scroll;"; echo " }"; }
				if ( !empty($options["show_license"]) ) { echo "\n#license { font-size:12px; display: block; width: 97%; padding: 3px; margin-top:2px; margin-right:6px; margin-bottom:8px; background-color:#fff; border:solid 1px #A7A6AA; font-weight:normal;"; if ( strlen($options["message_license"]) > 525) echo "height: 160px; overflow:scroll;"; echo " }"; }
				if ( !empty($options["show_privacy_policy"]) ) { echo "\n#privacy_policy { font-size:12px; display: block; width: 97%; padding: 3px; margin-top:2px; margin-right:6px; margin-bottom:8px; background-color:#fff; border:solid 1px #A7A6AA; font-weight:normal;"; if ( strlen($options["message_privacy_policy"]) > 525) echo "height: 160px; overflow:scroll;"; echo " }"; }
				if ( !empty($options["show_disclaimer"]) || !empty($options["show_license"]) || !empty($options["show_privacy_policy"]) ) echo "\n.accept_check { display:block; margin-bottom:8px; }";
				if ( !empty($options["user_set_password"]) ) {
					echo "\n#reg_passmail { display: none; }";
					if ( !empty($options["show_password_meter"]) ) echo "\n#pass-strength-result { width: 97%; padding: 3px; margin-top:0px; margin-right:6px; margin-bottom:4px; border: 1px solid; text-align: center; }";
				}
				if ( !empty($options["required_fields_style"]) ) {
					echo "\n#user_login, #user_email { ", $options["required_fields_style"], "} ";
					if ( !empty($options["double_check_email"]) ) echo "\n#user_email2 { ", $options["required_fields_style"], " }";
					if ( !empty($required_fields) ) echo "\n$required_fields { ", $options["required_fields_style"], " }";
					if ( !empty($required_custom_fields) ) echo "\n$required_custom_fields { ", $options["required_fields_style"], " }";
					if ( !empty($options["user_set_password"]) ) echo "\n#pass1, #pass2 { ", $options["required_fields_style"], " }";
					if ( !empty($options["require_invitation_code"]) ) echo "\n#invitation_code { ", $options["required_fields_style"], " }";
				}
				if ( !empty($options["custom_registration_page_css"]) ) echo "\n", stripslashes($options["custom_registration_page_css"]);
				echo "\n</style>";

				if ( !empty($show_custom_date_fields) ) {
					if ( empty($jquery_loaded) ) {
						wp_print_scripts("jquery");
						$jquery_loaded = true;
					}
					wp_print_scripts("jquery-ui-core");
					?>
					<link type="text/css" rel="stylesheet" href="<?php echo plugins_url("js/theme/jquery.ui.all.css", __FILE__); ?>" />
					<script type="text/javascript" src="<?php echo plugins_url("js/jquery.ui.datepicker.min.js", __FILE__); ?>"></script>
					<script type="text/javascript">
					jQuery(function() {
						jQuery(".datepicker").datepicker();
					});
					</script>
					<?php
				}
				if ( !empty($options["required_fields_asterisk"]) ) {
					if ( empty($jquery_loaded) ) {
						wp_print_scripts("jquery");
						$jquery_loaded = true;
					}
					?>
					<script type="text/javascript">
					jQuery(document).ready(function() {
						jQuery("#user_login").parent().prepend("*");
						jQuery("#user_email").parent().prepend("*");
					});
					</script>
					<?php
				}
				if ( !empty($options["user_set_password"]) && !empty($options["show_password_meter"]) ) {
					if ( empty($jquery_loaded) ) {
						wp_print_scripts("jquery");
						$jquery_loaded = true;
					}
					?>
					<script type="text/javascript">
						/* <![CDATA[ */
						pwsL10n={
							empty: "<?php echo stripslashes($options["message_empty_password"]); ?>",
							short: "<?php echo stripslashes($options["message_short_password"]); ?>",
							bad: "<?php echo stripslashes($options["message_bad_password"]); ?>",
							good: "<?php echo stripslashes($options["message_good_password"]); ?>",
							strong: "<?php echo stripslashes($options["message_strong_password"]); ?>",
							mismatch: "<?php echo stripslashes($options["message_mismatch_password"]); ?>"
						}
						/* ]]> */
						function check_pass_strength() {
							var pass1 = jQuery("#pass1").val(), user = jQuery("#user_login").val(), pass2 = jQuery("#pass2").val(), strength;
							jQuery("#pass-strength-result").removeClass("short bad good strong mismatch");
							if ( !pass1 ) {
								jQuery("#pass-strength-result").html( pwsL10n.empty );
								return;
							}
							strength = passwordStrength(pass1, user, pass2);
							switch ( strength ) {
								case 2:
									jQuery("#pass-strength-result").addClass("bad").html( pwsL10n["bad"] );
									break;
								case 3:
									jQuery("#pass-strength-result").addClass("good").html( pwsL10n["good"] );
									break;
								case 4:
									jQuery("#pass-strength-result").addClass("strong").html( pwsL10n["strong"] );
									break;
								case 5:
									jQuery("#pass-strength-result").addClass("mismatch").html( pwsL10n["mismatch"] );
									break;
								default:
									jQuery("#pass-strength-result").addClass("short").html( pwsL10n["short"] );
							}
						}
						function passwordStrength(password1, username, password2) {
							var shortPass = 1, badPass = 2, goodPass = 3, strongPass = 4, mismatch = 5, symbolSize = 0, natLog, score;
							// password 1 != password 2
							if ( (password1 != password2) && password2.length > 0 )
								return mismatch
							//password < 4
							if ( password1.length < <?php echo $options["min_password_length"]; ?> )
								return shortPass
							//password1 == username
							if ( password1.toLowerCase() == username.toLowerCase() )
								return badPass;
							if ( password1.match(/[0-9]/) )
								symbolSize +=10;
							if ( password1.match(/[a-z]/) )
								symbolSize +=26;
							if ( password1.match(/[A-Z]/) )
								symbolSize +=26;
							if ( password1.match(/[^a-zA-Z0-9]/) )
								symbolSize +=31;
							natLog = Math.log( Math.pow(symbolSize, password1.length) );
								score = natLog / Math.LN2;
							if ( score < 40 )
								return badPass
							if ( score < 56 )
								return goodPass
							return strongPass;
						}
						jQuery(document).ready( function() {
							jQuery("#pass1").val("").keyup( check_pass_strength );
							jQuery("#pass2").val("").keyup( check_pass_strength );
						});
					</script>
					<?php
				}
			} else {
				if ( !empty($options["custom_login_page_css"]) ) {
					echo "\n<style type=\"text/css\">";
					echo "\n", stripslashes($options["custom_login_page_css"]);
					echo "\n</style>";
				}
			}
		}

		function AlterRegisterSignupForm() {
			$options = get_option("register_plus_redux_options");
			if ( !empty($options["starting_tabindex"]) ) $tabindex = $options["starting_tabindex"];
			if ( !empty($options["double_check_email"]) ) {
				if ( isset($_GET["user_email2"]) ) $_POST["user_email2"] = $_GET["user_email2"];
				echo "\n<p id=\"user_email2-p\"><label id=\"user_email2-label\">";
				if ( !empty($options["required_fields_asterisk"]) ) echo "*";
				echo __("Confirm E-mail", "register-plus-redux"), "<br /><input type=\"text\" autocomplete=\"off\" name=\"user_email2\" id=\"user_email2\" class=\"input\" value=\"", $_POST["user_email2"], "\" size=\"25\" ";
				if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
				echo "/></label></p>";
			}
			if ( !is_array($options["show_fields"]) ) $options["show_fields"] = array();
			if ( !is_array($options["required_fields"]) ) $options["required_fields"] = array();
			if ( in_array("first_name", $options["show_fields"]) ) {
				if ( isset($_GET["first_name"]) ) $_POST["first_name"] = $_GET["first_name"];
				echo "\n<p id=\"first_name-p\"><label id=\"first_name-label\">";
				if ( !empty($options["required_fields_asterisk"]) && in_array("first_name", $options["required_fields"]) ) echo "*";
				echo __("First Name", "register-plus-redux"), "<br /><input type=\"text\" name=\"first_name\" id=\"first_name\" class=\"input\" value=\"", $_POST["first_name"],"\" size=\"25\" ";
				if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
				echo "/></label></p>";
			}
			if ( in_array("last_name", $options["show_fields"]) ) {
				if ( isset($_GET["last_name"]) ) $_POST["last_name"] = $_GET["last_name"];
				echo "\n<p id=\"last_name-p\"><label id=\"last_name-label\">";
				if ( !empty($options["required_fields_asterisk"]) && in_array("last_name", $options["required_fields"]) ) echo "*";
				echo __("Last Name", "register-plus-redux"), "<br /><input type=\"text\" name=\"last_name\" id=\"last_name\" class=\"input\" value=\"", $_POST["last_name"], "\" size=\"25\" ";
				if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
				echo "/></label></p>";
			}
			if ( in_array("user_url", $options["show_fields"]) ) {
				if ( isset($_GET["url"]) ) $_POST["url"] = $_GET["url"];
				echo "\n<p id=\"user_url-p\"><label id=\"user_url-label\">";
				if ( !empty($options["required_fields_asterisk"]) && in_array("user_url", $options["required_fields"]) ) echo "*";
				echo __("Website", "register-plus-redux"), "<br /><input type=\"text\" name=\"url\" id=\"user_url\" class=\"input\" value=\"", $_POST["url"], "\" size=\"25\" ";
				if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
				echo "/></label></p>";
			}
			if ( in_array("aim", $options["show_fields"]) ) {
				if ( isset($_GET["aim"]) ) $_POST["aim"] = $_GET["aim"];
				echo "\n<p id=\"aim-p\"><label id=\"aim-label\">";
				if ( !empty($options["required_fields_asterisk"]) && in_array("aim", $options["required_fields"]) ) echo "*";
				echo __("AIM", "register-plus-redux"), "<br /><input type=\"text\" name=\"aim\" id=\"aim\" class=\"input\" value=\"", $_POST["aim"], "\" size=\"25\" ";
				if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
				echo "/></label></p>";
			}
			if ( in_array("yahoo", $options["show_fields"]) ) {
				if ( isset($_GET["yahoo"]) ) $_POST["yahoo"] = $_GET["yahoo"];
				echo "\n<p id=\"yahoo-p\"><label id=\"yahoo-label\">";
				if ( !empty($options["required_fields_asterisk"]) && in_array("yahoo", $options["required_fields"]) ) echo "*";
				echo __("Yahoo IM", "register-plus-redux"), "<br /><input type=\"text\" name=\"yahoo\" id=\"yahoo\" class=\"input\" value=\"", $_POST["yahoo"], "\" size=\"25\" ";
				if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
				echo "/></label></p>";
			}
			if ( in_array("jabber", $options["show_fields"]) ) {
				if ( isset($_GET["jabber"]) ) $_POST["jabber"] = $_GET["jabber"];
				echo "\n<p id=\"jabber-p\"><label id=\"jabber-label\">";
				if ( !empty($options["required_fields_asterisk"]) && in_array("jabber", $options["required_fields"]) ) echo "*";
				echo __("Jabber / Google Talk", "register-plus-redux"), "<br /><input type=\"text\" name=\"jabber\" id=\"jabber\" class=\"input\" value=\"", $_POST["jabber"], "\" size=\"25\" ";
				if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
				echo "/></label></p>";
			}
			if ( in_array("about", $options["show_fields"]) ) {
				if ( isset($_GET["about"]) ) $_POST["about"] = $_GET["about"];
				echo "\n<p id=\"about-p\"><label id=\"about-label\" for=\"about\">";
				if ( !empty($options["required_fields_asterisk"]) && in_array("about", $options["required_fields"]) ) echo "*";
				echo __("About Yourself", "register-plus-redux"), "</label><br />";
				echo "\n<small id=\"about_msg\">", __("Share a little biographical information to fill out your profile. This may be shown publicly.", "register-plus-redux"), "</small><br />";
				echo "\n<textarea name=\"about\" id=\"about\" cols=\"25\" rows=\"5\"";
				if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
				echo ">", stripslashes($_POST["about"]), "</textarea></p>";
			}
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				if ( !empty($v["show_on_registration"]) ) {
					$key = $this->sanitizeText($v["custom_field_name"]);
					if ( isset($_GET[$key]) ) $_POST[$key] = $_GET[$key];
					switch ( $v["custom_field_type"] ) {
						case "text":
						case "url":
							echo "\n<p id=\"$key-p\"><label id=\"$key-label\">";
							if ( !empty($options["required_fields_asterisk"]) && !empty($v["required_on_registration"]) ) echo "*";
							echo stripslashes($v["custom_field_name"]), "<br /><input type=\"text\" name=\"$key\" id=\"$key\" class=\"input\" value=\"", $_POST[$key], "\" size=\"25\" ";
							if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
							echo "/></label></p>";
							break;
						case "select":
							echo "\n<p id=\"$key-p\"><label id=\"$key-label\">";
							if ( !empty($options["required_fields_asterisk"]) && !empty($v["required_on_registration"]) ) echo "*";
							echo stripslashes($v["custom_field_name"]), "<br />";
							echo "\n<select name=\"$key\" id=\"$key\"";
							if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
							echo ">";
							$custom_field_options = explode(",", $v["custom_field_options"]);
							foreach ( $custom_field_options as $custom_field_option ) {
								$option = $this->sanitizeText($custom_field_option);
								echo "<option id=\"$option\" value=\"", stripslashes($custom_field_option), "\"";
								if ( $_POST[$key] == stripslashes($custom_field_option) ) echo " selected=\"selected\"";
								echo ">", stripslashes($custom_field_option), "</option>";
							}
							echo "</select>";
							echo "\n</label></p>";
							break;
						case "checkbox":
							echo "\n<p id=\"$key-p\" style=\"margin-bottom:16px;\"><label id=\"$key-label\">";
							if ( !empty($options["required_fields_asterisk"]) && !empty($v["required_on_registration"]) ) echo "*";
							echo stripslashes($v["custom_field_name"]), "</label><br />";
							$custom_field_options = explode(",", $v["custom_field_options"]);
							foreach ( $custom_field_options as $custom_field_option ) {
								$option = $this->sanitizeText($custom_field_option);
								echo "\n<input type=\"checkbox\" name=\"", $key, "[]\" id=\"$option\" value=\"", stripslashes($custom_field_option), "\" ";
								if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
								if ( is_array($_POST[$key]) && in_array(stripslashes($custom_field_option), $_POST[$key]) ) echo "checked=\"checked\" ";
								if ( !is_array($_POST[$key]) && $_POST[$key] == stripslashes($custom_field_option) ) echo "checked=\"checked\" ";
								echo "/><label id=\"$option-label\" class=\"$key\" for=\"$option\">&nbsp;", stripslashes($custom_field_option), "</label><br />";
							}
							echo "\n</p>";
							break;
						case "radio":
							echo "\n<p id=\"$key-p\" style=\"margin-bottom:16px;\"><label id=\"$key-label\">";
							if ( !empty($options["required_fields_asterisk"]) && !empty($v["required_on_registration"]) ) echo "*";
							echo stripslashes($v["custom_field_name"]), "</label><br />";
							$custom_field_options = explode(",", $v["custom_field_options"]);
							foreach ( $custom_field_options as $custom_field_option ) {
								$option = $this->sanitizeText($custom_field_option);
								echo "\n<input type=\"radio\" name=\"$key\" id=\"$option\" value=\"", stripslashes($custom_field_option), "\" ";
								if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
								if ( $_POST[$key] == stripslashes($custom_field_option) ) echo "checked=\"checked\" ";
								echo "/><label id=\"$option-label\" class=\"$key\" for=\"$option\">&nbsp;", stripslashes($custom_field_option), "</label><br />";
							}
							echo "\n</p>";
							break;
						case "textarea":
							echo "\n<p id=\"$key-p\"><label id=\"$key-label\">";
							if ( !empty($options["required_fields_asterisk"]) && !empty($v["required_on_registration"]) ) echo "*";
							echo stripslashes($v["custom_field_name"]), "<br /><textarea name=\"$key\" id=\"$key\" cols=\"25\" rows=\"5\"";
							if ( !empty($options["starting_tabindex"]) ) echo " tabindex=\"", $tabindex++, "\" ";
							echo ">", $_POST[$key], "</textarea></label></p>";
							break;
						case "date":
							echo "\n<p id=\"$key-p\"><label id=\"$key-label\">";
							if ( !empty($options["required_fields_asterisk"]) && !empty($v["required_on_registration"]) ) echo "*";
							echo stripslashes($v["custom_field_name"]), "<br /><input type=\"text\" name=\"$key\" id=\"$key\" class=\"datepicker\" value=\"", $_POST[$key], "\" size=\"25\" ";
							if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
							echo " /></label></p>";
							break;
						case "hidden":
							echo "\n<input type=\"hidden\" name=\"$key\" id=\"$key\" value=\"", $_POST[$key], "\" ";
							if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
							echo "/>";
							break;
						case "static":
							echo "\n<p id=\"$key-p\"><small id=\"$key-small\">", stripslashes($v["custom_field_options"]), "</small></p>";
							break;
					}
				}
			}
			if ( !empty($options["user_set_password"]) ) {
				if ( isset($_GET["password"]) ) $_POST["password"] = $_GET["password"];
				echo "\n<p id=\"pass1-p\"><label id=\"pass1-label\">";
				if ( !empty($options["required_fields_asterisk"]) ) echo "*";
				echo __("Password", "register-plus-redux"), "<br /><input type=\"password\" autocomplete=\"off\" name=\"pass1\" id=\"pass1\" value=\"", $_POST["password"], "\" size=\"25\" ";
				if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
				echo "/></label></p>";
				echo "\n<p id=\"pass2-p\"><label id=\"pass2-label\">";
				if ( !empty($options["required_fields_asterisk"]) ) echo "*";
				echo __("Confirm Password", "register-plus-redux"), "<br /><input type=\"password\" autocomplete=\"off\" name=\"pass2\" id=\"pass2\" value=\"", $_POST["password"], "\" size=\"25\" ";
				if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
				echo "/></label></p>";
				if ( !empty($options["show_password_meter"]) ) {
					echo "\n<div id=\"pass-strength-result\">", stripslashes($options["message_empty_password"]), "</div>";
					echo "\n<small id=\"pass_strength_msg\">", sprintf(__("Your password must be at least %d characters long. To make your password stronger, use upper and lower case letters, numbers, and the following symbols !@#$%%^&amp;*()", "register-plus-redux"), $options["min_password_length"]), "</small>";
				}
			}
			if ( !empty($options["enable_invitation_code"]) ) {
				if ( isset($_GET["invitation_code"]) ) $_POST["invitation_code"] = $_GET["invitation_code"];
				echo "\n<p id=\"invitation_code-p\"><label id=\"invitation_code-label\">";
				if ( !empty($options["required_fields_asterisk"]) && !empty($options["require_invitation_code"]) ) echo "*";
				echo __("Invitation Code", "register-plus-redux"), "<br /><input type=\"text\" name=\"invitation_code\" id=\"invitation_code\" class=\"input\" value=\"", $_POST["invitation_code"], "\" size=\"25\" ";
				if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
				echo "/></label></p>";
				if ( !empty($options["require_invitation_code"]) )
					echo "\n<small id=\"invitation_code_msg\">", __("This website is currently closed to public registrations. You will need an invitation code to register.", "register-plus-redux"), "</small>";
				else
					echo "\n<small id=\"invitation_code_msg\">", __("Have an invitation code? Enter it here. (This is not required)", "register-plus-redux"), "</small>";
			}
			if ( !empty($options["show_disclaimer"]) ) {
				if ( isset($_GET["accept_disclaimer"]) ) $_POST["accept_disclaimer"] = $_GET["accept_disclaimer"];
				echo "\n<p id=\"disclaimer-p\">";
				echo "\n	<label id=\"disclaimer_title\">", stripslashes($options["message_disclaimer_title"]), "</label><br />";
				echo "\n	<span name=\"disclaimer\" id=\"disclaimer\">", stripslashes($options["message_disclaimer"]), "</span>";
				if ( !empty($options["require_disclaimer_agree"]) ) {
					echo "\n	<label id=\"accept_disclaimer-label\" class=\"accept_check\"><input type=\"checkbox\" name=\"accept_disclaimer\" id=\"accept_disclaimer\" value=\"1\""; if ( !empty($_POST["accept_disclaimer"]) ) echo " checked=\"checked\"";
					if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
					echo "/>&nbsp;", stripslashes($options["message_disclaimer_agree"]), "</label>";
				}
				echo "\n</p>";
			}
			if ( !empty($options["show_license"]) ) {
				if ( isset($_GET["accept_license"]) ) $_POST["accept_license"] = $_GET["accept_license"];
				echo "\n<p id=\"license-p\">";
				echo "\n	<label id=\"license_title\">", stripslashes($options["message_license_title"]), "</label><br />";
				echo "\n	<span name=\"license\" id=\"license\">", stripslashes($options["message_license"]), "</span>";
				if ( !empty($options["require_license_agree"]) ) {
					echo "\n	<label id=\"accept_license-label\" class=\"accept_check\"><input type=\"checkbox\" name=\"accept_license\" id=\"accept_license\" value=\"1\""; if ( !empty($_POST["accept_license"]) ) echo " checked=\"checked\"";
					if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
					echo "/>&nbsp;", stripslashes($options["message_license_agree"]), "</label>";
				}
				echo "\n</p>";
			}
			if ( !empty($options["show_privacy_policy"]) ) {
				if ( isset($_GET["accept_privacy_policy"]) ) $_POST["accept_privacy_policy"] = $_GET["accept_privacy_policy"];
				echo "\n<p id=\"privacy_policy-p\">";
				echo "\n	<label id=\"privacy_policy_title\">", stripslashes($options["message_privacy_policy_title"]), "</label><br />";
				echo "\n	<span name=\"privacy_policy\" id=\"privacy_policy\">", stripslashes($options["message_privacy_policy"]), "</span>";
				if ( !empty($options["require_privacy_policy_agree"]) ) {
					echo "\n	<label id=\"accept_privacy_policy-label\" class=\"accept_check\"><input type=\"checkbox\" name=\"accept_privacy_policy\" id=\"accept_privacy_policy\" value=\"1\""; if ( !empty($_POST["accept_privacy_policy"]) ) echo " checked=\"checked\"";
					if ( !empty($options["starting_tabindex"]) ) echo "tabindex=\"", $tabindex++, "\" ";
					echo "/>&nbsp;", stripslashes($options["message_privacy_policy_agree"]), "</label>";
				}
				echo "\n</p>";
			}
		}

		function CheckRegistrationForm( $errors, $sanitized_user_login, $user_email ) {
			$options = get_option("register_plus_redux_options");
			if ( !empty($options["username_is_email"]) && in_array('empty_username', $errors->get_error_codes()) ) {
				unset($errors->errors['empty_username']);
				unset($errors->error_data['empty_username']);
				$sanitized_user_login = sanitize_user($_POST['user_email']);
				if ( !in_array('empty_username', $errors->get_error_codes()) && $sanitized_user_login != $_POST['user_email'] ) {
					$errors->add("invalid_email", __("<strong>ERROR</strong>: Email address is not appropriate as a username, please enter another email address.", "register-plus-redux"));
				}
			}
			if ( !empty($sanitized_user_login) ) {
				global $wpdb;
				if ( $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key=\"stored_user_login\" AND meta_value=\"%s\"", $sanitized_user_login)) ) {
					$errors->add("username_exists", __("<strong>ERROR</strong>: This username is already registered, please choose another one.", "register-plus-redux"));
				}
			}
			if ( !empty($options["double_check_email"]) ) {
				if ( empty($_POST["user_email"]) || empty($_POST["user_email2"]) ) {
					$errors->add("empty_email", __("<strong>ERROR</strong>: Please confirm your e-mail address.", "register-plus-redux"));
				} elseif ( $_POST["user_email"] != $_POST["user_email2"] ) {
					$errors->add("email_mismatch", __("<strong>ERROR</strong>: Your e-mail address does not match.", "register-plus-redux"));
				}
			}
			if ( !is_array($options["show_fields"]) ) $options["show_fields"] = array();
			if ( !is_array($options["required_fields"]) ) $options["required_fields"] = array();
			if ( in_array("first_name", $options["show_fields"]) && in_array("first_name", $options["required_fields"]) ) {
				if ( empty($_POST["first_name"]) ) {
					$errors->add("empty_first_name", __("<strong>ERROR</strong>: Please enter your first name.", "register-plus-redux"));
				}
			}
			if ( in_array("last_name", $options["show_fields"]) && in_array("last_name", $options["required_fields"]) ) {
				if ( empty($_POST["last_name"]) ) {
					$errors->add("empty_last_name", __("<strong>ERROR</strong>: Please enter your last name.", "register-plus-redux"));
				}
			}
			if ( in_array("user_url", $options["show_fields"]) && in_array("user_url", $options["required_fields"]) ) {
				if ( empty($_POST["url"]) ) {
					$errors->add("empty_user_url", __("<strong>ERROR</strong>: Please enter your website URL.", "register-plus-redux"));
				}
			}
			if ( in_array("aim", $options["show_fields"]) && in_array("aim", $options["required_fields"]) ) {
				if ( empty($_POST["aim"]) ) {
					$errors->add("empty_aim", __("<strong>ERROR</strong>: Please enter your AIM username.", "register-plus-redux"));
				}
			}
			if ( in_array("yahoo", $options["show_fields"]) && in_array("yahoo", $options["required_fields"]) ) {
				if ( empty($_POST["yahoo"]) ) {
					$errors->add("empty_yahoo", __("<strong>ERROR</strong>: Please enter your Yahoo IM username.", "register-plus-redux"));
				}
			}
			if ( in_array("jabber", $options["show_fields"]) && in_array("jabber", $options["required_fields"]) ) {
				if ( empty($_POST["jabber"]) ) {
					$errors->add("empty_jabber", __("<strong>ERROR</strong>: Please enter your Jabber / Google Talk username.", "register-plus-redux"));
				}
			}
			if ( in_array("about", $options["show_fields"]) && in_array("about", $options["required_fields"]) ) {
				if ( empty($_POST["about"]) ) {
					$errors->add("empty_about", __("<strong>ERROR</strong>: Please enter some information about yourself.", "register-plus-redux"));
				}
			}
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				$key = $this->sanitizeText($v["custom_field_name"]);
				if ( !empty($v["show_on_registration"]) && !empty($v["required_on_registration"]) && empty($_POST[$key]) ) {
					$errors->add("empty_$key", sprintf(__("<strong>ERROR</strong>: Please complete %s.", "register-plus-redux"), $v["custom_field_name"]));
				}
				if ( !empty($v["show_on_registration"]) && $v["custom_field_type"] == "text" && !empty($v["custom_field_options"]) && !preg_match($v["custom_field_options"], $_POST[$key]) ) {
					$errors->add("invalid_$key", sprintf(__("<strong>ERROR</strong>: Please enter new value for %s, value specified is not in the correct format.", "register-plus-redux"), $v["custom_field_name"]));
				}
			}
			if ( !empty($options["user_set_password"]) ) {
				if ( empty($_POST["pass1"]) || empty($_POST["pass2"]) ) {
					$errors->add("empty_password", __("<strong>ERROR</strong>: Please enter a password.", "register-plus-redux"));
				} elseif ( $_POST["pass1"] != $_POST["pass2"] ) {
					$errors->add("password_mismatch", __("<strong>ERROR</strong>: Your password does not match.", "register-plus-redux"));
				} elseif ( strlen($_POST["pass1"]) < $options["min_password_length"] ) {
					$errors->add("password_length", sprintf(__("<strong>ERROR</strong>: Your password must be at least %d characters in length.", "register-plus-redux"), $options["min_password_length"]));
				} else {
					$_POST["password"] = $_POST["pass1"];
				}
			}
			if ( !empty($options["enable_invitation_code"]) ) {
				if ( empty($_POST["invitation_code"]) && !empty($options["require_invitation_code"]) ) {
					$errors->add("empty_invitation_code", __("<strong>ERROR</strong>: Please enter an invitation code.", "register-plus-redux"));
				} elseif ( !empty($_POST["invitation_code"]) ) {
					$invitation_code = $_POST["invitation_code"];
					$invitation_code_bank = $options["invitation_code_bank"];
					if ( empty($options["invitation_code_case_sensitive"]) ) {
						$invitation_code = strtolower($_POST["invitation_code"]);
						if ( !is_array($invitation_code_bank) ) $invitation_code_bank = array();
						foreach ( $invitation_code_bank as $k => $v )
							$invitation_code_bank[$k] = strtolower($v);
					}
					if ( !in_array($invitation_code, $invitation_code_bank) ) {
						$errors->add("invitation_code_mismatch", __("<strong>ERROR</strong>: That invitation code is invalid.", "register-plus-redux"));
					}
				}
			}
			if ( !empty($options["show_disclaimer"]) && !empty($options["require_disclaimer_agree"]) ) {
				if ( empty($_POST["accept_disclaimer"]) ) {
					$errors->add("accept_disclaimer", sprintf(__("<strong>ERROR</strong>: Please accept the %s", "register-plus-redux"), stripslashes($options["message_disclaimer_title"])) . ".");
				}
			}
			if ( !empty($options["show_license"]) && !empty($options["require_license_agree"]) ) {
				if ( empty($_POST["accept_license"]) ) {
					$errors->add("accept_license", sprintf(__("<strong>ERROR</strong>: Please accept the %s", "register-plus-redux"), stripslashes($options["message_license_title"])) . ".");
				}
			}
			if ( !empty($options["show_privacy_policy"]) && !empty($options["require_privacy_policy_agree"]) ) {
				if ( empty($_POST["accept_privacy_policy"]) ) {
					$errors->add("accept_privacy_policy", sprintf(__("<strong>ERROR</strong>: Please accept the %s", "register-plus-redux"), stripslashes($options["message_privacy_policy_title"])) . ".");
				}
			}
			return $errors;
		}

		function CheckSignupForm( $result ) {
			$options = get_option("register_plus_redux_options");
			if ( !empty($result["user_name"]) ) {
				global $wpdb;
				if ( $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key=\"stored_user_login\" AND meta_value=\"%s\"", $result["user_name"])) ) {
					$result['errors']->add("username_exists", __("<strong>ERROR</strong>: This username is already registered, please choose another one.", "register-plus-redux"));
				}
			}
			if ( !empty($options["double_check_email"]) ) {
				if ( empty($_POST["user_email"]) || empty($_POST["user_email2"]) ) {
					$result['errors']->add("empty_email", __("<strong>ERROR</strong>: Please confirm your e-mail address.", "register-plus-redux"));
				} elseif ( $_POST["user_email"] != $_POST["user_email2"] ) {
					$result['errors']->add("email_mismatch", __("<strong>ERROR</strong>: Your e-mail address does not match.", "register-plus-redux"));
				}
			}
			if ( !is_array($options["show_fields"]) ) $options["show_fields"] = array();
			if ( !is_array($options["required_fields"]) ) $options["required_fields"] = array();
			if ( in_array("first_name", $options["show_fields"]) && in_array("first_name", $options["required_fields"]) ) {
				if ( empty($_POST["first_name"]) ) {
					$result['errors']->add("empty_first_name", __("<strong>ERROR</strong>: Please enter your first name.", "register-plus-redux"));
				}
			}
			if ( in_array("last_name", $options["show_fields"]) && in_array("last_name", $options["required_fields"]) ) {
				if ( empty($_POST["last_name"]) ) {
					$result['errors']->add("empty_last_name", __("<strong>ERROR</strong>: Please enter your last name.", "register-plus-redux"));
				}
			}
			if ( in_array("user_url", $options["show_fields"]) && in_array("user_url", $options["required_fields"]) ) {
				if ( empty($_POST["url"]) ) {
					$result['errors']->add("empty_user_url", __("<strong>ERROR</strong>: Please enter your website URL.", "register-plus-redux"));
				}
			}
			if ( in_array("aim", $options["show_fields"]) && in_array("aim", $options["required_fields"]) ) {
				if ( empty($_POST["aim"]) ) {
					$result['errors']->add("empty_aim", __("<strong>ERROR</strong>: Please enter your AIM username.", "register-plus-redux"));
				}
			}
			if ( in_array("yahoo", $options["show_fields"]) && in_array("yahoo", $options["required_fields"]) ) {
				if ( empty($_POST["yahoo"]) ) {
					$result['errors']->add("empty_yahoo", __("<strong>ERROR</strong>: Please enter your Yahoo IM username.", "register-plus-redux"));
				}
			}
			if ( in_array("jabber", $options["show_fields"]) && in_array("jabber", $options["required_fields"]) ) {
				if ( empty($_POST["jabber"]) ) {
					$result['errors']->add("empty_jabber", __("<strong>ERROR</strong>: Please enter your Jabber / Google Talk username.", "register-plus-redux"));
				}
			}
			if ( in_array("about", $options["show_fields"]) && in_array("about", $options["required_fields"]) ) {
				if ( empty($_POST["about"]) ) {
					$result['errors']->add("empty_about", __("<strong>ERROR</strong>: Please enter some information about yourself.", "register-plus-redux"));
				}
			}
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				$key = $this->sanitizeText($v["custom_field_name"]);
				if ( !empty($v["show_on_registration"]) && !empty($v["required_on_registration"]) && empty($_POST[$key]) ) {
					$result['errors']->add("empty_$key", sprintf(__("<strong>ERROR</strong>: Please complete %s.", "register-plus-redux"), $v["custom_field_name"]));
				}
				if ( !empty($v["show_on_registration"]) && $v["custom_field_type"] == "text" && !empty($v["custom_field_options"]) && !preg_match($v["custom_field_options"], $_POST[$key]) ) {
					$result['errors']->add("invalid_$key", sprintf(__("<strong>ERROR</strong>: Please enter new value for %s, value specified is not in the correct format.", "register-plus-redux"), $v["custom_field_name"]));
				}
			}
			if ( !empty($options["user_set_password"]) ) {
				if ( empty($_POST["pass1"]) || empty($_POST["pass2"]) ) {
					$result['errors']->add("empty_password", __("<strong>ERROR</strong>: Please enter a password.", "register-plus-redux"));
				} elseif ( $_POST["pass1"] != $_POST["pass2"] ) {
					$result['errors']->add("password_mismatch", __("<strong>ERROR</strong>: Your password does not match.", "register-plus-redux"));
				} elseif ( strlen($_POST["pass1"]) < $options["min_password_length"] ) {
					$result['errors']->add("password_length", sprintf(__("<strong>ERROR</strong>: Your password must be at least %d characters in length.", "register-plus-redux"), $options["min_password_length"]));
				} else {
					$_POST["password"] = $_POST["pass1"];
				}
			}
			if ( !empty($options["enable_invitation_code"]) ) {
				if ( empty($_POST["invitation_code"]) && !empty($options["require_invitation_code"]) ) {
					$result['errors']->add("empty_invitation_code", __("<strong>ERROR</strong>: Please enter an invitation code.", "register-plus-redux"));
				} elseif ( !empty($_POST["invitation_code"]) ) {
					$invitation_code = $_POST["invitation_code"];
					$invitation_code_bank = $options["invitation_code_bank"];
					if ( empty($options["invitation_code_case_sensitive"]) ) {
						$invitation_code = strtolower($_POST["invitation_code"]);
						if ( !is_array($invitation_code_bank) ) $invitation_code_bank = array();
						foreach ( $invitation_code_bank as $k => $v )
							$invitation_code_bank[$k] = strtolower($v);
					}
					if ( !in_array($invitation_code, $invitation_code_bank) ) {
						$result['errors']->add("invitation_code_mismatch", __("<strong>ERROR</strong>: That invitation code is invalid.", "register-plus-redux"));
					}
				}
			}
			if ( !empty($options["show_disclaimer"]) && !empty($options["require_disclaimer_agree"]) ) {
				if ( empty($_POST["accept_disclaimer"]) ) {
					$result['errors']->add("accept_disclaimer", sprintf(__("<strong>ERROR</strong>: Please accept the %s", "register-plus-redux"), stripslashes($options["message_disclaimer_title"])) . ".");
				}
			}
			if ( !empty($options["show_license"]) && !empty($options["require_license_agree"]) ) {
				if ( empty($_POST["accept_license"]) ) {
					$result['errors']->add("accept_license", sprintf(__("<strong>ERROR</strong>: Please accept the %s", "register-plus-redux"), stripslashes($options["message_license_title"])) . ".");
				}
			}
			if ( !empty($options["show_privacy_policy"]) && !empty($options["require_privacy_policy_agree"]) ) {
				if ( empty($_POST["accept_privacy_policy"]) ) {
					$result['errors']->add("accept_privacy_policy", sprintf(__("<strong>ERROR</strong>: Please accept the %s", "register-plus-redux"), stripslashes($options["message_privacy_policy_title"])) . ".");
				}
			}
			return $result;
		}

		function DatepickerHead() {
			//global $pagenow;
			//echo $pagenow;
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				if ( !empty($v["show_on_profile"]) ) {
					if ( $v["custom_field_type"] == "date" ) {
						$show_custom_date_fields = true;
						break;
					}
				}
			}
			if ( !empty($show_custom_date_fields) ) {
				if ( empty($jquery_loaded) ) {
					wp_print_scripts("jquery");
					$jquery_loaded = true;
				}
				wp_print_scripts("jquery-ui-core");
				?>
				<link type="text/css" rel="stylesheet" href="<?php echo plugins_url("js/theme/jquery.ui.all.css", __FILE__); ?>" />
				<script type="text/javascript" src="<?php echo plugins_url("js/jquery.ui.datepicker.min.js", __FILE__); ?>"></script>
				<script type="text/javascript">
				jQuery(function() {
					jQuery(".datepicker").datepicker();
				});
				</script>
				<?php
			}
		}

		function ShowCustomFields( $profileuser ) {
			$custom_fields = get_option("register_plus_redux_custom_fields");
			
			if ( !empty($options["enable_invitation_code"]) || is_array($custom_fields) ) {
				echo "<h3>", __("Additional Information", "register-plus-redux"), "</h3>";
				echo "<table class=\"form-table\">";
				if ( !empty($options["enable_invitation_code"]) ) {
					echo "\n	<tr>";
					echo "\n		<th><label for=\"invitation_code\">", __("Invitation Code", "register-plus-redux"), "</label></th>";
					echo "\n		<td><input type=\"text\" name=\"invitation_code\" id=\"invitation_code\" value=\"$profileuser->invitation_code\" class=\"regular-text\" readonly=\"readonly\" /></td>";
					echo "\n	</tr>";
				}
				if ( is_array($custom_fields) ) {
					foreach ( $custom_fields as $k => $v ) {
						if ( current_user_can("edit_users") || !empty($v["show_on_profile"]) ) {
							$key = $this->sanitizeText($v["custom_field_name"]);
							$value = get_user_meta($profileuser->ID, $key, true);
							echo "\n	<tr>";
							echo "\n		<th><label for=\"$key\">", stripslashes($v["custom_field_name"]);
							if ( empty($v["show_on_profile"]) ) echo " <span class=\"description\">(hidden)</span>";
							if ( !empty($v["required_on_registration"]) ) echo " <span class=\"description\">(required)</span>";
							echo "</label></th>";
							switch ( $v["custom_field_type"] ) {
								case "text":
								case "url":
									echo "\n		<td><input type=\"text\" name=\"$key\" id=\"$key\" value=\"$value\" class=\"regular-text\" /></td>";
									break;
								case "select":
									echo "\n		<td>";
									echo "\n			<select name=\"$key\" id=\"$key\" style=\"width: 15em;\">";
									$custom_field_options = explode(",", $v["custom_field_options"]);
									foreach ( $custom_field_options as $custom_field_option ) {
										echo "<option value=\"", stripslashes($custom_field_option), "\"";
										if ( $value == stripslashes($custom_field_option) ) echo " selected=\"selected\"";
										echo ">", stripslashes($custom_field_option), "</option>";
									}
									echo "</select>";
									echo "\n		</td>";
									break;
								case "checkbox":
									echo "\n		<td>";
									$custom_field_options = explode(",", $v["custom_field_options"]);
									$values = explode(",", $value);
									foreach ( $custom_field_options as $custom_field_option ) {
										echo "\n			<label><input type=\"checkbox\" name=\"$key", "[]\" value=\"", stripslashes($custom_field_option), "\"";
										if ( in_array(stripslashes($custom_field_option), $values) ) echo " checked=\"checked\"";
										echo " />&nbsp;", stripslashes($custom_field_option), "</label><br />";
									}
									echo "\n		</td>";
									break;
								case "radio":
									echo "\n		<td>";
									$custom_field_options = explode(",", $v["custom_field_options"]);
									foreach ( $custom_field_options as $custom_field_option ) {
										echo "\n			<label><input type=\"radio\" name=\"$key\" value=\"", stripslashes($custom_field_option), "\"";
										if ( $value == stripslashes($custom_field_option) ) echo " checked=\"checked\"";
										echo " class=\"tog\">&nbsp;", stripslashes($custom_field_option), "</label><br />";
									}
									echo "\n		</td>";
									break;
								case "textarea":
									echo "\n		<td><textarea name=\"$key\" id=\"$key\" cols=\"25\" rows=\"5\">", stripslashes($value), "</textarea></td>";
									break;
								case "date":
									echo "\n		<td><input type=\"text\" name=\"$key\" id=\"$key\" class=\"datepicker\" value=\"$value\" /></td>";
									break;
								case "hidden":
									echo "\n		<td><input type=\"text\" disabled=\"disabled\" name=\"$key\" id=\"$key\" value=\"$value\" /></td>";
									break;
								case "static":
									echo "\n		<td><span class=\"description\">", stripslashes($v["custom_field_options"]), "</span></td>";
									break;
							}
							echo "\n	</tr>";
						}
					}
				}
				echo "</table>";
			}
		}

		function SaveCustomFields( $user_id ) {
			global $wpdb;
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				if ( current_user_can("edit_users") || !empty($v["show_on_profile"]) ) {
					$key = $this->sanitizeText($v["custom_field_name"]);
					if ( is_array($_POST[$key]) ) $_POST[$key] = implode(",", $_POST[$key]);
					if ( $v["custom_field_type"] == "url" ) {
						$_POST[$key] = esc_url_raw( $_POST[$key] );
						$_POST[$key] = preg_match("/^(https?|ftps?|mailto|news|irc|gopher|nntp|feed|telnet):/is", $_POST[$key]) ? $_POST[$key] : "http://".$_POST[$key];
					}
					
					$valid_value = true;
					if ( !empty($v["required_on_registration"]) && empty($_POST[$key]) ) $valid_value = false;
					if ( $v["custom_field_type"] == "text" && !empty($v["custom_field_options"]) && !preg_match($v["custom_field_options"], $_POST[$key]) ) $valid_value = false;
					if ( $valid_value ) update_user_meta($user_id, $key, $wpdb->prepare($_POST[$key]));
				}
			}
		}

		function SaveAddedFields ( $user_id ) {
			global $wpdb;
			$options = get_option("register_plus_redux_options");
			if ( !is_array($options["show_fields"]) ) $options["show_fields"] = array();
			if ( in_array("first_name", $options["show_fields"]) && !empty($_POST["first_name"]) ) update_user_meta($user_id, "first_name", $wpdb->prepare($_POST["first_name"]));
			if ( in_array("last_name", $options["show_fields"]) && !empty($_POST["last_name"]) ) update_user_meta($user_id, "last_name", $wpdb->prepare($_POST["last_name"]));
			if ( in_array("url", $options["show_fields"]) && !empty($_POST["url"]) ) {
				$user_url = esc_url_raw( $_POST["url"] );
				$user_url = preg_match("/^(https?|ftps?|mailto|news|irc|gopher|nntp|feed|telnet):/is", $user_url) ? $user_url : "http://".$user_url;
				wp_update_user(array("ID" => $user_id, "user_url" => $wpdb->prepare($user_url)));
			}
			if ( in_array("aim", $options["show_fields"]) && !empty($_POST["aim"]) ) update_user_meta($user_id, "aim", $wpdb->prepare($_POST["aim"]));
			if ( in_array("yahoo", $options["show_fields"]) && !empty($_POST["yahoo"]) ) update_user_meta($user_id, "yim", $wpdb->prepare($_POST["yahoo"]));
			if ( in_array("jabber", $options["show_fields"]) && !empty($_POST["jabber"]) ) update_user_meta($user_id, "jabber", $wpdb->prepare($_POST["jabber"]));
			if ( in_array("about", $options["show_fields"]) && !empty($_POST["about"]) ) update_user_meta($user_id, "description", $wpdb->prepare($_POST["about"]));

			$this->SaveCustomFields($user_id);

			if ( !empty($options["user_set_password"]) && !empty($_POST["password"]) ) {
				$plaintext_pass = $wpdb->prepare($_POST["password"]);
				update_user_option( $user_id, "default_password_nag", false, true );
				wp_set_password($plaintext_pass, $user_id);
			}
			$created_by = "user";
			$ref = explode("?", $_SERVER["HTTP_REFERER"]);
			if ( $ref[0] == site_url("wp-admin/user-new.php") )
				$created_by = "admin";
			if ( $created_by == "admin" && !empty($_POST["pass1"]) ) {
				$plaintext_pass = $wpdb->prepare($_POST["pass1"]);
				update_user_option( $user_id, "default_password_nag", false, true );
				wp_set_password($plaintext_pass, $user_id);
			}
			if ( !empty($options["enable_invitation_code"]) && !empty($_POST["invitation_code"]) )
				update_user_meta($user_id, "invitation_code", $wpdb->prepare($_POST["invitation_code"]));
			$user_info = get_userdata($user_id);
			if ( $created_by == "user" && (!empty($options["verify_user_email"]) || !empty($options["verify_user_admin"])) ) {
				update_user_meta($user_id, "stored_user_login", $wpdb->prepare($user_info->user_login));
				update_user_meta($user_id, "stored_user_password", $wpdb->prepare($plaintext_pass));
				$temp_user_login = $wpdb->prepare("unverified_".wp_generate_password(7, false));
				$wpdb->query("UPDATE $wpdb->users SET user_login = \"$temp_user_login\" WHERE ID = \"$user_id\"");
			}
		}

		function defaultOptions( $key = "" )
		{
			$blogname = stripslashes(wp_specialchars_decode(get_option("blogname"), ENT_QUOTES));
			$default = array(
				"custom_logo_url" => "",
				"verify_user_email" => "0",
				"message_verify_user_email" => __("Please verify your account using the verification link sent to your email address.", "register-plus-redux"),
				"verify_user_admin" => "0",
				"message_verify_user_admin" => __("Your account will be reviewed by an administrator and you will be notified when it is activated.", "register-plus-redux"),
				"delete_unverified_users_after" => "7",

				"username_is_email" => "0",
				"double_check_email" => "0",
				"show_fields" => array(),
				"required_fields" => array(),
				"user_set_password" => "0",
				"min_password_length" => "6",
				"show_password_meter" => "0",
				"message_empty_password" => "Strength Indicator",
				"message_short_password" => "Too Short",
				"message_bad_password" => "Bad Password",
				"message_good_password" => "Good Password",
				"message_strong_password" => "Strong Password",
				"message_mismatch_password" => "Password Mismatch",
				"enable_invitation_code" => "0",
				"require_invitation_code" => "0",
				"invitation_code_case_sensitive" => "0",
				"enable_invitation_tracking_widget" => "0",
				"invitation_code_bank" => array(),
				"show_disclaimer" => "0",
				"message_disclaimer_title" => "Disclaimer",
				"message_disclaimer" => "",
				"require_disclaimer_agree" => "1",
				"message_disclaimer_agree" => "Accept the Disclaimer",
				"show_license" => "0",
				"message_license_title" => "License Agreement",
				"message_license" => "",
				"require_license_agree" => "1",
				"message_license_agree" => "Accept the License Agreement",
				"show_privacy_policy" => "0",
				"message_privacy_policy_title" => "Privacy Policy",
				"message_privacy_policy" => "",
				"require_privacy_policy_agree" => "1",
				"message_privacy_policy_agree" => "Accept the Privacy Policy",
				"default_css" => "1",
				"required_fields_style" => "border:solid 1px #E6DB55; background-color:#FFFFE0;",
				"required_fields_asterisk" => "0",
				"starting_tabindex" => "21",

				"datepicker_firstdayofweek" => "6",
				"datepicker_dateformat" => "mm/dd/yyyy",
				"datepicker_startdate" => "",
				"datepicker_calyear" => "",
				"datepicker_calmonth" => "cur",

				"disable_user_message_registered" => "0",
				"disable_user_message_created" => "0",
				"custom_user_message" => "0",
				"user_message_from_email" => get_option("admin_email"),
				"user_message_from_name" => $blogname,
				"user_message_subject" => "[".$blogname."] ".__("Your Login Information", "register-plus-redux"),
				"user_message_body" => "Username: %user_login%\nPassword: %user_password%\n\n%site_url%\n",
				"send_user_message_in_html" => "0",
				"user_message_newline_as_br" => "0",
				"user_message_login_link" => wp_login_url(),
				"custom_verification_message" => "0",
				"verification_message_from_email" => get_option("admin_email"),
				"verification_message_from_name" => $blogname,
				"verification_message_subject" => "[".$blogname."] ".__("Verify Your Account", "register-plus-redux"),
				"verification_message_body" => "Verification URL: %verification_url%\nPlease use the above link to verify your email address and activate your account\n",
				"send_verification_message_in_html" => "0",
				"verification_message_newline_as_br" => "0",

				"disable_admin_message_registered" => "0",
				"disable_admin_message_created" => "0",
				"custom_admin_message" => "0",
				"admin_message_from_email" => get_option("admin_email"),
				"admin_message_from_name" => $blogname,
				"admin_message_subject" => "[".$blogname."] ".__("New User Registered", "register-plus-redux"),
				"admin_message_body" => "New user registered on your site %blogname%\n\nUsername: %user_login%\nE-mail: %user_email%\n",
				"send_admin_message_in_html" => "0",
				"admin_message_newline_as_br" => "0",

				"custom_registration_page_css" => "",
				"custom_login_page_css" => "",
				
				"registration_redirect" => "",
				"verification_redirect" => "",
				
				"disable_sanitize_key" => "",
				"disable_url_fopen" => ""
			);
			if ( !empty($key) )
				return $default[$key];
			else
				return $default;
		}

		function sendVerificationMessage ( $user_id, $verification_code ) {
			$user_info = get_userdata($user_id);
			$options = get_option("register_plus_redux_options");
			$subject = stripslashes($this->defaultOptions("verification_message_subject"));
			$message = stripslashes($this->defaultOptions("verification_message_body"));
			add_filter("wp_mail_content_type", array($this, "filter_message_content_type_text"), 10, 1);
			if ( !empty($options["custom_verification_message"]) ) {
				$subject = stripslashes($options["verification_message_subject"]);
				$message = stripslashes($options["verification_message_body"]);
				if ( !empty($options["send_verification_message_in_html"]) && !empty($options["verification_message_newline_as_br"]) )
					$message = nl2br($message);
				if ( !empty($options["verification_message_from_name"]) )
					add_filter("wp_mail_from_name", array($this, "filter_verification_message_from_name"), 10, 1);
				if ( !empty($options["verification_message_from_email"]) )
					add_filter("wp_mail_from", array($this, "filter_verification_message_from"), 10, 1);
				if ( !empty($options["send_verification_message_in_html"]) )
					add_filter("wp_mail_content_type", array($this, "filter_message_content_type_html"), 10, 1);
			}
			$subject = $this->replaceKeywords($subject, $user_info);
			$message = $this->replaceKeywords($message, $user_info, "", $verification_code);
			wp_mail($user_info->user_email, $subject, $message);
		}

		function sendUserMessage ( $user_id, $plaintext_pass ) {
			$user_info = get_userdata($user_id);
			$options = get_option("register_plus_redux_options");
			$subject = stripslashes($this->defaultOptions("user_message_subject"));
			$message = stripslashes($this->defaultOptions("user_message_body"));
			add_filter("wp_mail_content_type", array($this, "filter_message_content_type_text"), 10, 1);
			if ( !empty($options["custom_user_message"]) ) {
				$subject = stripslashes($options["user_message_subject"]);
				$message = stripslashes($options["user_message_body"]);
				if ( !empty($options["send_user_message_in_html"]) && !empty($options["user_message_newline_as_br"]) )
					$message = nl2br($message);
				if ( !empty($options["user_message_from_name"]) )
					add_filter("wp_mail_from_name", array($this, "filter_user_message_from_name"), 10, 1);
				if ( !empty($options["user_message_from_email"]) )
					add_filter("wp_mail_from", array($this, "filter_user_message_from"), 10, 1);
				if ( !empty($options["send_user_message_in_html"]) )
					add_filter("wp_mail_content_type", array($this, "filter_message_content_type_html"), 10, 1);
			}
			$subject = $this->replaceKeywords($subject, $user_info);
			$message = $this->replaceKeywords($message, $user_info, $plaintext_pass);
			wp_mail($user_info->user_email, $subject, $message);
		}

		function sendAdminMessage ( $user_id, $plaintext_pass, $verification_code ) {
			$user_info = get_userdata($user_id);
			$options = get_option("register_plus_redux_options");
			$subject = stripslashes($this->defaultOptions("admin_message_subject"));
			$message = stripslashes($this->defaultOptions("admin_message_body"));
			add_filter("wp_mail_content_type", array($this, "filter_message_content_type_text"), 10, 1);
			if ( !empty($options["custom_admin_message"]) ) {
				$subject = stripslashes($options["admin_message_subject"]);
				$message = stripslashes($options["admin_message_body"]);
				if ( !empty($options["send_admin_message_in_html"]) && !empty($options["admin_message_newline_as_br"]) )
					$message = nl2br($message);
				if ( !empty($options["admin_message_from_name"]) )
					add_filter("wp_mail_from_name", array($this, "filter_admin_message_from_name"), 10, 1);
				if ( !empty($options["admin_message_from_email"]) )
					add_filter("wp_mail_from", array($this, "filter_admin_message_from"), 10, 1);
				if ( !empty($options["send_admin_message_in_html"]) )
					add_filter("wp_mail_content_type", array($this, "filter_message_content_type_html"), 10, 1);
			}
			$subject = $this->replaceKeywords($subject, $user_info);
			$message = $this->replaceKeywords($message, $user_info, $plaintext_pass, $verification_code);
			wp_mail(get_option("admin_email"), $subject, $message);
		}

		function replaceKeywords ( $message, $user_info, $plaintext_pass = "", $verification_code = "" ) {
			$blogname = wp_specialchars_decode(get_option("blogname"), ENT_QUOTES);
			$message = str_replace("%blogname%", $blogname, $message);
			$message = str_replace("%site_url%", site_url(), $message);
			$message = str_replace("%user_password%", $plaintext_pass, $message);
			$message = str_replace("%verification_code%", $verification_code, $message);
			$message = str_replace("%verification_link%", wp_login_url()."?verification_code=".$verification_code, $message);
			$message = str_replace("%verification_url%", wp_login_url()."?verification_code=".$verification_code, $message);
			if ( !empty($_SERVER) ) {
				$message = str_replace("%registered_from_ip%", $_SERVER["REMOTE_ADDR"], $message);
				$message = str_replace("%registered_from_host%", gethostbyaddr($_SERVER["REMOTE_ADDR"]), $message);
				$message = str_replace("%http_referer%", $_SERVER["HTTP_REFERER"], $message);
				$message = str_replace("%http_user_agent%", $_SERVER["HTTP_USER_AGENT"], $message);
			}
			$message = str_replace("%user_login%", $user_info->user_login, $message);
			$message = str_replace("%user_email%", $user_info->user_email, $message);
			$message = str_replace("%stored_user_login%", stripslashes(get_user_meta($user_info->ID, "stored_user_login", true)), $message);
			$message = str_replace("%first_name%", stripslashes(get_user_meta($user_info->ID, "first_name", true)), $message);
			$message = str_replace("%last_name%", stripslashes(get_user_meta($user_info->ID, "last_name", true)), $message);
			$message = str_replace("%user_url%", get_user_meta($user_info->ID, "user_url", true), $message);
			$message = str_replace("%aim%", get_user_meta($user_info->ID, "aim", true), $message);
			$message = str_replace("%yahoo%", get_user_meta($user_info->ID, "yahoo", true), $message);
			$message = str_replace("%jabber%", get_user_meta($user_info->ID, "jabber", true), $message);
			$message = str_replace("%about%", stripslashes(get_user_meta($user_info->ID, "description", true)), $message);
			$message = str_replace("%invitation_code%", get_user_meta($user_info->ID, "invitation_code", true), $message);
			$custom_fields = get_option("register_plus_redux_custom_fields");
			if ( !is_array($custom_fields) ) $custom_fields = array();
			foreach ( $custom_fields as $k => $v ) {
				$key = $this->sanitizeText($v["custom_field_name"]);
				if ( !empty($v["show_on_registration"]) )
					$message = str_replace("%$key%", get_user_meta($user_info->ID, $key, true), $message);
			}
			return $message;
		}

		function sanitizeText( $key ) {
			$options = get_option("register_plus_redux_options");
			$key = str_replace(" ", "_", $key);
			$key = strtolower($key);
			if ( empty($options["disable_sanitize_key"]) ) $key = sanitize_key($key);
			return $key;
		}

		function filter_admin_message_from( $from_email ) {
			$options = get_option("register_plus_redux_options");
			return stripslashes($options["admin_message_from_email"]);
		}

		function filter_admin_message_from_name( $from_name ) {
			$options = get_option("register_plus_redux_options");
			return stripslashes($options["admin_message_from_name"]);
		}

		function filter_login_headertitle( $title ) {
			$desc = get_option("blogdescription");
			if ( empty($desc) ) 
				$title = get_option("blogname") . " - " . $desc;
			else
				$title = get_option("blogname");
			return $title;
		}

		function filter_login_headerurl( $href ) {
			return home_url();
		}

		function filter_login_message( $message ) {
			//Throw an error otherwise login_messages filter will not trigger
			$options = get_option("register_plus_redux_options");
			if ( isset($_GET["verification_code"]) ) {
				global $errors;
				$errors->add('invalidverificationcode', __('Invalid verification code.'), 'message');
			}
			return $message;
		}

		function filter_login_messages( $messages ) {
			$options = get_option("register_plus_redux_options");
			if ( isset($_GET["verification_code"]) ) {
				global $wpdb;
				$verification_code = $_GET["verification_code"];
				$user_id = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key=\"email_verification_code\" AND meta_value=\"$verification_code\"");
				if ( !empty($user_id) ) {
					if ( empty($options["verify_user_admin"]) ) {
						$stored_user_login = get_user_meta($user_id, "stored_user_login", true);
						$plaintext_pass = get_user_meta($user_id, "stored_user_password", true);
						$wpdb->query( $wpdb->prepare("UPDATE $wpdb->users SET user_login = \"$stored_user_login\" WHERE ID = \"$user_id\"") );
						delete_user_meta($user_id, "email_verification_code");
						delete_user_meta($user_id, "email_verification_sent");
						delete_user_meta($user_id, "stored_user_login");
						delete_user_meta($user_id, "stored_user_password");
						if ( empty($plaintext_pass) ) {
							$plaintext_pass = wp_generate_password();
							update_user_option( $user_id, "default_password_nag", true, true );
							wp_set_password($plaintext_pass, $user_id);
						}
						if ( empty($options["disable_user_message_registered"]) )
							$this->sendUserMessage($user_id, $plaintext_pass);
						if ( empty($options["user_set_password"]) )
							$messages = sprintf(__("Thank you %s, your account has been verified, your password will be emailed to you.", "register-plus-redux"), $stored_user_login);
						else
							$messages = sprintf(__("Thank you %s, your account has been verified, please login with the password you specified during registration.", "register-plus-redux"), $stored_user_login);
					} elseif ( !empty($options["verify_user_admin"]) ) {
						update_user_meta($user_id, "email_verified", gmdate("Y-m-d H:i:s"));
						$messages = __("Your account will be reviewed by an administrator and you will be notified when it is activated.", "register-plus-redux");
					}
				}
			}
			if ( isset($_GET["checkemail"]) && $_GET["checkemail"] == "registered" ) {
				if ( !empty($options["verify_user_email"]) ) {
					$messages = str_replace(array("\r", "\r\n", "\n"), "", nl2br(stripslashes($options["message_verify_user_email"])));
				} elseif ( !empty($options["verify_user_admin"]) ) {
					$messages = str_replace(array("\r", "\r\n", "\n"), "", nl2br(stripslashes($options["message_verify_user_admin"])));
				}
			}
			return $messages;
		}

		function filter_message_content_type_html( $content_type ) {
			return "text/html";
		}

		function filter_message_content_type_text( $content_type ) {
			return "text/plain";
		}

		function filter_password_reset( $allow, $user_id ) {
			global $wpdb;
			$check = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->usermeta WHERE user_id=\"$user_id\" AND meta_key=\"stored_user_login\"");
			if ( !empty($check) ) $allow = false;
			return $allow;
		}

		function filter_plugin_actions( $actions, $plugin_file, $plugin_data, $context ) {
			// before other links
			array_unshift($actions, "<a href=\"options-general.php?page=register-plus-redux\">".__("Settings", "register-plus-redux")."</a>");
			// ... or after other links
			//$links[] = "<a href=\"options-general.php?page=register-plus-redux\">".__("Settings", "register-plus-redux")."</a>";			
			return $actions;
		}

		function filter_pre_user_login( $user_login ) {
			$options = get_option("register_plus_redux_options");
			if ( !empty($options["username_is_email"]) && !empty($_POST['user_email']) ) $user_login = strtolower(sanitize_user($_POST['user_email']));
			return $user_login;
		}

		function filter_registration_redirect( $redirect_to ) {
			//default: 'wp-login.php?checkemail=registered'
			$options = get_option("register_plus_redux_options");
			if ( !empty($options["registration_redirect"]) ) $redirect_to = stripslashes($options["registration_redirect"]);
			return $redirect_to;
		}

		function filter_update_user_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
			if ( $meta_key == "default_password_nag" ) {
				$options = get_option("register_plus_redux_options");
				if ( !empty($options["user_set_password"]) ) $check = true;
			}
			return $check;
		}

		function filter_user_message_from( $from_email ) {
			$options = get_option("register_plus_redux_options");
			return stripslashes($options["user_message_from_email"]);
		}

		function filter_user_message_from_name( $from_name ) {
			$options = get_option("register_plus_redux_options");
			return stripslashes($options["user_message_from_name"]);
		}

		function filter_verification_message_from( $from_email ) {
			$options = get_option("register_plus_redux_options");
			return stripslashes($options["verification_message_from_email"]);
		}

		function filter_verification_message_from_name( $from_name ) {
			$options = get_option("register_plus_redux_options");
			return stripslashes($options["verification_message_from_name"]);
		}

		function ConflictWarning() {
			if ( current_user_can(10) && isset($_GET["page"]) && $_GET["page"] == "register-plus-redux" )
			echo "\n<div id=\"register-plus-redux-warning\" class=\"updated fade-ff0000\"><p><strong>", __("There is another active plugin that is conflicting with Register Plus Redux. The conflicting plugin is creating its own wp_new_user_notification function, this function is used to alter the messages sent out following the creation of a new user. Please refer to <a href=\"http://radiok.info/blog/wp_new_user_notification-conflicts/\">http://radiok.info/blog/wp_new_user_notification-conflicts/</a> for help resolving this issue.", "register-plus-redux"), "</strong></p></div>";
		}

		function VersionWarning() {
			global $wp_version;
			echo "\n<div id=\"register-plus-redux-warning\" class=\"updated fade-ff0000\"><p><strong>", sprintf(__("Register Plus Redux requires WordPress 3.0 or greater. You are currently using WordPress %s, please upgrade or deactivate Register Plus Redux.", "register-plus-redux"), $wp_version), "</strong></p></div>";
		}
	}
}

if ( class_exists("RegisterPlusReduxPlugin") )
	$registerPlusRedux = new RegisterPlusReduxPlugin();

function custom_wp_new_user_notification() {
	$options = get_option("register_plus_redux_options");
	$do_create = false;
	if ( !empty($options["verify_user_email"]) ) $do_create = true;
	if ( !empty($options["disable_user_message_registered"]) ) $do_create = true;
	if ( !empty($options["disable_user_message_created"]) ) $do_create = true;
	if ( !empty($options["custom_user_message"]) ) $do_create = true;
	if ( !empty($options["verify_user_admin"]) ) $do_create = true;
	if ( !empty($options["disable_admin_message_registered"]) ) $do_create = true;
	if ( !empty($options["disable_admin_message_created"]) ) $do_create = true;
	if ( !empty($options["custom_admin_message"]) ) $do_create = true;
	return $do_create;
}

if ( custom_wp_new_user_notification() == true ) {
	if ( function_exists("wp_new_user_notification") ) {
		add_action("admin_notices", array($registerPlusRedux, "ConflictWarning"), 10, 1);
	}
	
	// Called after user completes registration from wp-login.php
	// Called after admin creates user from wp-admin/user-new.php
	// Called after admin creates new site, which also creates new user from wp-admin/network/edit.php (MS)
	// Called after admin creates user from wp-admin/network/edit.php (MS)
	if ( !function_exists("wp_new_user_notification") ) {
		function wp_new_user_notification($user_id, $plaintext_pass = "") {
			global $wpdb, $registerPlusRedux;
			$created_by = "user";
			$ref = explode("?", $_SERVER["HTTP_REFERER"]);
			if ( $ref[0] == site_url("wp-admin/user-new.php") )
				$created_by = "admin";
			$options = get_option("register_plus_redux_options");
			if ( !empty($options["user_set_password"]) && !empty($_POST["password"]) )
				$plaintext_pass = $wpdb->prepare($_POST["password"]);
			if ( $created_by == "admin" && !empty($_POST["pass1"]) )
				$plaintext_pass = $wpdb->prepare($_POST["pass1"]);
			if ( $created_by == "user" && !empty($options["verify_user_email"]) ) {
				$verification_code = wp_generate_password(20, false);
				update_user_meta($user_id, "email_verification_code", $verification_code);
				update_user_meta($user_id, "email_verification_sent", gmdate("Y-m-d H:i:s"));
				$registerPlusRedux->sendVerificationMessage($user_id, $verification_code);
			}
			if ( $created_by == "user" && empty($options["disable_user_message_registered"]) || 
				$created_by == "admin" && empty($options["disable_user_message_created"]) ) {
				if ( empty($options["verify_user_email"]) && empty($options["verify_user_admin"]) ) {
					$registerPlusRedux->sendUserMessage($user_id, $plaintext_pass);
				}
			}
			if ( $created_by == "user" && empty($options["disable_admin_message_registered"]) || 
				$created_by == "admin" && empty($options["disable_admin_message_created"]) ) {
				$registerPlusRedux->sendAdminMessage($user_id, $plaintext_pass, $verification_code);
			}
		}
	}
}

$options = get_option("register_plus_redux_options");
if ( !empty($options["enable_invitation_tracking_widget"]) )
	include_once("dashboard_invitation_tracking_widget.php");
?>