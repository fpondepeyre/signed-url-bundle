<?php

namespace Zenstruck\UrlSigner;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Zenstruck\UrlSigner\Exception\ExpiredUrl;
use Zenstruck\UrlSigner\Exception\SingleUseUrlAlreadyUsed;
use Zenstruck\UrlSigner\Exception\SingleUseUrlMismatch;
use Zenstruck\UrlSigner\Exception\UrlSignatureMismatch;

/**
 * @internal
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class Signer
{
    public const EXPIRES_AT_KEY = '_expires';
    public const SINGLE_USE_TOKEN_KEY = '_token';

    private UriSigner $uriSigner;
    private UrlGeneratorInterface $router;
    private string $secret;

    public function __construct(UrlGeneratorInterface $router, string $secret)
    {
        $this->uriSigner = new UriSigner($this->secret = $secret);
        $this->router = $router;
    }

    public function sign(string $route, array $parameters, int $referenceType): string
    {
        return $this->uriSigner->sign($this->router->generate($route, $parameters, $referenceType));
    }

    public function verify($url, $singleUseToken): void
    {
        $request = $url instanceof Request ? $url : Request::create($url);

        if (!$this->isSignatureValid($request)) {
            throw new UrlSignatureMismatch($url);
        }

        $expiresAt = $request->query->getInt(self::EXPIRES_AT_KEY);

        if ($expiresAt && \time() > $expiresAt) {
            throw new ExpiredUrl(self::parseDateTime($expiresAt), $url);
        }

        $singleUseHash = $request->query->get(self::SINGLE_USE_TOKEN_KEY);

        if (!$singleUseHash && !$singleUseToken) {
            return;
        }

        if ($singleUseHash && !$singleUseToken) {
            throw new SingleUseUrlMismatch($url, 'Given url is single use but this was not expected.');
        }

        if (!$singleUseHash && $singleUseToken) {
            throw new SingleUseUrlMismatch($url, 'Expected single user url.');
        }

        if (!\hash_equals($this->hash($singleUseToken), $singleUseHash)) {
            throw new SingleUseUrlAlreadyUsed($url);
        }
    }

    public function hash($token): string
    {
        return \base64_encode(\hash_hmac('sha256', self::normalizeToken($token), $this->secret, true));
    }

    public static function parseDateTime($timestamp): \DateTimeInterface
    {
        if ($timestamp instanceof \DateTimeInterface) {
            return $timestamp;
        }

        if (\is_int($timestamp)) {
            return \DateTime::createFromFormat('U', $timestamp);
        }

        return new \DateTime($timestamp);
    }

    public static function normalizeToken($token): string
    {
        return \is_callable($token) ? $token() : $token;
    }

    private function isSignatureValid(Request $request): bool
    {
        if (\method_exists($this->uriSigner, 'checkRequest')) {
            return $this->uriSigner->checkRequest($request);
        }

        // compatibility layer for symfony/http-kernel < 5.1.
        $qs = ($qs = $request->server->get('QUERY_STRING')) ? '?'.$qs : '';

        // we cannot use $request->getUri() here as we want to work with the original URI (no query string reordering)
        return $this->uriSigner->check($request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().$qs);
    }
}