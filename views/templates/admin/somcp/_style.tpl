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

{* Inline rather than a media file, for the same reason as the script: the
   settings page is rendered by Symfony on PrestaShop 9, where the controller
   addCSS() would need is a proxy without that method. The stylesheet used to
   sit in views/css and was only ever loaded by the consent screen, so none of
   these rules applied to the settings page at all. *}
<style type="text/css">
/* Page frame, after PrestaShop's own modules of the 9.x generation: a title
   and a two-line promise, tabs underneath, and white cards on the back office
   grey instead of the old stacked panels. The merchant sees at a glance where
   each setting lives, and the page reads as part of the back office. */
.somcp .somcp-head {
    margin: 4px 0 18px;
}

.somcp .somcp-title {
    margin: 0 0 8px;
    font-size: 24px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .01em;
    color: #363a41;
}

.somcp .somcp-lead {
    max-width: 820px;
    margin: 0;
    color: #6c868e;
    font-size: 14px;
}

.somcp .somcp-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 24px;
    margin: 0 0 20px;
    padding: 0;
    list-style: none;
    border-bottom: 1px solid #dfe3e8;
}

.somcp .somcp-tabs[hidden] {
    display: none;
}

.somcp .somcp-tabs a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 0;
    margin-bottom: -1px;
    border-bottom: 3px solid transparent;
    color: #6c868e;
    font-weight: 600;
    text-decoration: none;
}

.somcp .somcp-tabs a:hover,
.somcp .somcp-tabs a:focus {
    color: #363a41;
    text-decoration: none;
}

.somcp .somcp-tabs a.is-active {
    color: #363a41;
    border-bottom-color: #363a41;
}

/* How many assistants are connected, on the tab itself: the one number a
   merchant opening the page may want before anything else. */
.somcp .somcp-count {
    display: inline-block;
    min-width: 20px;
    padding: 1px 6px;
    border-radius: 10px;
    background: #eef1f4;
    color: #363a41;
    font-size: 11px;
    text-align: center;
}

.somcp .somcp-pane[hidden] {
    display: none;
}

.somcp .somcp-card {
    background: #fff;
    border: 1px solid #dfe3e8;
    border-radius: 6px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
    padding: 20px 24px;
    margin-bottom: 18px;
}

.somcp .somcp-card h3 {
    margin: 0 0 8px;
    font-size: 16px;
    font-weight: 600;
    color: #363a41;
    text-transform: none;
}

.somcp .somcp-card h4 {
    margin: 18px 0 4px;
    font-size: 13px;
    font-weight: 600;
    color: #363a41;
}

/* The server switch: the text on the left, the switch on the right, the way
   every on/off setting of the 9.x back office is laid out. It undoes the
   form-group margins it carries for the inheritance script. */
.somcp .somcp-card-switch {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-left: 0;
    margin-right: 0;
}

.somcp .somcp-card-switch p {
    margin: 0;
    color: #6c868e;
}

.somcp .somcp-card-switch .switch {
    flex: 0 0 auto;
}

.somcp .somcp-card-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid #eef1f4;
}

/* Feedback sits apart from the settings, on the grey of a side note. */
.somcp .somcp-card-muted {
    background: #f8f9fb;
    box-shadow: none;
}

.somcp .somcp-card-muted p {
    color: #6c868e;
}

.somcp .somcp-card-muted .btn-link {
    margin-left: 8px;
}

.somcp .somcp-foot {
    margin: 6px 0 0;
    text-align: center;
    color: #98a3b0;
    font-size: 12px;
}

/* Address block. The address is the object of this block, so it leads the line
   in a fixed-width face; the shop name sits above it as a quiet heading, and
   the copy button ends the line. Everything on one row per shop, because a
   merchant scans this list looking for one shop and copies one address. */
.somcp .somcp-endpoints {
    border: 1px solid #dfe3e8;
    border-radius: 4px;
    background: #fff;
}

.somcp .somcp-endpoint + .somcp-endpoint {
    border-top: 1px solid #eef1f4;
}

.somcp .somcp-endpoint {
    padding: 14px 16px;
}

.somcp .somcp-endpoint:hover {
    background: #fafbfc;
}

.somcp .somcp-endpoint-shop {
    text-transform: uppercase;
    letter-spacing: .06em;
    font-size: 11px;
    font-weight: 700;
    color: #6c7a89;
    margin-bottom: 6px;
}

.somcp .somcp-badge {
    background: #25b9d7;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 400;
    margin-left: 6px;
}

.somcp .somcp-endpoint-url {
    display: flex;
    align-items: center;
    gap: 12px;
}

.somcp .somcp-endpoint-url input {
    flex: 1 1 auto;
    min-width: 0;
    border: 0;
    background: transparent;
    padding: 0;
    font-family: "SFMono-Regular", Menlo, Consolas, monospace;
    font-size: 13px;
    color: #363a41;
    cursor: text;
}

.somcp .somcp-endpoint-url input:focus {
    outline: none;
}

.somcp .somcp-endpoint-url .btn {
    flex: 0 0 auto;
}

/* The diagnostic bar: shop picker and button on one line, so the merchant
   reads "run this, on that shop" as a single sentence. */
/* The shop picker and its button sit right under the results of the last run,
   and the table's own bottom margin is not enough to keep them from reading as
   one more row of it. */
.somcp .somcp-diag-run {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 18px;
}

.somcp .somcp-diagnostic {
    margin-bottom: 0;
}

.somcp .somcp-note {
    margin-top: 10px;
    color: #7a7a7a;
    font-size: 12px;
}

.somcp .somcp-diagnostic th {
    white-space: nowrap;
    width: 1%;
}

.somcp .somcp-status {
    width: 1%;
    font-size: 16px;
    vertical-align: middle;
}

.somcp .somcp-status-ok {
    color: #3ca63c;
}

.somcp .somcp-status-warning {
    color: #ff9a52;
}

.somcp .somcp-status-error {
    color: #e08f95;
}

.somcp .somcp-log code {
    white-space: nowrap;
}

.somcp .somcp-log-failure td {
    background: #fdf3f4;
}

/* Consent screen: narrow column, because the merchant has one decision to make
   and a full-width back office table buries it. */
.somcp-consent {
    max-width: 680px;
    margin: 0 auto;
}

.somcp-consent .somcp-lead {
    font-size: 15px;
}

.somcp-consent .somcp-facts th {
    width: 35%;
    white-space: nowrap;
}

.somcp-consent .somcp-scopes {
    margin-bottom: 16px;
}

.somcp-consent .somcp-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 0;
}

/* Walking away belongs at the other end of the row. Put next to "send", it is
   one slip away from the button that does the opposite, and on this page the
   slip costs the merchant the files they just chose. */
.somcp-consent .somcp-actions-split {
    justify-content: space-between;
}

.somcp-consent .somcp-actions-go {
    display: inline-flex;
    gap: 8px;
}

/* The deposit area. A merchant arriving here has been sent by an assistant and
   is not looking for a form to fill in - they want to drop what they were
   asked for and go back. So the whole rectangle is the target, the file input
   itself is hidden behind it, and dragging works as well as clicking. */
.somcp-consent .somcp-drop {
    display: block;
    margin-top: 18px;
    border: 2px dashed #bfc9d4;
    border-radius: 6px;
    background: #fafbfc;
    padding: 28px 16px;
    text-align: center;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}

.somcp-consent .somcp-drop:hover,
.somcp-consent .somcp-drop.is-over {
    border-color: #25b9d7;
    background: #f2fbfd;
}

.somcp-consent .somcp-drop input[type="file"] {
    display: none;
}

/* Both are spans so the whole label stays one click target, so each has to be
   told to take its own line. */
.somcp-consent .somcp-drop-title {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
}

.somcp-consent .somcp-drop-hint {
    display: block;
    color: #6c7a89;
    font-size: 12px;
}

/* The names of what is about to be sent, listed under the target so the
   merchant sees their own choice before pressing anything. */
.somcp-consent .somcp-chosen {
    margin: 10px 0 0;
    padding: 0;
    list-style: none;
    font-size: 13px;
}

.somcp-consent .somcp-chosen li {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 3px 0;
    color: #363a41;
}

/* Dropping one file from the pile before sending. Small, quiet, and only
   meaningful next to the name it belongs to. */
.somcp-consent .somcp-drop-one {
    border: 0;
    background: none;
    color: #98a3b0;
    font-size: 16px;
    line-height: 1;
    padding: 0 2px;
    cursor: pointer;
}

.somcp-consent .somcp-drop-one:hover {
    color: #c0392b;
}

/* A file the page turned away before sending anything. Said in place, next to
   its own name, rather than as one message above the list. */
.somcp-consent .somcp-chosen li.somcp-rejected {
    color: #c0392b;
}

.somcp-consent .btn[disabled] {
    opacity: .5;
    cursor: default;
}

/* Every list on these two pages ends the same way. PrestaShop draws a rule
   above each row and none below the last, so a table stopped in mid-air and
   whatever came next - a button, a form - sat against its final line and read
   as one more row of it. Close the table, then leave the same gap the tool
   paging already leaves under its own. */
.somcp .table,
.somcp-consent .table {
    border-bottom: 1px solid #eef1f4;
}

.somcp .table + form,
.somcp .table + .somcp-actions,
.somcp-consent .table + form,
.somcp-consent .table + p,
.somcp-consent .table + h4,
.somcp-consent .table + .alert {
    margin-top: 18px;
}

/* Inheritance control: deliberately quiet. It sits under every setting on a
   multistore shop, so it must read as a note, not compete with the field. */
.somcp .somcp-inherit {
    margin-top: 6px;
    font-size: 12px;
}

.somcp .somcp-inherit label {
    font-weight: normal;
    margin: 0 8px 0 0;
}

/* The list of tools, paged five at a time rather than scrolled: a shop with a
   few modules would otherwise have a list long enough to push the Save button
   off the screen. The wrapper stays for the horizontal overflow on a narrow
   screen - and the table keeps its own display, because display:block on a
   table makes it shrink to its content instead of filling the column. */
.somcp .somcp-tools-scroll {
    margin-top: 10px;
    overflow-x: auto;
}

.somcp .somcp-tools {
    width: 100%;
    margin-bottom: 0;
}

.somcp .somcp-tools code {
    font-size: 11px;
}

.somcp .somcp-tools td,
.somcp .somcp-tools th {
    vertical-align: middle;
}

/* The switch column takes what a checkbox needs and no more, so the width goes
   to the tool name, which is the longest thing in the row. */
.somcp .somcp-tools .somcp-tool-on {
    width: 48px;
    text-align: center;
}

/* Paging, under the list. Hidden until the script takes over, so a page
   without JavaScript shows the whole list rather than two dead buttons. */
.somcp .somcp-tools-nav {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    /* The same gap the diagnostic leaves under its own table: buttons sitting
       against the last row read as one more row of it. */
    margin-top: 18px;
}

.somcp .somcp-tools-nav[hidden] {
    display: none;
}

.somcp .somcp-tools-count {
    color: #6c868e;
    font-size: 12px;
}

/* A tool that changes something, marked where the merchant decides whether to
   offer it at all. */
.somcp .somcp-writes {
    background: #fbc02d;
    color: #333;
}
</style>
