<?php

	Class extension_selectbox_link_field extends Extension{

		public function about(){
			return array('name' => 'Field: Select Box Link',
						 'version' => '1.14',
						 'release-date' => '2009-12-29',
						 'author' => array('name' => 'Symphony Team',
										   'website' => 'http://www.symphony-cms.com',
										   'email' => 'team@symphony-cms.com')
				 		);
		}

		public function uninstall(){
			Symphony::Database()->query("DROP TABLE `tbl_fields_selectbox_link`");
		}

		public function update($previousVersion){
			
			try{
				if(version_compare($previousVersion, '1.6', '<')){
					Symphony::Database()->query("ALTER TABLE `tbl_fields_selectbox_link` ADD `limit` INT(4) UNSIGNED NOT NULL DEFAULT '20'");
				}

				Symphony::Database()->query("ALTER TABLE `tbl_fields_selectbox_link` CHANGE `related_field_id` `related_field_id` VARCHAR(255) NOT NULL");
			}
			catch(Exception $e){
				// Discard
			}
			
			return true;
		}

		public function install(){
			
			try{
				Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_fields_selectbox_link` (
					  `id` int(11) unsigned NOT NULL auto_increment,
					  `field_id` int(11) unsigned NOT NULL,
					  `allow_multiple_selection` enum('yes','no') NOT NULL default 'no',
					  `related_field_id` VARCHAR(255) NOT NULL,
					  `limit` int(4) unsigned NOT NULL default '20',
				  PRIMARY KEY  (`id`),
				  KEY `field_id` (`field_id`)
				)");
			}
			catch(Exception $e){
				return false;
			}
			
			return true;
		}

	}
