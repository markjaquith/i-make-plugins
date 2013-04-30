<?php

class CWS_IMP_Test_Default_Options extends CWS_IMP_TestCase {
	function test_default_options() {
		$this->assertEquals( "<ul id=\"cws-imp-plugin-list\">\n\n[implist]\n<li class=\"cws-imp-plugin\"><a class=\"cws-imp-plugin-title\" href=\"[implist_url]\">[implist_name]</a>\n<p class=\"cws-imp-plugin-description\">[implist_desc]</p>\n</li>\n[/implist]\n\n</ul>", get_option( 'cws_imp_plugin_list_template' ) );
		$this->assertEquals( "[imp_full_desc]\n\n<h3>Download</h3>\nLatest version: <a href=\"[imp_zip_url]\">Download <b>[imp_name]</b> v[imp_version]</a> [zip]\n\n[if_imp_installation]\n<h3>Installation</h3>\n[imp_installation]\n[/if_imp_installation]\n\n[if_imp_faq]\n<h3>FAQ</h3>\n[imp_faq]\n[/if_imp_faq]\n\n[if_imp_changelog]\n<h3>Changelog</h3>\n[imp_changelog]\n[/if_imp_changelog]", get_option( 'cws_imp_plugin_template' ) );
	}
}
