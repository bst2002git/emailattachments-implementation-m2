<?php

/**
 * @author     Kristof Ringleff
 * @package    Fooman_EmailAttachments
 * @copyright  Copyright (c) 2015 Fooman Limited (http://www.fooman.co.nz)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Fooman\EmailAttachments\Observer;

use Fooman\EmailAttachments\TransportBuilder;

class Common extends \PHPUnit\Framework\TestCase
{
    protected $mailhogClient;
    protected $objectManager;
    protected $moduleManager;

    const BASE_URL = 'http://127.0.0.1:8025/api/';

    protected function setUp()
    {
        parent::setUp();
        $this->mailhogClient = new \Zend_Http_Client();
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->objectManager->configure(
            ['preferences' =>
                [\Magento\Framework\Mail\TransportInterface::class => \Magento\Framework\Mail\Transport::class],
                [\Magento\Framework\Mail\Template\TransportBuilder::class => TransportBuilder::class]
            ]
        );

        $this->moduleManager = $this->objectManager->create(\Magento\Framework\Module\Manager::class);
    }

    public function getLastEmail($number = 1)
    {
        $this->mailhogClient->setUri(self::BASE_URL . 'v2/messages?limit=' . $number);
        $lastEmail = json_decode($this->mailhogClient->request()->getBody(), true);
        $lastEmailId = $lastEmail['items'][$number - 1]['ID'];
        $this->mailhogClient->resetParameters(true);
        $this->mailhogClient->setUri(self::BASE_URL . 'v1/messages/' . $lastEmailId);
        return json_decode($this->mailhogClient->request()->getBody(), true);
    }

    public function getAttachmentOfType($email, $type)
    {
        if (isset($email['MIME']['Parts'])) {
            foreach ($email['MIME']['Parts'] as $part) {
                if (!isset($type, $part['Headers']['Content-Type'])) {
                    continue;
                }
                if ($part['Headers']['Content-Type'][0] == $type) {
                    return $part;
                }
            }
        }

        return false;
    }

    public function getAllAttachmentsOfType($email, $type)
    {
        $parts = [];
        if (isset($email['MIME']['Parts'])) {
            foreach ($email['MIME']['Parts'] as $part) {
                if (!isset($type, $part['Headers']['Content-Type'])) {
                    continue;
                }
                if ($part['Headers']['Content-Type'][0] == $type) {
                    $parts[] = $part;
                }
            }
        }

        return $parts;
    }

    /**
     * @param $pdf
     * @param $number
     */
    protected function compareWithReceivedPdf($pdf, $number = 1)
    {
        $pdfAttachment = $this->getAttachmentOfType($this->getLastEmail($number), 'application/pdf');
        $this->assertEquals(strlen($pdf->render()), strlen(base64_decode($pdfAttachment['Body'])));
    }

    /**
     * @param      $pdf
     * @param bool $title
     * @param $number
     */
    protected function comparePdfAsStringWithReceivedPdf($pdf, $title = false, $number = 1)
    {
        $pdfAttachment = $this->getAttachmentOfType($this->getLastEmail($number), 'application/pdf');
        $this->assertEquals(strlen($pdf), strlen(base64_decode($pdfAttachment['Body'])));
        if ($title !== false) {
            $this->assertEquals($title, $this->extractFilename($pdfAttachment));
        }
    }

    protected function checkReceivedHtmlTermsAttachment($number = 1, $attachmentIndex = 0)
    {
        if ($this->moduleManager->isEnabled('Fooman_PdfCustomiser')) {
            $pdfs = $this->getAllAttachmentsOfType($this->getLastEmail($number), 'application/pdf');
            $this->assertEquals(
                strlen($this->getExpectedPdfAgreementsString()),
                strlen(base64_decode($pdfs[$attachmentIndex]['Body']))
            );
        } else {
            $termsAttachment = $this->getAttachmentOfType(
                $this->getLastEmail($number),
                'text/html; charset=UTF-8'
            );
            $this->assertContains(
                'Checkout agreement content: <b>HTML</b>',
                base64_decode($termsAttachment['Body'])
            );
        }
    }

    protected function checkReceivedTxtTermsAttachment($number = 1, $attachmentIndex = 0)
    {
        if ($this->moduleManager->isEnabled('Fooman_PdfCustomiser')) {
            $pdfs = $this->getAllAttachmentsOfType($this->getLastEmail($number), 'application/pdf');
            $this->assertEquals(
                strlen($this->getExpectedPdfAgreementsString()),
                strlen(base64_decode($pdfs[$attachmentIndex]['Body']))
            );
        } else {
            $termsAttachment = $this->getAttachmentOfType($this->getLastEmail($number), 'text/plain');
            $this->assertContains(
                'Checkout agreement content: TEXT',
                base64_decode($termsAttachment['Body'])
            );
        }
    }

    protected function extractFilename($input)
    {
        $input = substr($input['Headers']['Content-Disposition'][0], strlen('attachment; filename="=?utf-8?B?'), -2);
        return base64_decode($input);
    }

    protected function getExpectedPdfAgreementsString()
    {
        $termsCollection = $this->objectManager->create(
            \Magento\CheckoutAgreements\Model\ResourceModel\Agreement\Collection::class
        );
        $termsCollection->addStoreFilter(1)->addFieldToFilter('is_active', 1);
        $agreements = [];
        foreach ($termsCollection as $agreement) {
            $agreements[] = $agreement->setStoreId(1);
        }

        return $this->objectManager
            ->create(\Fooman\PdfCustomiser\Model\PdfRenderer\TermsAndConditionsAdapter::class)
            ->getPdfAsString($agreements);
    }
}
