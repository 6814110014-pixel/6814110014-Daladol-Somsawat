<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Member extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['active'=>'boolean','credits'=>'integer']; }
    public function sales() { return $this->hasMany(Sale::class); }
    public function entries() { return $this->hasMany(CreditEntry::class); }
}
