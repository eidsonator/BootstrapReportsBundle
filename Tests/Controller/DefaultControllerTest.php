<?php
/**
 * Created by PhpStorm.
 * User: teidson
 * Date: 3/28/2016
 * Time: 11:16 AM
 */

namespace Eidsonator\BootstrapReportsBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    public function testReportsIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/reports');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testDashboardsIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/dashboards');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }
}