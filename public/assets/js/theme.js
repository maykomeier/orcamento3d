document.addEventListener('DOMContentLoaded',function(){
  var key='theme';
  var saved=localStorage.getItem(key)||'light';
  document.documentElement.setAttribute('data-theme',saved);
  var btn=document.getElementById('theme-toggle');
  if(btn){
    btn.addEventListener('click',function(){
      var cur=document.documentElement.getAttribute('data-theme')||'dark';
      var next=cur==='dark'?'light':'dark';
      document.documentElement.setAttribute('data-theme',next);
      localStorage.setItem(key,next);
      var use=document.getElementById('theme-toggle-icon');
      if(use){ use.setAttribute('href','/public/assets/icons.svg#'+(next==='dark'?'moon':'sun')); }
    });
    var use=document.getElementById('theme-toggle-icon');
    if(use){ use.setAttribute('href','/public/assets/icons.svg#'+(saved==='dark'?'moon':'sun')); }
  }
});