{if $restoreClass}
<div style="
    display: inline-block;
    padding: 8px 15px;
    margin: 10px auto;
    background: linear-gradient(to right, #5b5bff, #9e72ff);
    border-radius: 10px;
    font-size: 14px;
    font-family: Verdana, sans-serif;
    font-weight: bold;
    color: #fff;
    box-shadow: 1px 1px 3px rgba(0,0,0,0.2);
">
  <a href="{$restoreClass.url|escape:'html'}" style="color: #fff; text-decoration: none;">
    {$restoreClass.label|escape:'html'}
  </a>
</div>
{/if}
