<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerAccessTest extends TestCase {
    use RefreshDatabase;
    public function test_manager_directory_lists_both_roles_and_filters_correctly(): void {
        $manager=$this->manager();
        $seller=User::create(['name'=>'Directory Seller','email'=>'seller-list@example.test','password'=>'SellerPass!123','role'=>'admin','active'=>false]);
        $customer=app(\App\Services\CustomerAccountService::class)->create(['name'=>'Directory Customer','email'=>'customer-list@example.test','password'=>'CustomerPass!123']);
        $this->actingAs($manager)->get('/manager/accounts')->assertOk()->assertSee($seller->email)->assertSee($customer->email)->assertSee('ปิดใช้งาน')->assertDontSee($seller->password);
        $this->get('/manager/accounts?type=seller')->assertOk()->assertSee($seller->email)->assertDontSee($customer->email);
        $this->get('/manager/accounts?type=customer')->assertOk()->assertSee($customer->email)->assertDontSee($seller->email);
        $this->get('/manager/accounts?q=seller-list')->assertOk()->assertSee($seller->email)->assertDontSee($customer->email);
        $this->get('/manager/accounts?q=nonexistent')->assertOk()->assertSee('ไม่พบบัญชี');
        $this->get('/manager/accounts?type=manager')->assertSessionHasErrors('type');
        $seller->update(['active'=>true]);
        $this->actingAs($seller)->get('/manager/accounts')->assertForbidden();
        $this->actingAs($customer)->get('/manager/accounts')->assertForbidden();
    }
    private function manager(): User {
        return User::create(['name'=>'Store Manager','email'=>'manager@example.test','password'=>'ManagerPass!123','role'=>'manager','active'=>true]);
    }
    public function test_manager_portal_login_access_and_logout(): void {
        $manager=$this->manager();
        $this->get('/manager/login')->assertOk()->assertSee('เข้าสู่ระบบผู้จัดการร้าน')->assertDontSee('สมัครลูกค้า');
        $this->post('/manager/login',['email'=>$manager->email,'password'=>'ManagerPass!123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($manager);
        foreach(['/dashboard','/customers','/customers/create','/members','/tanks','/tanks/create','/reports','/credits','/sales','/pos','/guide','/profile'] as $url) $this->get($url)->assertOk();
        $this->get('/dashboard')->assertSee('ผู้จัดการร้าน');
        $this->post('/customers',['name'=>'Managed Customer','email'=>'managed@example.test','password'=>'CustomerPass!123','password_confirmation'=>'CustomerPass!123','active'=>1])->assertRedirect('/customers');
        $this->assertDatabaseHas('users',['email'=>'managed@example.test','role'=>'customer']);
        $this->post('/logout')->assertRedirect('/manager/login');$this->assertGuest();
    }
    public function test_manager_cannot_use_customer_or_seller_portals(): void {
        $manager=$this->manager();
        foreach(['/login','/seller/login'] as $url) {
            $this->post($url,['email'=>$manager->email,'password'=>'ManagerPass!123','role'=>'manager'])->assertSessionHasErrors('email');$this->assertGuest();
        }
    }
    public function test_seller_cannot_use_manager_portal_and_disabled_manager_is_rejected(): void {
        $seller=User::create(['name'=>'Seller','email'=>'seller@example.test','password'=>'SellerPass!123','role'=>'admin','active'=>true]);
        $this->post('/manager/login',['email'=>$seller->email,'password'=>'SellerPass!123'])->assertSessionHasErrors('email');$this->assertGuest();
        $manager=$this->manager();$manager->update(['active'=>false]);
        $this->post('/manager/login',['email'=>$manager->email,'password'=>'ManagerPass!123'])->assertSessionHasErrors('email');$this->assertGuest();
        $this->actingAs($manager)->get('/dashboard')->assertRedirect('/login');$this->assertGuest();
    }
    public function test_public_registration_cannot_grant_manager_role(): void {
        $this->post('/register',['name'=>'Player','email'=>'player@example.test','password'=>'PlayerPass!123','password_confirmation'=>'PlayerPass!123','role'=>'manager'])->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users',['email'=>'player@example.test','role'=>'customer']);
        $this->get('/reports')->assertForbidden();
    }
}
