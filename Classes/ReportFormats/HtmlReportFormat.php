<?php

namespace Eidsonator\BootstrapReportsBundle\Classes\ReportFormats;

use Eidsonator\BootstrapReportsBundle\lib\PhpReports\ReportFormatBase;
use Eidsonator\BootstrapReportsBundle\lib\PhpReports\Report;
use Symfony\Component\HttpFoundation\Request;

class HtmlReportFormat extends ReportFormatBase
{
	public static function display(Report &$report, Request &$request) {
		
		//determine if this is an asyncronous report or not		
		$report->async = !$request->query->has('content_only');
		if ($request->query->has('no_async')) {
            $report->async = false;
        }
		
		//if we're only getting the report content
		if($request->query->has('content_only')) {

			$template = '@BootstrapReports/Default/html/content_only.twig';
		} else {
			$template = '@BootstrapReports/Default/html/report.twig';
		}
		try {
			$additional_vars = array();
			if($request->query->has('no_charts')) {
                $additional_vars['no_charts'] = true;
            }

			$report->renderReportPage($template,$additional_vars);
            return ["template" => $template, "vars" => $additional_vars];

		}
		catch(\Exception $e) {
			if($request->query->has('content_only')) {
				$template = '@BootstrapReports/Default/html/blank_page.twig';
			}
			
			$vars = array(
				'title'=>$report->report,
				'header'=>'<h2>There was an error running your report</h2>',
				'error'=>$e->getMessage(),
				'content'=>"<h2>Report Query</h2>".$report->options['Query_Formatted'],
			);

            return ["template" => $template, "vars" => $vars];

		}
	}
}
