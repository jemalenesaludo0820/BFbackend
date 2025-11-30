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

    // SMTP Configuration - defaults (you can override globally or via properties)
    private $SMTP_HOST   = 'smtp.gmail.com';
    private $SMTP_PORT   = 587;
    private $SMTP_USER   = 'rochelleuchi38@gmail.com';
    private $SMTP_PASS   = 'bikb mgtg ojet mwgm';
    private $SMTP_SECURE = 'tls';

    // Resend configuration (provided)
    private $RESEND_API_KEY = 're_52XVCM6M_FAiNudTj3tFxWxRyHwnZdqnV';
    private $RESEND_FROM = 'LavaLust Cars <onboarding@resend.dev>';

    // control flags
    private $useResendOnly = false; // if true, skip PHPMailer and send directly via Resend
    private $allowResendFallback = true; // if PHPMailer fails, try Resend automatically

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
                // Still allow creation of the class so Resend-only mode can work
                // throw new \Exception('PHPMailer not found. Install via Composer or place PHPMailer in app/libraries/PHPMailer');
            }
        }

        // Only instantiate PHPMailer if class exists
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $this->mailer = new PHPMailer(true);
        } else {
            $this->mailer = null;
        }

        // Try to get SMTP settings from globals first (for backward compatibility)
        global $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_SECURE;

        $smtp_host = !empty($SMTP_HOST) ? $SMTP_HOST : $this->SMTP_HOST;
        $smtp_port = !empty($SMTP_PORT) ? $SMTP_PORT : $this->SMTP_PORT;
        $smtp_user = !empty($SMTP_USER) ? $SMTP_USER : $this->SMTP_USER;
        $smtp_pass = !empty($SMTP_PASS) ? $SMTP_PASS : $this->SMTP_PASS;
        $smtp_secure = !empty($SMTP_SECURE) ? $SMTP_SECURE : $this->SMTP_SECURE;

        // Configure PHPMailer SMTP if available and host provided
        if ($this->mailer && !empty($smtp_host)) {
            try {
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
                // $this->mailer->SMTPDebug = 0;
            } catch (\Exception $e) {
                // If PHPMailer initialization fails, we'll rely on Resend fallback later
                $this->mailer = null;
                $this->last_error = "PHPMailer init failed: " . $e->getMessage();
            }
        }

        $this->last_error = '';
    }

    /* ---------- Public configuration helpers ---------- */

    /**
     * Force sending only through Resend (skip PHPMailer)
     * @param bool $bool
     */
    public function useResendOnly($bool = true)
    {
        $this->useResendOnly = (bool)$bool;
    }

    /**
     * Enable/disable automatic fallback to Resend when PHPMailer fails.
     * @param bool $bool
     */
    public function allowResendFallback($bool = true)
    {
        $this->allowResendFallback = (bool)$bool;
    }

    /**
     * Override Resend API Key (optional)
     */
    public function setResendApiKey($key)
    {
        $this->RESEND_API_KEY = $key;
    }

    /* ---------- Original API preserved ---------- */

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

    /* ---------- Sending logic with automatic fallback A2 ---------- */

    public function send()
    {
        // Reset previous error
        $this->last_error = '';

        if (!is_array($this->recipients) || count($this->recipients) < 1) {
            $this->last_error = 'No recipient email address specified';
            return false;
        }

        if (empty($this->subject)) {
            $this->last_error = 'Email subject is empty';
            return false;
        }

        // If explicitly set to Resend-only, skip PHPMailer and go to Resend
        if ($this->useResendOnly || !$this->mailer) {
            $sent = $this->sendViaResend();
            return $sent;
        }

        // Otherwise, try PHPMailer first
        try {
            // Set sender
            if (!empty($this->sender)) {
                $this->mailer->setFrom($this->sender, $this->sender_name ?: null);
            } else {
                // Use SMTP user if no sender specified
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

            // Clear recipients/attachments from prior usage just in case
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

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

            if ($result) {
                return true;
            } else {
                // PHPMailer returned false without throwing
                $this->last_error = $this->mailer->ErrorInfo ?: 'Unknown PHPMailer error';
                // If allowed, attempt Resend fallback
                if ($this->allowResendFallback) {
                    error_log("PHPMailer failed: {$this->last_error}. Attempting Resend fallback...");
                    return $this->sendViaResend();
                }
                return false;
            }

        } catch (PHPMailerException $e) {
            $this->last_error = $e->getMessage() . ' (PHPMailer Exception)';
            if ($this->allowResendFallback) {
                error_log("PHPMailer exception: {$this->last_error}. Attempting Resend fallback...");
                return $this->sendViaResend();
            }
            return false;
        } catch (\Exception $e) {
            $this->last_error = $e->getMessage() . ' (General Exception)';
            if ($this->allowResendFallback) {
                error_log("PHPMailer exception: {$this->last_error}. Attempting Resend fallback...");
                return $this->sendViaResend();
            }
            return false;
        }
    }

    /* ---------- Private helper: send with Resend API (cURL) ---------- */

    private function sendViaResend()
    {
        // Validate resend configuration
        if (empty($this->RESEND_API_KEY)) {
            $this->last_error = 'Missing Resend API key';
            return false;
        }

        // Prepare from
        $sendFrom = !empty($this->sender) ? ($this->sender_name ? "{$this->sender_name} <{$this->sender}>" : $this->sender) : $this->RESEND_FROM;

        // Build payload
        $payload = [
            "from"    => $sendFrom,
            // Resend supports string or array for "to"
            "to"      => array_values($this->recipients),
            "subject" => $this->subject,
            "html"    => ($this->emailType === 'html') ? $this->emailContent : nl2br($this->emailContent)
        ];

        // NOTE: attachments are not sent via this simple Resend payload.
        if (!empty($this->attach_files)) {
            // Log that attachments can't be forwarded by this Resend implementation
            error_log("Attachments detected but will NOT be sent via Resend fallback. Files: " . implode(', ', $this->attach_files));
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.resend.com/emails",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$this->RESEND_API_KEY}",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($curlError) {
            $this->last_error = "cURL Error: $curlError";
            return false;
        }

        $decoded = json_decode($response, true);
        if ($httpStatus >= 400) {
            // Try to capture message from response
            $msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : ($decoded['message'] ?? $response);
            $this->last_error = "Resend API error (HTTP $httpStatus): $msg";
            return false;
        }

        // If response includes an 'id' or similar, consider success
        if (is_array($decoded) && (isset($decoded['id']) || isset($decoded['message']) || $httpStatus < 400)) {
            return true;
        }

        // Unknown response format, but treat as success if HTTP < 400
        if ($httpStatus < 400) return true;

        $this->last_error = "Unknown Resend response: " . $response;
        return false;
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
