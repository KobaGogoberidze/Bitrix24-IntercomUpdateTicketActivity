<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

$arActivityDescription = [
    "NAME" => Loc::getMessage("INTERCOM_UT_NAME"),
    "DESCRIPTION" => Loc::getMessage("INTERCOM_UT_DESCRIPTION"),
    "TYPE" => "activity",
    "CLASS" => "IntercomUpdateTicketActivity",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => [
        "OWN_ID" => "intercom",
        "OWN_NAME" => "Intercom",
    ]
];
