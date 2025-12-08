var azWin = '     Ё               ё       АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдежзийклмнопрстуфхцчшщъыьэюя'
var azKoi = 'ё                Ё           юабцдефгхийклмнопярстужвьызшэщчъЮАБЦДЕФГХИЙКЛМНОПЯРСТУЖВЬЫЗШЭЩЧЪ'
var AZ=azWin
var azURL = '0123456789ABCDEF'
var b64s  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'
var b64a  = b64s.split('')
function enBASE64(str) {
  var a=Array(), i
  for( i=0; i<str.length; i++ ){
    var cch=str.charCodeAt(i)
    if( cch>127 ){  cch=AZ.indexOf(str.charAt(i))+163; if(cch<163) continue; }
    a.push(cch)
  };
  var s=Array(), lPos = a.length - a.length % 3
  for(i=0;i<lPos;i+=3){
    var t=(a[i]<<16)+(a[i+1]<<8)+a[i+2]
    s.push( b64a[(t>>18)&0x3f]+b64a[(t>>12)&0x3f]+b64a[(t>>6)&0x3f]+b64a[t&0x3f] )
  }
  switch ( a.length-lPos ) {
    case 1 : var t=a[lPos]<<4; s.push(b64a[(t>>6)&0x3f]+b64a[t&0x3f]+'=='); break
    case 2 : var t=(a[lPos]<<10)+(a[lPos+1]<<2); s.push(b64a[(t>>12)&0x3f]+b64a[(t>>6)&0x3f]+b64a[t&0x3f]+'='); break
  }
  return s.join('')
}
function deBASE64(str) {
  while(str.substr(-1,1)=='=')str=str.substr(0,str.length-1);
  var b=str.split(''), i
  var s=Array(), t
  var lPos = b.length - b.length % 4
  for(i=0;i<lPos;i+=4){
    t=(b64s.indexOf(b[i])<<18)+(b64s.indexOf(b[i+1])<<12)+(b64s.indexOf(b[i+2])<<6)+b64s.indexOf(b[i+3])
    s.push( ((t>>16)&0xff), ((t>>8)&0xff), (t&0xff) )
  }
  if( (b.length-lPos) == 2 ){ t=(b64s.indexOf(b[lPos])<<18)+(b64s.indexOf(b[lPos+1])<<12); s.push( ((t>>16)&0xff)); }
  if( (b.length-lPos) == 3 ){ t=(b64s.indexOf(b[lPos])<<18)+(b64s.indexOf(b[lPos+1])<<12)+(b64s.indexOf(b[lPos+2])<<6); s.push( ((t>>16)&0xff), ((t>>8)&0xff) ); }
  for( i=s.length-1; i>=0; i-- ){
    if( s[i]>=168 ) s[i]=AZ.charAt(s[i]-163)
    else s[i]=String.fromCharCode(s[i])
  };
  return s.join('')
}
function ajaxpreview(objname) {
     var ajax = new tbdev_ajax();
     ajax.onShow ('');
     var varsString = "";
     ajax.requestFile = "preview.php?ajax";
    var txt = enBASE64(document.getElementById(objname).value);
     ajax.setVar("msg", txt);
     ajax.method = 'POST';
     ajax.element = 'preview';
     ajax.sendAJAX(varsString);

}  