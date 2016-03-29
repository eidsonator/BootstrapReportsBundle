<?php

namespace Eidsonator\BootstrapReportsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class EmailController extends Controller
{
    private $emailFrom;
    private $emailTo;
    private $link;
    private $response;
    private $mailEnabled;
    private $subject;
    private $body;
    private $mailer;
    
    public function emailAction(Request $request)
    {
        $mailSettings = $this->container->getParameter('mail_settings');
        $this->response = new JsonResponse();
        $this->emailTo = $request->query->get('email');
        $this->link = $request->query->get('url');
        $subject = $request->query->get('subject');
        $this->subject = $subject ? $subject : "Database Report";
        $body = $request->query->get('message');
        $this->body = $body ? $body : "You've been sent a database report!";

        $this->mailEnabled = isset($mailSettings['enabled']) ? $mailSettings['enabled'] : false;
        $this->emailFrom = isset($mailSettings['from']) ? $mailSettings['from'] : false;

        $this->mailer = $this->get('mailer');
        return $this->sendEmail();
    }

    public function sendEmail()
    {
        if (!$this->paramsIsValid()) {
            return $this->response;
        }
        $csv = $this->getCsv();
        $table = $this->getHtmlTable();
        $text = $this->getText();

        $email_text = $this->body . "\n\n" . $text . "\n\nView the report online at $this->link";

        $link = htmlentities($this->link);

        $email_html = "<p>$this->body</p>$table<p>View the report online at <a href=\"$link\">$this->link</a></p>";

        // Create the message
        $message = \Swift_Message::newInstance()
            ->setSubject($this->subject)
            ->setFrom($this->emailFrom)
            ->setTo($this->emailTo)
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

            $result = $this->mailer->send($message);
        } catch (\Exception $e) {
            $this->response->setData(['error' => $e->getMessage()]);
            return $this->response;
        }
        if ($result) {
            $this->response->setData(['success' => true]);
        } else {
            $this->response->setData(['error' => 'Failed to send email to requested recipient']);
        }
        return $this->response;
    }

    private function paramsIsValid()
    {
        if (!$this->emailTo || !filter_var($this->emailTo, FILTER_VALIDATE_EMAIL)) {
            $this->response->setData(['error' => 'Valid email address required']);
            return false;
        }
        if (!$this->link) {
            $this->response->setData(['error' => 'Report url required']);
            return false;
        }
        if (!$this->mailEnabled) {
            $this->response->setData(['error' => 'Email is disabled on this server']);
            return false;
        }
        if (!$this->emailFrom) {
            $this->response->setData(['error' => 'Email settings have not been properly configured on this server']);
            return false;
        }
        return true;
    }

    protected function urlDownload($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    /**
     * @return mixed
     */
    private function getCsv()
    {
        $csv_link = str_replace('report/html?', 'report/csv?', $this->link);
        $csv = $this->urlDownload($csv_link);
        return $csv;
    }

    /**
     * @return mixed
     */
    private function getHtmlTable()
    {
        $table_link = str_replace('report/html?', 'report/table?', $this->link);
        $table = $this->urlDownload($table_link);
        return $table;
    }

    /**
     * @return mixed
     */
    private function getText()
    {
        $text_link = str_replace('report/html?', 'report/text?', $this->link);
        $text = $this->urlDownload($text_link);
        return $text;
    }
}
