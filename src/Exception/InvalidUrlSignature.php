<?php

namespace Zenstruck\UrlSigner\Exception;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
abstract class InvalidUrlSignature extends \RuntimeException
{
    private string $url;

    public function __construct(string $url, $message = 'Invalid URL Signature.')
    {
        parent::__construct($message);

        $this->url = $url;
    }

    public function url(): string
    {
        return $this->url;
    }
}
