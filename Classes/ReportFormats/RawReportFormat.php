<?php

namespace Eidsonator\BootstrapReportsBundle\Classes\ReportFormats;

use Eidsonator\BootstrapReportsBundle\lib\PhpReports\ReportFormatBase;
use Eidsonator\BootstrapReportsBundle\lib\PhpReports\Report;
use Symfony\Component\HttpFoundation\Request;

class RawReportFormat extends ReportFormatBase {
	public static function display(Report &$report, Request &$request) {
		header("Content-type: text/plain");
		header("Pragma: no-cache");
		header("Expires: 0");
		
		echo nl2br($report->content);
	}
	
	//no need to instantiate a report object, just return the source
	public static function prepareReport(Report $report) {
		$contents = Report::getReportFileContents($report->getFullPath());
		$report->content = $contents;;
		return $report;
	}
}
