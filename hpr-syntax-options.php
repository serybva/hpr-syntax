<?php
/*
** HprSyntax Options
*/

if (!class_exists('HprSyntaxOptions')){

	class 					HprSyntaxOptions{
		const				PAGE_TYPE = 'hpr_syntax_options_page';

		public	function	__construct() {
			$this->_init();
		}

		private	function	_init(){
			if (current_user_can('manage_options')) {
				add_meta_box('hpr-syntax-settings-box', __('Settings', 'hpr_syntax'), array(&$this, 'displaySettingsBox'), HprSyntaxOptions::PAGE_TYPE, 'normal');
				add_meta_box('hpr-syntax-global-actions-box', __('Global actions', 'hpr_syntax'), array(&$this, 'displayGlobalActionBox'), HprSyntaxOptions::PAGE_TYPE, 'normal');
				if (isset($_POST['hpr_syntax_action']) && $_POST['hpr_syntax_action'] === HprSyntax::ACTION_PROCESS_ALL)
					HprSyntax::processAll();
				else if (isset($_POST['hpr_syntax_action']) && $_POST['hpr_syntax_action'] === HprSyntax::ACTION_UNPROCESS_ALL)
					HprSyntax::unProcessAll();
				else if (isset($_POST['hpr_syntax_action']) && $_POST['hpr_syntax_action'] === HprSyntax::ACTION_CLEAN_DB)
					HprSyntax::cleanDB();
				if (isset($_POST['hpr_syntax_settings_form'])){
					HprSyntax::$options['use_CSS_classes'] = $_POST['use_css_classes'] === 'on' ? true:false;
					if (isset($_POST['hpr_reprocess_on_setting_changes']))
						HprSyntax::$options['hpr_reprocess_on_setting_changes'] = $_POST['hpr_reprocess_on_setting_changes'] === 'on' ? true:false;
					HprSyntax::$options['post_types'] = $_POST['hpr_post_types'];
					if ($_POST['hpr_db_clean_interval'] != HprSyntax::$options['DB_clean_interval']){
						HprSyntax::$options['DB_clean_interval'] = $_POST['hpr_db_clean_interval'];
						HprSyntax::DBCleanIntervalChanged();
					}
					update_option(HprSyntax::OPTIONS_NAME, HprSyntax::$options);
					if (HprSyntax::$options['hpr_reprocess_on_setting_changes']){
						HprSyntax::unProcessAll();
						HprSyntax::processAll();
					}
				}
				if (count($_POST) > 0)
					wp_redirect($_SERVER['REQUEST_URI']);
			}
		}

		public	function	displayOptionsPage(){
			echo '<div class="wrap">';
			screen_icon();
			echo '<h2>'.__('HprSyntax settings page', 'hpr_syntax').'</h2>';
			echo '<div class="metabox-holder">';//Mandatory otherwise the metaboxes' headers don't display correctly
			do_meta_boxes(HprSyntaxOptions::PAGE_TYPE, 'normal', null);
			echo '</div></div>';
		}

		private	function	_generatePostTypesOptions(){
			$postTypes = array_merge(get_post_types(array('public' => true, '_builtin' => false)), get_post_types(array('public' => true, '_builtin' => true)));
			$options = '';
			foreach ($postTypes as $postType){
				if ($postType != 'attachment'){
					$selected = false;
					foreach (HprSyntax::$options['post_types'] as $registeredOptionPostType){
						if ($postType == $registeredOptionPostType){
							$selected = true;
							break;
						}
					}
					$options .= '<option value="'.$postType.'" '.($selected?'selected':'').'>'.$postType.'</option>';
				}
			}
			return $options;
		}
		
		public	function	displaySettingsBox(){
			echo '<form id="hpr_syntax_settings_form" action="'.$_SERVER['REQUEST_URI'].'" method="post">
					<table class="form-table">
						<tr>
							<th scope="row">Style</th>
								<input type="hidden" name="hpr_syntax_settings_form" value="true" />
							<td>
								<label for="hpr_use_classes">'.__('Use css classes for highlighting instead of inline styles', 'hpr_syntax').'</label>
								<input type="checkbox" '.(HprSyntax::$options['use_CSS_classes']?'checked="checked"':'').' id="hpr_use_classes" name="use_css_classes" /></td>
						</tr>
						<tr>
							<th scope="row">'.__('Other settings', 'hpr_syntax').'</th>
							<td>
								<label for="hpr_post_types" title="'.__('The post types to process', 'hpr_syntax').'">'.__('Post types', 'hpr_syntax').'</label>
								<select name="hpr_post_types[]" id="hpr_post_types" multiple>'.$this->_generatePostTypesOptions().'</select>
								</td>
						</tr>
						<tr>
							<th scope="row"></th>
							<td>
								<label for="hpr_db_clean_interval" title="'.__('When HprSyntax must delete dead references from the database?', 'hpr_syntax').'">'.__('Database clean interval', 'hpr_syntax').'</label>
								<select name="hpr_db_clean_interval" id="hpr_db_clean_interval">';
			$intervals = wp_get_schedules();
			foreach ($intervals as $key => $interval){
				echo '<option value="'.$key.'" '.($key == HprSyntax::$options['DB_clean_interval']?'selected':'').'>'.$interval['display'].'</option>';
			}
			echo '</select>
								</td>
						</tr>
						<tr>
							<th scope="row"></th>
							<td>
							<label for="hpr_reprocess_on_setting_changes" title="'.__('This option tells HprSyntax to reprocess every previously highlighted code so the new settings apply, otherwise you will have to manually unprocess and process', 'hpr_syntax').'">'.__('Reprocess on each setting change?', 'hpr_syntax').'</label>
							<input type="checkbox" '.(HprSyntax::$options['hpr_reprocess_on_setting_changes']?'checked="checked"':'').' id="hpr_reprocess_on_setting_changes" name="hpr_reprocess_on_setting_changes" /></td>
						</tr>
						<tr>
							<th scope="row"></th>
							<td>
							<input type="submit" value="'.__('Save', 'hpr_syntax').'" id="hpr_settings_form_submit" class="button button-primary" />
							</td>
						</tr>
					</table>
				</form>';
		}

		public	function	displayGlobalActionBox(){
			echo '<form id="hpr_syntax_process_all_form" action="'.$_SERVER["REQUEST_URI"].'" method="post">
					<table class="form-table">
						<tr>
							<td>
								<input type="hidden" name="hpr_syntax_action" value="'.HprSyntax::ACTION_PROCESS_ALL.'" />
								<label for="hpr_process_all_form_submit">'.__('Processes ALL the content, may take a while', 'hpr_syntax').'</label>
								<input type="submit" value="'.__('Process', 'hpr_syntax').'" id="hpr_process_all_form_submit" class="button button-primary" /></td>
						</tr>
						<tr>
					</table></form>
					<form id="hpr_syntax_unprocess_all_form" action="'.$_SERVER["REQUEST_URI"].'" method="post">
					<table class="form-table">
						<tr>
							<td>								
								<input type="hidden" name="hpr_syntax_action" value="'.HprSyntax::ACTION_UNPROCESS_ALL.'" />
								<label for="hpr_unprocess_all_form_submit">'.__('Unprocesses ALL the content, may take a while too', 'hpr_syntax').'</label>
								<input type="submit" value="'.__('Unprocess', 'hpr_syntax').'" id="hpr_unprocess_all_form_submit" class="button button-primary" /></td>
						</tr>
					</table></form>
					<form id="hpr_syntax_clean_db_form" action="'.$_SERVER["REQUEST_URI"].'" method="post">
					<table class="form-table">
						<tr>
							<td>								
								<input type="hidden" name="hpr_syntax_action" value="'.HprSyntax::ACTION_CLEAN_DB.'" />
								<label for="hpr_clean_db_form_submit">'.__('Deletes dead references from the database', 'hpr_syntax').'</label>
								<input type="submit" value="'.__('Clean', 'hpr_syntax').'" id="hpr_clean_db_form_submit" class="button button-primary" /></td>
						</tr>
					</table></form>';
			//HprSyntax::$lastMessage = '';
		}
	}
}