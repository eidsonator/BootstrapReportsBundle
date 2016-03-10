<?php

namespace Eidsonator\BootstrapReportsBundle\Classes\ReportFormats;

use Eidsonator\BootstrapReportsBundle\lib\PhpReports\ReportFormatBase;
use Eidsonator\BootstrapReportsBundle\lib\PhpReports\Report;
use Symfony\Component\HttpFoundation\Request;

class TextReportFormat extends ReportFormatBase
{
    public static function display(Report &$report, Request &$request)
    {
        header("Content-type: text/plain");
        header("Pragma: no-cache");
        header("Expires: 0");

        $report->use_cache = true;

        //run the report
        $report->run();

        if (!$report->options['DataSets']) {
            return "";
        }
        $return = "";
        foreach ($report->options['DataSets'] as $i => $dataset) {
            if (isset($dataset['title'])){
                $return .= $dataset['title'] . "\n";
            }

            $return .= TextReportFormat::displayDataSet($dataset);

            // If this isn't the last dataset, add some spacing
            if ($i < count($report->options['DataSets']) - 1) {
                $return .= "\n\n";
            }
        }

        return $return;
    }

    protected static function displayDataSet($dataset)
    {
        /**
         * This code taken from Stack Overflow answer by ehudokai
         * http://stackoverflow.com/a/4597190
         */

        //first get your sizes
        $sizes = array();
        $first_row = $dataset['rows'][0];
        foreach ($first_row['values'] as $key => $value) {
            $key = $value->key;
            $value = $value->getValue();

            //initialize to the size of the column name
            $sizes[$key] = strlen($key);
        }
        foreach ($dataset['rows'] as $row) {
            foreach ($row['values'] as $key => $value) {
                $key = $value->key;
                $value = $value->getValue();

                $length = strlen($value);
                if ($length > $sizes[$key]) $sizes[$key] = $length; // get largest result size
            }
        }

        //top of output
        $return = "";
        foreach ($sizes as $length) {
            $return .= "+" . str_pad("", $length + 2, "-");
        }
        $return .= "+\n";

        // column names
        foreach ($first_row['values'] as $key => $value) {
            $key = $value->key;
            $value = $value->getValue();

            $return .= "| ";
            $return .= str_pad($key, $sizes[$key] + 1);
        }
        $return .= "|\n";

        //line under column names
        foreach ($sizes as $length) {
            $return .= "+" . str_pad("", $length + 2, "-");
        }
        $return .= "+\n";

        //output data
        foreach ($dataset['rows'] as $row) {
            foreach ($row['values'] as $key => $value) {
                $key = $value->key;
                $value = $value->getValue();

                $return .= "| ";
                $return .= str_pad($value, $sizes[$key] + 1);
            }
            $return .= "|\n";
        }

        //bottom of output
        foreach ($sizes as $length) {
            $return .= "+" . str_pad("", $length + 2, "-");
        }
        $return .= "+\n";
        return $return;
    }
}
