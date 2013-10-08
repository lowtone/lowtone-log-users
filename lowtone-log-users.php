<?php
/*
 * Plugin Name: Log Users
 * Plugin URI: http://wordpress.lowtone.nl
 * Description: Create a log file of user actions.
 * Version: 1.0
 * Author: Lowtone <info@lowtone.nl>
 * Author URI: http://lowtone.nl
 * License: http://wordpress.lowtone.nl/license
 * Requires: lowtone-lib
 */
/**
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2013, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\log\users
 */

namespace lowtone\log\users {

	use lowtone\content\packages\Package;

	// Includes
	
	if (!include_once WP_PLUGIN_DIR . "/lowtone-content/lowtone-content.php") 
		return trigger_error("Lowtone Content plugin is required", E_USER_ERROR) && false;

	Package::init(array(
			Package::INIT_PACKAGES => array("lowtone\\log"),
			// Package::INIT_MERGED_PATH => __NAMESPACE__,
			Package::INIT_SUCCESS => function() {

				$filename = apply_filters("lowtone_log_users_filename", "users-" . date_i18n("Ymd") . ".log");

				$userString = function($user) {
					if (!($user instanceof \WP_User) && !(($user = get_user_by("id", $user)) instanceof \WP_User))
						throw new \ErrorException("Invalid user");

					return sprintf("%s (ID: %d)", $user->user_login, $user->ID);
				};

				$write = function($id, $action, $message = NULL, $ip = NULL) use ($filename, $userString) {
					$parts = array(
							$userString($id),
							trim($ip) ?: $_SERVER["REMOTE_ADDR"],
							$action
						);

					if ($message = trim($message))
						$parts[] = str_replace("-", "--", $message);

					\lowtone\log\write(implode(" - ", $parts), $filename);
				};

				// User logins

				add_action("wp_login", function($login, $user) use ($write) {

					$write($user->ID, "login");

				}, 0, 2);

				// User profile update
				
				add_action("profile_update", function($id, $old) use ($write, $userString) {
					$new = get_user_by("id", $id);

					$changed = array_filter(array(
							"email" => $new->user_email != $old->user_email,
							"pass" => $new->user_pass != $old->user_pass,
						));

					if (!$changed)
						return;

					$messages = array_map(function($key) {
							switch ($key) {
								case "pass":
									return "password";
							}

							return $key;
						}, array_keys($changed));

					if (count($messages) > 1) {
						$last = array_pop($messages);

						$message = sprintf("%s and %s", implode(", ", $messages), $last);
					} else
						$message = reset($messages);

					$write(get_current_user_id(), "profile", ucfirst(sprintf("%s changed for user %s", $message, $userString($id))));
				}, 0, 2);

				// Change user role
				
				add_action("set_user_role", function($id, $new, $old) use ($write, $userString) {
					$new = (array) $new;
					$old = (array) $old;

					if (!(array_diff($old, $new) || array_diff($new, $old)))
						return;


					$write(get_current_user_id(), "profile", ucfirst(sprintf("Roles changed for user %s", $userString($id))));
				}, 0, 3);

				// Register user
				
				add_action("user_register", function($id) use ($write, $userString) {
					$current = 0 !== ($current = get_current_user_id()) ? $current : $id;

					$write($current, "register", sprintf("Registered user %s", $userString($id))); 
				});

				// For user switching
				
				add_action("switch_to_user", function($to, $from) use ($write, $userString) {

					$from = get_user_by("id", $from);

					$to = get_user_by("id", $to);

					$write($from->ID, "switch", sprintf("Switched from user %s to %s", $userString($from), $userString($to)));

				}, 0, 2);

				add_action("switch_back_user", function($to) use ($write, $userString) {

					$to = get_user_by("id", $to);

					$write($to->ID, "switch", sprintf("Switched back to user %s", $userString($to)));
				
				}, 0);

				add_action("switch_off_user", function($from) use ($write, $userString) {

					$from = get_user_by("id", $from);

					$write($from->ID, "switch", sprintf("Switched off user %s", $userString($from)));

				}, 0);

			}
		));

}