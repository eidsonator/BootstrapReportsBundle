<?php

namespace Eidsonator\BootstrapReportsBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Eidsonator\BootstrapReportsBundle\lib\PhpReports\Report;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Eidsonator\BootstrapReportsBundle\lib\FileSystemCache\lib\FileSystemCache;
use Eidsonator\BootstrapReportsBundle\lib\simplediff\SimpleDiff;
use Symfony\Component\DependencyInjection\ContainerInterface;



class DefaultController extends Controller
{
    private $reportDirectory;
    private $defaultFileExtensionMapping;

    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);
        $this->reportDirectory = $this->container->getParameter('reportDirectory');
        $this->defaultFileExtensionMapping = $this->container->getParameter('default_file_extension_mapping');
        if (!defined('REPORT_DIRECTORY')) {
            define('REPORT_DIRECTORY', $this->reportDirectory);
        }
    }

    public function listReportsAction()
    {
        $errors = array();

        $reports = $this->getReports($this->reportDirectory, $errors);

        $template_vars['reports'] = $reports;
        $template_vars['report_errors'] = $errors;
        $template_vars['recent_reports'] = $this->getRecentReports();
        $start = microtime(true);
        return $this->render('@BootstrapReports/Default/html/report_list.twig',$template_vars);
    }

    public function listReportsJsonAction(Request $request)
    {

        $response = new JsonResponse();
        $parts = [];
        $this->generateReportListRecursive(null, $parts);
        $response->setData($parts);
        return $response;
    }

    public function emailAction(Request $request)
    {
        $mailSettings = $this->container->getParameter('mail_settings');
        $response = new JsonResponse();
        if(!isset($_REQUEST['email']) || !filter_var($_REQUEST['email'], FILTER_VALIDATE_EMAIL)) {
            $response->setData(['error' => 'Valid email address required']);
            return $response;
        }
        if(!isset($_REQUEST['url'])) {
            $response->setData(['error' => 'Report url required']);
            return $response;
        }
        if(!isset($mailSettings['enabled']) || !$mailSettings['enabled']) {
            $response->setData(['error' => 'Email is disabled on this server']);
            return $response;
        }
        if(!isset($mailSettings['from'])) {
            $response->setData(['error' => 'Email settings have not been properly configured on this server']);
            return $response;
        }

        $from = $mailSettings['from'];
        $subject = $_REQUEST['subject']? $_REQUEST['subject'] : 'Database Report';
        $body = $_REQUEST['message']? $_REQUEST['message'] : "You've been sent a database report!";
        $email = $_REQUEST['email'];
        $link = $_REQUEST['url'];
        $csv_link = str_replace('report/html?','report/csv?',$link);
        $table_link = str_replace('report/html?','report/table?',$link);
        $text_link = str_replace('report/html?','report/text?',$link);

        // Get the CSV file attachment and the inline HTML table
        $csv = $this->urlDownload($csv_link);
        $table = $this->urlDownload($table_link);
        $text = $this->urlDownload($text_link);

        $email_text = $body . "\n\n" . $text . "\n\nView the report online at $link";
        $email_html = "<p>$body</p>$table<p>View the report online at <a href=\"" . htmlentities($link) . "\">" .htmlentities($link) . "</a></p>";

        // Create the message
        $message = \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setFrom($from)
            ->setTo($email)
            //text body
            ->setBody($email_text)
            //html body
            ->addPart($email_html, 'text/html')
        ;

        $attachment = \Swift_Attachment::newInstance()
            ->setFilename('report.csv')
            ->setContentType('text/csv')
            ->setBody($csv)
        ;

        $message->attach($attachment);

        try {
            // Send the message
            $result = $this->get('mailer')->send($message);

        }
        catch (\Exception $e) {
            $response->setData(['error' => $e->getMessage()]);
            return $response;
        }
        if($result) {
            $response->setData(['success' => true]);
        }
        else {
            $response->setData(['error' => 'Failed to send email to requested recipient']);
        }
        return $response;
    }

    protected function urlDownload($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    protected function generateReportListRecursive($reports = null, &$parts)
    {
        if($reports === null) {
            $errors = array();
            $reports = $this->getReports($this->reportDirectory, $errors);
        }

        //weight by popular reports
        $recently_run = FileSystemCache::retrieve(FileSystemCache::generateCacheKey('recently_run'));
        $popular = array();
        if($recently_run !== false) {
            foreach($recently_run as $report) {
                if(!isset($popular[$report])) $popular[$report] = 1;
                else $popular[$report]++;
            }
        }

        foreach($reports as $report) {
            if($report['is_dir'] && $report['children']) {
                //skip if the directory doesn't have a title
                if(!isset($report['Title']) || !$report['Title']) continue;
                $part = $this->generateReportListRecursive($report['children'], $parts);
                //$part = trim(self::getReportListJSON($report['children']),'[],');
//                if($part) $parts[] = $part;
            }
            else {
                //skip if report is marked as dangerous
                if ((isset($report['stop'])&&$report['stop']) || isset($report['Caution']) || isset($report['warning'])) {
                    continue;
                }
                //skip if report is marked as ignore
                if (isset($report['ignore']) && $report['ignore']) {
                    continue;
                }

                if (!isset($report['report'])) {
                    continue;
                }
                if(isset($popular[$report['report']])) {
                    $popularity = $popular[$report['report']];
                }
                else $popularity = 0;

                $parts[] = [
                    'name'=>$report['Name'],
                    'url'=>$report['url'],
                    'popularity'=>$popularity
                ];
            }
        }
        //return '['.trim(implode(',',$parts),',').']';
    }

    public function displayHtmlAction(Request $request)
    {
        return $this->display($request, 'Html');
    }

    public function displayXmlAction(Request $request)
    {
        return $this->display($request, 'Xml');
    }

    public function displayChartAction(Request $request)
    {
        return $this->display($request, 'Chart');
    }

    public function displayXlsxAction(Request $request)
    {
        return $this->display($request, 'Xlsx');
    }

    public function displayXlsAction(Request $request)
    {
        return $this->display($request, 'Xls');
    }
    public function displayCsvAction(Request $request)
    {
        return $this->display($request, "Csv");
    }

    public function displayJsonAction(Request $request)
    {
        return $this->display($request, "Json");
    }
    public function displaySqlAction(Request $request)
    {
        return $this->display($request, "Sql");
    }
    public function displayTableAction(Request $request)
    {
        return $this->display($request, 'Table');
    }

    public function displayTextAction(Request $request)
    {
        return $this->display($request, 'Text');
    }

    public function displayDebugAction(Request $request)
    {
        return $this->display($request, 'Debug');
    }

    private function display(Request $request, $type)
    {
        $className = "Eidsonator\\BootstrapReportsBundle\\Classes\\ReportFormats\\{$type}ReportFormat";
        $error_header = 'An error occurred while running your report';
        $content = '';

        try {
            if(!class_exists($className)) {
                $error_header = 'Unknown report format';
                throw new \Exception("Unknown report format '$type'");
            }

            try {
                $report = new Report($request->query->get('report'), [], null, null, $this->container, $this );
                $report = $className::prepareReport($report);
            } catch (\Exception $e) {
                $error_header = 'An error occurred while preparing your report';
                throw $e;
            }

            $twigArray =  $className::display($report, $request);
            if (is_array($twigArray)) {
                $reportURL =  $this->generateUrl('eidsonator_generate_report');
                $report->setBaseURL($reportURL);
                $twigArray['vars'] = $report->getReportVariables($twigArray['vars']);
                $content = $report->options['Query_Formatted'];
            }


        }
        catch(\Exception $e) {
            if (isset($report)) {
                $title = $report->report;
            } else {
                $title = 'broken';
            }
            return  $this->render('@BootstrapReports/Default/html/page.twig',array(
                'title'=> $title,
                'header'=>'<h2>'.$error_header.'</h2>',
                'error'=>$e->getMessage(),
                'content'=>$content,
                'breadcrumb'=>array('Report List' => '', $title => true)
            ));
        }
        if (isset($twigArray['template'])) {
            //return $this->render(')
            return $this->render($twigArray['template'], $twigArray['vars']);
        } else {

            $twigArray = nl2br($twigArray);
            if ($type === 'Debug') {
                return new Response("{$twigArray}");
            } else {
                $twigArray = preg_replace('/[ ](?=[^>]*(?:<|$))/', '&nbsp', $twigArray);
                return new Response("<style>body{font-family: monospace;}</style><br/>{$twigArray}");
            }

        }
    }

    public function editAction(Request $request)
    {
        $templateVars = array();
        $report = $request->query->get('report');
        $ext = pathinfo($report, PATHINFO_EXTENSION);
        try {
            $report = new Report($report, [], null, false, $this->container, $this);
            $templateVars = [
                'report' => $report->report,
                'options' => $report->options,
                'contents' => $report->getRaw(),
                'extension' => $ext
            ];
            $templateVars = $report->getReportVariables($templateVars);
        }
            //if there is an error parsing the report
        catch(\Exception $e) {
            $templateVars = [
                'report' => $report,
                'contents' => Report::getReportFileContents($report),
                'options' => [],
                'extension' => $ext,
                'error' => $e
            ];
        }

        if(isset($_POST['preview'])) {
            $diff = new SimpleDiff();
            $html = "<pre>" . $diff->htmlDiffSummary($templateVars['contents'], $_POST['contents']) . "</pre>";
            $twig = clone $this->get('twig');
            $twig->setLoader(new \Twig_Loader_String());
            return new Response($html);
        }
        elseif(isset($_POST['save'])) {
            $html = $report->setReportFileContents($_POST['contents']);
            return new Response($html);
        }
        else {
            return $this->render('@BootstrapReports/Default/html/report_editor.twig', $templateVars);
        }

    }

    protected function getReports($dir, &$errors = null) {
        $base = $this->reportDirectory;
        $reports = glob($dir . '*',GLOB_NOSORT);
        $return = array();
        foreach($reports as $key=>$report) {
            $title = $description = false;

            if(is_dir($report)) {
                if(file_exists($report.'/TITLE.txt')) $title = file_get_contents($report.'/TITLE.txt');
                if(file_exists($report.'/README.txt')) $description = file_get_contents($report.'/README.txt');

                $id = str_replace(array('_','-','/',' '),array('','','_','-'),trim(substr($report,strlen($base)),'/'));

                $children = $this->getReports($report.'/', $errors);

                $count = 0;
                foreach($children as $child) {
                    if(isset($child['count'])) $count += $child['count'];
                    else $count++;
                }

                $return[] = array(
                    'Name'=>ucwords(str_replace(array('_','-'), ' ', basename($report))),
                    'Title'=>$title,
                    'Id'=> $id,
                    'Description'=>$description,
                    'is_dir'=>true,
                    'children'=>$children,
                    'count'=>$count
                );
            }
            else {
                //files to skip
                if(strpos(basename($report), '.') === false) continue;
                $ext = pathinfo($report, PATHINFO_EXTENSION);
                if(!isset($this->defaultFileExtensionMapping[$ext])) continue;

                $name = substr($report,strlen($base));

                try {
                    $data = $this->getReportHeaders($name);
                    $grantedAccess = true;
                    if (isset($data['permission'])) {
                        $grantedAccess = $this->get('security.context')->isGranted($data['permission']);
                    }
                    if ($grantedAccess) {
                        $return[] = $data;
                    }
                }
                catch(\Exception $e) {
                    if(!$errors) $errors = array();
                    $errors[] = array(
                        'report'=>$name,
                        'exception'=>$e
                    );
                }
            }
        }

        usort($return,function(&$a,&$b) {
            if ($a['is_dir'] && !$b['is_dir']) {
                return 1;
            } elseif ($b['is_dir'] && !$a['is_dir']) {
                return -1;
            }

            if(!isset($a['Title']) && !isset($b['Title'])) {
                return strcmp($a['Name'],$b['Name']);
            } elseif (!isset($a['Title'])) {
                return 1;
            } elseif (!isset($b['Title'])) {
                return -1;
            }

            return strcmp($a['Title'], $b['Title']);
        });

        return $return;
    }

    protected function getReportHeaders($report) {
        $cacheKey = FileSystemCache::generateCacheKey($report,'report_headers');

        //check if report data is cached and newer than when the report file was created
        //the url parameter ?nocache will bypass this and not use cache
        $data =false;
        if(!isset($_REQUEST['nocache'])) {
            $data = FileSystemCache::retrieve($cacheKey, $this->reportDirectory . $report);
        }

        //report data not cached, need to parse it
        if($data === false) {
            $temp = new Report($report, array(), null, null, $this->container, $this);

            $data = $temp->options;

            $data['report'] = $report;
            $data['url'] = $this->generateUrl('eidsonator_generate_report', ["report" => $report]); //'report='.$report;
            $data['get'] = $report; //todo generate url
            $data['is_dir'] = false;
            $data['Id'] = str_replace(array('_', '-', '/', ' ', '.'), array('', '', '_', '-', '_'), trim($report, '/'));
            if(!isset($data['Name'])) $data['Name'] = ucwords(str_replace(array('_', '-'), ' ', basename($report)));

            //store parsed report in cache
            FileSystemCache::store($cacheKey, $data);
        }

        return $data;
    }

    public function getRecentReports() {
        $recently_run = FileSystemCache::retrieve(FileSystemCache::generateCacheKey('recently_run'));
        $recent = array();
        if($recently_run !== false) {
            $i = 0;
            foreach($recently_run as $report) {
                if($i > 10) break;
                $headers = $this->getReportHeaders($report);
                if(!$headers) {
                    continue;
                }
                if(isset($headers['url'])) {
                    if(isset($recent[$headers['url']])) {
                        continue;
                    }
                }
                $recent[$headers['url']] = $headers;
                $i++;
            }
        }
        return array_values($recent);
    }
}
