<?php
namespace App\Services;
use App\Models\{Member,Tank,Sale,CreditEntry,StockMovement,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService {
    public function checkout(User $employee, array $data): Sale {
        abort_unless($employee->active && in_array($employee->role,['admin','manager','customer'],true),403);
        if($employee->role==='customer') {
            abort_unless($employee->member_id !== null && $employee->member_id === (int)$data['member_id'],403);
            if((int)($data['discount']??0)!==0)$this->fail('ลูกค้าไม่สามารถกำหนดส่วนลดเองได้');
        }
        return DB::transaction(function () use ($employee, $data) {
            // One member lock serializes balance changes and repeat requests for this basket.
            $member = Member::whereKey($data['member_id'])->lockForUpdate()->firstOrFail();
            $existing = Sale::where('checkout_token', $data['checkout_token'])->first();
            if ($existing) {
                abort_unless($existing->user_id === $employee->id && $existing->member_id === $member->id, 409);
                return $existing;
            }
            if (!$member->active) $this->fail('สมาชิกนี้ถูกปิดใช้งาน');
            $items = collect($data['items'])->sortBy('tank_id');
            $tanks = Tank::whereIn('id', $items->pluck('tank_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $subtotal = 0;
            foreach ($items as $item) {
                $tank = $tanks->get($item['tank_id']);
                if (!$tank || !$tank->active) $this->fail('มีรถถังที่ไม่พร้อมจำหน่าย กรุณาจัดตะกร้าใหม่');
                if ($tank->stock < $item['quantity']) $this->fail($tank->name.' มีไม่พอในคลัง (เหลือ '.$tank->stock.' คัน)');
                $subtotal += $tank->price * $item['quantity'];
            }
            $discount = (int) ($data['discount'] ?? 0);
            if ($discount > $subtotal) $this->fail('ส่วนลดต้องไม่เกินยอดสินค้า');
            $total = $subtotal - $discount;
            if ($member->credits < $total) $this->fail('เครดิตไม่เพียงพอ ขาดอีก '.number_format($total - $member->credits).' CR');
            $member->decrement('credits', $total);
            $sale = Sale::create([
                'number'=>'TD-'.now()->format('ymd').'-'.strtoupper(Str::random(8)),
                'checkout_token'=>$data['checkout_token'],'member_id'=>$member->id,'user_id'=>$employee->id,
                'member_name'=>$member->name,'player_code'=>$member->player_code,'employee_name'=>$employee->name,
                'subtotal'=>$subtotal,'discount'=>$discount,'total'=>$total,'balance_after'=>$member->credits,
            ]);
            foreach ($items as $item) {
                $tank = $tanks[$item['tank_id']]; $quantity = (int) $item['quantity'];
                $sale->items()->create(['tank_id'=>$tank->id,'tank_name'=>$tank->name,'sku'=>$tank->sku,'quantity'=>$quantity,'unit_price'=>$tank->price,'line_total'=>$quantity*$tank->price]);
                $tank->decrement('stock', $quantity);
                StockMovement::create(['tank_id'=>$tank->id,'user_id'=>$employee->id,'sale_id'=>$sale->id,'quantity'=>-$quantity,'balance_after'=>$tank->stock,'note'=>'ขาย '.$sale->number]);
            }
            CreditEntry::create(['member_id'=>$member->id,'user_id'=>$employee->id,'sale_id'=>$sale->id,'amount'=>-$total,'balance_after'=>$member->credits,'note'=>'ซื้อรถถัง '.$sale->number]);
            return $sale;
        }, 3);
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['checkout'=>$message]); }
}
