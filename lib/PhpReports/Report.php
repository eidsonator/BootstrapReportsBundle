<?php

namespace Eidsonator\BootstrapReportsBundle\lib\PhpReports;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\Container;
use Eidsonator\BootstrapReportsBundle\Classes\Headers;
use Eidsonator\BootstrapReportsBundle\lib\FileSystemCache\lib\FileSystemCache;

class Report extends ContainerAware
{
    public $report;
    public $macros = array();
    public $exported_headers = array();
    public $options = array();
    public $is_ready = false;
    public $async = false;
    public $headers = array();
    public $header_lines = array();
    public $raw_query;
    public $use_cache;

    protected $controller;
    protected $baseURL;
    protected $raw;
    protected $raw_headers;
    protected $filters = array();
    protected $filemtime;
    protected $has_run = false;
    protected $fullPath;

    private $environments;
    private $config;

    public function __construct($report, $macros = array(), $environment = null, $use_cache = null, Container $container, $controller)
    {
        $this->report = $report;
        $this->container = $container;
        $this->controller = $controller;
        $this->reportDirectory = $this->container->getParameter('reportDirectory');
        $this->defaultFileExtensionMapping = $this->container->getParameter('default_file_extension_mapping');
        $this->environments = $this->container->getParameter('environments');
        $this->config = [];
        $this->config['report_formats'] = $this->container->getParameter('report_formats');
        $this->config['mail_settings'] = $this->container->getParameter('mail_settings');
        $this->fullPath = $this->container->getParameter('reportDirectory') . $this->report;

        if (!file_exists($this->fullPath)) {
            throw new \Exception('Report not found - ' . $report);
        }

        $this->filemtime = filemtime($this->fullPath);

        $this->use_cache = $use_cache;

        //get the raw report file
        $this->raw = self::getReportFileContents($this->fullPath);

        //if there are no headers in this report
        if (strpos($this->raw, "\n\n") === false) {
            throw new \Exception('Report missing headers - ' . $report);
        }

        //split the raw report into headers and code
        list($this->raw_headers, $this->raw_query) = explode("\n\n", $this->raw, 2);

        $this->macros = array();
        foreach ($macros as $key => $value) {
            $this->addMacro($key, $value);
        }

        $this->parseHeaders();

        $this->options['Environment'] = $environment;

        $this->initDb();

        $this->getTimeEstimate();
    }

    public function setController($controller)
    {
        $this->controller = $controller;
    }

    public function getController()
    {
        return $this->controller;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function getEnvironments()
    {
        return $this->environments;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getFullPath()
    {
        return $this->fullPath;
    }

    public function getReportDirectory()
    {
        return $this->reportDirectory;
    }

    public static function getFileLocation($report)
    {
        //make sure the report path doesn't go up a level for security reasons
        if (strpos($report, "..") !== false) {
            $reportdir = realpath(PhpReports::$config['reportDir']) . '/';
            $reportpath = substr(realpath(PhpReports::$config['reportDir'] . '/' . $report), 0, strlen($reportdir));

            if ($reportpath !== $reportdir) throw new \Exception('Invalid report - ' . $report);
        }

        $reportDir = PhpReports::$config['reportDir'];
        return $reportDir . '/' . $report;
    }

    public function setReportFileContents($new_contents)
    {


        //echo "SAVING CONTENTS TO " . $this->fullPath;;

        if (!file_put_contents($this->fullPath, $new_contents)) {
            throw new \Exception("Failed to set report contents");
        }

        return "\n" . $new_contents;
    }

    public static function getReportFileContents($fullPath)
    {
        $contents = file_get_contents($fullPath);

        //convert EOL to unix format
        return str_replace(array("\r\n", "\r"), "\n", $contents);
    }

    public function getDatabase()
    {
        if (isset($this->options['Database']) && $this->options['Database']) {
            $environment = $this->getEnvironment();

            if (isset($environment[$this->options['Database']])) {
                return $environment[$this->options['Database']];
            }
        }

        return array();
    }

    public function getEnvironment()
    {
        return PhpReports::$config['environments'][$this->options['Environment']];
    }

    public function addMacro($name, $value)
    {
        $this->macros[$name] = $value;
    }

    public function exportHeader($name, $params)
    {
        $this->exported_headers[] = array('name' => $name, 'params' => $params);
    }

    public function getCacheKey()
    {
        return FileSystemCache::generateCacheKey(array(
            'report' => $this->report,
            'macros' => $this->macros,
            'database' => $this->options['Environment']
        ), 'report_results');
    }

    public function getReportTimesCacheKey()
    {
        return FileSystemCache::generateCacheKey($this->report, 'report_times');
    }

    protected function retrieveFromCache()
    {
        if (!$this->use_cache) {
            return false;
        }

        return FileSystemCache::retrieve($this->getCacheKey(), 'results', $this->filemtime);
    }

    protected function storeInCache()
    {
        if (isset($this->options['Cache']) && is_numeric($this->options['Cache'])) {
            $ttl = intval($this->options['Cache']);
        } else {
            //default to caching things for 10 minutes
            $ttl = 600;
        }

        FileSystemCache::store($this->getCacheKey(), $this->options, 'results', $ttl);
    }

    protected function parseHeaders()
    {
        //default the report to being ready
        //if undefined variables are found in the headers, set to false
        $this->is_ready = true;

        $this->options = array(
            'Filters' => array(),
            'Variables' => array(),
            'Includes' => array(),
        );
        $this->headers = array();

        $lines = explode("\n", $this->raw_headers);

        //remove empty headers and remove comment characters
        $fixed_lines = array();
        foreach ($lines as $line) {
            if (empty($line)) continue;

            //if the line doesn't start with a comment character, skip
            if (!in_array(substr($line, 0, 2), array('--', '/*', '//', ' *')) && $line[0] !== '#') continue;

            //remove comment from start of line and skip if empty
            $line = trim(ltrim($line, "-*/# \t"));
            if (!$line) continue;

            $fixed_lines[] = $line;
        }
        $lines = $fixed_lines;

        $name = null;
        $value = '';
        foreach ($lines as $line) {
            $has_name_value = preg_match('/^\s*[A-Z0-9_\-]+\s*\:/', $line);

            //if this is the first header and not in the format name:value, assume it is the report name
            if (!$has_name_value && $name === null && !isset($this->options['Name'])) {
                $this->parseHeader('Info', array('name' => $line));
            } else {
                //if this is a continuation of another header
                if (!$has_name_value) {
                    $value .= "\n" . trim($line);
                } //if this is a new header
                else {
                    //if the previous header didn't have a name, assume it is the description
                    if ($value && $name === null) {
                        $this->parseHeader('Info', array('description' => $value));
                    } //otherwise, parse the previous header
                    elseif ($value) {
                        $this->parseHeader($name, $value);
                    }

                    list($name, $value) = explode(':', $line, 2);
                    $name = trim($name);
                    $value = trim($value);

                    if (strtoupper($name) === $name) $name = ucfirst(strtolower($name));
                }
            }
        }
        //parse the last header
        if ($value && $name) {
            $this->parseHeader($name, $value);
        }

        //try to infer report type from file extension
        if (!isset($this->options['Type'])) {
            $file_type = pathinfo($this->report, PATHINFO_EXTENSION);

            if (!isset($this->defaultFileExtensionMapping[$file_type])) {
                throw new \Exception("Unknown report type - " . $this->report);
            } else {
                $this->options['Type'] = $this->defaultFileExtensionMapping[$file_type];
            }
        }

        if (!isset($this->options['Database'])) $this->options['Database'] = strtolower($this->options['Type']);

        if (!isset($this->options['Name'])) $this->options['Name'] = $this->report;
    }

    public function parseHeader($name, $value, $dataSet = null)
    {
        $className = 'Eidsonator\\BootstrapReportsBundle\\Classes\\Headers\\' . $name . 'Header';
        if (class_exists($className)) {
            if ($dataSet !== null && isset($className::$validation) && isset($className::$validation['dataset'])) {
                $value['dataset'] = $dataSet;
            }
            $className::parse($name, $value, $this);
            if (!in_array($name, $this->headers)) {
                $this->headers[] = $name;
            }
        } else {
            throw new \Exception("Unknown header '$name' - " . $this->report);
        }
    }

    public function addFilter($dataset, $column, $type, $options)
    {
        // If adding for multiple datasets
        if (is_array($dataset)) {
            foreach ($dataset as $d) {
                $this->addFilter($d, $column, $type, $options);
            }
        } // If adding for all datasets
        else if ($dataset === true) {
            $this->addFilter('all', $column, $type, $options);
        } // If adding for a single dataset
        else {
            if (!isset($this->filters[$dataset])) $this->filters[$dataset] = array();
            if (!isset($this->filters[$dataset][$column])) $this->filters[$dataset][$column] = array();

            $this->filters[$dataset][$column][$type] = $options;
        }

    }

    protected function applyFilters($dataset, $column, $value, $row)
    {
        // First, apply filters for all datasets
        if (isset($this->filters['all']) && isset($this->filters['all'][$column])) {
            foreach ($this->filters['all'][$column] as $type => $options) {
                $classname = "Eidsonator\\BootstrapReportsBundle\\Classes\\Filters\\{$type}Filter";
                $value = $classname::filter($value, $options, $this, $row);

                //if the column should not be displayed
                if ($value === false) return false;
            }
        }

        // Then apply filters for this specific dataset
        if (isset($this->filters[$dataset]) && isset($this->filters[$dataset][$column])) {
            foreach ($this->filters[$dataset][$column] as $type => $options) {
                $classname = "Eidsonator\\BootstrapReportsBundle\\Classes\\Filters\\{$type}Filter";
                $value = $classname::filter($value, $options, $this, $row);

                //if the column should not be displayed
                if ($value === false) return false;
            }
        }

        return $value;
    }

    protected function initDb()
    {
        //if the database isn't set, use the first defined one from config
        $environments = $this->environments;
        if (!$this->options['Environment']) {
            $this->options['Environment'] = current(array_keys($environments));
        }

        //set database options
        $environment_options = array();
        foreach ($environments as $key => $params) {
            $environment_options[] = array(
                'name' => $key,
                'selected' => $key === $this->options['Environment']
            );
        }
        $this->options['Environments'] = $environment_options;

        //add a host macro
        if (isset($environments[$this->options['Environment']]['host'])) {
            $this->macros['host'] = $environments[$this->options['Environment']]['host'];
        }

        $className = 'Eidsonator\\BootstrapReportsBundle\\Classes\\ReportTypes\\' . $this->options['Type'] . 'ReportType';

        if (!class_exists($className)) {
            throw new \Exception("Unknown report type '{$this->options['Type']}'");
        }

        $className::init($this);
    }

    public function getRaw()
    {
        return $this->raw;
    }

    public function getUrl()
    {
        return 'report/html/?report=' . urlencode($this->report);
    }

    public function prepareVariableForm()
    {
        $vars = array();

        if ($this->options['Variables']) {
            foreach ($this->options['Variables'] as $var => $params) {
                if (!is_array($params)) {
                    continue;
                }
                if (!isset($params['name'])) {
                    $params['name'] = ucwords(str_replace(array('_', '-'), ' ', $var));
                }
                if (!isset($params['type'])) {
                    $params['type'] = 'string';
                }
                if (!isset($params['options'])) {
                    $params['options'] = false;
                }
                $params['value'] = $this->macros[$var];
                $params['key'] = $var;

                if ($params['type'] === 'select') {
                    $params['is_select'] = true;

                    foreach ($params['options'] as $key => $option) {
                        if (!is_array($option)) {
                            $params['options'][$key] = array(
                                'display' => $option,
                                'value' => $option
                            );
                        }
                        if ($params['options'][$key]['value'] == $params['value']) $params['options'][$key]['selected'] = true;
                        elseif (is_array($params['value']) && in_array($params['options'][$key]['value'], $params['value'])) $params['options'][$key]['selected'] = true;
                        else $params['options'][$key]['selected'] = false;

                        if ($params['multiple']) {
                            $params['is_multiselect'] = true;
                            $params['choices'] = count($params['options']);
                        }
                    }
                } else {
                    if ($params['multiple']) {
                        $params['is_textarea'] = true;
                    }
                }

                if (isset($params['modifier_options'])) {
                    $modifier_value = isset($this->macros[$var . '_modifier']) ? $this->macros[$var . '_modifier'] : null;

                    foreach ($params['modifier_options'] as $key => $option) {
                        if (!is_array($option)) {
                            $params['modifier_options'][$key] = array(
                                'display' => $option,
                                'value' => $option
                            );
                        }

                        if ($params['modifier_options'][$key]['value'] == $modifier_value) $params['modifier_options'][$key]['selected'] = true;
                        else $params['modifier_options'][$key]['selected'] = false;
                    }

                }

                $vars[] = $params;
            }
        }

        return $vars;
    }

    protected function _runReport()
    {
        if (!$this->is_ready) {
            throw new \Exception("Report is not ready.  Missing variables");
        }

        PhpReports::setVar('Report', $this);

        //release the write lock on the session file
        //so the session isn't locked while the report is running
        session_write_close();

        $className = "Eidsonator\\BootstrapReportsBundle\\Classes\\ReportTypes\\{$this->options['Type']}ReportType";
        if (!class_exists($className)) {
            throw new \Exception("Unknown report type '" . $this->options['Type'] . "'");
        }

        foreach ($this->headers as $header) {
            $headerClass = "Eidsonator\\BootstrapReportsBundle\\Classes\\Headers\\{$header}Header";
            $headerClass::beforeRun($this);
        }

        $className::openConnection($this);
        $dataSets = $className::run($this);
        $className::closeConnection($this);

        // Convert old single dataset format to multi-dataset format
        if (!isset($dataSets[0]['rows']) || !is_array($dataSets[0]['rows'])) {
            $dataSets = array(
                array(
                    'rows' => $dataSets
                )
            );
        }

        // Only include a subset of datasets
        $include = array_keys($dataSets);
        if (isset($_GET['dataset'])) {
            $include = array($_GET['dataset']);
        } elseif (isset($_GET['datasets'])) {
            // If just a single dataset was specified, make it an array
            if (!is_array($_GET['datasets'])) {
                $include = explode(',', $_GET['datasets']);
            } else {
                $include = $_GET['datasets'];
            }
        }

        $this->options['DataSets'] = array();
        foreach ($include as $i) {
            if (!isset($dataSets[$i])) continue;
            $this->options['DataSets'][$i] = $dataSets[$i];
        }

        $this->parseDynamicHeaders();
    }

    protected function parseDynamicHeaders()
    {
        foreach ($this->options['DataSets'] as $i => &$dataset) {
            if (isset($dataset['headers'])) {
                foreach ($dataset['headers'] as $j => $header) {
                    if (isset($header['header']) && isset($header['value'])) {
                        $this->parseHeader($header['header'], $header['value'], $i);
                    }
                }
            }
        }
    }

    protected function getTimeEstimate()
    {
        $report_times = FileSystemCache::retrieve($this->getReportTimesCacheKey());
        if (!$report_times) return;

        sort($report_times);

        $sum = array_sum($report_times);
        $count = count($report_times);
        $average = $sum / $count;
        $quartile1 = $report_times[round(($count - 1) / 4)];
        $median = $report_times[round(($count - 1) / 2)];
        $quartile3 = $report_times[round(($count - 1) * 3 / 4)];
        $min = min($report_times);
        $max = max($report_times);
        $iqr = $quartile3 - $quartile1;
        $range = (1.5) * $iqr;

        $sample_square = 0;
        for ($i = 0; $i < $count; $i++) {
            $sample_square += pow($report_times[$i], 2);
        }
        $standard_deviation = sqrt($sample_square / $count - pow(($average), 2));

        $this->options['time_estimate'] = array(
            'times' => $report_times,
            'count' => $count,
            'min' => round($min, 2),
            'max' => round($max, 2),
            'median' => round($median, 2),
            'average' => round($average, 2),
            'q1' => round($quartile1, 2),
            'q3' => round($quartile3, 2),
            'iqr' => round($range, 2),
            'sum' => round($sum, 2),
            'stdev' => round($standard_deviation, 2)
        );
    }

    protected function prepareDataSets()
    {
        foreach ($this->options['DataSets'] as $i => $dataset) {
            $this->prepareRows($i);
        }
        $this->options['Rows'] = $this->options['DataSets'][0]['rows'];
        $this->options['Count'] = $this->options['DataSets'][0]['count'];
    }

    protected function prepareRows($dataset)
    {
        $rows = array();

        //generate list of all values for each numeric column
        //this is used to calculate percentiles/averages/etc.
        $vals = array();
        foreach ($this->options['DataSets'][$dataset]['rows'] as $row) {
            foreach ($row as $key => $value) {
                if (!isset($vals[$key])) $vals[$key] = array();

                if (is_numeric($value)) $vals[$key][] = $value;
            }
        }
        $this->options['DataSets'][$dataset]['values'] = $vals;

        foreach ($this->options['DataSets'][$dataset]['rows'] as $row) {
            $rowval = array();

            $i = 1;
            foreach ($row as $key => $value) {
                $val = new ReportValue($i, $key, $value);

                //apply filters for the column key
                $val = $this->applyFilters($dataset, $key, $val, $row);
                //apply filters for the column position
                if ($val) $val = $this->applyFilters($dataset, $i, $val, $row);

                if ($val) {
                    $rowval[] = $val;
                }

                $i++;
            }

            $first = !$rows;

            $rows[] = array(
                'values' => $rowval,
                'first' => $first
            );
        }

        $this->options['DataSets'][$dataset]['rows'] = $rows;
        $this->options['DataSets'][$dataset]['count'] = count($rows);
    }

    public function run()
    {
        if ($this->has_run) return true;

        //at this point, all the headers are parsed and we haven't run the report yet
        foreach ($this->headers as $header) {
            $classname = "Eidsonator\\BootstrapReportsBundle\\Classes\\Headers\\{$header}Header";
            $classname::afterParse($this);
        }

        //record how long it takes to run the report
        $start = microtime(true);

        if ($this->is_ready && !$this->async) {
            //if the report is cached
            if ($options = $this->retrieveFromCache()) {
                $this->options = $options;
                $this->options['FromCache'] = true;
            } else {
                $this->_runReport();
                $this->prepareDataSets();
                $this->storeInCache();
            }

            //add this to the list of recently run reports
            $recently_run_key = FileSystemCache::generateCacheKey('recently_run');
            $recently_run = FileSystemCache::retrieve($recently_run_key);
            if ($recently_run === false) {
                $recently_run = array();
            }
            array_unshift($recently_run, $this->report);
            if (count($recently_run) > 200) $recently_run = array_slice($recently_run, 0, 200);
            FileSystemCache::store($recently_run_key, $recently_run);
        }

        //call the beforeRender callback for each header
        foreach ($this->headers as $header) {
            $classname = "Eidsonator\\BootstrapReportsBundle\\Classes\\Headers\\{$header}Header";
            $classname::beforeRender($this);
        }

        $this->options['Time'] = round(microtime(true) - $start, 5);

        if ($this->is_ready && !$this->async && !isset($this->options['FromCache'])) {
            //get current report times for this report
            $report_times = FileSystemCache::retrieve($this->getReportTimesCacheKey());
            if (!$report_times) $report_times = array();
            //only keep the last 10 times for each report
            //this keeps the timing data up to date and relevant
            if (count($report_times) > 10) array_shift($report_times);

            //store report times
            $report_times[] = $this->options['Time'];
            FileSystemCache::store($this->getReportTimesCacheKey(), $report_times);
        }

        $this->has_run = true;
    }

    public function setBaseURL($url)
    {
        $this->baseURL = $url;
    }

    public function getReportVariables($additionalVars = [])
    {
        $templateVars = array(
            'is_ready' => $this->is_ready,
            'async' => $this->async,
            'report_url' => $this->baseURL . '?' . $_SERVER['QUERY_STRING'], //todo add base?
            'report_querystring' => $_SERVER['QUERY_STRING'],
            'recent_reports' => $this->controller->getRecentReports(),
            'report' => $this->report,
            'vars' => $this->prepareVariableForm(),
            'macros' => $this->macros,
            'config' => $this->config,
        );

        if (is_array($additionalVars)) {
            $templateVars = array_merge($templateVars, $additionalVars);
        }
        $templateVars = array_merge($templateVars, $this->options);

        return $templateVars;
    }

    public function renderReportPage($template = 'html/report', &$additional_vars = array())
    {
        $this->run();

        $templateVars = array(
            'is_ready' => $this->is_ready,
            'async' => $this->async,
            'report_url' => $this->baseURL . '?' . $_SERVER['QUERY_STRING'], //todo add base?
            'report_querystring' => $_SERVER['QUERY_STRING'],
            'recent_reports' => $this->controller->getRecentReports(),
            'report' => $this->report,
            'vars' => $this->prepareVariableForm(),
            'macros' => $this->macros,
            'config' => $this->config,
        );

        $additional_vars = array_merge($templateVars, $additional_vars);
        $additional_vars = array_merge($additional_vars, $this->options);

    }
    public function expandSql($sql, $macros) {
        $env = new \Twig_Environment(new \Twig_Loader_Array([]));
        $template = $env->createTemplate($sql);
        return $template->render($macros);
    }
}

?>
