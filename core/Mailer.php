<?php
class Mailer
{
    /**
     * Send one message. $headers is the \n-joined header block from the caller.
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array $headers
     * @return string
     */
    public static function send($to, $subject, $body, $headers)
    {

        $to = self::stripHeaderCrlf($to);
        $subject = self::stripHeaderCrlf($subject);

        $cfg = self::smtpConfig();
        if ($cfg === null) {
            return self::sendPhpMail($to, $subject, $body, $headers);
        }
        try {
            return self::sendSmtp($cfg, $to, $subject, $body, $headers);
        } catch (Throwable $e) {
            return self::sendPhpMail($to, $subject, $body, $headers);
        }
    }

    /**
     * Remove CR/LF/NUL so a value cannot inject extra mail headers.
     *
     * @param string $value
     */
    private static function stripHeaderCrlf($value)
    {
        return preg_replace('/[\r\n\x00]+/', ' ', (string) $value);
    }

    /**
     * PHP mail() with a proper envelope sender (-f). Without a valid return-path.
     *
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array $headers
     * @return string
     */
    private static function sendPhpMail($to, $subject, $body, $headers)
    {
        $from = self::fromAddress();

        if (preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+$/', $from)) {
            return mail($to, $subject, $body, $headers, '-f' . $from);
        }
        return mail($to, $subject, $body, $headers);
    }

    /**
     * The address mail is sent from: the configured SMTP "from" when it.
     *
     * @return string
     */
    public static function fromAddress()
    {
        $from = '';
        try { $from = trim((string) Setting::get('smtp_from', '')); } catch (Throwable $e) {}
        if ($from !== '' && strpos($from, '@') !== false) {
            return $from;
        }
        $domain = '';
        try { $domain = trim((string) Config::get('DOMAIN', '')); } catch (Throwable $e) {}
        if ($domain === '' || $domain === 'localhost') {
            $domain = defined('HOST') ? HOST : 'localhost';
        }
        $domain = preg_replace('/:\d+$/', '', $domain);
        return 'ezxss@' . $domain;
    }

    /**
     * The SMTP config, or null when SMTP is disabled or has no host.
     *
     * @return array|null
     */
    public static function smtpConfig()
    {
        if (!Setting::enabled('smtp_enabled')) {
            return null;
        }
        $host = trim((string) Setting::get('smtp_host', ''));
        if ($host === '') {
            return null;
        }
        $port = (int) Setting::get('smtp_port', '587');
        if ($port < 1 || $port > 65535) {
            $port = 587;
        }
        $security = (string) Setting::get('smtp_security', 'tls');
        if (!in_array($security, array('none', 'tls', 'ssl'), true)) {
            $security = 'tls';
        }
        return array(
            'host'     => $host,
            'port'     => $port,
            'security' => $security,
            'username' => (string) Setting::get('smtp_username', ''),
            'password' => (string) Setting::get('smtp_password', ''),
            'from'     => self::fromAddress(),
        );
    }

    /**
     * Build the RFC822 message (CRLF line endings, dot-stuffed) for the DATA.
     *
     * @param array $cfg
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array $headers
     */
    public static function buildMessage(array $cfg, $to, $subject, $body, $headers)
    {
        $lines = array(
            'From: ezXSS <' . $cfg['from'] . '>',
            'To: <' . $to . '>',
            'Subject: ' . $subject,
            'Date: ' . date('r'),
        );
        foreach (explode("\n", (string) $headers) as $h) {
            $h = trim($h);
            if ($h === '' || stripos($h, 'From:') === 0 || stripos($h, 'To:') === 0 ||
                stripos($h, 'Subject:') === 0 || stripos($h, 'Date:') === 0) {
                continue;
            }
            $lines[] = $h;
        }

        $message = implode("\r\n", $lines) . "\r\n\r\n" . (string) $body;
        $message = preg_replace('/\r\n|\r|\n/', "\r\n", $message);

        $message = preg_replace('/(^|\r\n)\./', '$1..', $message);
        return $message;
    }

    /**
     * Run the SMTP conversation.
     *
     * @param array $cfg
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array $headers
     * @throws Exception
     * @return bool
     */
    private static function sendSmtp(array $cfg, $to, $subject, $body, $headers)
    {
        $transport = ($cfg['security'] === 'ssl') ? 'ssl://' : 'tcp://';
        $fp = @stream_socket_client($transport . $cfg['host'] . ':' . $cfg['port'], $errno, $errstr, 15);
        if (!$fp) {
            throw new Exception('SMTP connect failed: ' . $errstr);
        }
        stream_set_timeout($fp, 15);

        try {
            self::read($fp, 220);
            self::cmd($fp, 'EHLO ezxss', 250);

            if ($cfg['security'] === 'tls') {
                self::cmd($fp, 'STARTTLS', 220);
                $ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$ok) {
                    throw new Exception('STARTTLS negotiation failed');
                }
                self::cmd($fp, 'EHLO ezxss', 250);
            }

            if ($cfg['username'] !== '') {
                self::cmd($fp, 'AUTH LOGIN', 334);
                self::cmd($fp, base64_encode($cfg['username']), 334);
                self::cmd($fp, base64_encode($cfg['password']), 235);
            }

            self::cmd($fp, 'MAIL FROM:<' . $cfg['from'] . '>', 250);
            self::cmd($fp, 'RCPT TO:<' . $to . '>', array(250, 251));
            self::cmd($fp, 'DATA', 354);

            fwrite($fp, self::buildMessage($cfg, $to, $subject, $body, $headers) . "\r\n.\r\n");
            self::read($fp, 250);

            self::cmd($fp, 'QUIT', 221);
        } finally {
            fclose($fp);
        }
        return true;
    }

    /**
     * Send a command and assert the reply code.
     *
     * @param resource $fp
     * @param string $command
     * @param int $expect
     */
    private static function cmd($fp, $command, $expect)
    {
        fwrite($fp, $command . "\r\n");
        self::read($fp, $expect);
    }

    /**
     * Read a (possibly multi-line) SMTP reply and assert its code.
     *
     * @param resource $fp
     * @param int $expect
     * @throws Exception
     */
    private static function read($fp, $expect)
    {
        $line = '';
        do {
            $line = fgets($fp, 515);
            if ($line === false) {
                throw new Exception('SMTP read failed (timeout or closed)');
            }

        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($line, 0, 3);
        $expect = is_array($expect) ? $expect : array($expect);
        if (!in_array($code, $expect, true)) {
            throw new Exception('SMTP unexpected reply: ' . trim($line));
        }
        return $code;
    }
}
