<?php

/**
 *
 */
class EntryQueryLinkAdapter extends EntryQueryListAdapter
{
    public function getFilterColumns()
    {
        return ['relation_id'];
    }

    protected function filterSingle(EntryQuery $query, $filter)
    {
        // Extract the portion of the filter which would be a search value
        $eFilter = explode(':', $filter, 2);
        $eFilterIndex = count($eFilter) === 2 ? 1 : 0;
        $eFilterValue = &$eFilter[$eFilterIndex];
        $eFilterValue = trim($eFilterValue);
        // If it is not an ID, use fetchAssociatedEntrySearchValue to convert to a proper search value
        if (General::intval($eFilterValue) === -1) {
            foreach ($this->field->get('related_field_id') as $relatedFieldId) {
                $eFilterValue = $this->field->fetchAssociatedEntrySearchValue(
                    ['handle' => General::createHandle($eFilterValue)],
                    $relatedFieldId
                );

                if ($eFilterValue) {
                    break;
                }
            }
        }
        // Use the parent's behavior, but with the search value replaced
        return parent::filterSingle($query, implode(':', $eFilter));
    }

    public function getSortColumns()
    {
        return ['relation_id'];
    }
}
