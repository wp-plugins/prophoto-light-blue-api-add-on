<?php

/*
Plugin Name: Gravity Forms Light Blue API Add-On
Description: This plugin allows Gravity Forms to send data directly to the Light Blue API
Version: 1.0.2
Version date: 08/02/2014
Author: Light Blue Software Ltd
Author URI: http://www.lightbluesoftware.com

Supported Gravity Forms field types:
	Single Line Text
	Paragraph Text
	Hidden
	Drop Down
	Multi Select
	Number
	Checkboxes
	Radio Buttons
	Name
	Date
	Time
	Phone
	Website
	Email
	Address
	
Unsupported Gravity Forms field types:
	File Upload
	List
*/

/*
Copyright 2014 Light Blue Software Ltd

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


add_action('init',  array('GFLightBlueAPI', 'init'));

class GFLightBlueAPI {

private static $name = "Gravity Forms Light Blue Add-On";
private static $path = "gravity-forms-light-blue-api-add-on/gravity-forms-light-blue.php";
private static $version = "0.1";
private static $min_gravityforms_version = "1.6";  // I'm specifying 1.6 in case we decide to change from gform_pre_submission to gform_after_submission


//Plugin starting point. Will load appropriate files
public static function init(){

	add_action("admin_notices", array('GFLightBlueAPI', 'display_gravity_forms_status'));

   if( !self::is_gravityforms_supported()){
	   return;
	}
	
	$plugin_file = self::$path;
	add_filter( "plugin_action_links_{$plugin_file}", array('GFLightBlueAPI', 'plugin_settings_link'), 10, 2 );

	if(is_admin()){  //creates a new Settings page on Gravity Forms' settings screen
			RGForms::add_settings_page("Light Blue API", array("GFLightBlueAPI", "lb_settings_page") );
	}
	
	add_action("gform_pre_submission", Array( "GFLightBlueAPI", "lb_gform_pre_submission" ) );
	
}  // end of init()
    

public static function lb_settings_page(){

	if(!empty($_POST["uninstall"])){
		check_admin_referer("deactivate", "gf_light_blue_deactivate");
		self::deactivate();
		?>
		<div class="updated fade" style="padding:20px;">The Light Blue Gravity Forms add-on has been successfully uninstalled. It can be re-activated from the <a href='plugins.php'>plugins page</a>.</div>
		<?php
		return;
	} elseif(!empty($_POST["gf_light_blue_submit"])){
		check_admin_referer("update", "gf_light_blue_update");
		$settings = array(
			"key" => stripslashes($_POST["gf_light_blue_api_key"]), 
			"date_format" => stripslashes($_POST["gf_light_blue_date_format"]), 
			"decimal_separator" => stripslashes($_POST["gf_light_blue_decimal_separator"]), 
			"debug" => stripslashes($_POST["gf_light_blue_debug"])
			);
		update_option("gf_light_blue_settings", $settings);
	}
	else{
		$settings = get_option("gf_light_blue_settings");
	}

	if ($settings["key"] == '') {
		$api_test_message = "";
	} elseif ( self::lb_test_account( $api_test_message ) ) {
		$api_test_message = "<p style='display:block;margin-top:10px;color:green;'>Account confirmed</p>";
	} else {
		$api_test_message = "<p style='display:block;margin-top:10px;color:red;'>".$api_test_message."</p>";
	}

	?>
	<div class="wrap">
	<h2>Light Blue API Setup</h2>

	<div class="hr-divider"></div>

	<h3>Using the Gravity Forms Light Blue API add on</h3>
	<p>To send data to the Light Blue API, you need to associate the fields in your forms with parameters that the Light Blue API recognises.</p>
	<p>To do this, you need to set the 'Parameter Name' for each field on your forms. You can find the 'Parameter Name' in the form editor, by clicking the 'Edit' button for a field, going to the 'Advanced' tab, checking the 'Allow field to be populated dynamically' box.</p>
	<p>You can find a list of the parameters that the Light Blue API recognises <a href='http://www.lightbluesoftware.com/api/' target='_blank'>on our website</a>. Any fields in your forms that do not have their 'Parameter Name' set, or have a 'Parameter Name' that doesn't match a valid Light Blue API paramater, will be ignored but stored in the Gravity Forms entries database as usual.</p>

	<div class="hr-divider"></div>

	<form method="post" action="" style="margin: 30px 0 30px; clear:both;">
		<?php wp_nonce_field("update", "gf_light_blue_update") ?>
		<h3>Your Light Blue Account</h3>
		<p>If you don't have a subscription to Light Blue's online services, you can <a href='http://www.lightbluesoftware.com' target='_blank'>sign up for one here</a>.</p>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="gf_light_blue_api_key">API Key</label></th>
				<td>
					<input type="text" size="75" id="gf_light_blue_api_key" class="code pre" name="gf_light_blue_api_key" value="<?php echo esc_attr($settings["key"]) ?>"/>
					<small style="display:block;">You can find your Light Blue API key by logging into your account <a href='http://www.lightbluesoftware.com' target='_blank'>on our website</a>.</small>
					<?php print $api_test_message;?>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><label for="gf_light_blue_date_format">Date Format</label></th>
				<td>
					<select name="gf_light_blue_date_format" id="gf_light_blue_date_format">
						<option <?php if( $settings["date_format"] == "dd/mm/yyyy" ) { print "selected='selected'"; } ?> value="dd/mm/yyyy">dd/mm/yyyy</option>
						<option <?php if( $settings["date_format"] == "d/m/yyyy" ) { print "selected='selected'"; } ?> value="d/m/yyyy">d/m/yyyy</option>
						<option <?php if( $settings["date_format"] == "mm/dd/yyyy" ) { print "selected='selected'"; } ?> value="mm/dd/yyyy">mm/dd/yyyy</option>
						<option <?php if( $settings["date_format"] == "m/d/yyyy" ) { print "selected='selected'"; } ?> value="m/d/yyyy">m/d/yyyy</option>
					</select>
					<small style="display:block;">You need to make sure that the format of any date fields in your forms matches the date format you've selected here, or the Light Blue API might not understand dates that you send to it.</small>
				</td>
			</tr>
			<tr>
				<th scope="row">Time Format</th>
				<td>
					<small style="display:block;">You don't need to specify a time format, but the format of any time fields in your forms must be one of the following: hh:mm (i.e. a 24-hour time with leading zeros for the hour), h:mm (i.e. a 24-hour time without leading zeros for the hour), hh:mm AM/PM, h:mm AM/PM</small>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gf_light_blue_decimal_separator">Decimal Separator</label></th>
				<td>
					<input type="text" size="5" id="gf_light_blue_decimal_separator" class="code pre" name="gf_light_blue_decimal_separator" value="<?php echo esc_attr($settings["decimal_separator"]) ?>"/>
					<small style="display:block;">If you send currency values to the Light Blue API, it will assume that you are using a full stop character as your decimal separator (e.g. $10.00) unless you specify a different decimal separator here.</small>
				</td>
			</tr>
			
			
			<tr>
				<th scope="row">Debugging</th>
				<td>
				<label for="gf_light_blue_debug">
					<?php if( isset($settings["debug"]) && ($settings["debug"] == 'on' ) ) { 
						print "<input name='gf_light_blue_debug' type='checkbox' id='gf_light_blue_debug' checked>"; 
					} else { 
						print "<input name='gf_light_blue_debug' type='checkbox' id='gf_light_blue_debug'>"; 
					} ?>
					Email debugging logs to your WordPress admin email address.
				</label>
				<small style="display:block;">(n.b. this option should only be turned on to help Light Blue Software diagnose any problems you have with sending data to the Light Blue API)</small>
				</td>
			</tr>
			<tr>
				<td colspan="2" ><input type="submit" name="gf_light_blue_submit" class="submit button-primary" value="Update Settings" /></td>
			</tr>
		</table>
	</form>

	<div class="hr-divider"></div>

	<form action="" method="post">
		<?php wp_nonce_field("deactivate", "gf_light_blue_deactivate") ?>

		<h3>Deactivate Light Blue API add-on</h3>
		<div class="delete-alert alert_red">
			<h3>Warning</h3>
			<p>Deactivating the Light Blue API add-on for Gravity Forms will mean that your forms will no longer submit data to the Light Blue API.</p>
			<input type="submit" name="uninstall" value="Deactivate Light Blue API Add-On" class="button" onclick="return confirm(\'Really deactivate the Light Blue add-on?\');"/>
		</div>
	</form>


	</div>
	<?php
}



public static function lb_test_account( &$message ) {
	
	$settings = get_option("gf_light_blue_settings");
	$submitted_data = Array();
	$submitted_data["Key"] = $settings["key"];
	
	// Post the submitted values to the Light Blue API account check script
	$response = wp_remote_post( "https://online.lightbluesoftware.com/apiCheck.php", array(
		'method' => 'POST',
		'timeout' => 45,
		'redirection' => 5,
		'body' => $submitted_data,
		)
	);
	
	if ( is_wp_error( $response ) ) {
		$message = "WordPress error: ".$response->get_error_message();
		Return False;
	} else {
		if ( $response["response"]["code"] == 200 ) {
			$status_array = Explode( Chr(13), $response["body"] );
			if( $status_array[0] == 0 ) {
				$message = $status_array[1];
				Return True;
			} else {
				$message = $status_array[1];
				Return False;
			}
		}
	}

}

public static function lb_gform_pre_submission( $form ) {
	$submitted_data = array();
	$settings = get_option("gf_light_blue_settings");
	
	
	if( isset($settings["debug"]) && ($settings["debug"] == 'on' ) ) {  // Send debugging email
		ob_start();
		var_dump($form);
		mail( get_option('admin_email'), 'Light Blue API for Gravity Forms $form', ob_get_contents() );
		ob_end_clean();
		ob_start();
		var_dump($_POST);
		mail( get_option('admin_email'), 'Light Blue API for Gravity Forms $_POST', ob_get_contents() );
		ob_end_clean();
	}
	

	// Build up an array of values submitted via the form
	foreach( $form["fields"] as $field) {
		$label = "";
		$value = "";
		if( is_array( $field["inputs"] ) ) {  // This is multi-input field, like the name field.
			if( $field["type"] == "checkbox" ) {
				if( isset( $field["inputName"] ) && ($field["inputName"] != '') ) {
					$label = $field["inputName"];
					foreach( $field["inputs"] as $input ) {
						if( isset( $_POST["input_".str_replace( '.', '_', $input["id"] )] ) ) {
							if( $value != '' ) { $value .= Chr(13); }
							$value .= stripslashes( $_POST["input_".str_replace('.', '_', $input["id"] )] );
						}
				   }	
				}
			} else {
				foreach( $field["inputs"] as $input ) {
					if( isset( $input["name"] ) && ($input["name"] != '') ) {
						$label = $input["name"];
						if( isset( $_POST["input_".str_replace( '.', '_', $input["id"] )] ) ) {
							$value = stripslashes( $_POST["input_".str_replace('.', '_', $input["id"] )] );
						}
					}
					if ($value != '') {
						$submitted_data[$label] = $value;
					}		
					$value = "";  // Clear $value because we're setting $submitted_data within this loop instead of within the fields loop
			   }
		   }
		} elseif( $field["type"] == "multiselect" ) {
			if( isset( $field["inputName"] ) && ($field["inputName"] != '') ) {
				$label = $field["inputName"];
				if( isset( $_POST["input_" . $field["id"]] ) ) {
					$value_array = $_POST["input_" . $field["id"]];
					foreach( $value_array as $multiselect_value ) {
						if( $value != '' ) { $value .= Chr(13); }
						$value .= stripslashes( $multiselect_value );
					}
				}
			}
		} elseif( $field["type"] == "time" ) {	
			if( isset( $field["inputName"] ) && ($field["inputName"] != '') ) {
				$label = $field["inputName"];
				$hours = 0;
				$minutes = 0;
				$ampm = '';
				if( isset( $_POST["input_" . $field["id"]][0] ) ) {
					$hours = stripslashes( $_POST["input_" . $field["id"]][0] );
				}
				if( isset( $_POST["input_" . $field["id"]][1] ) ) {
					$minutes = stripslashes( $_POST["input_" . $field["id"]][1] );
				}
				if( isset( $_POST["input_" . $field["id"]][2] ) ) {
					$ampm = stripslashes( $_POST["input_" . $field["id"]][2] );
				}
				$value = $hours.":".$minutes;
				if( $ampm != '' ) { $value .= ' '.$ampm; }
			}
		} else {  // This is a single-input field
			if( isset( $field["inputName"] ) && ($field["inputName"] != '') ) {
				$label = $field["inputName"];
				if( isset( $_POST["input_" . $field["id"]] ) ) {
					$value = stripslashes( $_POST["input_" . $field["id"]] );
				}
			}
		}
		if ($value != '') {
			$submitted_data[$label] = $value;
		}	
	}
	
	if( Count( $submitted_data ) == 0 ) { Return; }
	
	$submitted_data["Type"] = "contact form";
	$submitted_data["Source"] = self::$name;
	$submitted_data["SourceAPIVersion"] = self::$version;
	$submitted_data["Key"] = $settings["key"];
	if( isset( $settings["date_format"] ) && ($settings["date_format"] != '') ) {
		$submitted_data["DateFormat"] = $settings["date_format"];
	}
	if( isset( $settings["decimal_separator"] ) && ($settings["decimal_separator"] != '') ) {
		$submitted_data["DecimalSeparator"] = $settings["decimal_separator"];
	}
	
	if( isset($settings["debug"]) && ($settings["debug"] == 'on' ) ) {  // Send debugging email
		ob_start();
		var_dump($submitted_data);
		mail( get_option('admin_email'), 'Light Blue API for Gravity Forms about to send', ob_get_contents() );
		ob_end_clean();
	}	
	
	// Post the submitted values to the Light Blue API
	$response = wp_remote_post( "https://online.lightbluesoftware.com/api.php", array(
		'method' => 'POST',
		'timeout' => 45,
		'redirection' => 5,
		'body' => $submitted_data,
		)
	);
	
	
	if( isset($settings["debug"]) && ($settings["debug"] == 'on' ) ) {  // Send debugging email
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			mail( get_option('admin_email'), 'Light Blue API for Gravity Forms sending error', $error_message );
		} else {
			ob_start();
			var_dump($response);
			mail( get_option('admin_email'), 'Light Blue API for Gravity Forms sent', ob_get_contents() );
			ob_end_clean();
		}
	}
	
	
}  // end of lb_gform_pre_submission




public static function plugin_settings_link( $links ) {  // modify the link by unshifting the array
	$settings_link = '<a href="' . admin_url( 'admin.php?page=gf_settings&subview=Light+Blue+API' ) . '">Settings</a>';
	array_unshift( $links, $settings_link );
	return $links;
}


public static function deactivate(){
	// Remove stored settings
	delete_option("gf_light_blue_settings");

	// Deactivate plugin
	$plugin = self::$path;
	deactivate_plugins($plugin);
	update_option('recently_activated', array($plugin => time()) + (array)get_option('recently_activated'));
}

public static function display_gravity_forms_status() {
	if( self::is_gravity_forms_installed( True ) == 1) {  // Installed, so check version requirements
		if( self::is_gravityforms_supported() == False ) {
			$message = "<p>The ".self::$name." plugin requires <a href='http://www.gravityforms.com' target='_blank'>Gravity Forms</a> version ".self::$min_gravityforms_version.", and you are using version ".GFCommon::$version.". Please update Gravity Forms.</p>";
			print "<div id='message' class='updated'>".$message."</div>";
		}
	}
}

public static function is_gravity_forms_installed( $printStatus = False ) {
	$message = '';
	$installed = 0;
	if(!class_exists('RGForms')) {
		if(file_exists(WP_PLUGIN_DIR.'/gravityforms/gravityforms.php')) {
			$installed = 2;
			$message = "<p>Gravity Forms is installed but not active. Please activate the Gravity Forms plugin on the <a href='".wp_nonce_url(admin_url('plugins.php'))."'>plugin settins page</a> to use the ".self::$name." plugin.</p>";
		} else {
			$installed = 0;
			$message = "<p>The ".self::$name." plugin requires the <a href='http://www.gravityforms.com' target='_blank'>Gravity Forms</a> plugin.</p>";
		}
	} else {
		$installed = 1;
	}
	if(($message != '') && ($printStatus == True)) {
			print "<div id='message' class='updated'>".$message."</div>";
		}
	return $installed;
}

private static function is_gravityforms_supported(){
	if(class_exists("GFCommon")){
		$is_correct_version = version_compare(GFCommon::$version, self::$min_gravityforms_version, ">=");
		return $is_correct_version;
	}
	else{
		return false;
	}
}

}