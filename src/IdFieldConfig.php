<?php
namespace Centeron\Grids;

/**
 * Class IdFieldConfig
 *
 * IdFieldConfig is a column type that will render a row number in table rows.
 *
 * @package Centeron\Grids
 */
class IdFieldConfig extends FieldConfig
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('ID', 'Id');
    }

    /**
     * Returns row id (row number).
     *
     * @param DataRowInterface $row
     * @return int
     */
    public function getValue(DataRowInterface $row)
    {
        return $row->getId();
    }
}
