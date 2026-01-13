document.addEventListener('DOMContentLoaded',function(){
  var el=document.getElementById('phone');
  if(!el) return;
  el.addEventListener('input',function(){
    var v=el.value.replace(/\D/g,'');
    if(v.length<=10){
      el.value='('+(v.slice(0,2))+')'+(v.slice(2,6))+'-'+(v.slice(6,10));
    }else{
      el.value='('+(v.slice(0,2))+')'+(v.slice(2,7))+'-'+(v.slice(7,11));
    }
  });
});