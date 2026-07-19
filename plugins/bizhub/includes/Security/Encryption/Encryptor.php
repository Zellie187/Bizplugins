<?php

declare(strict_types=1);

namespace BizHub\Security\Encryption;

use RuntimeException;

/**
 * Symmetric encryption for data at rest (e.g. stored secrets, tokens).
 *
 * Uses AES-256-GCM. The encryption key is provided at construction time
 * and should be derived from a WordPress salt (e.g. wp_salt('auth')) so
 * that it is unique per installation.
 *
 * @package BizHub\Security\Encryption
 */
final class Encryptor
{
    private const CIPHER = 'aes-256-gcm';

    private string $key;

    public function __construct(string $key)
    {
        $this->key = hash('sha256', $key, true);
    }

    /**
     * Encrypt a plaintext string.
     *
     * Returns a base64-encoded bundle of IV, authentication tag and
     * ciphertext.
     */
    public function encrypt(string $plaintext): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt value.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a value previously produced by encrypt().
     *
     * Returns null if the value cannot be decrypted (invalid or tampered).
     */
    public function decrypt(string $encoded): ?string
    {
        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            return null;
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $tagLength = 16;

        if (strlen($decoded) < $ivLength + $tagLength) {
            return null;
        }

        $iv = substr($decoded, 0, $ivLength);
        $tag = substr($decoded, $ivLength, $tagLength);
        $ciphertext = substr($decoded, $ivLength + $tagLength);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext === false ? null : $plaintext;
    }
}
