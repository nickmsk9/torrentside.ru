{* $fullwidth:boolean, $padding:int *}
{assign var=width_attr value=''}
{if $fullwidth}{assign var=width_attr value=' width="100%"'}{/if}
<table class="main"{$width_attr} border="1" cellspacing="0" cellpadding="{$padding|default:5}">
