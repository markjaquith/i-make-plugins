<?php

require_once getenv( 'WP_TESTS_DIR' ) . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../i-make-plugins.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';

class CWS_IMP_TestCase extends WP_UnitTestCase {
	function plugin() {
		return CWS_I_Make_Plugins::$instance;
	}
}
