<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
    use Notifiable, \Illuminate\Database\Eloquent\SoftDeletes;
    protected $fillable = ['name','email','password','role','active','member_id'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed','active'=>'boolean','member_id'=>'integer']; }
    public function managesStore(): bool { return in_array($this->role,['admin','manager'],true); }
    public function member() { return $this->belongsTo(Member::class); }
}
