<?php	
	if (defined('WP_UNINSTALL_PLUGIN')){
		require_once diname(__FILE__).'/hpr-syntax.php';
		HprSyntax::uninstall();
	}
?>