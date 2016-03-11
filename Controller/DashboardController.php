<?php

namespace Eidsonator\BootstrapReportsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DashboardController extends Controller
{
    private $dashboardDirectory = null;
    private $defaultFileExtensionMapping = null;

    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);
        $this->dashboardDirectory = $this->container->getParameter('dashboardDirectory');
        $this->defaultFileExtensionMapping = $this->container->getParameter('default_file_extension_mapping');
        if (!defined('DASHBOARD_DIRECTORY')) {
            define('DASHBOARD_DIRECTORY', $this->dashboardDirectory);
        }
    }

    public function listDashboardsAction()
    {
        return $this->render('@BootstrapReports/Default/html/dashboard_list.twig', ['dashboards' => $this->getDashboards()]);
    }

    protected function getDashboards()
    {
        $dashboards = glob($this->dashboardDirectory . '/*.json');

        $ret = [];
        foreach($dashboards as $key=>$value) {
            $name = basename($value,'.json');
            $ret[$name] = $this->getDashboard($name);
        }

        uasort($ret, function($a,$b) {
            return strcmp($a['title'],$b['title']);
        });

        return $ret;
    }

    protected function getDashboard($dashboard) {
        $file = $this->dashboardDirectory . '/'.$dashboard.'.json';
        if(!file_exists($file)) {
            throw new \Exception("Unknown dashboard - ".$dashboard);
        }

        return json_decode(file_get_contents($file),true);
    }

    public function showDashboardAction($dashboard)
    {
        return $this->render('@BootstrapReports/Default/html/dashboard.twig', ['dashboard' => $this->getDashboard($dashboard)]);
    }
}
