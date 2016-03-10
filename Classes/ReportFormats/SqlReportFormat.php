<?php
namespace Eidsonator\BootstrapReportsBundle\Classes\ReportFormats;
use Eidsonator\BootstrapReportsBundle\lib\PhpReports\ReportFormatBase;
use Eidsonator\BootstrapReportsBundle\lib\PhpReports\Report;
use Symfony\Component\HttpFoundation\Request;

class SqlReportFormat extends ReportFormatBase {
	public static function display(Report &$report, Request &$request) {
		header("Content-type: text/plain");
		header("Pragma: no-cache");
		header("Expires: 0");
		$vars = [];
		$template = '@BootstrapReports/Default/sql/report.twig';
		$report->renderReportPage($template, $vars);
		return ["template" => $template, "vars" => $vars];
	}
}
