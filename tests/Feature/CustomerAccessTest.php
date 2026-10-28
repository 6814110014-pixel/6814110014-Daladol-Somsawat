<?php
namespace Tests\Feature;
use App\Models\{User,Member,Tank,Sale,CreditEntry};
use App\Services\{CustomerAccountService,CheckoutService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAccessTest extends TestCase {
    use RefreshDatabase;
    private User $customer;
    private User $other;
    private User $admin;
    private Tank $tank;
    protected function setUp(): void {
        parent::setUp();
        $accounts=app(CustomerAccountService::class);
        $this->customer=$accounts->create(['name'=>'Own Customer','email'=>'own@example.test','password'=>'Password!123']);
        $this->other=$accounts->create(['name'=>'Private Other','email'=>'other@example.test','password'=>'Password!123']);
        $this->customer->member->update(['credits'=>10000]);$this->other->member->update(['credits'=>54321]);
        $this->admin=User::create(['name'=>'Admin','email'=>'admin@example.test','password'=>'Password!123','role'=>'admin','active'=>true]);
        $this->tank=Tank::create(['sku'=>'DE-TANK','name'=>'Test Vehicle','country'=>'DE','category'=>'heavy','description'=>'History','image_file'=>'test.jpg','price'=>3000,'stock'=>10]);
    }
    public function test_login_portals_render_separate_actions(): void {
        $this->get('/login')->assertOk()->assertSee('เข้าสู่ระบบลูกค้า')->assertSee('action="'.url('/login').'"',false)->assertSee('สมัครลูกค้า');
        $this->get('/seller/login')->assertOk()->assertSee('เข้าสู่ระบบผู้ขาย')->assertSee('action="'.url('/seller/login').'"',false)->assertDontSee('สมัครลูกค้า');
    }
    public function test_admin_cannot_login_through_customer_endpoint_even_with_forged_role(): void {
        $this->from('/login')->post('/login',['email'=>$this->admin->email,'password'=>'Password!123','role'=>'admin'])->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }
    public function test_customer_cannot_login_through_seller_endpoint_even_with_forged_role(): void {
        $this->from('/seller/login')->post('/seller/login',['email'=>$this->customer->email,'password'=>'Password!123','role'=>'admin'])->assertRedirect('/seller/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }
    public function test_customer_login_and_logout_use_customer_portal(): void {
        $this->post('/login',['email'=>$this->customer->email,'password'=>'Password!123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->customer);
        $this->post('/logout')->assertRedirect('/login');$this->assertGuest();
    }
    private function basket(User $user): array { return ['member_id'=>$user->member_id,'discount'=>0,'checkout_token'=>(string)Str::uuid(),'items'=>[['tank_id'=>$this->tank->id,'quantity'=>1]]]; }
    public function test_registration_creates_unique_zero_credit_customer_and_cannot_claim_existing_member(): void {
        $old=Member::create(['player_code'=>'LEGACY','name'=>'Legacy','email'=>'signup@example.test','credits'=>99999]);
        $this->get('/register')->assertOk();
        $this->post('/register',['name'=>'New Player','email'=>'SIGNUP@example.test','password'=>'NewPassword!1','password_confirmation'=>'NewPassword!1','role'=>'admin','credits'=>100000,'member_id'=>$old->id])->assertRedirect('/dashboard');
        $user=User::where('email','signup@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);$this->assertSame('customer',$user->role);
        $this->assertNotEquals($old->id,$user->member_id);$this->assertSame(0,$user->member->credits);
        $this->assertSame(99999,$old->fresh()->credits);
    }
    public function test_registration_rejects_duplicate_email_and_weak_password(): void {
        $count=Member::count();
        $this->post('/register',['name'=>'New','email'=>'OWN@example.test','password'=>'tiny','password_confirmation'=>'tiny'])->assertSessionHasErrors(['email','password']);
        $this->assertSame($count,Member::count());$this->assertGuest();
    }
    public function test_customer_pages_only_show_own_data_and_hide_internal_stock_history(): void {
        $foreign=app(CheckoutService::class)->checkout($this->other,$this->basket($this->other));
        CreditEntry::create(['member_id'=>$this->other->member_id,'user_id'=>$this->admin->id,'amount'=>1,'balance_after'=>51322,'note'=>'PRIVATE-OTHER-CREDIT']);
        $this->actingAs($this->customer);
        foreach(['/dashboard','/pos','/garage','/profile','/sales','/credits','/guide','/tanks','/tanks/'.$this->tank->id] as $url) {
            $this->get($url)->assertOk()->assertDontSee('Private Other')->assertDontSee('PRIVATE-OTHER-CREDIT')->assertDontSee($foreign->number);
        }
        $this->get('/pos')->assertSee('Own Customer')->assertDontSee('เลือกสมาชิกเพื่อชำระเงิน')->assertDontSee('ส่วนลด (CR)');
        $this->get('/tanks/'.$this->tank->id)->assertDontSee('ประวัติคลัง')->assertDontSee('ตำแหน่งใส่ภาพรุ่นนี้');
        $this->get('/sales/'.$foreign->id)->assertNotFound();
        $this->get('/members/'.$this->other->member_id)->assertForbidden();
        $this->get('/members/'.$this->other->member_id.'/edit')->assertForbidden();
        $this->put('/members/'.$this->other->member_id,['name'=>'Hacked'])->assertForbidden();
    }
    public function test_customer_cannot_charge_another_wallet_or_give_discount(): void {
        $this->actingAs($this->customer)->post('/checkout',$this->basket($this->other))->assertForbidden();
        $this->post('/checkout',[...$this->basket($this->customer),'discount'=>1])->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('sales',0);$this->assertSame(10000,$this->customer->member->fresh()->credits);$this->assertSame(54321,$this->other->member->fresh()->credits);
    }
    public function test_customer_purchase_adds_own_garage_and_duplicate_does_not_charge_again(): void {
        $basket=$this->basket($this->customer);
        $this->actingAs($this->customer)->post('/checkout',$basket)->assertRedirect();$this->post('/checkout',$basket)->assertRedirect();
        $sale=Sale::firstOrFail();$this->assertSame($this->customer->id,$sale->user_id);$this->assertSame($this->customer->member_id,$sale->member_id);
        $this->assertSame(7000,$this->customer->member->fresh()->credits);$this->assertDatabaseCount('sales',1);
        $this->get('/garage')->assertSee('เป็นเจ้าของ 1 คัน');$this->get('/sales/'.$sale->id)->assertOk();
    }
    public function test_profile_update_cannot_change_role_credits_or_owner_and_requires_current_password(): void {
        $this->actingAs($this->customer)->put('/profile',['name'=>'Changed','email'=>'changed@example.test','phone'=>'0800000000','role'=>'admin','credits'=>999999,'member_id'=>$this->other->member_id,'active'=>0])->assertRedirect();
        $user=$this->customer->fresh();$this->assertSame('customer',$user->role);$this->assertSame($this->customer->member_id,$user->member_id);$this->assertTrue($user->active);
        $this->assertSame('Changed',$user->member->name);$this->assertSame('changed@example.test',$user->member->email);$this->assertSame(10000,$user->member->credits);
        $data=['name'=>'Changed','email'=>'changed@example.test','password'=>'ChangedPassword!1','password_confirmation'=>'ChangedPassword!1'];
        $this->put('/profile',$data)->assertSessionHasErrors('current_password');
        $this->put('/profile',[...$data,'current_password'=>'wrong'])->assertSessionHasErrors('current_password');
        $this->put('/profile',[...$data,'current_password'=>'Password!123'])->assertRedirect();
        $this->assertTrue(Hash::check('ChangedPassword!1',$this->customer->fresh()->password));
    }
    public function test_inactive_linked_member_loses_session_and_cannot_login(): void {
        $this->actingAs($this->customer);$this->customer->member->update(['active'=>false]);
        $this->get('/garage')->assertRedirect('/login');$this->assertGuest();
        $this->post('/login',['email'=>$this->customer->email,'password'=>'Password!123'])->assertSessionHasErrors('email');$this->assertGuest();
    }
    public function test_inactive_tank_is_hidden_from_customer(): void {
        $this->tank->update(['active'=>false]);$this->actingAs($this->customer);
        $this->get('/tanks')->assertDontSee('Test Vehicle');$this->get('/pos')->assertDontSee('Test Vehicle');$this->get('/tanks/'.$this->tank->id)->assertNotFound();
    }
    public function test_admin_customer_update_syncs_account_and_member_without_resetting_balance(): void {
        $this->actingAs($this->admin)->put('/customers/'.$this->customer->id,['name'=>'Updated','email'=>'updated@example.test','active'=>0,'role'=>'admin','credits'=>0])->assertRedirect('/customers');
        $user=$this->customer->fresh();$this->assertSame('customer',$user->role);$this->assertFalse($user->active);$this->assertFalse($user->member->active);$this->assertSame('Updated',$user->member->name);$this->assertSame(10000,$user->member->credits);
    }
}
