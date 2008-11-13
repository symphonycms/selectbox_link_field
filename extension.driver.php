<?php

	Class extension_selectbox_link_field extends Extension{
	
		public function about(){
			return array('name' => 'Field: Select Box Link',
						 'version' => '1.0',
						 'release-date' => '2008-11-13',
						 'author' => array('name' => 'Symphony Team',
										   'website' => 'http://www.symphony21.com',
										   'email' => 'team@symphony21.com')
				 		);
		}
		
		public function uninstall(){
			$this->_Parent->Database->query("DROP TABLE `tbl_fields_selectbox_link`");
		}


		public function install(){

			return $this->_Parent->Database->query("CREATE TABLE `tbl_fields_selectbox_link` (
		 	  `id` int(11) unsigned NOT NULL auto_increment,
			  `field_id` int(11) unsigned NOT NULL,
			  `allow_multiple_selection` enum('yes','no') NOT NULL default 'no',
			  `related_field_id` int(11) unsigned default NULL,
			  PRIMARY KEY  (`id`),
			  KEY `field_id` (`field_id`)
			) TYPE=MyISAM;");

		}
			
	}

