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

class AbstractSendInvoiceObserver extends AbstractObserver
{
    const XML_PATH_ATTACH_PDF = 'sales_email/invoice/attachpdf';
    const XML_PATH_ATTACH_AGREEMENT = 'sales_email/invoice/attachagreement';

    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        /**
         * @var $invoice \Magento\Sales\Api\Data\InvoiceInterface
         */
        $invoice = $observer->getInvoice();
        if ($this->scopeConfig->getValue(
            static::XML_PATH_ATTACH_PDF,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $invoice->getStoreId()
        )
        ) {
            $this->attachPdf(
                $this->pdfRenderer->getPdfAsString([$invoice]),
                $this->pdfRenderer->getFileName(__('Invoice ' . $invoice->getIncrementId())),
                $observer->getAttachmentContainer()
            );
        }
    }
}
