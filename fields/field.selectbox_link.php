<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	Class fieldSelectBox_Link extends Field{
		static private $cacheRelations = array();
		static private $cacheFields = array();
		static private $cacheValues = array();

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Select Box Link');
			$this->_required = true;
			$this->_showassociation = true;

			// Set default
			$this->set('show_column', 'no');
			$this->set('show_association', 'yes');
			$this->set('required', 'yes');
			$this->set('limit', 20);
			$this->set('related_field_id', array());
		}

		public function canToggle(){
			return ($this->get('allow_multiple_selection') == 'yes' ? false : true);
		}

		public function getToggleStates(){
			$options = $this->findOptions();
			$output = $options[0]['values'];
			$output[""] = __('None');
			return $output;
		}

		public function toggleFieldData($data, $new_value){
			$data['relation_id'] = $new_value;
			return $data;
		}

		public function canFilter(){
			return true;
		}

		public function allowDatasourceOutputGrouping(){
			return true;
		}

		public function allowDatasourceParamOutput(){
			return true;
		}

		public function getParameterPoolValue($data){
			return $data['relation_id'];
		}

		public function set($field, $value){
			if($field == 'related_field_id' && !is_array($value)){
				$value = explode(',', $value);
			}
			$this->_fields[$field] = $value;
		}

		public function setArray($array){
			if(empty($array) || !is_array($array)) return;
			foreach($array as $field => $value) $this->set($field, $value);
		}

		public function groupRecords($records){
			if(!is_array($records) || empty($records)) return;

			$groups = array($this->get('element_name') => array());

			foreach($records as $r){
				$data = $r->getData($this->get('id'));
				$value = $data['relation_id'];
				$primary_field = $this->__findPrimaryFieldValueFromRelationID($data['relation_id']);

				if(!isset($groups[$this->get('element_name')][$value])){
					$groups[$this->get('element_name')][$value] = array(
						'attr' => array(
							'link-id' => $data['relation_id'],
							'link-handle' => Lang::createHandle($primary_field['value']),
							'value' => General::sanitize($primary_field['value'])),
						'records' => array(),
						'groups' => array()
					);
				}

				$groups[$this->get('element_name')][$value]['records'][] = $r;
			}

			return $groups;
		}

		public function prepareTableValue($data, XMLElement $link=NULL){
			$result = array();

			if(!is_array($data) || (is_array($data) && !isset($data['relation_id']))) return parent::prepareTableValue(NULL);

			if(!is_array($data['relation_id'])){
				$data['relation_id'] = array($data['relation_id']);
			}

			foreach($data['relation_id'] as $relation_id){
				if((int)$relation_id <= 0) continue;

				$primary_field = $this->__findPrimaryFieldValueFromRelationID($relation_id);

				if(!is_array($primary_field) || empty($primary_field)) continue;

				$result[$relation_id] = $primary_field;
			}

			if(!is_null($link)){
				$label = NULL;
				foreach($result as $item){
					$label .= ' ' . $item['value'];
				}
				$link->setValue(General::sanitize(trim($label)));
				return $link->generate();
			}

			$output = NULL;

			foreach($result as $relation_id => $item){
				$link = Widget::Anchor($item['value'], sprintf('%s/symphony/publish/%s/edit/%d/', URL, $item['section_handle'], $relation_id));
				$output .= $link->generate() . ' ';
			}

			return trim($output);
		}

		private function __findPrimaryFieldValueFromRelationID($entry_id){
			$field_id = $this->findFieldIDFromRelationID($entry_id);

			if (!isset(self::$cacheFields[$field_id])) {
				self::$cacheFields[$field_id] = $this->_engine->Database->fetchRow(0, "
					SELECT
						f.id,
						s.name AS `section_name`,
						s.handle AS `section_handle`
					 FROM
					 	`tbl_fields` AS f
					 INNER JOIN
					 	`tbl_sections` AS s
					 	ON s.id = f.parent_section
					 WHERE
					 	f.id = '{$field_id}'
					 ORDER BY
					 	f.sortorder ASC
					 LIMIT 1
				");
			}

			$primary_field = self::$cacheFields[$field_id];

			if(!$primary_field) return NULL;

			$fm = new FieldManager($this->_Parent);
			$field = $fm->fetch($field_id);

			if (!isset(self::$cacheValues[$entry_id])) {
				self::$cacheValues[$entry_id] = $this->_engine->Database->fetchRow(0, sprintf("
						SELECT *
				 		FROM `tbl_entries_data_%d`
				 		WHERE `entry_id` = %d
						ORDER BY `id` DESC
						LIMIT 1
				", $field_id, $entry_id));
			}

			$data = self::$cacheValues[$entry_id];

			if(empty($data)) return null;

			$primary_field['value'] = $field->prepareTableValue($data);

			return $primary_field;
		}

		public function checkFields(&$errors, $checkForDuplicates = true) {
			parent::checkFields($errors, $checkForDuplicates);

			$related_fields = $this->get('related_field_id');
			if(empty($related_fields)){
				$errors['related_field_id'] = __('This is a required field.');
			}

			return (is_array($errors) && !empty($errors) ? self::__ERROR__ : self::__OK__);
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			$status = self::__OK__;
			if(!is_array($data)) return array('relation_id' => $data);

			if(empty($data)) return NULL;

			$result = array();

			foreach($data as $a => $value) {
			  $result['relation_id'][] = $data[$a];
			}

			return $result;
		}

		public function fetchAssociatedEntrySearchValue($data, $field_id=NULL, $parent_entry_id=NULL){
			// We dont care about $data, but instead $parent_entry_id
			if(!is_null($parent_entry_id)) return $parent_entry_id;

			if(!is_array($data)) return $data;

			$searchvalue = $this->_engine->Database->fetchRow(0,
				sprintf("
					SELECT `entry_id` FROM `tbl_entries_data_%d`
					WHERE `handle` = '%s'
					LIMIT 1", $field_id, addslashes($data['handle']))
			);

			return $searchvalue['entry_id'];
		}

		public function fetchAssociatedEntryCount($value){
			return $this->_engine->Database->fetchVar('count', 0, "SELECT count(*) AS `count` FROM `tbl_entries_data_".$this->get('id')."` WHERE `relation_id` = '$value'");
		}

		public function fetchAssociatedEntryIDs($value){
			return $this->_engine->Database->fetchCol('entry_id', "SELECT `entry_id` FROM `tbl_entries_data_".$this->get('id')."` WHERE `relation_id` = '$value'");
		}

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
			if(!is_array($data) || empty($data) || is_null($data['relation_id'])) return;

			$list = new XMLElement($this->get('element_name'));

			if(!is_array($data['relation_id'])) $data['relation_id'] = array($data['relation_id']);

			foreach($data['relation_id'] as $relation_id){
				$primary_field = $this->__findPrimaryFieldValueFromRelationID($relation_id);

				$value = $primary_field['value'];

				$item = new XMLElement('item');
				$item->setAttribute('id', $relation_id);
				$item->setAttribute('handle', Lang::createHandle($primary_field['value']));
				$item->setAttribute('section-handle', $primary_field['section_handle']);
				$item->setAttribute('section-name', General::sanitize($primary_field['section_name']));
				$item->setValue(General::sanitize($value));

				$list->appendChild($item);
			}

			$wrapper->appendChild($list);
		}

		public function findFieldIDFromRelationID($id){
			if(is_null($id)) return NULL;

			if (isset(self::$cacheRelations[$id])) {
				return self::$cacheRelations[$id];
			}

			try{
				## Figure out the section
				$section_id = $this->_engine->Database->fetchVar('section_id', 0, "SELECT `section_id` FROM `tbl_entries` WHERE `id` = {$id} LIMIT 1");

				## Figure out which related_field_id is from that section
				$field_id = $this->_engine->Database->fetchVar('field_id', 0, "SELECT f.`id` AS `field_id`
					FROM `tbl_fields` AS `f`
					LEFT JOIN `tbl_sections` AS `s` ON f.parent_section = s.id
					WHERE `s`.id = {$section_id} AND f.id IN ('".@implode("', '", $this->get('related_field_id'))."') LIMIT 1");
			}
			catch(Exception $e){
				return NULL;
			}

			self::$cacheRelations[$id] = $field_id;

			return $field_id;
		}

		public function findOptions(array $existing_selection=NULL){
			$values = array();
			$limit = $this->get('limit');

			// find the sections of the related fields
			$sections = $this->_engine->Database->fetch("SELECT DISTINCT (s.id), s.name, f.id as `field_id`
				 								FROM `tbl_sections` AS `s`
												LEFT JOIN `tbl_fields` AS `f` ON `s`.id = `f`.parent_section
												WHERE `f`.id IN ('" . implode("','", $this->get('related_field_id')) . "')
												ORDER BY s.sortorder ASC");

			if(is_array($sections) && !empty($sections)){
				foreach($sections as $section){

					$group = array('name' => $section['name'], 'section' => $section['id'], 'values' => array());

					// build a list of entry IDs with the correct sort order
					$entryManager = new EntryManager($this->_Parent);
					$entries = $entryManager->fetch(NULL, $section['id'], $limit, 0);

					$results = array();
					foreach($entries as $entry) $results[] = $entry->get('id');

					// if a value is already selected, ensure it is added to the list (if it isn't in the available options)
					if(!is_null($existing_selection) && !empty($existing_selection)){
						foreach($existing_selection as $key => $entry_id){
							$x = $this->findFieldIDFromRelationID($entry_id);
							if($x == $section['field_id']) $results[] = $entry_id;
						}
					}

					if(is_array($results) && !empty($results)){
						foreach($results as $entry_id){
							$value = $this->__findPrimaryFieldValueFromRelationID($entry_id);
							$group['values'][$entry_id] = $value['value'];
						}
					}

					$values[] = $group;
				}
			}

			return $values;
		}

		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			$entry_ids = array();

			if(!is_null($data['relation_id'])){
				if(!is_array($data['relation_id'])){
					$entry_ids = array($data['relation_id']);
				}
				else{
					$entry_ids = array_values($data['relation_id']);
				}
			}

			$states = $this->findOptions($entry_ids);
			$options = array();

			if($this->get('required') != 'yes') $options[] = array(NULL, false, NULL);

			if(!empty($states)){
				foreach($states as $s){
					$group = array('label' => $s['name'], 'options' => array());
					foreach($s['values'] as $id => $v){
						$group['options'][] = array($id, in_array($id, $entry_ids), General::sanitize($v));
					}
					$options[] = $group;
				}
			}

			$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix;
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';

			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Select($fieldname, $options, ($this->get('allow_multiple_selection') == 'yes' ? array('multiple' => 'multiple') : NULL)));

			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
		}

		public function commit(){
			if(!parent::commit()) return false;

			$id = $this->get('id');

			if($id === false) return false;

			$fields = array();
			$fields['field_id'] = $id;
			if($this->get('related_field_id') != '') $fields['related_field_id'] = $this->get('related_field_id');
			$fields['allow_multiple_selection'] = $this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no';
			$fields['show_association'] = $this->get('show_association') == 'yes' ? 'yes' : 'no';
			$fields['limit'] = max(1, (int)$this->get('limit'));
			$fields['related_field_id'] = implode(',', $this->get('related_field_id'));

			$this->_engine->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id'");

			if(!$this->_engine->Database->insert($fields, 'tbl_fields_' . $this->handle())) return false;

			//$sections = $this->get('related_field_id');

			$this->removeSectionAssociation($id);

			//$section_id = $this->_engine->Database->fetchVar('parent_section', 0, "SELECT `parent_section` FROM `tbl_fields` WHERE `id` = '".$fields['related_field_id']."' LIMIT 1");

			foreach($this->get('related_field_id') as $field_id){
				$this->createSectionAssociation(NULL, $id, $field_id, $this->get('show_association') == 'yes' ? true : false);
			}

			return true;
		}

		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`ed`.`relation_id` $order");
		}

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){
			$field_id = $this->get('id');

			if(preg_match('/^sql:\s*/', $data[0], $matches)) {
				$data = trim(array_pop(explode(':', $data[0], 2)));

				if(strpos($data, "NOT NULL") !== false) {

					$joins .= " LEFT JOIN
									`tbl_entries_data_{$field_id}` AS `t{$field_id}`
								ON (`e`.`id` = `t{$field_id}`.entry_id)";
					$where .= " AND `t{$field_id}`.relation_id IS NOT NULL ";

				} else if(strpos($data, "NULL") !== false) {

					$joins .= " LEFT JOIN
									`tbl_entries_data_{$field_id}` AS `t{$field_id}`
								ON (`e`.`id` = `t{$field_id}`.entry_id)";
					$where .= " AND `t{$field_id}`.relation_id IS NULL ";

				}
			}
			else {
				$negation = false;
				if(preg_match('/^not:/', $data[0])) {
					$data[0] = preg_replace('/^not:/', null, $data[0]);
					$negation = true;
				}

				foreach($data as $key => &$value) {
					// for now, I assume string values are the only possible handles.
					// of course, this is not entirely true, but I find it good enough.
					if(!is_numeric($value) && !is_null($value)){
						$related_field_ids = $this->get('related_field_id');
						$id = null;

						foreach($related_field_ids as $related_field_id) {
							try {
								$return = Symphony::Database()->fetchCol("id", sprintf(
									"SELECT
										`entry_id` as `id`
									FROM
										`tbl_entries_data_%d`
									WHERE
										`handle` = '%s'
									LIMIT 1", $related_field_id, Lang::createHandle($value)
								));

								// Skipping returns wrong results when doing an AND operation, return 0 instead.
								if(!empty($return)) {
									$id = $return[0];
									break;
								}
							} catch (Exception $ex) {
								// Do nothing, this would normally be the case when a handle
								// column doesn't exist!
							}
						}

						$value = (is_null($id)) ? 0 : $id;
					}
				}

				if($andOperation) {
					$condition = ($negation) ? '!=' : '=';
					foreach($data as $key => $bit){
						$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
						$where .= " AND `t$field_id$key`.relation_id $condition '$bit' ";
					}
				}
				else {
					$condition = ($negation) ? 'NOT IN' : 'IN';
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
					$where .= " AND `t$field_id`.relation_id $condition ('".implode("', '", $data)."') ";
				}

			}

			return true;
		}

		public function findDefaults(&$fields){
			if(!isset($fields['allow_multiple_selection'])) $fields['allow_multiple_selection'] = 'no';
			if(!isset($fields['show_association'])) $fields['show_association'] = 'yes';
		}

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);

			$label = Widget::Label(__('Values'));

			$sectionManager = new SectionManager($this->_engine);
			$sections = $sectionManager->fetch(NULL, 'ASC', 'sortorder');
			$field_groups = array();

			if(is_array($sections) && !empty($sections)){
				foreach($sections as $section) $field_groups[$section->get('id')] = array('fields' => $section->fetchFields(), 'section' => $section);
			}

			$options = array();

			foreach($field_groups as $group){
				if(!is_array($group['fields'])) continue;

				$fields = array();

				foreach($group['fields'] as $f){
					if($f->get('id') != $this->get('id') && $f->canPrePopulate()){
						$fields[] = array($f->get('id'), @in_array($f->get('id'), $this->get('related_field_id')), $f->get('label'));
					}
				}

				if(is_array($fields) && !empty($fields)) $options[] = array('label' => $group['section']->get('name'), 'options' => $fields);
			}

			$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][related_field_id][]', $options, array('multiple' => 'multiple')));

			if(isset($errors['related_field_id'])) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $errors['related_field_id']));
			else $wrapper->appendChild($label);

			## Maximum entries
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][limit]', $this->get('limit'));
			$input->setAttribute('size', '3');
			$label->setValue(__('Limit to the %s most recent entries', array($input->generate())));
			$wrapper->appendChild($label);

			## Allow selection of multiple items
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' ' . __('Allow selection of multiple options'));

			$div = new XMLElement('div', NULL, array('class' => 'compact'));
			$div->appendChild($label);
			$this->appendShowAssociationCheckbox($div);
			$this->appendRequiredCheckbox($div);
			$this->appendShowColumnCheckbox($div);
			$wrapper->appendChild($div);
		}

		public function createTable(){
			return $this->_engine->Database->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				`id` int(11) unsigned NOT NULL auto_increment,
				`entry_id` int(11) unsigned NOT NULL,
				`relation_id` int(11) unsigned DEFAULT NULL,
				PRIMARY KEY	 (`id`),
				KEY `entry_id` (`entry_id`),
				KEY `relation_id` (`relation_id`)
				) ENGINE=MyISAM;"
			);
		}

		public function getExampleFormMarkup(){
			return Widget::Input('fields['.$this->get('element_name').']', '...', 'hidden');
		}

	}
