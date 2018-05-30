<?php

    Class extension_selectbox_link_field extends Extension{

        public function install(){
            return Symphony::Database()
                ->create('tbl_fields_selectbox_link')
                ->ifNotExists()
                ->charset('utf8')
                ->collate('utf8_unicode_ci')
                ->fields([
                    'id' => [
                        'type' => 'int(11)',
                        'auto' => true,
                    ],
                    'field_id' => 'int(11)',
                    'allow_multiple_selection' => [
                        'type' => 'enum',
                        'values' => ['yes','no'],
                        'default' => 'no',
                    ],
                    'hide_when_prepopulated' => [
                        'type' => 'enum',
                        'values' => ['yes','no'],
                        'default' => 'no',
                    ],
                    'related_field_id' => 'varchar(255)',
                    'limit' => [
                        'type' => 'int(4)',
                        'default' => 20
                    ],
                ])
                ->keys([
                    'id' => 'primary',
                    'field_id' => 'key',
                ])
                ->execute()
                ->success();
        }

        public function uninstall(){
            Symphony::Database()
                ->drop('tbl_fields_selectbox_link')
                ->ifExists()
                ->execute()
                ->success();
        }

        public function update($previousVersion = false){
            try{
                if(version_compare($previousVersion, '1.27', '<')){
                    Symphony::Database()
                        ->alter('tbl_fields_selectbox_link')
                        ->add([
                            'hide_when_prepopulated' => [
                                'type' => 'enum',
                                'values' => ['yes','no'],
                                'default' => 'no',
                            ],
                        ])
                        ->execute()
                        ->success();
                }
            }
            catch(Exception $e){
                // Discard
            }

            try{
                if(version_compare($previousVersion, '1.6', '<')){
                    Symphony::Database()
                        ->alter('tbl_fields_selectbox_link')
                        ->add([
                            'limit' => [
                                'type' => 'int(4)',
                                'default' => 20
                            ],
                        ])
                        ->execute()
                        ->success();
                }
            }
            catch(Exception $e){
                // Discard
            }

            if(version_compare($previousVersion, '1.15', '<')){
                try{
                    $fields = Symphony::Database()
                        ->select(['field_id'])
                        ->from('tbl_fields_selectbox_link')
                        ->execute()
                        ->column('field_id');
                }
                catch(Exception $e){
                    // Discard
                }

                if(is_array($fields) && !empty($fields)){
                    foreach($fields as $field_id){
                        try{
                            Symphony::Database()
                                ->alter('tbl_entries_data_' . $field_id)
                                ->modify([
                                    'relation_id' => [
                                        'type' => 'int(11)',
                                        'null' => true,
                                    ],
                                ])
                                ->execute()
                                ->success();
                        }
                        catch(Exception $e){
                        }
                    }
                }
            }

            try{
                Symphony::Database()->query("ALTER TABLE `tbl_fields_selectbox_link` CHANGE `related_field_id` `related_field_id` VARCHAR(255) NOT NULL");
            }
            catch(Exception $e){
                // Discard
            }

            if(version_compare($previousVersion, '1.19', '<')){
                try{
                    Symphony::Database()->query("ALTER TABLE `tbl_fields_selectbox_link` ADD COLUMN `show_association` ENUM('yes','no') NOT NULL default 'yes'");
                }
                catch(Exception $e){
                    // Discard
                }
            }

            if(version_compare($previousVersion, '1.31', '<')){
                try{
                    Symphony::Database()->query("ALTER TABLE `tbl_fields_selectbox_link` DROP COLUMN `show_association`");
                }
                catch(Exception $e){
                    // Discard
                }
            }

            return true;
        }
    }
