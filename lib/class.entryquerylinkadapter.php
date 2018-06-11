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

    public function getSortColumns()
    {
        return ['relation_id'];
    }
}
