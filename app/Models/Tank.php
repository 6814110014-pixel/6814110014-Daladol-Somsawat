<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Tank extends Model {
    protected $guarded = ['id'];
    public const COUNTRIES = ['DE'=>'เยอรมนี','US'=>'อเมริกา','SU'=>'สหภาพโซเวียต','JP'=>'ญี่ปุ่น','SE'=>'สวีเดน'];
    public const CATEGORIES = ['light'=>'รถถังเบา','medium'=>'รถถังกลาง','heavy'=>'รถถังหนัก','tank_destroyer'=>'ยานเกราะพิฆาตรถถัง'];
    protected function casts(): array { return ['active'=>'boolean','price'=>'integer','stock'=>'integer']; }
    public function getImageUrlAttribute(): ?string {
        return is_file(public_path('images/tanks/'.$this->image_file)) ? asset('images/tanks/'.$this->image_file) : null;
    }
    public function movements() { return $this->hasMany(StockMovement::class); }
}
