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

<div class="somcp">

    {foreach from=$somcp_confirmations item=confirmation}
        <div class="alert alert-success">{$confirmation|escape:'html':'UTF-8'}</div>
    {/foreach}
    {foreach from=$somcp_errors item=error}
        <div class="alert alert-danger">{$error|escape:'html':'UTF-8'}</div>
    {/foreach}

    <div class="panel">
        <div class="panel-heading"><i class="icon-link"></i> {l s='Address to give to your assistant' d='Modules.Somcp.Admin'}</div>
        <p>{l s='Paste this address into Claude, ChatGPT, Cursor or any other assistant that speaks MCP. It will ask you to sign in to this shop and to approve the connection.' d='Modules.Somcp.Admin'}</p>
        <div class="somcp-endpoints">
            {foreach from=$somcp_endpoints item=endpoint}
                <div class="somcp-endpoint">
                    {if $somcp_multistore}
                        <div class="somcp-endpoint-shop">
                            {$endpoint.name|escape:'html':'UTF-8'}
                            {if $endpoint.current}
                                <span class="badge somcp-badge">{l s='current shop' d='Modules.Somcp.Admin'}</span>
                            {/if}
                        </div>
                    {/if}
                    <div class="somcp-endpoint-url">
                        <input type="text" id="somcp-endpoint-{$endpoint.id|intval}" readonly
                               value="{$endpoint.url|escape:'html':'UTF-8'}">
                        <button type="button" class="btn btn-default btn-sm"
                                data-somcp-copy="somcp-endpoint-{$endpoint.id|intval}">
                            <i class="icon-copy"></i> {l s='Copy' d='Modules.Somcp.Admin'}
                        </button>
                    </div>
                </div>
            {/foreach}
        </div>

        <p class="somcp-note">
            {if $somcp_multistore}
                {l s='Each shop has its own address and its own connections. An assistant connected to one shop reaches that shop only.' d='Modules.Somcp.Admin'}
            {else}
                {l s='Authorization server:' d='Modules.Somcp.Admin'} <code>{$somcp_issuer|escape:'html':'UTF-8'}</code>
            {/if}
        </p>
    </div>

    <div class="panel">
        <div class="panel-heading"><i class="icon-stethoscope"></i> {l s='Diagnostic' d='Modules.Somcp.Admin'}</div>
        <p>{l s='Checks one shop the way an assistant would: it calls that shop own addresses from the outside and reports what comes back.' d='Modules.Somcp.Admin'}</p>

        {if $somcp_diagnostic}
            <table class="table somcp-diagnostic">
                <tbody>
                {foreach from=$somcp_diagnostic item=check}
                    <tr>
                        <td class="somcp-status somcp-status-{$check.status|escape:'html':'UTF-8'}">
                            {if $check.status === 'ok'}<i class="icon-check"></i>
                            {elseif $check.status === 'warning'}<i class="icon-warning"></i>
                            {else}<i class="icon-remove"></i>{/if}
                        </td>
                        <th>{$check.label|escape:'html':'UTF-8'}</th>
                        <td>
                            {$check.detail|escape:'html':'UTF-8'}
                            {if $check.url}<br><code>{$check.url|escape:'html':'UTF-8'}</code>{/if}
                        </td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        {/if}

        <form method="post" action="{$somcp_form_action|escape:'html':'UTF-8'}" class="somcp-diag-run">
            {if $somcp_diag_pick}
                <select name="somcp_diag_shop" class="form-control fixed-width-xl">
                    {foreach from=$somcp_diag_shops item=candidate}
                        <option value="{$candidate.id|intval}"{if $candidate.id == $somcp_diag_shop} selected{/if}>
                            {$candidate.name|escape:'html':'UTF-8'}
                        </option>
                    {/foreach}
                </select>
            {/if}
            <button type="submit" name="submitSomcpDiagnostic" value="1" class="btn btn-default">
                {l s='Run the diagnostic' d='Modules.Somcp.Admin'}
            </button>
        </form>
    </div>

    <form method="post" action="{$somcp_form_action|escape:'html':'UTF-8'}" class="defaultForm form-horizontal">
        <div class="panel">
            <div class="panel-heading"><i class="icon-cogs"></i> {l s='Settings' d='Modules.Somcp.Admin'}</div>

            <h4>{l s='MCP server' d='Modules.Somcp.Admin'}</h4>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Enable the MCP server' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_ENABLED'}
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="SOMCP_ENABLED" id="SOMCP_ENABLED_on" value="1"{if $somcp_settings.SOMCP_ENABLED === '1'} checked{/if}>
                        <label for="SOMCP_ENABLED_on">{l s='Yes' d='Modules.Somcp.Admin'}</label>
                        <input type="radio" name="SOMCP_ENABLED" id="SOMCP_ENABLED_off" value="0"{if $somcp_settings.SOMCP_ENABLED !== '1'} checked{/if}>
                        <label for="SOMCP_ENABLED_off">{l s='No' d='Modules.Somcp.Admin'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">{l s='Turning this off closes the endpoint immediately. Tokens already issued stop working.' d='Modules.Somcp.Admin'}</p>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Employees allowed to connect an assistant' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_REQUIRED_PROFILE'}
                    <select name="SOMCP_REQUIRED_PROFILE" class="form-control fixed-width-xl">
                        <option value="0"{if $somcp_settings.SOMCP_REQUIRED_PROFILE === '0'} selected{/if}>
                            {l s='Any employee' d='Modules.Somcp.Admin'}
                        </option>
                        {foreach from=$somcp_profiles item=profile}
                            <option value="{$profile.id_profile|intval}"{if $somcp_settings.SOMCP_REQUIRED_PROFILE == $profile.id_profile} selected{/if}>
                                {$profile.name|escape:'html':'UTF-8'}
                            </option>
                        {/foreach}
                    </select>
                    <p class="help-block">{l s='An assistant never gets more rights than the employee who connected it.' d='Modules.Somcp.Admin'}</p>
                </div>
            </div>

            <hr>
            <h4>{l s='OAuth 2.1' d='Modules.Somcp.Admin'}</h4>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Accept new connections' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_OAUTH_ENABLED'}
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="SOMCP_OAUTH_ENABLED" id="SOMCP_OAUTH_ENABLED_on" value="1"{if $somcp_settings.SOMCP_OAUTH_ENABLED === '1'} checked{/if}>
                        <label for="SOMCP_OAUTH_ENABLED_on">{l s='Yes' d='Modules.Somcp.Admin'}</label>
                        <input type="radio" name="SOMCP_OAUTH_ENABLED" id="SOMCP_OAUTH_ENABLED_off" value="0"{if $somcp_settings.SOMCP_OAUTH_ENABLED !== '1'} checked{/if}>
                        <label for="SOMCP_OAUTH_ENABLED_off">{l s='No' d='Modules.Somcp.Admin'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Access token lifetime (seconds)' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_ACCESS_TTL'}
                    <input type="number" min="60" name="SOMCP_ACCESS_TTL" class="form-control fixed-width-xl" value="{$somcp_settings.SOMCP_ACCESS_TTL|escape:'html':'UTF-8'}">
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Refresh token lifetime (seconds)' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_REFRESH_TTL'}
                    <input type="number" min="600" name="SOMCP_REFRESH_TTL" class="form-control fixed-width-xl" value="{$somcp_settings.SOMCP_REFRESH_TTL|escape:'html':'UTF-8'}">
                    <p class="help-block">{l s='How long an assistant can keep reconnecting on its own before the merchant has to approve it again.' d='Modules.Somcp.Admin'}</p>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Replace the refresh token on every use' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_REFRESH_ROTATION'}
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="SOMCP_REFRESH_ROTATION" id="SOMCP_REFRESH_ROTATION_on" value="1"{if $somcp_settings.SOMCP_REFRESH_ROTATION === '1'} checked{/if}>
                        <label for="SOMCP_REFRESH_ROTATION_on">{l s='Yes' d='Modules.Somcp.Admin'}</label>
                        <input type="radio" name="SOMCP_REFRESH_ROTATION" id="SOMCP_REFRESH_ROTATION_off" value="0"{if $somcp_settings.SOMCP_REFRESH_ROTATION !== '1'} checked{/if}>
                        <label for="SOMCP_REFRESH_ROTATION_off">{l s='No' d='Modules.Somcp.Admin'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">{l s='Recommended. A token that is used twice then reveals a theft, and the shop cuts the whole connection.' d='Modules.Somcp.Admin'}</p>
                </div>
            </div>

            <hr>
            <h4>{l s='Security' d='Modules.Somcp.Admin'}</h4>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Let applications identify themselves by address' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_CIMD_ENABLED'}
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="SOMCP_CIMD_ENABLED" id="SOMCP_CIMD_ENABLED_on" value="1"{if $somcp_settings.SOMCP_CIMD_ENABLED === '1'} checked{/if}>
                        <label for="SOMCP_CIMD_ENABLED_on">{l s='Yes' d='Modules.Somcp.Admin'}</label>
                        <input type="radio" name="SOMCP_CIMD_ENABLED" id="SOMCP_CIMD_ENABLED_off" value="0"{if $somcp_settings.SOMCP_CIMD_ENABLED !== '1'} checked{/if}>
                        <label for="SOMCP_CIMD_ENABLED_off">{l s='No' d='Modules.Somcp.Admin'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">{l s='This is how the Claude assistants connect. Turning it off will stop them from working.' d='Modules.Somcp.Admin'}</p>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Let applications register themselves' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_DCR_ENABLED'}
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="SOMCP_DCR_ENABLED" id="SOMCP_DCR_ENABLED_on" value="1"{if $somcp_settings.SOMCP_DCR_ENABLED === '1'} checked{/if}>
                        <label for="SOMCP_DCR_ENABLED_on">{l s='Yes' d='Modules.Somcp.Admin'}</label>
                        <input type="radio" name="SOMCP_DCR_ENABLED" id="SOMCP_DCR_ENABLED_off" value="0"{if $somcp_settings.SOMCP_DCR_ENABLED !== '1'} checked{/if}>
                        <label for="SOMCP_DCR_ENABLED_off">{l s='No' d='Modules.Somcp.Admin'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">{l s='Older way of connecting, kept for assistants that know nothing else. Registering costs nothing on its own: an application still has to be approved by an employee before it can do anything.' d='Modules.Somcp.Admin'}</p>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Only allow these return addresses' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_REDIRECT_HOSTS'}
                    <input type="text" name="SOMCP_REDIRECT_HOSTS" class="form-control" value="{$somcp_settings.SOMCP_REDIRECT_HOSTS|escape:'html':'UTF-8'}">
                    <p class="help-block">{l s='Host names separated by spaces, for example claude.ai chatgpt.com. Leave empty to accept any address an application registers. Desktop assistants use your own computer and are always allowed.' d='Modules.Somcp.Admin'}</p>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Keep a security log' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_LOG_ENABLED'}
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="SOMCP_LOG_ENABLED" id="SOMCP_LOG_ENABLED_on" value="1"{if $somcp_settings.SOMCP_LOG_ENABLED === '1'} checked{/if}>
                        <label for="SOMCP_LOG_ENABLED_on">{l s='Yes' d='Modules.Somcp.Admin'}</label>
                        <input type="radio" name="SOMCP_LOG_ENABLED" id="SOMCP_LOG_ENABLED_off" value="0"{if $somcp_settings.SOMCP_LOG_ENABLED !== '1'} checked{/if}>
                        <label for="SOMCP_LOG_ENABLED_off">{l s='No' d='Modules.Somcp.Admin'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">{l s='Connections, approvals and refusals, kept for thirty days. Never contains a token.' d='Modules.Somcp.Admin'}</p>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='How long an assistant keeps the list of tools' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_TOOLS_TTL'}
                    <input type="number" min="10" max="3600" name="SOMCP_TOOLS_TTL" class="form-control fixed-width-xl"
                           value="{$somcp_settings.SOMCP_TOOLS_TTL|escape:'html':'UTF-8'}">
                    <p class="help-block">{l s='In seconds, between 10 and 3600. This shop cannot tell an assistant that the list changed - there is no connection open to it - so the assistant re-reads only when this has run out. A tool switched on or off appears or disappears after that delay, not before. Lower it while you are adding tools, raise it back afterwards. 300 by default.' d='Modules.Somcp.Admin'}</p>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Available tools' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_TOOLS_OFF'}
                    <p class="help-block">{l s='What an assistant may ask this shop to do. A tool switched off here is offered to nobody, whatever permissions their account holds. Tools published by another module carry that module name in front of theirs.' d='Modules.Somcp.Admin'}</p>
                    {if $somcp_tools}
                        <div class="somcp-tools-scroll">
                        <table class="table somcp-tools">
                            <thead>
                            <tr>
                                <th class="somcp-tool-on">{l s='On' d='Modules.Somcp.Admin'}</th>
                                <th>{l s='Tool' d='Modules.Somcp.Admin'}</th>
                                <th>{l s='Published by' d='Modules.Somcp.Admin'}</th>
                                <th>{l s='Requires' d='Modules.Somcp.Admin'}</th>
                            </tr>
                            </thead>
                            <tbody>
                            {foreach from=$somcp_tools item=tool}
                                <tr>
                                    <td class="somcp-tool-on">
                                        <input type="checkbox" name="somcp_tool_on[{$tool.name|escape:'html':'UTF-8'}]"
                                               value="1"{if $tool.on} checked{/if}>
                                    </td>
                                    <td>
                                        <strong>{$tool.title|escape:'html':'UTF-8'}</strong>
                                        {if $tool.writes}
                                            <span class="badge somcp-writes">{l s='changes data' d='Modules.Somcp.Admin'}</span>
                                        {/if}
                                        <br><code>{$tool.name|escape:'html':'UTF-8'}</code>
                                    </td>
                                    <td>
                                        {if $tool.module}<code>{$tool.module|escape:'html':'UTF-8'}</code>
                                        {else}{l s='This module' d='Modules.Somcp.Admin'}{/if}
                                    </td>
                                    <td>
                                        {if $tool.permission}{$tool.permission|escape:'html':'UTF-8'}
                                        {else}<span class="text-muted">{l s='no particular permission' d='Modules.Somcp.Admin'}</span>{/if}
                                    </td>
                                </tr>
                            {/foreach}
                            </tbody>
                        </table>
                        </div>
                        {* Paged in the browser, with every row left in the form.
                           Paging on the server would post only the boxes on
                           screen, and saving would switch off every tool on the
                           other pages. *}
                        <div class="somcp-tools-nav" data-somcp-pager="5" hidden>
                            <button type="button" class="btn btn-default btn-sm" data-somcp-page="-1">
                                &laquo; {l s='Previous' d='Modules.Somcp.Admin'}
                            </button>
                            <span class="somcp-tools-count"
                                  data-somcp-range="{l s='Tools %from% to %to% of %total%' d='Modules.Somcp.Admin' js=1}"></span>
                            <button type="button" class="btn btn-default btn-sm" data-somcp-page="1">
                                {l s='Next' d='Modules.Somcp.Admin'} &raquo;
                            </button>
                        </div>
                    {else}
                        <p class="text-muted">{l s='No tool is available on this shop.' d='Modules.Somcp.Admin'}</p>
                    {/if}
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-4">{l s='Available prompts' d='Modules.Somcp.Admin'}</label>
                <div class="col-lg-8">
                    {include file='./_inherit.tpl' field='SOMCP_PROMPTS_OFF'}
                    <p class="help-block">{l s='A prompt is a guided task you start yourself from your assistant menu. It costs nothing until you pick it, then hands the assistant step-by-step instructions for using the tools. A prompt published by another module puts that module words into your assistant, which is why you can switch each one off here.' d='Modules.Somcp.Admin'}</p>
                    {if $somcp_prompts}
                        <div class="somcp-tools-scroll">
                        <table class="table somcp-tools">
                            <thead>
                            <tr>
                                <th class="somcp-tool-on">{l s='On' d='Modules.Somcp.Admin'}</th>
                                <th>{l s='Prompt' d='Modules.Somcp.Admin'}</th>
                                <th>{l s='Published by' d='Modules.Somcp.Admin'}</th>
                                <th>{l s='Asks for' d='Modules.Somcp.Admin'}</th>
                                <th>{l s='Requires' d='Modules.Somcp.Admin'}</th>
                            </tr>
                            </thead>
                            <tbody>
                            {foreach from=$somcp_prompts item=prompt}
                                <tr>
                                    <td class="somcp-tool-on">
                                        <input type="checkbox" name="somcp_prompt_on[{$prompt.name|escape:'html':'UTF-8'}]"
                                               value="1"{if $prompt.on} checked{/if}>
                                    </td>
                                    <td>
                                        <strong>{$prompt.title|escape:'html':'UTF-8'}</strong>
                                        <br><code>{$prompt.name|escape:'html':'UTF-8'}</code>
                                        <br><span class="text-muted">{$prompt.description|escape:'html':'UTF-8'}</span>
                                    </td>
                                    <td>
                                        {if $prompt.module}<code>{$prompt.module|escape:'html':'UTF-8'}</code>
                                        {else}{l s='This module' d='Modules.Somcp.Admin'}{/if}
                                    </td>
                                    <td>
                                        {if $prompt.asks}<code>{$prompt.asks|escape:'html':'UTF-8'}</code>
                                        {else}<span class="text-muted">{l s='nothing' d='Modules.Somcp.Admin'}</span>{/if}
                                    </td>
                                    <td>
                                        {if $prompt.permission}{$prompt.permission|escape:'html':'UTF-8'}
                                        {else}<span class="text-muted">{l s='no particular permission' d='Modules.Somcp.Admin'}</span>{/if}
                                    </td>
                                </tr>
                            {/foreach}
                            </tbody>
                        </table>
                        </div>
                        <div class="somcp-tools-nav" data-somcp-pager="5" hidden>
                            <button type="button" class="btn btn-default btn-sm" data-somcp-page="-1">
                                &laquo; {l s='Previous' d='Modules.Somcp.Admin'}
                            </button>
                            <span class="somcp-tools-count"
                                  data-somcp-range="{l s='Prompts %from% to %to% of %total%' d='Modules.Somcp.Admin' js=1}"></span>
                            <button type="button" class="btn btn-default btn-sm" data-somcp-page="1">
                                {l s='Next' d='Modules.Somcp.Admin'} &raquo;
                            </button>
                        </div>
                    {else}
                        <p class="text-muted">{l s='No prompt is available on this shop.' d='Modules.Somcp.Admin'}</p>
                    {/if}
                </div>
            </div>

            <div class="panel-footer">
                <button type="submit" name="submitSomcpSettings" value="1" class="btn btn-default pull-right">
                    <i class="process-icon-save"></i> {l s='Save' d='Modules.Somcp.Admin'}
                </button>
            </div>
        </div>
    </form>

    <div class="panel">
        <div class="panel-heading"><i class="icon-plug"></i> {l s='Connected assistants' d='Modules.Somcp.Admin'}</div>

        {if $somcp_consents}
            <table class="table">
                <thead>
                    <tr>
                        <th>{l s='Application' d='Modules.Somcp.Admin'}</th>
                        <th>{l s='Approved by' d='Modules.Somcp.Admin'}</th>
                        <th>{l s='Profile' d='Modules.Somcp.Admin'}</th>
                        {if $somcp_multistore}<th>{l s='Shop' d='Modules.Somcp.Admin'}</th>{/if}
                        <th>{l s='Permissions' d='Modules.Somcp.Admin'}</th>
                        <th>{l s='Since' d='Modules.Somcp.Admin'}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                {foreach from=$somcp_consents item=consent}
                    <tr>
                        <td>
                            {if $consent.client_name}{$consent.client_name|escape:'html':'UTF-8'}{else}{$consent.client_id|escape:'html':'UTF-8'}{/if}
                        </td>
                        <td>
                            {if $consent.firstname || $consent.lastname}
                                {$consent.firstname|escape:'html':'UTF-8'} {$consent.lastname|escape:'html':'UTF-8'}<br>
                            {/if}
                            <span class="text-muted">{$consent.email|escape:'html':'UTF-8'}</span>
                            {if isset($consent.employee_active) && !$consent.employee_active}
                                <br><span class="text-muted">{l s='account disabled' d='Modules.Somcp.Admin'}</span>
                            {/if}
                        </td>
                        <td>
                            {if $consent.profile_name}{$consent.profile_name|escape:'html':'UTF-8'}
                            {else}<span class="text-muted">{l s='unknown' d='Modules.Somcp.Admin'}</span>{/if}
                        </td>
                        {if $somcp_multistore}<td>{$consent.shop_name|escape:'html':'UTF-8'}</td>{/if}
                        <td><code>{$consent.scopes|escape:'html':'UTF-8'}</code></td>
                        <td>{$consent.date_add|escape:'html':'UTF-8'}</td>
                        <td class="text-right">
                            <form method="post" action="{$somcp_form_action|escape:'html':'UTF-8'}">
                                <input type="hidden" name="id_consent" value="{$consent.id_consent|intval}">
                                <button type="submit" name="submitSomcpWithdraw" value="1" class="btn btn-default btn-sm">
                                    {l s='Disconnect' d='Modules.Somcp.Admin'}
                                </button>
                            </form>
                        </td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        {else}
            <p class="text-muted">{l s='No assistant is connected to this shop yet.' d='Modules.Somcp.Admin'}</p>
        {/if}
    </div>

    <div class="panel">
        <div class="panel-heading"><i class="icon-list"></i> {l s='Security log' d='Modules.Somcp.Admin'}</div>

        {if $somcp_logs}
            <table class="table somcp-log">
                <thead>
                    <tr>
                        <th>{l s='Date' d='Modules.Somcp.Admin'}</th>
                        <th>{l s='Event' d='Modules.Somcp.Admin'}</th>
                        {if $somcp_multistore}<th>{l s='Shop' d='Modules.Somcp.Admin'}</th>{/if}
                        <th>{l s='Application' d='Modules.Somcp.Admin'}</th>
                        <th>{l s='Detail' d='Modules.Somcp.Admin'}</th>
                    </tr>
                </thead>
                <tbody>
                {foreach from=$somcp_logs item=line}
                    <tr class="somcp-log-{$line.outcome|escape:'html':'UTF-8'}">
                        <td>{$line.date_add|escape:'html':'UTF-8'}</td>
                        <td><code>{$line.event|escape:'html':'UTF-8'}</code></td>
                        {if $somcp_multistore}<td>{$line.shop_name|escape:'html':'UTF-8'}</td>{/if}
                        <td>{$line.client_id|escape:'html':'UTF-8'}</td>
                        <td>{$line.message|escape:'html':'UTF-8'}</td>
                    </tr>
                {/foreach}
                </tbody>
            </table>

            <form method="post" action="{$somcp_form_action|escape:'html':'UTF-8'}">
                <button type="submit" name="submitSomcpClearLog" value="1" class="btn btn-default">
                    {l s='Empty the log' d='Modules.Somcp.Admin'}
                </button>
            </form>
        {else}
            <p class="text-muted">{l s='Nothing logged yet.' d='Modules.Somcp.Admin'}</p>
        {/if}
    </div>

    {include file='./_copy.tpl'}

</div>
