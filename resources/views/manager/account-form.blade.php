@extends('layouts.app')
@section('title',$account->exists?'แก้ไขบัญชี':'เพิ่มบัญชี')
@section('content')
<a class="back-link" href="{{ route('manager.accounts') }}">← ลูกค้า & พนักงานขาย</a>
<div class="page-heading"><div><span class="eyebrow">ACCOUNT MANAGEMENT</span><h1>{{ $account->exists?'แก้ไขบัญชี':'เพิ่มบัญชีลูกค้า / พนักงานขาย' }}</h1><p>ลูกค้าใหม่จะมีเครดิตเริ่มต้น 0 CR ส่วนพนักงานขายใช้ช่องเข้าสู่ระบบผู้ขาย</p></div></div>
<form class="panel panel-body form-layout" method="post" action="{{ $account->exists?route('manager.accounts.update',$account):route('manager.accounts.store') }}">
@csrf @if($account->exists)@method('put')@endif
<div class="form-grid">
@if($account->exists)<label>ประเภทบัญชี<input value="{{ $account->role==='admin'?'พนักงานขาย':'ลูกค้า' }}" readonly></label>@else<label>ประเภทบัญชี<select name="type" required><option value="customer" @selected(old('type')==='customer')>ลูกค้า</option><option value="seller" @selected(old('type')==='seller')>พนักงานขาย</option></select></label>@endif
<label>ชื่อ<input name="name" required maxlength="100" value="{{ old('name',$account->name) }}"></label>
<label>อีเมลเข้าสู่ระบบ<input name="email" type="email" required maxlength="200" value="{{ old('email',$account->email) }}"></label>
@if(!$account->exists || $account->role==='customer')<label>เบอร์โทรลูกค้า (ไม่บังคับ)<input name="phone" maxlength="30" value="{{ old('phone',$account->member?->phone) }}"></label>@endif
<label>สถานะ<select name="active"><option value="1" @selected(old('active',$account->active)==1)>ใช้งาน</option><option value="0" @selected(old('active',$account->active)==0)>ปิดใช้งาน</option></select></label>
<label>{{ $account->exists?'รหัสผ่านใหม่ (เว้นว่างหากไม่เปลี่ยน)':'รหัสผ่าน' }}<input type="password" name="password" minlength="10" maxlength="100" autocomplete="new-password" @required(!$account->exists)></label>
<label>ยืนยันรหัสผ่าน<input type="password" name="password_confirmation" minlength="10" maxlength="100" autocomplete="new-password" @required(!$account->exists)></label>
</div><div class="form-actions"><a class="btn secondary" href="{{ route('manager.accounts') }}">ยกเลิก</a><button class="btn primary">บันทึกบัญชี</button></div></form>
@endsection
