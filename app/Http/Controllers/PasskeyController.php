<?php

namespace App\Http\Controllers;

use App\Actions\PasskeyAuthenticateAction;
use App\Actions\PasskeyStoreAction;
use App\Models\Passkey;
use Illuminate\Http\Request;

class PasskeyController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, PasskeyStoreAction $passkeyStoreAction)
    {
        $passkeyStoreAction->handle($request);
    }

    public function destroy(Passkey $passkey)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete', $passkey);

        $passkey->delete();

        return redirect()->back()->withFragment('managePasskeys');
    }

    public function authenticate(Request $request, PasskeyAuthenticateAction $passkeyAuthenticateAction)
    {
        $passkeyAuthenticateAction->handle($request);
    }
}
