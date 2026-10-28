<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\CustomerAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManagerAccountController extends Controller {
    private function authorizeAccount(?User $account=null): void {
        abort_unless(auth()->user()->role==='manager',403);
        if($account)abort_unless(in_array($account->role,['admin','customer'],true),404);
    }
    public function create() {
        $this->authorizeAccount();
        return view('manager.account-form',['account'=>new User(['active'=>true])]);
    }
    public function edit(User $account) {
        $this->authorizeAccount($account);
        return view('manager.account-form',compact('account'));
    }
    public function save(Request $r,CustomerAccountService $service,?User $account=null) {
        $this->authorizeAccount($account);
        $r->merge(['email'=>strtolower(trim((string)$r->input('email')))]);
        $data=$r->validate([
            'name'=>'required|string|max:100','email'=>['required','email','max:200',Rule::unique('users')->ignore($account?->id)],
            'type'=>$account?->exists?['sometimes',Rule::in([$account->role==='admin'?'seller':'customer'])]:['required',Rule::in(['seller','customer'])],
            'phone'=>'nullable|string|max:30','active'=>'required|boolean',
            'password'=>[$account?->exists?'nullable':'required','string','min:10','max:100','confirmed'],
        ]);
        if($account?->exists) {
            $service->update($account,$data);
        } elseif($data['type']==='customer') {
            $service->create($data);
        } else {
            User::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>$data['password'],'active'=>$data['active'],'role'=>'admin']);
        }
        return redirect()->route('manager.accounts')->with('success','บันทึกบัญชีเรียบร้อยแล้ว');
    }
    public function destroy(User $account) {
        $this->authorizeAccount($account);
        DB::transaction(function()use($account){
            // Preserve member balances and transaction references for historical reports.
            if($account->member_id)$account->member()->update(['active'=>false]);
            $account->update(['active'=>false]);
            $account->delete();
        });
        return redirect()->route('manager.accounts')->with('success','ลบบัญชีแล้ว บัญชีนี้เข้าสู่ระบบไม่ได้ และประวัติธุรกรรมยังคงอยู่');
    }
}
