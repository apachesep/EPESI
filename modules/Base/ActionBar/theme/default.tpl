<div class="pull-left">
    {foreach item=i from=$icons}
        {$i.open}
        <div class="btn btn-default" helpID="{$i.helpID}">
            <i class="fa fa-{$i.icon} fa-3x"></i>
            <div>{$i.label}</div>
        </div>
        {$i.close}
    {/foreach}
</div>

<div class="pull-right">
{foreach item=i from=$launcher}
    {$i.open}
    <div class="btn btn-default">
        <div class="div_icon"><img src="{$i.icon}" alt="" align="middle" border="0" width="32" height="32"></div>
        <div style="white-space: normal">{$i.label}</div>
    </div>
    {$i.close}
{/foreach}
</div>