<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActiveAccount {
    public function handle(Request $request,Closure $next) {
        $user=$request->user();
        if($user?->role==='customer')$user->load('member');
        if(!$user?->active || !in_array($user->role,['admin','manager','customer'],true)
            || ($user->role==='customer' && !$user->member?->active)) {
            Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email'=>'บัญชีนี้ไม่พร้อมใช้งาน กรุณาติดต่อผู้ดูแล']);
        }
        return $next($request);
    }
}
