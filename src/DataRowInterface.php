<?php
namespace Centeron\Grids;

/**
 * Interface DataRowInterface
 *
 * Interface for row of data received from data provider.
 *
 * @package Centeron\Grids
 */
interface DataRowInterface
{
    /**
     * Returns row ID (row number).
     *
     * @return integer
     */
    public function getId();

    /**
     * Returns value of specified field.
     *
     * @param string|FieldConfig $field
     * @return mixed
     */
    public function getCellValue($field);
}
