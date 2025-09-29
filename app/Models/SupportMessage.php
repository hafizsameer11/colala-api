<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    protected $fillable = ['ticket_id','sender_id','message','attachment','is_read'];

    public function ticket(){ return $this->belongsTo(SupportTicket::class,'ticket_id'); }
    public function sender(){ return $this->belongsTo(User::class,'sender_id'); }
}
