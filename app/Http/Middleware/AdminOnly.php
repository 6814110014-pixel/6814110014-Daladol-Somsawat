<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class AdminOnly {
    public function handle(Request $request, Closure $next) {
        abort_unless($request->user()?->managesStore(), 403, 'เฉพาะผู้ขายหรือผู้จัดการร้าน');
        return $next($request);
    }
}
