<?php
/**
 * Minimal, dependency-free SMTP mailer (no Composer/PHPMailer needed).
 *
 * Supports implicit TLS (port 465, "ssl") and STARTTLS (port 587, "tls") with
 * AUTH LOGIN — which covers Gmail, Outlook/Office365, Zoho, Mailgun, SendGrid,
 * Brevo, Postmark, and most SMTP providers. Sends a single HTML email.
 *
 * Usage:
 *   $m = new Mailer($host,$port,$user,$pass,$secure,$fromEmail,$fromName);
 *   [$ok,$err] = $m->send($toEmail, $toName, $subject, $htmlBody);
 */
class Mailer
{
    private $host, $port, $user, $pass, $secure, $fromEmail, $fromName;

    public function __construct($host, $port, $user, $pass, $secure, $fromEmail, $fromName)
    {
        $this->host = $host;
        $this->port = (int) $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->secure = strtolower($secure); // 'ssl' | 'tls' | ''
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    /**
     * @param string $textBody Optional plain-text alternative. Providing one
     *        sends a proper multipart/alternative message, which materially
     *        improves deliverability (pure-HTML mail scores as spam more often).
     * @return array{0:bool,1:string} [success, errorMessage]
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): array
    {
        $transport = $this->secure === 'ssl' ? 'ssl://' : '';
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client(
            $transport . $this->host . ':' . $this->port,
            $errno, $errstr, 20,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
        );
        if (!$fp) return [false, "Connect failed: $errstr ($errno)"];
        stream_set_timeout($fp, 20);

        try {
            $this->expect($fp, 220);
            $host = $this->clientHostname();
            $this->cmd($fp, "EHLO $host", 250);

            if ($this->secure === 'tls') {
                $this->cmd($fp, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT
                        | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                    throw new RuntimeException('STARTTLS negotiation failed');
                }
                $this->cmd($fp, "EHLO $host", 250);
            }

            // AUTH LOGIN (base64 user/pass)
            $this->cmd($fp, 'AUTH LOGIN', 334);
            $this->cmd($fp, base64_encode($this->user), 334);
            $this->cmd($fp, base64_encode($this->pass), 235);

            $this->cmd($fp, 'MAIL FROM:<' . $this->fromEmail . '>', 250);
            $this->cmd($fp, 'RCPT TO:<' . $toEmail . '>', 250);
            $this->cmd($fp, 'DATA', 354);

            $fromDomain = substr(strrchr($this->fromEmail, '@') ?: '@localhost', 1);
            $messageId  = '<' . bin2hex(random_bytes(12)) . '@' . $fromDomain . '>';

            // Deliverability headers: a real Message-ID, Reply-To, Date, and a
            // List-Unsubscribe all raise sender reputation with Gmail/Outlook.
            $headers  = 'From: ' . $this->encodeName($this->fromName) . ' <' . $this->fromEmail . ">\r\n";
            $headers .= 'To: ' . $this->encodeName($toName) . ' <' . $toEmail . ">\r\n";
            $headers .= 'Reply-To: ' . $this->encodeName($this->fromName) . ' <' . $this->fromEmail . ">\r\n";
            $headers .= 'Subject: ' . $this->encodeHeader($subject) . "\r\n";
            $headers .= 'Date: ' . date('r') . "\r\n";
            $headers .= 'Message-ID: ' . $messageId . "\r\n";
            $headers .= 'List-Unsubscribe: <mailto:' . $this->fromEmail . '?subject=unsubscribe>' . "\r\n";
            $headers .= 'X-Auto-Response-Suppress: All' . "\r\n";
            $headers .= 'MIME-Version: 1.0' . "\r\n";

            if ($textBody !== '') {
                // multipart/alternative: text part first, HTML second.
                $boundary = 'fd_' . bin2hex(random_bytes(10));
                $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n";
                $mime  = '--' . $boundary . "\r\n";
                $mime .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
                $mime .= $textBody . "\r\n\r\n";
                $mime .= '--' . $boundary . "\r\n";
                $mime .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
                $mime .= $htmlBody . "\r\n\r\n";
                $mime .= '--' . $boundary . "--\r\n";
                $payload = $mime;
            } else {
                $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
                $payload = $htmlBody;
            }

            // Dot-stuffing: any line starting with "." must be doubled.
            $body = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], "\r\n", $payload));
            $this->write($fp, $headers . "\r\n" . $body . "\r\n.");
            $this->expect($fp, 250);

            $this->write($fp, 'QUIT');
            fclose($fp);
            return [true, ''];
        } catch (Throwable $e) {
            @fclose($fp);
            return [false, $e->getMessage()];
        }
    }

    private function clientHostname(): string
    {
        $h = $_SERVER['SERVER_NAME'] ?? gethostname() ?: 'localhost';
        return preg_replace('/[^A-Za-z0-9.\-]/', '', $h) ?: 'localhost';
    }
    private function encodeName(string $n): string { return '"' . str_replace('"', '', $n) . '"'; }
    private function encodeHeader(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }

    private function write($fp, string $line): void { fwrite($fp, $line . "\r\n"); }
    private function cmd($fp, string $line, int $expect): void { $this->write($fp, $line); $this->expect($fp, $expect); }

    private function expect($fp, int $code): void
    {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            // Multi-line replies keep a '-' after the code; final line has a space.
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $got = (int) substr($data, 0, 3);
        if ($got !== $code) {
            throw new RuntimeException("SMTP expected $code, got: " . trim($data));
        }
    }
}
