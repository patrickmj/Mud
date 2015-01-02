<?php
class Api_MudIdsMap extends Omeka_Record_Api_AbstractRecordAdapter
{
    public function getRepresentation(Omeka_Record_AbstractRecord $record)
    {
        $representation = array(
                'id'      => $record->id,
                'mid'     => $record->mid,
                'dbpedia_uri' => $record->dbpedia_uri
                );
        $representation['item'] = array(
                'id'       => $record->item_id,
                'resource' => 'items',
                'url' => self::getResourceUrl("/items/{$record->item_id}")
                );
        return $representation;
    }
}