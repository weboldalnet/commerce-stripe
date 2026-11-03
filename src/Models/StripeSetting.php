<?php

namespace Weboldalnet\CommerceStripe\Models;

use Illuminate\Database\Eloquent\Model;
use Weboldalnet\CommerceCore\Models\Concerns\UsesCommerceConnection;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property string $group
 * @mixin \Eloquent
 */
class StripeSetting extends Model
{
    use UsesCommerceConnection;

    protected $table = 'public.commerce_stripe_settings';

    protected $fillable = ['key', 'value', 'type', 'group'];

    // a titkosított értékek ne kerülhessenek ki JSON-ban (pl. egy véletlen dump vagy API-válasz)
    protected $hidden = ['value'];
}
