document.querySelector('.mobile-menu')?.addEventListener('click',function(){const open=document.getElementById('sidebar').classList.toggle('open');this.setAttribute('aria-expanded',String(open));});
document.addEventListener('click',event=>{const sidebar=document.getElementById('sidebar');if(sidebar?.classList.contains('open')&&!sidebar.contains(event.target)&&!event.target.closest('.mobile-menu')){sidebar.classList.remove('open');document.querySelector('.mobile-menu')?.setAttribute('aria-expanded','false');}});
document.querySelectorAll('[data-print]').forEach(button=>button.addEventListener('click',()=>window.print()));
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!window.confirm(form.dataset.confirm))e.preventDefault();}));

const posData=document.getElementById('pos-data');
if(posData){
    const data=JSON.parse(posData.textContent), storageKey=`tank-depot-cart-${data.user}`;
    const stock=new Map(data.tanks.map(tank=>[Number(tank.id),tank]));
    const form=document.getElementById('checkout-form'), itemsEl=document.getElementById('cart-items'),member=document.getElementById('member-select'),discount=document.getElementById('discount'),token=document.getElementById('checkout-token'),pay=document.getElementById('pay-button');
    let saved={};try{saved=JSON.parse(sessionStorage.getItem(storageKey)||'{}');}catch{}
    let cart=new Map();
    const oldItems=Object.values(data.old||{});
    if(oldItems.length){for(const item of oldItems){const tank=stock.get(Number(item.tank_id));if(tank)cart.set(tank.id,{...tank,quantity:Number(item.quantity)});}member.value=data.member||'';discount.value=data.discount||0;if(data.oldToken)token.value=data.oldToken;}
    else {for(const item of saved.items||[]){if(!Number.isInteger(item.id)||!Number.isInteger(item.quantity)||item.quantity<1)continue;const latest=stock.get(item.id);cart.set(item.id,{...item,...latest,quantity:Math.min(item.quantity,99)});}member.value=saved.member||'';discount.value=saved.discount||0;if(saved.token)token.value=saved.token;}
    if(data.customer){member.value=String(data.member);discount.value=0;}
    const fmt=value=>new Intl.NumberFormat('th-TH').format(value);
    function persist(){try{sessionStorage.setItem(storageKey,JSON.stringify({items:[...cart.values()],member:member.value,discount:discount.value,token:token.value}));}catch{}}
    function node(tag,cls,text){const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;}
    function render(){
        itemsEl.replaceChildren();let subtotal=0,count=0;
        if(!cart.size){const empty=node('div','empty');empty.append(node('span','','⊞'),node('h3','','ตะกร้ายังว่าง'),node('p','','เลือกรถถังจากคอลเลกชันด้านซ้าย'));itemsEl.append(empty);}
        [...cart.values()].forEach((item,index)=>{
            subtotal+=item.price*item.quantity;count+=item.quantity;
            const row=node('div','cart-item'),head=node('div','cart-item-head'),bottom=node('div','cart-item-bottom'),controls=node('div','quantity-controls');
            head.append(node('strong','',item.name));const remove=node('button','remove-item','นำออก');remove.type='button';remove.setAttribute('aria-label',`นำ ${item.name} ออก`);remove.addEventListener('click',()=>{cart.delete(item.id);render();});head.append(remove);
            const minus=node('button','','−'),plus=node('button','','+');minus.type=plus.type='button';minus.setAttribute('aria-label',`ลดจำนวน ${item.name}`);plus.setAttribute('aria-label',`เพิ่มจำนวน ${item.name}`);minus.addEventListener('click',()=>{if(item.quantity===1)cart.delete(item.id);else item.quantity--;render();});plus.disabled=item.quantity>=Math.min(item.stock,99);plus.addEventListener('click',()=>{item.quantity++;render();});controls.append(minus,node('span','',item.quantity),plus);bottom.append(controls,node('strong','',`${fmt(item.price*item.quantity)} CR`));row.append(head,bottom);
            for(const [field,value] of [['tank_id',item.id],['quantity',item.quantity]]){const hidden=document.createElement('input');hidden.type='hidden';hidden.name=`items[${index}][${field}]`;hidden.value=value;row.append(hidden);}itemsEl.append(row);
        });
        const off=Number(discount.value),validDiscount=Number.isInteger(off)&&off>=0&&off<=subtotal;
        const total=subtotal-(validDiscount?off:0),balance=Number(member.selectedOptions[0]?.dataset.credits||0);
        document.getElementById('cart-count').textContent=count;document.getElementById('subtotal').textContent=`${fmt(subtotal)} CR`;document.getElementById('total').textContent=`${fmt(total)} CR`;document.getElementById('member-balance').textContent=member.value?`${fmt(balance)} CR`:'— CR';
        const overStock=[...cart.values()].some(item=>item.quantity>item.stock);
        let message='';if(cart.size){if(!member.value)message='เลือกสมาชิกเพื่อชำระเงิน';else if(!validDiscount)message='ส่วนลดต้องเป็นจำนวนเต็มและไม่เกินยอดสินค้า';else if(overStock)message='บางรายการมีจำนวนมากกว่าคลัง กรุณาปรับจำนวน';else if(balance<total)message=`เครดิตไม่พอ ขาดอีก ${fmt(total-balance)} CR`;}
        document.getElementById('cart-message').textContent=message;pay.disabled=!cart.size||!member.value||!validDiscount||overStock||balance<total;
        document.getElementById('mobile-cart-summary').textContent=`${count} คัน · ${fmt(total)} CR`;
        document.querySelectorAll('.add-cart').forEach(button=>{const tank=stock.get(Number(button.dataset.tankId));button.disabled=!tank||tank.stock<1||(cart.get(tank.id)?.quantity||0)>=Math.min(tank.stock,99);});persist();
    }
    document.querySelectorAll('.add-cart').forEach(button=>button.addEventListener('click',()=>{const id=Number(button.dataset.tankId),tank=stock.get(id);if(!tank||tank.stock<1)return;const existing=cart.get(id);if(existing)existing.quantity++;else cart.set(id,{...tank,quantity:1});render();}));
    document.getElementById('clear-cart').addEventListener('click',()=>{cart.clear();discount.value=0;render();});member.addEventListener('change',render);discount.addEventListener('input',render);
    form.addEventListener('submit',event=>{if(pay.disabled){event.preventDefault();return;}pay.disabled=true;pay.textContent='กำลังบันทึกรายการ…';});
    window.addEventListener('pageshow',event=>{if(event.persisted)window.location.reload();});render();
}
