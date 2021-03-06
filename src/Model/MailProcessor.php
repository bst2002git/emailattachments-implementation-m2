<?php
declare(strict_types=1);

/**
 * @author     Kristof Ringleff
 * @package    Fooman_EmailAttachments
 * @copyright  Copyright (c) 2015 Fooman Limited (http://www.fooman.co.nz)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fooman\EmailAttachments\Model;

use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Mime;
use Zend\Mime\Part as MimePart;

class MailProcessor implements Api\MailProcessorInterface
{
    public function createMultipartMessage(
        \Magento\Framework\Mail\MailMessageInterface $message,
        Api\AttachmentContainerInterface $attachmentContainer
    ) {
        if ($attachmentContainer->hasAttachments()) {
            $body = new MimeMessage();
            $existingEmailBody = $message->getBody();

            //For html emails Magento already creates a MimePart
            //@see \Magento\Framework\Mail\Message::createHtmlMimeFromString()
            if (\is_object($existingEmailBody) && $existingEmailBody instanceof \Zend\Mime\Message) {
                $htmlPart = $existingEmailBody->getParts()[0];
                $htmlPart->type = Mime::TYPE_HTML;
                $body->addPart($htmlPart);
            } else {
                $textPart = new MimePart($existingEmailBody);
                $textPart->type = Mime::TYPE_TEXT;
                $textPart->charset = 'utf-8';
                $textPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
                $body->addPart($textPart);
            }

            foreach ($attachmentContainer->getAttachments() as $attachment) {
                $mimeAttachment = new MimePart($attachment->getContent());
                $mimeAttachment->filename = $this->getEncodedFileName($attachment);
                $mimeAttachment->type = $attachment->getMimeType();
                $mimeAttachment->encoding = $attachment->getEncoding();
                $mimeAttachment->disposition = $attachment->getDisposition();

                $body->addPart($mimeAttachment);
            }

            $message->setBodyText($body);
        }
    }

    public function getEncodedFileName($attachment)
    {
        return sprintf('=?utf-8?B?%s?=', base64_encode($attachment->getFilename()));
    }
}
