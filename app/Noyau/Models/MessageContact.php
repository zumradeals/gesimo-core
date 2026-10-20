<?php

namespace App\Noyau\Models;

use Illuminate\Database\Eloquent\Model;

class MessageContact extends Model
{
    protected $table = 'message_contact';

    public const UPDATED_AT = null;

    protected $fillable = ['nom', 'contact', 'message', 'adresse_ip'];
}
