<?php
namespace Tests\Feature;
use App\Models\{User,Member,CreditEntry};
use App\Services\CustomerAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
class ManagerAccountCrudTest extends TestCase {
 use RefreshDatabase;
 private function manager(): User {return User::create(['name'=>'Manager','email'=>'manager@test.local','password'=>'Password!123','role'=>'manager','active'=>true]);}
 private function data(string $type): array {return ['type'=>$type,'name'=>'New Account','email'=>$type.'@test.local','password'=>'Password!123','password_confirmation'=>'Password!123','active'=>1];}
 public function test_manager_creates_and_edits_both_types_without_changing_roles_or_balances(): void {
  $this->actingAs($this->manager())->get('/manager/accounts/create')->assertOk();
  foreach(['seller','customer'] as $type){
   $data=$this->data($type);$this->post('/manager/accounts',$data+['role'=>'manager','credits'=>9999])->assertRedirect('/manager/accounts');
   $account=User::where('email',$data['email'])->firstOrFail();$this->assertSame($type==='seller'?'admin':'customer',$account->role);
   if($type==='customer'){$this->assertSame(0,$account->member->credits);$account->member->update(['credits'=>500]);}
   $this->get('/manager/accounts/'.$account->id.'/edit')->assertOk();
   $this->put('/manager/accounts/'.$account->id,['name'=>'Edited','email'=>'edit-'.$data['email'],'phone'=>'123','active'=>0,'role'=>'manager','credits'=>0])->assertRedirect('/manager/accounts');
   $account->refresh();$this->assertSame('Edited',$account->name);$this->assertFalse($account->active);$this->assertTrue(Hash::check('Password!123',$account->password));
   if($type==='customer'){$this->assertSame(500,$account->member->credits);$this->assertSame('Edited',$account->member->name);$this->assertFalse($account->member->active);}
  }
 }
 public function test_deleting_accounts_preserves_ledger_and_disables_access(): void {
  $manager=$this->manager();
  $seller=User::create(['name'=>'Seller History','email'=>'seller@test.local','password'=>'Password!123','role'=>'admin','active'=>true]);
  $customer=app(CustomerAccountService::class)->create($this->data('customer'));
  $entry=CreditEntry::create(['member_id'=>$customer->member_id,'user_id'=>$seller->id,'amount'=>100,'balance_after'=>100,'note'=>'Historical credit']);
  $customer->member->update(['credits'=>100]);
  $this->actingAs($manager);
  foreach([$seller,$customer] as $account){$this->delete('/manager/accounts/'.$account->id)->assertRedirect('/manager/accounts');$this->assertSoftDeleted('users',['id'=>$account->id]);$this->get('/manager/accounts')->assertDontSee($account->email);}
  $this->assertSame('Seller History',$entry->fresh()->user->name);
  $this->assertSame(100,Member::find($customer->member_id)->credits);$this->assertFalse(Member::find($customer->member_id)->active);
  $this->get('/credits')->assertOk()->assertSee('Seller History');
  $this->post('/logout');
  foreach([['/login',$customer],['/seller/login',$seller]] as [$url,$account]){$this->post($url,['email'=>$account->email,'password'=>'Password!123'])->assertSessionHasErrors('email');$this->assertGuest();}
 }
 public function test_non_managers_cannot_modify_accounts_and_managers_cannot_target_managers(): void {
  $manager=$this->manager();$customer=app(CustomerAccountService::class)->create($this->data('customer'));
  $seller=User::create(['name'=>'Seller','email'=>'seller@test.local','password'=>'Password!123','role'=>'admin','active'=>true]);
  foreach([$customer,$seller] as $actor){$this->actingAs($actor);$this->get('/manager/accounts/create')->assertForbidden();$this->post('/manager/accounts',$this->data('seller'))->assertForbidden();$this->get('/manager/accounts/'.$customer->id.'/edit')->assertForbidden();$this->put('/manager/accounts/'.$customer->id,$this->data('customer'))->assertForbidden();$this->delete('/manager/accounts/'.$customer->id)->assertForbidden();}
  $this->actingAs($manager)->delete('/manager/accounts/'.$manager->id)->assertNotFound();
  $this->put('/manager/accounts/'.$manager->id,$this->data('seller'))->assertNotFound();
  $this->post('/manager/accounts',$this->data('manager'))->assertSessionHasErrors('type');
  $this->put('/manager/accounts/'.$customer->id,$this->data('seller'))->assertSessionHasErrors('type');
 }
}
