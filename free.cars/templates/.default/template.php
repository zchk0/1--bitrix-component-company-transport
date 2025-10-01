<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

$this->setFrameMode(false);
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
$json  = json_encode($arResult, $flags);
if ($json === false) {
    $json = json_encode(['error' => 'JSON encode failed'], $flags);
}
?>
<pre style="white-space: pre-wrap; word-wrap: break-word; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; line-height: 1.4; border: 1px solid #e3e3e3; border-radius: 6px; padding: 10px; background: #fafafa;">
<?= htmlspecialcharsbx($json) ?>
</pre>
