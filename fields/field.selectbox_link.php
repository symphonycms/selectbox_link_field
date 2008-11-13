<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	Class fieldSelectBox_Link extends Field{
		
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Select Box Link';
		}

		function canFilter(){
			return true;
		}

		function allowDatasourceOutputGrouping(){
			return true;
		}

		public function getParameterPoolValue($data){
			return $data['relation_id'];
		}		

		function groupRecords($records){

			if(!is_array($records) || empty($records)) return;

			$groups = array($this->get('element_name') => array());

			foreach($records as $r){
				$data = $r->getData($this->get('id'));

				$value = $data['relation_id'];

				$primary_field = $this->__findPrimaryFieldValueFromRelationID($data['relation_id']);

				if(!isset($groups[$this->get('element_name')][$value])){
					$groups[$this->get('element_name')][$value] = array('attr' => array('link-id' => $data['relation_id'], 'link-handle' => Lang::createHandle($primary_field['value'])),
																		'records' => array(), 'groups' => array());	
				}	

				$groups[$this->get('element_name')][$value]['records'][] = $r;

			}

			return $groups;

		}

		function prepareTableValue($data, XMLElement $link=NULL){

			if(is_array($data['relation_id'])) $entry_id = $data['relation_id'][0];
			else $entry_id = $data['relation_id'];

			if(!$data['relation_id'] || !$primary_field = $this->__findPrimaryFieldValueFromRelationID($data['relation_id'])) return parent::prepareTableValue(NULL);

			$label = $primary_field['value'];

			if($link){
				$link->setValue(General::sanitize($label));
				//$view = Widget::Anchor('(view)', URL . '/symphony/publish/'.$primary_field['section_handle'].'/edit/' . $entry_id . '/');
				return $link->generate(); // . ' ' . $view->generate();
			}

			else{
				$link = Widget::Anchor(General::sanitize($label), URL . '/symphony/publish/'.$primary_field['section_handle'].'/edit/' . $entry_id . '/');
				return $link->generate();
			}
		}

		private function __findPrimaryFieldValueFromRelationID($id){

			$primary_field = $this->Database->fetchRow(0,

				"SELECT `f`.*, `s`.handle AS `section_handle`
				 FROM `tbl_fields` AS `f`
				 INNER JOIN `tbl_sections` AS `s` ON `s`.id = `f`.parent_section
				 WHERE `f`.id = '".$this->get('related_field_id')."'
				 ORDER BY `f`.sortorder ASC 
				 LIMIT 1"

			);

			if(!$primary_field) return NULL;

			$field = $this->_Parent->create($primary_field['type']);

			$data = $this->Database->fetchRow(0, 
				"SELECT *
				 FROM `tbl_entries_data_".$this->get('related_field_id')."`
				 WHERE `entry_id` = '$id' ORDER BY `id` DESC LIMIT 1"
			);

			if(empty($data)) return NULL;

			$primary_field['value'] = $field->prepareTableValue($data);	

			return $primary_field;

		}

		function checkPostFieldData($data, &$message, $entry_id=NULL){

			$message = NULL;

			if(empty($data)){
				$message = 'Records for this section must be created the proper way. Shoo';
				return self::__MISSING_FIELDS__;
			}

			return self::__OK__;

		}

		function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){

			$status = self::__OK__;
			$data = (is_array($data) ? $data[0] : $data);
			return array('relation_id' => $data);

		}

		function fetchAssociatedEntryCount($value){
			return $this->_engine->Database->fetchVar('count', 0, "SELECT count(*) AS `count` FROM `tbl_entries_data_".$this->get('id')."` WHERE `relation_id` = '$value'");
		}

		function fetchAssociatedEntryIDs($value){
			return $this->_engine->Database->fetchCol('entry_id', "SELECT `entry_id` FROM `tbl_entries_data_".$this->get('id')."` WHERE `relation_id` = '$value'");
		}		

		function appendFormattedElement(&$wrapper, $data, $encode=false){

			if(!isset($data['relation_id']) || empty($data['relation_id'])) return;

			$primary_field = $this->__findPrimaryFieldValueFromRelationID($data['relation_id']);		
			$section = $this->_engine->Database->fetchRow(0, "SELECT `id`, `handle` FROM `tbl_sections` WHERE `id` = '".$primary_field['parent_section']."' LIMIT 1");

			$wrapper->appendChild(new XMLElement($this->get('element_name'), NULL, array('section-id' => $section['id'], 'section-handle' => $section['handle'], 'link-id' => $data['relation_id'], 'link-handle' => Lang::createHandle($primary_field['value']))));

		}
		
		function findOptions(){

			$values = array();

			$sql = "SELECT DISTINCT `value`, `entry_id` FROM `tbl_entries_data_".$this->get('related_field_id')."`
					ORDER BY `value` DESC";

			if($results = $this->Database->fetch($sql)){
				foreach($results as $r){
					$values[$r['entry_id']] = $r['value'];
				}
			}

			return $values;

		}		

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			$states = $this->findOptions();

			if(is_array($data['relation_id'])) $entry_id = $data['relation_id'][0];
			else $entry_id = $data['relation_id'];
			
			$options = array();

			foreach($states as $id => $v){
				$options[] = array($id, $id == $entry_id, $v);
			}
			
			$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix;
			
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Select($fieldname, $options));
			
			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);		
		}

/*		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix;

			$label = Widget::Label($this->get('label'));

			$span = new XMLElement('span');

			if(is_array($data['relation_id'])) $entry_id = $data['relation_id'][0];
			else $entry_id = $data['relation_id'];

			if(!$primary_field = $this->__findPrimaryFieldValueFromRelationID($entry_id)){
				$span->setValue('None');
				$flagWithError = 'Records for this section must be created the proper way. Shoo';
			}

			else{

				$text = $data['relation_id'] . ': ' . $primary_field['value'];
				$span->appendChild(Widget::Anchor(General::sanitize($text), URL . '/symphony/publish/'.$primary_field['section_handle'].'/edit/' . $entry_id . '/'));
				$span->appendChild(Widget::Input($fieldname, $data['relation_id'], 'hidden'));

			}

			$label->appendChild($span);

			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);

		}*/
		
		function commit(){

			if(!parent::commit()) return false;
			
			$id = $this->get('id');

			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
			if($this->get('related_field_id') != '') $fields['related_field_id'] = $this->get('related_field_id');
			$fields['allow_multiple_selection'] = 'no'; //($this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no');

			$this->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			
			if(!$this->Database->insert($fields, 'tbl_fields_' . $this->handle())) return false;

			$this->removeSectionAssociation($id);
			
			$section_id = $this->Database->fetchVar('parent_section', 0, "SELECT `parent_section` FROM `tbl_fields` WHERE `id` = '".$fields['related_field_id']."' LIMIT 1");
			$this->createSectionAssociation($section_id, $id);
			
			return true;
					
		}

		function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (strtolower($order) == 'random' ? 'RAND()' : "`ed`.`relation_id` $order");
		}

		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			if($andOperation):

				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND `t$field_id$key`.relation_id = '$bit' ";
				}

			else:

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.relation_id IN ('".@implode("', '", $data)."') ";

			endif;

			return true;

		}

		function displaySettingsPanel(&$wrapper, $errors=NULL){		
			
			parent::displaySettingsPanel($wrapper, $errors);

			$div = new XMLElement('div', NULL, array('class' => 'group'));
			
			$label = Widget::Label('Options');
			
			$sectionManager = new SectionManager($this->_engine);
		    $sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$field_groups = array();
			
			if(is_array($sections) && !empty($sections))
				foreach($sections as $section) $field_groups[$section->get('id')] = array('fields' => $section->fetchFields(), 'section' => $section);
			
			$options = array();
			
			foreach($field_groups as $group){
				
				if(!is_array($group['fields'])) continue;
				
				$fields = array();
				foreach($group['fields'] as $f){
					if($f->get('id') != $this->get('id') && $f->canPrePopulate()) $fields[] = array($f->get('id'), ($this->get('related_field_id') == $f->get('id')), $f->get('label'));
				}
				
				if(is_array($fields) && !empty($fields)) $options[] = array('label' => $group['section']->get('name'), 'options' => $fields);
			}

			$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][related_field_id]', $options));
			$div->appendChild($label);
						
			if(isset($errors['related_field_id'])) $wrapper->appendChild(Widget::wrapFormElementWithError($div, $errors['related_field_id']));
			else $wrapper->appendChild($div);
						
			## Allow selection of multiple items
			/*$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') $input->setAttribute('checked', 'checked');			
			$label->setValue($input->generate() . ' Allow selection of multiple options');
			$wrapper->appendChild($label);*/
			
			$this->appendShowColumnCheckbox($wrapper);
						
		}


		//function displaySettingsPanel(&$wrapper, $errors=NULL){
        //
		//	parent::displaySettingsPanel($wrapper, $errors);
        //
		//	$sql = "SELECT DISTINCT s.`id`, s.`name` 
		//			FROM tbl_sections AS `s` ".($this->get('parent_section') != '' ? " WHERE `s`.id != '".$this->get('parent_section')."'" : '')."
		//			ORDER BY s.`sortorder`";
        //
		//	$sections = $this->Database->fetch($sql);
        //
		//	$association_sections = $this->get('section_association_list');
        //
		//	if(!is_array($association_sections)){
		//		$association_sections = preg_split('/\,\s*/i', $association_sections, -1, PREG_SPLIT_NO_EMPTY);
		//		$association_sections = array_map('trim', $association_sections);
		//	}
        //
		//	## Section
		//	$label = Widget::Label('Related Section');
        //
		//	$options = array();
        //
		//	if(is_array($sections) && !empty($sections)){
		//		foreach($sections as $s) $options[] = array($s['id'], in_array($s['id'], $association_sections), $s['name']);
		//	}			
        //
		//	$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][section_association_list][]', $options, array('multiple' => 'multiple')));
        //
		//	if(isset($errors['section_association_list'])) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $errors['section_association_list']));
		//	else $wrapper->appendChild($label);
        //
		//	$this->appendShowColumnCheckbox($wrapper);
        //
		//}

		function createTable(){

			return $this->_engine->Database->query(

				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `relation_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `relation_id` (`relation_id`)
				) TYPE=MyISAM;"

			);
		}

		public function getExampleFormMarkup(){
			return Widget::Input('fields['.$this->get('element_name').']', '...', 'hidden');
		}			

	}



/*		function findOptions(){

			$values = array();

			$sql = "SELECT DISTINCT `value`, `entry_id` FROM `tbl_entries_data_".$this->get('related_field_id')."`
					ORDER BY `value` DESC";
					
			if($results = $this->Database->fetch($sql)){
				foreach($results as $r){
					$values[$r['entry_id']] = $r['value'];
				}
			}
			
			return $values;

		}

				
		function displaySettingsPanel(&$wrapper, $errors=NULL){		
			
			parent::displaySettingsPanel($wrapper, $errors);

			$div = new XMLElement('div', NULL, array('class' => 'group'));
			
			$label = Widget::Label('Options');
			
			$sectionManager = new SectionManager($this->_engine);
		    $sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$field_groups = array();
			
			if(is_array($sections) && !empty($sections))
				foreach($sections as $section) $field_groups[$section->get('id')] = array('fields' => $section->fetchFields(), 'section' => $section);
			
			$options = array(
				array('', false, 'None'),
			);
			
			foreach($field_groups as $group){
				
				if(!is_array($group['fields'])) continue;
				
				$fields = array();
				foreach($group['fields'] as $f){
					if($f->get('id') != $this->get('id') && $f->canPrePopulate()) $fields[] = array($f->get('id'), ($this->get('related_field_id') == $f->get('id')), $f->get('label'));
				}
				
				if(is_array($fields) && !empty($fields)) $options[] = array('label' => $group['section']->get('name'), 'options' => $fields);
			}
			
			$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][related_field_id]', $options));
			$div->appendChild($label);
						
			if(isset($errors['related_field_id'])) $wrapper->appendChild(Widget::wrapFormElementWithError($div, $errors['related_field_id']));
			else $wrapper->appendChild($div);
						
			## Allow selection of multiple items
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') $input->setAttribute('checked', 'checked');			
			$label->setValue($input->generate() . ' Allow selection of multiple options');
			$wrapper->appendChild($label);
			
			$this->appendShowColumnCheckbox($wrapper);
						
		}

	*/

