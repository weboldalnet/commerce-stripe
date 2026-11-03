<?php

namespace Weboldalnet\CommerceStripe\Models;

use Illuminate\Database\Eloquent\Model;

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
    protected $table = 'public.commerce_stripe_settings';

    protected $fillable = ['key', 'value', 'type', 'group'];

    // a titkosított értékek ne kerülhessenek ki JSON-ban (pl. egy véletlen dump vagy API-válasz)
    protected $hidden = ['value'];
}
