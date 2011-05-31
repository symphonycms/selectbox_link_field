<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	Class fieldSelectBox_Link extends Field{

		static private $cacheRelations = array();
		static private $cacheFields = array();
		static private $cacheValues = array();

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Select Box Link');
			$this->_required = true;
			$this->_showassociation = true;

			// Default settings
			$this->set('show_column', 'no');
			$this->set('show_association', 'yes');
			$this->set('required', 'yes');
			$this->set('limit', 20);
			$this->set('related_field_id', array());
		}

		public function canToggle(){
			return ($this->get('allow_multiple_selection') == 'yes' ? false : true);
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

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`relation_id` int(11) unsigned DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `relation_id` (`relation_id`)
				) ENGINE=MyISAM;"
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function set($field, $value){
			if($field == 'related_field_id' && !is_array($value)){
				$value = explode(',', $value);
			}
			$this->_fields[$field] = $value;
		}

		public function findOptions(array $existing_selection=NULL){
			$values = array();
			$limit = $this->get('limit');

			if(!is_array($this->get('related_field_id'))) return $values;

			// find the sections of the related fields
			$sections = Symphony::Database()->fetch("
				SELECT DISTINCT (s.id), s.name, f.id as `field_id`
				FROM `tbl_sections` AS `s`
				LEFT JOIN `tbl_fields` AS `f` ON `s`.id = `f`.parent_section
				WHERE `f`.id IN ('" . implode("','", $this->get('related_field_id')) . "')
				ORDER BY s.sortorder ASC
			");

			if(is_array($sections) && !empty($sections)){
				foreach($sections as $section){

					$group = array('name' => $section['name'], 'section' => $section['id'], 'values' => array());

					// build a list of entry IDs with the correct sort order
					$entryManager = new EntryManager($this->_Parent);
					$entries = $entryManager->fetch(NULL, $section['id'], $limit, 0, null, null, false, false);

					$results = array();
					foreach($entries as $entry) {
						$results[] = (int)$entry['id'];
					}

					// if a value is already selected, ensure it is added to the list (if it isn't in the available options)
					if(!is_null($existing_selection) && !empty($existing_selection)){
						foreach($existing_selection as $key => $entry_id) {
							$entry_id = (int)$entry_id;
							$field_id = $this->findFieldIDFromRelationID($entry_id);
							if($field_id == $section['field_id']) {
								$results[] = $entry_id;
							}
						}
					}

					if(is_array($results) && !empty($results)){
						foreach($results as $entry_id){
							$value = $this->findPrimaryFieldValueFromRelationID($entry_id);
							$group['values'][$entry_id] = $value['value'];
						}
					}

					$values[] = $group;
				}
			}

			return $values;
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

		public function fetchAssociatedEntryCount($value){
			return Symphony::Database()->fetchVar('count', 0, sprintf("
					SELECT COUNT(*) as `count`
					FROM `tbl_entries_data_%d`
					WHERE `relation_id` = %d
				",
				$this->get('id'), $value
			));
		}

		public function fetchAssociatedEntryIDs($value){
			return Symphony::Database()->fetchCol('entry_id', sprintf("
					SELECT `entry_id`
					FROM `tbl_entries_data_%d`
					WHERE `relation_id` = %d
				",
				$this->get('id'), $value
			));
		}

		public function fetchAssociatedEntrySearchValue($data, $field_id=NULL, $parent_entry_id=NULL){
			// We dont care about $data, but instead $parent_entry_id
			if(!is_null($parent_entry_id)) return $parent_entry_id;

			if(!is_array($data)) return $data;

			$searchvalue = Symphony::Database()->fetchRow(0, sprintf("
				SELECT `entry_id` FROM `tbl_entries_data_%d`
				WHERE `handle` = '%s'
				LIMIT 1",
				$field_id, addslashes($data['handle'])
			));

			return $searchvalue['entry_id'];
		}

		public function findFieldIDFromRelationID($id){
			if(is_null($id) || !is_array($this->get('related_field_id'))) return null;

			if (isset(self::$cacheRelations[$this->get('id').'_'.$id])) {
				return self::$cacheRelations[$this->get('id').'_'.$id];
			}

			try{
				// Get the `section_id` given the `entry_id`
				$section_id = Symphony::Database()->fetchVar('section_id', 0, sprintf("
						SELECT `section_id`
						FROM `tbl_entries`
						WHERE `id` = %d
						LIMIT 1
					", $id
				));

				// Figure out which `related_field_id` is from that section
				$field_id = Symphony::Database()->fetchVar('field_id', 0, sprintf("
						SELECT f.`id` AS `field_id`
						FROM `tbl_fields` AS `f`
						LEFT JOIN `tbl_sections` AS `s` ON f.parent_section = s.id
						WHERE `s`.id = %d
						AND f.id IN (%s)
						LIMIT 1
					",
					$section_id, implode(",", $this->get('related_field_id'))
				));
			}
			catch(Exception $e){
				return null;
			}

			self::$cacheRelations[$this->get('id').'_'.$id] = $field_id;

			return $field_id;
		}

		protected function findPrimaryFieldValueFromRelationID($entry_id){
			if(!is_numeric($entry_id)) return null;

			$field_id = $this->findFieldIDFromRelationID($entry_id);

			if (!isset(self::$cacheFields[$this->get('id').'_'.$entry_id.'_'.$field_id])) {
				self::$cacheFields[$this->get('id').'_'.$entry_id.'_'.$field_id] = Symphony::Database()->fetchRow(0, "
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

			$primary_field = self::$cacheFields[$this->get('id').'_'.$entry_id.'_'.$field_id];

			if(!$primary_field) return null;

			$fm = new FieldManager($this->_Parent);
			$field = $fm->fetch($field_id);

			if (!isset(self::$cacheValues[$this->get('id').'_'.$entry_id.'_'.$field_id])) {
				self::$cacheValues[$this->get('id').'_'.$entry_id.'_'.$field_id] = Symphony::Database()->fetchRow(0, sprintf("
						SELECT *
				 		FROM `tbl_entries_data_%d`
				 		WHERE `entry_id` = %d
						ORDER BY `id` DESC
						LIMIT 1
				", $field_id, $entry_id));
			}

			$data = self::$cacheValues[$this->get('id').'_'.$entry_id.'_'.$field_id];

			if(empty($data)) return null;

			$primary_field['value'] = $field->prepareTableValue($data);

			return $primary_field;
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function findDefaults(&$fields){
			if(!isset($fields['allow_multiple_selection'])) $fields['allow_multiple_selection'] = 'no';
			if(!isset($fields['show_association'])) $fields['show_association'] = 'yes';
		}

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);

			$sectionManager = new SectionManager($this->_engine);
			$sections = $sectionManager->fetch(NULL, 'ASC', 'sortorder');
			$options = array();

			if(is_array($sections) && !empty($sections)) foreach($sections as $section){
				$section_fields = $section->fetchFields();
				if(!is_array($section_fields)) continue;

				$fields = array();
				foreach($section_fields as $f){
					if($f->get('id') != $this->get('id') && $f->canPrePopulate()) {
						$fields[] = array(
							$f->get('id'),
							is_array($this->get('related_field_id')) ? in_array($f->get('id'), $this->get('related_field_id')) : false,
							$f->get('label')
						);
					}
				}

				if(!empty($fields)) {
					$options[] = array(
						'label' => $section->get('name'),
						'options' => $fields
					);
				}
			}

			$label = Widget::Label(__('Values'));
			$label->appendChild(
				Widget::Select('fields['.$this->get('sortorder').'][related_field_id][]', $options, array(
					'multiple' => 'multiple'
				))
			);

			// Add options
			if(isset($errors['related_field_id'])) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $errors['related_field_id']));
			}
			else $wrapper->appendChild($label);

			// Maximum entries
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][limit]', $this->get('limit'));
			$input->setAttribute('size', '3');
			$label->setValue(__('Limit to the %s most recent entries', array($input->generate())));
			$wrapper->appendChild($label);

			// Allow selection of multiple items
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

		public function checkFields(&$errors, $checkForDuplicates = true) {
			parent::checkFields($errors, $checkForDuplicates);

			$related_fields = $this->get('related_field_id');
			if(empty($related_fields)){
				$errors['related_field_id'] = __('This is a required field.');
			}

			return (is_array($errors) && !empty($errors) ? self::__ERROR__ : self::__OK__);
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

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id'");

			if(!Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle())) return false;

			$this->removeSectionAssociation($id);
			foreach($this->get('related_field_id') as $field_id){
				$this->createSectionAssociation(NULL, $id, $field_id, $this->get('show_association') == 'yes' ? true : false);
			}

			return true;
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$entry_ids = array();
			$options = array();

			if(!is_null($data['relation_id'])){
				if(!is_array($data['relation_id'])){
					$entry_ids = array($data['relation_id']);
				}
				else{
					$entry_ids = array_values($data['relation_id']);
				}
			}

			if($this->get('required') != 'yes') $options[] = array(NULL, false, NULL);

			$states = $this->findOptions($entry_ids);
			if(!empty($states)){
				foreach($states as $s){
					$group = array('label' => $s['name'], 'options' => array());
					foreach($s['values'] as $id => $v){
						$group['options'][] = array($id, in_array($id, $entry_ids), General::sanitize($v));
					}
					$options[] = $group;
				}
			}

			$fieldname = 'fields'.$prefix.'['.$this->get('element_name').']'.$postfix;
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';

			$label = Widget::Label($this->get('label'));
			$label->appendChild(
				Widget::Select($fieldname, $options, ($this->get('allow_multiple_selection') == 'yes' ? array(
					'multiple' => 'multiple') : NULL
				))
			);

			if(!is_null($error)) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $error));
			}
			else $wrapper->appendChild($label);
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			$status = self::__OK__;

			if(!is_array($data)) return array('relation_id' => $data);
			if(empty($data)) return null;

			$result = array();

			foreach($data as $a => $value) {
				$result['relation_id'][] = (int)$data[$a];
			}

			return $result;
		}

		public function getExampleFormMarkup(){
			return Widget::Input('fields['.$this->get('element_name').']', '...', 'hidden');
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
			if(!is_array($data) || empty($data) || is_null($data['relation_id'])) return;

			$list = new XMLElement($this->get('element_name'));

			if(!is_array($data['relation_id'])) {
				$data['relation_id'] = array($data['relation_id']);
			}

			foreach($data['relation_id'] as $relation_id){
				$primary_field = $this->findPrimaryFieldValueFromRelationID((int)$relation_id);

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

		public function getParameterPoolValue($data){
			return $data['relation_id'];
		}

		public function prepareTableValue($data, XMLElement $link=NULL){
			$result = array();

			if(!is_array($data) || (is_array($data) && !isset($data['relation_id']))) {
				return parent::prepareTableValue(null);
			}

			if(!is_array($data['relation_id'])){
				$data['relation_id'] = array($data['relation_id']);
			}

			foreach($data['relation_id'] as $relation_id) {
				$relation_id = (int)$relation_id;

				if($relation_id <= 0) continue;

				$primary_field = $this->findPrimaryFieldValueFromRelationID($relation_id);

				if(!is_array($primary_field) || empty($primary_field)) continue;

				$result[$relation_id] = $primary_field;
			}

			if(!is_null($link)){
				$label = '';
				foreach($result as $item){
					$label .= ' ' . $item['value'];
				}
				$link->setValue(General::sanitize(trim($label)));
				return $link->generate();
			}

			$output = '';

			foreach($result as $relation_id => $item){
				$link = Widget::Anchor($item['value'], sprintf('%s/symphony/publish/%s/edit/%d/', URL, $item['section_handle'], $relation_id));
				$output .= $link->generate() . ' ';
			}

			return trim($output);
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){
			$field_id = $this->get('id');

			if(preg_match('/^sql:\s*/', $data[0], $matches)) {
				$data = trim(array_pop(explode(':', $data[0], 2)));

				// Check for NOT NULL (ie. Entries that have any value)
				if(strpos($data, "NOT NULL") !== false) {

					$joins .= " LEFT JOIN
									`tbl_entries_data_{$field_id}` AS `t{$field_id}`
								ON (`e`.`id` = `t{$field_id}`.entry_id)";
					$where .= " AND `t{$field_id}`.relation_id IS NOT NULL ";

				}
				// Check for NULL (ie. Entries that have no value)
				else if(strpos($data, "NULL") !== false) {

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

	/*-------------------------------------------------------------------------
		Sorting:
	-------------------------------------------------------------------------*/

		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`ed`.`relation_id` $order");
		}

	/*-------------------------------------------------------------------------
		Grouping:
	-------------------------------------------------------------------------*/

		public function groupRecords($records){
			if(!is_array($records) || empty($records)) return;

			$groups = array($this->get('element_name') => array());

			foreach($records as $r){
				$data = $r->getData($this->get('id'));
				$value = $data['relation_id'];
				$primary_field = $this->findPrimaryFieldValueFromRelationID($data['relation_id']);

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

	}
