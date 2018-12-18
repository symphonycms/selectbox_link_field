<?php

    Class extension_selectbox_link_field extends Extension{

        public function install(){
            return Symphony::Database()
                ->create('tbl_fields_selectbox_link')
                ->ifNotExists()
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
            return Symphony::Database()
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
                Symphony::Database()
                    ->alter('tbl_fields_selectbox_link')
                    ->modify([
                        'related_field_id' => 'varchar(255)'
                    ])
                    ->execute()
                    ->success();
            }
            catch(Exception $e){
                // Discard
            }

            if(version_compare($previousVersion, '1.19', '<')){
                try{
                    Symphony::Database()
                        ->alter()
                        ->add([
                            'show_association' => [
                                'type' => 'enum',
                                'values' => ['yes','no'],
                                'default' => 'yes',
                            ],
                        ])
                        ->execute()
                        ->success();
                }
                catch(Exception $e){
                    // Discard
                }
            }

            if(version_compare($previousVersion, '1.31', '<')){
                try{
                    Symphony::Database()
                        ->alter('tbl_fields_selectbox_link')
                        ->drop('show_association')
                        ->execute()
                        ->success();
                }
                catch(Exception $e){
                    // Discard
                }
            }

            return true;
        }
    }
