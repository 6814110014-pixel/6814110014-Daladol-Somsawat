<?php
namespace Tests\Feature;
use App\Models\{User,Member,Tank,Sale,CreditEntry,StockMovement};
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TankDepotTest extends TestCase {
    use RefreshDatabase;
    private User $admin;
    private User $customer;
    private Member $member;
    private Tank $tank;
    protected function setUp(): void {
        parent::setUp();
        $this->admin=User::create(['name'=>'Admin','email'=>'admin@test.local','password'=>'password123','role'=>'admin','active'=>true]);
        $this->member=Member::create(['name'=>'Player','player_code'=>'P1','credits'=>10000,'active'=>true]);
        $this->customer=User::create(['name'=>'Customer','email'=>'customer@test.local','password'=>'password123','role'=>'customer','active'=>true,'member_id'=>$this->member->id]);
        $this->tank=Tank::create(['sku'=>'DE-TEST','name'=>'Tiger Test','country'=>'DE','category'=>'heavy','year'=>1942,'description'=>'Test history','image_file'=>'test.jpg','price'=>3000,'stock'=>3,'low_stock'=>1,'active'=>true]);
    }
    private function basket(array $replace=[]): array { return array_replace(['member_id'=>$this->member->id,'checkout_token'=>(string)Str::uuid(),'discount'=>0,'items'=>[['tank_id'=>$this->tank->id,'quantity'=>2]]],$replace); }
    public function test_guest_redirect_and_login_logout(): void {
        $this->get('/pos')->assertRedirect('/login');
        $this->post('/seller/login',['email'=>$this->admin->email,'password'=>'password123'])->assertRedirect();
        $this->assertAuthenticatedAs($this->admin);
        $this->post('/logout')->assertRedirect('/seller/login');$this->assertGuest();
    }
    public function test_all_admin_pages_render_and_missing_images_have_placeholders(): void {
        $this->actingAs($this->admin);
        foreach (['/dashboard','/pos','/tanks','/tanks/create','/tanks/'.$this->tank->id,'/tanks/'.$this->tank->id.'/edit','/members','/members/create','/members/'.$this->member->id,'/members/'.$this->member->id.'/edit','/customers','/customers/create','/customers/'.$this->customer->id.'/edit','/sales','/credits','/reports','/guide'] as $url) $this->get($url)->assertOk();
        $this->get('/tanks/'.$this->tank->id)->assertDontSee('ตำแหน่งใส่ภาพรุ่นนี้');
    }
    public function test_sale_changes_all_ledgers_and_garage_together(): void {
        $this->actingAs($this->admin)->post('/checkout',$this->basket(['discount'=>500]))->assertRedirect();
        $sale=Sale::firstOrFail();
        $this->assertEquals(5500,$sale->total);$this->assertEquals(4500,$this->member->fresh()->credits);$this->assertEquals(1,$this->tank->fresh()->stock);
        $this->assertDatabaseHas('sale_items',['sale_id'=>$sale->id,'quantity'=>2,'unit_price'=>3000,'line_total'=>6000]);
        $this->assertDatabaseHas('credit_entries',['sale_id'=>$sale->id,'amount'=>-5500,'balance_after'=>4500]);
        $this->assertDatabaseHas('stock_movements',['sale_id'=>$sale->id,'quantity'=>-2,'balance_after'=>1]);
        $this->get('/members/'.$this->member->id)->assertSee('เป็นเจ้าของ 2 คัน');
        $this->get('/sales/'.$sale->id)->assertOk()->assertSee('5,500');
    }
    public function test_repeated_checkout_token_does_not_double_charge(): void {
        $basket=$this->basket();$this->actingAs($this->customer)->post('/checkout',$basket)->assertRedirect();$this->post('/checkout',$basket)->assertRedirect();
        $this->assertDatabaseCount('sales',1);$this->assertEquals(4000,$this->member->fresh()->credits);$this->assertEquals(1,$this->tank->fresh()->stock);
    }
    public function test_insufficient_credit_leaves_all_data_unchanged(): void {
        $this->member->update(['credits'=>500]);
        $this->actingAs($this->customer)->post('/checkout',$this->basket())->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('sales',0);$this->assertDatabaseCount('credit_entries',0);$this->assertDatabaseCount('stock_movements',0);
        $this->assertEquals(500,$this->member->fresh()->credits);$this->assertEquals(3,$this->tank->fresh()->stock);
    }
    public function test_insufficient_stock_rolls_back_entire_multiline_basket(): void {
        $other=Tank::create(['sku'=>'US-TEST','name'=>'Sherman','country'=>'US','category'=>'medium','description'=>'Test','image_file'=>'other.jpg','price'=>1000,'stock'=>0]);
        $basket=$this->basket(['items'=>[['tank_id'=>$this->tank->id,'quantity'=>1],['tank_id'=>$other->id,'quantity'=>1]]]);
        $this->actingAs($this->customer)->post('/checkout',$basket)->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('sales',0);$this->assertEquals(10000,$this->member->fresh()->credits);$this->assertEquals(3,$this->tank->fresh()->stock);
    }
    public function test_checkout_uses_server_price_and_rejects_invalid_discount_and_duplicates(): void {
        $this->actingAs($this->customer)->post('/checkout',$this->basket(['discount'=>99999]))->assertSessionHasErrors('checkout');
        $this->post('/checkout',$this->basket(['items'=>[['tank_id'=>$this->tank->id,'quantity'=>1],['tank_id'=>$this->tank->id,'quantity'=>1]]]))->assertSessionHasErrors();
        $this->post('/checkout',$this->basket(['items'=>[['tank_id'=>$this->tank->id,'quantity'=>1,'price'=>1]],'total'=>1]))->assertRedirect();
        $this->assertEquals(3000,Sale::firstOrFail()->total);
    }
    public function test_inactive_member_and_tank_cannot_be_sold(): void {
        $this->actingAs($this->admin);$this->member->update(['active'=>false]);
        $this->post('/checkout',$this->basket())->assertSessionHasErrors('checkout');
        $this->member->update(['active'=>true]);$this->tank->update(['active'=>false]);
        $this->post('/checkout',$this->basket())->assertSessionHasErrors('checkout');$this->assertDatabaseCount('sales',0);
    }
    public function test_customer_cannot_change_admin_resources(): void {
        $this->actingAs($this->customer);
        foreach(['/customers','/customers/create','/reports','/reports/export','/tanks/create','/tanks/'.$this->tank->id.'/edit'] as $url) $this->get($url)->assertForbidden();
        $this->post('/members/'.$this->member->id.'/topup',['amount'=>500,'note'=>'x'])->assertForbidden();
        $this->post('/tanks/'.$this->tank->id.'/stock',['quantity'=>1,'note'=>'x'])->assertForbidden();
        $this->post('/customers',[])->assertForbidden();
    }
    public function test_disabled_customer_is_logged_out(): void {
        $this->actingAs($this->customer);$this->customer->update(['active'=>false]);
        $this->get('/pos')->assertRedirect('/login');$this->assertGuest();
        $this->post('/login',['email'=>$this->customer->email,'password'=>'password123'])->assertSessionHasErrors('email');
    }
    public function test_topup_stock_audits_and_negative_stock_rejected(): void {
        $this->actingAs($this->admin)->post('/members/'.$this->member->id.'/topup',['amount'=>2500,'note'=>'demo'])->assertRedirect();
        $this->assertEquals(12500,$this->member->fresh()->credits);$this->assertDatabaseHas('credit_entries',['amount'=>2500,'balance_after'=>12500]);
        $this->post('/tanks/'.$this->tank->id.'/stock',['quantity'=>-4,'note'=>'adjust'])->assertSessionHasErrors('quantity');
        $this->post('/tanks/'.$this->tank->id.'/stock',['quantity'=>5,'note'=>'restock'])->assertRedirect();
        $this->assertEquals(8,$this->tank->fresh()->stock);$this->assertDatabaseHas('stock_movements',['quantity'=>5,'balance_after'=>8]);
    }
    public function test_admin_creates_customer_without_granting_admin_role(): void {
        $this->actingAs($this->admin)->post('/customers',['name'=>'New Customer','email'=>'new@test.local','password'=>'newpassword123','password_confirmation'=>'newpassword123','role'=>'admin','active'=>1])->assertRedirect('/customers');
        $new=User::where('email','new@test.local')->firstOrFail();
        $this->assertSame('customer',$new->role);$this->assertSame(0,$new->member->credits);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword123',$new->password));
        $this->put('/customers/'.$this->admin->id,['name'=>'x'])->assertNotFound();
    }
    public function test_member_create_update_and_tank_image_path_validation(): void {
        $this->actingAs($this->admin)->post('/members',['player_code'=>'P2','name'=>'Second','active'=>1,'credits'=>999999])->assertRedirect();
        $second=Member::where('player_code','P2')->firstOrFail();$this->assertEquals(0,$second->credits);
        $this->put('/members/'.$second->id,['player_code'=>'P2','name'=>'Renamed','active'=>0])->assertRedirect();$this->assertFalse($second->fresh()->active);
        $data=['sku'=>'US-NEW','name'=>'New tank','country'=>'US','category'=>'light','description'=>'History','image_file'=>'../../evil.php','price'=>2000,'low_stock'=>2,'active'=>1];
        $this->actingAs($this->admin)->post('/tanks',$data)->assertSessionHasErrors('image_file');
        $data['image_file']='new-tank.webp';$this->post('/tanks',$data)->assertRedirect();$this->assertDatabaseHas('tanks',['sku'=>'US-NEW','stock'=>0]);
    }
    public function test_receipt_keeps_original_names_and_prices_after_edit(): void {
        $this->actingAs($this->customer)->post('/checkout',$this->basket())->assertRedirect();$sale=Sale::firstOrFail();
        $this->tank->update(['name'=>'Changed','price'=>9000]);$this->member->update(['name'=>'Renamed']);
        $this->get('/sales/'.$sale->id)->assertSee('Tiger Test')->assertSee('Player')->assertSee('3,000');
    }
    public function test_reports_date_range_export_and_csv_formula_escaping(): void {
        $this->member->update(['name'=>'=HYPERLINK("x")']);
        $this->actingAs($this->admin)->post('/checkout',$this->basket(['discount'=>500]))->assertRedirect();
        $this->get('/reports?from='.today()->format('Y-m-d').'&to='.today()->format('Y-m-d'))->assertOk()->assertSee('5,500');
        $csv=$this->get('/reports/export')->assertOk()->streamedContent();$this->assertStringContainsString("'=HYPERLINK",$csv);$this->assertStringContainsString('5500',$csv);
        $this->get('/reports?from=2026-10-20&to=2026-10-01')->assertSessionHasErrors('to');
        $this->get('/reports?from=1990-01-01&to=1990-01-02')->assertSee('ไม่มีการขาย');
    }
    public function test_seed_is_repeatable_without_resetting_business_data(): void {
        $this->seed();$count=Tank::count();$m=Member::where('player_code','PLAYER-001')->firstOrFail();$m->update(['credits'=>123]);
        $this->seed();$this->assertEquals($count,Tank::count());$this->assertEquals(123,$m->fresh()->credits);
        $this->assertEquals(15,Tank::where('sku','!=','DE-TEST')->count());
    }
}
