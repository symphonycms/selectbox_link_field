<?php

    if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

    require_once FACE . '/interface.exportablefield.php';
    require_once FACE . '/interface.importablefield.php';
    require_once(EXTENSIONS . '/selectbox_link_field/lib/class.entryquerylinkadapter.php');

    class FieldSelectBox_Link extends Field implements ExportableField, ImportableField {
        private static $cache = array();

    /*-------------------------------------------------------------------------
        Definition:
    -------------------------------------------------------------------------*/

        public function __construct(){
            parent::__construct();
            $this->entryQueryFieldAdapter = new EntryQueryLinkAdapter($this);

            $this->_name = __('Select Box Link');
            $this->_required = true;
            $this->_showassociation = true;

            // Default settings
            $this->set('show_column', 'no');
            $this->set('show_association', 'yes');
            $this->set('hide_when_prepopulated', 'no');
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

        public function canPrePopulate() {
            return true;
        }

        public function isSortable(){
            $relatedFieldsId = $this->getRelatedFieldsId();
            foreach ($relatedFieldsId as $relatedFieldId) {
                $fieldSchema = $this->getFieldSchema($relatedFieldId);
                if (empty($fieldSchema)) {
                    return false;
                }
            }
            return true;
        }

        public function allowDatasourceOutputGrouping(){
            return ($this->get('allow_multiple_selection') == 'yes' ? false : true);
        }

        public function allowDatasourceParamOutput(){
            return true;
        }

        public function requiresSQLGrouping(){
            return ($this->get('allow_multiple_selection') == 'yes' ? true : false);
        }

        public function fetchSuggestionTypes()
        {
            return array('association');
        }

    /*-------------------------------------------------------------------------
        Setup:
    -------------------------------------------------------------------------*/

        public function createTable(){
            return Symphony::Database()
                ->create('tbl_entries_data_' . $this->get('id'))
                ->ifNotExists()
                ->fields([
                    'id' => [
                        'type' => 'int(11)',
                        'auto' => true,
                    ],
                    'entry_id' => 'int(11)',
                    'relation_id' => [
                        'type' => 'int(11)',
                        'null' => true,
                    ],
                ])
                ->keys([
                    'id' => 'primary',
                    'entry_id' => 'key',
                    'relation_id' => 'key',
                ])
                ->execute()
                ->success();
        }

    /*-------------------------------------------------------------------------
        Utilities:
    -------------------------------------------------------------------------*/

        public function set($field, $value){
            if($field == 'related_field_id' && !is_array($value)){
                $value = explode(',', $value);
            }
            $this->_settings[$field] = $value;
        }

        public function findOptions(array $existing_selection = null, $entry_id = null){
            $values = array();
            $limit = $this->get('limit');

            if(!is_array($this->get('related_field_id'))) return $values;

            $sections = Symphony::Database()
                ->select(['s.id', 's.sortorder', 'f.id' => 'field_id'])
                ->distinct()
                ->from('tbl_sections', 's')
                ->leftJoin('tbl_fields', 'f')
                ->on(['s.id' => '$f.parent_section'])
                ->where(['f.id' => ['in' => $this->get('related_field_id')]])
                ->orderBy('s.sortorder')
                ->execute()
                ->rows();

            foreach($sections as $_section) {
                $section = (new SectionManager)
                    ->select()
                    ->section($_section['id'])
                    ->execute()
                    ->next();
                $group = array(
                    'name' => $section->get('name'),
                    'section' => $section->get('id'),
                    'values' => array()
                );

                $results = (new EntryManager)
                    ->select()
                    ->projection(['e.id'])
                    ->section($section->get('id'))
                    ->limit($limit)
                    ->execute()
                    ->column('id');

                // if a value is already selected, ensure it is added to the list (if it isn't in the available options)
                if(!is_null($existing_selection) && !empty($existing_selection)){
                    $entries_for_field = $this->findEntriesForField($existing_selection, $_section['field_id']);
                    $results = array_merge($results, $entries_for_field);
                }

                if(is_array($results) && !empty($results)){
                    $related_values = $this->findRelatedValues($results);
                    foreach($related_values as $value){
                        $group['values'][$value['id']] = $value['value'];
                    }
                }

                if(!is_null($entry_id) && isset($group['values'][$entry_id])){
                    unset($group['values'][$entry_id]);
                }
                $values[] = $group;
            }

            return $values;
        }

        public function getToggleStates(){
            $options = $this->findOptions();
            $output = $options[0]['values'];

            if($this->get('required') !== 'yes') {
                $output[""] = __('None');
            }

            return $output;
        }

        public function toggleFieldData(array $data, $newState, $entry_id = null){
            $data['relation_id'] = $newState;
            return $data;
        }

        public function fetchAssociatedEntryCount($value){
            return Symphony::Database()
                ->select(['COUNT(*)' => 'count'])
                ->from('tbl_entries_data_' . $this->get('id'))
                ->where(['relation_id' => $value])
                ->execute()
                ->variable('count');
        }

        public function fetchAssociatedEntryIDs($value){
            return Symphony::Database()
                ->select(['entry_id'])
                ->from('tbl_entries_data_' . $this->get('id'))
                ->where(['relation_id' => $value])
                ->execute()
                ->variable('entry_id');
        }

        public function fetchAssociatedEntrySearchValue($data, $field_id = null, $parent_entry_id = null){
            // We dont care about $data, but instead $parent_entry_id
            if(!is_null($parent_entry_id)) return $parent_entry_id;

            if(!is_array($data)) return $data;

            return Symphony::Database()
                ->select(['entry_id'])
                ->from('tbl_entries_data_' . $field_id)
                ->where(['handle' => addslashes($data['handle'])])
                ->limit(1)
                ->execute()
                ->variable('entry_id');
        }

        public function findEntriesForField(array $relation_id = array(), $field_id = null) {
            if(empty($relation_id) || !is_array($this->get('related_field_id'))) return array();

            try {
                $relations = Symphony::Database()
                    ->select(['e.id'])
                    ->from('tbl_fields', 'f')
                    ->leftJoin('tbl_sections', 's')
                    ->on(['f.parent_section' => '$s.id'])
                    ->leftJoin('tbl_entries', 'e')
                    ->on(['e.section_id' => '$s.id'])
                    ->where(['f.id' => (int)$field_id])
                    ->where(['e.id' => ['in' => $relation_id]])
                    ->execute()
                    ->column('id');
            }
            catch(Exception $e){
                return array();
            }

            return $relations;
        }

        protected function findRelatedValues(array $relation_id = array()) {
            // 1. Get the field instances from the SBL's related_field_id's
            // FieldManager->fetch doesn't take an array of ID's (unlike other managers)
            // so instead we'll instead build a custom where to emulate the same result
            // We also cache the result of this where to prevent subsequent calls to this
            // field repeating the same query.
            $where = ' AND id IN (' . implode(',', $this->get('related_field_id')) . ') ';
            $hash = md5($where);
            if(!isset(self::$cache[$hash]['fields'])) {
                $fields = (new FieldManager)
                    ->select()
                    ->sort('sortorder', 'asc')
                    ->where(['id' => ['in' => $this->get('related_field_id')]])
                    ->execute()
                    ->rows();

                if(!is_array($fields)) {
                    $fields = array($fields);
                }

                self::$cache[$hash]['fields'] = $fields;
            }
            else {
                $fields = self::$cache[$hash]['fields'];
            }

            if(empty($fields)) return array();

            // 2. Find all the provided `relation_id`'s related section
            // We also cache the result using the `relation_id` as identifier
            // to prevent unnecessary queries
            $relation_id = array_filter($relation_id);
            if(empty($relation_id)) return array();

            $hash = md5(serialize($relation_id).$this->get('element_name'));

            if(!isset(self::$cache[$hash]['relation_data'])) {
                $relation_ids = Symphony::Database()
                    ->select(['e.id', 'e.section_id', 's.name', 's.handle'])
                    ->from('tbl_entries', 'e')
                    ->leftJoin('tbl_sections', 's')
                    ->on(['s.id' => '$e.section_id'])
                    ->where(['e.id' => ['in' => $relation_id]])
                    ->execute()
                    ->rows();

                // 3. Group the `relation_id`'s by section_id
                $section_ids = array();
                $section_info = array();
                foreach($relation_ids as $relation_information) {
                    $section_ids[$relation_information['section_id']][] = $relation_information['id'];

                    if(!array_key_exists($relation_information['section_id'], $section_info)) {
                        $section_info[$relation_information['section_id']] = array(
                            'name' => $relation_information['name'],
                            'handle' => $relation_information['handle']
                        );
                    }
                }

                // 4. Foreach Group, use the EntryManager to fetch the entry information
                // using the schema option to only return data for the related field
                $relation_data = array();
                foreach($section_ids as $section_id => $entry_data) {
                    $schema = array();
                    // Get schema
                    foreach($fields as $field) {
                        if($field->get('parent_section') == $section_id) {
                            $schema = array($field->get('element_name'));
                            break;
                        }
                    }

                    $section = (new SectionManager)
                        ->select()
                        ->section($section_id)
                        ->execute()
                        ->next();

                    if(($section instanceof Section) === false) continue;

                    $entries = (new EntryManager)
                        ->select()
                        ->section($section_id)
                        ->sort($section->getSortingField(), $section->getSortingOrder())
                        ->entries(array_values($entry_data))
                        ->includeAllFields()
                        ->schema($schema)
                        ->execute()
                        ->rows();

                    foreach ($entries as $entry) {
                        $field_data = $entry->getData($field->get('id'));

                        if (is_array($field_data) === false || empty($field_data)) continue;

                        // Get unformatted content:
                        if (
                            $field instanceof ExportableField
                            && in_array(ExportableField::UNFORMATTED, $field->getExportModes())
                        ) {
                            $value = $field->prepareExportValue(
                                $field_data, ExportableField::UNFORMATTED, $entry->get('id')
                            );
                        }

                        // Get values:
                        else if (
                            $field instanceof ExportableField
                            && in_array(ExportableField::VALUE, $field->getExportModes())
                        ) {
                            $value = $field->prepareExportValue(
                                $field_data, ExportableField::VALUE, $entry->get('id')
                            );
                        }

                        // Handle fields that are not exportable:
                        else {
                            $value = $field->prepareTextValue(
                                $field_data, $entry->get('id')
                            );
                        }

                        /**
                         * To ensure that the output is 'safe' for whoever consumes this function,
                         * we will sanitize the value. Before sanitizing, we will reverse sanitise
                         * the value to handle the scenario where the Field has been good and
                         * has already sanitized the value.
                         *
                         * @see https://github.com/symphonycms/symphony-2/issues/2318
                         */
                        $value = General::sanitize(General::reverse_sanitize($value));

                        $relation_data[] = array(
                            'id' =>             $entry->get('id'),
                            'section_handle' => $section_info[$section_id]['handle'],
                            'section_name' =>   $section_info[$section_id]['name'],
                            'value' =>          $value
                        );
                    }
                }

                self::$cache[$hash]['relation_data'] = $relation_data;
            }
            else {
                $relation_data = self::$cache[$hash]['relation_data'];
            }

            // 6. Return the resulting array containing the id, section_handle, section_name and value
            return $relation_data;
        }

        /**
         * Given a string (assumed to be a handle or value), this function
         * will do a lookup to field the `entry_id` from the related fields
         * of the field and returns the `entry_id`.
         *
         * @since 1.27
         * @param string $value
         * @return integer
         */
        public function fetchIDfromValue($value) {
            $id = null;
            $related_field_ids = $this->get('related_field_id');

            foreach($related_field_ids as $related_field_id) {
                try {
                    return Symphony::Database()
                        ->select(['entry_id' => 'id'])
                        ->from('tbl_entries_data_' . $related_field_id)
                        ->where(['handle' => Lang::createHandle($value)])
                        ->limit(1)
                        ->execute()
                        ->column('id');

                    // Skipping returns wrong results when doing an
                    // AND operation, return 0 instead.
                    if(!empty($return)) {
                        $id = $return[0];
                        break;
                    }
                } catch (Exception $ex) {
                    // Do nothing, this would normally be the case when a handle
                    // column doesn't exist!
                }
            }

            $value = (is_null($id)) ? 0 : (int)$id;

            return $value;
        }

    /*-------------------------------------------------------------------------
        Settings:
    -------------------------------------------------------------------------*/

        public function findDefaults(array &$settings){
            if(!isset($settings['allow_multiple_selection'])) $settings['allow_multiple_selection'] = 'no';
            if(!isset($settings['show_association'])) $settings['show_association'] = 'yes';
        }

        public function displaySettingsPanel(XMLElement &$wrapper, $errors = null){
            parent::displaySettingsPanel($wrapper, $errors);

            // Only append selected ids, load full section information asynchronously
            $options = array();

            if(is_array($this->get('related_field_id'))) {
                foreach ($this->get('related_field_id') as $related_field_id) {
                    $options[] = array($related_field_id);
                }
            }

            $label = Widget::Label(__('Values'));
            $label->appendChild(
                Widget::Select('fields['.$this->get('sortorder').'][related_field_id][]', $options, array(
                    'multiple' => 'multiple',
                    'class' => 'js-fetch-sections',
                    'data-required' => 'true',
                ))
            );

            // Add options
            if(isset($errors['related_field_id'])) {
                $wrapper->appendChild(Widget::Error($label, $errors['related_field_id']));
            }
            else {
                $wrapper->appendChild($label);
            }

            // Maximum entries
            $label = Widget::Label(__('Maximum entries'));
            $input = Widget::Input('fields['.$this->get('sortorder').'][limit]', (string)$this->get('limit'));
            $label->appendChild($input);
            $wrapper->appendChild($label);

            // Options
            $div = new XMLElement('div', null, array('class' => 'two columns'));
            $wrapper->appendChild($div);

            // Allow selection of multiple items
            $label = Widget::Label();
            $label->setAttribute('class', 'column');
            $input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');

            if($this->get('allow_multiple_selection') == 'yes') {
                $input->setAttribute('checked', 'checked');
            }

            $label->setValue($input->generate() . ' ' . __('Allow selection of multiple options'));
            $div->appendChild($label);

            // Show associations
            $this->appendShowAssociationCheckbox($div);

            // Hide when prepopulated
            $label = Widget::Label();
            $label->setAttribute('class', 'column');
            $input = Widget::Input('fields['.$this->get('sortorder').'][hide_when_prepopulated]', 'yes', 'checkbox');

            if($this->get('hide_when_prepopulated') == 'yes') {
                $input->setAttribute('checked', 'checked');
            }

            $label->setValue($input->generate() . ' ' . __('Hide when prepopulated'));
            $div->appendChild($label);

            // Requirements and table display
            $this->appendStatusFooter($wrapper);
        }

        public function checkPostFieldData($data, &$message, $entry_id = null){
            $message = null;

            if (is_array($data)) {
                $data = isset($data['relation_id'])
                    ? array_filter($data['relation_id'])
                    : array_filter($data);
            }

            if ($this->get('required') == 'yes' && (empty($data))) {
                $message = __('‘%s’ is a required field.', array($this->get('label')));

                return self::__MISSING_FIELDS__;
            }

            return self::__OK__;
        }

        public function checkFields(array &$errors, $checkForDuplicates = true) {
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
            $fields['related_field_id'] = implode(',', $this->get('related_field_id'));
            $fields['allow_multiple_selection'] = $this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no';
            $fields['hide_when_prepopulated'] = $this->get('hide_when_prepopulated') == 'yes' ? 'yes' : 'no';
            $fields['limit'] = max(1, (int)$this->get('limit'));

            if(!FieldManager::saveSettings($id, $fields)) return false;

            SectionManager::removeSectionAssociation($id);
            foreach($this->get('related_field_id') as $field_id){
                SectionManager::createSectionAssociation(null, $id, $field_id, $this->get('show_association') == 'yes' ? true : false);
            }

            return true;
        }

    /*-------------------------------------------------------------------------
        Publish:
    -------------------------------------------------------------------------*/

        public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null) {
            $entry_ids = array();
            $options = array(
                array(null, false, null)
            );

            if(!is_null($data['relation_id'])){
                if(!is_array($data['relation_id'])){
                    $entry_ids = array($data['relation_id']);
                }
                else{
                    $entry_ids = array_values($data['relation_id']);
                }
            }

            $states = $this->findOptions($entry_ids,$entry_id);
            if(!empty($states)){
                foreach($states as $s){
                    $group = array('label' => $s['name'], 'options' => array());
                    if (count($s['values']) == 0) {
                        $group['options'][] = array(null, false, __('None found.'), null, null, array('disabled' => 'disabled'));
                    }
                    else {
                        foreach($s['values'] as $id => $v){
                            $group['options'][] = array($id, in_array($id, $entry_ids), General::sanitize($v));
                        }
                    }

                    if(count($states) == 1) {
                        $options = array_merge($options, $group['options']);
                    }
                    else {
                        $options[] = $group;
                    }
                }
            }

            $fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix;
            if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';

            $label = Widget::Label($this->get('label'));
            if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));
            $label->appendChild(
                Widget::Select($fieldname, $options, ($this->get('allow_multiple_selection') == 'yes' ? array(
                    'multiple' => 'multiple') : null
                ))
            );

            if(!is_null($flagWithError)) {
                $wrapper->appendChild(Widget::Error($label, $flagWithError));
            }
            else $wrapper->appendChild($label);
        }

        public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null) {
            $status = self::__OK__;
            $result = array();

            if(!is_array($data)) {
                $result['relation_id'] = ((int)$data === 0) ? null : (int)$data;
            }
            else foreach($data as $a => $value) {
                $result['relation_id'][] = ((int)$data[$a] === 0) ? null : (int)$data[$a];
            }

            return $result;
        }

        public function getExampleFormMarkup(){
            return Widget::Input('fields['.$this->get('element_name').']', '...', 'hidden');
        }

    /*-------------------------------------------------------------------------
        Output:
    -------------------------------------------------------------------------*/

        public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null) {
            if(!is_array($data) || empty($data) || is_null($data['relation_id'])) return;

            $list = new XMLElement($this->get('element_name'));

            if(!is_array($data['relation_id'])) {
                $data['relation_id'] = array($data['relation_id']);
            }
            $related_values = $this->findRelatedValues($data['relation_id']);

            foreach($related_values as $relation) {
                $value = $relation['value'];

                $item = new XMLElement('item');
                $item->setAttribute('id', $relation['id']);
                $item->setAttribute('handle', Lang::createHandle(General::reverse_sanitize($relation['value'])));
                $item->setAttribute('section-handle', $relation['section_handle']);
                $item->setAttribute('section-name', General::sanitize($relation['section_name']));
                $item->setValue($relation['value']);

                $list->appendChild($item);
            }

            $wrapper->appendChild($list);
        }

        public function getParameterPoolValue(array $data, $entry_id = null){
            return $this->prepareExportValue($data, ExportableField::LIST_OF + ExportableField::ENTRY, $entry_id);
        }

        public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
            $result = array();

            if(!is_array($data) || (is_array($data) && !isset($data['relation_id']))) {
                return parent::prepareTableValue(null);
            }

            if(!is_array($data['relation_id'])){
                $data['relation_id'] = array($data['relation_id']);
            }

            if(!is_null($link)){
                $link->setValue($this->prepareReadableValue($data, $entry_id, true, __('None')));
                return $link->generate();
            }

            $result = $this->findRelatedValues($data['relation_id']);
            $output = '';

            foreach($result as $item){
                $link = Widget::Anchor(is_null($item['value']) ? '' : $item['value'], sprintf('%s/publish/%s/edit/%d/', SYMPHONY_URL, $item['section_handle'], $item['id']));
                $output .= $link->generate() . ', ';
            }

            return trim($output, ', ');
        }

        public function prepareTextValue($data, $entry_id = null) {
            if(!is_array($data) || (is_array($data) && !isset($data['relation_id']))) {
                return parent::prepareTextValue($data, $entry_id);
            }

            if(!is_array($data['relation_id'])){
                $data['relation_id'] = array($data['relation_id']);
            }

            $result = $this->findRelatedValues($data['relation_id']);

            $label = '';
            foreach($result as $item){
                $label .= $item['value'] . ', ';
            }

            return trim($label, ', ');
        }

    /*-------------------------------------------------------------------------
        Import:
    -------------------------------------------------------------------------*/

        public function getImportModes() {
            return array(
                'getPostdata' =>    ImportableField::ARRAY_VALUE,
                'getValue' =>       ImportableField::STRING_VALUE
            );
        }

        public function prepareImportValue($data, $mode, $entry_id = null) {
            $message = $status = null;
            $modes = (object)$this->getImportModes();

            if(!is_array($data)) {
                $data = array($data);
            }

            if($mode === $modes->getValue) {
                if ($this->get('allow_multiple_selection') === 'no') {
                    $data = array(implode('', $data));
                }

                return implode($data);
            }
            else if($mode === $modes->getPostdata) {
                // Iterate over $data, and where the value is not an ID,
                // do a lookup for it!
                foreach($data as $key => &$value) {
                    if(!is_numeric($value) && !is_null($value)){
                        $value = $this->fetchIDfromValue($value);
                    }
                }

                return $this->processRawFieldData($data, $status, $message, true, $entry_id);
            }

            return null;
        }

    /*-------------------------------------------------------------------------
        Export:
    -------------------------------------------------------------------------*/

        /**
         * Return a list of supported export modes for use with `prepareExportValue`.
         *
         * @return array
         */
        public function getExportModes() {
            return array(
                'getPostdata' =>        ExportableField::POSTDATA,
                'listEntry' =>          ExportableField::LIST_OF
                                        + ExportableField::ENTRY,
                'listEntryObject' =>    ExportableField::LIST_OF
                                        + ExportableField::ENTRY
                                        + ExportableField::OBJECT,
                'listEntryToValue' =>   ExportableField::LIST_OF
                                        + ExportableField::ENTRY
                                        + ExportableField::VALUE,
                'listValue' =>          ExportableField::LIST_OF
                                        + ExportableField::VALUE
            );
        }

        /**
         * Give the field some data and ask it to return a value using one of many
         * possible modes.
         *
         * @param mixed $data
         * @param integer $mode
         * @param integer $entry_id
         * @return array|null
         */
        public function prepareExportValue($data, $mode, $entry_id = null) {
            $modes = (object)$this->getExportModes();

            if (isset($data['relation_id']) === false) return null;

            if (is_array($data['relation_id']) === false) {
                $data['relation_id'] = array(
                    $data['relation_id']
                );
            }

            // Return postdata:
            if ($mode === $modes->getPostdata) {
                return $data;
            }

            // Return the entry IDs:
            else if ($mode === $modes->listEntry) {
                return $data['relation_id'];
            }

            // Return entry objects:
            else if ($mode === $modes->listEntryObject) {
                $items = array();

                $entries = (new EntryManager)
                    ->select()
                    ->entry($data['relation_id'])
                    ->execute()
                    ->rows();
                foreach ($entries as $entry) {
                    if (is_array($entry) === false || empty($entry)) continue;

                    $items[] = current($entry);
                }

                return $items;
            }

            // All other modes require full data:
            $data = $this->findRelatedValues($data['relation_id']);
            $items = array();

            foreach ($data as $item) {
                $item = (object)$item;

                if ($mode === $modes->listValue) {
                    $items[] = General::reverse_sanitize($item->value);
                }

                else if ($mode === $modes->listEntryToValue) {
                    $items[$item->id] = General::reverse_sanitize($item->value);
                }
            }

            return $items;
        }

    /*-------------------------------------------------------------------------
        Filtering:
    -------------------------------------------------------------------------*/

        public function fetchFilterableOperators()
        {
            return array(
                array(
                    'title' => 'is',
                    'filter' => ' ',
                    'help' => __('Find values that are an exact match for the given string.')
                ),
                array(
                    'filter' => 'sql: NOT NULL',
                    'title' => 'is not empty',
                    'help' => __('Find entries where any value is selected.')
                ),
                array(
                    'filter' => 'sql: NULL',
                    'title' => 'is empty',
                    'help' => __('Find entries where no value is selected.')
                ),
                array(
                    'filter' => 'sql-null-or-not: ',
                    'title' => 'is empty or not',
                    'help' => __('Find entries where no value is selected or it is not equal to this value.')
                ),
                array(
                    'filter' => 'not: ',
                    'title' => 'is not',
                    'help' => __('Find entries where the value is not equal to this value.')
                )
            );
        }

        public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false){
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
                $null = false;
                if(preg_match('/^not:/', $data[0])) {
                    $data[0] = preg_replace('/^not:/', null, $data[0]);
                    $negation = true;
                }
                else if(preg_match('/^sql-null-or-not:/', $data[0])) {
                    $data[0] = preg_replace('/^sql-null-or-not:/', null, $data[0]);
                    $negation = true;
                    $null = true;
                }
                else if(preg_match('/^regexp:/', $data[0])) {
                    $data[0] = preg_replace('/^regexp:/', null, $data[0]);
                }

                foreach($data as $key => &$value) {
                    // for now, I assume string values are the only possible handles.
                    // of course, this is not entirely true, but I find it good enough.
                    if(!is_numeric($value) && !is_null($value)){
                        $value = $this->fetchIDfromValue($value);
                    }
                }

                if($andOperation) {
                    $condition = ($negation) ? '!=' : '=';
                    foreach($data as $key => $bit){
                        $joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
                        $where .= " AND (`t$field_id$key`.relation_id $condition '$bit' ";

                        if($null) {
                            $where .= " OR `t$field_id$key`.`relation_id` IS NULL) ";
                        }
                        else {
                            $where .= ") ";
                        }
                    }
                }
                else {
                    $condition = ($negation) ? 'NOT IN' : 'IN';

                    // Apply a different where condition if we are using $negation. RE: #29
                    if($negation) {
                        $condition = 'NOT EXISTS';
                        $where .= " AND $condition (
                            SELECT *
                            FROM `tbl_entries_data_$field_id` AS `t$field_id`
                            WHERE `t$field_id`.entry_id = `e`.id AND `t$field_id`.relation_id IN (".implode(", ", $data).")
                        )";
                    }
                    // Normal filtering
                    else {
                        $joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
                        $where .= " AND (`t$field_id`.relation_id $condition ('".implode("', '", $data)."') ";

                        // If we want entries with null values included in the result
                        $where .= ($null) ? " OR `t$field_id`.`relation_id` IS NULL) " : ") ";
                    }
                }
            }

            return true;
        }

    /*-------------------------------------------------------------------------
        Sorting:
    -------------------------------------------------------------------------*/

        protected function getFieldSchema($fieldId) {
            try {
                return Symphony::Database()
                    ->showColumns()
                    ->from('tbl_entries_data_' . $fieldId)
                    ->where(['Field' => ['in' => ['value']]])
                    ->execute()
                    ->rows();
            }
            catch (Exception $ex) {
                // bail out
            }
            return null;
        }

        private function getRelatedFieldsId() {
            $related_field_id = $this->get('related_field_id');
            if (is_array($related_field_id)) {
                return $related_field_id;
            }
            return explode(',', $related_field_id);
        }

        public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC'){
            if(in_array(strtolower($order), array('random', 'rand'))) {
                $sort = 'ORDER BY RAND()';
            }
            else {
                $sort = array();
                $joinnedFieldSchema = array();
                $joinnedFieldsId = $this->getRelatedFieldsId();

                foreach ($joinnedFieldsId as $key => $joinnedFieldId) {
                    $joinnedFieldSchema = $this->getFieldSchema($joinnedFieldId);

                    if (empty($joinnedFieldSchema)) {
                        // bail out
                        return;
                    }
                    $joinnedFieldSchema = current($joinnedFieldSchema);
                    $sortColumn = $joinnedFieldSchema['Field'];
                    // create SQL
                    $joins .= "LEFT OUTER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed_$key` ON (`e`.`id` = `ed_$key`.`entry_id`) ";
                    $joins .= "LEFT OUTER JOIN `tbl_entries_data_$joinnedFieldId` AS `jd_$key` ON (`ed_$key`.`relation_id` = `jd_$key`.`entry_id`) ";
                    $sort[] = "`jd_$key`.`$sortColumn` $order";
                }

                if (empty($sort)) {
                    $sort = '';
                }
                else {
                    $sort = 'ORDER BY ' . implode(',', $sort);
                }
            }
        }

        public function buildSortingSelectSQL($sort, $order = 'ASC')
        {
            if ($this->isRandomOrder($order)) {
                return null;
            }
            $sort = array();
            $joinnedFieldSchema = array();
            $joinnedFieldsId = $this->getRelatedFieldsId();

            foreach ($joinnedFieldsId as $key => $joinnedFieldId) {
                $joinnedFieldSchema = $this->getFieldSchema($joinnedFieldId);

                if (empty($joinnedFieldSchema)) {
                    // bail out
                    return;
                }
                $joinnedFieldSchema = current($joinnedFieldSchema);
                $sortColumn = $joinnedFieldSchema['Field'];
                $sort[] = "`jd_$key`.`$sortColumn`";
            }

            if (!empty($sort)) {
                return implode(',', $sort);
            }
            return null;
        }

    /*-------------------------------------------------------------------------
        Grouping:
    -------------------------------------------------------------------------*/

        public function groupRecords($records){
            if(!is_array($records) || empty($records)) return;

            $groups = array($this->get('element_name') => array());

            $related_field_id = current($this->get('related_field_id'));
            $field = (new FieldManager)
                ->select()
                ->field($related_field_id)
                ->execute()
                ->next();

            if(!$field instanceof Field) return;

            foreach($records as $r){
                $data = $r->getData($this->get('id'));
                $value = (int)$data['relation_id'];

                if($value === 0) {
                    if(!isset($groups[$this->get('element_name')][$value])){
                        $groups[$this->get('element_name')][$value] = array(
                            'attr' => array(
                                'link-handle' => 'none',
                                'value' => "None"
                            ),
                            'records' => array(),
                            'groups' => array()
                        );
                    }
                }
                else {
                    $related_data = (new EntryManager)
                        ->select()
                        ->entry($value)
                        ->section($field->get('parent_section'))
                        ->schema([$field->get('element_name')])
                        ->limit(1)
                        ->execute()
                        ->next();

                    if(!$related_data instanceof Entry) continue;

                    $primary_field = $field->prepareTableValue($related_data->getData($related_field_id));

                    if(!isset($groups[$this->get('element_name')][$value])){
                        $groups[$this->get('element_name')][$value] = array(
                            'attr' => array(
                                'link-id' => $data['relation_id'],
                                'link-handle' => Lang::createHandle($primary_field),
                                'value' => General::sanitize($primary_field)),
                            'records' => array(),
                            'groups' => array()
                        );
                    }
                }

                $groups[$this->get('element_name')][$value]['records'][] = $r;
            }

            return $groups;
        }

    }
