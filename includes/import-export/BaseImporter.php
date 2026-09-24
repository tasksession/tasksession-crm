<?php
/**
 * Future hook for ClickUp/Trello/etc. Normalizes source rows to the same shape as CSV mapping.
 */
abstract class Comon_IE_BaseImporter
{
    /** @return iterable<list<string>> */
    abstract public function iterateRows(): iterable;
}
