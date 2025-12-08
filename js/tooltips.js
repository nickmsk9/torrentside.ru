this.tooltip = function(){
   xOffset = 6;
   yOffset = 16;
   jQuery("[title],[alt]").hover(function(e){
   if(this.title !=''){
      this.t = this.title;
      this.title = "";
   } else {
      this.t = this.alt;
      this.alt = "";
 }
 jQuery("body").append("<p id='tooltip'>"+ this.t +"</p>");
      jQuery("#tooltip")
         .css("top",(e.pageY - xOffset) + "px")
         .css("left",(e.pageX + yOffset) + "px")
         .fadeIn("fast")
         .show();
   },
   function(){
      this.title = this.t;
      this.alt = this.t;
      jQuery("#tooltip").remove();
   });
   jQuery("[title],[alt]").mousemove(function(e){
      jQuery("#tooltip")
         .css("top",(e.pageY - xOffset) + "px")
         .css("left",(e.pageX + yOffset) + "px");
   });
};

jQuery(document).ready(function(){
   tooltip();
});


(function() {
jQuery.keyboardLayout = {};
jQuery.keyboardLayout.indicator = $('<span class="keyboardLayout" />');
jQuery.keyboardLayout.target;
jQuery.keyboardLayout.layout;
jQuery.keyboardLayout.show = function(layout){
this.layout = layout;
this.indicator.text(layout);
this.target.after(this.indicator);
};
jQuery.keyboardLayout.hide = function(){
this.target = null;
this.layout = null;
this.indicator.remove();
};

jQuery.fn.keyboardLayout = function()  {
this.each(function(){

$(this).focus(function(){
jQuery.keyboardLayout.target = $(this);
});

$(this).blur(function(){
jQuery.keyboardLayout.hide();
});

$(this).keypress(function(e){
var c = (e.charCode == undefined ? e.keyCode : e.charCode);
var layout = jQuery.keyboardLayout.layout;

if (c >= 97/*a*/  && c <= 122/*z*/ && !e.shiftKey || c >= 65/*A*/  && c <= 90/*Z*/  &&  e.shiftKey || (c == 91/*[*/  && !e.shiftKey || c == 93/*]*/  && !e.shiftKey || c == 123/*{*/ &&  e.shiftKey || c == 125/*}*/ &&  e.shiftKey || c == 96/*`*/  && !e.shiftKey || c == 126/*~*/ &&  e.shiftKey || c == 64/*@*/  &&  e.shiftKey || c == 35/*#*/  &&  e.shiftKey || c == 36/*$*/  &&  e.shiftKey || c == 94/*^*/ && e.shiftKey || c == 38/*&*/  &&  e.shiftKey || c == 59/*;*/  && !e.shiftKey || c == 39/*'*/ && !e.shiftKey || c == 44/*,*/  && !e.shiftKey || c == 60/*<*/  &&  e.shiftKey || c == 62/*>*/  &&  e.shiftKey) && layout != 'EN') {
layout = 'en'; //Tesla TT
} else if (c >= 65/*A*/ && c <= 90/*Z*/  && !e.shiftKey || c >= 97/*a*/ && c <= 122/*z*/ &&  e.shiftKey) {
layout = 'EN';
} else if (c >= 1072/*¦-*/ && c <= 1103/*TÏ*/ && !e.shiftKey || c >= 1040/*¦Ð*/ && c <= 1071/*¦ï*/ &&  e.shiftKey ||
(c == 1105/*TÑ*/ && !e.shiftKey || c == 1025/*¦Á*/ &&  e.shiftKey || /*Tesla TT*/ c == 8470/*òÄÖ*/ &&  e.shiftKey || c == 59/*;*/  &&  e.shiftKey || c == 44/*,*/   &&  e.shiftKey) && layout != 'RU') {
layout = 'ru';
} else if (c >= 1040/*¦Ð*/ && c <= 1071/*¦ï*/ && !e.shiftKey || c >= 1072/*¦-*/ && c <= 1103/*TÏ*/ &&  e.shiftKey) {
layout = 'RU';
}
 if (layout) {
jQuery.keyboardLayout.show(layout);
}
});});};})();


$(function(){
$(':text').keyboardLayout();
$(':password').keyboardLayout();
});


$(document).ready(function() {
$.get("md5.php",function(hash) {
$("#o_O").append('<input type="hidden" name="tica" value="'+hash+'" />');
});
});


var sphour;
var spminute;
var spsecond;
var chour = 06;
var cminute = 06;
var csecond = 06;
var rels, widget, widgetnum;
jQuery(function(){
sphour = jQuery("#chour");
spminute = jQuery("#cminute");
spsecond = jQuery("#csecond");
sphour.text(makedigit(chour));
spminute.text(makedigit(cminute));
spsecond.text(makedigit(csecond));
setInterval ("updateClock()", 995);
widget = jQuery(".widget");
widgetnum = jQuery(".widget").length;
for(i=0;i<widgetnum;i++) {
tmp = widget[i].id.substr(0,3);
updateRel[i] = new Array();
updateRel[i][0] = true;
updateRel[i][1] = 1;
updateRel[i][2] = tmp;
}
rels = jQuery(".relprew");
setInterval ( "updateRels()", 10000 );
jQuery(".forclick").click(function(e){userNewsClicked(e);});
rels.hover(
function (e) {
showRelTitle(e);
},
function () {
}
);
});

var updateRel = new Array();

function showRelTitle(e){
clickedId = e.target.id;
clickedId = clickedId.substr(0, 3);
jQuery("#"+clickedId+"_tit").text(jQuery(e.target).attr("title"));
}
function showRealeseDo(pref, id){
jQuery("#"+pref+"_tit").text("");
changeContent(jQuery("#"+pref+"_con"), jQuery("#"+pref+"_"+id));
jQuery(".relprew").hover(
function (e) {
showRelTitle(e);
},
function () {
}
);
}
function showRealese(pref, id){

for(i=0;i<widgetnum;i++) {
if(updateRel[i][2]==pref) {
updateRel[i][0] = false;
}
}
showRealeseDo(pref, id);
}

var currentNewsCont = 1;
var animateNews = true;
function updateRels() {
for(i=0;i<widgetnum;i++) {
if(updateRel[i][0]) {
if(updateRel[i][1]==4)
next = 1
else
next = updateRel[i][1] + 1;
showRealeseDo(updateRel[i][2],next);
updateRel[i][1] = next;
}
}
}
function changeContent(cont, text){
cont.animate({opacity: 0}, 100).html(text.html()).animate({opacity: 1}, 500);
}
function changeSimple(cont, text){
cont.html(text.html());
}
function updateClock() {
csecond += 1;
if(csecond == 60) {
csecond = 0;
cminute += 1;
if(cminute == 60) {
cminute = 0;
chour += 1;
if(chour==24) {
chour = 0;
}
}
}
sphour.text(makedigit(chour));
spminute.text(makedigit(cminute));
spsecond.text(makedigit(csecond));
}
function makedigit(number){
if(number < 10)
return "0"+number;
else
return number;
}