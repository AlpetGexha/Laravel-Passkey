# Passkey

### What is Passkey and why we need them

Passkeys are a simple and secure alternative to passwords. With a passkey, you can sign in to your Google Account with
your fingerprint, face scan, or device screen lock, like a PIN. Passkeys provide the strongest protection against
threats like phishing.


> **Note:** Make sure first to add HTTPS to your environment. If u are using herd use this command
> ```bash
> herd secure
> ```

### Database structure

The database structure is as follows:

- user_id
- name
- credential_id
- data

this is all we need to work with the passkey.

For the Modal

```bash
php artisan make:model Passkey -mf
```

The migration

```php
Schema::create('passkeys', function (Blueprint $table) {
    $table->id();
    $table->foreignIdFor(\App\Models\User::class)->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('credential_id');
    $table->json('data');
    $table->timestamps();
});
```

add Fillable and Casts to the Passkey Model

```php
protected $fillable = ['name', 'credential_id', 'data'];
 
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

Also to make sure we are getting the right typo from the data we can make the field cast to json but this will lead to
more error so we need to add an Attribute for that

```php
public function data(): Attribute
{
    return new Attribute(
        get: fn (string $value) => (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))
            ->create()
            ->deserialize($value, PublicKeyCredentialSource::class, 'json'),
        set: fn (PublicKeyCredentialSource $value) => [
            'credential_id' => $value->publicKeyCredentialId,
            'data' => json_encode($value),
        ],
    );
}
```

### Passkey Operations

Passkey are small part of Webauthn, which is a standard for secure passwordless authentication on the web, developed by
the W3C.

So for example the usb auth key we put on computer to verify our identity is a webauthn. So the web need to know what
operations we what we want to support

For that we need a Synchronous operation request.

But first let install [WebAuthn](https://webauthn-doc.spomky-labs.com/v/v4.8/symfony-ux/installation) package for php.

```bash 
composer require web-auth/webauthn-stimulus
```

But Before we start we need an API to work with so:

```php
php artisan install:api
```

on the `boostrap/app.php` add the following line

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->statefulApi();
})
```

After the installation lets create the API for Credential Creation Opstion for the FrontEnd

```php
php artisan make:controller Api/PasskeyController
```

...

```php
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialCreationOptions
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;


class PasskeyController extends Controller
{
   public function registerOptions(Request $request)
    {
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

            //            authenticatorSelection: new AuthenticatorSelectionCriteria(
            //                authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE,
            //                requireResidentKey: true,
            //            ),
        );

        Session::flash('passkey-register-options', $options);

        return response()->json($options);
    }
```

- **PublicKeyCredentialCreationOptions** - This is the object that the server sends to the client to tell it what kind
  of
  credentials it wants to create. This object includes things like the challenge, the relying party ID, and the user
  information.

    - **rp** (relying party) - This is for the server to identify itself so HOST and DOMAIN
        - _name_ - The name of the App.
        - _id_ - The domain of the App. (This need to be with https to work)
- **challenge** - A random value that the server generates to prevent replay attacks.


- **PublicKeyCredentialUserEntity** - This is the user Object
    - name - The unique value of the user (username or email)
    - id - The id of the user
    - displayName - The user name or full name

For the part that storing the options on session we need that to easy pass on Controller

on route/api.php lets add a route for that

```php
Route::get('passkeys/register', [\App\Http\Controllers\Api\PasskeyController::class, 'registerOptions'])
    ->middleware('auth:sanctum')
    ->name('passkeys.register');
```

Now after that we need to make sure the data are going on front end for the security reason whe can install npm install
the [@simplewebauthn/browser](https://simplewebauthn.dev/docs/packages/browser) package to help us with that.

```bash
npm install @simplewebauthn/browser
```

### Storing the Passkey

So after setting the Webauthn for Frontend we need a way to communicate with the backend.

To store the Public Key Credential first we need to make sure that the Response we are getting from frontend is valid
Attestation Object.

- First we need to Validate the Request data
- Deserialize the json given data
- Make sure we are getting the Attestation Object (just in case)
- Create and Store the Passkey

I make this as an Action but you can make directly on the Controller just a preference

```php
namespace App\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;

class PasskeyStoreAction
{
    public function store(Request $request)
    {
        $data = $request->validateWithBag('createPasskey', [
            'name' => ['required', 'string', 'max:255'],
            'passkey' => ['required', 'json'],
        ]);

        /**
         * @var PublicKeyCredential $publicKeyCredential
         */
        $publicKeyCredential = (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))
            ->create()
            ->deserialize($data['passkey'], PublicKeyCredential::class, 'json');

        //        Make sure that the response Attestation and not Assertion.
        if (! $publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            return to_route('login');
        }

        try {
            $publicKeyCredentialSource = AuthenticatorAttestationResponseValidator::create()->check(
                authenticatorAttestationResponse: $publicKeyCredential->response,
                publicKeyCredentialCreationOptions: Session::get('passkey-register-options'),
                request: $request->getHost(),
            );
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'name' => 'The given passkey is invalid . ',
            ])->errorBag('createPasskey');
        }

        $request->user()->passkeys()->create([
            'name' => $data['name'],
            'credential_id' => $publicKeyCredentialSource->publicKeyCredentialId,
            'data' => $publicKeyCredentialSource,
        ]);

        return to_route('profile.edit')->withFragment('managePasskeys');
    }
}
``` 

Validating the Attestation we can use `AuthenticatorAttestationResponseValidator(...)` from the Webauthn package.

This method takes 3 parameters:

- **request** - witch is the host
- **publicKeyCredentialCreationOptions** - The Creation Options whe created from api
- **authenticatorAttestationResponse**: The response from the front end, witch need to be deserialized, ...

### Authenticating the Passkey

So far we setup a way to store the Passkey on database but we need a way to authenticate the user by that passkey we
created.

```php
use App\Models\Passkey;
use Webauthn\PublicKeyCredentialSource;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

public function authenticateOptions(Request $request)
{
    $allowedCredentials = $request->filled('email')
        ? Passkey::whereRelation('user', 'email', $request->email)
            ->get()
            ->map(fn (Passkey $passkey) => $passkey->data)
            ->map(fn (PublicKeyCredentialSource $publicKeyCredentialSource) => $publicKeyCredentialSource->getPublicKeyCredentialDescriptor())
        : [];

    $options = PublicKeyCredentialRequestOptions::create(
        challenge: Str::random(),
        rpId: parse_url(config('app.url'), PHP_URL_HOST),
        allowCredentials: $allowedCredentials,
    );

    Session::flash('passkey-authenticate-options', $options);

    return response()->json($options);
}
```

To create Request Options lets call PublicKeyCredentialRequestOptions(...) and we need to pass the following parameters:

- challenge - Random String witch is a secure cryptographic
- rpID - Relying Party ID (The domain of the App)
- allowCredentials - The credentials that are allowed to authenticate the user

On API Route

```php
Route::get('passkeys/authenticate', [\App\Http\Controllers\Api\PasskeyController::class, 'authenticateOptions']);
```

We need to send the challenge to the backend from frontend and take that challenge to the backend to:

The logic is almost the same as store except now we need to check for Assertion instead of Attestation. And we are not
Storing the Passkey but we are checking if the Passkey is valid and Authenticate the user. So:

- Take the response on Backend
    - Validate
    - Deserialize
    - Get the Passkey
    - Check if that is Valid
    - Login user

```php
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
```

To make more user friendly on front end first we ask for email and then for the passkey. If we dont have passkey we
simple ask for password
