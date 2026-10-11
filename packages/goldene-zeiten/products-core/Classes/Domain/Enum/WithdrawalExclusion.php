<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Enum;

/**
 * Why a product is exempt from the EU right of withdrawal (Art. 16 Directive 2011/83/EU).
 *
 * PERSONALIZED covers goods made to the consumer's specifications or clearly personalized
 * (Art. 16(c)) - engraved jewelry, custom-fit pieces. The exemption applies by itself.
 *
 * DIGITAL_CONTENT covers digital content not supplied on a tangible medium (Art. 16(m)) -
 * downloads. The exemption only takes effect once the consumer has expressly consented to
 * immediate performance and acknowledged losing the withdrawal right; before that consent
 * the right stands.
 *
 * SEALED_HYGIENE (Art. 16(e)) and SEALED_MEDIA (Art. 16(i)) cover sealed goods that lose the right
 * only once the consumer unsealed them after delivery. The shop cannot know that, so these stay
 * withdrawable; confirmations and instructions state the condition instead.
 */
enum WithdrawalExclusion: string
{
    case NONE = '';
    case PERSONALIZED = 'personalized';
    case DIGITAL_CONTENT = 'digital_content';
    case SEALED_HYGIENE = 'sealed_hygiene';
    case SEALED_MEDIA = 'sealed_media';

    public function requiresWaiverConsent(): bool
    {
        return $this === self::DIGITAL_CONTENT;
    }

    public function excludesWithdrawal(bool $waiverConsentGiven): bool
    {
        return match ($this) {
            self::NONE, self::SEALED_HYGIENE, self::SEALED_MEDIA => false,
            self::PERSONALIZED => true,
            self::DIGITAL_CONTENT => $waiverConsentGiven,
        };
    }

    /**
     * Whether the right only holds while the consumer has not unsealed the goods after delivery.
     */
    public function isLostByUnsealing(): bool
    {
        return $this === self::SEALED_HYGIENE || $this === self::SEALED_MEDIA;
    }
}
