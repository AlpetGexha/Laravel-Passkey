<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyController extends Controller
{
    /**
     * @throws InvalidDataException
     */
    public function registerOptions(Request $request)
    {
        auth()->loginUsingId(1);

        $request->validate(['name' => ['required', 'string', 'max:255']]);

        $options = new PublicKeyCredentialCreationOptions(
            rp: new PublicKeyCredentialRpEntity(
                name: config('app.name'),
                id: parse_url(config('app.url'), PHP_URL_HOST),
            ),
            user: new PublicKeyCredentialUserEntity(
                name: $request->user()->email,
                id: $request->user()->id,
                displayName: $request->user()->name,
            ),
            challenge: Str::random(), // Attestation
            authenticatorSelection: new AuthenticatorSelectionCriteria(
                authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE,
                requireResidentKey: true,
            ),
        );

        Session::flash('publicKeyCredentialCreationOptions', $options);

        return response()->json($options);
    }

    public function requestOptions()
    {
        $options = PublicKeyCredentialRequestOptions::create(
            challenge: Str::random(),
            rpId: parse_url(config('app.url'), PHP_URL_HOST),
        );

        Session::flash('publicKeyCredentialRequestOptions', $options);

        return response()->json($options);
    }
}
