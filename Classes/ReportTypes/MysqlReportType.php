<?php

namespace Eidsonator\BootstrapReportsBundle\Classes\ReportTypes;

use Eidsonator\BootstrapReportsBundle\lib\PhpReports\ReportTypeBase;
use Eidsonator\BootstrapReportsBundle\lib\PhpReports\Report;
use Eidsonator\BootstrapReportsBundle\lib\SqlFormatter\lib\SqlFormatter;

class MysqlReportType extends ReportTypeBase
{
    public static function init(Report &$report)
    {
        $environments = $report->getEnvironments();

        if (!isset($environments[$report->options['Environment']][$report->options['Database']])) {
            throw new \Exception("No ".$report->options['Database']." info defined for environment '".$report->options['Environment']."'");
        }

        //make sure the syntax highlighting is using the proper class
        SqlFormatter::$pre_attributes = "class='prettyprint linenums lang-sql'";

        $mysql = $environments[$report->options['Environment']][$report->options['Database']];

        //default host macro to mysql's host if it isn't defined elsewhere
        if (!isset($report->macros['host'])) {
            $report->macros['host'] = $mysql['host'];
            $report->options['Variables']['host'] = $mysql['host'];
        }

        //replace legacy shorthand macro format
        foreach ($report->macros as $key=>$value) {
            if (!isset($report->options['Variables'][$key])) {
                continue;
            }
            $params = $report->options['Variables'][$key];

            //macros shortcuts for arrays
            if (isset($params['multiple']) && $params['multiple']) {
                //allow {macro} instead of {% for item in macro %}{% if not item.first %},{% endif %}{{ item.value }}{% endfor %}
                //this is shorthand for comma separated list
                $report->raw_query = preg_replace('/([^\{])\{'.$key.'\}([^\}])/','$1{% for item in '.$key.' %}{% if not loop.first %},{% endif %}\'{{ item }}\'{% endfor %}$2',$report->raw_query);

                //allow {(macro)} instead of {% for item in macro %}{% if not item.first %},{% endif %}{{ item.value }}{% endfor %}
                //this is shorthand for quoted, comma separated list
                $report->raw_query = preg_replace('/([^\{])\{\('.$key.'\)\}([^\}])/','$1{% for item in '.$key.' %}{% if not loop.first %},{% endif %}(\'{{ item }}\'){% endfor %}$2',$report->raw_query);
            } else { //macros sortcuts for non-arrays
                //allow {macro} instead of {{macro}} for legacy support
                $report->raw_query = preg_replace('/([^\{])(\{'.$key.'+\})([^\}])/','$1{$2}$3',$report->raw_query);
            }
        }

        //if there are any included reports, add the report sql to the top
        if (isset($report->options['Includes'])) {
            $included_sql = '';
            foreach ($report->options['Includes'] as &$included_report) {
                $included_sql .= trim($included_report->raw_query)."\n";
            }

            $report->raw_query = $included_sql . $report->raw_query;
        }

        //set a formatted query here for debugging.  It will be overwritten below after macros are substituted.
        $report->options['Query_Formatted'] = SqlFormatter::format($report->raw_query);
    }

    public static function openConnection(&$report) {
        if(isset($report->conn)) return;

        $environments = $report->getEnvironments();
        $config = $environments[$report->options['Environment']][$report->options['Database']];

        //the default is to use a user with read only privileges
        $username = $config['user'];
        $password = $config['pass'];
        $host = $config['host'];

        //if the report requires read/write privileges
        if(isset($report->options['access']) && $report->options['access']==='rw') {
            if(isset($config['user_rw'])) $username = $config['user_rw'];
            if(isset($config['pass_rw'])) $password = $config['pass_rw'];
            if(isset($config['host_rw'])) $host = $config['host_rw'];
        }

        if(!($report->conn = mysqli_connect($host, $username, $password))) {
            throw new \Exception('Could not connect to Mysql: '.mysqli_error($report->conn));
        }

        if(isset($config['database'])) {
            if(!mysqli_select_db($report->conn, $config['database'])) {
                throw new \Exception('Could not select Mysql database: '.mysqli_error($report->conn));
            }
        }
    }

    public static function closeConnection(&$report) {
        if(!isset($report->conn)) return;
        mysqli_close($report->conn);
        unset($report->conn);
    }

    public static function getVariableOptions($params, &$report) {
        $query = 'SELECT DISTINCT '.$params['column'].' FROM '.$params['table'];

        if(isset($params['where'])) {
            $query .= ' WHERE '.$params['where'];
        }

        if(isset($params['order']) && in_array($params['order'], array('ASC', 'DESC')) ) {
            $query .= ' ORDER BY '.$params['column'].' '.$params['order'];
        }

        $result = mysqli_query($report->conn, $query);

        if(!$result) {
            throw new \Exception("Unable to get variable options: ".mysqli_error($report->conn));
        }

        $options = array();

        if(isset($params['all'])) $options[] = 'ALL';

        while($row = mysqli_fetch_assoc($result)) {
            $options[] = $row[$params['column']];
        }

        return $options;
    }

    public static function run(&$report) {
        $macros = $report->macros;
        foreach($macros as $key=>$value) {
            if(is_array($value)) {
                $first = true;
                foreach($value as $key2=>$value2) {
                    $value[$key2] = mysqli_real_escape_string($report->conn, trim($value2));
                    $first = false;
                }
                $macros[$key] = $value;
            }
            else {
                $macros[$key] = mysqli_real_escape_string($report->conn, $value);
            }

            if($value === 'ALL') $macros[$key.'_all'] = true;
        }

        //add the config and environment settings as macros
        $macros['config'] = $report->getConfig();
        $macros['environment'] = $report->getEnvironments()[$report->options['Environment']];

        //expand macros in query
        $macros = $report->getReportVariables($macros);
        $twig = clone $report->getController()->get('twig');
        $twig->setLoader(new \Twig_Loader_String());
        $sql = $twig->render($report->raw_query,$macros);

        $report->options['Query'] = $sql;

        $report->options['Query_Formatted'] = SqlFormatter::format($sql);

        //split into individual queries and run each one, saving the last result
        $queries = SqlFormatter::splitQuery($sql);

        $datasets = array();

        $explicit_datasets = preg_match('/--\s+@dataset(\s*=\s*|\s+)true/',$sql);

        foreach($queries as $i=>$query) {
            $is_last = $i === count($queries)-1;

            //skip empty queries
            $query = trim($query);
            if(!$query) continue;

            $result = mysqli_query($report->conn, $query);
            if(!$result) {
                throw new \Exception("Query failed: ".mysqli_error($report->conn));
            }

            //if this query had an assert=empty flag and returned results, throw error
            if(preg_match('/^--[\s+]assert[\s]*=[\s]*empty[\s]*\n/',$query)) {
                if(mysqli_fetch_assoc($result))  throw new \Exception("Assert failed.  Query did not return empty results.");
            }

            // If this query should be included as a dataset
            if((!$explicit_datasets && $is_last) || preg_match('/--\s+@dataset(\s*=\s*|\s+)true/',$query)) {
                $dataset = array('rows'=>array());

                while($row = mysqli_fetch_assoc($result)) {
                    $dataset['rows'][] = $row;
                }

                // Get dataset title if it has one
                if(preg_match('/--\s+@title(\s*=\s*|\s+)(.*)/',$query,$matches)) {
                    $dataset['title'] = $matches[2];
                }

                $datasets[] = $dataset;
            }
        }

        return $datasets;
}
}
