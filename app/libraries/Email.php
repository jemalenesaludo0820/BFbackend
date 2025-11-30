<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (!class_exists('Email')) {

class Email {
    private $mailer;
    private $sender;
    public $sender_name = '';
    private $recipients = array();
    private $reply_to = '';
    private $subject;
    private $attach_files = array();
    private $emailContent = '';
    private $emailType = 'plain';
    private $last_error = '';
    
    // SMTP Configuration - Set these values here
    private $SMTP_HOST   = 'smtp.gmail.com';
    private $SMTP_PORT   = 587;
    private $SMTP_USER   = 'rochelleuchi38@gmail.com';       
    private $SMTP_PASS   = 'bikb mgtg ojet mwgm';
    private $SMTP_SECURE = 'tls';                        

    public function __construct()
    {
        // autoload PHPMailer (composer) or fallback to library copy
        if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
            require_once __DIR__ . '/../../vendor/autoload.php';
        } else {
            if (file_exists(__DIR__ . '/PHPMailer/src/Exception.php')) {
                require_once __DIR__ . '/PHPMailer/src/Exception.php';
                require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
                require_once __DIR__ . '/PHPMailer/src/SMTP.php';
            } else {
                throw new \Exception('PHPMailer not found. Install via Composer or place PHPMailer in app/libraries/PHPMailer');
            }
        }

        $this->mailer = new PHPMailer(true);

        // Try to get SMTP settings from globals first (for backward compatibility)
        global $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_SECURE;
        
        // Use global values if they exist, otherwise use class properties
        $smtp_host = !empty($SMTP_HOST) ? $SMTP_HOST : $this->SMTP_HOST;
        $smtp_port = !empty($SMTP_PORT) ? $SMTP_PORT : $this->SMTP_PORT;
        $smtp_user = !empty($SMTP_USER) ? $SMTP_USER : $this->SMTP_USER;
        $smtp_pass = !empty($SMTP_PASS) ? $SMTP_PASS : $this->SMTP_PASS;
        $smtp_secure = !empty($SMTP_SECURE) ? $SMTP_SECURE : $this->SMTP_SECURE;

        // Always configure SMTP if we have a host
        if (!empty($smtp_host)) {
            $this->mailer->isSMTP();
            $this->mailer->Host       = $smtp_host;
            $this->mailer->Port       = $smtp_port ?: 587;
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = $smtp_user;
            $this->mailer->Password   = $smtp_pass;
            if (!empty($smtp_secure)) {
                $this->mailer->SMTPSecure = $smtp_secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            }
            $this->mailer->SMTPAutoTLS = true;
            // Enable verbose debug output (can be disabled in production)
            // $this->mailer->SMTPDebug = 2; // Uncomment for debugging
        } else {
            // If no SMTP host is configured, this will fail
            // So we'll throw an error instead of using mail()
            throw new \Exception('SMTP configuration is missing. Please configure SMTP settings in Email.php');
        }

        $this->mailer->CharSet = (function_exists('config_item') ? config_item('charset') : null) ?: 'UTF-8';
    }

    private function valid_email($email)
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        throw new \Exception('Invalid email address');
    }

    private function filter_string($string)
    {
        return filter_var($string, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH);
    }

    public function sender($sender_email, $display_name = '')
    {
        if (!empty($sender_email) && $this->valid_email($sender_email)) {
            $this->sender = $sender_email;
            if (!is_null($display_name)) {
                $this->sender_name = $this->filter_string($display_name);
            }
            return $this->sender;
        }
    }

    public function recipient($recipient)
    {
        try {
            if (!empty($recipient) && $this->valid_email($recipient)) {
                if (!in_array($recipient, $this->recipients)) {
                    $this->recipients[] = $recipient;
                }
                return true;
            }
        } catch (\Exception $e) {
            $this->last_error = 'Invalid recipient email: ' . $e->getMessage();
            return false;
        }
        return false;
    }

    public function reply_to($reply_to)
    {
        if ($this->valid_email($reply_to)) {
            $this->reply_to = $reply_to;
            return $this->reply_to;
        }
    }

    public function subject($subject)
    {
        if (!empty($subject)) {
            $this->subject = $this->filter_string($subject);
            return $this->subject;
        }
        throw new \Exception("Email subject is empty");
    }

    public function email_content($emailContent, $type = 'plain')
    {
        // Only apply wordwrap to plain text, not HTML
        if ($type !== 'html') {
            $emailContent = wordwrap($emailContent, 70, "\n");
        }
        $this->emailContent = $emailContent;
        $this->emailType = $type;
    }

    public function attachment($attach_file)
    {
        if (!empty($attach_file)) {
            if (!in_array($attach_file, $this->attach_files)) {
                $this->attach_files[] = $attach_file;
            }
        } else {
            throw new \Exception("No file attachment was specified");
        }
    }

    public function send()
    {
        // Reset error
        $this->last_error = '';
        
        if (!is_array($this->recipients) || count($this->recipients) < 1) {
            $this->last_error = 'No recipient email address specified';
            return false;
        }

        // Validate subject
        if (empty($this->subject)) {
            $this->last_error = 'Email subject is empty';
            return false;
        }

        try {
            if (!empty($this->sender)) {
                $this->mailer->setFrom($this->sender, $this->sender_name ?: null);
            } else {
                // Use class property for SMTP user
                if (!empty($this->SMTP_USER)) {
                    $this->mailer->setFrom($this->SMTP_USER);
                } else {
                    $this->last_error = 'No sender email address configured';
                    return false;
                }
            }

            if (!empty($this->reply_to)) {
                $this->mailer->addReplyTo($this->reply_to);
            }

            foreach ($this->recipients as $r) {
                $this->mailer->addAddress($r);
            }

            $this->mailer->Subject = $this->subject;
            if ($this->emailType === 'html') {
                $this->mailer->isHTML(true);
                $this->mailer->Body = $this->emailContent;
                $this->mailer->AltBody = strip_tags($this->emailContent);
            } else {
                $this->mailer->isHTML(false);
                $this->mailer->Body = $this->emailContent;
            }

            foreach ($this->attach_files as $file) {
                if (file_exists($file)) {
                    $this->mailer->addAttachment($file);
                }
            }

            $result = $this->mailer->send();
            if (!$result) {
                $this->last_error = $this->mailer->ErrorInfo ?: 'Unknown error occurred while sending email';
            }
            return $result;
        } catch (PHPMailerException $e) {
            $this->last_error = $e->getMessage() . ' (PHPMailer Error)';
            return false;
        } catch (\Exception $e) {
            $this->last_error = $e->getMessage() . ' (General Error)';
            return false;
        }
    }

    /**
     * Get the last error message
     * @return string
     */
    public function get_error()
    {
        return $this->last_error;
    }
} // class Email

} // if !class_exists

?>