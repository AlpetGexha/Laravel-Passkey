# Passkey

### What is Passkey and why we need them 

Passkeys are a simple and secure alternative to passwords. With a passkey, you can sign in to your Google Account with your fingerprint, face scan, or device screen lock, like a PIN. Passkeys provide the strongest protection against threats like phishing.

### Database structure

The database structure is as follows:
- user_id
- name
- credential_id
- data

this is all we need to work with the passkey.

### Passkey Operations

Passkey are small part of Webauthn, which is a standard for secure passwordless authentication on the web, developed by the W3C.

So for example the usb auth key we put on computer to verify our identity is a webauthn. So the web need to know what operations we what we want to support 
For that we need a Sycnronous operation request.

- PublicKeyCredentialCreationOptions - This is the object that the server sends to the client to tell it what kind of credentials it wants to create. This object includes things like the challenge, the relying party ID, and the user information.
    - rp (relying party) - This is for the server to identify itself so HOST and DOMAIN
        - name - The name of the App.
        - id - The domain of the App. (This need to be with https to work)
- challenge - A random value that the server generates to prevent replay attacks.
- PublicKeyCredentialUserEntity - This is the user Object 
    - name - The unique value of the user (username or email)
    - id - The id of the user
    - displayName - The user name or full name

Now after that we need to make sure the data are going on front end for the security reason whe can install npm install the @simplewebauthn/browser package to help us with that.

...

Dont forget to add HTTPS to your domain to make it work. (herd secure) and done

- API install
- API Controller
- WEBAuthn Framework 
  - Config
- Alpine js config for Front
- Install Front end Webauthn

### Storing the Passkey

To store the Public Key Credential first we need to check if the Response is valid Attestation Object. To check this we can use AuthenticatorAttestationResponseValidator::create()->check([...])
with the following parameters:
- request - witch is the host
- publicKeyCredentialCreationOptions - The Creation Options whe created from api
- authenticatorAttestationResponse: The response from the front end, witch need to be deserialized, ... 

Make sure that the response Attestation and not Assertion. (just in case)


### Authenticating the Passkey

We need to send the challenge to the backend from frontend and take that challenge (answer) to the backend to:
- Send the Request Options from the front to the frontend
- Take the response on Backend 
  - Validate 
  - Deserialize
  - Get the Passkey
  - Check if that is Valid
  - Login user 

To create Request Options lets call PublicKeyCredentialRequestOptions(...) and we need to pass the following parameters:
- challenge - Random String witch is a secure cryptographic 
- rpID - Relying Party ID (The domain of the App)

We store that on Session and we send to the backend.

The logic is almost the same as store except now we need to check for Assertion instead of Attestation. And we are not Storing the Passkey but we are checking if the Passkey is valid and Authenticate the user.

We retrieve the user passkey from the database and we check if the response is valid.
