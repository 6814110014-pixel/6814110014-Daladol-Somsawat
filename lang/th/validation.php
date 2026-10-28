<?php
return [
    'required'=>'กรุณาระบุ :attribute','email'=>':attribute ต้องเป็นอีเมลที่ถูกต้อง','integer'=>':attribute ต้องเป็นจำนวนเต็ม',
    'min'=>['numeric'=>':attribute ต้องไม่น้อยกว่า :min','string'=>':attribute ต้องมีอย่างน้อย :min ตัวอักษร','array'=>':attribute ต้องมีอย่างน้อย :min รายการ'],
    'max'=>['numeric'=>':attribute ต้องไม่เกิน :max','string'=>':attribute ต้องไม่เกิน :max ตัวอักษร','array'=>':attribute ต้องไม่เกิน :max รายการ'],
    'unique'=>':attribute นี้มีอยู่ในระบบแล้ว','exists'=>'ไม่พบ :attribute ที่เลือก','confirmed'=>'ยืนยัน :attribute ไม่ตรงกัน',
    'in'=>':attribute ไม่อยู่ในตัวเลือกที่รองรับ','boolean'=>':attribute ไม่ถูกต้อง','regex'=>'รูปแบบ :attribute ไม่ถูกต้อง',
    'uuid'=>'รหัสรายการไม่ถูกต้อง กรุณาเปิดหน้าขายใหม่','distinct'=>'รายการ :attribute ซ้ำกัน','url'=>'ลิงก์ :attribute ต้องเป็น http หรือ https',
    'date_format'=>'รูปแบบ :attribute ต้องเป็น :format','after_or_equal'=>':attribute ต้องไม่น้อยกว่า :date','not_in'=>':attribute มีค่าที่ไม่อนุญาต',
    'attributes'=>['name'=>'ชื่อ','email'=>'อีเมล','password'=>'รหัสผ่าน','member_id'=>'สมาชิก','items'=>'สินค้าในตะกร้า','discount'=>'ส่วนลด','quantity'=>'จำนวน','amount'=>'เครดิต','note'=>'เหตุผล','player_code'=>'รหัสผู้เล่น','sku'=>'รหัสสินค้า','image_file'=>'ชื่อไฟล์ภาพ','price'=>'ราคา','year'=>'ปีอ้างอิง','description'=>'รายละเอียด','from'=>'วันเริ่มต้น','to'=>'วันสิ้นสุด','role'=>'สิทธิ์','active'=>'สถานะ','country'=>'ชาติ','category'=>'ประเภท'],
];
