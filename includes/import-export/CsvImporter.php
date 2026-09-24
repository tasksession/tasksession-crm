<?php

class Comon_IE_CsvImporter extends Comon_IE_BaseImporter
{
    /** @var resource */
    private $handle;

    public function __construct(string $path)
    {
        $this->handle = fopen($path, 'r');
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    public function iterateRows(): iterable
    {
        if (!is_resource($this->handle)) {
            return;
        }
        while (($row = fgetcsv($this->handle)) !== false) {
            yield $row;
        }
    }
}
