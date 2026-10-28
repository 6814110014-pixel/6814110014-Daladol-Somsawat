<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CreditEntry extends Model {
    protected $guarded = ['id'];
    public function member() { return $this->belongsTo(Member::class); }
    public function user() { return $this->belongsTo(User::class)->withTrashed(); }
    public function sale() { return $this->belongsTo(Sale::class); }
}
