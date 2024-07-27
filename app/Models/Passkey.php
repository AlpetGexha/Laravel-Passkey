<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredentialSource;

class Passkey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'credential_id',
        'data',
    ];

    //    protected $casts = [
    //        'data' => 'json',
    //    ];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
