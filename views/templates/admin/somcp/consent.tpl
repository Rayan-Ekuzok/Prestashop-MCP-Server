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

{include file='./_style.tpl'}

<div class="somcp-consent">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-plug"></i> {$somcp_title|escape:'html':'UTF-8'}
        </div>

        {if $somcp_state === 'expired'}
            <div class="alert alert-warning">{$somcp_message|escape:'html':'UTF-8'}</div>
        {else}
            <p class="somcp-lead">
                {l s='An application is asking to connect to your shop on your behalf.' d='Modules.Somcp.Admin'}
            </p>

            <table class="table somcp-facts">
                <tbody>
                    <tr>
                        <th>{l s='Application' d='Modules.Somcp.Admin'}</th>
                        <td>
                            <strong>{$somcp_client_name|escape:'html':'UTF-8'}</strong>
                            {if $somcp_client_uri}
                                <br><span class="text-muted">{$somcp_client_uri|escape:'html':'UTF-8'}</span>
                            {/if}
                            <br><span class="text-muted">{$somcp_client_origin|escape:'html':'UTF-8'}</span>
                        </td>
                    </tr>
                    <tr>
                        <th>{l s='Identifier' d='Modules.Somcp.Admin'}</th>
                        <td><code>{$somcp_client_id|escape:'html':'UTF-8'}</code></td>
                    </tr>
                    <tr>
                        <th>{l s='Signed in as' d='Modules.Somcp.Admin'}</th>
                        <td>{$somcp_employee|escape:'html':'UTF-8'}</td>
                    </tr>
                    <tr>
                        <th>{l s='Shop' d='Modules.Somcp.Admin'}</th>
                        <td>{$somcp_shop|escape:'html':'UTF-8'}</td>
                    </tr>
                    <tr>
                        <th>{l s='Will be sent back to' d='Modules.Somcp.Admin'}</th>
                        <td><code>{$somcp_redirect_uri|escape:'html':'UTF-8'}</code></td>
                    </tr>
                </tbody>
            </table>

            <h4>{l s='What it will be able to do' d='Modules.Somcp.Admin'}</h4>
            <ul class="somcp-scopes">
                {foreach from=$somcp_scopes item=scope}
                    <li>
                        {$scope.label|escape:'html':'UTF-8'}
                        <span class="text-muted">({$scope.name|escape:'html':'UTF-8'})</span>
                    </li>
                {/foreach}
            </ul>

            <p class="text-muted">
                {l s='The application never gets more rights than your own account. You can disconnect it at any time from the module settings.' d='Modules.Somcp.Admin'}
            </p>

            {if !$somcp_may_grant}
                <div class="alert alert-warning">
                    {if !$somcp_on_this_shop}
                        {$somcp_shop_warning|escape:'html':'UTF-8'}
                    {else}
                        {$somcp_profile_warning|escape:'html':'UTF-8'}
                    {/if}
                </div>
            {/if}

            <form method="post" action="{$somcp_form_action|escape:'html':'UTF-8'}">
                <input type="hidden" name="submitSomcpConsent" value="1">
                <input type="hidden" name="somcp_csrf" value="{$somcp_csrf|escape:'html':'UTF-8'}">
                <div class="somcp-actions">
                    <button type="submit" name="decision" value="deny" class="btn btn-default">
                        {l s='Refuse' d='Modules.Somcp.Admin'}
                    </button>
                    <button type="submit" name="decision" value="allow" class="btn btn-primary"{if !$somcp_may_grant} disabled{/if}>
                        {l s='Allow the connection' d='Modules.Somcp.Admin'}
                    </button>
                </div>
            </form>
        {/if}
    </div>
</div>
