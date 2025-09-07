<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get decrypted value
     */
    public function getDecryptedValue()
    {
        if ($this->type === 'encrypted') {
            return Crypt::decryptString($this->value);
        }

        if ($this->type === 'json') {
            return json_decode($this->value, true);
        }

        return $this->value;
    }

    /**
     * Set encrypted value
     */
    public function setEncryptedValue($value)
    {
        if ($this->type === 'encrypted') {
            $this->value = Crypt::encryptString($value);
        } elseif ($this->type === 'json') {
            $this->value = json_encode($value);
        } else {
            $this->value = $value;
        }
    }

    /**
     * Get setting by key
     */
    public static function get($key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        return $setting->getDecryptedValue();
    }

    /**
     * Set setting by key
     */
    public static function set($key, $value, $type = 'string', $description = null, $isPublic = false)
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->type = $type;
        $setting->description = $description;
        $setting->is_public = $isPublic;
        $setting->setEncryptedValue($value);
        $setting->save();

        return $setting;
    }

    /**
     * Get PagHiper settings
     */
    public static function getPagHiperSettings()
    {
        return [
            'api_key' => static::get('paghiper_api_key'),
            'token' => static::get('paghiper_token'),
            'environment' => static::get('paghiper_environment', 'homologacao'),
        ];
    }

    /**
     * Set PagHiper settings
     */
    public static function setPagHiperSettings($apiKey, $token, $environment)
    {
        static::set('paghiper_api_key', $apiKey, 'encrypted', 'PagHiper API Key');
        static::set('paghiper_token', $token, 'encrypted', 'PagHiper Token');
        static::set('paghiper_environment', $environment, 'string', 'PagHiper Environment (producao or homologacao)');
    }

    /**
     * Scope for public settings
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
