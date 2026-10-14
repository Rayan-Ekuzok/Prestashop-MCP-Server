{*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to obtain
 * it through the world-wide-web, please send an email to
 * contact@novalo.fr so we can send you a copy immediately.
 *
 * @author    Novalo <contact@novalo.fr>
 * @copyright 2026 Novalo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 *}

{* Shown only where a level can inherit from another: a single shop or a group.
   In the all-shops context there is nothing above to inherit from. *}
{if $somcp_inheritable}
    {assign var=state value=$somcp_inherit[$field]}
    <div class="somcp-inherit">
        <label>
            <input type="checkbox" name="somcp_own_{$field|escape:'html':'UTF-8'}" value="1"
                   data-somcp-own="{$field|escape:'html':'UTF-8'}"{if $state.own} checked{/if}>
            {l s='Value of its own here' d='Modules.Somcp.Admin'}
        </label>
        {if !$state.own}
            <span class="text-muted">
                {if $state.source === 'group'}{l s='inherited from the group' d='Modules.Somcp.Admin'}
                {elseif $state.source === 'global'}{l s='inherited from all shops' d='Modules.Somcp.Admin'}
                {else}{l s='module default' d='Modules.Somcp.Admin'}{/if}
            </span>
        {/if}
    </div>
{/if}
