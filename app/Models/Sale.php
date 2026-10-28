<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Sale extends Model {
    protected $guarded = ['id'];
    public function items() { return $this->hasMany(SaleItem::class); }
    public function member() { return $this->belongsTo(Member::class); }
    public function user() { return $this->belongsTo(User::class)->withTrashed(); }
}
