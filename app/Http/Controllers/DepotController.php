<?php
namespace App\Http\Controllers;

use App\Models\{User,Member,Tank,Sale,SaleItem,CreditEntry,StockMovement};
use App\Services\CheckoutService;
use App\Services\CustomerAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DepotController extends Controller
{
    public function loginForm(Request $r) { return view('auth.login',['portal'=>$r->routeIs('manager.*')?'manager':($r->routeIs('seller.*')?'seller':'customer')]); }
    public function login(Request $r) {
        $data = $r->validate(['email'=>'required|email','password'=>'required|string']);
        // The endpoint determines the role; never accept a role from form input.
        $role=$r->routeIs('manager.*')?'manager':($r->routeIs('seller.*')?'admin':'customer');
        if (!Auth::attempt([...$data, 'active'=>true, 'role'=>$role])) return back()->withErrors(['email'=>'อีเมลหรือรหัสผ่านไม่ถูกต้อง บัญชีถูกปิดใช้งาน หรือเลือกช่องเข้าสู่ระบบไม่ตรงกับบัญชี'])->onlyInput('email');
        if(!in_array($r->user()->role,['admin','manager','customer'],true)||($r->user()->role==='customer'&&!$r->user()->member?->active)) {
            Auth::logout();return back()->withErrors(['email'=>'บัญชีนี้ไม่พร้อมใช้งาน กรุณาติดต่อผู้ดูแล'])->onlyInput('email');
        }
        $r->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }
    public function logout(Request $r) {
        $loginRoute=match($r->user()?->role){'manager'=>'manager.login','admin'=>'seller.login',default=>'login'};
        Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken();
        return redirect()->route($loginRoute);
    }
    public function dashboard() {
        if(auth()->user()->role==='customer') {
            $member=auth()->user()->member;
            return view('customer.dashboard',[
                'member'=>$member,'units'=>SaleItem::whereHas('sale',fn($q)=>$q->where('member_id',$member->id))->sum('quantity'),
                'bills'=>$member->sales()->count(),'spent'=>$member->sales()->sum('total'),
                'recent'=>$member->sales()->latest()->limit(5)->get(),
                'featured'=>Tank::where('active',true)->where('stock','>',0)->orderBy('id')->limit(3)->get(),
            ]);
        }
        $today = Sale::whereDate('created_at', today());
        $revenue = (clone $today)->sum('total'); $bills = (clone $today)->count();
        $units = SaleItem::whereHas('sale', fn ($q)=>$q->whereDate('created_at', today()))->sum('quantity');
        $recent = Sale::latest()->limit(5)->get();
        $low = Tank::where('active',true)->whereColumn('stock','<=','low_stock')->orderBy('stock')->limit(5)->get();
        $best = SaleItem::select('tank_id','tank_name',DB::raw('SUM(quantity) as units'))->groupBy('tank_id','tank_name')->orderByDesc('units')->limit(4)->get();
        $days = collect(range(6,0))->map(function ($i) {
            $date = today()->subDays($i);
            return ['label'=>$date->format('d/m'),'value'=>(int) Sale::whereDate('created_at',$date)->sum('total')];
        });
        return view('dashboard', compact('revenue','bills','units','recent','low','best','days')+['stock'=>Tank::where('active',true)->sum('stock'),'members'=>Member::where('active',true)->count()]);
    }
    public function pos(Request $r) {
        $tanks = $this->tankQuery($r)->where('active',true)->orderBy('country')->orderBy('name')->get();
        $members = Member::where('active',true)->when($r->user()->role==='customer',fn($q)=>$q->whereKey($r->user()->member_id))->orderBy('name')->get();
        return view('pos',compact('tanks','members')+['token'=>Str::uuid()->toString()]);
    }
    public function checkout(Request $r, CheckoutService $service) {
        $data = $r->validate([
            'member_id'=>'required|integer|exists:members,id','checkout_token'=>'required|uuid',
            'discount'=>'required|integer|min:0|max:100000000',
            'items'=>'required|array|min:1|max:100','items.*.tank_id'=>'required|integer|distinct|exists:tanks,id',
            'items.*.quantity'=>'required|integer|min:1|max:99',
        ]);
        $sale = $service->checkout($r->user(), $data);
        return redirect()->route('sales.show',$sale)->with('success','ชำระสำเร็จ รถถังถูกเพิ่มเข้าโรงรถของผู้เล่นแล้ว');
    }
    private function tankQuery(Request $r) {
        $r->validate(['q'=>'nullable|string|max:100','country'=>['nullable',Rule::in(array_keys(Tank::COUNTRIES))],'category'=>['nullable',Rule::in(array_keys(Tank::CATEGORIES))]]);
        return Tank::query()->when($r->q,fn($q)=>$q->where(fn($q)=>$q->where('name','like','%'.$r->q.'%')->orWhere('sku','like','%'.$r->q.'%')))
            ->when($r->country,fn($q)=>$q->where('country',$r->country))->when($r->category,fn($q)=>$q->where('category',$r->category));
    }
    public function tanks(Request $r) { return view('tanks.index',['tanks'=>$this->tankQuery($r)->when($r->user()->role==='customer',fn($q)=>$q->where('active',true))->orderBy('country')->orderBy('name')->paginate(12)->withQueryString()]); }
    public function tank(Tank $tank) {
        abort_if(auth()->user()->role==='customer'&&!$tank->active,404);
        return view('tanks.show',['tank'=>$tank,'movements'=>auth()->user()->managesStore()?$tank->movements()->with('user')->latest()->paginate(10):null]);
    }
    public function tankForm(?Tank $tank = null) { return view('tanks.form',['tank'=>$tank ?? new Tank(['active'=>true,'price'=>1000,'low_stock'=>3])]); }
    public function tankSave(Request $r, ?Tank $tank = null) {
        $data = $r->validate([
            'sku'=>['required','regex:/^[A-Z0-9-]+$/','max:40',Rule::unique('tanks')->ignore($tank?->id)],
            'name'=>'required|string|max:120','country'=>['required',Rule::in(array_keys(Tank::COUNTRIES))],
            'category'=>['required',Rule::in(array_keys(Tank::CATEGORIES))], 'year'=>'nullable|integer|min:1900|max:1945',
            'description'=>'required|string|max:5000','history_note'=>'nullable|string|max:2000',
            'source_title'=>'nullable|string|max:255','source_url'=>'nullable|url:http,https|max:1000',
            'image_file'=>['required','regex:/^[a-z0-9][a-z0-9_-]*\.(jpg|jpeg|png|webp)$/','max:100',Rule::unique('tanks')->ignore($tank?->id)],
            'price'=>'required|integer|min:1|max:100000000','low_stock'=>'required|integer|min:0|max:100000','active'=>'required|boolean',
        ]);
        if ($tank?->exists) $tank->update($data); else $tank = Tank::create($data);
        return redirect()->route('tanks.show',$tank)->with('success','บันทึกข้อมูลรถถังแล้ว');
    }
    public function stock(Request $r,Tank $tank) {
        $data = $r->validate(['quantity'=>'required|integer|not_in:0|min:-100000|max:100000','note'=>'required|string|max:200']);
        DB::transaction(function () use($r,$tank,$data) {
            $locked = Tank::whereKey($tank->id)->lockForUpdate()->firstOrFail();
            $balance = $locked->stock + (int)$data['quantity'];
            if ($balance < 0 || $balance > 1000000) throw ValidationException::withMessages(['quantity'=>'ยอดคงเหลือต้องอยู่ระหว่าง 0 ถึง 1,000,000']);
            $locked->update(['stock'=>$balance]);
            StockMovement::create(['tank_id'=>$locked->id,'user_id'=>$r->user()->id,'quantity'=>$data['quantity'],'balance_after'=>$balance,'note'=>$data['note']]);
        },3);
        return back()->with('success','ปรับคลังและบันทึกประวัติแล้ว');
    }
    public function members(Request $r) {
        $r->validate(['q'=>'nullable|string|max:100']);
        $members = Member::when($r->q,fn($q)=>$q->where(fn($q)=>$q->where('name','like','%'.$r->q.'%')->orWhere('player_code','like','%'.$r->q.'%')->orWhere('phone','like','%'.$r->q.'%')))->latest()->paginate(15)->withQueryString();
        return view('members.index',compact('members'));
    }
    public function memberForm(?Member $member=null) { return view('members.form',['member'=>$member ?? new Member(['active'=>true])]); }
    public function memberSave(Request $r, ?Member $member=null) {
        $data = $r->validate(['player_code'=>['required','regex:/^[A-Za-z0-9_-]+$/','max:40',Rule::unique('members')->ignore($member?->id)],'name'=>'required|string|max:100','email'=>'nullable|email|max:200','phone'=>'nullable|string|max:30','active'=>'required|boolean']);
        if ($member?->exists) {
            // Keep login identity separate; linked account edits go through account management.
            $account=User::where('member_id',$member->id)->first();
            if($account) {
                $r->validate(['email'=>['required','email',Rule::unique('users')->ignore($account->id)]]);
                app(CustomerAccountService::class)->update($account,$data);
                $member->update(['player_code'=>$data['player_code']]);
            } else $member->update($data);
        } else $member=Member::create($data);
        return redirect()->route('members.show',$member)->with('success','บันทึกสมาชิกแล้ว');
    }
    public function member(Member $member) {
        return view('members.show',['member'=>$member,'sales'=>$member->sales()->latest()->paginate(10),'garage'=>SaleItem::with('tank')->whereHas('sale',fn($q)=>$q->where('member_id',$member->id))->get()->groupBy('tank_id'),'entries'=>$member->entries()->with('user')->latest()->limit(20)->get()]);
    }
    public function topup(Request $r, Member $member) {
        $data=$r->validate(['amount'=>'required|integer|min:1|max:10000000','note'=>'required|string|max:200']);
        DB::transaction(function () use($r,$member,$data) {
            $m=Member::whereKey($member->id)->lockForUpdate()->firstOrFail();
            if (!$m->active || $m->credits+(int)$data['amount']>1000000000) throw ValidationException::withMessages(['amount'=>'สมาชิกต้องเปิดใช้งาน และยอดสะสมต้องไม่เกิน 1,000,000,000 CR']);
            $m->increment('credits',(int)$data['amount']);
            CreditEntry::create(['member_id'=>$m->id,'user_id'=>$r->user()->id,'amount'=>$data['amount'],'balance_after'=>$m->credits,'note'=>$data['note']]);
        },3);
        return back()->with('success','เติมเครดิตจำลองเรียบร้อย');
    }
    public function registerForm() { return view('auth.register'); }
    public function register(Request $r,CustomerAccountService $accounts) {
        $r->merge(['email'=>strtolower(trim((string)$r->input('email')))]);
        $data=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:200|unique:users,email',
            'phone'=>'nullable|string|max:30','password'=>'required|string|min:10|max:100|confirmed']);
        $user=$accounts->create($data);Auth::login($user);$r->session()->regenerate();
        return redirect()->route('dashboard')->with('success','สมัครสมาชิกสำเร็จ ติดต่อผู้ดูแลเพื่อเติมเครดิตและเริ่มสะสมรถถัง');
    }
    public function accountDirectory(Request $r) {
        abort_unless($r->user()->role==='manager',403);
        $data=$r->validate(['q'=>'nullable|string|max:100','type'=>['nullable',Rule::in(['customer','seller'])]]);
        $query=User::with('member')->whereIn('role',['customer','admin']);
        if(!empty($data['type']))$query->where('role',$data['type']==='seller'?'admin':'customer');
        if(!empty($data['q']))$query->where(fn($q)=>$q->where('name','like','%'.$data['q'].'%')->orWhere('email','like','%'.$data['q'].'%'));
        return view('manager.accounts',['accounts'=>$query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString()]);
    }
    public function customers(Request $r) {
        $r->validate(['q'=>'nullable|string|max:100']);
        return view('customers.index',['customers'=>User::with('member')->where('role','customer')
            ->when($r->q,fn($q)=>$q->where(fn($q)=>$q->where('name','like','%'.$r->q.'%')->orWhere('email','like','%'.$r->q.'%')))
            ->latest()->paginate(15)->withQueryString()]);
    }
    public function customerForm(?User $customer=null) {
        abort_if($customer?->exists&&$customer->role!=='customer',404);
        return view('customers.form',['customer'=>$customer??new User(['active'=>true])]);
    }
    public function customerSave(Request $r,CustomerAccountService $accounts,?User $customer=null) {
        abort_if($customer?->exists&&$customer->role!=='customer',404);
        $r->merge(['email'=>strtolower(trim((string)$r->input('email')))]);
        $data=$r->validate(['name'=>'required|string|max:100','email'=>['required','email','max:200',Rule::unique('users')->ignore($customer?->id)],
            'phone'=>'nullable|string|max:30','password'=>[$customer?->exists?'nullable':'required','string','min:10','max:100','confirmed'],'active'=>'required|boolean']);
        if($customer?->exists)$accounts->update($customer,$data);else $accounts->create($data);
        return redirect()->route('customers.index')->with('success','บันทึกบัญชีลูกค้าแล้ว');
    }
    public function garage(Request $r) {
        abort_unless($r->user()->role==='customer',403);
        return $this->member($r->user()->member);
    }
    public function profile() { return view('customer.profile',['account'=>auth()->user()]); }
    public function profileSave(Request $r,CustomerAccountService $accounts) {
        $r->merge(['email'=>strtolower(trim((string)$r->input('email')))]);
        $data=$r->validate(['name'=>'required|string|max:100','email'=>['required','email','max:200',Rule::unique('users')->ignore($r->user()->id)],
            'phone'=>'nullable|string|max:30','password'=>'nullable|string|min:10|max:100|confirmed',
            'current_password'=>'required_with:password|nullable|current_password']);
        $accounts->update($r->user(),$data);
        return back()->with('success','บันทึกข้อมูลบัญชีแล้ว');
    }
    private function period(Request $r): array {
        $r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from=$r->input('from') ?: today()->startOfMonth()->format('Y-m-d');
        $to=$r->input('to') ?: today()->format('Y-m-d');
        if ($from>$to) throw ValidationException::withMessages(['from'=>'วันเริ่มต้องไม่เกินวันสิ้นสุด']);
        return [$from,$to];
    }
    public function sales(Request $r) {
        [$from,$to]=$this->period($r);
        $sales=Sale::whereBetween('created_at',[$from.' 00:00:00',$to.' 23:59:59'])
            ->when($r->user()->role==='customer',fn($q)=>$q->where('member_id',$r->user()->member_id))->latest()->paginate(20)->withQueryString();
        return view('sales.index',compact('sales','from','to'));
    }
    public function receipt(Sale $sale) {
        abort_unless(auth()->user()->managesStore()||$sale->member_id===auth()->user()->member_id,404);
        return view('sales.show',['sale'=>$sale->load('items')]);
    }
    public function credits() { return view('credits',['entries'=>CreditEntry::with('member','user','sale')
        ->when(auth()->user()->role==='customer',fn($q)=>$q->where('member_id',auth()->user()->member_id))->latest()->paginate(25)]); }
    public function reports(Request $r) {
        [$from,$to]=$this->period($r); $range=[$from.' 00:00:00',$to.' 23:59:59'];
        $query=Sale::whereBetween('created_at',$range);
        $summary=['revenue'=>(clone $query)->sum('total'),'bills'=>(clone $query)->count(),'discount'=>(clone $query)->sum('discount')];
        $ranking=SaleItem::whereHas('sale',fn($q)=>$q->whereBetween('created_at',$range))->select('sku','tank_name',DB::raw('SUM(quantity) as units'),DB::raw('SUM(line_total) as gross'))->groupBy('sku','tank_name')->orderByDesc('units')->get();
        $daily=(clone $query)->selectRaw('DATE(created_at) as day, COUNT(*) as bills, SUM(total) as total')->groupBy('day')->orderBy('day')->get();
        return view('reports',compact('from','to','summary','ranking','daily'));
    }
    public function export(Request $r) {
        [$from,$to]=$this->period($r);
        return response()->streamDownload(function () use($from,$to) {
            $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['เลขที่ใบเสร็จ','วันที่','รหัสผู้เล่น','สมาชิก','ผู้ทำรายการ','ยอดสินค้า CR','ส่วนลด CR','สุทธิ CR']);
            foreach (Sale::whereBetween('created_at',[$from.' 00:00:00',$to.' 23:59:59'])->orderBy('id')->cursor() as $s) {
                $row=[$s->number,$s->created_at->format('Y-m-d H:i:s'),$s->player_code,$s->member_name,$s->employee_name,$s->subtotal,$s->discount,$s->total];
                fputcsv($out,array_map(fn($v)=>is_string($v)&&preg_match('/^[\s]*[=+@-]/u',$v)?"'".$v:$v,$row));
            }
            fclose($out);
        },'tank-depot-'.$from.'-'.$to.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function guide() {
        return view(auth()->user()->managesStore()?'guide':'customer.guide',['tanks'=>Tank::where('active',true)->orderBy('country')->orderBy('name')->get()]);
    }
}
