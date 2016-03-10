<?php

namespace Eidsonator\BootstrapReportsBundle\lib\PhpReports;

use Eidsonator\BootstrapReportsBundle\lib\PhpReports\Report;

abstract class FilterBase
{
    /**
     * Filter a datapoint in the report
     * @param String $value - The current value
     * @param array $options - An array of filter options
     * @param Report $report - The report object
     * @param array $row - The report row the value is in
     * @return String The filtered value or false if the column should not be shown
     */
    public static function filter($value, $options=array(), Report &$report, &$row) {}
}
