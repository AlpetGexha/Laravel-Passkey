<?php

namespace App\Actions;

use App\Models\Passkey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;

class PasskeyAuthenticateAction
{
    public function handle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'answer' => ['required', 'json'],
        ]);

        /**
         * @var PublicKeyCredential $publicKeyCredential
         */
        $publicKeyCredential = (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))
            ->create()
            ->deserialize($data['answer'], PublicKeyCredential::class, 'json');

        //        Make sure that the key is Assertion.
        if (! $publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            return to_route('profile.edit');
        }

        //        We retrieve the user passkey from the database and we check if the response is valid.
        $passkey = Passkey::query()->where('credential_id', $publicKeyCredential->rawId)->first();

        //        we check in case if the user deleted the passkey on they profile but not on authenticator. (this will end on invalid token)
        if (! $passkey) {
            throw ValidationException::withMessages([
                'answer' => 'The given passkey is invalid.',
            ]);
        }

        //            create the assertion response validator and check the response.
        try {
            $publicKeyCredentialSource = AuthenticatorAssertionResponseValidator::create()->check(
                credentialId: $passkey->data,
                authenticatorAssertionResponse: $publicKeyCredential->response,
                publicKeyCredentialRequestOptions: Session::get('passkey-authenticate-options'),
                request: $request->getHost(),
                userHandle: null,
            );
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'answer' => 'The given passkey is invalid.',
            ]);
        }

        //        if everything goes right we log the user in.
        Auth::loginUsingId($passkey->user_id);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
