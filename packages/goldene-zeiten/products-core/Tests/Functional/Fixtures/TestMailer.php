<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Fixtures;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use TYPO3\CMS\Core\Mail\MailerInterface;

final class TestMailer implements MailerInterface
{
    /**
     * @var Email[]
     */
    private static array $sentEmails = [];

    private static bool $failing = false;

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if (self::$failing) {
            throw new TransportException('The test transport refuses every message.');
        }
        if ($message instanceof Email) {
            self::$sentEmails[] = $message;
        }
    }

    public function getSentMessage(): ?SentMessage
    {
        return null;
    }

    public function getTransport(): TransportInterface
    {
        return new NullTransport();
    }

    public function getRealTransport(): TransportInterface
    {
        return new NullTransport();
    }

    /**
     * @return Email[]
     */
    public static function getSentEmails(): array
    {
        return self::$sentEmails;
    }

    public static function reset(): void
    {
        self::$sentEmails = [];
        self::$failing = false;
    }

    /**
     * Makes every following send fail like an unreachable mail server would.
     */
    public static function failAllSends(): void
    {
        self::$failing = true;
    }
}
