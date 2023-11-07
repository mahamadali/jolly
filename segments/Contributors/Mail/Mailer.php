<?php

namespace Contributors\Mail;

use Bones\Skeletons\Supporters\AutoMethodMap;
use Bones\MailerException;
use Contributors\Mail\PHPMailer\PHPMailer;
use Contributors\Mail\PHPMailer\Exception;
use Jolly\Engine;

class Mailer extends AutoMethodMap
{
    protected $to;
    protected $bodyHtml;
    protected $bodyText;
    protected $subject;
    protected $cc;
    protected $bcc;
    protected $attachments;
    protected $headers;

    public function __send()
    {
        $this->validate();

        $via = setting('alert.mail.via', 'default');

        if ($via == 'smtp') {
            $this->viaPHPMailer();
        } else {
            $this->viaDefaultMailer();
        }
    }

    public function validate()
    {
        if (empty($this->to)) {
            throw new MailerException('Mailer: must have atleast one receipient to send an email to');
        }

        if (empty($this->subject)) {
            throw new MailerException('Mailer: email must have a subject');
        }

        if (empty($this->bodyHtml) && empty($this->bodyText)) {
            throw new MailerException('Mailer: empty body can not be sent as an email');
        }

    }

    public function __to(...$emails)
    {
        $this->to = resolveAsArray($emails);
        return $this;
    }

    public function __subject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    public function __bodyHtml($bodyHtml)
    {
        $this->bodyHtml = $bodyHtml;
        return $this;
    }

    public function __bodyText($bodyText)
    {
        $this->bodyText = $bodyText;
        return $this;
    }

    public function __html($bodyHtml)
    {
        return $this->__bodyHtml($bodyHtml);
    }

    public function __text($bodyText)
    {
        return $this->__bodyText($bodyText);
    }

    public function __cc(...$emails)
    {
        $this->cc = resolveAsArray($emails);
        return $this;
    }

    public function __bcc(...$emails)
    {
        $this->bcc = resolveAsArray($emails);
        return $this;
    }

    public function __attach($attachmentPath, $attachmentAs = '')
    {
        if (empty($attachmentAs)) {
            $attachmentAs = basename($attachmentPath);
        }

        $this->attachments[$attachmentAs] = $attachmentPath;
        return $this;
    }

    public function __headers($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function getHeaderString()
    {
        $headerString = '';
        $headerCount = 1;
        foreach ($this->headers as $headerKey => $headerValue) {
            $headerString .= $headerKey . ':' . $headerValue;
            if (count($this->headers) != $headerCount) {
                $headerString .= PHP_EOL;
            }
            $headerCount++;
        }

        return $headerString;
    }

    public function viaDefaultMailer()
    {
        $this->headers('From', setting('alert.mail.from.name', 'Administration') . ' <' . setting('alert.mail.from.email', 'admin@administration.com') . '>');
        $this->headers('Reply-To', setting('alert.mail.reply.name', 'Administration') . ' <' . setting('alert.mail.reply.email', 'admin@administration.com') . '>');

        if (!empty($this->attachments)) {
            $this->headers('MIME-Version', "1.0" . PHP_EOL . "Content-Type: multipart/mixed; boundary=\"1a2a3a\"");
            foreach ($this->attachments as $attachmentAs => $attachment) {
                $this->bodyHtml .= "Content-Type: ".mime_content_type($attachment)."; name=\"".$attachmentAs."\"\r\n"
                ."Content-Transfer-Encoding: base64\r\n"
                ."Content-disposition: attachment; file=\"".$attachment."\"\r\n"
                ."\r\n"
                .chunk_split(base64_encode($attachment))
                ."--1a2a3a--";
                $this->bodyText = "Content-Type: ".mime_content_type($attachment)."; name=\"".$attachmentAs."\"\r\n"
                ."Content-Transfer-Encoding: base64\r\n"
                ."Content-disposition: attachment; file=\"".$attachment."\"\r\n"
                ."\r\n"
                .chunk_split(base64_encode($attachment))
                ."--1a2a3a--";
            }
        } else {
            $this->headers('Content-type', "text/html;charset=iso-8859-1");
        }

        if (!empty($this->cc)) {
            $this->headers('Cc:', implode(',', $this->cc));
        }

        if (!empty($this->bcc)) {
            $this->headers('Bcc:', implode(',', $this->bcc));
        }

        $this->headers('X-Mailer', "PHP/" . phpversion());

        mail(implode(',', $this->to), $this->subject, (!empty($this->bodyHtml) ? $this->bodyHtml : $this->bodyText), $this->getHeaderString());

        return true;
    }

    public function viaPHPMailer()
    {
        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->SMTPDebug = setting('alert.mail.smtp.debug', false);
            $mail->isSMTP();
            $mail->Host       = setting('alert.mail.smtp.host');
            $mail->SMTPAuth   = true;
            $mail->Username   = setting('alert.mail.smtp.username');
            $mail->Password   = setting('alert.mail.smtp.password');
            $mail->SMTPSecure = setting('alert.mail.smtp.encryption');
            $mail->Port       = setting('alert.mail.smtp.port');
        
            // Set from
            $mail->setFrom(setting('alert.mail.from.email', 'admin@administration.com'), setting('alert.mail.from.name', 'Administration'));

            //Recipients
            foreach ($this->to as $recipient) {
                $mail->addAddress($recipient);
            }

            // Set from to
            $mail->addReplyTo(setting('alert.mail.reply.email', 'reply@administration.com'), setting('alert.mail.reply.name', 'Administration'));

            // Set cc
            if (!empty($this->cc)) {
                foreach ($this->cc as $cc) {
                    $mail->addCC($cc);
                }
            }

            // Set bcc
            if (!empty($this->bcc)) {
                foreach ($this->bcc as $bcc) {
                    $mail->addCC($bcc);
                }
            }
        
            //Attachments
            if (!empty($this->attachments)) {
                foreach ($this->attachments as $attachmentAs => $attachment) {
                    $mail->addAttachment($attachment, $attachmentAs);
                }
            }
        
            //Content
            $mail->isHTML(true);
            $mail->Subject = $this->subject;

            if (!empty($this->bodyHtml)) {
                $mail->Body = $this->bodyHtml;
            }

            if (!empty($this->bodyText)) {
                $mail->AltBody = $this->bodyText;
            }
        
            // Send email
            $mail->send();

            return true;
        } catch (Exception $e) {
            Engine::error([
                'error' => $mail->ErrorInfo
            ], true);
        }
    }

}