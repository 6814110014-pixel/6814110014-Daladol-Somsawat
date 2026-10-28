<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('member_id')->nullable()->unique()->constrained('members')->restrictOnDelete();
            $t->string('role')->default('customer')->change();
        });
        // Preserve existing accounts, credentials, receipts and member balances.
        DB::transaction(function () {
            foreach(DB::table('users')->where('role','!=','admin')->get() as $user) {
                $memberId=DB::table('members')->insertGetId([
                    'player_code'=>'C-'.Str::ulid(),'name'=>$user->name,'email'=>$user->email,
                    'credits'=>0,'active'=>$user->active,'created_at'=>now(),'updated_at'=>now(),
                ]);
                DB::table('users')->where('id',$user->id)->update(['role'=>'customer','member_id'=>$memberId]);
            }
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['member_id']);$t->dropUnique(['member_id']);$t->dropColumn('member_id');
            $t->string('role')->default('staff')->change();
        });
    }
};
