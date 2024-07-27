<?php

namespace App\Http\Controllers;

use App\Models\Passkey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;

class PasskeyController extends Controller
{

    public function authenticate(Request $request)
    {
        $data = $request->validate([
            'answer' => ['required', 'json'],
        ]);

        /** @var PublicKeyCredential $publicKeyCredential */
        $publicKeyCredential = (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))
            ->create()
            ->deserialize($data['answer'], PublicKeyCredential::class, 'json');

//        Make sure that the key is Assertion and not .
        if (!$publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            return to_route('profile.edit');
        }

        $passkey = Passkey::query()->where('credential_id', $publicKeyCredential->rawId)->first();

        if (!$passkey) {
            throw ValidationException::withMessages([
                'answer' => 'The given passkey is invalid.'
            ]);
        }

        try {
            $publicKeyCredentialSource = AuthenticatorAssertionResponseValidator::create()->check(
                credentialId: $passkey->data,
                authenticatorAssertionResponse: $publicKeyCredential->response,
                publicKeyCredentialRequestOptions: Session::get('publicKeyCredentialRequestOptions'),
                request: $request->getHost(),
                userHandle: null,
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'name' => 'The given passkey is invalid . '
            ])->errorBag('createPasskey');
        }

        Auth::loginUsingId($passkey->user_id);
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validateWithBag('createPasskey', [
            'name' => ['required', 'string', 'max:255'],
            'passkey' => ['required', 'json'],
        ]);

        /** @var PublicKeyCredential $publicKeyCredential */
        $publicKeyCredential = (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))
            ->create()
            ->deserialize($data['passkey'], PublicKeyCredential::class, 'json');

//        Make sure that the response Attestation and not Assertion.
        if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            return to_route('login');
        }

        try {
            $publicKeyCredentialSource = AuthenticatorAttestationResponseValidator::create()->check(
                authenticatorAttestationResponse: $publicKeyCredential->response,
                publicKeyCredentialCreationOptions: Session::get('publicKeyCredentialCreationOptions'),
                request: $request->getHost(),
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'name' => 'The given passkey is invalid . '
            ])->errorBag('createPasskey');
        }

        $request->user()->passkeys()->create([
            'name' => $data['name'],
            'credential_id' => $publicKeyCredentialSource->publicKeyCredentialId,
            'data' => $publicKeyCredentialSource,
        ]);

        return to_route('profile . edit')->withFragment('passkey');
    }

    public function destroy(Passkey $passkey)
    {
        Gate::authorize('delete', $passkey);

        $passkey->delete();

        return redirect()->back()->withFragment('passkey');
    }
}
