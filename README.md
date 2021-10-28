# zenstruck/signed-url-bundle

```php
public function sendPasswordResetEmail(User $user, Generator $generator)
{
    $resetUrl = $generator->factory('password_reset_route')
        ->expiresAt('+1 day')
        ->singleUse($user->getPassword())
    ;

    // create email with $resetUrl and send
}
```

```php
public function resetPasswordAction(User $user, Verifier $urlVerifier)
{
    try {
        $urlVerifier->verifyCurrentRequest(singleUseToken: $user->getPassword());
    } catch (ExpiredUrl) {
        // flash "This url has expired, please try again" and redirect
    } catch (UrlAlreadyUsed) {
        // flash "This url has already been used, please try again" and redirect
    } catch (InvalidUrlSignature) {
        // flash "This url is invalid, please try again" and redirect
    }

    // continue
}
```
