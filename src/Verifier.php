<?php

namespace Zenstruck\UrlSigner;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\UriSigner;
use Zenstruck\UrlSigner\Exception\ExpiredUrl;
use Zenstruck\UrlSigner\Exception\InvalidUrlSignature;
use Zenstruck\UrlSigner\Exception\UrlSignatureMismatch;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class Verifier
{
    private UriSigner $signer;
    private ?RequestStack $requests;

    public function __construct(UriSigner $signer, ?RequestStack $requests = null)
    {
        $this->signer = $signer;
        $this->requests = $requests;
    }

    /**
     * @param string|Request $url
     *
     * @throws UrlSignatureMismatch If the signed url cannot be verified
     * @throws ExpiredUrl           If the signed url is valid but expired
     */
    public function verify($url): void
    {
        $request = $url instanceof Request ? $url : Request::create($url);

        if (!$this->checkRequest($request)) {
            throw new UrlSignatureMismatch($url);
        }

        if (!$expiresAt = $request->query->getInt(Generator::EXPIRES_AT_KEY)) {
            return;
        }

        if (\time() > $expiresAt) {
            throw new ExpiredUrl(Generator::parseDateTime($expiresAt), $url);
        }
    }

    /**
     * Attempt to verify the current request.
     *
     * @throws \RuntimeException    If no current request available
     * @throws UrlSignatureMismatch If the current request cannot be verified
     * @throws ExpiredUrl           If the current request is valid but expired
     */
    public function verifyCurrentRequest(): void
    {
        if (!$this->requests || !$request = $this->requests->getCurrentRequest()) {
            throw new \RuntimeException('Current request not available.');
        }

        $this->verify($request);
    }

    /**
     * @param string|Request $url
     *
     * @return bool true if verified, false if not
     */
    public function isVerified($url): bool
    {
        try {
            $this->verify($url);
        } catch (InvalidUrlSignature $e) {
            return false;
        }

        return true;
    }

    /**
     * @return bool true if verified, false if not
     *
     * @throws \RuntimeException If no current request available
     */
    public function isCurrentRequestVerified(): bool
    {
        try {
            $this->verifyCurrentRequest();
        } catch (InvalidUrlSignature $e) {
            return false;
        }

        return true;
    }

    /**
     * Compatibility layer for symfony/http-kernel < 5.1.
     *
     * @see UriSigner::checkRequest() (in symfony/http-kernel >= 5.1)
     */
    private function checkRequest(Request $request): bool
    {
        if (\method_exists($this->signer, 'checkRequest')) {
            return $this->signer->checkRequest($request);
        }

        $qs = ($qs = $request->server->get('QUERY_STRING')) ? '?'.$qs : '';

        // we cannot use $request->getUri() here as we want to work with the original URI (no query string reordering)
        return $this->signer->check($request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().$qs);
    }
}