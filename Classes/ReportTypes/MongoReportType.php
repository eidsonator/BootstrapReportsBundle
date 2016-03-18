<?php

namespace Eidsonator\BootstrapReportsBundle\Classes\ReportTypes;

use Eidsonator\BootstrapReportsBundle\lib\PhpReports\ReportTypeBase;
use Eidsonator\BootstrapReportsBundle\lib\PhpReports\Report;


class MongoReportType extends ReportTypeBase {
	public static function init(Report &$report) {
		$environments = $report->getEnvironments();
		
		if(!isset($environments[$report->options['Environment']][$report->options['Database']])) {
			throw new \Exception("No ".$report->options['Database']." database defined for environment '".$report->options['Environment']."'");
		}
		
		$mongo = $environments[$report->options['Environment']][$report->options['Database']];
		
		//default host macro to mysql's host if it isn't defined elsewhere
		if(!isset($report->macros['host'])) $report->macros['host'] = $mongo['host'];
		
		//if there are any included reports, add it to the top of the raw query
		if(isset($report->options['Includes'])) {
			$included_code = '';
			foreach($report->options['Includes'] as &$included_report) {
				$included_code .= trim($included_report->raw_query)."\n";
			}
			
			$report->raw_query = $included_code . $report->raw_query;
		}
	}
	
	public static function openConnection(&$report) {
		
	}
	
	public static function closeConnection(&$report) {
		
	}
	
	public static function run(&$report) {		
		$eval = '';
		foreach($report->macros as $key=>$value) {
			if(is_array($value)) {
				$value = json_encode($value);
			}
			else {
				$value = "'" . addslashes($value) . "'";
			}

			$eval .= 'var '.$key.' = '.$value.';'."\n";
		}
		$eval .= $report->raw_query;

		$environments = $report->getEnvironments();
		$config = $environments[$report->options['Environment']][$report->options['Database']];

		$mongoClient = isset($config['path'])? $config['path'] : 'mongo';
		$mongo_database = isset($report->options['Mongodatabase'])? $report->options['Mongodatabase'] : '';
		$mongo_database = 'test';
		//command without eval string
		$command = "$mongoClient {$config['host']}:{$config['port']}/$mongo_database --quiet --eval ";

		//easy to read formatted query
		$report->options['Query_Formatted'] = '<div>
			<pre style="background-color: black; color: white; padding: 10px 5px;">$ '.$command.'"..."</pre>'.
			'Eval String:'.
			'<pre class="prettyprint linenums lang-js">'.htmlentities($eval).'</pre>
		</div>';

		//  Removes multi-line comments and does not create
		//  a blank line, also treats white spaces/tabs
		$eval = preg_replace('!/\*.*?\*/!s', '', $eval);
		//  Removes single line '//' comments, treats blank characters
		$eval = preg_replace('![ \t]*//.*[ \t]*[\r\n]!', '', $eval);
		//  Strip blank lines
		$eval = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $eval);
		//remove new lines
		$eval = trim(preg_replace('/\s+/', ' ', $eval));

		//escape the eval string and add it to the command
		$command .= '"' . $eval . '"';

		$report->options['Query'] = '$ '.$command;

		//include stderr so we can capture shell errors (like "command mongo not found")
		$result = shell_exec($command.' 2>&1');
		
		$result = trim($result);
		//remove lines up to the first [ in case we got a result, preceded by a worthless message like the 'no need to zero out....'
		$position = strpos($result, '[ {');
		$result = substr($result, $position);
		$json = json_decode($result, true);
		if($json === NULL) throw new \Exception($result);
		
		return $json;
	}
}
