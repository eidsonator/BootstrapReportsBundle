<?php

namespace Eidsonator\BootstrapReportsBundle\Classes\Headers;

use Eidsonator\BootstrapReportsBundle\lib\PhpReports\HeaderBase;
use Eidsonator\BootstrapReportsBundle\lib\PhpReports\Report;

class IncludeHeader extends HeaderBase
{
	static $validation = array(
		'report'=>array(
			'required'=>true,
			'type'=>'string'
		)
	);
	
	public static function init($params, Report &$report) {
		if($params['report'][0] === '/') {
			$reportPath = substr($params['report'], 1);
		}
		else {
			$reportPath = dirname($report->report) . '/' . $params['report'];
		}
		
		
		if(!file_exists(REPORT_DIRECTORY . '/' . $reportPath)) {
			$possible_reports = glob(REPORT_DIRECTORY . '/' . $reportPath . '.*');
			
			if($possible_reports) {
				$reportPath = substr($possible_reports[0], strlen(REPORT_DIRECTORY . '/'));
			}
			else {
				throw new \Exception("Unknown report in INCLUDE header '$reportPath'");
			}
		}

		$container = $report->getContainer();
		$includedReport = new Report($reportPath, [], null, null, $container);
		
		//parse any exported headers from the included report
		foreach($includedReport->exported_headers as $header) {
			$report->parseHeader($header['name'],$header['params']);
		}
		
		if(!isset($report->options['Includes'])) $report->options['Includes'] = array();
		
		$report->options['Includes'][] = $includedReport;
	}
	
	public static function parseShortcut($value) {
		return array(
			'report'=>$value
		);
	}
}
