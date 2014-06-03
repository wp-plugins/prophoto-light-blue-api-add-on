<?php

/*
Plugin Name: Light Blue API for ProPhoto Plug-in
Description: This plugin allows sites using the ProPhoto theme to send data directly from their contact forms to the Light Blue API.
Version: 1.0.2
Version date: 03/06/2014
Author: Light Blue Software Ltd
Author URI: http://www.lightbluesoftware.com
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


add_action('init',  array('PPLightBlueAPI', 'init'));

class PPLightBlueAPI {

private static $name = "Light Blue API for ProPhoto Plug-in";
private static $path = "phophoto-light-blue-api/phophoto-light-blue-api.php";
private static $version = "1.0.2";


//Plugin starting point. Will load appropriate files
public static function init(){

	// Check that the ProPhoto 5 theme is installed and active
	if (self::check_prophoto_status() == False) {
		print "<div id='message' class='updated'><p>The ".self::$name." plugin requires <a href='http://http://www.prophoto.com' target='_blank'>ProPhoto 5</a>. Please check that it is installed and activated.</p></div>";
	}
	
	// Add a settings link to the plugins list
	$plugin = plugin_basename(__FILE__); 
	add_filter("plugin_action_links_$plugin", array('PPLightBlueAPI', 'plugin_settings_link') );
	
	// Add a settings link in the Settings menu
	add_action('admin_menu', array('PPLightBlueAPI', 'lb_settings_add_page') );
	
	// Configure the method that will run when a ProPhoto contact form is submitted
	add_action("pp_contact_pre_email", Array( "PPLightBlueAPI", "lb_pp_contact_pre_email" ) );
	
}  // end of init()

 

public static function check_prophoto_status() {
//	Return True;
	Return ( defined( 'pp::CURRENT_VERSION_NUMBER' ) && (pp::CURRENT_VERSION_NUMBER == 5) );
}


public static function lb_settings_add_page() {
	add_options_page('LB Settings Page Title', 'Light Blue / ProPhoto', 'manage_options', 'light_blue_pro_photo_settings', Array( "PPLightBlueAPI", 'lb_settings_page' ) );
}


public static function plugin_settings_link( $links ) {  // modify the link by unshifting the array
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=light_blue_pro_photo_settings' ) . '">Settings</a>';
	array_unshift( $links, $settings_link );
	return $links;
}


public static function lb_settings_page(){

	$inputPrefixMapping = "pp_light_blue_mapping_field_";
	$inputPrefixParam = "pp_light_blue_mapping_param_";

	if(!empty($_POST["pp_light_blue_submit"])){
		check_admin_referer("update", "pp_light_blue_update");
		
		$mappings = "";
		foreach( $_POST as $key => $value ) {
			if(($value != '') && ($value != 'none') && ( substr( $key, 0, strlen( $inputPrefixMapping ) ) == $inputPrefixMapping )) {
				$indexNumber = substr( $key, strlen( $inputPrefixMapping ) );
				if( isset( $_POST[ $inputPrefixParam.$indexNumber ] ) && ($_POST[ $inputPrefixParam.$indexNumber ] != '') ) {
					if( $mappings != "" ) { $mappings .= Chr(13); }
					$mappings .= $value.":".$_POST[ $inputPrefixParam.$indexNumber ];
				}
			}
		}
		
		$settings = array(
			"key" => stripslashes($_POST["pp_light_blue_api_key"]), 
			"decimal_separator" => stripslashes($_POST["pp_light_blue_decimal_separator"]), 
			"debug" => stripslashes($_POST["pp_light_blue_debug"]),
			"field_mappings" => stripslashes( $mappings )
			);
		update_option("pp_light_blue_settings", $settings);
	}
	else{
		$settings = get_option("pp_light_blue_settings");
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
	<h2>Light Blue API for ProPhoto Setup</h2>

	<div class="hr-divider"></div>

	<h3>Using the Light Blue API for ProPhoto plug-in</h3>
	<p>To send data to the Light Blue API, you need to associate the fields in your forms with parameters that the Light Blue API recognises.</p>
	<p>To do this, you need to select your contact form fields from the menus in the 'Field Mappings' section below, and enter the Light Blue API parameter that you want to send it to into the field next to it. The menu will list all of the fields from your contact form apart from 'Headline text' fields, and the field numbers correspond to those that appear in the 'Form Fields' section of the ProPhoto theme's <a href="<?php print admin_url( 'admin.php?page=pp-customize&area=contact#form' ); ?>">Contact Form customisation page</a>.</p>
	<p>You can find a list of the parameters that the Light Blue API recognises <a href='http://www.lightbluesoftware.com/api/' target='_blank'>on our website</a>. Any fields in your forms that do not have their 'Parameter Name' set, or have a 'Parameter Name' that doesn't match a valid Light Blue API paramater, will be ignored by the Light Blue API.</p>

	<div class="hr-divider"></div>

	<form method="post" action="" style="margin: 30px 0 30px; clear:both;">
		<?php wp_nonce_field("update", "pp_light_blue_update") ?>
		<h3>Your Light Blue Account</h3>
		<p>If you don't have a subscription to Light Blue's online services, you can <a href='http://www.lightbluesoftware.com' target='_blank'>sign up for one here</a>.</p>

		<table class="form-table" id="settings-table">
			<tr>
				<th scope="row"><label for="pp_light_blue_api_key">API Key</label></th>
				<td colspan=3>
					<input type="text" size="75" id="pp_light_blue_api_key" class="code pre" name="pp_light_blue_api_key" value="<?php echo esc_attr($settings["key"]) ?>"/>
					<small style="display:block;">You can find your Light Blue API key by logging into your account <a href='http://www.lightbluesoftware.com' target='_blank'>on our website</a>.</small>
					<?php print $api_test_message;?>
				</td>
			</tr>
			
			
			<tr>
				<th scope="row">Debugging</th>
				<td colspan=3>
					<label for="pp_light_blue_debug">
						<?php if( isset($settings["debug"]) && ($settings["debug"] == 'on' ) ) { 
							print "<input name='pp_light_blue_debug' type='checkbox' id='pp_light_blue_debug' checked>"; 
						} else { 
							print "<input name='pp_light_blue_debug' type='checkbox' id='pp_light_blue_debug'>"; 
						} ?>
						Email debugging logs to your WordPress admin email address.
					</label>
					<small style="display:block;">(n.b. this option should only be turned on to help Light Blue Software diagnose any problems you have with sending data to the Light Blue API)</small>
				</td>
			</tr>
			
			
			<tr>
				<th scope="row">Field Mappings</th>
				<td>Contact Form Field</td>
				<td>Light Blue API Parameter</td>
			</tr>
			<?php
				$fieldMappings = Array();
				if( isset( $settings["field_mappings"] ) ) { $fieldMappings = Explode( Chr(13), $settings["field_mappings"] ); }
				natsort( $fieldMappings );
				$fieldMappings[] = '';  // Append a blank entry to the end
				$i = 0;
				foreach( $fieldMappings as $key => $value ) {
					$i++;
					$field = '';
					$param = '';
					$pos = strpos( $value, ":" );
					if ($pos !== False) {
						$field = substr( $value, 0, $pos );
						$param = substr( $value, $pos+1 );
					}
			?>
			<tr>
				<td/>
				<td>
					<select name="<?php print $inputPrefixMapping.$i ?>"><?php self::construct_field_selector( $field ) ?></select>
				</td>
				<td>
					<input type="text" size="40" placeholder="Light Blue API Parameter"  name="<?php print $inputPrefixParam.$i; ?>" value="<?php if( isset( $param ) ) { print $param; } ?>"/>
				</td>
				<td>
					<input type="submit" class="button action removeMapping" value="Remove" style="float: right;">
				</td>
			</tr>
			<?php
				}
			?>
		</table>
		<table class="form-table">
			<tr>
				<td><input type="submit" class="button action" id="addMapping" value="Add Mapping" style="float: right;"></td>
				<script>
					var $ = jQuery.noConflict();
					$(document).ready(function() {
						var count = <?php echo Count( $fieldMappings ); ?>;
						$("#addMapping").click(function() {
							count = count + 1;
							$('#settings-table').append('<tr><td/><td><select name="<?php print $inputPrefixMapping; ?>'+count+'"><?php self::construct_field_selector( '' ) ?></select></td><td><input type="text" size="40" placeholder="Light Blue API Parameter"  name="<?php print $inputPrefixParam; ?>'+count+'" value=""/></td><td><input type="submit" class="button action removeMapping" value="Remove" style="float: right;"></td></tr>' );
							return false;
						});
						$(".removeMapping").live('click', function() {
							$(this).parent().parent().remove();
						});
					});
				</script>
			</tr>
			<tr>
				<td><input type="submit" name="pp_light_blue_submit" class="submit button-primary" value="Update Settings" /></td>
			</tr>
		</table>
	</form>


	</div>
	<?php
}


public static function lb_test_account( &$message ) {
	
	$settings = get_option("pp_light_blue_settings");
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



public static function construct_field_selector( $selectedField = '' ) {
	print '<option value="none">None</option>';
	for ( $i = 1; $i <= pp::num()->maxContactFormCustomFields; $i++ ) {
		if( (ppOpt::id( 'contact_field_'.$i.'_type' ) == "off") || (ppOpt::id( 'contact_field_'.$i.'_type' ) == "headline") ) {
			Continue;
		}
		if( $i == $selectedField ) {
			$selected = ' selected="selected"';
		} else {
			$selected = '';
		}
		print '<option value="'.$i.'"'.$selected.'>Field #'.$i.': '.ppOpt::id( 'contact_field_'.$i.'_label' ).'</option>';
	}
}


public static function lb_pp_contact_pre_email() {
	$submitted_data = array();
	$settings = get_option("pp_light_blue_settings");
		
	if( isset($settings["debug"]) && ($settings["debug"] == 'on' ) ) {  // Send debugging email
		ob_start();
		var_dump($_POST);
		mail( get_option('admin_email'), 'Light Blue API for ProPhoto $_POST', ob_get_contents() );
		ob_end_clean();
	}
	
	$fieldMappings = Array();
	if( isset( $settings["field_mappings"] ) ) { $fieldMappings = Explode( Chr(13), $settings["field_mappings"] ); }	
	
	if( isset($settings["debug"]) && ($settings["debug"] == 'on' ) ) {  // Send debugging email
		ob_start();
		var_dump($fieldMappings);
		mail( get_option('admin_email'), 'Light Blue API for ProPhoto $fieldMappings', ob_get_contents() );
		ob_end_clean();
	}
	
	for( $i=0; $i <= Count( $fieldMappings ); $i++ ) {
		$field = '';
		$param = '';
		$pos = strpos( $value, ":" );
		if ($pos !== False) {
			$field = substr( $value, 0, $pos );
			$param = substr( $value, $pos+1 );
		}
		if( isset( $_POST["contact_field_".$field] ) ) {
			$submitted_data[ $param ] = str_replace( Array( "\\'", '\\"' ), Array( "'", '"' ), $_POST["contact_field_".$field] );
		} 
	}
	
	if( Count( $submitted_data ) == 0 ) { Return; }
	
	$submitted_data["Type"] = "contact form";
	$submitted_data["Source"] = self::$name;
	$submitted_data["SourceAPIVersion"] = self::$version;
	$submitted_data["Key"] = $settings["key"];
	$submitted_data["DateFormat"] = get_option( 'date_format' );  // ProPhoto uses the WordPress date format for its date widget
	if( isset( $settings["decimal_separator"] ) && ($settings["decimal_separator"] != '') ) {
		$submitted_data["DecimalSeparator"] = $settings["decimal_separator"];
	}
	
	if( isset($settings["debug"]) && ($settings["debug"] == 'on' ) ) {  // Send debugging email
		ob_start();
		var_dump($submitted_data);
		mail( get_option('admin_email'), 'Light Blue API for ProPhoto about to send', ob_get_contents() );
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
			mail( get_option('admin_email'), 'Light Blue API for ProPhoto sending error', $error_message );
		} else {
			ob_start();
			var_dump($response);
			mail( get_option('admin_email'), 'Light Blue API for ProPhoto sent', ob_get_contents() );
			ob_end_clean();
		}
	}
	
}  // end of pp_contact_pre_email



}  // end of class

?>